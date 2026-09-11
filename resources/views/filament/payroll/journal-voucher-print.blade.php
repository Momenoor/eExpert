<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Journal Voucher') }} — {{ $voucher['period'] }}</title>

    {{--
        Styles are inline and self-contained on purpose. The four existing print
        views in this project pull Tailwind's v3 Play CDN into a v4 project — a
        different major version styling the page, an external network dependency
        on the print path, and a script its own authors mark dev-only. A voucher
        that has to print correctly in an office with flaky internet cannot
        depend on a CDN.

        Logical properties throughout (margin-inline, text-align: end) so the
        sheet mirrors correctly in Arabic without a second stylesheet.
    --}}
    <style>
        @page {
            size: A4;
            margin: 18mm 14mm;
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
            vertical-align: top;
        }

        /* Figures line up column-wise whatever the digits. */
        .amount {
            text-align: end;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        th.amount { text-align: end; }

        /* The credit side is indented the way a paper voucher indents it. */
        .credit-account { padding-inline-start: 22px; }

        .detail { color: #555; }

        tfoot td {
            border-top: 2px solid #111;
            border-bottom: none;
            font-weight: 700;
            padding-top: 8px;
        }

        .balance {
            font-size: 10pt;
            margin: 0 0 26px;
        }

        .balance.off { color: #b00020; font-weight: 700; }

        .signatures {
            display: flex;
            gap: 40px;
            margin-top: 40px;
        }

        .signatures div {
            flex: 1;
            border-top: 1px solid #111;
            padding-top: 6px;
            font-size: 9.5pt;
        }

        @media print {
            /* Nothing to click on paper. */
            .no-print { display: none !important; }

            tr { break-inside: avoid; }
        }

        .no-print {
            margin-bottom: 16px;
        }

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
            <div class="office">{{ $companyName }}</div>
            <div class="muted">{{ __('Payroll') }}</div>
        </div>
        <div class="meta">
            <h1>{{ __('Journal Voucher') }}</h1>
            <div class="muted">{{ __('Period') }}: {{ $voucher['period'] }}</div>
            <div class="muted">{{ __('Employees') }}: {{ $voucher['employee_count'] }}</div>
            <div class="muted">{{ __('Printed') }}: {{ now()->translatedFormat('d M Y') }}</div>
        </div>
    </header>

    <table>
        <thead>
            <tr>
                <th>{{ __('Account') }}</th>
                <th class="amount">{{ __('Debit') }}</th>
                <th class="amount">{{ __('Credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($voucher['debits'] as $line)
                <tr>
                    <td>
                        {{ __($line['account']) }}
                        @if ($line['detail'])
                            <span class="detail">— {{ $line['detail'] }}</span>
                        @endif
                    </td>
                    <td class="amount">{{ number_format($line['amount'], 2) }}</td>
                    <td class="amount">—</td>
                </tr>
            @endforeach

            @foreach ($voucher['credits'] as $line)
                <tr>
                    <td class="credit-account">
                        {{ __($line['account']) }}
                        @if ($line['detail'])
                            {{-- Loan clearing is itemised per employee, since the
                                 loan ledger it reconciles against is. --}}
                            <span class="detail">— {{ $line['detail'] }}</span>
                        @endif
                    </td>
                    <td class="amount">—</td>
                    <td class="amount">{{ number_format($line['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>{{ __('Total') }}</td>
                <td class="amount">{{ number_format($voucher['total_debit'], 2) }}</td>
                <td class="amount">{{ number_format($voucher['total_credit'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($voucher['balanced'])
        <p class="balance">{{ __('Debits and credits agree.') }}</p>
    @else
        {{-- Printed in red rather than omitted: an unbalanced voucher keyed into
             QuickBooks is refused there with no explanation of which line is
             missing an account. --}}
        <p class="balance off">{{ __('This voucher does not balance. Regenerate the run before posting it.') }}</p>
    @endif

    {{--
        Only the annual EOSG closing voucher carries a rollforward — the
        monthly Salaries voucher this same template also renders has no such
        key, so it never shows this table.
    --}}
    @isset($voucher['rollforward'])
        <h1 style="margin-top: 24px;">{{ __('Opening / Closing Balances Per Employee') }}</h1>

        <table>
            <thead>
                <tr>
                    <th>{{ __('Employee') }}</th>
                    <th class="amount">{{ __('Opening Balance') }}</th>
                    <th class="amount">{{ __('Current Year Amount') }}</th>
                    <th class="amount">{{ __('Closing Balance') }}</th>
                    <th class="amount">{{ __('Paid') }}</th>
                    <th class="amount">{{ __('Outstanding') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($voucher['rollforward'] as $row)
                    <tr>
                        <td>
                            {{ $row['party_name'] }}
                            @if ($row['left_during_year'])
                                <span class="detail">— {{ __('left :date', ['date' => $row['date_of_leaving']]) }}</span>
                            @endif
                        </td>
                        <td class="amount">{{ number_format($row['opening_balance'], 2) }}</td>
                        <td class="amount">{{ number_format($row['current_year_amount'], 2) }}</td>
                        <td class="amount">{{ number_format($row['closing_balance'], 2) }}</td>
                        <td class="amount">{{ number_format($row['paid_amount'], 2) }}</td>
                        <td class="amount">{{ number_format($row['outstanding'], 2) }}</td>
                        <td>{{ $row['payment_status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endisset

    <div class="signatures">
        <div>{{ __('Prepared by') }}</div>
        <div>{{ __('Reviewed by') }}</div>
        <div>{{ __('Approved by') }}</div>
    </div>
</body>
</html>
