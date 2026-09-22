<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenancy Contract — {{ $lease->government_contract_number ?? $lease->id }}</title>
    @include('filament.pms.leases.partials.print-styles')
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    @include('filament.pms.leases.partials.template-pages', [
        'template' => $template,
        'resolve' => fn (string $fieldKey, ?string $language) => $resolver->resolve($lease, $fieldKey, $language),
        'notConfiguredMessage' => "This contract format's print template hasn't been set up yet.",
    ])
</body>
</html>
