@props([
    'title' => null,
])

@php
    $reportTitle = $title ?? (method_exists($this, 'getTitle') ? $this->getTitle() : (method_exists($this, 'getHeading') ? $this->getHeading() : __('Report')));
    $indicators = method_exists($this, 'getTable') ? $this->getTable()->getFilterIndicators() : [];
@endphp

<div class="report-print-header hidden print:block mb-6 border-b-2 border-gray-800 pb-3">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $reportTitle }}</h1>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mt-0.5">{{ config('app.name', 'eExpert') }}</p>
        </div>
        <div class="text-end text-xs text-gray-600 space-y-0.5">
            <div><span class="font-semibold text-gray-700">{{ __('Printed On') }}:</span> {{ now()->format('Y-m-d H:i') }}</div>
            @if(auth()->check())
                <div><span class="font-semibold text-gray-700">{{ __('Printed By') }}:</span> {{ auth()->user()->name }}</div>
            @endif
        </div>
    </div>

    <div class="mt-3 pt-2 border-t border-gray-200 text-xs flex flex-wrap items-center gap-1.5">
        <span class="font-bold text-gray-800">{{ __('Applied Filters') }}:</span>
        @if (count($indicators))
            @foreach ($indicators as $indicator)
                @php
                    $indicatorLabel = $indicator instanceof \Filament\Tables\Filters\Indicator ? $indicator->getLabel() : (string) $indicator;
                @endphp
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 border border-gray-300">
                    {{ $indicatorLabel }}
                </span>
            @endforeach
        @else
            <span class="text-gray-500 italic">{{ __('None (All Records)') }}</span>
        @endif
    </div>
</div>
