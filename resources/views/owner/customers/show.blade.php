<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('panel.customers.edit', $customer) }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                    تعديل
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <livewire:customer-show :customer="$customer" />
    </div>
</x-app-layout>
