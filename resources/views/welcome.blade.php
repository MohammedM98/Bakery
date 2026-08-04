<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased text-gray-900 bg-gradient-to-b from-violet-50 via-slate-50 to-pink-50">
        <div class="min-h-screen flex flex-col">
            <header class="max-w-5xl mx-auto w-full flex items-center justify-between px-6 py-6">
                <div class="flex items-center gap-2 text-2xl font-bold text-violet-700">
                    🥖 <span>{{ config('app.name') }}</span>
                </div>
                <nav class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="px-5 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium shadow-sm shadow-violet-200 hover:bg-violet-700">
                            لوحة التحكم
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-5 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium shadow-sm shadow-violet-200 hover:bg-violet-700">
                            تسجيل الدخول
                        </a>
                    @endauth
                </nav>
            </header>

            <main class="flex-1 max-w-5xl mx-auto w-full px-6">
                <section class="text-center py-16">
                    <span class="inline-block px-4 py-1 rounded-full bg-violet-100 text-violet-700 text-xs font-semibold mb-4">
                        منصة SaaS لإدارة المخابز
                    </span>
                    <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mb-4">
                        نظام إدارة المخابز
                    </h1>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        منصة متكاملة لإدارة المبيعات اليومية، حسابات العملاء، ورصيد القمح مقابل الخبز — مع لوحة تحكم خاصة لكل مخبز واشتراك شهري يديره مالك المنصة.
                    </p>
                </section>

                <section class="grid sm:grid-cols-2 gap-6 pb-12">
                    <div class="rounded-3xl bg-gradient-to-br from-violet-600 to-indigo-700 text-white p-8 shadow-xl shadow-violet-200">
                        <div class="text-3xl mb-3">📊</div>
                        <h3 class="font-semibold text-xl mb-2">تتبع المبيعات اليومية</h3>
                        <p class="text-violet-100 text-sm leading-relaxed">سجل المبيعات اليومية والمقبوضات، وتابع كمية الخبز المباع بالكيلو من لوحة تحكم واحدة.</p>
                    </div>
                    <div class="rounded-3xl bg-gradient-to-br from-pink-500 to-rose-500 text-white p-8 shadow-xl shadow-pink-200">
                        <div class="text-3xl mb-3">🌾</div>
                        <h3 class="font-semibold text-xl mb-2">رصيد القمح مقابل الخبز</h3>
                        <p class="text-pink-50 text-sm leading-relaxed">يمكن للعميل تزويد المخبز بالقمح والحصول على خبز على دفعات، بسعر خاص تحدده أنت.</p>
                    </div>
                </section>

                <section class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 pb-20">
                    <div class="bg-white rounded-2xl shadow-sm p-6">
                        <div class="text-2xl mb-2">👥</div>
                        <h3 class="font-semibold mb-1">إدارة العملاء</h3>
                        <p class="text-gray-500 text-sm">أضف وعدّل واحذف بيانات العملاء مع كامل سجلاتهم.</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm p-6">
                        <div class="text-2xl mb-2">✅</div>
                        <h3 class="font-semibold mb-1">حالة الدفع</h3>
                        <p class="text-gray-500 text-sm">حوّل حالة العملية إلى مدفوعة مع رسالة تأكيد فورية.</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm p-6">
                        <div class="text-2xl mb-2">🏬</div>
                        <h3 class="font-semibold mb-1">مخابز متعددة</h3>
                        <p class="text-gray-500 text-sm">أضف عدة مخابز، ولكل مخبز حساب دخول ولوحة تحكم منفصلة.</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm p-6">
                        <div class="text-2xl mb-2">🔁</div>
                        <h3 class="font-semibold mb-1">اشتراك شهري</h3>
                        <p class="text-gray-500 text-sm">يقوم مالك المنصة بتجديد اشتراك كل مخبز يدويًا.</p>
                    </div>
                </section>
            </main>

            <footer class="text-center text-sm text-gray-500 py-6">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </footer>
        </div>
    </body>
</html>
