<div class="{{ $isModal ? 'p-6 max-h-[80vh] overflow-y-auto' : 'max-w-2xl mx-auto' }}">
    @if ($isModal)
        <h2 class="text-lg font-semibold text-gray-800 mb-5">تسجيل عملية بيع</h2>
    @else
        <div class="bg-white rounded-2xl shadow-sm p-6">
    @endif
        <form wire:submit="save" class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="buyer_name" value="اسم المشتري (اختياري)" />
                    <x-text-input id="buyer_name" type="text" class="block mt-1 w-full" wire:model="buyer_name" />
                    <x-input-error :messages="$errors->get('buyer_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="buyer_mobile" value="رقم جوال المشتري (اختياري)" />
                    <x-text-input id="buyer_mobile" type="text" class="block mt-1 w-full" wire:model="buyer_mobile" />
                    <x-input-error :messages="$errors->get('buyer_mobile')" class="mt-2" />
                </div>
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
                        {{ money($this->pricePerKg) }}
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        يُحدَّد تلقائيًا من
                        <a href="{{ route('panel.settings.edit') }}" class="text-violet-600 hover:underline">إعدادات الأسعار</a>.
                    </p>
                </div>
            </div>

            <div class="rounded-xl bg-violet-50 text-violet-800 px-4 py-3 text-sm flex items-center justify-between">
                <span>الإجمالي المتوقع</span>
                <span class="font-bold text-lg">{{ money($this->total) }}</span>
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
                @if ($isModal)
                    <button type="button" @click="$dispatch('close-modal', 'sale-create')" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</button>
                @else
                    <a href="{{ route('panel.sales.index') }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                @endif
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    حفظ العملية
                </button>
            </div>
        </form>
    @if (! $isModal)
        </div>
    @endif
</div>
