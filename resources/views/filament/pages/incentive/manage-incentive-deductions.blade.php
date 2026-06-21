{{-- resources/views/filament/pages/incentive/manage-deductions.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Summary Cards --}}
        <div class="grid grid-cols-3 gap-4">
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Lines') }}</p>
                <p class="text-2xl font-bold">{{ $this->record->lines->count() }}</p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Base Amount') }}</p>
                <p class="text-2xl font-bold">AED {{ number_format($this->getLinesTotalBase(), 2) }}</p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Net Amount') }}</p>
                <p class="text-2xl font-bold text-success-600">AED {{ number_format($this->getLinesTotalNet(), 2) }}</p>
            </x-filament::section>
        </div>

        {{-- Lines Table --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Calculation Lines') }}</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="bg-primary-700 text-white">
                        <th class="px-3 py-2 text-left">{{ __('Matter') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Difficulty') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Days') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Fee (excl. VAT)') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Rate %') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Base Amount') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Deductions %') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Net Amount') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Deduction Details') }}</th>
                        <th class="px-3 py-2 text-left">{{ __('Assistants') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($this->getLinesWithDeductions() as $item)
                        @php $line = $item['line']; @endphp
                        <tr class="{{ $loop->even ? 'bg-gray-50 dark:bg-gray-800' : 'bg-white dark:bg-gray-900' }} border-b dark:border-gray-700">
                            <td class="px-3 py-2 font-medium">{{ $line->matter->year }}/{{ $line->matter->number }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $line->difficulty === 'simple' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $line->difficulty === 'normal' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $line->difficulty === 'exceptional' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                    {{ __($line->difficulty) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right text-gray-500">{{ $line->completion_days ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($line->fee_amount_excl_vat, 2) }}</td>
                            <td class="px-3 py-2 text-right">
                                {{ $line->effective_percentage }}%
                                @if($line->committee_adjustment != 0)
                                    <span class="text-xs text-gray-400">({{ $line->base_percentage }}%{{ $line->committee_adjustment > 0 ? '+' : '' }}{{ $line->committee_adjustment }}%)</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($line->base_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right {{ $line->total_deduction_pct > 0 ? 'text-danger-600 font-medium' : 'text-gray-400' }}">
                                {{ $line->total_deduction_pct > 0 ? '-' . $line->total_deduction_pct . '%' : '0%' }}
                            </td>
                            <td class="px-3 py-2 text-right font-bold {{ $line->net_amount < $line->base_amount ? 'text-warning-600' : 'text-success-600' }}">
                                {{ number_format($line->net_amount, 2) }}
                            </td>
                            <td class="px-3 py-2">
                                @forelse($item['deductions'] as $d)
                                    <div class="text-xs text-danger-600">−{{ $d->percentage }}% <span class="text-gray-500">({{ __($d->type) }})</span></div>
                                @empty
                                    <span class="text-xs text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-3 py-2">
                                @foreach($item['assistants'] as $a)
                                    <div class="text-xs">
                                        <span class="font-medium">{{ $a['name'] }}</span>:
                                        <span class="text-success-600">{{ number_format($a['share'], 2) }}</span>
                                        @if($a['extra'] > 0)<span class="text-info-600"> +{{ number_format($a['extra'], 2) }}</span>@endif
                                        @if($a['penalty'] > 0)<span class="text-danger-600"> −{{ number_format($a['penalty'], 2) }}</span>@endif
                                        = <span class="font-bold">{{ number_format($a['total'], 2) }}</span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="bg-primary-50 dark:bg-primary-950 font-bold border-t-2 border-primary-200">
                        <td colspan="5" class="px-3 py-2 text-right">{{ __('Totals') }}</td>
                        <td class="px-3 py-2 text-right">AED {{ number_format($this->getLinesTotalBase(), 2) }}</td>
                        <td class="px-3 py-2 text-right text-danger-600">
                            −{{ number_format($this->record->lines->avg('total_deduction_pct'), 1) }}% avg
                        </td>
                        <td class="px-3 py-2 text-right text-success-600">AED {{ number_format($this->getLinesTotalNet(), 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

        {{-- Assistant Summary --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('Assistant Summary') }}</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                    <tr class="bg-primary-700 text-white">
                        <th class="px-3 py-2 text-left">{{ __('Assistant') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Completed Matters') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Meets Min (6)') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Share Total') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Extra %') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Extra Amount') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Penalty %') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Penalty Amount') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Fixed Deduction') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Total') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($this->getAssistantSummary() as $row)
                        @php $extra = $row['extra']; @endphp
                        <tr class="{{ $loop->even ? 'bg-gray-50 dark:bg-gray-800' : 'bg-white dark:bg-gray-900' }} border-b dark:border-gray-700">
                            <td class="px-3 py-2 font-medium">{{ $row['party']->name }}</td>
                            <td class="px-3 py-2 text-right">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $extra->completed_matter_count >= 6 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $extra->completed_matter_count }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($extra->meets_minimum)
                                    <x-filament::badge color="success">{{ __('Yes') }}</x-filament::badge>
                                @else
                                    <x-filament::badge color="danger">{{ __('No') }}</x-filament::badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($row['share_total'], 2) }}</td>
                            <td class="px-3 py-2 text-right text-success-600">{{ $extra->extra_percentage }}%</td>
                            <td class="px-3 py-2 text-right text-success-600">{{ number_format($extra->extra_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right text-danger-600">{{ $extra->minimum_penalty_pct > 0 ? '-' . $extra->minimum_penalty_pct . '%' : '—' }}</td>
                            <td class="px-3 py-2 text-right text-danger-600">{{ $extra->penalty_amount > 0 ? number_format($extra->penalty_amount, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-right text-danger-600">{{ $extra->fixed_deduction > 0 ? number_format($extra->fixed_deduction, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-right font-bold text-success-700">{{ number_format($row['total'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr class="bg-primary-50 dark:bg-primary-950 font-bold border-t-2 border-primary-200">
                        <td colspan="3" class="px-3 py-2 text-right">{{ __('Grand Total') }}</td>
                        <td class="px-3 py-2 text-right">AED {{ number_format($this->getGrandTotalShare(), 2) }}</td>
                        <td colspan="3"></td>
                        <td class="px-3 py-2 text-right text-danger-600">AED {{ number_format($this->record->assistantExtras->sum('penalty_amount'), 2) }}</td>
                        <td class="px-3 py-2 text-right text-danger-600">AED {{ number_format($this->record->assistantExtras->sum('fixed_deduction'), 2) }}</td>
                        <td class="px-3 py-2 text-right text-success-700">AED {{ number_format($this->getGrandTotal(), 2) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
