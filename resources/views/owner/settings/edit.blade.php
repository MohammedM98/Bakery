<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">إعدادات أسعار الخبز</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <form method="POST" action="{{ route('panel.settings.update') }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="regular_price_per_kg" value="سعر الكيلو للبيع النقدي" />
                        <x-text-input id="regular_price_per_kg" name="regular_price_per_kg" type="number" step="0.01" min="0"
                                      class="block mt-1 w-full" :value="old('regular_price_per_kg', $bakery->regular_price_per_kg)" required />
                        <p class="text-xs text-gray-500 mt-1">السعر المستخدم عند بيع الخبز نقدًا للعملاء.</p>
                        <x-input-error :messages="$errors->get('regular_price_per_kg')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="flour_exchange_fee_per_kg" value="أجرة الكيلو مقابل رصيد القمح" />
                        <x-text-input id="flour_exchange_fee_per_kg" name="flour_exchange_fee_per_kg" type="number" step="0.01" min="0"
                                      class="block mt-1 w-full" :value="old('flour_exchange_fee_per_kg', $bakery->flour_exchange_fee_per_kg)" required />
                        <p class="text-xs text-gray-500 mt-1">السعر الخاص المستخدم عند تسليم خبز مقابل القمح الذي زوّد به العميل المخبز.</p>
                        <x-input-error :messages="$errors->get('flour_exchange_fee_per_kg')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">حفظ الأسعار</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
