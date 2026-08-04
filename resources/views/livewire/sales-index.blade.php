<div class="space-y-6">
    @if ($message)
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3" wire:key="flash-message">
            {{ $message }}
        </div>
    @endif

    <div class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-sm text-gray-600 mb-1">الحالة</label>
            <select wire:model.live="status" class="rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                <option value="">الكل</option>
                <option value="paid">مدفوع</option>
                <option value="unpaid">غير مدفوع</option>
            </select>
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">التاريخ</label>
            <input type="date" wire:model.live="date" class="rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
        </div>
        <button type="button" wire:click="clearFilters" class="text-sm text-gray-500 hover:underline pb-2">مسح التصفية</button>

        <div wire:loading class="text-sm text-violet-600 pb-2">جارٍ التحديث...</div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-6 py-3 text-right">التاريخ</th>
                        <th class="px-6 py-3 text-right">العميل</th>
                        <th class="px-6 py-3 text-right">النوع</th>
                        <th class="px-6 py-3 text-right">الكمية (كجم)</th>
                        <th class="px-6 py-3 text-right">المبلغ</th>
                        <th class="px-6 py-3 text-right">الحالة</th>
                        <th class="px-6 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($sales as $sale)
                        <tr wire:key="sale-{{ $sale->id }}">
                            <td class="px-6 py-3">{{ $sale->sale_date->translatedFormat('d M Y') }}</td>
                            <td class="px-6 py-3">{{ $sale->customer->name ?? 'عميل نقدي' }}</td>
                            <td class="px-6 py-3">{{ $sale->isFlourExchange() ? 'مقابل قمح' : 'بيع نقدي' }}</td>
                            <td class="px-6 py-3">{{ number_format($sale->kg_amount, 2) }}</td>
                            <td class="px-6 py-3">{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="px-6 py-3">
                                <span class="{{ $sale->isPaid() ? 'text-green-600' : 'text-red-600' }} font-medium">
                                    {{ $sale->isPaid() ? 'مدفوع' : 'غير مدفوع' }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-left whitespace-nowrap">
                                @if ($sale->isPaid())
                                    <button type="button" wire:click="markUnpaid({{ $sale->id }})"
                                            class="text-xs px-3 py-1 rounded-lg border border-gray-300 hover:bg-gray-50">
                                        تحويل لغير مدفوع
                                    </button>
                                @else
                                    <button type="button" wire:click="markPaid({{ $sale->id }})"
                                            class="text-xs px-3 py-1 rounded-lg bg-green-600 text-white hover:bg-green-700">
                                        تأكيد الدفع
                                    </button>
                                @endif
                                <button type="button" wire:click="delete({{ $sale->id }})" wire:confirm="هل أنت متأكد من حذف هذه العملية؟"
                                        class="text-xs px-3 py-1 rounded-lg text-red-600 hover:bg-red-50">
                                    حذف
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-6 text-center text-gray-400">لا توجد عمليات بيع.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $sales->links() }}
</div>
