<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }}</h2>
            <div class="flex gap-2 flex-wrap">
                <button type="button" x-data @click="$dispatch('open-modal', 'flour-deposit')"
                        class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
                    + تسجيل استلام قمح
                </button>
                <button type="button" x-data @click="$dispatch('open-modal', 'bread-delivery')"
                        class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
                    + تسليم خبز مقابل القمح
                </button>
                @if ($outstandingBalance > 0)
                    <button type="button" x-data @click="$dispatch('open-modal', 'record-payment')"
                            class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-medium hover:bg-green-700">
                        + تسجيل دفعة
                    </button>
                @endif
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
