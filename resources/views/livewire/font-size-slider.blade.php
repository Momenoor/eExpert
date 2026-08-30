<div>
    <hr style="margin: -5px; margin-top: 5px; border-top: 1px solid #e5e5e5">
    <div style="margin: 10px">

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


