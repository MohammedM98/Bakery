<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $customer->name }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('panel.sales.create', ['customer_id' => $customer->id]) }}" class="px-4 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">
                    + عملية بيع
                </a>
                <a href="{{ route('panel.customers.edit', $customer) }}" class="px-4 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                    تعديل
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-sm text-gray-500 mb-1">رقم الجوال</div>
                    <div class="text-lg font-semibold">{{ $customer->mobile_number }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-sm text-gray-500 mb-1">رصيد القمح الحالي</div>
                    <div class="text-2xl font-bold text-amber-700">{{ number_format($customer->flour_balance_kg, 2) }} كجم</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-sm text-gray-500 mb-1">ملاحظات</div>
                    <div class="text-sm">{{ $customer->notes ?: '—' }}</div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="font-semibold mb-4">تسجيل استلام قمح جديد</h3>
                <form method="POST" action="{{ route('panel.flour-deposits.store', $customer) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                    @csrf
                    <div class="sm:col-span-1">
                        <x-input-label for="kg_amount" value="الكمية (كجم)" />
                        <x-text-input id="kg_amount" name="kg_amount" type="number" step="0.01" min="0.01" class="block mt-1 w-full" required />
                    </div>
                    <div class="sm:col-span-1">
                        <x-input-label for="deposit_date" value="التاريخ" />
                        <x-text-input id="deposit_date" name="deposit_date" type="date" class="block mt-1 w-full" :value="now()->toDateString()" required />
                    </div>
                    <div class="sm:col-span-1">
                        <x-input-label for="notes" value="ملاحظات" />
                        <x-text-input id="notes" name="notes" type="text" class="block mt-1 w-full" />
                    </div>
                    <div class="sm:col-span-1">
                        <button type="submit" class="w-full px-4 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">إضافة للرصيد</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold">سجل استلام القمح</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-6 py-2 text-right">التاريخ</th>
                                <th class="px-6 py-2 text-right">الكمية (كجم)</th>
                                <th class="px-6 py-2 text-right">ملاحظات</th>
                                <th class="px-6 py-2 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customer->flourDeposits as $deposit)
                                <tr>
                                    <td class="px-6 py-2">{{ $deposit->deposit_date->translatedFormat('d M Y') }}</td>
                                    <td class="px-6 py-2">{{ number_format($deposit->kg_amount, 2) }}</td>
                                    <td class="px-6 py-2">{{ $deposit->notes ?: '—' }}</td>
                                    <td class="px-6 py-2 text-left">
                                        <form method="POST" action="{{ route('panel.flour-deposits.destroy', $deposit) }}" onsubmit="return confirm('حذف هذه العملية؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:underline text-xs">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-4 text-center text-gray-400">لا يوجد سجل استلام قمح.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold">سجل عمليات البيع</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-6 py-2 text-right">التاريخ</th>
                                <th class="px-6 py-2 text-right">النوع</th>
                                <th class="px-6 py-2 text-right">الكمية (كجم)</th>
                                <th class="px-6 py-2 text-right">المبلغ</th>
                                <th class="px-6 py-2 text-right">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customer->sales as $sale)
                                <tr>
                                    <td class="px-6 py-2">{{ $sale->sale_date->translatedFormat('d M Y') }}</td>
                                    <td class="px-6 py-2">{{ $sale->isFlourExchange() ? 'مقابل قمح' : 'بيع نقدي' }}</td>
                                    <td class="px-6 py-2">{{ number_format($sale->kg_amount, 2) }}</td>
                                    <td class="px-6 py-2">{{ number_format($sale->total_amount, 2) }}</td>
                                    <td class="px-6 py-2">
                                        <span class="{{ $sale->isPaid() ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $sale->isPaid() ? 'مدفوع' : 'غير مدفوع' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-400">لا يوجد سجل مبيعات.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
