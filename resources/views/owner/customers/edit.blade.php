<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">تعديل بيانات العميل</h2>
    </x-slot>

    <div class="py-8">
        <livewire:customer-edit :customer="$customer" />
    </div>
</x-app-layout>
