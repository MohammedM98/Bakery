<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased text-gray-900 bg-gradient-to-br from-violet-50 via-slate-50 to-pink-50">
        <div class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
            <div class="bg-white rounded-2xl shadow-xl shadow-violet-100 border border-violet-50 px-8 py-10 max-w-md">
                <div class="text-5xl mb-4">⛔</div>
                <h1 class="text-2xl font-bold mb-2">الاشتراك غير مفعّل</h1>
                <p class="text-gray-600 mb-6">
                    اشتراك مخبزكم منتهٍ أو غير مفعّل حاليًا. يرجى التواصل مع مالك المنصة لتجديد الاشتراك للوصول إلى لوحة التحكم.
                </p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-5 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
                        تسجيل الخروج
                    </button>
                </form>
            </div>
        </div>
    </body>
</html>
