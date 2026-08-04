<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            لوحة التحكم — {{ $bakery->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <livewire:dashboard-page />
    </div>
</x-app-layout>
