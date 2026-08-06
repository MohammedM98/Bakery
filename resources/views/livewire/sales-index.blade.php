<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex gap-2 border-b border-gray-200">
            <button type="button" wire:click="$set('type', 'cash')"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ $type === 'cash' ? 'border-violet-600 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                عمليات البيع النقدي
            </button>
            <button type="button" wire:click="$set('type', 'flour_exchange')"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px {{ $type === 'flour_exchange' ? 'border-violet-600 text-violet-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                عمليات البيع مقابل القمح
            </button>
        </div>

        @if ($type === 'cash')
            <button type="button" x-data @click="$dispatch('open-modal', 'sale-create')"
                    class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
                + تسجيل عملية بيع
            </button>
        @endif
    </div>

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
                            <td class="px-6 py-3">
                                {{ $sale->buyerDisplayName() }}
                                @if (! $sale->customer && $sale->buyer_mobile)
                                    <div class="text-xs text-gray-400">{{ $sale->buyer_mobile }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ number_format($sale->kg_amount, 2) }}</td>
                            <td class="px-6 py-3">{{ money($sale->total_amount) }}</td>
                            <td class="px-6 py-3">
                                @if ($sale->isPaid())
                                    <span class="text-green-600 font-medium inline-flex items-center gap-1">
                                        مدفوع
                                        <span title="عملية مؤكدة، لا يمكن التراجع عنها">🔒</span>
                                    </span>
                                @elseif ($sale->isPartiallyPaid())
                                    <span class="text-amber-600 font-medium">مدفوع جزئيًا — متبقي {{ money($sale->remainingAmount()) }}</span>
                                @else
                                    <span class="text-red-600 font-medium">غير مدفوع</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-left whitespace-nowrap">
                                @unless ($sale->isPaid())
                                    <button type="button" wire:click="confirmMarkPaid({{ $sale->id }})"
                                            class="text-xs px-3 py-1 rounded-lg bg-green-600 text-white hover:bg-green-700">
                                        تأكيد الدفع
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-6 text-center text-gray-400">لا توجد عمليات بيع.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $sales->links() }}

    <x-modal name="sale-create" maxWidth="lg">
        <livewire:sale-create :is-modal="true" wire:key="sale-create-modal" />
    </x-modal>

    <x-confirm-modal
        name="confirm-payment"
        title="تأكيد استلام الدفع"
        :message="'سيتم تأكيد استلام المتبقي وقدره '.($confirmingSale ? money($confirmingSale->remainingAmount()) : '').' نهائيًا لهذه العملية. لا يمكن التراجع عن هذا الإجراء بعد ذلك.'"
        confirmLabel="تأكيد الدفع"
        :confirmAction="'markPaid('.$confirmingPaymentSaleId.')'"
        tone="success"
    />
</div>
