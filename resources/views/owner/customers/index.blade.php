<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">العملاء</h2>
            <a href="{{ route('panel.customers.create') }}" class="px-4 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">
                + إضافة عميل
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="GET" class="flex gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث بالاسم أو رقم الجوال"
                       class="w-full sm:w-96 rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                <button class="px-4 py-2 rounded-md bg-gray-800 text-white text-sm font-medium hover:bg-gray-900">بحث</button>
            </form>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-6 py-3 text-right">الاسم</th>
                                <th class="px-6 py-3 text-right">رقم الجوال</th>
                                <th class="px-6 py-3 text-right">رصيد القمح (كجم)</th>
                                <th class="px-6 py-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customers as $customer)
                                <tr>
                                    <td class="px-6 py-3">
                                        <a href="{{ route('panel.customers.show', $customer) }}" class="font-medium text-amber-700 hover:underline">
                                            {{ $customer->name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-3">{{ $customer->mobile_number }}</td>
                                    <td class="px-6 py-3">{{ number_format($customer->flour_balance_kg, 2) }}</td>
                                    <td class="px-6 py-3 text-left">
                                        <a href="{{ route('panel.customers.edit', $customer) }}" class="text-gray-600 hover:underline">تعديل</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-6 text-center text-gray-400">لا يوجد عملاء بعد.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $customers->links() }}
        </div>
    </div>
</x-app-layout>
