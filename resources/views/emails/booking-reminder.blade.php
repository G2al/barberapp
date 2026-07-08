<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reminder prenotazione</title>
</head>
<body style="margin:0; padding:0; background:#f4f0ea; font-family:Arial, Helvetica, sans-serif; color:#1f1a17;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f0ea; margin:0; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e6ded4;">
                    <tr>
                        <td style="padding:0; background:#1f1a17;">
                            <img src="{{ $heroImage }}" alt="La Barberia Di Salvatore Nappa" width="640" style="display:block; width:100%; max-width:640px; height:210px; object-fit:cover; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 34px 10px;">
                            <div style="display:inline-block; padding:7px 12px; border-radius:999px; background:#1f1a17; color:#f6e4c8; font-size:12px; font-weight:bold; letter-spacing:1px; text-transform:uppercase;">Reminder</div>
                            <h1 style="margin:16px 0 12px; font-size:28px; line-height:1.2; color:#1f1a17;">{{ $title }}</h1>
                            <p style="margin:0; font-size:16px; line-height:1.7; color:#5d5148;">Ciao {{ $name }}, {!! $message !!}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 34px 8px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fbf8f4; border:1px solid #eadfd3; border-radius:12px;">
                                <tr>
                                    <td style="padding:22px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding:0 0 14px; font-size:12px; color:#8a7460; text-transform:uppercase; letter-spacing:1px;">Data</td>
                                                <td align="right" style="padding:0 0 14px; font-size:16px; font-weight:bold; color:#1f1a17;">{{ $date }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 0 14px; font-size:12px; color:#8a7460; text-transform:uppercase; letter-spacing:1px;">Ora</td>
                                                <td align="right" style="padding:0 0 14px; font-size:16px; font-weight:bold; color:#1f1a17;">{{ $time }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0 0 14px; font-size:12px; color:#8a7460; text-transform:uppercase; letter-spacing:1px;">Servizio</td>
                                                <td align="right" style="padding:0 0 14px; font-size:16px; font-weight:bold; color:#1f1a17;">{{ $service }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:0; font-size:12px; color:#8a7460; text-transform:uppercase; letter-spacing:1px;">Barbiere</td>
                                                <td align="right" style="padding:0; font-size:16px; font-weight:bold; color:#1f1a17;">{{ $staff }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 34px 34px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7; color:#5d5148;">Ti consigliamo di presentarti puntuale. Se hai bisogno di modificare i tuoi programmi, controlla la prenotazione dall&apos;app.</p>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#8a7460;">La Barberia Di Salvatore Nappa</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
