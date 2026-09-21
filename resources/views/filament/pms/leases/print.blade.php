{{--
    Every field on every page is placed as a percentage of the background
    image's own natural size, so positions set in the click-to-place
    builder land in the same spot here regardless of print/screen scale.
--}}
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenancy Contract — {{ $lease->government_contract_number ?? $lease->id }}</title>

    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background: #fff;
            color: #111;
        }

        .page {
            position: relative;
            width: 210mm;
            page-break-after: always;
            overflow: hidden;
        }

        .page:last-child { page-break-after: auto; }

        .page img.background {
            display: block;
            width: 100%;
            height: auto;
        }

        .field {
            position: absolute;
            white-space: pre-line;
            line-height: 1.2;
        }

        .field.boxed {
            display: flex;
            align-items: center;
            direction: ltr;
        }

        .field.boxed span { max-width: 100%; }

        .not-configured {
            padding: 60px 40px;
            font-size: 12pt;
            color: #555;
            max-width: 500px;
        }

        @media print {
            .no-print { display: none !important; }
        }

        .no-print { margin: 12px; }

        .no-print button {
            font: inherit;
            padding: 6px 14px;
            border: 1px solid #111;
            background: #111;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    @if (! $template || $template->pages->isEmpty() || $template->pages->every(fn ($page) => blank($page->background_image_path)))
        <div class="not-configured">
            <p><strong>This contract format's print template hasn't been set up yet.</strong></p>
            <p>Upload the background page images and place the fields under <em>Conditions Templates → Print Templates</em> in the admin panel.</p>
        </div>
    @else
        @foreach ($template->pages as $page)
            @if ($page->background_image_path)
                <div class="page">
                    <img class="background" src="{{ $page->imageUrl() }}" alt="">
                    @foreach ($page->fields as $field)
                        @php $value = $resolver->resolve($lease, $field->field_key, $field->language); @endphp
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
                    @endforeach
                </div>
            @endif
        @endforeach
    @endif
</body>
</html>
