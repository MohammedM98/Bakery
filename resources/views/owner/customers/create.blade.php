<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">إضافة عميل جديد</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <form method="POST" action="{{ route('panel.customers.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="name" value="اسم العميل" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="mobile_number" value="رقم الجوال" />
                        <x-text-input id="mobile_number" name="mobile_number" type="text" class="block mt-1 w-full" :value="old('mobile_number')" required />
                        <x-input-error :messages="$errors->get('mobile_number')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="flour_balance_kg" value="رصيد قمح ابتدائي (كجم) — اختياري" />
                        <x-text-input id="flour_balance_kg" name="flour_balance_kg" type="number" step="0.01" min="0" class="block mt-1 w-full" :value="old('flour_balance_kg', 0)" />
                        <x-input-error :messages="$errors->get('flour_balance_kg')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="ملاحظات" />
                        <textarea id="notes" name="notes" rows="3" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('panel.customers.index') }}" class="px-4 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                        <button type="submit" class="px-4 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">حفظ العميل</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
