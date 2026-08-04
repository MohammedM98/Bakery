<div class="space-y-6">
    @if ($flashMessage)
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3" wire:key="customers-flash">
            {{ $flashMessage }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <div class="relative w-full sm:w-96">
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="ابحث بالاسم أو رقم الجوال"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
            <div wire:loading wire:target="search" class="absolute left-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-violet-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>
        </div>

        <button type="button" x-data @click="$dispatch('open-modal', 'customer-create')"
                class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 shrink-0">
            + إضافة عميل
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-6 py-3 text-right">الاسم</th>
                        <th class="px-6 py-3 text-right">رقم الجوال</th>
                        <th class="px-6 py-3 text-right">رصيد القمح (كجم)</th>
                        <th class="px-6 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $customer)
                        <tr wire:key="customer-{{ $customer->id }}">
                            <td class="px-6 py-3">
                                <a href="{{ route('panel.customers.show', $customer) }}" class="font-medium text-violet-600 hover:underline">
                                    {{ $customer->name }}
                                </a>
                            </td>
                            <td class="px-6 py-3">{{ $customer->mobile_number }}</td>
                            <td class="px-6 py-3">{{ number_format($customer->flour_balance_kg, 2) }}</td>
                            <td class="px-6 py-3 text-left">
                                <button type="button" wire:click="editCustomer({{ $customer->id }})" class="text-gray-600 hover:underline">تعديل</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-6 text-center text-gray-400">
                                @if ($search)
                                    لا يوجد عملاء مطابقون لـ "{{ $search }}".
                                @else
                                    لا يوجد عملاء بعد.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $customers->links() }}

    <x-modal name="customer-create" maxWidth="lg">
        <livewire:customer-create :is-modal="true" wire:key="customer-create-modal" />
    </x-modal>

    <x-modal name="customer-edit" maxWidth="lg">
        @if ($editingCustomer)
            <livewire:customer-edit :customer="$editingCustomer" :is-modal="true" wire:key="customer-edit-{{ $editingCustomer->id }}" />
        @endif
    </x-modal>
</div>
