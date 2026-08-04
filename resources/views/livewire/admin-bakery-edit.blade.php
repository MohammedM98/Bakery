<div class="max-w-2xl mx-auto space-y-6">
    @if ($message)
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3" wire:key="bakery-edit-flash">
            {{ $message }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm p-6">
        <form wire:submit="save" class="space-y-5">
            <div>
                <x-input-label for="name" value="اسم المخبز" />
                <x-text-input id="name" type="text" class="block mt-1 w-full" wire:model="name" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="phone" value="رقم الهاتف" />
                    <x-text-input id="phone" type="text" class="block mt-1 w-full" wire:model="phone" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="address" value="العنوان" />
                    <x-text-input id="address" type="text" class="block mt-1 w-full" wire:model="address" />
                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.bakeries.index') }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    حفظ التعديلات
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6">
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

        <div class="flex items-end gap-3">
            <div>
                <x-input-label for="subscription_months" value="تجديد لعدد أشهر" />
                <x-text-input id="subscription_months" type="number" min="1" max="24" class="block mt-1 w-32" wire:model="subscription_months" />
            </div>
            <button type="button" wire:click="renew" class="px-4 py-2 rounded-xl bg-green-600 text-white text-sm font-medium hover:bg-green-700">تجديد الاشتراك</button>
        </div>

        <button type="button" wire:click="toggleStatus" class="mt-3 px-4 py-2 rounded-xl border border-gray-300 text-sm hover:bg-gray-50">
            {{ $bakery->subscription_status === 'active' ? 'تعطيل الاشتراك يدويًا' : 'تفعيل الاشتراك' }}
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 class="font-semibold text-gray-700 mb-2">حساب المالك</h3>
        @foreach ($bakery->owners as $owner)
            <div class="text-sm text-gray-700">{{ $owner->name }} — {{ $owner->email }}</div>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6 border border-red-100">
        <h3 class="font-semibold text-red-700 mb-2">حذف المخبز</h3>
        <p class="text-sm text-gray-600 mb-4">سيتم حذف المخبز وحساب المالك وجميع العملاء والعمليات المرتبطة به نهائيًا.</p>
        <button type="button" wire:click="delete" wire:confirm="هل أنت متأكد من حذف هذا المخبز وجميع بياناته؟"
                class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-medium hover:bg-red-700">
            حذف المخبز نهائيًا
        </button>
    </div>
</div>
