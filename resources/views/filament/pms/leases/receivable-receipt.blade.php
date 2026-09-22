<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receivable Receipt — {{ $lease->government_contract_number ?? $lease->id }}</title>
    @include('filament.pms.leases.partials.print-styles')
    <style>
        .schedule-wrap { padding: 24px; }
        .schedule-wrap p { font-size: 9pt; color: #6b7280; margin: 0 0 8px; }
        table.schedule { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.schedule th, table.schedule td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        table.schedule th { background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    @php
        // If the office already placed the "Instalments Table" field on the
        // letterhead, that positioned/sized version is the schedule — the
        // plain fallback table below only covers what it doesn't: no
        // template yet, or a template that hasn't placed that field.
        $hasPlacedTable = (bool) $template?->pages
            ->flatMap(fn ($page) => $page->fields)
            ->contains(fn ($field) => \App\Services\PMS\LeasePrintFieldResolver::isTable($field->field_key));
    @endphp

    {{-- The letterhead is optional branding — a fallback schedule below
         covers the case where there's no template or it doesn't place its
         own table field. --}}
    @if ($template)
        @include('filament.pms.leases.partials.template-pages', [
            'template' => $template,
            'resolve' => fn (string $fieldKey, ?string $language) => $resolver->resolve($lease, $fieldKey, $language),
            'notConfiguredMessage' => "This owner group's Receivable Receipt letterhead hasn't been set up yet.",
            'installments' => $lease->installments,
        ])
    @endif

    @unless ($hasPlacedTable)
        <div class="schedule-wrap">
            @unless ($template)
                <p>This owner group has no Receivable Receipt letterhead set up yet — showing the payment schedule only. Upload one under <em>Leasing → Print Templates</em>.</p>
            @endunless

            <table class="schedule">
                <thead>
                    <tr>
                        <th>Due Date</th>
                        <th>Net</th>
                        <th>VAT</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lease->installments as $installment)
                        <tr>
                            <td>{{ $installment->due_date?->format('d/m/Y') }}</td>
                            <td>{{ number_format((float) $installment->net_amount, 2) }}</td>
                            <td>{{ number_format((float) $installment->vat_amount, 2) }}</td>
                            <td>{{ number_format((float) $installment->total_due_amount, 2) }}</td>
                            <td>{{ $installment->payment_status?->getLabel() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endunless
</body>
</html>
