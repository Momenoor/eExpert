{{-- resources/views/bulk-mail/preview.blade.php --}}
    <!DOCTYPE html>
<html lang="{{ str_contains($recipient->email, '@') ? 'en' : 'en' }}" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        /* ── Reset ── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        /* ── Base ── */
        body {
            font-family: Calibri, 'Segoe UI', Tahoma, Geneva, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            background: #fff;
        }

        /* ══════════════════════════════════════
           SCREEN-ONLY TOOLBAR
        ══════════════════════════════════════ */
        .screen-toolbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 44px;
            background: #0072c6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 9999;
            box-shadow: 0 2px 6px rgba(0,0,0,.25);
        }

        .screen-toolbar .toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 12pt;
            font-weight: 600;
        }

        .screen-toolbar .toolbar-left img {
            height: 22px;
        }

        .screen-toolbar .toolbar-right {
            display: flex;
            gap: 8px;
        }

        .toolbar-btn {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.35);
            color: white;
            padding: 5px 14px;
            font-size: 11pt;
            font-family: Calibri, 'Segoe UI', sans-serif;
            border-radius: 2px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background .15s;
        }

        .toolbar-btn:hover { background: rgba(255,255,255,0.25); }

        /* ══════════════════════════════════════
           PAGE LAYOUT
        ══════════════════════════════════════ */
        .page-wrap {
            margin-top: 44px; /* offset for fixed toolbar */
            padding: 28px 36px 48px;
            max-width: 860px;
        }

        /* ══════════════════════════════════════
           OUTLOOK HEADER — top date + title line
        ══════════════════════════════════════ */
        .print-page-header {
            display: none; /* shown only in print via @media print */
            justify-content: space-between;
            font-size: 9pt;
            color: #555;
            padding-bottom: 6px;
            border-bottom: 1px solid #ccc;
            margin-bottom: 14px;
        }

        /* ══════════════════════════════════════
           OUTLOOK LOGO BLOCK
        ══════════════════════════════════════ */
        .outlook-branding {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .outlook-branding svg {
            width: 36px;
            height: 36px;
        }

        .outlook-branding span {
            font-size: 18pt;
            font-weight: 300;
            color: #1a1a1a;
            letter-spacing: -0.3px;
        }

        /* ══════════════════════════════════════
           SUBJECT LINE
        ══════════════════════════════════════ */
        .divider-line {
            border: none;
            border-top: 1px solid #d0d0d0;
            margin: 10px 0;
        }

        .email-subject {
            font-size: 14pt;
            font-weight: 700;
            color: #1a1a1a;
            padding: 10px 0 8px;
            line-height: 1.3;
        }

        /* ══════════════════════════════════════
           META TABLE (From / Date / To / CC)
        ══════════════════════════════════════ */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-top: 4px;
        }

        .meta-table tr td {
            padding: 2px 0;
            vertical-align: top;
            line-height: 1.6;
        }

        .meta-table tr td.meta-label {
            font-weight: 700;
            color: #1a1a1a;
            white-space: nowrap;
            padding-right: 10px;
            width: 44px;
        }

        .meta-table tr td.meta-value {
            color: #1a1a1a;
        }

        /* ══════════════════════════════════════
           ATTACHMENTS BLOCK
        ══════════════════════════════════════ */
        .attachments-row {
            margin-top: 10px;
            padding-top: 8px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 4px;
            font-size: 10pt;
        }

        .attachments-row .att-icon-col {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #444;
            margin-right: 4px;
        }

        /* Paperclip icon using CSS */
        .clip-icon {
            font-size: 13pt;
        }

        .att-summary {
            color: #444;
            margin-right: 6px;
        }

        .att-names {
            color: #1a1a1a;
        }

        /* ══════════════════════════════════════
           EMAIL BODY
        ══════════════════════════════════════ */
        .body-spacer {
            height: 20px;
        }

        .email-body {
            font-size: 11pt;
            line-height: 1.6;
            color: #1a1a1a;
        }

        /* Support RTL content inside body */
        .email-body [dir="rtl"],
        .email-body p:lang(ar),
        .email-body span:lang(ar) {
            direction: rtl;
            text-align: right;
        }

        /* ══════════════════════════════════════
           PRINT FOOTER URL (like Outlook)
        ══════════════════════════════════════ */
        .print-footer {
            display: none;
        }

        /* ══════════════════════════════════════
           PRINT STYLES
        ══════════════════════════════════════ */
        @media print {

            .screen-toolbar { display: none !important; }

            body { font-size: 10pt; background: white; }

            .page-wrap {
                margin-top: 0;
                padding: 0;
                max-width: 100%;
            }

            /* Show the Outlook-style page header (date + mailbox) */
            .print-page-header {
                display: flex !important;
            }

            .print-footer {
                display: block;
                position: fixed;
                bottom: 0; left: 0; right: 0;
                font-size: 7.5pt;
                color: #888;
                border-top: 1px solid #ddd;
                padding-top: 4px;
                display: flex;
                justify-content: space-between;
            }

            /* Avoid breaking header across pages */
            .outlook-branding,
            .email-subject,
            .meta-table,
            .attachments-row {
                page-break-inside: avoid;
            }

            @page {
                size: A4 portrait;
                margin: 14mm 14mm 18mm 14mm;
            }
        }
    </style>
</head>
<body>

{{-- ── Screen toolbar (hidden on print) ── --}}
<div class="screen-toolbar">
    <div class="toolbar-left">
        {{-- Outlook "O" icon SVG --}}
        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <rect width="48" height="48" rx="6" fill="#0078D4"/>
            <path d="M24 10C16.268 10 10 16.268 10 24s6.268 14 14 14 14-6.268 14-14S31.732 10 24 10zm0 22c-4.418 0-8-3.582-8-8s3.582-8 8-8 8 3.582 8 8-3.582 8-8 8z" fill="white"/>
            <text x="27" y="34" font-size="14" font-family="Segoe UI,Arial" font-weight="700" fill="white">t</text>
        </svg>
        <span>Outlook</span>
    </div>
    <div class="toolbar-right">
        <button class="toolbar-btn" onclick="window.print()">
            🖨&nbsp; Print / Save as PDF
        </button>
        <button class="toolbar-btn" onclick="window.close()">
            ✕&nbsp; Close
        </button>
    </div>
</div>

<div class="page-wrap">

    {{-- ── Print-only page header (mirrors Outlook's "07/05/2026, 04:35  Inbox - Name - Outlook") ── --}}
    <div class="print-page-header">
        <span>{{ \Carbon\Carbon::parse($sentAt)->format('d/m/Y, H:i') }}</span>
        <span>Sent - {{ $sender['name'] }} – Outlook</span>
    </div>

    {{-- ── Outlook branding block ── --}}
    <div class="outlook-branding">
        {{-- Outlook icon --}}
        <img src="{{asset('images/MicrosoftOutlook.png')}}" width="48" alt="Outlook icon">
        <span>Outlook</span>
    </div>

    <hr class="divider-line">

    {{-- ── Subject ── --}}
    <div class="email-subject">{{ $subject }}</div>

    <hr class="divider-line">

    {{-- ── Meta table ── --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">From</td>
            <td class="meta-value">
                <strong>{{ $sender['name'] }}</strong>
                &lt;{{ $sender['address'] }}&gt;
            </td>
        </tr>
        <tr>
            <td class="meta-label">Date</td>
            <td class="meta-value">
                {{ \Carbon\Carbon::parse($sentAt)->format('D d-m-Y g:i A') }}
            </td>
        </tr>
        <tr>
            <td class="meta-label">To</td>
            <td class="meta-value">
                {{ $recipient->name }}
                &lt;{{ $recipient->email }}&gt;
            </td>
        </tr>
        @if(!empty($cc))
            <tr>
                <td class="meta-label">Cc</td>
                <td class="meta-value">
                    @foreach($cc as $ccEmail)
                        {{ $ccEmail }} &lt;{{ $ccEmail }}&gt;@if(!$loop->last); @endif
                    @endforeach
                </td>
            </tr>
        @endif
        @if(!empty($bcc))
            <tr>
                <td class="meta-label">Bcc</td>
                <td class="meta-value">
                    {{ implode('; ', $bcc) }}
                </td>
            </tr>
        @endif
    </table>

    {{-- ── Attachments (like Outlook: 📎 2 attachments (220 KB) \n filenames;) ── --}}
    @if(!empty($attachments))
        <div class="attachments-row">
            <div class="att-icon-col">
                <span class="clip-icon">📎</span>
                <span class="att-summary">
                {{ count($attachments) }} {{ Str::plural('attachment', count($attachments)) }}
            </span>
            </div>
            <span class="att-names">
            @foreach($attachments as $path)
                    {{ basename($path) }}@if(!$loop->last); @endif
                @endforeach
        </span>
        </div>
    @endif

    {{-- ── Body ── --}}
    <div class="body-spacer"></div>

    <div class="email-body">
        {!! $html !!}
    </div>

</div>

{{-- ── Print footer (URL, like Outlook) ── --}}
<div class="print-footer">
    <span>{{ request()->url() }}</span>
    <span>1 / 1</span>
</div>

<script>
    // Auto-trigger print dialog if ?print=1
    if (new URLSearchParams(window.location.search).get('print') === '1') {
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    }
</script>

</body>
</html>
