<?php
/**
 * Teklif formu e-postası — PHPMailer ile SMTP üzerinden gönderilir.
 *
 * PHPMailer composer ile değil, inc/phpmailer/ altına elle konularak
 * kullanılıyor (paylaşımlı hostinglerde composer çalıştırmak zahmetli).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Teklif talebini gönderir.
 *
 * @param array $data   Formdan gelen (doğrulanmış) alanlar
 * @param array|null $attachment ['path'=>..,'name'=>..] veya null
 * @throws RuntimeException Gönderilemezse
 */
function bm_send_quote_mail(array $data, $attachment = null) {
    $smtp = bm_config('smtp');

    if (empty($smtp['to'])) {
        throw new RuntimeException('E-posta ayarları eksik: alıcı adresi (to) girilmemiş.');
    }

    // Gönderim yöntemi:
    //   'mail' -> sunucunun kendi posta servisi (kullanıcı adı/şifre gerekmez)
    //   'smtp' -> bir posta hesabına bağlanarak gönderir (varsayılan)
    $method = strtolower((string)($smtp['method'] ?? 'smtp'));

    $mail = new PHPMailer(true);

    try {
        if ($method === 'mail') {
            $mail->isMail();
            $mail->CharSet = 'UTF-8';
        } else {
            if (empty($smtp['host']) || empty($smtp['user'])) {
                throw new RuntimeException('SMTP ayarları eksik (inc/config.php).');
            }
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            // Şifre girilmemişse kimlik doğrulama denenmez (kimlik doğrulaması
            // istemeyen sunucularda AUTH göndermek bağlantıyı reddettiriyor).
            $mail->SMTPAuth   = !empty($smtp['pass']);
            $mail->Username   = $smtp['user'];
            $mail->Password   = $smtp['pass'] ?? '';
            $mail->Port       = (int)($smtp['port'] ?? 587);
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 20;

            $secure = strtolower((string)($smtp['secure'] ?? 'tls'));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure  = false;
            }
        }

        // Gönderen her zaman kendi alan adımız olmalı; müşterinin adresini
        // From'a koyarsak SPF/DKIM nedeniyle mail spam'e düşer.
        // Müşteriye "Yanıtla" ile dönülebilsin diye Reply-To kullanıyoruz.
        $from = $smtp['from'] ?: ($smtp['user'] ?: $smtp['to']);
        $mail->setFrom($from, $smtp['from_name'] ?: 'Beyli Makina');
        foreach (array_filter(array_map('trim', explode(',', $smtp['to']))) as $to) {
            $mail->addAddress($to);
        }
        if (!empty($data['email'])) {
            $mail->addReplyTo($data['email'], $data['fullname'] ?: $data['email']);
        }

        if ($attachment && !empty($attachment['path']) && is_file($attachment['path'])) {
            $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
        }

        $kurumsalMi = (($data['musteri_turu'] ?? '') === 'kurumsal');

        $mail->Subject = 'Teklif Talebi' . ($kurumsalMi ? ' (Kurumsal)' : '')
            . ' — ' . ($data['parttype'] ?: 'Özel Sipariş');

        // Telefon: ülke kodlu biçimi varsa onu kullanır
        $telefonYazi = $data['phone_display'] ?? ($data['phone'] ?? '');
        if (!empty($data['phone_e164']) && !empty($data['phone_display'])) {
            $telefonYazi = $data['phone_display'] . '  (' . $data['phone_e164'] . ')';
        }

        // Vergi no hatalı görünüyorsa engellenmiyor, sadece not düşülüyor
        $vergiYazi = $data['taxno'] ?? '';
        if ($vergiYazi !== '' && !empty($data['taxno_supheli'])) {
            $vergiYazi .= '  ⚠️ (numara doğrulamadan geçmedi, teyit edin)';
        }

        $rows = [
            'Talep Türü'      => $kurumsalMi ? 'Kurumsal' : 'Bireysel',
            'Ad Soyad'        => $data['fullname'] ?? '',
            'Telefon'         => $telefonYazi,
            'E-Posta'         => $data['email'] ?? '',
            'Parça / Hizmet'  => $data['parttype'] ?? '',
            'İlgili Ürün'     => $data['product'] ?? '',
        ];

        if ($kurumsalMi) {
            $rows['Firma Ünvanı']   = $data['company'] ?? '';
            $rows['Görev / Birim']  = $data['role'] ?? '';
            $rows['Vergi Dairesi']  = $data['taxoffice'] ?? '';
            $rows['Vergi No / TCKN'] = $vergiYazi;
            $rows['Ülke']           = $data['company_country_name'] ?? ($data['company_country'] ?? '');
            $rows['İl / İlçe']      = trim(($data['city'] ?? '') . (empty($data['district']) ? '' : ' / ' . $data['district']));
            $rows['Fatura Adresi']  = $data['address'] ?? '';
            $rows['Web Sitesi']     = $data['company_web'] ?? '';
            $rows['Tahmini Adet']   = $data['qty'] ?? '';
            $rows['İstenen Termin'] = $data['deadline'] ?? '';
            $rows['Sipariş No']     = $data['pono'] ?? '';
        }

        if (!empty($data['kvkk']))  $rows['Aydınlatma Metni'] = 'Onayladı';
        if (!empty($data['izin']))  $rows['Ticari İleti İzni'] = 'Onayladı';

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1B2434;">';
        $html .= '<h2 style="color:#0B3D8F;margin:0 0 14px;">Yeni Teklif Talebi</h2>';
        $html .= '<table cellpadding="7" cellspacing="0" border="0" style="border-collapse:collapse;">';
        foreach ($rows as $label => $value) {
            if ($value === '') continue;
            $html .= '<tr>'
                . '<td style="background:#F4F6FA;border:1px solid #E3E7EF;font-weight:bold;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="border:1px solid #E3E7EF;">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }
        $html .= '</table>';
        if (!empty($data['details'])) {
            $html .= '<h3 style="color:#0B3D8F;margin:20px 0 8px;">Sipariş Detayları</h3>';
            $html .= '<div style="white-space:pre-wrap;border-left:3px solid #E4231D;padding:8px 14px;background:#FAFBFD;">'
                  . htmlspecialchars($data['details'], ENT_QUOTES, 'UTF-8') . '</div>';
        }
        if ($attachment) {
            $html .= '<p style="margin-top:18px;color:#63708A;">📎 Ek dosya: '
                  . htmlspecialchars($attachment['name'] ?? '', ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $html .= '<p style="margin-top:22px;color:#8a97ae;font-size:12px;">Bu mesaj beylimakina.com üzerindeki teklif formundan gönderildi.</p>';
        $html .= '</div>';

        $mail->isHTML(true);
        $mail->Body = $html;

        $plain = "Yeni Teklif Talebi\n\n";
        foreach ($rows as $label => $value) {
            if ($value === '') continue;
            $plain .= "$label: $value\n";
        }
        if (!empty($data['details'])) {
            $plain .= "\nSipariş Detayları:\n" . $data['details'] . "\n";
        }
        $mail->AltBody = $plain;

        $mail->send();
    } catch (PHPMailerException $e) {
        // PHPMailer'ın kendi mesajı teknik olabiliyor; günlüğe yazıp
        // kullanıcıya sade bir mesaj gösteriyoruz.
        error_log('[beyli-teklif] SMTP hatası: ' . $mail->ErrorInfo);
        throw new RuntimeException('E-posta gönderilemedi.');
    }
}
