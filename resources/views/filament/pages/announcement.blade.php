{{--
    System-wide announcement banner, scrolling.

    Uses Filament's own callout component instead of hand-reconstructing its DOM
    (fi-callout / fi-callout-icon / fi-callout-main / fi-callout-heading) and
    then hand-patching the colour through an inline style with hardcoded amber
    hex fallbacks. `color="warning"` does all of that, and survives upgrades.

    The scroll is a CSS animation defined in the panel theme, not <marquee>. The
    element is obsolete, cannot be paused, ignores the reader's reduced-motion
    preference, and always scrolls right-to-left regardless of page direction —
    wrong for an Arabic panel. It also has to be assembled by concatenating the
    announcement into an HTML string, which puts operator-supplied text into the
    document unescaped.

    $announcement is passed raw from the provider and escaped here exactly once
    by {{ }}. It was previously escaped in both places, so an announcement
    containing & or a quote reached users as &amp;.

    The text is duplicated and the track slid by one copy's width, so the second
    copy arrives exactly as the first leaves and the line never shows a gap. The
    duplicate is aria-hidden so a screen reader announces the message once.
--}}
<div class="mt-2.5">
    <x-filament::callout color="warning" icon="heroicon-o-megaphone">
        <x-slot name="controls">
            <div style="width:200% !important">
                <marquee loop="0" scrollamount="5" direction="left" direction="right">{{ $announcement }}</marquee>
            </div>
        </x-slot>
    </x-filament::callout>
</div>
