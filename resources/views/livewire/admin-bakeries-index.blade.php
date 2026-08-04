<div class="space-y-6">
    @if ($message)
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3" wire:key="admin-flash">
            {{ $message }}
        </div>
    @endif

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
                                <a href="{{ route('admin.bakeries.edit', $bakery) }}" class="font-medium text-violet-600 hover:underline">
                                    {{ $bakery->name }}
                                </a>
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
                                            class="text-xs px-3 py-1 rounded-lg text-red-600 hover:bg-red-50">
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
</div>
