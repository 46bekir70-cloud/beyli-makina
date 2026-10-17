<?php
/**
 * ÖRNEK YAPILANDIRMA DOSYASI
 *
 * Bu dosyayı "config.php" adıyla kopyalayın ve kendi bilgilerinizi yazın:
 *     inc/config.ornek.php  ->  inc/config.php
 *
 * config.php içinde şifreler bulunduğu için o dosya siteyle birlikte
 * paylaşılmamalı, yedeklerde de dikkatli olunmalıdır.
 */

return [

    // ---------- VERİTABANI ----------
    // Natro/Plesk panelinde oluşturduğunuz MySQL veritabanının bilgileri.
    'db' => [
        'host'    => 'localhost',
        'name'    => 'beyli_makina',
        'user'    => 'beyli',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // ---------- E-POSTA ----------
    // Teklif formu bu bilgilerle mail gönderir.
    //
    // 'method' iki değer alır:
    //   'mail' -> Sunucunun kendi posta servisiyle gönderir. Kullanıcı adı/şifre
    //             GEREKMEZ, sadece 'to' ve 'from' doldurulur. En kolayı; bazı
    //             sunucularda kapalıdır ya da spam'e düşebilir.
    //   'smtp' -> Bir posta hesabına bağlanarak gönderir. En sağlamı, ama
    //             hesabın host/user/pass bilgileri gerekir.
    //             Gmail için: host = smtp.gmail.com, port = 587, secure = 'tls',
    //             pass = Google'dan alınan 16 haneli "uygulama şifresi".
    'smtp' => [
        'method'    => 'mail',

        'host'      => 'mail.beylimakina.com',
        'port'      => 587,
        'secure'    => 'tls',              // 'tls' | 'ssl' | '' (şifresiz)
        'user'      => 'siparis@beylimakina.com',
        'pass'      => '',                 // e-posta hesabının şifresi
        'from'      => 'siparis@beylimakina.com',
        'from_name' => 'Beyli Makina Web Sitesi',
        // Teklif taleplerinin düşeceği adres(ler). Birden fazlaysa virgülle ayırın.
        'to'        => 'siparis@beylimakina.com',
    ],

    // ---------- TEKLİF FORMU ----------
    'form' => [
        // Yüklenebilecek en büyük dosya boyutu (MB).
        //
        // ÖNEMLİ: Bu değeri yükseltmek tek başına yetmez. Sunucunun PHP ayarları
        // (upload_max_filesize ve post_max_size) bundan küçükse gerçek sınır
        // onlar olur. Plesk'te: Web Siteleri ve Alan Adları > PHP Ayarları.
        // Buradaki değer sunucununkinden 1 MB AŞAĞIDA olmalı: dosyanın yanında form
        // alanları da gidiyor, tam sınıra eşitlersen büyük dosyalar reddedilir.
        // Sunucu 16M ise burası 15 olmalı.
        // Site formda hangisi küçükse onu yazar, yanlış rakam göstermez.
        'max_upload_mb'  => 15,
        // Aynı ziyaretçinin iki gönderimi arasında beklemesi gereken süre (saniye).
        'throttle_secs'  => 60,
    ],
];
