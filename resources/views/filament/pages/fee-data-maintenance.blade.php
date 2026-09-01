<x-filament-panels::page>
    <x-filament::section
        :heading="__('Before you run anything')"
        icon="heroicon-o-exclamation-triangle"
        icon-color="warning"
    >
        <div class="prose prose-sm max-w-none dark:prose-invert">
            <p>
                {{ __('These actions write to live financial records. Each one shows exactly what it will change before you confirm it, runs inside a transaction, and is safe to run twice — the second run finds nothing left to do.') }}
            </p>
            <p>
                {{ __('Neither action affects incentive calculations. The incentive engine reads registered fee amounts only and never reads allocations, and finalized calculations keep their own stored snapshot.') }}
            </p>
        </div>
    </x-filament::section>

    {{ $this->content }}
</x-filament-panels::page>
