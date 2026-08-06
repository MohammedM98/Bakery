<div class="{{ $isModal ? 'p-6 space-y-6' : 'max-w-2xl mx-auto space-y-6' }}">
    @if ($isModal)
        <h2 class="text-lg font-semibold text-gray-800">تعديل بيانات العميل</h2>
    @else
        <div class="bg-white rounded-2xl shadow-sm p-6">
    @endif

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
                <x-input-label for="notes" value="ملاحظات" />
                <textarea id="notes" wire:model="notes" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"></textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3">
                @if ($isModal)
                    <button type="button" @click="$dispatch('close-modal', 'customer-edit')" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</button>
                @else
                    <a href="{{ route('panel.customers.show', $customer) }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                @endif
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    حفظ التعديلات
                </button>
            </div>
        </form>

    @if (! $isModal)
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm p-6 border border-red-100">
        <h3 class="font-semibold text-red-700 mb-2">حذف العميل</h3>
        <p class="text-sm text-gray-600 mb-4">سيتم حذف العميل وجميع سجلاته (عمليات البيع واستلام القمح) نهائيًا. لا يمكن التراجع عن هذا الإجراء.</p>
        <button type="button" wire:click="confirmDelete"
                class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-medium hover:bg-red-700">
            حذف العميل نهائيًا
        </button>
    </div>

    <x-confirm-modal
        name="confirm-delete-customer"
        title="حذف العميل نهائيًا"
        message="سيتم حذف العميل وجميع سجلاته (عمليات البيع واستلام القمح) نهائيًا. لا يمكن التراجع عن هذا الإجراء."
        confirmLabel="حذف نهائيًا"
        confirmAction="delete"
    />
</div>
