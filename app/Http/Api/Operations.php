<?php

namespace App\Http\Api;

use App\Actions\CreateBakeryWithOwner;
use App\Actions\CreateCustomer;
use App\Actions\DeleteCustomer;
use App\Actions\MarkSalePaid;
use App\Actions\RecordAdjustment;
use App\Actions\RecordCustomerPayment;
use App\Actions\RecordFlourDeposit;
use App\Actions\RecordSale;
use App\Exceptions\ApiException;
use App\Http\Requests\Admin\RenewSubscriptionRequest;
use App\Http\Requests\Admin\StoreBakeryRequest;
use App\Http\Requests\Admin\UpdateBakeryRequest;
use App\Http\Requests\Admin\UpdateSubscriptionStatusRequest;
use App\Http\Requests\Owner\BankRequest;
use App\Http\Requests\Owner\CustomerRequest;
use App\Http\Requests\Owner\StaffRequest;
use App\Http\Requests\Owner\StoreAdjustmentRequest;
use App\Http\Requests\Owner\StoreFlourDepositRequest;
use App\Http\Requests\Owner\StoreFlourExchangeSaleRequest;
use App\Http\Requests\Owner\StorePaymentRequest;
use App\Http\Requests\Owner\StoreSaleRequest;
use App\Http\Requests\Owner\UpdateSettingsRequest;
use App\Http\Requests\Owner\UpdateStaffStatusRequest;
use App\Http\Resources\BakeryResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\MeResource;
use App\Http\Resources\MovementResource;
use App\Http\Resources\SaleResource;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\IdempotencyKey;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Every write the mobile API offers, online (one REST call) or replayed from the offline queue
 * (`/sync/push`). Each runs through the website's own Form Request (rules, Arabic messages,
 * authorize()), the same gate as its web route, and the same Action, in one transaction.
 *
 * Idempotency: an operation with a `client_uuid` is stored with its answer in that transaction.
 * The same user sending the same key again gets the stored answer and nothing is written twice;
 * the same key with a different operation or payload is refused (`idempotency_conflict`).
 */
class Operations
{
    /** type => [area, permission gate, Form Request, client_uuid required] */
    const TYPES = [
        'sale' => ['panel', 'record-sale', StoreSaleRequest::class, true],
        'flour_sale' => ['panel', 'record-flour-sale', StoreFlourExchangeSaleRequest::class, true],
        'deposit' => ['panel', 'record-deposit', StoreFlourDepositRequest::class, true],
        'payment' => ['panel', 'record-payment', StorePaymentRequest::class, true],
        'mark_paid' => ['panel', 'record-payment', null, true],
        'adjustment' => ['panel', 'record-adjustment', StoreAdjustmentRequest::class, true],
        'customer.create' => ['panel', 'create-customers', CustomerRequest::class, true],
        'customer.update' => ['panel', 'edit-customers', CustomerRequest::class, false],
        'customer.delete' => ['panel', 'delete-customers', null, false],
        'bank.create' => ['panel', 'manage-banks', BankRequest::class, true],
        'bank.update' => ['panel', 'manage-banks', BankRequest::class, false],
        'bank.delete' => ['panel', 'delete-banks', null, false],
        'settings.update' => ['panel', 'manage-settings', UpdateSettingsRequest::class, false],
        'staff.create' => ['panel', 'manage-users', StaffRequest::class, true],
        'staff.update' => ['panel', 'manage-users', StaffRequest::class, false],
        'staff.status' => ['panel', 'manage-users', UpdateStaffStatusRequest::class, false],
        'bakery.create' => ['admin', null, StoreBakeryRequest::class, true],
        'bakery.update' => ['admin', null, UpdateBakeryRequest::class, false],
        'bakery.renew' => ['admin', null, RenewSubscriptionRequest::class, true],
        'bakery.status' => ['admin', null, UpdateSubscriptionStatusRequest::class, false],
    ];

    /** What the app may queue offline and send through /sync/push (section 13); the rest is online only. */
    const OFFLINE = ['sale', 'flour_sale', 'deposit', 'payment', 'adjustment', 'customer.create'];

    /** Answered 201 Created; the rest 200. */
    const CREATES = ['sale', 'flour_sale', 'deposit', 'payment', 'adjustment', 'customer.create', 'bank.create', 'staff.create', 'bakery.create'];

    /** How far a device clock may run behind the server when its sale price is checked against the price history. */
    const CLOCK_SKEW_MINUTES = 5;

    /** An offline sale may use a price replaced at most this long before it: a device offline longer must sync first. */
    const STALE_PRICE_DAYS = 14;

    /**
     * @param  array<string, mixed>  $input  the payload, with `client_uuid` and the ids of the records it acts on
     * @return array{0: int, 1: array<string, mixed>, 2: bool} status, body, whether it is a replay of an earlier run
     */
    public function run(User $user, string $type, array $input, ?CarbonInterface $at = null): array
    {
        [$area, $permission, $form, $keyRequired] = self::TYPES[$type]
            ?? throw new ApiException('unknown_operation', 'نوع العملية غير معروف.', 422);

        $uuid = $this->clientUuid($input, $keyRequired);
        $hash = hash('sha256', json_encode([$type, Arr::except($input, 'client_uuid')]));

        if ($uuid && ($replay = $this->replay($user, $uuid, $type, $hash))) {
            return $replay;
        }

        $this->authorize($user, $area, $permission);
        $models = $this->resolve($user, $type, $input);
        $data = $form ? $this->validate($form, $type, $input, $models) : [];
        // Only a queued offline sale (pushed with its time) keeps the device's price; online calls use the current one.
        $price = $at && in_array($type, ['sale', 'flour_sale'], true) ? $this->devicePrice($user->bakery, $type, $input, $at) : null;

        try {
            return DB::transaction(function () use ($user, $type, $uuid, $hash, $models, $data, $price) {
                $key = $uuid ? IdempotencyKey::create([
                    'bakery_id' => $user->bakery_id, 'user_id' => $user->id, 'client_uuid' => $uuid,
                    'operation' => $type, 'request_hash' => $hash,
                ]) : null;

                $body = $this->handle($user, $type, $models, $data, $price);
                $status = in_array($type, self::CREATES, true) ? 201 : 200;
                $key?->update(['status' => $status, 'response' => $body]);

                return [$status, $body, false];
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same key committed first: answer with its result.
            return ($uuid ? $this->replay($user, $uuid, $type, $hash) : null) ?? throw $e;
        }
    }

    /** @param  array<string, mixed>  $input */
    private function clientUuid(array $input, bool $required): ?string
    {
        Validator::make($input, ['client_uuid' => [$required ? 'required' : 'nullable', 'uuid']], [], ['client_uuid' => 'معرّف العملية'])->validate();

        return isset($input['client_uuid']) ? strtolower($input['client_uuid']) : null;
    }

    /** @return array{0: int, 1: array<string, mixed>, 2: bool}|null */
    private function replay(User $user, string $uuid, string $type, string $hash): ?array
    {
        $key = IdempotencyKey::where('user_id', $user->id)->where('client_uuid', $uuid)->whereNotNull('status')->first();

        if (! $key) {
            return null;
        }

        if ($key->operation !== $type || ! hash_equals($key->request_hash, $hash)) {
            throw new ApiException('idempotency_conflict', 'معرّف العملية هذا استُخدم لعملية أخرى.', 409);
        }

        return [$key->status, $key->response, true];
    }

    /** The /panel and /admin route groups (section 5), then the permission's gate (9-ب item 7). */
    private function authorize(User $user, string $area, ?string $permission): void
    {
        if ($area === 'admin') {
            $user->isSuperAdmin() || throw ApiException::forbidden();

            return;
        }

        ($user->isBakeryOwner() || $user->isBakeryStaff()) || throw ApiException::forbidden();
        $user->bakery?->isSubscriptionActive() || throw ApiException::subscriptionExpired();
        Gate::forUser($user)->allows($permission) || throw ApiException::forbidden();
    }

    /**
     * The records the operation acts on, always looked up inside the user's bakery: another bakery's id is "not found".
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function resolve(User $user, string $type, array $input): array
    {
        $bakery = $user->bakery;
        $id = fn (string $key) => isset($input[$key]) && is_scalar($input[$key]) && ctype_digit((string) $input[$key]) ? (int) $input[$key] : null;

        return match ($type) {
            'flour_sale', 'deposit', 'payment', 'adjustment', 'customer.update', 'customer.delete' => ['customer' => $this->customer($bakery, $input)],
            'mark_paid' => ['sale' => $bakery->sales()->find($id('sale_id'))
                ?? throw new ApiException('sale_not_found', 'البيع غير موجود.', 404)],
            'bank.update', 'bank.delete' => ['bank' => $bakery->banks()->find($id('bank_id'))
                ?? throw new ApiException('bank_not_found', 'البنك غير موجود، ربما حُذف.', 404)],
            'staff.update', 'staff.status' => ['user' => $bakery->staff()->find($id('user_id'))
                ?? throw new ApiException('user_not_found', 'الموظف غير موجود.', 404)],
            'bakery.update', 'bakery.renew', 'bakery.status' => ['bakery' => Bakery::find($id('bakery_id'))
                ?? throw new ApiException('bakery_not_found', 'المخبز غير موجود.', 404)],
            default => [],
        };
    }

    /**
     * By server id, or by the client_uuid of the offline `customer.create` that made it (same bakery).
     *
     * @param  array<string, mixed>  $input
     */
    private function customer(Bakery $bakery, array $input): Customer
    {
        $id = $input['customer_id'] ?? null;

        if ($id === null && is_string($input['customer_client_uuid'] ?? null)) {
            $id = IdempotencyKey::where('bakery_id', $bakery->id)->where('operation', 'customer.create')
                ->where('client_uuid', strtolower($input['customer_client_uuid']))->whereNotNull('status')
                ->first()?->response['customer']['id'] ?? null;
        }

        return (is_scalar($id) && ctype_digit((string) $id) ? $bakery->customers()->find((int) $id) : null)
            ?? throw new ApiException('customer_not_found', 'الزبون غير موجود، ربما حُذف.', 404);
    }

    /**
     * Runs the website's Form Request on the payload: its rules, messages, attributes and authorize().
     *
     * @param  class-string<FormRequest>  $form
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $models
     * @return array<string, mixed>
     */
    private function validate(string $form, string $type, array $input, array $models): array
    {
        $request = $form::createFrom(request(), new $form);
        // Own input bags: createFrom shares the JSON bag with the real request, which must keep its own body.
        $request->setJson(new InputBag);
        $request->request->replace();
        $request->query->replace();
        // The website's duplicate check (submission_id) is replaced here by the durable client_uuid.
        $request->replace([...$input, 'submission_id' => $input['client_uuid'] ?? null]);
        $request->setMethod(str_ends_with($type, '.update') ? 'PUT' : 'POST');
        $request->setRouteResolver(fn () => new class($models)
        {
            /** @param  array<string, mixed>  $parameters */
            public function __construct(private array $parameters) {}

            public function parameter(string $name, mixed $default = null): mixed
            {
                return $this->parameters[$name] ?? $default;
            }
        });
        $request->setContainer(app())->setRedirector(app(Redirector::class));
        $request->validateResolved();

        return Arr::except($request->validated(), 'submission_id');
    }

    /**
     * Section 13: an offline sale is recorded at the price the device used, if the bakery really had that price
     * at the operation's time (client_created_at, with a little clock skew), or had it until at most
     * STALE_PRICE_DAYS before (a device offline across a price change). Without a price: the current one.
     *
     * @param  array<string, mixed>  $input
     */
    private function devicePrice(Bakery $bakery, string $type, array $input, CarbonInterface $at): ?string
    {
        if (($input['price_per_kg'] ?? null) === null) {
            return null;
        }

        Validator::make($input, ['price_per_kg' => ['numeric', 'decimal:0,2', 'min:0', 'max:999.99']], [], ['price_per_kg' => 'السعر'])->validate();

        $cents = Movement::cents($input['price_per_kg']);
        $column = $type === 'flour_sale' ? 'flour_exchange_fee_per_kg' : 'regular_price_per_kg';
        $at = $at->copy()->utc();
        $refuse = fn () => throw new ApiException('price_mismatch', 'السعر الذي استخدمه الجهاز لم يكن من أسعار المخبز في وقت العملية.', 422);

        // client_created_at comes from the device: older than the stale window, it could pick any past price.
        // And 0 only when it is the price now (a history row may hold 0 in its other column).
        if ($at->lt(now()->subDays(self::STALE_PRICE_DAYS)) || ($cents === 0 && Movement::cents($bakery->{$column}) !== 0)) {
            $refuse();
        }
        $history = DB::table('bakery_prices')->where('bakery_id', $bakery->id)
            ->where('effective_from', '<=', $at->copy()->addMinutes(self::CLOCK_SKEW_MINUTES)->toDateTimeString())
            ->orderBy('effective_from')->orderBy('id')
            ->get([$column, 'effective_from'])->values();
        $oldest = $at->copy()->subDays(self::STALE_PRICE_DAYS)->toDateTimeString();

        // Each price is in effect until the next row: accept it if that period reaches into the allowed window.
        $known = $history->contains(fn ($row, int $i) => Movement::cents($row->{$column}) === $cents
            && (! isset($history[$i + 1]) || $history[$i + 1]->effective_from > $oldest));

        return $known ? Movement::decimal($cents) : $refuse();
    }

    /**
     * @param  array<string, mixed>  $models
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handle(User $user, string $type, array $models, array $data, ?string $price): array
    {
        $bakery = $user->bakery;
        $customer = $models['customer'] ?? null;

        return match ($type) {
            'sale', 'flour_sale' => $this->saleResult(app(RecordSale::class)->handle(
                $bakery, $customer, $type === 'sale' ? Sale::TYPE_CASH : Sale::TYPE_FLOUR_EXCHANGE, $data, $user, $price,
            )),
            'deposit' => $this->ledgerResult(collect([app(RecordFlourDeposit::class)->handle($customer, $data, $user)]), $customer),
            'payment' => $this->ledgerResult(collect([app(RecordCustomerPayment::class)->handle($customer, $data, $user)]), $customer),
            'adjustment' => $this->ledgerResult(collect([app(RecordAdjustment::class)->handle($customer, $data, $user)]), $customer),
            'mark_paid' => $this->markPaid($models['sale'], $user),
            'customer.create' => $this->customerCreated(app(CreateCustomer::class)->handle($bakery, $data, $user)),
            'customer.update' => ['customer' => CustomerResource::make(tap($customer)->update([
                'name' => $data['name'], 'mobile_number' => $data['mobile_number'], 'notes' => $data['notes'] ?? null,
            ]))->resolve()],
            'customer.delete' => tap(['deleted' => true], fn () => app(DeleteCustomer::class)->handle($customer)),
            'bank.create' => ['bank' => $bakery->banks()->create($data)->only('id', 'name')],
            'bank.update' => ['bank' => tap($models['bank'])->update($data)->only('id', 'name')],
            'bank.delete' => tap(['deleted' => true], fn () => $models['bank']->delete()),
            'settings.update' => ['prices' => MeResource::prices(tap($bakery)->update($data))],
            'staff.create' => ['user' => $this->staff($bakery->users()->create([
                ...Arr::only($data, ['name', 'email', 'password', 'permissions']),
                'role' => User::ROLE_BAKERY_STAFF,
                'is_active' => true,
            ]))],
            'staff.update' => ['user' => $this->staff(tap($models['user'])->update([
                ...Arr::only($data, ['name', 'email', 'permissions']),
                ...(filled($data['password'] ?? null) ? ['password' => $data['password']] : []),
            ]))],
            'staff.status' => ['user' => $this->staff(tap($models['user'])->update(['is_active' => (bool) $data['active']]))],
            'bakery.create' => ['bakery' => BakeryResource::make(app(CreateBakeryWithOwner::class)->handle($data, $user)->load('owners:id,bakery_id,name,email'))->resolve()],
            'bakery.update' => ['bakery' => BakeryResource::make(tap($models['bakery'])->update($data))->resolve()],
            'bakery.renew' => ['bakery' => BakeryResource::make(tap($models['bakery'])->renew((int) $data['months']))->resolve()],
            'bakery.status' => ['bakery' => BakeryResource::make(tap($models['bakery'])->update(['subscription_status' => $data['status']]))->resolve()],
        };
    }

    /** @return array<string, mixed> */
    private function saleResult(Sale $sale): array
    {
        $sale->load('customer');

        return $this->ledgerResult(Movement::where('sale_id', $sale->id)->orderBy('number')->get(), $sale->customer, $sale);
    }

    /** @return array<string, mixed> */
    private function markPaid(Sale $sale, User $user): array
    {
        app(MarkSalePaid::class)->handle($sale, $user)
            ?? throw new ApiException('already_paid', 'هذه العملية مدفوعة مسبقًا.', 409);

        $sale = $sale->fresh('customer');

        return $this->ledgerResult(
            Movement::where('sale_id', $sale->id)->where('type', Movement::TYPE_PAYMENT)->orderByDesc('number')->limit(1)->get(),
            $sale->customer,
            $sale,
        );
    }

    /** @return array<string, mixed> */
    private function customerCreated(Customer $customer): array
    {
        return ['customer' => CustomerResource::make($customer->fresh())->resolve(), 'movements' => MovementResource::collection($customer->movements()->orderBy('number')->get())->resolve()];
    }

    /**
     * The movement numbers the server gave and the customer's balances after them.
     *
     * @param  Collection<int, Movement>  $movements
     * @return array<string, mixed>
     */
    private function ledgerResult(Collection $movements, ?Customer $customer, ?Sale $sale = null): array
    {
        return [
            'movements' => MovementResource::collection($movements)->resolve(),
            'customer' => $customer ? CustomerResource::make($customer->fresh())->resolve() : null,
            'sale' => $sale ? SaleResource::make($sale->fresh('customer'))->resolve() : null,
        ];
    }

    /** @return array<string, mixed> */
    private function staff(User $user): array
    {
        return [...$user->only('id', 'name', 'email', 'is_active'), 'permissions' => $user->permissions ?? []];
    }
}
