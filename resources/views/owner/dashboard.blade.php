<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                لوحة التحكم — {{ $bakery->name }}
            </h2>
            <a href="{{ route('panel.sales.create') }}" class="px-4 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">
                + تسجيل عملية بيع
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-sm text-gray-500 mb-1">خبز مباع اليوم (كجم)</div>
                    <div class="text-3xl font-bold text-amber-700">{{ number_format($todayKg, 2) }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-sm text-gray-500 mb-1">مقبوضات اليوم</div>
                    <div class="text-3xl font-bold text-green-700">{{ number_format($todayTakings, 2) }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="text-sm text-gray-500 mb-1">غير مدفوع اليوم</div>
                    <div class="text-3xl font-bold text-red-600">{{ number_format($todayUnpaid, 2) }}</div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 font-semibold">آخر 7 أيام</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-6 py-2 text-right">التاريخ</th>
                                <th class="px-6 py-2 text-right">الكيلوغرامات المباعة</th>
                                <th class="px-6 py-2 text-right">المقبوضات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($last7Days as $day)
                                <tr>
                                    <td class="px-6 py-2">{{ \Illuminate\Support\Carbon::parse($day->sale_date)->translatedFormat('d M Y') }}</td>
                                    <td class="px-6 py-2">{{ number_format($day->kg_total, 2) }}</td>
                                    <td class="px-6 py-2">{{ number_format($day->takings, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-6 py-4 text-center text-gray-400">لا توجد بيانات بعد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 font-semibold flex items-center justify-between">
                        <span>عمليات غير مدفوعة</span>
                        <a href="{{ route('panel.sales.index', ['status' => 'unpaid']) }}" class="text-sm text-amber-700 hover:underline">عرض الكل</a>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($unpaidSales as $sale)
                            <li class="px-6 py-3 flex items-center justify-between text-sm">
                                <div>
                                    <div class="font-medium">{{ $sale->customer->name ?? 'عميل نقدي' }}</div>
                                    <div class="text-gray-500">{{ $sale->sale_date->translatedFormat('d M Y') }} — {{ number_format($sale->kg_amount, 2) }} كجم</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-semibold text-red-600">{{ number_format($sale->total_amount, 2) }}</span>
                                    <form method="POST" action="{{ route('panel.sales.mark-paid', $sale) }}">
                                        @csrf
                                        <button class="text-xs px-3 py-1 rounded-md bg-green-600 text-white hover:bg-green-700">تأكيد الدفع</button>
                                    </form>
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-4 text-center text-gray-400 text-sm">لا توجد عمليات غير مدفوعة.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 font-semibold flex items-center justify-between">
                        <span>آخر العمليات</span>
                        <a href="{{ route('panel.sales.index') }}" class="text-sm text-amber-700 hover:underline">عرض الكل</a>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @forelse ($recentSales as $sale)
                            <li class="px-6 py-3 flex items-center justify-between text-sm">
                                <div>
                                    <div class="font-medium">{{ $sale->customer->name ?? 'عميل نقدي' }}</div>
                                    <div class="text-gray-500">
                                        {{ $sale->sale_date->translatedFormat('d M Y') }} —
                                        {{ $sale->isFlourExchange() ? 'مقابل قمح' : 'بيع نقدي' }}
                                    </div>
                                </div>
                                <div class="text-left">
                                    <div class="font-semibold">{{ number_format($sale->total_amount, 2) }}</div>
                                    <span class="text-xs {{ $sale->isPaid() ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $sale->isPaid() ? 'مدفوع' : 'غير مدفوع' }}
                                    </span>
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-4 text-center text-gray-400 text-sm">لا توجد عمليات بعد.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
