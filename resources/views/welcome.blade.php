<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-amber-50 text-gray-900" style="font-family: 'Cairo', sans-serif;">
        <div class="min-h-screen flex flex-col">
            <header class="max-w-5xl mx-auto w-full flex items-center justify-between px-6 py-6">
                <div class="flex items-center gap-2 text-2xl font-bold text-amber-700">
                    🥖 <span>{{ config('app.name') }}</span>
                </div>
                <nav class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="px-5 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">
                            لوحة التحكم
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-5 py-2 rounded-md bg-amber-700 text-white text-sm font-medium hover:bg-amber-800">
                            تسجيل الدخول
                        </a>
                    @endauth
                </nav>
            </header>

            <main class="flex-1 max-w-5xl mx-auto w-full px-6">
                <section class="text-center py-16">
                    <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mb-4">
                        نظام إدارة المخابز
                    </h1>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                        منصة متكاملة لإدارة المبيعات اليومية، حسابات العملاء، ورصيد القمح مقابل الخبز — مع لوحة تحكم خاصة لكل مخبز واشتراك شهري يديره مالك المنصة.
                    </p>
                </section>

                <section class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 pb-20">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="text-3xl mb-3">📊</div>
                        <h3 class="font-semibold text-lg mb-2">تتبع المبيعات اليومية</h3>
                        <p class="text-gray-600 text-sm">سجل المبيعات اليومية والمقبوضات، وتابع كمية الخبز المباع بالكيلو من لوحة تحكم واحدة.</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="text-3xl mb-3">🌾</div>
                        <h3 class="font-semibold text-lg mb-2">رصيد القمح مقابل الخبز</h3>
                        <p class="text-gray-600 text-sm">يمكن للعميل تزويد المخبز بالقمح والحصول على خبز على دفعات، بسعر خاص تحدده أنت.</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="text-3xl mb-3">👥</div>
                        <h3 class="font-semibold text-lg mb-2">إدارة العملاء</h3>
                        <p class="text-gray-600 text-sm">أضف وعدّل واحذف بيانات العملاء (الاسم ورقم الجوال) مع كامل سجلاتهم.</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="text-3xl mb-3">✅</div>
                        <h3 class="font-semibold text-lg mb-2">حالة الدفع</h3>
                        <p class="text-gray-600 text-sm">حدد العمليات غير المدفوعة، وعند السداد حوّل حالتها إلى مدفوعة مع رسالة تأكيد فورية.</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="text-3xl mb-3">🏬</div>
                        <h3 class="font-semibold text-lg mb-2">مخابز متعددة</h3>
                        <p class="text-gray-600 text-sm">يمكن لمالك المنصة إضافة عدة مخابز، ولكل مخبز حساب دخول ولوحة تحكم منفصلة.</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="text-3xl mb-3">🔁</div>
                        <h3 class="font-semibold text-lg mb-2">اشتراك شهري</h3>
                        <p class="text-gray-600 text-sm">يقوم مالك المنصة بتجديد اشتراك كل مخبز يدويًا شهريًا.</p>
                    </div>
                </section>
            </main>

            <footer class="text-center text-sm text-gray-500 py-6">
                &copy; {{ date('Y') }} {{ config('app.name') }}
            </footer>
        </div>
    </body>
</html>
