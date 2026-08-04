<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">عمليات البيع</h2>
            <a href="{{ route('panel.sales.create') }}" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
                + تسجيل عملية بيع
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto">
            <livewire:sales-index />
        </div>
    </div>
</x-app-layout>
