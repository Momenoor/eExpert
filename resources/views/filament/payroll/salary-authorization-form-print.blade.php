<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Salary Authorization Form') }} — {{ $form['period'] }}</title>

    {{--
        English/LTR always, regardless of the panel's locale — this is a bank
        form read by the exchange house's own back office, not an internal
        document. Inline, self-contained styles for the same reason the other
        print views here are: no CDN dependency on the print path.
    --}}
    <style>
        @page {
            size: A4;
            margin: 6mm 10mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.2;
            color: #111;
            background: #fff;
        }

        h1 {
            font-size: 13pt;
            margin: 0 0 6px;
            text-align: center;
            text-decoration: underline;
        }

        .company {
            font-size: 12pt;
            font-weight: 700;
            margin-bottom: 4px;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        table.meta td {
            border: 1px solid #111;
            padding: 2px 8px;
        }

        table.meta td.label {
            font-weight: 700;
            width: 18%;
            background: #f2f2f2;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
        }

        table.lines th, table.lines td {
            border: 1px solid #111;
            padding: 3px 6px;
            vertical-align: top;
        }

        table.lines th {
            background: #f2f2f2;
            font-size: 9pt;
            text-transform: uppercase;
        }

        .personal-no {
            width: 15%;
        }

        .detail {
            color: #555;
            font-size: 8.5pt;
            line-height: 1.15;
        }

        .amount {
            text-align: end;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        /*
         * `tfoot` prints on every page a table spans, like `thead` — right for
         * a header, wrong for a running total that should read once, at the
         * end. Forcing it back to an ordinary row group stops the repeat
         * without changing anything else about how it renders.
         */
        table.lines tfoot {
            display: table-row-group;
        }

        tfoot td {
            font-weight: 700;
        }

        tfoot td.totals-label {
            text-align: end;
        }

        .authorization {
            margin-top: 10px;
            font-size: 9.5pt;
        }

        .signature-line {
            margin-top: 18px;
            margin-bottom: 50px;
            text-align: center;
            font-weight: 700;
        }

        .instructions {
            margin-top: 8px;
            font-size: 8.5pt;
        }

        .internal-use {
            margin-top: 8px;
            border: 1px dashed #111;
            padding: 5px 10px;
            font-size: 9pt;
        }

        .internal-use .heading {
            font-weight: 700;
            text-decoration: underline;
            margin-bottom: 3px;
        }

        .internal-use table {
            width: 100%;
            border-collapse: collapse;
        }

        .internal-use td {
            padding: 8px 6px 8px 0;

            white-space: nowrap;
        }

        .internal-use td.fill {
            width: 50%;
            border-bottom: 1px solid #111;
        }

        @media print {
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

    {{--
        Deliberately plain English strings below, not __() — this form must
        stay in English for the exchange house no matter what locale the
        panel itself is running in, so routing it through translation would
        be wrong even if `ar.json` covered every key.
    --}}
    <div class="company">AL FARDAN EXCHANGE - UAE</div>
    <h1>Salary Authorization and Upload Form - EMONEY LITE</h1>

    <table class="meta">
        <tr>
            <td class="label">Company Name</td>
            <td colspan="3">{{ $form['company_name'] }}</td>
        </tr>
        <tr>
            <td class="label">Employer ID</td>
            <td>{{ $form['employer_id'] ?? '—' }}</td>
            <td class="label">Trade License</td>
            <td>{{ $form['trade_license'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Salary Month</td>
            <td>{{ $form['period'] }}</td>
            <td class="label">Employees</td>
            <td>{{ $form['employee_count'] }}</td>
        </tr>
        <tr>
            {{-- Receipt No is left blank for the exchange house to fill in on
                 receipt — nothing this system assigns. GL is the office's own
                 fixed account number at the exchange (Financial Settings →
                 Payroll → GL Number) — the same on every submission. --}}
            <td class="label">Receipt No</td>
            <td></td>
            <td class="label">GL</td>
            <td>{{ $form['gl_number'] ?? '—' }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th class="personal-no">Personal No.</th>
                <th>Employee Name</th>
                <th>Mobile</th>
                <th class="amount">Salary</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($form['lines'] as $line)
                @php
                    // Only lines the employee's profile actually has — a
                    // missing IBAN prints as nothing, not a row of dashes that
                    // reads like a data-entry omission.
                    $bankDetails = array_filter([
                        $line['bank_name'] ?? null,
                        filled($line['account_no'] ?? null) ? 'AC# '.$line['account_no'] : null,
                        filled($line['iban'] ?? null) ? 'IBAN '.$line['iban'] : null,
                        filled($line['routing_code'] ?? null) ? 'Routing code '.$line['routing_code'] : null,
                    ]);
                @endphp
                <tr>
                    <td class="personal-no">{{ $line['personal_no'] ?? '—' }}</td>
                    <td>
                        {{ $line['employee_name'] }}
                        @if ($bankDetails !== [])
                            <br>
                            <span class="detail">{!! implode('<br>', array_map('e', $bankDetails)) !!}</span>
                        @endif
                    </td>
                    <td>{{ $line['mobile'] ?? '—' }}</td>
                    <td class="amount">{{ number_format($line['salary'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="totals-label">Total Salary</td>
                <td class="amount">{{ number_format($form['total_salary'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="totals-label">Services Charge + SIF Handling</td>
                <td class="amount">{{ number_format($form['service_charge'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="totals-label">VAT</td>
                <td class="amount">{{ number_format($form['vat'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="totals-label">Total Amount</td>
                <td class="amount">{{ number_format($form['total_amount'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="authorization">
        I, the undersigned, hereby authorize the exchange house to issue salaries to all our employees for the period mentioned above.
    </p>

    <p class="signature-line">Authorized Name, Signature &amp; Seal</p>

    <p class="instructions">
        Please email the scanned form with seal ans stamp on wps@alfardanexchange.com or fax it to 04-3523532.
    </p>

    <div class="internal-use">
        <div class="heading">For internal use by the exchange house only</div>
        <table>
            <tr>
                <td>GL Code:</td>
                <td class="fill"></td>
                <td>Corporate ID:</td>
                <td class="fill"></td>
            </tr>
            <tr>
                <td>Total Salary:</td>
                <td class="fill"></td>
                <td>Charges:</td>
                <td class="fill"></td>
            </tr>
            <tr>
                <td>Gross Salary:</td>
                <td class="fill"></td>
                <td>Processed By:</td>
                <td class="fill"></td>
            </tr>
        </table>
    </div>
</body>
</html>
