{{--
    System-wide announcement banner.

    Uses Filament's own callout component instead of hand-reconstructing its DOM
    (fi-callout / fi-callout-icon / fi-callout-main / fi-callout-heading) and
    then hand-patching the colour through an inline style with hardcoded amber
    hex fallbacks. `color="warning"` does all of that, and survives upgrades.

    $announcement is raw here on purpose: Blade's {{ }} escapes it exactly once.
    It was previously escaped in the provider AND again here, so an announcement
    containing & or a quote displayed as &amp; to users.
--}}
<div class="mt-2.5">
    <x-filament::callout color="warning" icon="heroicon-o-megaphone">
        {{ $announcement }}
    </x-filament::callout>
</div>
