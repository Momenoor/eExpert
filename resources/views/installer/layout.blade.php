<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Application Setup') }}</title>

    {{--
        Deliberately no @vite() and no CDN. This page has to render on a codebase
        that was just cloned or uploaded, before `npm install && npm run build`
        has ever run and before any network dependency can be assumed — the
        moment it needs a compiled asset that does not exist yet, the installer
        itself becomes the first thing that is broken.
    --}}
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f6f8;
            --surface: #ffffff;
            --border: #e2e5ea;
            --text: #1f2430;
            --muted: #667085;
            --primary: #2f5233;
            --primary-hover: #24401f;
            --danger: #b3261e;
            --success: #1b7a43;
            --warning: #a15c00;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        .installer-shell {
            max-width: 720px;
            margin: 0 auto;
            padding: 48px 20px 80px;
        }

        .installer-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .installer-header h1 {
            font-size: 22px;
            margin: 0 0 4px;
        }

        .installer-header p {
            color: var(--muted);
            margin: 0;
        }

        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 4px;
        }

        .steps .dot {
            flex: 1;
            text-align: center;
            font-size: 11px;
            color: var(--muted);
            padding-bottom: 8px;
            border-bottom: 3px solid var(--border);
        }

        .steps .dot.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 600;
        }

        .steps .dot.done {
            border-bottom-color: var(--success);
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px;
        }

        .card h2 {
            margin-top: 0;
            font-size: 17px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .field input, .field select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: #fff;
            color: var(--text);
        }

        .field .hint {
            color: var(--muted);
            font-size: 12px;
            margin-top: 4px;
        }

        .field .error {
            color: var(--danger);
            font-size: 12px;
            margin-top: 4px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 18px;
            border-radius: 6px;
            border: 1px solid transparent;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            background: var(--primary);
            color: #fff;
        }

        .btn:hover { background: var(--primary-hover); }

        .btn.secondary {
            background: transparent;
            color: var(--text);
            border-color: var(--border);
        }

        .btn[disabled] {
            opacity: .5;
            cursor: not-allowed;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .check-list {
            list-style: none;
            margin: 0 0 16px;
            padding: 0;
        }

        .check-list li {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }

        .check-list li:last-child { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.ok { background: #e4f5ea; color: var(--success); }
        .badge.fail { background: #fbe7e6; color: var(--danger); }
        .badge.warn { background: #fdf1de; color: var(--warning); }

        .alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .alert.success { background: #e4f5ea; color: var(--success); }
        .alert.danger { background: #fbe7e6; color: var(--danger); }

        pre.output {
            background: #14181f;
            color: #d8dee9;
            padding: 14px;
            border-radius: 8px;
            font-size: 12px;
            max-height: 260px;
            overflow: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="installer-shell">
        <div class="installer-header">
            <h1>{{ __('Application Setup') }}</h1>
            <p>{{ __('A one-time wizard to get this deployment running.') }}</p>
        </div>

        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
