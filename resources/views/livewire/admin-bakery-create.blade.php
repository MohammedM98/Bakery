<div class="{{ $isModal ? 'p-6 max-h-[80vh] overflow-y-auto' : 'max-w-2xl mx-auto' }}">
    @unless ($isModal)
        <div class="bg-white rounded-2xl shadow-sm p-6">
    @endunless
        <form wire:submit="save" class="space-y-5">
            <h3 class="font-semibold text-gray-700">بيانات المخبز</h3>

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

            <div>
                <x-input-label for="subscription_months" value="مدة الاشتراك الأولى (بالأشهر)" />
                <x-text-input id="subscription_months" type="number" min="1" max="24" class="block mt-1 w-full" wire:model="subscription_months" required />
                <x-input-error :messages="$errors->get('subscription_months')" class="mt-2" />
            </div>

            <hr class="my-2">

            <h3 class="font-semibold text-gray-700">حساب مالك المخبز</h3>

            <div>
                <x-input-label for="owner_name" value="اسم المالك" />
                <x-text-input id="owner_name" type="text" class="block mt-1 w-full" wire:model="owner_name" required />
                <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="owner_email" value="البريد الإلكتروني (لتسجيل الدخول)" />
                <x-text-input id="owner_email" type="email" class="block mt-1 w-full" wire:model="owner_email" required />
                <x-input-error :messages="$errors->get('owner_email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="owner_password" value="كلمة المرور" />
                <x-text-input id="owner_password" type="password" class="block mt-1 w-full" wire:model="owner_password" required />
                <x-input-error :messages="$errors->get('owner_password')" class="mt-2" />
            </div>

            <div class="flex justify-end gap-3">
                @if ($isModal)
                    <button type="button" @click="$dispatch('close-modal', 'bakery-create')" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</button>
                @else
                    <a href="{{ route('admin.bakeries.index') }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                @endif
                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 disabled:opacity-60">
                    إنشاء المخبز
                </button>
            </div>
        </form>
    @unless ($isModal)
        </div>
    @endunless
</div>
