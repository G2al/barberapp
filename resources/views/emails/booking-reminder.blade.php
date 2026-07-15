<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reminder prenotazione</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family:Arial, Helvetica, sans-serif; color:#111111;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5; margin:0; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:760px; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e5e5e5;">
                    <tr>
                        <td style="padding:0; background:#111111;">
                            <img src="{{ $heroImage }}" alt="Giovanni Cerino Hair Stylist" width="760" style="display:block; width:100%; max-width:760px; height:auto; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 34px 10px;">
                            <div style="display:inline-block; padding:7px 12px; border-radius:999px; background:#111111; color:#ffffff; font-size:12px; font-weight:bold; letter-spacing:1px; text-transform:uppercase;">Reminder</div>
                            <h1 style="margin:16px 0 12px; font-size:28px; line-height:1.2; color:#111111;">{{ $title }}</h1>
                            <p style="margin:0; font-size:16px; line-height:1.7; color:#4b4b4b;">Ciao {{ $name }}, {!! $reminderText !!}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 34px 8px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fafafa; border:1px solid #e6e6e6; border-radius:12px;">
                                <tr>
                                    <td style="padding:22px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding:0 0 14px; font-size:12px; color:#666666; text-transform:uppercase; letter-spacing:1px;">Data</td>
                                                <td align="right" style="padding:0 0 14px; font-size:16px; font-weight:bold; color:#111111;">{{ $date }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 0 14px; font-size:12px; color:#666666; text-transform:uppercase; letter-spacing:1px;">Ora</td>
                                                <td align="right" style="padding:0 0 14px; font-size:16px; font-weight:bold; color:#111111;">{{ $time }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 0 14px; font-size:12px; color:#666666; text-transform:uppercase; letter-spacing:1px;">Servizio</td>
                                                <td align="right" style="padding:0 0 14px; font-size:16px; font-weight:bold; color:#111111;">{{ $service }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0; font-size:12px; color:#666666; text-transform:uppercase; letter-spacing:1px;">Barbiere</td>
                                                <td align="right" style="padding:0; font-size:16px; font-weight:bold; color:#111111;">{{ $staff }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 34px 34px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7; color:#4b4b4b;">Ti consigliamo di presentarti puntuale. Se hai bisogno di modificare i tuoi programmi, controlla la prenotazione dall&apos;app.</p>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#666666;">Giovanni Cerino Hair Stylist</p>
                            <p style="margin:4px 0 0; font-size:13px; line-height:1.6; color:#777777;">Via Macedonia, 114, 81030 Lusciano CE</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
