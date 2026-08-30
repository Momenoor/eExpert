<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('System Offline') }} - {{ config('app.name', 'JPA Emirates') }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            max-width: 32rem;
            width: 90%;
            text-align: center;
        }

        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 4.5rem;
            height: 4.5rem;
            background-color: #fee2e2;
            color: #dc2626;
            border-radius: 50%;
            margin-bottom: 1.5rem;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #111827;
        }

        p {
            font-size: 1rem;
            line-height: 1.5;
            color: #4b5563;
            margin-bottom: 1.5rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: #fef3c7;
            color: #92400e;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 9999px;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            text-decoration: none;
            transition: background-color 0.2s;
            font-size: 1rem;
            font-weight: 1000;
        }

        .btn:hover {
            background-color: #1d4ed8;
            border: 1px solid #d1d5db;
        }

        .btn-secondary {
            background-color: transparent;
            color: #4b5563;
            border: 1px solid #d1d5db;
            margin-inline-start: 0.5rem;
        }

        .btn-secondary:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24"
             stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.07a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091.458.114.93.07 1.399"/>
        </svg>
    </div>
    <br>
    <h1 class="badge">{{ __('Scheduled Maintenance') }}</h1>
    <p>{{ filled($message ?? null) ? $message : __('The system is temporarily offline for maintenance. Please try again soon.') }}</p>
    <div>
        @auth
            {{-- Already signed in as a non-admin account: the login page would just
                 bounce back here, so offer to sign out and try a different account. --}}
            <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">
                {{ __('Signed in as :name — this account does not have maintenance access.', ['name' => auth()->user()->name]) }}
            </p>
            <form method="POST" action="{{ route('filament.admin.auth.logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn">{{ __('Sign Out') }}</button>
            </form>
        @else
            <a href="{{ url('/admin/login') }}" class="btn">{{ __('Admin Login') }}</a>
        @endauth
    </div>
</div>
</body>
</html>
