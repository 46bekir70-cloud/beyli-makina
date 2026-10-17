-- Beyli Makina — ürün kataloğu tabloları
-- admin/veritabani-kurulum.php bu dosyayı çalıştırır.

CREATE TABLE IF NOT EXISTS product_categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(80)  NOT NULL,
  label       VARCHAR(120) NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_category_slug (slug),
  KEY idx_category_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id  INT UNSIGNED NULL,
  slug         VARCHAR(190) NOT NULL,
  title        VARCHAR(190) NOT NULL,
  -- Stok/parça kodu. Aramada en çok bu kullanılacağı için ayrı tutuluyor.
  code         VARCHAR(80)  NULL,
  description  TEXT         NULL,
  emoji        VARCHAR(16)  NULL,
  image        VARCHAR(255) NULL,
  -- Görselin odak noktası ("50% 30%" gibi). Kartlarda ve detay sayfasında
  -- fotoğraf kırpılarak sığdırıldığı için hangi bölgenin ortada kalacağını
  -- belirler. Kurulu veritabanlarına admin tarafından sonradan eklenir.
  image_focus  VARCHAR(16)  NULL,
  -- Yakınlaştırma yüzdesi (100 = olduğu gibi). Küçük duran parçaları kadraja
  -- doldurmak için kullanılıyor.
  image_zoom   SMALLINT UNSIGNED NULL,
  -- Teknik özellikler: [["Malzeme","Çelik"],["Tolerans","±0.02 mm"]] biçiminde JSON.
  -- MariaDB'de JSON tipi LONGTEXT'e karşılık geldiği için TEXT kullanıyoruz.
  specs        TEXT         NULL,
  is_featured  TINYINT(1)   NOT NULL DEFAULT 0,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order   INT          NOT NULL DEFAULT 0,
  -- DİKKAT: MySQL/MariaDB 5.5'te bir tabloda yalnızca BİR sütun
  -- DEFAULT/ON UPDATE CURRENT_TIMESTAMP kullanabilir (hata 1293).
  -- Bu yüzden created_at otomatik, updated_at ise PHP tarafından yazılıyor.
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_slug (slug),
  KEY idx_product_category (category_id),
  KEY idx_product_featured (is_featured, is_active),
  KEY idx_product_active_sort (is_active, sort_order, id),
  KEY idx_product_code (code),
  KEY idx_product_title (title),
  CONSTRAINT fk_product_category FOREIGN KEY (category_id)
    REFERENCES product_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
