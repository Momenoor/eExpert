<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ __('Dispute Received Date') }}</title>
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
<div class="bg-white rounded-xl shadow-lg max-w-lg w-full p-8">

    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-red-100 rounded-full mb-3">
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-800">{{ __('Dispute Received Date') }}</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ __('Matter') }}: <span class="font-semibold">{{ $matter->year }}/{{ $matter->number }}</span>
            @if($matter->court)
                · {{ $matter->court->name }}
            @endif
        </p>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-800">
        <strong>{{ __('Current Received Date') }}:</strong>
        {{ \Carbon\Carbon::parse($matterRequest->extra['current_distributed_at'] ?? null)->locale('ar')->translatedFormat('d F Y') }}
    </div>

    <form method="POST"
          action="{{ $submitUrl }}"
          class="space-y-5">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('Correct Received Date') }} <span class="text-red-500">*</span>
            </label>
            <input type="date"
                   name="proposed_distributed_at"
                   max="{{ now()->format('Y-m-d') }}"
                   value="{{ old('proposed_distributed_at') }}"
                   required
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            @error('proposed_distributed_at')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('Reason for Dispute') }} <span class="text-red-500">*</span>
            </label>
            <textarea
                name="comment"
                rows="4"
                required
                minlength="10"
                placeholder="{{ __('Please explain why the received date is incorrect...') }}"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('comment') }}</textarea>
            @error('comment')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-lg text-sm transition">
            {{ __('Submit Dispute') }}
        </button>
    </form>

    <div class="mt-8 pt-6 border-t border-gray-100 text-[10px] text-gray-400 text-center">
        {{ config('app.name') }} · {{ now()->format('Y') }} · {{ __('All rights reserved.') }}
    </div>

</div>
</body>
</html>
