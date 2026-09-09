@props([
    'title' => null,
    'heading' => null,
    'subheading' => null,
])

@php
    $reportTitle = $title ?? $heading ?? (method_exists($this, 'getTitle') ? $this->getTitle() : (method_exists($this, 'getHeading') ? $this->getHeading() : __('Report')));
    $indicators = method_exists($this, 'getTable') ? $this->getTable()->getFilterIndicators() : [];
@endphp

<x-filament-panels::page>
<div class="report-print-container fi-page w-full">
    {{-- Embedded Print Styling for Total Panel Isolation --}}
    <style>
        @media print {
            @page {
                size: auto;
                margin: 12mm 10mm 12mm 10mm;
            }

            body {
                background: #ffffff !important;
                color: #111827 !important;
                font-size: 10pt !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            body * {
                visibility: hidden;
            }

            .report-print-container,
            .report-print-container * {
                visibility: visible !important;
            }

            .report-print-container {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            .report-print-header {
                display: block !important;
                visibility: visible !important;
            }

            aside,
            nav,
            header,
            .fi-sidebar,
            .fi-main-sidebar,
            .fi-topbar,
            .fi-breadcrumbs,
            .fi-header,
            .fi-page-header,
            .fi-header-actions-ctn,
            .fi-layout-sidebar-toggle-btn-ctn,
            .fi-sidebar-close-overlay,
            .fi-ta-header-toolbar,
            .fi-ta-filters,
            .fi-ta-filters-trigger-action-ctn,
            .fi-ta-actions,
            .fi-ta-col-wrp-actions,
            .fi-ta-selection-cell,
            .fi-ta-selection-checkbox,
            .fi-ta-selection-checkbox-column,
            .fi-ta-filter-indicators,
            .fi-pagination,
            .fi-announcement,
            .fi-wi-chart,
            #chat-widget,
            .no-print {
                display: none !important;
            }

            .fi-ta-ctn {
                box-shadow: none !important;
                border: none !important;
                background: transparent !important;
                overflow: visible !important;
            }

            .fi-ta-content {
                overflow: visible !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                page-break-inside: auto !important;
            }

            thead {
                display: table-header-group !important;
            }

            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            th, td {
                border: 1px solid #d1d5db !important;
                padding: 4px 6px !important;
                color: #111827 !important;
                background-color: transparent !important;
            }

            th {
                background-color: #f3f4f6 !important;
                font-weight: 700 !important;
            }

            .fi-badge {
                border: 1px solid #9ca3af !important;
                background: transparent !important;
                color: #111827 !important;
                box-shadow: none !important;
            }
        }
    </style>

    {{-- Printable Report Header (Active on print, cleanly styled) --}}
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

    {{-- Printable Report Body / Content --}}
    <div class="report-print-body w-full">
        {{ $slot }}
    </div>
</div>
</x-filament-panels::page>
