<?php
/**
 * Sahte yönetim girişi (tuzak / honeypot).
 *
 * Gerçek panel /teknikel adresinde. Burası, "/admin" yolunu deneyen botlar ve
 * meraklılar için duran bir kabuk: gerçek şifreyi hiç görmez, hiçbir şeyi
 * doğrulamaz, hiçbir koşulda içeri almaz. Tek yaptığı denemeyi kaydetmek.
 *
 * Buradaki hiçbir kod oturum açmaz, veritabanına dokunmaz, dosya okumaz —
 * yalnızca denemeleri content/honeypot.json içine yazar.
 */
header('X-Robots-Tag: noindex, nofollow', true);

$kok = dirname(__DIR__);
$kayitDosya = $kok . '/content/honeypot.json';

/** Ziyaretçinin gerçek adresi (nginx arkasında REMOTE_ADDR 127.0.0.1 olabiliyor) */
function bm_tuzak_ip() {
    $uzak = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $acik = filter_var($uzak, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if (!$acik && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $liste = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $son = end($liste);
        if (filter_var($son, FILTER_VALIDATE_IP)) return $son;
    }
    return $uzak;
}

$hata = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Denemeyi kaydet. Şifrenin KENDİSİ kaydedilmiyor — yalnızca uzunluğu.
    // Böylece kayıt dosyası ele geçse bile kimsenin şifresi sızmıyor.
    $kayit = [];
    if (is_file($kayitDosya)) {
        $kayit = json_decode((string)file_get_contents($kayitDosya), true) ?: [];
    }
    $kayit[] = [
        't'   => date('c'),
        'ip'  => bm_tuzak_ip(),
        'ua'  => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
        'kul' => substr((string)($_POST['username'] ?? ''), 0, 60),
        'uzn' => strlen((string)($_POST['password'] ?? '')),
    ];
    $kayit = array_slice($kayit, -200);
    @file_put_contents($kayitDosya, json_encode($kayit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

    // Gerçek panel gibi yavaş cevap ver ve her zaman reddet
    usleep(900000);
    $hata = 'Kullanıcı adı veya şifre hatalı.';
}
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Yönetim Paneli</title>
<meta name="robots" content="noindex, nofollow">
<style>
  *{box-sizing:border-box;}
  body{
    margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
    font-family:'Segoe UI',Arial,sans-serif;background:#0f172a;padding:20px;
  }
  .kutu{
    background:#fff;border-radius:10px;padding:34px 30px;width:100%;max-width:360px;
    box-shadow:0 20px 50px rgba(0,0,0,.35);
  }
  h1{font-size:17px;margin:0 0 4px;color:#1e293b;text-align:center;}
  .alt{font-size:12.5px;color:#64748b;text-align:center;margin:0 0 22px;}
  label{display:block;font-size:12px;color:#475569;margin-bottom:5px;font-weight:600;}
  input{
    width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:6px;
    font-size:14px;margin-bottom:14px;font-family:inherit;
  }
  input:focus{outline:none;border-color:#3b82f6;}
  button{
    width:100%;padding:11px;border:none;border-radius:6px;background:#1e40af;
    color:#fff;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;
  }
  button:hover{background:#1d4ed8;}
  .hata{
    background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;
    padding:9px 12px;border-radius:6px;font-size:13px;margin-bottom:16px;
  }
  .dip{margin:18px 0 0;font-size:11px;color:#94a3b8;text-align:center;}
</style>
</head>
<body>
  <div class="kutu">
    <h1>Yönetim Paneli</h1>
    <p class="alt">Devam etmek için giriş yapın</p>
    <?php if ($hata): ?><div class="hata"><?php echo htmlspecialchars($hata, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <label for="username">Kullanıcı Adı</label>
      <input type="text" id="username" name="username" autocomplete="off" required>
      <label for="password">Şifre</label>
      <input type="password" id="password" name="password" autocomplete="off" required>
      <button type="submit">Giriş Yap</button>
    </form>
    <p class="dip">v2.4.1</p>
  </div>
</body>
</html>
