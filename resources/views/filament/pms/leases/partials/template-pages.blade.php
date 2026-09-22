{{--
    Shared by every document that renders a `LeasePrintTemplate` (lease
    contract, tax invoice, receivable receipt) — every field on every page
    is placed as a percentage of the background image's own natural size,
    so positions set in the click-to-place builder land in the same spot
    here regardless of print/screen scale.

    Expects:
    - $template: ?LeasePrintTemplate
    - $resolve: Closure(string $fieldKey, ?string $language): ?string
    - $notConfiguredMessage: string
    - $installments: ?Collection<int, Installment> — only the Receivable
      Receipt's `installments_table` field renders from this; every other
      document leaves it unset.
--}}
@if (! $template || $template->pages->isEmpty() || $template->pages->every(fn ($page) => blank($page->background_image_path)))
    <div class="not-configured">
        <p><strong>{!! $notConfiguredMessage !!}</strong></p>
        <p>Upload the background page image and place the fields under <em>Leasing → Print Templates</em> in the admin panel.</p>
    </div>
@else
    @foreach ($template->pages as $page)
        @if ($page->background_image_path)
            <div class="page">
                <img class="background" src="{{ $page->imageUrl() }}" alt="">
                @foreach ($page->fields as $field)
                    @php $isTable = \App\Services\PMS\LeasePrintFieldResolver::isTable($field->field_key); @endphp
                    @if ($isTable)
                        @if (($installments ?? collect())->isNotEmpty())
                            @php
                                $columns = \App\Services\PMS\LeasePrintFieldResolver::visibleInstallmentsTableColumns($field->hidden_columns, $field->language);
                                $columnWidths = \App\Services\PMS\LeasePrintFieldResolver::resolveInstallmentsTableColumnWidths($field->column_widths, $field->hidden_columns);
                            @endphp
                            <table
                                class="field-table"
                                dir="{{ $field->rtl ? 'rtl' : 'ltr' }}"
                                style="
                                    left: {{ $field->x_percent }}%;
                                    top: {{ $field->y_percent }}%;
                                    font-size: {{ $field->font_size }}pt;
                                    @if (filled($field->width_percent)) width: {{ $field->width_percent }}%; @endif
                                    @if (filled($field->height_percent)) height: {{ $field->height_percent }}%; @endif
                                "
                            >
                                <colgroup>
                                    @foreach (array_keys($columns) as $columnKey)
                                        <col style="width: {{ $columnWidths[$columnKey] }}%;">
                                    @endforeach
                                </colgroup>
                                <thead>
                                    <tr>
                                        @foreach ($columns as $columnLabel)
                                            <th>{{ $columnLabel }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($installments as $installment)
                                        <tr>
                                            @foreach (array_keys($columns) as $columnKey)
                                                <td>{{ \App\Services\PMS\LeasePrintFieldResolver::installmentColumnValue($installment, $columnKey) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    @else
                        @php $value = $resolve($field->field_key, $field->language); @endphp
                        @if (filled($value))
                            @php $hasBox = filled($field->width_percent); @endphp
                            <div
                                class="field {{ $hasBox ? 'boxed' : '' }} {{ $field->rtl ? 'arabic' : '' }}"
                                style="
                                    left: {{ $field->x_percent }}%;
                                    top: {{ $field->y_percent }}%;
                                    font-size: {{ $field->font_size }}pt;
                                    text-align: {{ $field->text_align }};
                                    @if ($hasBox)
                                        width: {{ $field->width_percent }}%;
                                        @if (filled($field->height_percent)) height: {{ $field->height_percent }}%; @endif
                                        justify-content: {{ ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$field->text_align] ?? 'flex-start' }};
                                    @endif
                                "
                            ><span dir="{{ $field->rtl ? 'rtl' : 'ltr' }}">{{ $value }}</span></div>
                        @endif
                    @endif
                @endforeach
            </div>
        @endif
    @endforeach
@endif
