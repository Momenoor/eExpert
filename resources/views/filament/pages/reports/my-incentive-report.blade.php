@php
    $calculation = $this->selectedCalculation();
    $extra = $this->periodTotals();
@endphp

<x-filament-panels::page>
    @if ($calculation)
        <x-filament::section
            :heading="$calculation->name"
            :description="__('Finalized :date', ['date' => $calculation->finalized_at?->translatedFormat('d M Y') ?? '—'])"
            icon="heroicon-o-banknotes"
        >
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Share') }}</dt>
                    <dd class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ number_format($this->shareTotal(), 2) }} <span class="text-sm font-normal">AED</span>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Extra') }}</dt>
                    <dd class="text-lg font-semibold text-success-600 dark:text-success-400">
                        {{ number_format((float) ($extra?->extra_amount ?? 0), 2) }}
                        @if (($extra?->extra_percentage ?? 0) > 0)
                            <span class="text-sm font-normal">(+{{ $extra->extra_percentage }}%)</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Penalty') }}</dt>
                    <dd class="text-lg font-semibold text-danger-600 dark:text-danger-400">
                        {{ number_format((float) ($extra?->penalty_amount ?? 0), 2) }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Fixed Deduction') }}</dt>
                    <dd class="text-lg font-semibold text-danger-600 dark:text-danger-400">
                        {{ number_format((float) ($extra?->fixed_deduction ?? 0), 2) }}
                    </dd>
                    @if ($extra?->fixed_deduction_reason)
                        <dd class="text-xs text-gray-500 dark:text-gray-400">{{ $extra->fixed_deduction_reason }}</dd>
                    @endif
                </div>

                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('Net Total') }}</dt>
                    <dd class="text-xl font-bold text-primary-600 dark:text-primary-400">
                        {{ number_format($this->netTotal(), 2) }} <span class="text-sm font-normal">AED</span>
                    </dd>
                </div>
            </dl>

            @unless ($extra?->meets_minimum ?? true)
                <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" class="mt-4">
                    <x-slot name="footer">
                        {{ __('This period did not reach the minimum number of completed matters, so a penalty was applied.') }}
                    </x-slot>
                </x-filament::callout>
            @endunless
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
