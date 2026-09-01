<x-filament-panels::page>
    <x-filament::section
        :heading="__('Before you run anything')"
        icon="heroicon-o-exclamation-triangle"
        icon-color="warning"
    >
        <div class="prose prose-sm max-w-none dark:prose-invert">
            <p>
                {{ __('These actions change who can do what. Each one shows exactly what it will grant before you confirm it, runs inside a transaction, and is safe to run twice — the second run finds nothing left to do.') }}
            </p>
            <p>
                {{ __('Both only ever grant. No permission is revoked and no role is deleted, so a mistaken run cannot lock anybody out.') }}
            </p>
        </div>
    </x-filament::section>

    {{ $this->content }}
</x-filament-panels::page>
