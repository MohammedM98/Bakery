@php $maxKg = max($last7Days->max('kg_total') ?? 0, 1); @endphp

<div class="max-w-7xl mx-auto space-y-6">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 rounded-3xl bg-gradient-to-br from-violet-600 to-indigo-700 text-white p-6 shadow-xl shadow-violet-200">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-violet-100 text-sm mb-1">خبز مباع اليوم</div>
                    <div class="text-4xl font-extrabold">{{ number_format($todayKg, 2) }} <span class="text-lg font-medium text-violet-200">كجم</span></div>
                </div>
                <div class="text-3xl">🍞</div>
            </div>
            <div class="mt-6 flex items-end gap-2 h-16">
                @forelse ($last7Days as $day)
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <div class="w-full bg-white/20 rounded-full overflow-hidden flex items-end" style="height: 3rem">
                            <div class="w-full bg-white rounded-full" style="height: {{ max(($day->kg_total / $maxKg) * 100, 6) }}%"></div>
                        </div>
                        <span class="text-[10px] text-violet-200">{{ \Illuminate\Support\Carbon::parse($day->sale_date)->translatedFormat('d/m') }}</span>
                    </div>
                @empty
                    <span class="text-violet-200 text-sm">لا توجد بيانات لآخر 7 أيام بعد.</span>
                @endforelse
            </div>
        </div>

        <div class="rounded-3xl bg-gradient-to-br from-pink-500 to-rose-500 text-white p-6 shadow-xl shadow-pink-200 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-pink-50 text-sm mb-1">غير مدفوع اليوم</div>
                    <div class="text-3xl font-extrabold">{{ number_format($todayUnpaid, 2) }}</div>
                </div>
                <div class="text-3xl">⚠️</div>
            </div>
            <a href="{{ route('panel.sales.index', ['status' => 'unpaid']) }}" class="mt-6 inline-flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/30 text-sm font-medium py-2 transition">
                عرض العمليات غير المدفوعة
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
            <div class="h-11 w-11 rounded-xl bg-green-100 text-green-700 flex items-center justify-center text-xl">💰</div>
            <div>
                <div class="text-xs text-gray-500">مقبوضات اليوم</div>
                <div class="text-xl font-bold text-gray-800">{{ number_format($todayTakings, 2) }}</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
            <div class="h-11 w-11 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center text-xl">🧾</div>
            <div>
                <div class="text-xs text-gray-500">عدد العمليات اليوم</div>
                <div class="text-xl font-bold text-gray-800">{{ $todaySalesCount }}</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
            <div class="h-11 w-11 rounded-xl bg-red-100 text-red-700 flex items-center justify-center text-xl">⏳</div>
            <div>
                <div class="text-xs text-gray-500">عمليات غير مدفوعة اليوم</div>
                <div class="text-xl font-bold text-gray-800">{{ $todayUnpaidCount }}</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
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
        <livewire:dashboard-unpaid-sales />

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 font-semibold flex items-center justify-between">
                <span>آخر العمليات</span>
                <a href="{{ route('panel.sales.index') }}" class="text-sm text-violet-600 hover:underline">عرض الكل</a>
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
