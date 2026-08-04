<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <form wire:submit="save" class="space-y-5">
            <div>
                <x-input-label for="name" value="اسم العميل" />
                <x-text-input id="name" type="text" class="block mt-1 w-full" wire:model="name" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="mobile_number" value="رقم الجوال" />
                <x-text-input id="mobile_number" type="text" class="block mt-1 w-full" wire:model="mobile_number" required />
                <x-input-error :messages="$errors->get('mobile_number')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="flour_balance_kg" value="رصيد قمح ابتدائي (كجم) — اختياري" />
                <x-text-input id="flour_balance_kg" type="number" step="0.01" min="0" class="block mt-1 w-full" wire:model="flour_balance_kg" />
                <x-input-error :messages="$errors->get('flour_balance_kg')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="notes" value="ملاحظات" />
                <textarea id="notes" wire:model="notes" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"></textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('panel.customers.index') }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">حفظ العميل</span>
                    <span wire:loading wire:target="save">جارٍ الحفظ...</span>
                </button>
            </div>
        </form>
    </div>
</div>
