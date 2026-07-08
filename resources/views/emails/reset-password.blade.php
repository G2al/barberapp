<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reimposta password</title>
</head>
<body style="margin:0; padding:0; background:#f4f0ea; font-family:Arial, Helvetica, sans-serif; color:#1f1a17;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f0ea; margin:0; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e6ded4;">
                    <tr>
                        <td style="padding:0; background:#1f1a17;">
                            <img src="{{ $heroImage }}" alt="Aletta Barber 2k24" width="640" style="display:block; width:100%; max-width:640px; height:220px; object-fit:cover; border:0;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 34px 10px;">
                            <div style="font-size:12px; font-weight:bold; letter-spacing:1.4px; text-transform:uppercase; color:#a6763f;">Accesso account</div>
                            <h1 style="margin:10px 0 12px; font-size:28px; line-height:1.2; color:#1f1a17;">Reimposta la tua password</h1>
                            <p style="margin:0; font-size:16px; line-height:1.7; color:#5d5148;">Ciao {{ $name }}, abbiamo ricevuto una richiesta per reimpostare la password del tuo account Aletta Barber 2k24.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 34px 10px;" align="center">
                            <a href="{{ $resetUrl }}" style="display:inline-block; background:#1f1a17; color:#f6e4c8; text-decoration:none; font-size:15px; font-weight:bold; letter-spacing:.3px; padding:14px 22px; border-radius:10px;">Reimposta password</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 34px 34px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fbf8f4; border:1px solid #eadfd3; border-radius:12px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="margin:0 0 12px; font-size:15px; line-height:1.7; color:#5d5148;">Se il pulsante non funziona, copia e incolla questo link nel browser:</p>
                                        <p style="margin:0; font-size:13px; line-height:1.6; word-break:break-all; color:#8a7460;">{{ $resetUrl }}</p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:18px 0 0; font-size:14px; line-height:1.7; color:#8a7460;">Se non hai richiesto tu il reset, ignora questa email. La tua password restera&apos; invariata.</p>
                            <p style="margin:14px 0 0; font-size:14px; line-height:1.6; color:#8a7460;">Aletta Barber 2k24</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
