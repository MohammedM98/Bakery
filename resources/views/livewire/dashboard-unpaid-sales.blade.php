<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 font-semibold flex items-center justify-between">
        <span>عمليات غير مدفوعة</span>
        <a href="{{ route('panel.sales.index', ['status' => 'unpaid']) }}" class="text-sm text-violet-600 hover:underline">عرض الكل</a>
    </div>

    @if ($message)
        <div class="px-6 py-2 bg-green-50 text-green-800 text-sm border-b border-green-100" wire:key="dashboard-flash">
            {{ $message }}
        </div>
    @endif

    <ul class="divide-y divide-gray-100">
        @forelse ($unpaidSales as $sale)
            <li class="px-6 py-3 flex items-center justify-between text-sm" wire:key="unpaid-{{ $sale->id }}">
                <div>
                    <div class="font-medium">{{ $sale->customer->name ?? 'عميل نقدي' }}</div>
                    <div class="text-gray-500">{{ $sale->sale_date->translatedFormat('d M Y') }} — {{ number_format($sale->kg_amount, 2) }} كجم</div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-semibold text-red-600">{{ number_format($sale->total_amount, 2) }}</span>
                    <button type="button" wire:click="markPaid({{ $sale->id }})"
                            wire:loading.attr="disabled" wire:target="markPaid({{ $sale->id }})"
                            class="text-xs px-3 py-1 rounded-md bg-green-600 text-white hover:bg-green-700 disabled:opacity-50">
                        تأكيد الدفع
                    </button>
                </div>
            </li>
        @empty
            <li class="px-6 py-4 text-center text-gray-400 text-sm">لا توجد عمليات غير مدفوعة.</li>
        @endforelse
    </ul>
</div>
