<x-filament::dropdown
    teleport
    placement="bottom-start"
    class="fi-dropdown"
>
    <x-slot name="trigger">
        <button
            type="button"
            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-500/5 dark:text-gray-200 dark:hover:bg-white/5"
        >
            <x-filament::icon
                icon="heroicon-o-squares-2x2"
                class="w-5 h-5"
            />
            {{ $currentSystem === 'pms' ? __('Properties Management System') : __('Matters Management System') }}
            <x-filament::icon
                icon="heroicon-m-chevron-down"
                class="w-4 h-4"
            />
        </button>
    </x-slot>

    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            :href="$mmsUrl"
            tag="a"
            :icon="$currentSystem === 'mms' ? 'heroicon-m-check' : null"
        >
            {{ __('Matters Management System') }}
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            :href="$pmsUrl"
            tag="a"
            :icon="$currentSystem === 'pms' ? 'heroicon-m-check' : null"
        >
            {{ __('Properties Management System') }}
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
</x-filament::dropdown>
