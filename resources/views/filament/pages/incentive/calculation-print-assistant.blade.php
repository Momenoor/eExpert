<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Incentive Statement') }} — {{ $assistantSummary['party']->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                font-size: 11px;
            }

            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="bg-white text-gray-800 font-sans text-sm p-10">

{{-- Print Button --}}
<div class="no-print flex justify-end mb-6">
    <button onclick="window.print()"
            class="flex items-center gap-2 bg-blue-900 hover:bg-blue-800 text-white text-sm font-medium px-5 py-2 rounded shadow">
        🖨 {{ __('Print') }}
    </button>
</div>

{{-- Header --}}
<div class="flex justify-between items-start border-b-2 border-blue-900 pb-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-blue-900">{{ __('Incentive Statement') }}</h1>
        <h2 class="text-base text-blue-600 mt-1">{{ $assistantSummary['party']->name }}</h2>
        <h3 class="text-xs text-gray-500 mt-1">{{ $calculation->name }}</h3>
    </div>
    <div class="text-right text-xs text-gray-500 space-y-1">
        <p>
            <span class="font-semibold text-gray-700">{{ __('Period') }}:</span>
            {{ $calculation->period_start->format('d M Y') }} — {{ $calculation->period_end->format('d M Y') }}
        </p>
        <p>
            <span class="font-semibold text-gray-700">{{ __('Status') }}:</span>
            @if($calculation->status === 'finalized')
                <span
                    class="inline-block bg-green-100 text-green-800 text-xs font-bold px-2 py-0.5 rounded uppercase">{{ __('Finalized') }}</span>
            @else
                <span
                    class="inline-block bg-yellow-100 text-yellow-800 text-xs font-bold px-2 py-0.5 rounded uppercase">{{ __('Draft') }}</span>
            @endif
        </p>
        <p>
            <span class="font-semibold text-gray-700">{{ __('Printed') }}:</span>
            {{ now()->format('d M Y H:i') }}
        </p>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Completed') }}</p>
        <p class="text-base font-semibold {{ $assistantSummary['meets_minimum'] ? 'text-green-700' : 'text-red-600' }}">
            {{ $assistantSummary['completed_matter_count'] }}
            {{ $assistantSummary['meets_minimum'] ? '✓' : '✗' }}
        </p>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Share Total') }}</p>
        <p class="text-base font-semibold text-gray-800">AED {{ number_format($assistantSummary['share_total'], 2) }}</p>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Extra Bonus') }}</p>
        <p class="text-base font-semibold text-green-700">
            {{ $assistantSummary['extra_percentage'] > 0 ? '+' . $assistantSummary['extra_percentage'] . '% · ' : '' }}
            AED {{ number_format($assistantSummary['extra_amount'], 2) }}
        </p>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Penalty') }}</p>
        <p class="text-base font-semibold text-red-700">
            {{ $assistantSummary['minimum_penalty_pct'] > 0 ? '-' . $assistantSummary['minimum_penalty_pct'] . '% · ' : '' }}
            AED {{ number_format($assistantSummary['penalty_amount'], 2) }}
        </p>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Fixed Ded.') }}</p>
        <p class="text-base font-semibold text-red-700">AED {{ number_format($assistantSummary['fixed_deduction'], 2) }}</p>
        @if($assistantSummary['fixed_deduction'] > 0 && $assistantSummary['fixed_deduction_reason'])
            <p class="text-[10px] text-gray-400 mt-0.5">{{ $assistantSummary['fixed_deduction_reason'] }}</p>
        @endif
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Total') }}</p>
        <p class="text-base font-bold text-blue-900">AED {{ number_format($assistantSummary['total'], 2) }}</p>
    </div>
</div>

{{-- Matters Detail --}}
<div class="mb-8">
    <h3 class="text-sm font-bold text-blue-900 border-b border-blue-400 pb-1 mb-3">
        {{ __('Calculation Lines') }}
    </h3>
    <table class="w-full text-xs border-collapse">
        <thead>
        <tr class="bg-blue-900 text-white">
            <th class="px-3 py-2 text-left">{{ __('Matter') }}</th>
            <th class="px-3 py-2 text-left">{{ __('Court / Type') }}</th>
            <th class="px-3 py-2 text-left">{{ __('Difficulty') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Days') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Fee (excl. VAT)') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Base %') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Rate %') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Base Amount') }}</th>
            <th class="px-3 py-2 text-left">{{ __('Deductions') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Share') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Extra') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Penalty') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($assistantSummary['matters'] as $m)
            <tr class="{{ $loop->even ? 'bg-blue-50' : 'bg-white' }} border-b border-gray-200">
                <td class="px-3 py-2 font-semibold">{{ $m['matter_reference'] }}</td>
                <td class="px-3 py-2 text-gray-600">
                    {{ $m['type_name'] ?? '—' }}
                    @if($m['court_name'])
                        <div class="text-gray-400">{{ $m['court_name'] }}</div>
                    @endif
                    @if($m['commissioning'])
                        <div class="text-gray-400">{{ $m['commissioning']->getLabel() }}</div>
                    @endif
                </td>
                <td class="px-3 py-2">{{ $m['difficulty']?->getLabel() ?? '—' }}</td>
                <td class="px-3 py-2 text-right text-gray-400">{{ $m['completion_days'] ?? '—' }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($m['fee_amount_excl_vat'], 2) }}</td>
                <td class="px-3 py-2 text-right text-gray-500">
                    {{ $m['base_percentage'] }}%{{ $m['committee_adjustment'] != 0 ? ($m['committee_adjustment'] > 0 ? '+' : '').$m['committee_adjustment'].'%' : '' }}
                </td>
                <td class="px-3 py-2 text-right">
                    @if($m['percentage_override'] !== null)
                        {{ $m['percentage_override'] }}% <span class="text-gray-400">({{ __('override') }})</span>
                    @else
                        {{ $m['percentage'] }}%
                    @endif
                </td>
                <td class="px-3 py-2 text-right text-gray-500">{{ number_format($m['base_amount'], 2) }}</td>
                <td class="px-3 py-2">
                    @forelse($m['deductions'] as $d)
                        <div class="text-red-600">−{{ $d->percentage }}% ({{ __($d->type) }})</div>
                        @if($d->notes)
                            <div class="text-gray-400">{{ $d->notes }}</div>
                        @endif
                    @empty
                        —
                    @endforelse
                </td>
                <td class="px-3 py-2 text-right">{{ number_format($m['share_amount'], 2) }}</td>
                <td class="px-3 py-2 text-right text-green-700">
                    {{ $m['extra_amount'] > 0 ? number_format($m['extra_amount'], 2) : '—' }}
                    @if($m['extra_reason'])
                        <div class="text-gray-400">{{ $m['extra_reason'] }}</div>
                    @endif
                </td>
                <td class="px-3 py-2 text-right text-red-600">
                    {{ $m['penalty_amount'] > 0 ? number_format($m['penalty_amount'], 2) : '—' }}
                    @if($m['penalty_reason'])
                        <div class="text-gray-400">{{ $m['penalty_reason'] }}</div>
                    @endif
                </td>
                <td class="px-3 py-2 text-right font-semibold">{{ number_format($m['total_amount'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr class="bg-blue-100 font-bold border-t-2 border-blue-900">
            <td colspan="9" class="px-3 py-2 text-right text-blue-900">{{ __('Grand Total') }}</td>
            <td class="px-3 py-2 text-right">AED {{ number_format($assistantSummary['share_total'], 2) }}</td>
            <td class="px-3 py-2 text-right text-green-700">AED {{ number_format($assistantSummary['extra_amount'], 2) }}</td>
            <td class="px-3 py-2 text-right text-red-600">AED {{ number_format($assistantSummary['penalty_amount'], 2) }}</td>
            <td class="px-3 py-2 text-right text-blue-900">AED {{ number_format($assistantSummary['total'], 2) }}</td>
        </tr>
        </tfoot>
    </table>
</div>

{{-- Footer --}}
<div class="flex justify-between items-center border-t border-gray-200 pt-4 mt-8 text-xs text-gray-400">
    <span>{{ config('app.name') }}</span>
    <span>{{ __('Incentive Statement') }} — {{ $assistantSummary['party']->name }}</span>
    <span>{{ now()->format('d M Y') }}</span>
</div>

</body>
</html>
