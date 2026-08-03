<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">عمليات البيع</h2>
            <a href="{{ route('panel.sales.create') }}" class="px-4 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">
                + تسجيل عملية بيع
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">الحالة</label>
                    <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">الكل</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>مدفوع</option>
                        <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>غير مدفوع</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">التاريخ</label>
                    <input type="date" name="date" value="{{ request('date') }}" class="rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                </div>
                <button class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm font-medium hover:bg-gray-900">تصفية</button>
                <a href="{{ route('panel.sales.index') }}" class="text-sm text-gray-500 hover:underline">مسح التصفية</a>
            </form>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
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
                                <tr>
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
                                            <form method="POST" action="{{ route('panel.sales.mark-unpaid', $sale) }}" class="inline">
                                                @csrf
                                                <button class="text-xs px-3 py-1 rounded-md border border-gray-300 hover:bg-gray-50">تحويل لغير مدفوع</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('panel.sales.mark-paid', $sale) }}" class="inline">
                                                @csrf
                                                <button class="text-xs px-3 py-1 rounded-md bg-green-600 text-white hover:bg-green-700">تأكيد الدفع</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('panel.sales.destroy', $sale) }}" class="inline" onsubmit="return confirm('حذف هذه العملية؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-xs px-3 py-1 rounded-md text-red-600 hover:bg-red-50">حذف</button>
                                        </form>
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
    </div>
</x-app-layout>
