<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">إضافة مخبز جديد</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <form method="POST" action="{{ route('admin.bakeries.store') }}" class="space-y-5">
                    @csrf

                    <h3 class="font-semibold text-gray-700">بيانات المخبز</h3>

                    <div>
                        <x-input-label for="name" value="اسم المخبز" />
                        <x-text-input id="name" name="name" type="text" class="block mt-1 w-full" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="phone" value="رقم الهاتف" />
                            <x-text-input id="phone" name="phone" type="text" class="block mt-1 w-full" :value="old('phone')" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="address" value="العنوان" />
                            <x-text-input id="address" name="address" type="text" class="block mt-1 w-full" :value="old('address')" />
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="subscription_months" value="مدة الاشتراك الأولى (بالأشهر)" />
                        <x-text-input id="subscription_months" name="subscription_months" type="number" min="1" max="24" class="block mt-1 w-full" :value="old('subscription_months', 1)" required />
                        <x-input-error :messages="$errors->get('subscription_months')" class="mt-2" />
                    </div>

                    <hr class="my-2">

                    <h3 class="font-semibold text-gray-700">حساب مالك المخبز</h3>

                    <div>
                        <x-input-label for="owner_name" value="اسم المالك" />
                        <x-text-input id="owner_name" name="owner_name" type="text" class="block mt-1 w-full" :value="old('owner_name')" required />
                        <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="owner_email" value="البريد الإلكتروني (لتسجيل الدخول)" />
                        <x-text-input id="owner_email" name="owner_email" type="email" class="block mt-1 w-full" :value="old('owner_email')" required />
                        <x-input-error :messages="$errors->get('owner_email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="owner_password" value="كلمة المرور" />
                        <x-text-input id="owner_password" name="owner_password" type="password" class="block mt-1 w-full" required />
                        <x-input-error :messages="$errors->get('owner_password')" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.bakeries.index') }}" class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">إلغاء</a>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">إنشاء المخبز</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
