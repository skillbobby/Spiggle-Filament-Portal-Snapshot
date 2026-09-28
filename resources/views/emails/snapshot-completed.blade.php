<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif; background:#f8fafc; padding:24px; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff; border-radius:16px; padding:32px; border:1px solid #e2e8f0;">
                    <tr>
                        <td>
                            <p style="margin:0 0 8px; font-size:12px; letter-spacing:.16em; text-transform:uppercase; color:#64748b;">Spiggle Portal Snapshot</p>
                            <h1 style="margin:0 0 16px; font-size:22px; line-height:1.3;">{{ $title }}</h1>
                            <p style="margin:0 0 20px; font-size:15px; line-height:1.6; color:#334155;">{{ $body }}</p>
                            @if (! empty($meta))
                                <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; color:#475569;">
                                    @foreach ($meta as $key => $value)
                                        <tr>
                                            <td style="padding:6px 0; border-top:1px solid #e2e8f0; text-transform:capitalize;">{{ str_replace('_', ' ', $key) }}</td>
                                            <td style="padding:6px 0; border-top:1px solid #e2e8f0; text-align:right;">{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                            <p style="margin:24px 0 0; font-size:12px; color:#94a3b8;">Status: {{ $success ? 'Succeeded' : 'Failed' }} · {{ config('app.name') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
