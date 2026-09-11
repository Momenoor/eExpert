<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('fonts/Boutros.css') }}">
    <title>{{ __('New Leave Request') }}</title>
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
            background: #1B3A5C;
            padding: 30px 40px;
            text-align: center;
        }

        .header h1 {
            color: #fff;
            font-size: 20px;
            margin: 0;
        }

        .header p {
            color: #b3c6db;
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
            margin: 0 8px;
            text-align: center;
        }

        .btn-accept {
            background: #16a34a;
            color: #ffffff !important;
        }

        .btn-dispute {
            background: #dc2626;
            color: #ffffff !important;
        }

        .footer {
            background: #f4f4f7;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #888;
        }

        .note {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 4px;
            padding: 12px 16px;
            font-size: 12px;
            color: #92400e;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <img src="{{ url('images/logo-dark-for-email.png') }}" alt="Logo" style="max-width:200px!important;width: 200px!important; height: auto;">
        <h1>{{ config('app.name') }}</h1>
        <p>{{ __('New Leave Request') }}</p>
    </div>

    <div class="body">
        <p>
            {{ __('A new leave request has been submitted and requires your review.') }}
        </p>

        <div class="info-box">
            <table>
                <tr>
                    <td>{{ __('Employee') }}:</td>
                    <td>{{ $leaveRequest->party->name }}</td>
                </tr>
                <tr>
                    <td>{{ __('From') }}:</td>
                    <td>{{ $leaveRequest->start_date->locale('ar')->translatedFormat('d F Y') }}</td>
                </tr>
                <tr>
                    <td>{{ __('To') }}:</td>
                    <td>{{ $leaveRequest->end_date->locale('ar')->translatedFormat('d F Y') }}</td>
                </tr>
                <tr>
                    <td>{{ __('Requested Leave Type') }}:</td>
                    <td>{{ $leaveRequest->requested_leave_type?->getLabel() ?? '—' }}</td>
                </tr>
                <tr>
                    <td>{{ __('Days Requested') }}:</td>
                    <td>{{ rtrim(rtrim(number_format($leaveRequest->requestedDays(), 1), '0'), '.') }}</td>
                </tr>
                <tr>
                    <td>{{ __('Vacation Balance') }}:</td>
                    <td>{{ rtrim(rtrim(number_format($annualLeaveBalance, 1), '0'), '.') }}</td>
                </tr>
                <tr>
                    <td>{{ __('Reason') }}:</td>
                    <td>{{ $leaveRequest->comment ?: '—' }}</td>
                </tr>
            </table>
        </div>

        <div class="actions">
            <a href="{{ $approveUrl }}" class="btn btn-accept">
                ✓ {{ __('Approve') }}
            </a>
            <a href="{{ $rejectUrl }}" class="btn btn-dispute">
                ✗ {{ __('Reject') }}
            </a>
        </div>

        <div class="note">
            ⚠ {{ __('Clicking a button decides this request immediately and notifies the employee — no login required.') }}
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
