<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** PDF and Excel downloads of the customer statements (9-ب item 5). */
class StatementExportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز السنابل',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 1.5,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id, 'name' => 'أبو محمد']);
    }

    /** 12.5 kg opening credit on 2026-10-15, then 20 kg of bread on 2026-11-20 (unpaid fee 30 شيكل) and a 10 شيكل transfer. */
    private function customer(User $owner): Customer
    {
        $this->travelTo('2026-10-15 09:00');
        $this->actingAs($owner)->post(route('panel.customers.store'), ['submission_id' => fake()->uuid(), 'name' => 'أحمد', 'mobile_number' => '0591111111', 'flour_balance_kg' => '12.5']);
        $customer = Customer::where('bakery_id', $owner->bakery_id)->latest('id')->first();
        $this->travelTo('2026-11-26 09:00');

        $this->actingAs($owner)->post(route('panel.customers.flour-sales.store', $customer), [
            'kg_amount' => '20', 'payment_status' => 'unpaid', 'sale_date' => '2026-11-20', 'submission_id' => fake()->uuid(),
        ]);
        $bank = $owner->bakery->banks()->create(['name' => 'جوال باي']);
        $this->actingAs($owner)->post(route('panel.customers.payments.store', $customer), [
            'amount' => '10', 'payment_date' => '2026-11-21', 'method' => 'transfer', 'from_bank_id' => $bank->id, 'to_bank_id' => $bank->id,
            'transfer_reference' => 'R-55', 'submission_id' => fake()->uuid(),
        ]);

        return $customer;
    }

    private function export(User $user, Customer $customer, string $ledger, string $format, array $range = [])
    {
        return $this->actingAs($user)->get(route('panel.customers.statement.export', [$customer, $ledger, $format, ...$range]));
    }

    private function sheet(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        return [$sheet, $sheet->toArray(null, false, false)];
    }

    public function test_the_flour_excel_matches_the_statement_on_screen(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        $response = $this->export($owner, $customer, 'flour', 'xlsx', ['from' => '2026-11-01', 'to' => '2026-11-26'])->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        [$sheet, $rows] = $this->sheet($response->streamedContent());
        $this->assertTrue($sheet->getRightToLeft());
        $this->assertSame(['المخبز', 'مخبز السنابل'], array_slice($rows[0], 0, 2));
        $this->assertSame(['الزبون', 'أحمد'], array_slice($rows[1], 0, 2));
        $this->assertSame(['رقم الجوال', '0591111111'], array_slice($rows[2], 0, 2));
        $this->assertSame(['الكشف', 'كشف الطحين'], array_slice($rows[3], 0, 2));
        $this->assertSame(['الفترة', 'من 01/11/2026 إلى 26/11/2026'], array_slice($rows[4], 0, 2));
        $this->assertSame('تاريخ الإصدار', $rows[5][0]);
        $this->assertSame(['رقم الحركة', 'التاريخ', 'البيان', 'عليه (كغ)', 'له (كغ)', 'الرصيد', 'سجّلها'], $rows[7]);
        // "رصيد سابق" = the opening credit before November, in words; then the bread; then the closing balance.
        $this->assertSame([null, '01/11/2026', 'رصيد سابق', null, null, 'له 12.5 كغ', null], $rows[8]);
        $this->assertSame(['20/11/2026', 'خبز مقابل طحين 20 كغ', 20.0, null, 'عليه 7.5 كغ', 'أبو محمد'], array_slice($rows[9], 1));
        $this->assertSame('الرصيد الختامي', $rows[10][2]);
        $this->assertSame('عليه 7.5 كغ', $rows[10][5]);
        $this->assertCount(11, $rows);
    }

    public function test_the_money_excel_has_the_transfer_column_and_signed_balances(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        [, $rows] = $this->sheet($this->export($owner, $customer, 'money', 'xlsx', ['from' => '', 'to' => ''])->streamedContent());

        $this->assertSame(['الفترة', 'كل الحركات'], array_slice($rows[4], 0, 2));
        $this->assertSame(['رقم الحركة', 'التاريخ', 'البيان', 'التحويل', 'عليه', 'له', 'الرصيد', 'سجّلها'], $rows[7]);
        $this->assertSame([null, null, 'رصيد سابق', null, null, null, 0.0, null], $rows[8]);
        $this->assertSame([30.0, null, 30.0], [$rows[9][4], $rows[9][5], $rows[9][6]]);
        $this->assertSame(["جوال باي ← جوال باي\nحوالة R-55", null, 10.0, 20.0], [$rows[10][3], $rows[10][4], $rows[10][5], $rows[10][6]]);
        $this->assertSame(['الرصيد الختامي', 20.0], [$rows[11][2], $rows[11][6]]);
    }

    public function test_the_pdf_downloads(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        foreach (['money', 'flour'] as $ledger) {
            $response = $this->export($owner, $customer, $ledger, 'pdf')->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringStartsWith('%PDF', $response->streamedContent());
        }
    }

    public function test_owner_cannot_export_another_bakerys_customer(): void
    {
        $customer = $this->customer($this->owner());

        $this->export($this->owner(), $customer, 'money', 'pdf')->assertNotFound();
        $this->export($this->owner(), $customer, 'flour', 'xlsx')->assertNotFound();
    }

    public function test_admin_cannot_export_and_unknown_formats_are_not_found(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->export($admin, $customer, 'money', 'pdf')->assertForbidden();
        $this->actingAs($owner)->get("/panel/customers/{$customer->id}/statement/money.csv")->assertNotFound();
        $this->actingAs($owner)->get("/panel/customers/{$customer->id}/statement/sales.pdf")->assertNotFound();
    }

    public function test_the_customer_page_offers_the_export(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        $this->actingAs($owner)->get(route('panel.customers.show', $customer))
            ->assertInertia(fn ($page) => $page->where('canExport', true));
    }
}
