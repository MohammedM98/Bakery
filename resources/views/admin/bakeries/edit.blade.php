<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">تعديل بيانات المخبز</h2>
    </x-slot>

    <div class="py-8">
        <livewire:admin-bakery-edit :bakery="$bakery" />
    </div>
</x-app-layout>
