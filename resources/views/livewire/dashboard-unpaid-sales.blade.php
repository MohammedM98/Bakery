<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 font-semibold flex items-center justify-between">
        <span>عمليات غير مدفوعة</span>
        <a href="{{ route('panel.sales.index', ['status' => 'unpaid']) }}" class="text-sm text-violet-600 hover:underline">عرض الكل</a>
    </div>

    <ul class="divide-y divide-gray-100">
        @forelse ($unpaidSales as $sale)
            <li class="px-6 py-3 flex items-center justify-between text-sm" wire:key="unpaid-{{ $sale->id }}">
                <div>
                    <div class="font-medium">{{ $sale->buyerDisplayName() }}</div>
                    <div class="text-gray-500">{{ $sale->sale_date->translatedFormat('d M Y') }} — {{ number_format($sale->kg_amount, 2) }} كجم</div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-semibold text-red-600">{{ money($sale->total_amount) }}</span>
                    <button type="button" wire:click="confirmMarkPaid({{ $sale->id }})"
                            class="text-xs px-3 py-1 rounded-md bg-green-600 text-white hover:bg-green-700 disabled:opacity-50">
                        تأكيد الدفع
                    </button>
                </div>
            </li>
        @empty
            <li class="px-6 py-4 text-center text-gray-400 text-sm">لا توجد عمليات غير مدفوعة.</li>
        @endforelse
    </ul>

    <x-confirm-modal
        name="dashboard-confirm-payment"
        title="تأكيد استلام الدفع"
        :message="'سيتم تأكيد دفع هذه العملية نهائيًا بمبلغ '.($confirmingSale ? money($confirmingSale->total_amount) : '').'. لا يمكن التراجع عن هذا الإجراء بعد ذلك.'"
        confirmLabel="تأكيد الدفع"
        :confirmAction="'markPaid('.$confirmingPaymentSaleId.')'"
        tone="success"
    />
</div>
