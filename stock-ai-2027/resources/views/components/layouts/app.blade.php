@props(['title' => 'Trang chủ'])

<!DOCTYPE html>
<html lang="vi" x-data="tallstackui_darkTheme({ name: 'stock-ai-dark-theme' })">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · {{ config('app.name', 'StockAI') }}</title>
        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-bind:class="{ dark: darkTheme }" {{ $attributes->class(['min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100']) }}>

        <x-layout>
            <x-slot:header>
                <x-layout.header>
                    <x-slot:left>
                        <span class="font-semibold">{{ $title }}</span>
                    </x-slot:left>
                    <x-slot:right>
                        <x-theme-switch />
                    </x-slot:right>
                </x-layout.header>
            </x-slot:header>
            <x-slot:menu>
                <x-side-bar collapsible thin-scroll navigate>
                    <x-slot:brand>
                        <a href="{{ url('/') }}" wire:navigate class="flex items-center gap-3 px-5 py-6">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-primary-600 font-bold text-white">S</span>
                            <span class="text-lg font-bold">StockAI <span class="text-primary-600 dark:text-primary-400">2027</span></span>
                        </a>
                    </x-slot:brand>
                    <x-slot:brand-collapsed>
                        <a href="{{ url('/') }}" wire:navigate aria-label="StockAI — Trang chủ" class="mx-auto my-6 flex size-10 items-center justify-center rounded-xl bg-primary-600 font-bold text-white">S</a>
                    </x-slot:brand-collapsed>
                    <x-side-bar.item text="Trang chủ" icon="home" :route="url('/')" :current="request()->is('/')" />
                    <x-side-bar.item text="Công ty" icon="building-office" :route="route('companies.index')" :current="request()->routeIs('companies.index')" />
                    <x-side-bar.item text="Giám sát queue" icon="queue-list" :route="route('queues.index')" :current="request()->routeIs('queues.index')" />
                    <x-side-bar.item text="API Reference" icon="code-bracket" :href="url('/scalar')" />
                </x-side-bar>
            </x-slot:menu>
            {{ $slot }}
        </x-layout>
        <x-toast />
        <x-dialog />
        @livewireScripts
    </body>
</html>
