@php
    $pixels = $size ?? 40;
    $showStatus = $showStatus ?? true;
    $online = $user->isOnline();
@endphp

<span class="relative inline-flex flex-shrink-0" style="width: {{ $pixels }}px; height: {{ $pixels }}px;">
    <img
        src="{{ $this->avatarUrl($user) }}"
        width="{{ $pixels }}" height="{{ $pixels }}"
        class="h-full w-full rounded-full object-cover ring-2 ring-white dark:ring-gray-900"
        alt=""
    />
    @if ($showStatus)
        <span
            @class([
                'absolute bottom-0 end-0 block h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900',
                'bg-green-500 shadow-[0_0_6px_2px_rgba(34,197,94,0.55)]' => $online,
                'bg-red-500 shadow-[0_0_6px_2px_rgba(239,68,68,0.5)]' => ! $online,
            ])
            title="{{ $online ? __('Online') : __('Offline') }}"
        ></span>
    @endif
</span>
