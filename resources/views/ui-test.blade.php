<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>TallStackUI Test</title>

    <x-analytics.google-tag />

    {{-- TallStackUI JS/CSS hooks --}}
    <tallstackui:script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-slate-100 p-10">
    <x-toast />

    <div class="max-w-4xl mx-auto space-y-8">
        <h1 class="text-3xl font-bold text-center">TallStackUI Test Page</h1>

        <div class="flex gap-3 justify-center">
            <x-button color="blue">Primary</x-button>
            <x-button color="teal">Teal</x-button>
            <x-button color="slate" light>Light</x-button>
            <x-button color="blue" outline>Outline</x-button>
        </div>

        <div class="flex gap-2 justify-center">
            <x-badge text="Blue badge" color="blue" />
            <x-badge text="Green badge" color="emerald" />
            <x-badge text="Rounded badge" color="teal" round />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <x-input placeholder="Name" />
            <x-input type="email" placeholder="Email" />

            <div class="col-span-2">
                <x-select.styled
                    placeholder="Select an option"
                    :options="[
                        ['label'=>'Option A','value'=>'a'],
                        ['label'=>'Option B','value'=>'b'],
                    ]"
                />
            </div>
        </div>

        <x-card>
            <x-slot:header>
                <div class="font-bold">TallStack Card</div>
            </x-slot:header>

            <p class="text-slate-600">
                If the select opens, and this card renders without Alpine errors,
                TallStackUI JS is working.
            </p>

            <x-slot:footer>
                <x-button
                    color="blue"
                    x-on:click="$dispatch('toast', { type: 'success', title: 'TallStackUI', message: 'JS is working ✅' })"
                >
                    Test Toast
                </x-button>
            </x-slot:footer>
        </x-card>

        <div class="flex gap-4 items-center justify-center">
            <x-icon name="heart" class="w-6 h-6 text-red-500" />
            <x-icon name="chat-bubble-left-right" class="w-6 h-6 text-blue-600" />
            <x-icon name="shield-check" class="w-6 h-6 text-green-600" />
        </div>
    </div>

    @livewireScripts
</body>
</html>
