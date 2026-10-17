<?php
/**
 * Kaydetmeden önizleme.
 *
 * Panelden POST edilen (henüz kaydedilmemiş) değerleri alır, bellekteki
 * içeriğin üzerine yazar ve sitenin GERÇEK şablon dosyasını çalıştırır.
 * content.json'a ve veritabanına hiçbir şey yazılmaz.
 *
 * Gerçek şablonu kullanmamız önemli: önizleme için ayrı bir kopya yazsaydık
 * zamanla siteden farklılaşır ve yanlış bir görüntü verirdi.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../inc/products.php';

bm_admin_require_login();

if (!bm_csrf_check()) {
    http_response_code(403);
    echo '<p style="font:14px/1.6 sans-serif;padding:20px;color:#a11616;">'
       . 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.</p>';
    exit;
}

// Önizleme sayfası arama motorlarına düşmesin, tarayıcı da önbelleklemesin.
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$tur = $_POST['onizleme_turu'] ?? '';
$content = bm_load_content();

/** POST'tan gelen metni kırpıp döndürür */
function bm_p($key, $default = '') {
    $v = $_POST[$key] ?? null;
    return $v === null ? $default : trim((string)$v);
}

switch ($tur) {

    // ---- Teklif sayfası ----
    case 'teklif':
        $order = $content['order_section'] ?? [];
        $order['tag']        = bm_p('order_tag', $order['tag'] ?? '');
        $order['title']      = bm_p('order_title', $order['title'] ?? '');
        $order['text']       = bm_p('order_text', $order['text'] ?? '');
        $order['side_title'] = bm_p('side_title', $order['side_title'] ?? '');
        $order['side_text']  = bm_p('side_text', $order['side_text'] ?? '');
        $order['form_title'] = bm_p('form_title', $order['form_title'] ?? '');
        $order['hero_image'] = bm_p('hero_image', $order['hero_image'] ?? '');
        $order['hero_focus'] = bm_p('hero_focus', $order['hero_focus'] ?? '');
        $types = [];
        foreach ((array)($_POST['part_type'] ?? []) as $t) {
            $t = trim((string)$t);
            if ($t !== '') $types[] = $t;
        }
        if ($types) $order['part_types'] = $types;

        // İletişim ve sosyal medya butonları. Form gönderildiyse (tek satır bile
        // olsa) kayıtlı listenin yerine geçer — böylece silinen buton
        // önizlemede de kaybolur.
        $bm_liste = function ($prefix) {
            $out = [];
            foreach ((array)$_POST[$prefix . '_text'] as $i => $text) {
                $text  = trim((string)$text);
                $label = trim((string)($_POST[$prefix . '_label'][$i] ?? ''));
                $image = trim((string)($_POST[$prefix . '_image'][$i] ?? ''));
                if ($text === '' && $label === '' && $image === '') continue;
                $out[] = [
                    'icon'  => trim((string)($_POST[$prefix . '_icon'][$i] ?? '')),
                    'image' => $image,
                    'label' => $label,
                    'text'  => $text,
                    'type'  => trim((string)($_POST[$prefix . '_type'][$i] ?? 'text')),
                    'value' => trim((string)($_POST[$prefix . '_value'][$i] ?? '')),
                ];
            }
            return $out;
        };
        if (isset($_POST['cb_text'])) $order['contact_buttons'] = $bm_liste('cb');
        if (isset($_POST['sb_text'])) $order['social_buttons']  = $bm_liste('sb');
        $content['order_section'] = $order;
        bm_content_set($content);
        bm_preview_render(BM_ROOT . '/teklif.php');
        break;

    // ---- Ana sayfa ve genel ayarlar: ikisi de sadece content.json'u düzenliyor,
    //      panelin kendi kaydetme mantığını burada tekrar etmemek için POST
    //      alanları doğrudan eşleştiriliyor. ----
    case 'anasayfa':
        // Alan adları anasayfa.php'deki form ile birebir aynı olmalı.
        foreach ([
            'hero_tag'             => 'tag',
            'hero_tag_link'        => 'tag_link',
            'hero_title_before'    => 'title_before',
            'hero_title_highlight' => 'title_highlight',
            'hero_title_after'     => 'title_after',
            'hero_subtitle'        => 'subtitle',
            'hero_cta1_text'       => 'cta1_text',
            'hero_cta1_link'       => 'cta1_link',
            'hero_cta2_text'       => 'cta2_text',
            'hero_cta2_link'       => 'cta2_link',
        ] as $post => $key) {
            if (isset($_POST[$post])) $content['hero'][$key] = bm_p($post);
        }

        $content['hero']['stats'] = bm_preview_pairs('hero_stat_num', 'hero_stat_label', 'num', 'label')
            ?: ($content['hero']['stats'] ?? []);

        // Hero slaytları. Form gönderildiyse (boş bile olsa) kayıtlı listenin
        // yerine geçer — böylece silinen slayt önizlemede de kaybolur ve
        // kurulumla gelen örnekler geri gelmez.
        if (isset($_POST['hero_slide_image'])) {
            $slaytlar = [];
            foreach ((array)$_POST['hero_slide_image'] as $i => $img) {
                $img = trim((string)$img);
                if ($img === '') continue;
                $slaytlar[] = ['image' => $img, 'focus' => trim((string)($_POST['hero_slide_focus'][$i] ?? ''))];
            }
            $content['hero']['slides'] = $slaytlar;
        }
        if (isset($_POST['hero_slide_interval'])) {
            $content['hero']['slide_interval'] = bm_num_range($_POST['hero_slide_interval'], 2, 20, 6);
        }

        foreach ([
            'about_tag'          => 'tag',
            'about_title'        => 'title',
            'about_visual_emoji' => 'visual_emoji',
            'about_visual_image' => 'visual_image',
            'about_link_text'    => 'link_text',
            'about_link_target'  => 'link_target',
        ] as $post => $key) {
            if (isset($_POST[$post])) $content['about_teaser'][$key] = bm_p($post);
        }
        if (isset($_POST['about_html'])) $content['about_teaser']['html'] = $_POST['about_html'];
        $content['about_teaser']['stats'] = bm_preview_pairs('about_stat_num', 'about_stat_label', 'num', 'label')
            ?: ($content['about_teaser']['stats'] ?? []);

        // Hizmetler bu formda düzenlenmiyor (kendi bölümü var), kayıtlı hâli kalır.

        $content['order_section']['tag']   = bm_p('order_tag', $content['order_section']['tag'] ?? '');
        $content['order_section']['title'] = bm_p('order_title', $content['order_section']['title'] ?? '');
        $content['order_section']['text']  = bm_p('order_text', $content['order_section']['text'] ?? '');

        bm_content_set($content);
        bm_preview_render(BM_ROOT . '/index.php');
        break;

    case 'genel':
        foreach (['phone_display','phone_link','whatsapp_link','whatsapp_text','email','address',
                  'linkedin_link','footer_about','footer_note',
                  'phone_icon','whatsapp_icon','email_icon','address_icon'] as $k) {
            if (isset($_POST[$k])) $content['site'][$k] = bm_p($k);
        }
        $content['menu']['urunler_label']   = bm_p('urunler_label', $content['menu']['urunler_label'] ?? '');
        $content['menu']['hizmetler_label'] = bm_p('hizmetler_label', $content['menu']['hizmetler_label'] ?? '');
        bm_content_set($content);
        bm_preview_render(BM_ROOT . '/index.php');
        break;

    // ---- CMS sayfası (Hakkımızda, Blog, vb.) ----
    case 'sayfa':
        $slug = bm_p('slug');
        $page = bm_find_page($content, $slug);
        if (!$page) { echo '<p style="font:14px sans-serif;padding:20px;">Sayfa bulunamadı.</p>'; exit; }
        $page['title']         = bm_p('title', $page['title']);
        $page['hero_tag']      = bm_p('hero_tag', $page['hero_tag'] ?? '');
        $page['hero_subtitle'] = bm_p('hero_subtitle', $page['hero_subtitle'] ?? '');
        $page['hero_image'] = bm_p('hero_image', $page['hero_image'] ?? '');
        $page['hero_focus'] = bm_p('hero_focus', $page['hero_focus'] ?? '');
        if (isset($_POST['html'])) $page['html'] = $_POST['html'];
        // sayfa.php normalde slug'a göre veritabanı/JSON'dan okuyor;
        // önizlemede hazır diziyi enjekte ediyoruz.
        $GLOBALS['bm_preview_page'] = $page;
        bm_preview_render(BM_ROOT . '/sayfa.php');
        break;

    // ---- Hizmet detay sayfası ----
    case 'hizmet':
        $slug = bm_p('slug');
        $service = bm_find_service($content, $slug);
        if (!$service) { echo '<p style="font:14px sans-serif;padding:20px;">Hizmet bulunamadı.</p>'; exit; }

        $service['title']         = bm_p('title', $service['title']);
        $service['desc']          = bm_p('desc', $service['desc'] ?? '');
        $service['card_image']    = bm_p('card_image', $service['card_image'] ?? '');
        $service['card_focus']    = bm_p('card_focus', $service['card_focus'] ?? '');
        $service['card_blur']     = bm_num_range($_POST['card_blur'] ?? null, 0, 20, BM_CARD_BLUR_DEFAULT);
        $service['card_bright']   = bm_num_range($_POST['card_bright'] ?? null, 20, 130, BM_CARD_BRIGHT_DEFAULT);
        $service['hero_tag']      = bm_p('hero_tag', $service['hero_tag'] ?? '');
        $service['hero_subtitle'] = bm_p('hero_subtitle', $service['hero_subtitle'] ?? '');
        $service['image']         = bm_p('image', $service['image'] ?? '');
        $service['hero_image']    = bm_p('hero_image', $service['hero_image'] ?? '');
        $service['hero_focus']    = bm_p('hero_focus', $service['hero_focus'] ?? '');
        if (isset($_POST['html'])) $service['html'] = $_POST['html'];
        // Önizlemede sayfa her zaman gösterilir; "yayında" kutusu işaretli
        // olmasa da hazırlarken görebilmek gerekiyor.
        $service['has_page'] = true;

        $service['stats'] = bm_preview_pairs('stat_num', 'stat_label', 'num', 'label');

        $machines = [];
        foreach ((array)($_POST['mac_name'] ?? []) as $i => $name) {
            $name = trim((string)$name);
            if ($name === '') continue;
            $machines[] = [
                'name'  => $name,
                'emoji' => trim((string)($_POST['mac_emoji'][$i] ?? '')),
                'image' => trim((string)($_POST['mac_image'][$i] ?? '')),
                'desc'  => trim((string)($_POST['mac_desc'][$i] ?? '')),
                'specs' => bm_parse_spec_lines($_POST['mac_specs'][$i] ?? ''),
            ];
        }
        $service['machines'] = $machines;

        $GLOBALS['bm_preview_service'] = $service;
        bm_preview_render(BM_ROOT . '/hizmet.php');
        break;

    // ---- Ürün detay sayfası ----
    case 'urun':
        $specs = [];
        foreach ((array)($_POST['spec_label'] ?? []) as $i => $label) {
            $label = trim((string)$label);
            $value = trim((string)($_POST['spec_value'][$i] ?? ''));
            if ($label === '' && $value === '') continue;
            $specs[] = [$label, $value];
        }
        $catLabel = '';
        $catId = (int)bm_p('category_id');
        if ($catId) {
            foreach (bm_categories() as $c) {
                if ((int)$c['id'] === $catId) { $catLabel = $c['label']; break; }
            }
        }
        $GLOBALS['bm_preview_product'] = [
            'id'          => (int)bm_p('preview_id'),
            'slug'        => bm_p('preview_slug', 'onizleme'),
            'title'       => bm_p('title', 'Ürün adı') ?: 'Ürün adı',
            'code'        => bm_p('code'),
            'description' => bm_p('description'),
            'emoji'       => bm_p('emoji'),
            'image'       => bm_p('image'),
            'image_focus' => bm_p('image_focus'),
            'image_zoom'  => bm_num_range($_POST['image_zoom'] ?? null, 100, 250, 100),
            'specs'       => $specs,
            'category_id' => $catId ?: null,
            'cat_slug'    => '',
            'cat_label'   => $catLabel,
        ];
        bm_preview_render(BM_ROOT . '/urun.php');
        break;

    default:
        echo '<p style="font:14px sans-serif;padding:20px;">Önizleme türü tanımlı değil.</p>';
}

/**
 * Şablonu çalıştırır ve çıktıya önizlemeye özel bir stil ekler
 * (bağlantılar tıklanamaz olur — panelde gezinmeye çalışılmasın).
 */
function bm_preview_render($file) {
    ob_start();
    include $file;
    $html = ob_get_clean();

    // Önizlemede animasyon istemiyoruz: her yenilemede içerik baştan
    // kayarak gelirse düzenleme yaparken göz yorar.
    $css = '<style>'
         . 'a,button,input,select,textarea{pointer-events:none !important;}'
         . 'html{scroll-behavior:auto !important;}'
         . '*,*::before,*::after{animation:none !important;transition:none !important;}'
         . '.reveal{opacity:1 !important;transform:none !important;}'
         . '</style>';

    // </head> varsa oraya, yoksa en başa ekle
    $pos = stripos($html, '</head>');
    echo $pos !== false
        ? substr($html, 0, $pos) . $css . substr($html, $pos)
        : $css . $html;
}

/** İki paralel POST dizisini [{k1:..,k2:..}] biçimine çevirir */
function bm_preview_pairs($postA, $postB, $keyA, $keyB) {
    $out = [];
    foreach ((array)($_POST[$postA] ?? []) as $i => $a) {
        $a = trim((string)$a);
        $b = trim((string)($_POST[$postB][$i] ?? ''));
        if ($a === '' && $b === '') continue;
        $out[] = [$keyA => $a, $keyB => $b];
    }
    return $out;
}
