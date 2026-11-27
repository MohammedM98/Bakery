<?php

namespace Tests\Feature\Api;

use App\Actions\RecordCustomerPayment;
use App\Actions\RecordSale;
use App\Models\Bakery;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The app's read endpoints: the website's figures, only the needed fields, tenant isolation and permissions. */
class ReadTest extends TestCase
{
    use ApiHelpers, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->bakeryOwner();
        $customer = $this->customerOf($this->owner);
        app(RecordSale::class)->handle($this->owner->bakery, $customer, Sale::TYPE_FLOUR_EXCHANGE, [
            'kg_amount' => '20', 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => local_today()->toDateString(),
        ], $this->owner);
        app(RecordSale::class)->handle($this->owner->bakery, null, Sale::TYPE_CASH, [
            'kg_amount' => '4', 'payment_status' => Sale::STATUS_PAID, 'sale_date' => local_today()->toDateString(),
        ], $this->owner);
        app(RecordCustomerPayment::class)->handle($customer, ['amount' => '50', 'payment_date' => local_today()->toDateString()], $this->owner);
    }

    public function test_customers_list_has_both_balances_with_display_texts(): void
    {
        $this->customerOf($this->bakeryOwner(), 'زبون مخبز آخر');

        $this->as($this->owner)->api('GET', 'customers')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'أحمد')
            // 20 kg × 1.50 fee = 30 owed, 50 paid: 20 له.
            ->assertJsonPath('data.0.money_balance', '-20.00')
            ->assertJsonPath('data.0.money_balance_text', "\u{2066}-20.00\u{2069} شيكل")
            ->assertJsonPath('data.0.flour_balance', '20.00')
            ->assertJsonPath('data.0.flour_balance_text', 'عليه 20 كغ')
            ->assertJsonPath('meta.total', 1);

        $this->api('GET', 'customers?search=غير موجود')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_customer_detail_has_both_statements_and_the_payment_form_data(): void
    {
        $customer = $this->owner->bakery->customers()->first();
        $this->owner->bakery->banks()->create(['name' => 'بنك فلسطين']);

        $response = $this->as($this->owner)->api('GET', "customers/{$customer->id}?from=&to=")->assertOk()
            ->assertJsonPath('customer.money_balance', '-20.00')
            ->assertJsonPath('money_statement.opening', '0.00')
            ->assertJsonPath('money_statement.closing', '-20.00')
            ->assertJsonPath('money_statement.closing_text', "\u{2066}-20.00\u{2069} شيكل")
            ->assertJsonPath('money_statement.rows.0.debit_text', '30.00 شيكل')
            ->assertJsonPath('money_statement.rows.1.credit_text', '50.00 شيكل')
            ->assertJsonPath('money_statement.rows.1.transfer', 'نقدًا')
            ->assertJsonPath('flour_statement.rows.0.balance_text', 'عليه 20 كغ')
            ->assertJsonPath('flour_statement.rows.0.debit_text', '20 كغ')
            ->assertJsonPath('banks.0.name', 'بنك فلسطين')
            ->assertJsonPath('can_adjust', true)
            ->assertJsonCount(1, 'sales');

        $this->assertStringNotContainsString('-', $response->json('flour_statement.closing_text'));
    }

    public function test_customer_detail_hides_each_part_without_its_permission(): void
    {
        $customer = $this->owner->bakery->customers()->first();

        $this->as($this->staffOf($this->owner, ['view-customers']))->api('GET', "customers/{$customer->id}")->assertOk()
            ->assertJsonPath('money_statement', null)
            ->assertJsonPath('flour_statement', null)
            ->assertJsonPath('sales', null)
            ->assertJsonPath('unpaid_sales', [])
            ->assertJsonPath('banks', [])
            ->assertJsonPath('can_adjust', false);
    }

    public function test_another_bakerys_customer_is_not_found(): void
    {
        $other = $this->customerOf($this->bakeryOwner());

        $this->as($this->owner)->api('GET', "customers/{$other->id}")->assertStatus(404)->assertJsonPath('code', 'not_found');
    }

    public function test_dashboard_has_the_websites_figures_with_texts(): void
    {
        $this->as($this->owner)->api('GET', 'dashboard?period=day')->assertOk()
            ->assertJsonPath('bread.kg', '24.00')
            ->assertJsonPath('bread.kg_text', '24 كغ')
            ->assertJsonPath('takings.received', '70.00')
            ->assertJsonPath('takings.received_text', '70.00 شيكل')
            ->assertJsonCount(7, 'chart');

        $this->api('GET', 'dashboard?period=decade')->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    }

    public function test_sales_list_and_sales_log(): void
    {
        $this->as($this->owner)->api('GET', 'sales?type=flour_exchange')->assertOk()
            ->assertJsonCount(1, 'sales.data')
            ->assertJsonPath('sales.data.0.total_text', '30.00 شيكل')
            ->assertJsonPath('sales.data.0.payment_status', 'paid')
            ->assertJsonPath('filters.type', 'flour_exchange');

        $this->api('GET', 'sales-log?period=day')->assertOk()
            ->assertJsonPath('summary.bread_kg', '24.00')
            ->assertJsonPath('summary.money_received_text', '70.00 شيكل')
            ->assertJsonPath('summary.flour_spent_kg_text', '20 كغ')
            ->assertJsonPath('movements.total', 5)
            ->assertJsonPath('movements.data.0.direction_label', 'له');
    }

    public function test_banks_staff_and_settings(): void
    {
        $this->owner->bakery->banks()->create(['name' => 'جوال باي']);
        $this->staffOf($this->owner, ['view-banks']);
        $this->staffOf($this->bakeryOwner());

        $this->as($this->owner)->api('GET', 'banks')->assertOk()->assertJsonPath('banks.0.name', 'جوال باي')->assertJsonPath('can_delete', true);
        $this->api('GET', 'users')->assertOk()->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.permissions', ['view-banks'])
            ->assertJsonMissingPath('users.0.password');
        $this->api('GET', 'settings')->assertOk()->assertJsonPath('prices.flour_exchange_fee_per_kg', '1.50');
    }

    public function test_admin_lists_and_opens_bakeries(): void
    {
        $this->as($this->admin())->api('GET', 'admin/bakeries')->assertOk()
            ->assertJsonPath('data.0.name', 'مخبز النور')
            ->assertJsonPath('data.0.subscription.active', true)
            ->assertJsonPath('data.0.owner.email', $this->owner->email);

        $this->api('GET', "admin/bakeries/{$this->owner->bakery_id}")->assertOk()->assertJsonPath('bakery.subscription.status', Bakery::STATUS_ACTIVE);
        $this->api('GET', 'admin/bakeries/999')->assertStatus(404);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function readPermissions(): array
    {
        return [
            'dashboard' => ['dashboard', 'view-dashboard'],
            'customers' => ['customers', 'view-customers'],
            'customer' => ['customers/{id}', 'view-customers'],
            'sales' => ['sales', 'view-sales'],
            'sales log' => ['sales-log', 'view-sales-log'],
            'banks' => ['banks', 'view-banks'],
            'users' => ['users', 'manage-users'],
            'settings' => ['settings', 'manage-settings'],
        ];
    }

    #[DataProvider('readPermissions')]
    public function test_each_read_needs_its_permission(string $uri, string $permission): void
    {
        $uri = str_replace('{id}', (string) $this->owner->bakery->customers()->value('id'), $uri);
        $others = array_values(array_diff(User::permissionKeys(), [$permission]));

        $this->as($this->staffOf($this->owner, $others))->api('GET', $uri)->assertStatus(403)->assertJsonPath('code', 'forbidden');

        if (! in_array($permission, User::OWNER_ONLY, true)) {
            $this->as($this->staffOf($this->owner, [$permission]))->api('GET', $uri)->assertOk();
        }
    }
}
