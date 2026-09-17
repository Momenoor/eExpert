<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{asset('fonts/Boutros.css')}}">
    <title>{{ __('Request Status Update') }}</title>
    <style>
        * {
            font-family: 'Boutros MBC Dinkum', sans-serif !important;
        }

        body {
            background: #f4f4f7;
            margin: 0;
            padding: 0;
            color: #333;
            direction: {{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }};
            text-align: {{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
        }

        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: #1B3A5C;
            padding: 30px 40px;
            text-align: center;
        }

        .header h1 {
            color: #fff;
            font-size: 20px;
            margin: 10px 0 0;
        }

        .body {
            padding: 32px 40px;
        }

        .body p {
            font-size: 14px;
            line-height: 1.7;
            margin: 0 0 16px;
        }

        .info-box {
            background: #f0f5fb;
            border- {{ app()->getLocale() == 'ar' ? 'right' : 'left' }}: 4px solid #1B3A5C;
            border-radius: 4px;
            padding: 16px 20px;
            margin: 20px 0;
        }

        .info-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-box td {
            padding: 8px 0;
            font-size: 14px;
        }

        .info-box td:first-child {
            color: #666;
            width: 35%;
        }

        .info-box td:last-child {
            font-weight: bold;
            color: #1B3A5C;
        }

        .actions {
            text-align: center;
            margin: 32px 0 16px;
        }

        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            text-decoration: none;
            background: #1B3A5C;
            color: #ffffff !important;
        }

        .footer {
            background: #f4f4f7;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <img src="{{ url('images/logo-dark-for-email.png') }}" alt="Logo" style="max-width:200px; height: auto;">
        <h1>{{ config('app.name') }}</h1>
    </div>

    <div class="body">
        <p><strong>{{ __('Hello') }}،</strong></p>
        <p>{{ __('There is an update in your request. Below are the details:') }}</p>

        <div class="info-box">
            <table>
                <tr>
                    <td>{{ __('Request ID') }}:</td>
                    <td>#{{ $matterRequest->id }}</td>
                </tr>
                <tr>
                    <td>{{ __('Matter') }}:</td>
                    <td>{{ $matter->year }}/{{ $matter->number }}</td>
                </tr>
                <tr>
                    <td>{{ __('Status') }}:</td>
                    <td>{{ $statusLabel }}</td>
                </tr>
                <tr>
                    <td>{{ __('Reviewer Comment') }}:</td>
                    <td>{{ $matterRequest->approved_comment ?? '---' }}</td>
                </tr>
                <tr>
                    <td>{{ __('Action Date') }}:</td>
                    <td>{{ $matterRequest->approved_at->locale(app()->getLocale())->translatedFormat('d F Y - h:i A') }}</td>
                </tr>
            </table>
        </div>

        @if($matterRequest->attachments()->exists())
            <div style="margin-top: 20px;">
                <p><strong>{{ __('Attachments') }}:</strong></p>
                <ul style="font-size: 14px; color: #1B3A5C; padding-inline-start: 20px;">
                    @foreach($matterRequest->attachments as $attachment)
                        <li>
                            <a href="{{ Storage::disk('public')->url($attachment->path) }}"
                               style="color: #1B3A5C; text-decoration: underline;">
                                {{ $attachment->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="actions">
            <a href="{{ url('/mms/matter-requests/' . $matterRequest->id) }}" class="btn">
                {{ __('View Request Details') }}
            </a>
        </div>
    </div>

    <div class="footer">
        {{ config('app.name') }} · {{ now()->format('Y') }}<br>
        {{ __('All rights reserved.') }}
    </div>
</div>
</body>
</html>
