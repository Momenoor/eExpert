<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Quotation') }} — {{ $quotation->party->name }}</title>

    {{-- Inline, self-contained styles for the same reason every other print
         view in this app is: no CDN dependency on the print path. --}}
    <style>
        @page {
            size: A4;
            margin: 14mm 12mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, "Noto Naskh Arabic", Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #111;
            background: #fff;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

        .office { font-size: 14pt; font-weight: 700; }
        .muted { color: #555; font-size: 9.5pt; }

        h1 {
            font-size: 13pt;
            margin: 0 0 2px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .meta { text-align: end; }
        .meta div { white-space: nowrap; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        th {
            text-align: start;
            font-size: 9.5pt;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-bottom: 1.5px solid #111;
            padding: 6px 4px;
        }

        td {
            padding: 5px 4px;
            border-bottom: 1px solid #e5e5e5;
        }

        .amount {
            text-align: end;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        th.amount { text-align: end; }

        tfoot td {
            border-top: 2px solid #111;
            border-bottom: none;
            font-weight: 700;
            padding-top: 8px;
        }

        .schedule {
            font-size: 10pt;
        }

        .schedule li {
            margin-bottom: 4px;
        }

        @media print {
            .no-print { display: none !important; }
            tr { break-inside: avoid; }
        }

        .no-print { margin-bottom: 16px; }

        .no-print button {
            font: inherit;
            padding: 6px 14px;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    <header>
        <div>
            <div class="office">{{ config('app.name') }}</div>
            <div class="muted">{{ __('Property Management') }}</div>
        </div>
        <div class="meta">
            <h1>{{ __('Quotation') }}</h1>
            <div class="muted">{{ __('Prospect / Tenant') }}: {{ $quotation->party->name }}</div>
            <div class="muted">{{ __('Valid Until') }}: {{ $quotation->validity_date->translatedFormat('d M Y') }}</div>
            <div class="muted">{{ __('Printed') }}: {{ now()->translatedFormat('d M Y') }}</div>
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th>{{ __('Unit') }}</th>
                <th class="amount">{{ __('Offered Rent') }}</th>
                <th class="amount">{{ __('VAT') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->units as $unit)
                <tr>
                    <td>{{ $unit->building?->name }} — {{ $unit->unit_number }}</td>
                    <td class="amount">{{ number_format($unit->pivot->offered_rent, 2) }}</td>
                    <td class="amount">{{ number_format($unit->pivot->vat_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>{{ __('Base Rent') }}</td>
                <td class="amount" colspan="2">{{ number_format($quotation->base_rent, 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('VAT') }}</td>
                <td class="amount" colspan="2">{{ number_format($quotation->vat_amount, 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('Attestation Fee (Estimate)') }}</td>
                <td class="amount" colspan="2">{{ number_format($quotation->attestation_fee_estimate, 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('Security Deposit') }}</td>
                <td class="amount" colspan="2">{{ number_format($quotation->security_deposit, 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('Total Due') }}</td>
                <td class="amount" colspan="2">{{ number_format($quotation->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <h1 style="margin-top: 10px;">{{ __('Payment Schedule') }}</h1>
    <ol class="schedule">
        @foreach ($paymentSchedule as $amount)
            <li>{{ __('Instalment :n', ['n' => $loop->iteration]) }}: {{ number_format($amount, 2) }} AED</li>
        @endforeach
    </ol>
</body>
</html>
