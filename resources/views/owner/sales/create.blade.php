<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">تسجيل عملية بيع</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <form method="POST" action="{{ route('panel.sales.store') }}" class="space-y-5"
                      x-data="{
                          saleType: '{{ old('sale_type', 'cash') }}',
                          cashPrice: {{ $bakery->regular_price_per_kg ?? 0 }},
                          exchangePrice: {{ $bakery->flour_exchange_fee_per_kg ?? 0 }},
                          pricePerKg: {{ old('price_per_kg', $bakery->regular_price_per_kg ?? 0) }},
                          updatePrice() { this.pricePerKg = this.saleType === 'cash' ? this.cashPrice : this.exchangePrice; }
                      }">
                    @csrf

                    <div>
                        <x-input-label value="نوع العملية" />
                        <div class="mt-2 flex gap-4">
                            <label class="flex items-center gap-2">
                                <input type="radio" class="text-violet-600 focus:ring-violet-500" name="sale_type" value="cash" x-model="saleType" @change="updatePrice()" {{ old('sale_type', 'cash') === 'cash' ? 'checked' : '' }}>
                                <span>بيع نقدي</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" class="text-violet-600 focus:ring-violet-500" name="sale_type" value="flour_exchange" x-model="saleType" @change="updatePrice()" {{ old('sale_type') === 'flour_exchange' ? 'checked' : '' }}>
                                <span>مقابل رصيد قمح</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('sale_type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="customer_id" value="العميل" />
                        <select id="customer_id" name="customer_id" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                            <option value="">— عميل نقدي بدون تسجيل —</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ (old('customer_id', $selectedCustomer) == $customer->id) ? 'selected' : '' }}>
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
                            <x-text-input id="kg_amount" name="kg_amount" type="number" step="0.01" min="0.01" class="block mt-1 w-full" :value="old('kg_amount')" required />
                            <x-input-error :messages="$errors->get('kg_amount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="price_per_kg" value="سعر الكيلو" />
                            <input id="price_per_kg" name="price_per_kg" type="number" step="0.01" min="0" x-model="pricePerKg"
                                   class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500" required>
                            <x-input-error :messages="$errors->get('price_per_kg')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="sale_date" value="التاريخ" />
                            <x-text-input id="sale_date" name="sale_date" type="date" class="block mt-1 w-full" :value="old('sale_date', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('sale_date')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label value="حالة الدفع" />
                            <div class="mt-2 flex gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="radio" class="text-violet-600 focus:ring-violet-500" name="payment_status" value="paid" {{ old('payment_status', 'paid') === 'paid' ? 'checked' : '' }}>
                                    <span>مدفوع</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" class="text-violet-600 focus:ring-violet-500" name="payment_status" value="unpaid" {{ old('payment_status') === 'unpaid' ? 'checked' : '' }}>
                                    <span>غير مدفوع</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('payment_status')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" value="ملاحظات" />
                        <textarea id="notes" name="notes" rows="2" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('panel.sales.index') }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">حفظ العملية</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
