<div>
    <hr class="mt-1 border-t border-gray-200 dark:border-white/10">
    <div class="m-2.5">

        {{ $this->form }}
    </div>

    {{--
        The initial font size is already applied server-side, before first
        paint, via the HEAD_END render hook in AdminPanelProvider (setting
        --user-font-size on :root) — applying it again here on
        DOMContentLoaded (which fires after the page has already rendered)
        caused a visible flash-then-resize. Live updates while dragging the
        slider are handled by updateUserFont()'s own $this->js() call, which
        sets the same --user-font-size CSS variable directly.
    --}}
</div>


