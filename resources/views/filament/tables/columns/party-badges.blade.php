{{--
    Renders a matter's parties or experts as "role badge + name", one per line.

    Replaces two sprintf() blocks that hand-authored Filament's internal fi-badge
    / fi-color classes into a raw HTML string. Those rendered correctly only
    because of the order Filament emits its own CSS, so any upstream reordering
    would have silently turned every badge grey. This uses the real component.

    Expected state: a list of ['label', 'index', 'name', 'color'].
--}}
@php
    $rows = $getState() ?? [];
@endphp

<div class="flex flex-col gap-1 px-3 py-2">
    @forelse ($rows as $row)
        <div class="flex items-center gap-1.5 text-xs">
            <x-filament::badge :color="$row['color']" size="sm">
                <span class="type-label">{{ $row['label'] }}</span> #{{ $row['index'] }}
            </x-filament::badge>
            <span>{{ $row['name'] }}</span>
        </div>
    @empty
        <span class="text-xs text-gray-400 dark:text-gray-500">&mdash;</span>
    @endforelse
</div>
