<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div class="min-h-screen flex flex-col lg:flex-row bg-slate-50">
            @include('layouts.navigation')

            <div class="flex-1 min-w-0">
                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white border-b border-gray-100">
                        <div class="px-4 sm:px-6 lg:px-10 py-6">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                @if (session('status'))
                    <div class="px-4 sm:px-6 lg:px-10 mt-4">
                        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3">
                            {{ session('status') }}
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="px-4 sm:px-6 lg:px-10 mt-4">
                        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <!-- Page Content -->
                <main class="px-4 sm:px-6 lg:px-10">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
