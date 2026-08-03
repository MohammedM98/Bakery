<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-gray-100 text-gray-900" style="font-family: 'Cairo', sans-serif;">
        <div class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
            <div class="text-5xl mb-4">⛔</div>
            <h1 class="text-2xl font-bold mb-2">الاشتراك غير مفعّل</h1>
            <p class="text-gray-600 max-w-md mb-6">
                اشتراك مخبزكم منتهٍ أو غير مفعّل حاليًا. يرجى التواصل مع مالك المنصة لتجديد الاشتراك للوصول إلى لوحة التحكم.
            </p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="px-5 py-2 rounded-md bg-gray-800 text-white text-sm font-medium hover:bg-gray-900">
                    تسجيل الخروج
                </button>
            </form>
        </div>
    </body>
</html>
