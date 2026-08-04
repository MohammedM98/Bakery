<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">المخابز والاشتراكات</h2>
            <a href="{{ route('admin.bakeries.create') }}" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
                + إضافة مخبز جديد
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto">
            <livewire:admin-bakeries-index />
        </div>
    </div>
</x-app-layout>
