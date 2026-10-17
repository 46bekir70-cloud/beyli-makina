<?php
/**
 * Veritabanı kurulum ve kontrol ekranı.
 * Eksik tabloları tek tıkla kurar veya günceller.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/products.php';
require_once __DIR__ . '/../inc/articles.php';

bm_admin_require_login();

$bm_admin_active = 'genel';
$bm_admin_title = 'Veritabanı Kurulumu';
$message = '';

$configExists = bm_config_exists();
$dbConnected = false;
$dbError = '';
$tableStatus = [];

if ($configExists) {
    try {
        $pdo = bm_db();
        $dbConnected = true;

        // Tablo kontrolleri
        $tablolar = ['products', 'product_categories', 'product_category_map', 'articles'];
        foreach ($tablolar as $tbl) {
            try {
                $pdo->query("SELECT 1 FROM `{$tbl}` LIMIT 1");
                $tableStatus[$tbl] = true;
            } catch (Throwable $e) {
                $tableStatus[$tbl] = false;
            }
        }
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dbConnected) {
    if (!bm_csrf_check()) {
        $message = 'err|Oturum süresi doldu, lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'install') {
            try {
                $schemaFile = __DIR__ . '/../inc/schema.sql';
                if (file_exists($schemaFile)) {
                    bm_run_sql_file(bm_db(), $schemaFile);
                }
                bm_products_migrate();
                bm_categories_migrate();
                bm_articles_migrate();

                $message = 'ok|Tüm veritabanı tabloları başarıyla kuruldu ve güncellendi.';
                
                // Durumu tekrar tara
                foreach ($tablolar as $tbl) {
                    try {
                        bm_db()->query("SELECT 1 FROM `{$tbl}` LIMIT 1");
                        $tableStatus[$tbl] = true;
                    } catch (Throwable $e) {
                        $tableStatus[$tbl] = false;
                    }
                }
            } catch (Throwable $e) {
                $message = 'err|Kurulum sırasında hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

require __DIR__ . '/layout-top.php';
?>
<div class="admin-topline">
  <div>
    <h1>Veritabanı Durumu &amp; Kurulum</h1>
    <div class="sub">Ürünler, kategoriler ve makaleler için veritabanı bağlantısı ve tablo kontrolü</div>
  </div>
  <a href="/teknikel/urunler.php" class="logout-link">← Ürün Listesine Dön</a>
</div>

<?php if ($message): [$type, $text] = explode('|', $message, 2); ?>
  <div class="flash <?php echo $type === 'ok' ? 'ok' : 'err'; ?>"><?php echo e($text); ?></div>
<?php endif; ?>

<div class="card">
  <h2>1. Yapılandırma Dosyası (inc/config.php)</h2>
  <?php if ($configExists): ?>
    <p><span class="badge on">Mevcut</span> <code>inc/config.php</code> dosyası bulundu.</p>
  <?php else: ?>
    <p><span class="badge off">Eksik</span> <code>inc/config.php</code> dosyası bulunamadı. Lütfen <code>inc/config.ornek.php</code> dosyasını kopyalayıp veritabanı bilgilerinizi girin.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>2. Veritabanı Bağlantısı</h2>
  <?php if ($dbConnected): ?>
    <p><span class="badge on">Bağlantı Başarılı</span> MySQL veritabanına başarıyla bağlanıldı.</p>
  <?php else: ?>
    <p><span class="badge off">Bağlanılamadı</span> <?php echo e($dbError ?: 'Yapılandırma dosyası bekleniyor.'); ?></p>
  <?php endif; ?>
</div>

<?php if ($dbConnected): ?>
<div class="card">
  <h2>3. Tablo Durumları</h2>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Tablo Adı</th>
          <th>Açıklama</th>
          <th>Durum</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><code>products</code></td>
          <td>Ürün kataloğu ve teknik özellikler</td>
          <td><?php echo !empty($tableStatus['products']) ? '<span class="badge on">Kurulu</span>' : '<span class="badge off">Eksik</span>'; ?></td>
        </tr>
        <tr>
          <td><code>product_categories</code></td>
          <td>Ürün kategorileri ve ağaç yapısı</td>
          <td><?php echo !empty($tableStatus['product_categories']) ? '<span class="badge on">Kurulu</span>' : '<span class="badge off">Eksik</span>'; ?></td>
        </tr>
        <tr>
          <td><code>product_category_map</code></td>
          <td>Ürün - Çoklu Kategori eşleştirmesi</td>
          <td><?php echo !empty($tableStatus['product_category_map']) ? '<span class="badge on">Kurulu</span>' : '<span class="badge off">Eksik</span>'; ?></td>
        </tr>
        <tr>
          <td><code>articles</code></td>
          <td>Blog ve makale yazıları</td>
          <td><?php echo !empty($tableStatus['articles']) ? '<span class="badge on">Kurulu</span>' : '<span class="badge off">Eksik</span>'; ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <form method="post" style="margin-top:20px;">
    <input type="hidden" name="csrf" value="<?php echo e(bm_csrf_token()); ?>">
    <input type="hidden" name="action" value="install">
    <button type="submit" class="btn">⚡ Tabloları Kur / Güncelle</button>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/layout-bottom.php'; ?>
