<?php
/**
 * CSV ile toplu ürün içe aktarma.
 *
 * 1000+ kalemi tek tek elle girmek gerçekçi olmadığı için Excel'den
 * kaydedilen bir CSV dosyası doğrudan yüklenebiliyor.
 *
 * Beklenen sütunlar (ilk satır başlık olmalı):
 *   kod;ad;kategori;aciklama;emoji;gorsel;ozellikler;vitrin;yayin;sira
 * "ozellikler" biçimi:  Malzeme=Çelik|Tolerans=±0.02 mm
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../inc/products.php';

bm_admin_require_login();

$bm_admin_active = 'urunler';
$bm_admin_title = 'CSV İçe Aktar';
$message = '';
$report = null;

if (!bm_config_exists() || !bm_db_ready()) {
    header('Location: /teknikel/veritabani-kurulum.php');
    exit;
}

/** Excel'in Türkçe yerelinde kaydettiği CSV genelde noktalı virgül ayraçlı olur. */
function bm_detect_delimiter($line) {
    return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, sayfayı yenileyip tekrar deneyin.';
    } elseif (empty($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $message = 'err|Dosya yüklenemedi. CSV dosyası seçtiğinizden emin olun.';
    } else {
        $path = $_FILES['csv']['tmp_name'];
        $updateExisting = !empty($_POST['update_existing']);

        $handle = fopen($path, 'r');
        if (!$handle) {
            $message = 'err|Dosya okunamadı.';
        } else {
            $first = fgets($handle);
            // Excel'in başa koyduğu BOM sütun adını bozuyor
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            $delim = bm_detect_delimiter($first);
            rewind($handle);

            $header = fgetcsv($handle, 0, $delim);
            if ($header) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                $header = array_map(function ($h) { return mb_strtolower(trim($h)); }, $header);
            }

            $col = array_flip($header ?: []);
            $get = function ($row, $name) use ($col) {
                return isset($col[$name]) ? trim((string)($row[$col[$name]] ?? '')) : '';
            };

            if (!isset($col['ad'])) {
                $message = 'err|CSV dosyasında "ad" sütunu bulunamadı. Örnek dosyayı indirip biçimi kontrol edin.';
            } else {
                // Kategoriler ada göre eşleştirilir, yoksa oluşturulur.
                $catByLabel = [];
                foreach (bm_categories() as $c) {
                    $catByLabel[mb_strtolower($c['label'])] = (int)$c['id'];
                }

                $pdo = bm_db();
                $insert = $pdo->prepare(
                    'INSERT INTO products (category_id, slug, title, code, description, emoji, image, specs, is_featured, is_active, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                );
                $updateByCode = $pdo->prepare(
                    'UPDATE products SET category_id=?, title=?, description=?, emoji=?, image=?, specs=?, is_featured=?, is_active=?, sort_order=?, updated_at=NOW()
                     WHERE code = ?'
                );
                $findByCode = $pdo->prepare('SELECT id FROM products WHERE code = ? LIMIT 1');
                $newCat = $pdo->prepare('INSERT INTO product_categories (slug, label, sort_order) VALUES (?,?,?)');

                $added = 0; $updated = 0; $skipped = 0; $errors = [];
                $line = 1;

                $pdo->beginTransaction();
                try {
                    while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                        $line++;
                        if (count(array_filter($row, function ($v) { return trim((string)$v) !== ''; })) === 0) {
                            continue; // boş satır
                        }

                        $title = $get($row, 'ad');
                        if ($title === '') {
                            $skipped++;
                            $errors[] = "Satır $line: ürün adı boş, atlandı.";
                            continue;
                        }

                        $catLabel = $get($row, 'kategori');
                        $catId = null;
                        if ($catLabel !== '') {
                            $key = mb_strtolower($catLabel);
                            if (!isset($catByLabel[$key])) {
                                $slug = bm_slugify($catLabel);
                                $newCat->execute([$slug, $catLabel, count($catByLabel) * 10 + 10]);
                                $catByLabel[$key] = (int)$pdo->lastInsertId();
                            }
                            $catId = $catByLabel[$key];
                        }

                        // "Malzeme=Çelik|Tolerans=±0.02" -> [[Malzeme,Çelik],[Tolerans,±0.02]]
                        $specs = [];
                        $specRaw = $get($row, 'ozellikler');
                        if ($specRaw !== '') {
                            foreach (explode('|', $specRaw) as $pair) {
                                $parts = explode('=', $pair, 2);
                                if (count($parts) === 2) {
                                    $specs[] = [trim($parts[0]), trim($parts[1])];
                                }
                            }
                        }

                        $code       = $get($row, 'kod');
                        $vitrinRaw  = mb_strtolower($get($row, 'vitrin'));
                        $yayinRaw   = mb_strtolower($get($row, 'yayin'));
                        $isFeatured = in_array($vitrinRaw, ['1', 'evet', 'x', 'true'], true) ? 1 : 0;
                        // "yayin" sütunu yoksa varsayılan yayında olsun
                        $isActive   = ($yayinRaw === '') ? 1 : (in_array($yayinRaw, ['0', 'hayir', 'hayır', 'false'], true) ? 0 : 1);

                        $common = [
                            $catId,
                            $title,
                            $get($row, 'aciklama') ?: null,
                            $get($row, 'emoji') ?: null,
                            $get($row, 'gorsel') ?: null,
                            json_encode($specs, JSON_UNESCAPED_UNICODE),
                            $isFeatured,
                            $isActive,
                            (int)($get($row, 'sira') ?: 0),
                        ];

                        $existingId = null;
                        if ($code !== '') {
                            $findByCode->execute([$code]);
                            $found = $findByCode->fetch();
                            $existingId = $found ? (int)$found['id'] : null;
                        }

                        if ($existingId && $updateExisting) {
                            $updateByCode->execute(array_merge($common, [$code]));
                            $updated++;
                        } elseif ($existingId) {
                            $skipped++;
                            $errors[] = "Satır $line: \"$code\" kodlu ürün zaten var, atlandı.";
                        } else {
                            $insert->execute([
                                $catId,
                                bm_unique_product_slug($title),
                                $title,
                                $code !== '' ? $code : null,
                                $common[2], $common[3], $common[4], $common[5], $common[6], $common[7], $common[8],
                            ]);
                            $added++;
                        }
                    }
                    $pdo->commit();
                    $report = ['added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
                    $message = 'ok|İçe aktarma tamamlandı.';
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    // Tümü geri alınır — yarım kalmış bir aktarım en kötüsü olurdu.
                    $message = 'err|Satır ' . $line . ' işlenirken hata oluştu, hiçbir kayıt yazılmadı: ' . $e->getMessage();
                }
            }
            fclose($handle);
        }
    }
}

// Örnek CSV indirme
if (isset($_GET['ornek'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="beyli-urun-ornek.csv"');
    echo "\xEF\xBB\xBF"; // Excel'in UTF-8 tanıması için BOM
    echo "kod;ad;kategori;aciklama;emoji;gorsel;ozellikler;vitrin;yayin;sira\n";
    echo "BM-TO-1001;Miller Şaft Ø40;Torna;Hassas işlenmiş miller şaft;⚙️;;Malzeme=Çelik (St 52)|Tolerans=±0.02 mm|Çap=Ø40 mm;evet;evet;10\n";
    echo "BM-FR-1002;Kalıp Gövdesi;Freze;;🧩;/images/uploads/kalip.jpg;Malzeme=Alüminyum 7075|Tolerans=±0.015 mm;hayir;evet;20\n";
    exit;
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>CSV ile Toplu Ürün Aktarma</h1>
    <div class="sub">Excel'den kaydettiğiniz listeyi tek seferde yükleyin.</div>
  </div>
  <a href="/teknikel/urunler.php" class="logout-link">← Ürün Listesi</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<?php if ($report): ?>
  <div class="card">
    <h2>Sonuç</h2>
    <ul style="color:var(--muted);font-size:14px;line-height:1.9;margin-left:18px;">
      <li><strong><?php echo $report['added']; ?></strong> ürün eklendi</li>
      <li><strong><?php echo $report['updated']; ?></strong> ürün güncellendi</li>
      <li><strong><?php echo $report['skipped']; ?></strong> satır atlandı</li>
    </ul>
    <?php if ($report['errors']): ?>
      <details style="margin-top:14px;">
        <summary style="cursor:pointer;color:var(--muted);font-size:14px;">Atlanan satırların ayrıntısı (<?php echo count($report['errors']); ?>)</summary>
        <ul style="color:var(--muted);font-size:13px;line-height:1.8;margin:10px 0 0 18px;">
          <?php foreach (array_slice($report['errors'], 0, 100) as $err): ?>
            <li><?php echo e($err); ?></li>
          <?php endforeach; ?>
          <?php if (count($report['errors']) > 100): ?>
            <li>… ve <?php echo count($report['errors']) - 100; ?> satır daha</li>
          <?php endif; ?>
        </ul>
      </details>
    <?php endif; ?>
    <p style="margin-top:16px;"><a href="/teknikel/urunler.php" class="btn">Ürün Listesine Git →</a></p>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Dosya Yükle</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <div class="field">
      <label for="csv">CSV Dosyası</label>
      <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
    </div>
    <label class="check">
      <input type="checkbox" name="update_existing" value="1">
      Aynı koda sahip ürünler varsa üzerine yaz (işaretlemezseniz atlanır)
    </label>
    <div class="form-actions">
      <button type="submit" class="btn">İçe Aktar</button>
      <a href="/teknikel/urun-ice-aktar.php?ornek=1" class="btn ghost">Örnek CSV İndir</a>
    </div>
  </form>
</div>

<div class="card">
  <h2>Dosya Biçimi</h2>
  <p class="card-hint">İlk satır sütun başlıkları olmalı. Sütun sırası önemli değil, isimleri önemli.</p>
  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>Sütun</th><th>Zorunlu</th><th>Açıklama</th></tr></thead>
      <tbody>
        <tr><td class="mono">ad</td><td>Evet</td><td>Ürün adı</td></tr>
        <tr><td class="mono">kod</td><td>Hayır</td><td>Parça/stok kodu. Tekrar aktarımlarda eşleştirme bununla yapılır.</td></tr>
        <tr><td class="mono">kategori</td><td>Hayır</td><td>Kategori adı. Yoksa otomatik oluşturulur.</td></tr>
        <tr><td class="mono">aciklama</td><td>Hayır</td><td>Serbest metin</td></tr>
        <tr><td class="mono">emoji</td><td>Hayır</td><td>Görsel yoksa kullanılır</td></tr>
        <tr><td class="mono">gorsel</td><td>Hayır</td><td>Örn. <span class="mono">/images/uploads/parca.jpg</span></td></tr>
        <tr><td class="mono">ozellikler</td><td>Hayır</td><td><span class="mono">Malzeme=Çelik|Tolerans=±0.02 mm</span> biçiminde</td></tr>
        <tr><td class="mono">vitrin</td><td>Hayır</td><td><span class="mono">evet</span> ise ana sayfa vitrininde çıkar</td></tr>
        <tr><td class="mono">yayin</td><td>Hayır</td><td><span class="mono">hayir</span> ise gizli kalır. Varsayılan: yayında</td></tr>
        <tr><td class="mono">sira</td><td>Hayır</td><td>Küçük sayı önce listelenir</td></tr>
      </tbody>
    </table>
  </div>
  <p class="card-hint" style="margin-top:14px;">
    Ayraç olarak hem noktalı virgül (<span class="mono">;</span>) hem virgül (<span class="mono">,</span>)
    desteklenir — Excel Türkçe sürümü genelde noktalı virgül kullanır.
    Bir satırda hata çıkarsa <strong>hiçbir kayıt yazılmaz</strong>, dosyayı düzeltip tekrar deneyebilirsiniz.
  </p>
</div>

<?php require __DIR__ . '/layout-bottom.php'; ?>
