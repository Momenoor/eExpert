<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('fonts/Boutros.css') }}">
    <title>{{ __('Leave Request') }}</title>
    <style>
        * {
            font-family: 'Boutros MBC Dinkum' !important;
        }
        body {
            font-family: 'Boutros MBC Dinkum' !important;
            background: #f4f4f7;
            margin: 0;
            padding: 0;
            color: #333;
            direction: rtl;
            text-align: right;
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
            padding: 30px 40px;
            text-align: center;
        }

        .header.approved { background: #16a34a; }
        .header.rejected { background: #dc2626; }

        .header h1 {
            color: #fff;
            font-size: 20px;
            margin: 0;
        }

        .header p {
            color: #ecfdf5;
            font-size: 13px;
            margin: 6px 0 0;
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
            border-right: 4px solid #1B3A5C;
            border-left: none;
            border-radius: 4px;
            padding: 16px 20px;
            margin: 20px 0;
        }

        .info-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-box td {
            padding: 6px 0;
            font-size: 14px;
            text-align: right;
        }

        .info-box td:first-child {
            color: #666;
            width: 40%;
        }

        .info-box td:last-child {
            font-weight: bold;
            color: #1B3A5C;
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
    @php $approved = $leaveRequest->isApproved(); @endphp
    <div class="header {{ $approved ? 'approved' : 'rejected' }}">
        <img src="{{ url('images/logo-dark-for-email.png') }}" alt="Logo" style="max-width:200px!important;width: 200px!important; height: auto;">
        <h1>{{ config('app.name') }}</h1>
        <p>{{ $leaveRequest->status->getLabel() }}</p>
    </div>

    <div class="body">
        <p><strong>{{ __('Dear :name', ['name' => $leaveRequest->party->name]) }}،</strong></p>
        <p>
            @if ($approved)
                {{ __('Your leave request has been approved.') }}
            @else
                {{ __('Your leave request has been rejected.') }}
            @endif
        </p>

        <div class="info-box">
            <table>
                <tr>
                    <td>{{ __('From') }}:</td>
                    <td>{{ $leaveRequest->start_date->locale('ar')->translatedFormat('d F Y') }}</td>
                </tr>
                <tr>
                    <td>{{ __('To') }}:</td>
                    <td>{{ $leaveRequest->end_date->locale('ar')->translatedFormat('d F Y') }}</td>
                </tr>
                @if ($leaveRequest->approved_comment)
                    <tr>
                        <td>{{ __('Note') }}:</td>
                        <td>{{ $leaveRequest->approved_comment }}</td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    <div class="footer">
        {{ config('app.name') }} · {{ now()->format('Y') }}<br>
        {{ __('All rights reserved.') }}<br>
        {{ __('This email was sent automatically. Do not reply.') }}
    </div>
</div>
</body>
</html>
