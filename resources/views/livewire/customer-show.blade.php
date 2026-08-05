<div class="max-w-5xl mx-auto space-y-6">

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="text-sm text-gray-500 mb-1">رقم الجوال</div>
            <div class="text-lg font-semibold">{{ $customer->mobile_number }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="text-sm text-gray-500 mb-1">رصيد القمح الحالي</div>
            <div class="text-2xl font-bold text-violet-600">{{ number_format($customer->flour_balance_kg, 2) }} كجم</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="text-sm text-gray-500 mb-1">ملاحظات</div>
            <div class="text-sm">{{ $customer->notes ?: '—' }}</div>
        </div>
    </div>

    @if ($depositMessage)
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3" wire:key="deposit-flash">
            {{ $depositMessage }}
        </div>
    @endif

    @if ($saleMessage)
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3" wire:key="sale-flash">
            {{ $saleMessage }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-sm p-6">
            <h3 class="font-semibold mb-4">تسجيل استلام قمح جديد</h3>
            <form wire:submit="addDeposit" class="space-y-4">
                <div>
                    <x-input-label for="kg_amount" value="الكمية (كجم)" />
                    <x-text-input id="kg_amount" type="number" step="0.01" min="0.01" class="block mt-1 w-full" wire:model="kg_amount" required />
                    <x-input-error :messages="$errors->get('kg_amount')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="deposit_date" value="التاريخ" />
                    <x-text-input id="deposit_date" type="date" class="block mt-1 w-full" wire:model="deposit_date" required />
                    <x-input-error :messages="$errors->get('deposit_date')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="deposit_notes" value="ملاحظات" />
                    <x-text-input id="deposit_notes" type="text" class="block mt-1 w-full" wire:model="deposit_notes" />
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    إضافة للرصيد
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <h3 class="font-semibold mb-4">تسليم خبز مقابل رصيد القمح</h3>
            <form wire:submit="takeBreadForFlour" class="space-y-4">
                <div>
                    <x-input-label for="bread_kg_amount" value="الكمية (كجم)" />
                    <x-text-input id="bread_kg_amount" type="number" step="0.01" min="0.01" class="block mt-1 w-full" wire:model="bread_kg_amount" required />
                    <p class="text-xs text-gray-500 mt-1">الرصيد المتاح: {{ number_format($customer->flour_balance_kg, 2) }} كجم</p>
                    <x-input-error :messages="$errors->get('bread_kg_amount')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="bread_sale_date" value="التاريخ" />
                    <x-text-input id="bread_sale_date" type="date" class="block mt-1 w-full" wire:model="bread_sale_date" required />
                    <x-input-error :messages="$errors->get('bread_sale_date')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="حالة الدفع" />
                    <div class="mt-2 flex gap-4">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="bread_payment_status" class="text-violet-600 focus:ring-violet-500" wire:model="bread_payment_status" value="paid">
                            <span>مدفوع</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="bread_payment_status" class="text-violet-600 focus:ring-violet-500" wire:model="bread_payment_status" value="unpaid">
                            <span>غير مدفوع</span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('bread_payment_status')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="bread_notes" value="ملاحظات" />
                    <x-text-input id="bread_notes" type="text" class="block mt-1 w-full" wire:model="bread_notes" />
                </div>
                <button type="submit" wire:loading.attr="disabled" class="w-full px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    تسجيل التسليم
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 font-semibold">سجل استلام القمح</div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-6 py-2 text-right">التاريخ</th>
                        <th class="px-6 py-2 text-right">الكمية (كجم)</th>
                        <th class="px-6 py-2 text-right">ملاحظات</th>
                        <th class="px-6 py-2 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($flourDeposits as $deposit)
                        <tr wire:key="deposit-{{ $deposit->id }}">
                            <td class="px-6 py-2">{{ $deposit->deposit_date->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-2">{{ number_format($deposit->kg_amount, 2) }}</td>
                            <td class="px-6 py-2">{{ $deposit->notes ?: '—' }}</td>
                            <td class="px-6 py-2 text-left">
                                <button type="button" wire:click="deleteDeposit({{ $deposit->id }})" wire:confirm="حذف هذه العملية؟"
                                        class="text-red-600 hover:underline text-xs">
                                    حذف
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-400">لا يوجد سجل استلام قمح.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 font-semibold">سجل عمليات البيع</div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-6 py-2 text-right">التاريخ</th>
                        <th class="px-6 py-2 text-right">النوع</th>
                        <th class="px-6 py-2 text-right">الكمية (كجم)</th>
                        <th class="px-6 py-2 text-right">المبلغ</th>
                        <th class="px-6 py-2 text-right">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($sales as $sale)
                        <tr wire:key="sale-{{ $sale->id }}">
                            <td class="px-6 py-2">{{ $sale->sale_date->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-2">{{ $sale->isFlourExchange() ? 'مقابل قمح' : 'بيع نقدي' }}</td>
                            <td class="px-6 py-2">{{ number_format($sale->kg_amount, 2) }}</td>
                            <td class="px-6 py-2">{{ money($sale->total_amount) }}</td>
                            <td class="px-6 py-2">
                                <span class="{{ $sale->isPaid() ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $sale->isPaid() ? 'مدفوع' : 'غير مدفوع' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-400">لا يوجد سجل مبيعات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
