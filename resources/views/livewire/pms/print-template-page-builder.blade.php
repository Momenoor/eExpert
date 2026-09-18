<div
    x-data="{
        dragging: null,
        onImageClick(e) {
            if (! $wire.selectedFieldKey) return;
            const rect = $refs.bgImage.getBoundingClientRect();
            const xPct = ((e.clientX - rect.left) / rect.width) * 100;
            const yPct = ((e.clientY - rect.top) / rect.height) * 100;
            $wire.placeField(xPct, yPct);
        },
        startDrag(index, e) {
            this.dragging = index;
            $wire.selectMarker(index, e.shiftKey || e.ctrlKey || e.metaKey);
            e.currentTarget.focus();
        },
        onDrag(e) {
            if (this.dragging === null) return;
            const rect = $refs.bgImage.getBoundingClientRect();
            const xPct = Math.min(100, Math.max(0, ((e.clientX - rect.left) / rect.width) * 100));
            const yPct = Math.min(100, Math.max(0, ((e.clientY - rect.top) / rect.height) * 100));
            $wire.moveField(this.dragging, xPct, yPct);
        },
        stopDrag() {
            this.dragging = null;
        },
    }"
    @mousemove.window="onDrag($event)"
    @mouseup.window="stopDrag()"
    style="display: flex; gap: 16px; align-items: flex-start;"
>
    <div style="width: 280px; flex-shrink: 0;">
        <label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 4px;">
            {{ __('Field to place') }}
        </label>
        <select wire:model="selectedFieldKey" style="width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 6px;">
            <option value="">{{ __('— pick a field —') }}</option>
            @foreach ($availableFields as $group => $groupFields)
                <optgroup label="{{ \App\Services\PMS\LeasePrintFieldResolver::groupLabel($group) }}">
                    @foreach ($groupFields as $key => $label)
                        <option value="{{ $key }}">{{ \App\Services\PMS\LeasePrintFieldResolver::label($key) }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <p style="font-size: 11px; color: #6b7280; margin-top: 6px;">
            {{ __('Pick a field, then click on the image where it belongs. Click a placed field to select it, drag to move it, or use the keyboard arrows once selected. Shift-click (or ctrl/cmd-click) multiple fields to align them together.') }}
        </p>

        @if (! empty($fields))
            <div style="margin-top: 12px;">
                <label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 4px;">
                    {{ __('Align All') }}
                </label>
                <div style="display: flex; gap: 6px;">
                    <button type="button" wire:click="alignAll('left')" style="flex: 1; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px; cursor: pointer; background: #fff;">
                        {{ __('Align Start') }}
                    </button>
                    <button type="button" wire:click="alignAll('right')" style="flex: 1; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px; cursor: pointer; background: #fff;">
                        {{ __('Align End') }}
                    </button>
                </div>
                <p style="font-size: 10px; color: #9ca3af; margin-top: 4px;">
                    {{ __('Sets the text alignment of every field.') }}
                </p>
            </div>

            <div style="margin-top: 12px;">
                <label style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 4px;">
                    {{ __('Align Selected') }} ({{ count($selectedIndexes) }})
                </label>
                <div style="display: flex; gap: 6px;">
                    <button
                        type="button"
                        wire:click="alignSelected('left')"
                        @disabled(count($selectedIndexes) < 2)
                        style="flex: 1; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px; cursor: pointer; background: #fff; opacity: {{ count($selectedIndexes) < 2 ? '0.5' : '1' }};"
                    >
                        {{ __('Align Start') }}
                    </button>
                    <button
                        type="button"
                        wire:click="alignSelected('right')"
                        @disabled(count($selectedIndexes) < 2)
                        style="flex: 1; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px; cursor: pointer; background: #fff; opacity: {{ count($selectedIndexes) < 2 ? '0.5' : '1' }};"
                    >
                        {{ __('Align End') }}
                    </button>
                </div>
                <p style="font-size: 10px; color: #9ca3af; margin-top: 4px;">
                    {{ __('Snaps the selected fields to a shared left/right edge. Shift-click (or ctrl/cmd-click) markers or list rows to select more than one.') }}
                </p>
            </div>
        @endif

        @if (count($selectedIndexes) === 1 && isset($fields[$selectedIndexes[0]]))
            @php($selectedIndex = $selectedIndexes[0])
            <div style="margin-top: 12px; border: 1px solid #d1d5db; border-radius: 6px; padding: 10px;">
                <p style="font-weight: 600; font-size: 12px; margin: 0 0 8px;">
                    {{ \App\Services\PMS\LeasePrintFieldResolver::label($fields[$selectedIndex]['field_key']) }}
                </p>

                <div style="display: flex; gap: 8px;">
                    <label style="flex: 1; font-size: 11px;">
                        {{ __('X %') }}
                        <input type="number" step="0.1" min="0" max="100" wire:model.blur="fields.{{ $selectedIndex }}.x_percent" style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 4px;">
                    </label>
                    <label style="flex: 1; font-size: 11px;">
                        {{ __('Y %') }}
                        <input type="number" step="0.1" min="0" max="100" wire:model.blur="fields.{{ $selectedIndex }}.y_percent" style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 4px;">
                    </label>
                </div>

                <label style="display: block; font-size: 11px; margin-top: 8px;">
                    {{ __('Font Size') }}
                    <input type="number" min="6" max="48" wire:model.blur="fields.{{ $selectedIndex }}.font_size" style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 4px;">
                </label>

                <label style="display: block; font-size: 11px; margin-top: 8px;">
                    {{ __('Alignment') }}
                    <select wire:model="fields.{{ $selectedIndex }}.text_align" style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 4px;">
                        <option value="left">{{ __('Start Align') }}</option>
                        <option value="center">{{ __('Center Align') }}</option>
                        <option value="right">{{ __('End Align') }}</option>
                    </select>
                </label>

                <p style="font-size: 10px; color: #9ca3af; margin-top: 8px;">
                    {{ __('Tip: click the marker on the image, then use the arrow keys to nudge it (hold Shift for bigger steps).') }}
                </p>
            </div>
        @endif

        <div style="margin-top: 16px; max-height: 300px; overflow-y: auto;">
            @forelse ($fields as $i => $field)
                <div
                    @click="$wire.selectMarker({{ $i }}, $event.shiftKey || $event.ctrlKey || $event.metaKey)"
                    style="display: flex; align-items: center; justify-content: space-between; gap: 6px; font-size: 11px; border: 1px solid {{ in_array($i, $selectedIndexes, true) ? '#2563eb' : '#e5e7eb' }}; border-radius: 6px; padding: 4px 8px; margin-bottom: 4px; cursor: pointer;"
                >
                    <span>{{ \App\Services\PMS\LeasePrintFieldResolver::label($field['field_key']) }}</span>
                    <span style="display: flex; gap: 6px; align-items: center;">
                        <label style="display:flex; align-items:center; gap:2px; cursor:pointer;" @click.stop>
                            <input type="checkbox" wire:click="toggleRtl({{ $i }})" @checked($field['rtl'])>
                            {{ __('AR') }}
                        </label>
                        <button type="button" wire:click.stop="removeField({{ $i }})" style="color: #dc2626; border: none; background: none; cursor: pointer;">✕</button>
                    </span>
                </div>
            @empty
                <p style="font-size: 11px; color: #9ca3af;">{{ __('No fields placed yet.') }}</p>
            @endforelse
        </div>

        <button
            type="button"
            x-on:click="$wire.save().then(() => { if (typeof close === 'function') { close(); } })"
            style="margin-top: 16px; width: 100%; background: #111; color: #fff; border: none; border-radius: 6px; padding: 8px; cursor: pointer;"
        >
            {{ __('Save') }}
        </button>
    </div>

    <div style="position: relative; width: 760px; max-width: 100%;">
        @if ($imageUrl)
            <img
                x-ref="bgImage"
                src="{{ $imageUrl }}"
                @click="onImageClick($event)"
                style="width: 100%; display: block; cursor: crosshair; border: 1px solid #d1d5db;"
            >
            @foreach ($fields as $i => $field)
                <div
                    tabindex="0"
                    @mousedown.prevent="startDrag({{ $i }}, $event)"
                    @keydown.up.prevent="$wire.nudgeField({{ $i }}, 'up', $event.shiftKey)"
                    @keydown.down.prevent="$wire.nudgeField({{ $i }}, 'down', $event.shiftKey)"
                    @keydown.left.prevent="$wire.nudgeField({{ $i }}, 'left', $event.shiftKey)"
                    @keydown.right.prevent="$wire.nudgeField({{ $i }}, 'right', $event.shiftKey)"
                    style="position: absolute; left: {{ $field['x_percent'] }}%; top: {{ $field['y_percent'] }}%; background: {{ in_array($i, $selectedIndexes, true) ? '#dc2626' : '#2563eb' }}; color: #fff; font-size: 10px; padding: 2px 5px; border-radius: 4px; cursor: move; white-space: nowrap; transform: translate(-2px, -2px); user-select: none; outline: none; box-shadow: {{ in_array($i, $selectedIndexes, true) ? '0 0 0 2px #fff, 0 0 0 4px #dc2626' : 'none' }};"
                >{{ \App\Services\PMS\LeasePrintFieldResolver::label($field['field_key']) }}</div>
            @endforeach
        @else
            <p style="color: #9ca3af;">{{ __('Upload a background image for this page first.') }}</p>
        @endif
    </div>
</div>
