{{--
    Why this employee's loan deduction is the number it is.

    A payslip shows one figure for "Loan", but an employee can be repaying a
    staff loan and a petty cash advance at the same time, and an instalment
    missed in an earlier month is swept into the current run. One total cannot
    explain any of that, and the question it provokes — "why was 1,750 taken?" —
    is the one HR gets asked.
--}}
<div class="fi-section space-y-4 text-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-start">
            <thead>
                <tr class="border-b">
                    <th class="py-2 text-start font-semibold">{{ __('Type') }}</th>
                    <th class="py-2 text-start font-semibold">{{ __('Advance') }}</th>
                    <th class="py-2 text-start font-semibold">{{ __('Instalment') }}</th>
                    <th class="py-2 text-start font-semibold">{{ __('Due Period') }}</th>
                    <th class="py-2 text-end font-semibold">{{ __('Amount (AED)') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($installments as $installment)
                    <tr class="border-b border-dashed">
                        <td class="py-1.5">{{ $installment->loan->kind->getLabel() }}</td>
                        <td class="py-1.5 tabular-nums">{{ number_format((float) $installment->loan->principal, 2) }}</td>
                        <td class="py-1.5">
                            {{ __(':seq of :total', ['seq' => $installment->seq, 'total' => $installment->loan->months]) }}
                        </td>
                        <td class="py-1.5">
                            {{ $installment->due_period }}
                            @if ($installment->due_period !== $period)
                                {{-- An instalment carried over from a month this
                                     employee was not paid in. --}}
                                <span class="fi-color-warning">({{ __('carried over') }})</span>
                            @endif
                        </td>
                        <td class="py-1.5 text-end tabular-nums">{{ number_format((float) $installment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 font-semibold">
                    <td class="py-2" colspan="4">{{ __('Total') }}</td>
                    <td class="py-2 text-end tabular-nums">
                        {{ number_format((float) $installments->sum('amount'), 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($outstanding > 0)
        <p class="fi-color-gray text-xs">
            {{ __('Still outstanding after this run: :amount AED', ['amount' => number_format($outstanding, 2)]) }}
        </p>
    @else
        <p class="fi-color-success text-xs">{{ __('These advances are fully recovered.') }}</p>
    @endif
</div>
