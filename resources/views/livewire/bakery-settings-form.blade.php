<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        @if ($message)
            <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 mb-5" wire:key="settings-flash">
                {{ $message }}
            </div>
        @endif

        <form wire:submit="save" class="space-y-5">
            <div>
                <x-input-label for="regular_price_per_kg" value="سعر الكيلو للبيع النقدي" />
                <x-text-input id="regular_price_per_kg" type="number" step="0.01" min="0" class="block mt-1 w-full" wire:model="regular_price_per_kg" required />
                <p class="text-xs text-gray-500 mt-1">السعر المستخدم عند بيع الخبز نقدًا للعملاء.</p>
                <x-input-error :messages="$errors->get('regular_price_per_kg')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="flour_exchange_fee_per_kg" value="أجرة الكيلو مقابل رصيد القمح" />
                <x-text-input id="flour_exchange_fee_per_kg" type="number" step="0.01" min="0" class="block mt-1 w-full" wire:model="flour_exchange_fee_per_kg" required />
                <p class="text-xs text-gray-500 mt-1">السعر الخاص المستخدم عند تسليم خبز مقابل القمح الذي زوّد به العميل المخبز.</p>
                <x-input-error :messages="$errors->get('flour_exchange_fee_per_kg')" class="mt-2" />
            </div>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    حفظ الأسعار
                </button>
            </div>
        </form>
    </div>
</div>
