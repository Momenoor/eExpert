<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Response Recorded') }}</title>
    @vite('resources/css/print.css')
    <link rel="stylesheet" href="{{asset('fonts/Boutros.css')}}">
    <style>
        * {
            font-family: 'Boutros MBC Dinkum' !important;
        }
        body {
            font-family: 'Boutros MBC Dinkum' !important;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6 text-right" dir="rtl">
<div class="bg-white rounded-xl shadow-lg max-w-md w-full p-8 text-center">

    @if($type === 'accepted')
        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-green-700 mb-2">{{ __('Date Confirmed') }}</h1>
    @elseif($type === 'disputed')
        <div class="inline-flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-yellow-700 mb-2">{{ __('Dispute Submitted') }}</h1>
    @else
        <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-red-700 mb-2">{{ __('Invalid Link') }}</h1>
    @endif

    <p class="text-gray-600 text-sm leading-relaxed">{{ $message }}</p>
    <div class="mt-8 pt-6 border-t border-gray-100 text-[10px] text-gray-400">
        {{ config('app.name') }} · {{ now()->format('Y') }}<br>
        {{ __('All rights reserved.') }}
    </div>

    @if($matter)
        <div class="mt-4 bg-gray-50 rounded-lg p-3 text-xs text-gray-500">
            {{ __('Matter') }}: <span class="font-semibold">{{ $matter->year }}/{{ $matter->number }}</span>
        </div>
    @endif

</div>
</body>
</html>
