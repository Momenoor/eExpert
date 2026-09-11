<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $action === 'approve' ? __('Confirm Approval') : __('Confirm Rejection') }}</title>
    @vite('resources/css/print.css')
    <link rel="stylesheet" href="{{ asset('fonts/Boutros.css') }}">
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
        <div class="inline-flex items-center justify-center w-14 h-14 {{ $action === 'approve' ? 'bg-green-100' : 'bg-red-100' }} rounded-full mb-3">
            @if ($action === 'approve')
                <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            @else
                <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            @endif
        </div>
        <h1 class="text-xl font-bold text-gray-800">
            {{ $action === 'approve' ? __('Confirm Approval') : __('Confirm Rejection') }}
        </h1>
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 text-sm text-gray-700 space-y-1">
        <div><strong>{{ __('Employee') }}:</strong> {{ $leaveRequest->party->name }}</div>
        <div>
            <strong>{{ __('From') }}:</strong> {{ $leaveRequest->start_date->locale('ar')->translatedFormat('d F Y') }}
            —
            <strong>{{ __('To') }}:</strong> {{ $leaveRequest->end_date->locale('ar')->translatedFormat('d F Y') }}
        </div>
        <div><strong>{{ __('Requested Leave Type') }}:</strong> {{ $leaveRequest->requested_leave_type?->getLabel() ?? '—' }}</div>
    </div>

    <form method="POST" action="{{ url()->full() }}">
        @csrf
        <button type="submit"
                class="w-full {{ $action === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} text-white font-semibold py-3 rounded-lg text-sm transition">
            {{ $action === 'approve' ? __('Yes, Approve') : __('Yes, Reject') }}
        </button>
    </form>

    <p class="text-xs text-gray-400 text-center mt-4">
        {{ __('This decides the request immediately. Close this page instead if you did not mean to click it.') }}
    </p>

    <div class="mt-8 pt-6 border-t border-gray-100 text-[10px] text-gray-400 text-center">
        {{ config('app.name') }} · {{ now()->format('Y') }} · {{ __('All rights reserved.') }}
    </div>

</div>
</body>
</html>
