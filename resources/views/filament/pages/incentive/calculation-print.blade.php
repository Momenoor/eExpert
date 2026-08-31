<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Incentive Calculation') }} — {{ $calculation->name }}</title>
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
        <h1 class="text-xl font-bold text-blue-900">{{ __('Incentive Calculation Report') }}</h1>
        <h2 class="text-base text-blue-600 mt-1">{{ $calculation->name }}</h2>
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
        @if($calculation->finalized_at)
            <p>
                <span class="font-semibold text-gray-700">{{ __('Finalized') }}:</span>
                {{ $calculation->finalized_at->format('d M Y H:i') }}
            </p>
        @endif
        <p>
            <span class="font-semibold text-gray-700">{{ __('Created By') }}:</span>
            {{ $calculation->creator->name }}
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
        <p class="text-xs text-gray-500 mb-1">{{ __('Total Fee Lines') }}</p>
        <p class="text-base font-semibold text-gray-800">{{ $lines->count() }}</p>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Total Base Amount') }}</p>
        <p class="text-base font-semibold text-gray-800">AED {{ number_format($lines->sum('base_amount'), 2) }}</p>
    </div>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Total Net Amount') }}</p>
        <p class="text-base font-semibold text-green-700">AED {{ number_format($lines->sum('net_amount'), 2) }}</p>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Total Assistant Incentive') }}</p>
        <p class="text-base font-bold text-green-800">AED {{ number_format($assistantSummary->sum('total'), 2) }}</p>
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Total Extra Bonuses') }}</p>
        <p class="text-base font-semibold text-blue-700">
            AED {{ number_format($assistantSummary->sum('extra_amount'), 2) }}</p>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-lg p-3">
        <p class="text-xs text-gray-500 mb-1">{{ __('Total Penalties') }}</p>
        <p class="text-base font-semibold text-red-700">
            AED {{ number_format($assistantSummary->sum('penalty_amount'), 2) }}</p>
    </div>
</div>

{{-- Assistant Summary --}}
<div class="mb-8">
    <h3 class="text-sm font-bold text-blue-900 border-b border-blue-400 pb-1 mb-3">
        {{ __('Assistant Summary') }}
    </h3>
    <table class="w-full text-xs border-collapse">
        <thead>
        <tr class="bg-blue-900 text-white">
            <th class="px-3 py-2 text-left">{{ __('Assistant') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Matters') }}</th>
            <th class="px-3 py-2 text-center">{{ __('Meets Min (6)') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Fees Amount') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Share Total') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Extra %') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Extra Amount') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Penalty %') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Penalty') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Fixed Ded.') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($assistantSummary as $row)
            <tr class="{{ $loop->even ? 'bg-blue-50' : 'bg-white' }} border-b border-gray-200">
                <td class="px-3 py-2 font-semibold">{{ $row['party']->name }}</td>
                <td class="px-3 py-2 text-right">
                        <span
                            class="font-bold {{ $row['completed_matter_count'] >= 6 ? 'text-green-700' : 'text-red-600' }}">
                            {{ $row['completed_matter_count'] }}
                        </span>
                </td>
                <td class="px-3 py-2 text-center">
                    @if($row['meets_minimum'])
                        <span class="text-green-600 font-bold">✓</span>
                    @else
                        <span class="text-red-600 font-bold">✗</span>
                    @endif
                </td>
                <td class="px-3 py-2 text-right">{{ number_format($row['fees_amount'], 2) }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($row['share_total'], 2) }}</td>
                <td class="px-3 py-2 text-right text-green-700">
                    {{ $row['extra_percentage'] > 0 ? '+' . $row['extra_percentage'] . '%' : '—' }}
                </td>
                <td class="px-3 py-2 text-right text-green-700">
                    {{ $row['extra_amount'] > 0 ? number_format($row['extra_amount'], 2) : '—' }}
                </td>
                <td class="px-3 py-2 text-right text-red-600">
                    {{ $row['minimum_penalty_pct'] > 0 ? '-' . $row['minimum_penalty_pct'] . '%' : '—' }}
                </td>
                <td class="px-3 py-2 text-right text-red-600">
                    {{ $row['penalty_amount'] > 0 ? number_format($row['penalty_amount'], 2) : '—' }}
                </td>
                <td class="px-3 py-2 text-right text-red-600">
                    {{ $row['fixed_deduction'] > 0 ? number_format($row['fixed_deduction'], 2) : '—' }}
                    @if($row['fixed_deduction'] > 0 && $row['fixed_deduction_reason'])
                        <div class="text-gray-400 text-[10px]">{{ $row['fixed_deduction_reason'] }}</div>
                    @endif
                </td>
                <td class="px-3 py-2 text-right font-bold text-green-800">
                    AED {{ number_format($row['total'], 2) }}
                </td>
            </tr>
            <tr class="{{ $loop->even ? 'bg-blue-50' : 'bg-white' }} border-b border-gray-200">
                <td colspan="11" class="px-3 py-2">
                    <table class="w-full text-[10px] border-collapse">
                        <thead>
                        <tr class="text-gray-500">
                            <th class="px-2 py-1 text-left">{{ __('Matter') }}</th>
                            <th class="px-2 py-1 text-left">{{ __('Difficulty') }}</th>
                            <th class="px-2 py-1 text-right">{{ __('Days') }}</th>
                            <th class="px-2 py-1 text-right">{{ __('Fee (excl. VAT)') }}</th>
                            <th class="px-2 py-1 text-right">{{ __('Percentage') }}</th>
                            <th class="px-2 py-1 text-left">{{ __('Deductions') }}</th>
                            <th class="px-2 py-1 text-right">{{ __('Share') }}</th>
                            <th class="px-2 py-1 text-right">{{ __('Extra') }}</th>
                            <th class="px-2 py-1 text-right">{{ __('Penalty') }}</th>
                            <th class="px-2 py-1 text-right">{{ __('Total') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($row['matters'] as $m)
                            <tr class="border-t border-gray-100">
                                <td class="px-2 py-1">{{ $m['matter_reference'] }}</td>
                                <td class="px-2 py-1">{{ $m['difficulty']?->getLabel() ?? '—' }}</td>
                                <td class="px-2 py-1 text-right text-gray-400">{{ $m['completion_days'] ?? '—' }}</td>
                                <td class="px-2 py-1 text-right">{{ number_format($m['fee_amount_excl_vat'], 2) }}</td>
                                <td class="px-2 py-1 text-right">
                                    @if($m['percentage_override'] !== null)
                                        {{ $m['percentage_override'] }}% <span class="text-gray-400">({{ __('override') }})</span>
                                    @else
                                        {{ $m['percentage'] }}%
                                    @endif
                                </td>
                                <td class="px-2 py-1">
                                    @forelse($m['deductions'] as $d)
                                        <div class="text-red-600">−{{ $d->percentage }}% ({{ __($d->type) }})</div>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td class="px-2 py-1 text-right">{{ number_format($m['share_amount'], 2) }}</td>
                                <td class="px-2 py-1 text-right text-green-700">
                                    {{ $m['extra_amount'] > 0 ? number_format($m['extra_amount'], 2) : '—' }}
                                    @if($m['extra_reason'])
                                        <div class="text-gray-400">{{ $m['extra_reason'] }}</div>
                                    @endif
                                </td>
                                <td class="px-2 py-1 text-right text-red-600">
                                    {{ $m['penalty_amount'] > 0 ? number_format($m['penalty_amount'], 2) : '—' }}
                                    @if($m['penalty_reason'])
                                        <div class="text-gray-400">{{ $m['penalty_reason'] }}</div>
                                    @endif
                                </td>
                                <td class="px-2 py-1 text-right font-semibold">{{ number_format($m['total_amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr class="bg-blue-100 font-bold border-t-2 border-blue-900">
            <td colspan="3" class="px-3 py-2 text-right text-blue-900">{{ __('Grand Total') }}</td>
            {{-- Fees Amount is per-assistant (unsplit, matter-level), so it isn't
                 summed here: a matter shared by co-assistants would be double-
                 counted, unlike Share Total and the columns below it, which are
                 already split per assistant. --}}
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2 text-right">AED {{ number_format($assistantSummary->sum('share_total'), 2) }}</td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2 text-right text-green-700">
                AED {{ number_format($assistantSummary->sum('extra_amount'), 2) }}</td>
            <td class="px-3 py-2"></td>
            <td class="px-3 py-2 text-right text-red-600">
                AED {{ number_format($assistantSummary->sum('penalty_amount'), 2) }}</td>
            <td class="px-3 py-2 text-right text-red-600">
                AED {{ number_format($assistantSummary->sum('fixed_deduction'), 2) }}</td>
            <td class="px-3 py-2 text-right text-green-800">
                AED {{ number_format($assistantSummary->sum('total'), 2) }}</td>
        </tr>
        </tfoot>
    </table>
</div>

{{-- Calculation Lines --}}
<div class="mb-8">
    <h3 class="text-sm font-bold text-blue-900 border-b border-blue-400 pb-1 mb-3">
        {{ __('Calculation Lines') }}
    </h3>
    <table class="w-full text-xs border-collapse">
        <thead>
        <tr class="bg-blue-900 text-white">
            <th class="px-3 py-2 text-left">{{ __('Matter') }}</th>
            <th class="px-3 py-2 text-left">{{ __('Difficulty') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Days') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Fee (excl. VAT)') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Rate %') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Base Amount') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Deductions') }}</th>
            <th class="px-3 py-2 text-right">{{ __('Net Amount') }}</th>
            <th class="px-3 py-2 text-left">{{ __('Detail') }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($lines as $line)
            <tr class="{{ $loop->even ? 'bg-blue-50' : 'bg-white' }} border-b border-gray-200">
                <td class="px-3 py-2 font-semibold">
                    {{ $line->matter->year }}/{{ $line->matter->number }}
                </td>
                <td class="px-3 py-2">
                    @php $diffClass = match($line->matter->difficulty) {
                            \App\Enums\MatterDifficulty::EASY   => 'bg-green-100 text-green-800',
                            \App\Enums\MatterDifficulty::MEDIUM => 'bg-yellow-100 text-yellow-800',
                            \App\Enums\MatterDifficulty::HARD   => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800',
                        }; @endphp
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $diffClass }}">
                            {{ $line->matter->difficulty?->getLabel() ?? '—' }}
                        </span>
                </td>
                <td class="px-3 py-2 text-right text-gray-400">{{ $line->completion_days ?? '—' }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($line->fee_amount_excl_vat, 2) }}</td>
                <td class="px-3 py-2 text-right">
                    {{ $line->effective_percentage }}%
                    @if($line->committee_adjustment != 0)
                        <div class="text-gray-400 text-xs">
                            ({{ $line->base_percentage }}
                            %{{ $line->committee_adjustment > 0 ? '+' : '' }}{{ $line->committee_adjustment }}%)
                        </div>
                    @endif
                </td>
                <td class="px-3 py-2 text-right">{{ number_format($line->base_amount, 2) }}</td>
                <td class="px-3 py-2 text-right {{ $line->total_deduction_pct > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                    {{ $line->total_deduction_pct > 0 ? '-' . $line->total_deduction_pct . '%' : '0%' }}
                </td>
                <td class="px-3 py-2 text-right font-bold {{ $line->net_amount < $line->base_amount ? 'text-yellow-700' : 'text-green-700' }}">
                    {{ number_format($line->net_amount, 2) }}
                </td>
                <td class="px-3 py-2">
                    @foreach($line->deductions as $d)
                        <div class="text-red-600">
                            −{{ $d->percentage }}%
                            <span class="text-gray-500">({{ __($d->type) }})</span>
                        </div>
                        @if($d->notes)
                            <div class="text-gray-400 text-xs">{{ $d->notes }}</div>
                        @endif
                    @endforeach
                </td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr class="bg-blue-100 font-bold border-t-2 border-blue-900">
            <td colspan="5" class="px-3 py-2 text-right text-blue-900">{{ __('Totals') }}</td>
            <td class="px-3 py-2 text-right">AED {{ number_format($lines->sum('base_amount'), 2) }}</td>
            <td class="px-3 py-2 text-right text-red-600">
                −{{ number_format($lines->avg('total_deduction_pct'), 1) }}% avg
            </td>
            <td class="px-3 py-2 text-right text-green-700">AED {{ number_format($lines->sum('net_amount'), 2) }}</td>
            <td></td>
        </tr>
        </tfoot>
    </table>
</div>

{{-- Notes --}}
@if($calculation->notes)
    <div class="mb-8">
        <h3 class="text-sm font-bold text-blue-900 border-b border-blue-400 pb-1 mb-3">{{ __('Notes') }}</h3>
        <p class="text-gray-600 text-xs leading-relaxed">{{ $calculation->notes }}</p>
    </div>
@endif

{{-- Footer --}}
<div class="flex justify-between items-center border-t border-gray-200 pt-4 mt-8 text-xs text-gray-400">
    <span>{{ config('app.name') }}</span>
    <span>{{ __('Incentive Calculation') }} — {{ $calculation->name }}</span>
    <span>{{ now()->format('d M Y') }}</span>
</div>


</body>
</html>
