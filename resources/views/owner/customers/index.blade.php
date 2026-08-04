<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">العملاء</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto">
            <livewire:customers-index />
        </div>
    </div>
</x-app-layout>
