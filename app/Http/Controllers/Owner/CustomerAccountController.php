<?php

namespace App\Http\Controllers\Owner;

use App\Actions\RecordCustomerPayment;
use App\Actions\RecordFlourDeposit;
use App\Actions\RecordFlourExchangeSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreFlourDepositRequest;
use App\Http\Requests\Owner\StoreFlourExchangeSaleRequest;
use App\Http\Requests\Owner\StorePaymentRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The actions on the customer page: flour deposit, bread against flour, payment. */
class CustomerAccountController extends Controller
{
    public function deposit(StoreFlourDepositRequest $request, Customer $customer, RecordFlourDeposit $action): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);

        $request->once(fn () => $action->handle($customer, $request->validated(), $request->user()));

        return back()->with('success', 'تم تسجيل استلام القمح وإضافته لرصيد العميل.');
    }

    public function flourSale(StoreFlourExchangeSaleRequest $request, Customer $customer, RecordFlourExchangeSale $action): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);

        $request->once(fn () => $action->handle($customer, $request->validated(), $request->user()));

        return back()->with('success', 'تم تسجيل تسليم الخبز مقابل رصيد القمح بنجاح.');
    }

    public function payment(StorePaymentRequest $request, Customer $customer, RecordCustomerPayment $action): RedirectResponse
    {
        $this->authorizeCustomer($request, $customer);

        $request->once(fn () => $action->handle($customer, $request->validated(), $request->user()));

        return back()->with('success', 'تم تسجيل الدفعة بمبلغ '.money($request->validated('amount')).'.');
    }

    private function authorizeCustomer(Request $request, Customer $customer): void
    {
        abort_unless($customer->bakery_id === $request->user()->bakery_id, 404);
    }
}
