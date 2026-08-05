<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="antialiased">
        <div
            x-data="{ show: false, message: '' }"
            x-on:toast.window="
                message = $event.detail.message;
                show = true;
                clearTimeout(window.__bakeryToastTimer);
                window.__bakeryToastTimer = setTimeout(() => show = false, 3000);
            "
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed top-4 inset-x-0 z-[60] flex justify-center px-4 pointer-events-none"
            style="display: none;"
        >
            <div class="bg-green-600 text-white rounded-xl shadow-lg px-5 py-3 text-sm font-medium max-w-md text-center pointer-events-auto">
                <span x-text="message"></span>
            </div>
        </div>

        @if (session('status'))
            <div x-data x-init="window.dispatchEvent(new CustomEvent('toast', { detail: { message: @js(session('status')) } }))"></div>
        @endif

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
        @livewireScripts
    </body>
</html>
