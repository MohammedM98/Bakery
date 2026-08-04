<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                لوحة التحكم — {{ $bakery->name }}
            </h2>
            <a href="{{ route('panel.sales.create') }}" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium shadow-sm shadow-violet-200 hover:bg-violet-700">
                + تسجيل عملية بيع
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <livewire:dashboard-page />
    </div>
</x-app-layout>
