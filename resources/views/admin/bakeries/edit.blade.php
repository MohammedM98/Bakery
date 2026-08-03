<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">تعديل بيانات المخبز</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white rounded-xl shadow-sm p-6">
                <form method="POST" action="{{ route('admin.bakeries.update', $bakery) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="اسم المخبز" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name', $bakery->name)" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="phone" value="رقم الهاتف" />
                            <x-text-input id="phone" name="phone" type="text" class="block mt-1 w-full" :value="old('phone', $bakery->phone)" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="address" value="العنوان" />
                            <x-text-input id="address" name="address" type="text" class="block mt-1 w-full" :value="old('address', $bakery->address)" />
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.bakeries.index') }}" class="px-4 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                        <button type="submit" class="px-4 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">حفظ التعديلات</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="font-semibold text-gray-700 mb-4">الاشتراك</h3>
                <div class="flex items-center gap-3 mb-4 text-sm">
                    <span>الحالة:</span>
                    @if ($bakery->isSubscriptionActive())
                        <span class="px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">مفعّل</span>
                    @else
                        <span class="px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">غير مفعّل</span>
                    @endif
                    <span class="text-gray-500">ينتهي في {{ $bakery->subscription_expires_at?->translatedFormat('d M Y') ?? '—' }}</span>
                </div>

                <form method="POST" action="{{ route('admin.bakeries.renew', $bakery) }}" class="flex items-end gap-3">
                    @csrf
                    <div>
                        <x-input-label for="subscription_months" value="تجديد لعدد أشهر" />
                        <x-text-input id="subscription_months" name="subscription_months" type="number" min="1" max="24" class="block mt-1 w-32" value="1" />
                    </div>
                    <button class="px-4 py-2 rounded-md bg-green-600 text-white text-sm font-medium hover:bg-green-700">تجديد الاشتراك</button>
                </form>

                <form method="POST" action="{{ route('admin.bakeries.toggle-status', $bakery) }}" class="mt-3">
                    @csrf
                    <button class="px-4 py-2 rounded-md border border-gray-300 text-sm hover:bg-gray-50">
                        {{ $bakery->subscription_status === 'active' ? 'تعطيل الاشتراك يدويًا' : 'تفعيل الاشتراك' }}
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="font-semibold text-gray-700 mb-2">حساب المالك</h3>
                @foreach ($bakery->owners as $owner)
                    <div class="text-sm text-gray-700">{{ $owner->name }} — {{ $owner->email }}</div>
                @endforeach
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-red-100">
                <h3 class="font-semibold text-red-700 mb-2">حذف المخبز</h3>
                <p class="text-sm text-gray-600 mb-4">سيتم حذف المخبز وحساب المالك وجميع العملاء والعمليات المرتبطة به نهائيًا.</p>
                <form method="POST" action="{{ route('admin.bakeries.destroy', $bakery) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا المخبز وجميع بياناته؟');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 rounded-md bg-red-600 text-white text-sm font-medium hover:bg-red-700">حذف المخبز نهائيًا</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
