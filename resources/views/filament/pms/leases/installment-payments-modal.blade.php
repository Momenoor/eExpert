<div style="display: flex; flex-direction: column; gap: 6px;">
    @forelse ($payments as $payment)
        <div style="display: flex; justify-content: space-between; gap: 12px; font-size: 13px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px;">
            <span>{{ $payment->paid_date?->format('d/m/Y') }}</span>
            <span style="font-weight: 600;">{{ number_format((float) $payment->amount, 2) }} {{ __('AED') }}</span>
            <span>{{ $payment->payment_method?->getLabel() ?? '—' }}</span>
            <span style="color: #6b7280;">{{ $payment->transaction_reference ?? '—' }}</span>
            <span style="color: #6b7280;">{{ $payment->bank_name ?? '—' }}</span>
        </div>
    @empty
        <p style="font-size: 13px; color: #9ca3af;">{{ __('No payments recorded yet.') }}</p>
    @endforelse
</div>
