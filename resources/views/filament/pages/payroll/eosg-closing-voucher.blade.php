<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <label class="mb-2 block text-sm font-medium">{{ __('Closing Year') }}</label>
        <select
            wire:model.live="year"
            class="fi-select-input block w-full max-w-xs rounded-lg border-none bg-white text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
        >
            @foreach ($this->selectableYears as $selectableYear)
                <option value="{{ $selectableYear }}">{{ $selectableYear }}</option>
            @endforeach
        </select>
    </div>

    @if ($this->voucher['employee_count'] > 0)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            @include('filament.payroll.journal-voucher', ['voucher' => $this->voucher])
        </div>
    @else
        <div class="fi-section rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            {{ __('No gratuity was accrued for :year among employees applicable for EOSG.', ['year' => $year]) }}
        </div>
    @endif
</x-filament-panels::page>
