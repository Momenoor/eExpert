<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoices — {{ $lease->government_contract_number ?? $lease->id }}</title>
    @include('filament.pms.leases.partials.print-styles')
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    @forelse ($lease->installments as $installment)
        @include('filament.pms.leases.partials.template-pages', [
            'template' => $template,
            'resolve' => fn (string $fieldKey, ?string $language) => $resolver->resolve($lease, $fieldKey, $language, $installment),
            'notConfiguredMessage' => "This owner group's Tax Invoice letterhead hasn't been set up yet.",
        ])
    @empty
        <div class="not-configured">
            <p><strong>This lease has no instalments to invoice yet.</strong></p>
        </div>
    @endforelse
</body>
</html>
