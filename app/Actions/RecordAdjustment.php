<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordAdjustment
{
    public function __construct(private RecordMovements $movements, private SettleSales $settle) {}

    /**
     * The only way to correct a balance: a new movement "عليه" or "له" with a mandatory reason,
     * optionally pointing at the movement it corrects. It is itself never edited or deleted.
     *
     * @param  array{ledger: string, direction: string, amount: string, reason: string, movement_date: string, corrects_number?: ?int}  $data
     */
    public function handle(Customer $customer, array $data, User $user): Movement
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            $customer = $customer->bakery->customers()->lockForUpdate()->findOrFail($customer->id);
            $corrects = isset($data['corrects_number'])
                ? $customer->movements()->where('number', $data['corrects_number'])->first()
                    ?? throw ValidationException::withMessages(['corrects_number' => 'لا توجد حركة بهذا الرقم في حساب هذا الزبون.'])
                : null;
            $cents = Movement::cents($data['amount']);

            $movement = $this->movements->handle($customer->bakery, $user, [[
                'customer_id' => $customer->id,
                'ledger' => $data['ledger'],
                'type' => Movement::TYPE_ADJUSTMENT,
                'amount_cents' => $data['direction'] === 'debit' ? $cents : -$cents,
                'description' => 'تسوية: '.$data['reason'].($corrects ? ' (تصحيح الحركة رقم '.$corrects->number.')' : ''),
                'corrects_movement_id' => $corrects?->id,
                'movement_date' => $data['movement_date'],
            ]])->first();

            if ($data['ledger'] === Movement::LEDGER_MONEY) {
                $this->settle->handle($customer);
            }

            return $movement;
        });
    }
}
