<div class="space-y-6">
    <div class="flex items-center justify-end">
        <button type="button" x-data @click="$dispatch('open-modal', 'bakery-create')"
                class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
            + إضافة مخبز جديد
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-6 py-3 text-right">المخبز</th>
                        <th class="px-6 py-3 text-right">المالك</th>
                        <th class="px-6 py-3 text-right">حالة الاشتراك</th>
                        <th class="px-6 py-3 text-right">تاريخ الانتهاء</th>
                        <th class="px-6 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($bakeries as $bakery)
                        <tr wire:key="bakery-{{ $bakery->id }}">
                            <td class="px-6 py-3">
                                <button type="button" wire:click="editBakery({{ $bakery->id }})" class="font-medium text-violet-600 hover:underline">
                                    {{ $bakery->name }}
                                </button>
                                <div class="text-xs text-gray-500">{{ $bakery->phone }}</div>
                            </td>
                            <td class="px-6 py-3">
                                @foreach ($bakery->owners as $owner)
                                    <div>{{ $owner->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $owner->email }}</div>
                                @endforeach
                            </td>
                            <td class="px-6 py-3">
                                @if ($bakery->isSubscriptionActive())
                                    <span class="px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">مفعّل</span>
                                @else
                                    <span class="px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">غير مفعّل</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                {{ $bakery->subscription_expires_at?->translatedFormat('d M Y') ?? '—' }}
                            </td>
                            <td class="px-6 py-3 text-left whitespace-nowrap">
                                <div class="flex items-center gap-2 justify-end">
                                    <input type="number" wire:model="renewMonths.{{ $bakery->id }}" placeholder="1" min="1" max="24" class="w-16 rounded-md border-gray-300 text-xs">
                                    <button type="button" wire:click="renew({{ $bakery->id }})" class="text-xs px-3 py-1 rounded-lg bg-green-600 text-white hover:bg-green-700">تجديد</button>
                                    <button type="button" wire:click="toggleStatus({{ $bakery->id }})" class="text-xs px-3 py-1 rounded-lg border border-gray-300 hover:bg-gray-50">
                                        {{ $bakery->subscription_status === 'active' ? 'تعطيل' : 'تفعيل' }}
                                    </button>
                                    <button type="button" wire:click="delete({{ $bakery->id }})" wire:confirm="سيتم حذف المخبز وكل بياناته نهائيًا. متابعة؟"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 transition">
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-6 text-center text-gray-400">لا توجد مخابز مسجلة بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $bakeries->links() }}

    <x-modal name="bakery-create" maxWidth="lg">
        <livewire:admin-bakery-create :is-modal="true" wire:key="bakery-create-modal" />
    </x-modal>

    <x-modal name="bakery-edit" maxWidth="lg">
        @if ($editingBakery)
            <livewire:admin-bakery-edit :bakery="$editingBakery" :is-modal="true" wire:key="bakery-edit-{{ $editingBakery->id }}" />
        @endif
    </x-modal>
</div>
