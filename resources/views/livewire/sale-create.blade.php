<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <form wire:submit="save" class="space-y-5">
            <div>
                <x-input-label value="نوع العملية" />
                <div class="mt-2 flex gap-4">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="sale_type" class="text-violet-600 focus:ring-violet-500" wire:model.live="sale_type" value="cash">
                        <span>بيع نقدي</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="sale_type" class="text-violet-600 focus:ring-violet-500" wire:model.live="sale_type" value="flour_exchange">
                        <span>مقابل رصيد قمح</span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('sale_type')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="customer_id" value="العميل" />
                <select id="customer_id" wire:model="customer_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                    <option value="">— عميل نقدي بدون تسجيل —</option>
                    @foreach ($this->customers as $customer)
                        <option value="{{ $customer->id }}">
                            {{ $customer->name }} ({{ $customer->mobile_number }}) — رصيد {{ number_format($customer->flour_balance_kg, 2) }} كجم
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">يجب اختيار عميل عند التسليم مقابل رصيد القمح.</p>
                <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="kg_amount" value="الكمية (كجم)" />
                    <x-text-input id="kg_amount" type="number" step="0.01" min="0.01" class="block mt-1 w-full" wire:model.live.debounce.300ms="kg_amount" required />
                    <x-input-error :messages="$errors->get('kg_amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label value="سعر الكيلو" />
                    <div class="mt-1 flex items-center h-[42px] px-3 rounded-md border border-gray-200 bg-gray-50 text-gray-700">
                        {{ number_format($this->pricePerKg, 2) }}
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        يُحدَّد تلقائيًا من
                        <a href="{{ route('panel.settings.edit') }}" class="text-violet-600 hover:underline">إعدادات الأسعار</a>.
                    </p>
                </div>
            </div>

            <div class="rounded-xl bg-violet-50 text-violet-800 px-4 py-3 text-sm flex items-center justify-between">
                <span>الإجمالي المتوقع</span>
                <span class="font-bold text-lg">{{ number_format($this->total, 2) }}</span>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="sale_date" value="التاريخ" />
                    <x-text-input id="sale_date" type="date" class="block mt-1 w-full" wire:model="sale_date" required />
                    <x-input-error :messages="$errors->get('sale_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label value="حالة الدفع" />
                    <div class="mt-2 flex gap-4">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="payment_status" class="text-violet-600 focus:ring-violet-500" wire:model="payment_status" value="paid">
                            <span>مدفوع</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="payment_status" class="text-violet-600 focus:ring-violet-500" wire:model="payment_status" value="unpaid">
                            <span>غير مدفوع</span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('payment_status')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="notes" value="ملاحظات" />
                <textarea id="notes" wire:model="notes" rows="2" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"></textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('panel.sales.index') }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    حفظ العملية
                </button>
            </div>
        </form>
    </div>
</div>
