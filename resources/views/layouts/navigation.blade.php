@php
    $links = Auth::user()->isSuperAdmin()
        ? [['route' => 'admin.bakeries.index', 'pattern' => 'admin.bakeries.*', 'label' => 'المخابز والاشتراكات', 'icon' => 'building']]
        : [
            ['route' => 'panel.dashboard', 'pattern' => 'panel.dashboard', 'label' => 'لوحة التحكم', 'icon' => 'home'],
            ['route' => 'panel.customers.index', 'pattern' => 'panel.customers.*', 'label' => 'العملاء', 'icon' => 'users'],
            ['route' => 'panel.sales.index', 'pattern' => 'panel.sales.*', 'label' => 'عمليات البيع', 'icon' => 'chart'],
            ['route' => 'panel.settings.edit', 'pattern' => 'panel.settings.edit', 'label' => 'إعدادات الأسعار', 'icon' => 'tag'],
        ];

    $icons = [
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 11.5 12 4l9 7.5M5 9.5V20h5v-6h4v6h5V9.5" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-1.5a3.5 3.5 0 0 0-5-3.163M9 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 0c-3.314 0-7 1.79-7 4v1.5a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5V16c0-2.21-3.686-4-7-4Zm8-4a3 3 0 1 0 0-6" />',
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 19V10m6 9V5m6 14v-7m5-8-8.5 8.5-4-4L3 14" />',
        'tag' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="m11.5 3.5 7 7a2 2 0 0 1 0 2.83l-5.17 5.17a2 2 0 0 1-2.83 0l-7-7V4a.5.5 0 0 1 .5-.5h7.5Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M7.5 7.5h.01" />',
        'building' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M4 21V6a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v15M4 21h16M14 21v-9a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v9M7.5 8h.01M7.5 11h.01M7.5 14h.01M17.5 13h.01M17.5 16h.01" />',
    ];
@endphp

<aside x-data="{ mobileOpen: false }" class="lg:w-64 shrink-0">
    <!-- Mobile top bar -->
    <div class="lg:hidden flex items-center justify-between bg-white border-b border-gray-100 px-4 py-3">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-lg font-bold text-violet-700">
            🥖 <span>{{ config('app.name') }}</span>
        </a>
        <button @click="mobileOpen = ! mobileOpen" class="p-2 rounded-md text-gray-500 hover:bg-gray-100">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{ hidden: mobileOpen }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{ hidden: ! mobileOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div :class="{ hidden: ! mobileOpen }" class="hidden lg:flex lg:flex-col h-full bg-white lg:min-h-screen lg:border-s lg:border-gray-100">
        <div class="hidden lg:flex items-center gap-2 px-6 py-6 text-xl font-bold text-violet-700">
            🥖 <span>{{ config('app.name') }}</span>
        </div>

        <nav class="flex-1 px-3 space-y-1">
            @foreach ($links as $link)
                @php $active = request()->routeIs($link['pattern']); @endphp
                <a href="{{ route($link['route']) }}"
                   class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition
                          {{ $active ? 'bg-violet-600 text-white shadow-sm shadow-violet-200' : 'text-gray-500 hover:bg-violet-50 hover:text-violet-700' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        {!! $icons[$link['icon']] !!}
                    </svg>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="px-3 pb-6 pt-4 border-t border-gray-100">
            <x-dropdown align="right" width="56">
                <x-slot name="trigger">
                    <button class="w-full flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-violet-100 text-violet-700 font-semibold">
                            {{ mb_substr(Auth::user()->name, 0, 1) }}
                        </span>
                        <span class="flex-1 text-start truncate">{{ Auth::user()->name }}</span>
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 20 20" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-dropdown-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</aside>
