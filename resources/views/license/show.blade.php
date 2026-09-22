<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('License Required') }}</title>

    {{--
        No @vite() — a lapsed license shouldn't ever depend on a frontend
        build having run, same reasoning as the installer's own layout.
    --}}
    <style>
        :root { color-scheme: light; --bg:#f5f6f8; --surface:#fff; --border:#e2e5ea; --text:#1f2430; --muted:#667085; --primary:#2f5233; --primary-hover:#24401f; --danger:#b3261e; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; font-size:14px; line-height:1.5; }
        .shell { max-width:480px; margin:0 auto; padding:64px 20px; }
        h1 { font-size:20px; text-align:center; margin:0 0 4px; }
        p.lead { text-align:center; color:var(--muted); margin:0 0 28px; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:28px; }
        .field { margin-bottom:16px; }
        .field label { display:block; font-weight:600; font-size:13px; margin-bottom:4px; }
        .field input { width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:14px; }
        .field .error { color:var(--danger); font-size:12px; margin-top:4px; }
        .btn { display:block; width:100%; padding:9px 18px; border-radius:6px; border:none; font-size:14px; font-weight:600; cursor:pointer; background:var(--primary); color:#fff; }
        .btn:hover { background:var(--primary-hover); }
        .status { font-size:12px; color:var(--muted); text-align:center; margin-top:16px; }
    </style>
</head>
<body>
    <div class="shell">
        <h1>{{ __('License Required') }}</h1>
        <p class="lead">
            @if ($license?->status === 'suspended')
                {{ __('This installation\'s license has been suspended.') }}
            @elseif ($license?->status === 'revoked')
                {{ __('This installation\'s license has been revoked.') }}
            @elseif ($license?->expires_at?->isPast())
                {{ __('This installation\'s license has expired.') }}
            @else
                {{ __('This installation could not confirm a valid license.') }}
            @endif
        </p>

        <div class="card">
            <form method="post" action="{{ route('license.activate') }}">
                @csrf
                <div class="field">
                    <label>{{ __('License Key') }}</label>
                    <input type="text" name="license_key" placeholder="MIE-XXXXX-XXXXX-XXXXX-XXXXX" required autofocus>
                    @error('license_key')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn">{{ __('Activate') }}</button>
            </form>

            @if ($license?->last_checked_at)
                <p class="status">{{ __('Last checked: :time', ['time' => $license->last_checked_at->diffForHumans()]) }}</p>
            @endif
        </div>
    </div>
</body>
</html>
