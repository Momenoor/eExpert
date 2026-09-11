{{--
    The journal voucher, formatted for keying into QuickBooks by hand.

    There is no QuickBooks integration and this does not pretend to be one: the
    office types the entry in, so what matters is that the two columns are easy
    to read off and that the totals visibly agree.
--}}
<div class="fi-section space-y-4 p-4 text-sm">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <div class="fi-color-gray text-xs uppercase tracking-wide opacity-70">{{ __('Period') }}</div>
            <div class="font-semibold">{{ $voucher['period'] }}</div>
        </div>
        <div>
            <div class="fi-color-gray text-xs uppercase tracking-wide opacity-70">{{ __('Employees') }}</div>
            <div class="font-semibold">{{ $voucher['employee_count'] }}</div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-start">
            <thead>
                <tr class="border-b">
                    <th class="py-2 text-start font-semibold">{{ __('Account') }}</th>
                    <th class="py-2 text-end font-semibold">{{ __('Debit') }}</th>
                    <th class="py-2 text-end font-semibold">{{ __('Credit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($voucher['debits'] as $line)
                    <tr class="border-b border-dashed">
                        <td class="py-1.5">
                            {{ __($line['account']) }}
                            @if ($line['detail'])
                                <span class="fi-color-gray opacity-70">— {{ $line['detail'] }}</span>
                            @endif
                        </td>
                        <td class="py-1.5 text-end tabular-nums">{{ number_format($line['amount'], 2) }}</td>
                        <td class="py-1.5 text-end opacity-40">—</td>
                    </tr>
                @endforeach

                @foreach ($voucher['credits'] as $line)
                    <tr class="border-b border-dashed">
                        {{-- Indented the way a paper voucher indents its credit side. --}}
                        <td class="py-1.5 ps-6">
                            {{ __($line['account']) }}
                            @if ($line['detail'])
                                {{-- Loan clearing is itemised per employee, since
                                     the loan ledger it reconciles against is. --}}
                                <span class="fi-color-gray opacity-70">— {{ $line['detail'] }}</span>
                            @endif
                        </td>
                        <td class="py-1.5 text-end opacity-40">—</td>
                        <td class="py-1.5 text-end tabular-nums">{{ number_format($line['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 font-semibold">
                    <td class="py-2">{{ __('Total') }}</td>
                    <td class="py-2 text-end tabular-nums">{{ number_format($voucher['total_debit'], 2) }}</td>
                    <td class="py-2 text-end tabular-nums">{{ number_format($voucher['total_credit'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($voucher['balanced'])
        <p class="fi-color-success text-xs">
            {{ __('Debits and credits agree.') }}
        </p>
    @else
        {{--
            Stated rather than silently swallowed. An unbalanced voucher means
            a payslip line is missing an account, and keying it in as-is would
            leave QuickBooks refusing the entry with no explanation.
        --}}
        <p class="fi-color-danger text-xs font-semibold">
            {{ __('This voucher does not balance. Regenerate the run before posting it.') }}
        </p>
    @endif
</div>
