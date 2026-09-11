@php
    $total_balance = [
        'opening_balance' => 0,
        'current_year_amount' =>0,
        'closing_balance' => 0,
        'paid_amount' => 0,
        'outstanding' => 0,
];
@endphp
{{--
    Opening / current year / closing balance per employee — purely
    informational. The journal entry itself (journal-voucher.blade.php) still
    posts only the current year's movement; this exists so the running total
    behind that movement, and whether it has actually been paid out, is
    visible without opening each employee's file.
--}}
<div class="fi-section space-y-4 p-4 text-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-start">
            <thead>
            <tr class="border-b">
                <th class="py-2 text-start font-semibold">{{ __('Employee') }}</th>
                <th class="py-2 text-end font-semibold">{{ __('Opening Balance') }}</th>
                <th class="py-2 text-end font-semibold">{{ __('Current Year Amount') }}</th>
                <th class="py-2 text-end font-semibold">{{ __('Closing Balance') }}</th>
                <th class="py-2 text-end font-semibold">{{ __('Paid') }}</th>
                <th class="py-2 text-end font-semibold">{{ __('Outstanding') }}</th>
                <th class="py-2 text-center font-semibold">{{ __('Status') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rollforward as $row)
                @php
                    $total_balance['opening_balance'] += $row['opening_balance'];
                    $total_balance['current_year_amount'] += $row['current_year_amount'];
                    $total_balance['closing_balance'] += $row['closing_balance'];
                    $total_balance['paid_amount'] += $row['paid_amount'];
                    $total_balance['outstanding'] += $row['outstanding'];
                @endphp
                <tr class="border-b border-dashed">
                    <td class="py-1.5">
                        {{ $row['party_name'] }}
                        @if ($row['left_during_year'])
                            <span class="fi-color-gray opacity-70">
                                    — {{ __('left :date', ['date' => $row['date_of_leaving']]) }}
                                </span>
                        @endif
                    </td>
                    <td class="py-1.5 text-end tabular-nums">{{ number_format($row['opening_balance'], 2) }}</td>
                    <td class="py-1.5 text-end tabular-nums">{{ number_format($row['current_year_amount'], 2) }}</td>
                    <td class="py-1.5 text-end font-semibold tabular-nums">{{ number_format($row['closing_balance'], 2) }}</td>
                    <td class="py-1.5 text-end tabular-nums">{{ number_format($row['paid_amount'], 2) }}</td>
                    <td class="py-1.5 text-end tabular-nums">{{ number_format($row['outstanding'], 2) }}</td>
                    <td class="py-1.5 text-center">
                        @if ($row['payment_status'] === __('Paid in Full'))
                            <span class="fi-color-success font-semibold">{{ $row['payment_status'] }}</span>
                        @elseif ($row['payment_status'] === __('Partially Paid'))
                            <span class="fi-color-warning font-semibold">{{ $row['payment_status'] }}</span>
                        @else
                            <span class="fi-color-gray opacity-70">{{ $row['payment_status'] }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr class="border-t-2 font-semibold">
                <td class="py-2">{{ __('Total') }}</td>
                <td class="py-2 text-end tabular-nums">{{ number_format($total_balance['opening_balance'], 2) }}</td>
                <td class="py-2 text-end tabular-nums">{{ number_format($total_balance['current_year_amount'], 2) }}</td>
                <td class="py-2 text-end tabular-nums">{{ number_format($total_balance['closing_balance'], 2) }}</td>
                <td class="py-2 text-end tabular-nums">{{ number_format($total_balance['paid_amount'], 2) }}</td>
                <td class="py-2 text-end tabular-nums">{{ number_format($total_balance['outstanding'], 2) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
