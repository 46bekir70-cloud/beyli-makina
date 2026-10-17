-- ========================================================
-- BEYLÄ° MAKÄ°NA CANLI ÃœRÃœN VE KATEGORÄ° VERÄ°TABANI YEDEÄÄ°
-- Otomatik olarak canlÄ± sistemden tam uyumlu yedeklenmiÅŸtir.
-- ========================================================

-- 1. Kategoriler
INSERT INTO product_categories (slug, label, sort_order) VALUES ('kuyumcu-malzemeleri', 'KUYUMCU MALZEMELERİ', 0) ON DUPLICATE KEY UPDATE label='KUYUMCU MALZEMELERİ';
INSERT INTO product_categories (slug, label, sort_order) VALUES ('iplik-›-open-end-›-schlft-›-s11', 'İPLİK › OPEN-END › SCHLFT › S11', 0) ON DUPLICATE KEY UPDATE label='İPLİK › OPEN-END › SCHLFT › S11';
INSERT INTO product_categories (slug, label, sort_order) VALUES ('iplik-›-open-end-›-schlft-›-aco', 'İPLİK › OPEN-END › SCHLFT › ACO', 0) ON DUPLICATE KEY UPDATE label='İPLİK › OPEN-END › SCHLFT › ACO';
INSERT INTO product_categories (slug, label, sort_order) VALUES ('tekstil-makinalari-›-iplik-makinalari-›-open-end-›-schlft-›-aco', 'TEKSTİL MAKİNALARI › İPLİK MAKİNALARI › OPEN-END › SCHLFT › ACO', 0) ON DUPLICATE KEY UPDATE label='TEKSTİL MAKİNALARI › İPLİK MAKİNALARI › OPEN-END › SCHLFT › ACO';
INSERT INTO product_categories (slug, label, sort_order) VALUES ('iplik-›-open-end-›-rtr-open-end-›-r70', 'İPLİK › OPEN-END › RTR OPEN-END › R70', 0) ON DUPLICATE KEY UPDATE label='İPLİK › OPEN-END › RTR OPEN-END › R70';

-- 2. ÃœrÃ¼nler
INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'yuzuk-buyutme-makine', 'YÜZÜK BÜYÜTME MAKİNE', 'TEK006455', '/images/uploads/urun-gorselleri/yuzuk-buyutme-makine-20260921_194128_2b83dc.jpg', 0, 1, 0 FROM product_categories WHERE slug='kuyumcu-malzemeleri' LIMIT 1
ON DUPLICATE KEY UPDATE title='YÜZÜK BÜYÜTME MAKİNE', code='TEK006455', image='/images/uploads/urun-gorselleri/yuzuk-buyutme-makine-20260921_194128_2b83dc.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-enig-eksantrik-mil', 'Open-End İĞ EKSANTRİK MİL', 'TEK000005', '/images/uploads/urun-gorselleri/open-end-ig-eksantrik-mil-20260921_193729_1d6d51.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-s11' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End İĞ EKSANTRİK MİL', code='TEK000005', image='/images/uploads/urun-gorselleri/open-end-ig-eksantrik-mil-20260921_193729_1d6d51.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'l-elek', 'L  ELEK', 'TEK006856', '/images/uploads/urun-gorselleri/l-elek-20260921_193157_17a3e0.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='L  ELEK', code='TEK006856', image='/images/uploads/urun-gorselleri/l-elek-20260921_193157_17a3e0.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-baglayici-piston-kolu', 'Open-End BAĞLAYICI PİSTON KOLU', 'TEK006137', '/images/uploads/urun-gorselleri/open-end-baglayici-piston-kolu-20260921_192907_b7ad83.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End BAĞLAYICI PİSTON KOLU', code='TEK006137', image='/images/uploads/urun-gorselleri/open-end-baglayici-piston-kolu-20260921_192907_b7ad83.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-baglayici-enjeksiyon-kapli-teker', 'Open-End BAĞLAYICI ENJEKSİYON KAPLI TEKER', 'TEK005482', '/images/uploads/urun-gorselleri/open-end-baglayici-enjeksiyon-kapli-teker-20260921_192619_deb880.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End BAĞLAYICI ENJEKSİYON KAPLI TEKER', code='TEK005482', image='/images/uploads/urun-gorselleri/open-end-baglayici-enjeksiyon-kapli-teker-20260921_192619_deb880.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'bant-motor-sanzimani-disli-mil', 'BANT MOTOR ŞANZIMANI  DİŞLİ MİL', 'TEK000458', '/images/uploads/urun-gorselleri/bant-motor-sanzimani-disli-mil-20260921_192401_cfbec1.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='BANT MOTOR ŞANZIMANI  DİŞLİ MİL', code='TEK000458', image='/images/uploads/urun-gorselleri/bant-motor-sanzimani-disli-mil-20260921_192401_cfbec1.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-kasnak-gerdirme-parcasi-takim', 'Open-End KASNAK GERDİRME PARÇASI TAKIM', 'TEK007198', '/images/uploads/urun-gorselleri/open-end-kasnak-gerdirme-parcasi-takim-20260921_192226_e402fe.jpg', 0, 1, 0 FROM product_categories WHERE slug='tekstil-makinalari-›-iplik-makinalari-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End KASNAK GERDİRME PARÇASI TAKIM', code='TEK007198', image='/images/uploads/urun-gorselleri/open-end-kasnak-gerdirme-parcasi-takim-20260921_192226_e402fe.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-kapak-sac-yay', 'Open-End KAPAK SAÇ YAY', 'TEK005520', '/images/uploads/urun-gorselleri/open-end-kapak-sac-yay-20260921_191935_0b42cc.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End KAPAK SAÇ YAY', code='TEK005520', image='/images/uploads/urun-gorselleri/open-end-kapak-sac-yay-20260921_191935_0b42cc.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'kam-mili-5', 'KAM MİLİ', 'TEK007237', '/images/uploads/urun-gorselleri/kam-mili-20260921_191800_8fa7b9.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='KAM MİLİ', code='TEK007237', image='/images/uploads/urun-gorselleri/kam-mili-20260921_191800_8fa7b9.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'fitil-kelebek-triger-disli-gobek', 'FİTİL KELEBEK TRİGER DİŞLİ GÖBEK', 'TEK006272', '/images/uploads/urun-gorselleri/fitil-kelebek-triger-disli-gobek-20260921_191441_b267d8.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='FİTİL KELEBEK TRİGER DİŞLİ GÖBEK', code='TEK006272', image='/images/uploads/urun-gorselleri/fitil-kelebek-triger-disli-gobek-20260921_191441_b267d8.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'kam-mili-4', 'KAM MİLİ', 'TEK004402', '/images/uploads/urun-gorselleri/kam-mili-20260921_191113_2b4d24.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='KAM MİLİ', code='TEK004402', image='/images/uploads/urun-gorselleri/kam-mili-20260921_191113_2b4d24.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-sanziman-kanalli-sari-kece-yatagi', 'Open-End ŞANZIMAN KANALLI SARI KEÇE YATAĞI', 'TEK002446', '/images/uploads/urun-gorselleri/open-end-sanziman-kanalli-sari-kece-yatagi-20260921_190922_72b048.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End ŞANZIMAN KANALLI SARI KEÇE YATAĞI', code='TEK002446', image='/images/uploads/urun-gorselleri/open-end-sanziman-kanalli-sari-kece-yatagi-20260921_190922_72b048.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'on-frenleme-kolu-parcasi-az-acili', 'ÖN FRENLEME KOLU PARÇASI (Az Açılı)', 'TEK006089', '/images/uploads/urun-gorselleri/on-frenleme-kolu-parcasi-az-acili-20260921_190716_7858d6.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='ÖN FRENLEME KOLU PARÇASI (Az Açılı)', code='TEK006089', image='/images/uploads/urun-gorselleri/on-frenleme-kolu-parcasi-az-acili-20260921_190716_7858d6.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-tasiyici-ana-kam-mili', 'Open-End TAŞIYICI ANA KAM MİLİ', 'TEK001198', '/images/uploads/urun-gorselleri/open-end-tasiyici-ana-kam-mili-20260921_190011_eba442.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End TAŞIYICI ANA KAM MİLİ', code='TEK001198', image='/images/uploads/urun-gorselleri/open-end-tasiyici-ana-kam-mili-20260921_190011_eba442.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'cekme-yayi', 'ÇEKME YAYI', 'TEK001743', '/images/uploads/urun-gorselleri/cekme-yayi-20260921_185731_c47261.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='ÇEKME YAYI', code='TEK001743', image='/images/uploads/urun-gorselleri/cekme-yayi-20260921_185731_c47261.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'kam-mili-3', 'KAM MİLİ', 'TEK001198', '/images/uploads/urun-gorselleri/kam-mili-20260921_185101_fb70e7.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='KAM MİLİ', code='TEK001198', image='/images/uploads/urun-gorselleri/kam-mili-20260921_185101_fb70e7.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'yuruyus-celik-tekeri', 'YÜRÜYÜŞ ÇELİK TEKERİ', 'TEK001188', '/images/uploads/urun-gorselleri/yuruyus-celik-tekeri-20260921_184839_b63843.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='YÜRÜYÜŞ ÇELİK TEKERİ', code='TEK001188', image='/images/uploads/urun-gorselleri/yuruyus-celik-tekeri-20260921_184839_b63843.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'kam-capraz-baglanti', 'KAM ÇAPRAZ BAĞLANTI', 'TEK001179', '/images/uploads/urun-gorselleri/kam-capraz-baglanti-20260921_184442_c31747.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='KAM ÇAPRAZ BAĞLANTI', code='TEK001179', image='/images/uploads/urun-gorselleri/kam-capraz-baglanti-20260921_184442_c31747.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'y-eksen-derlin-yatak', 'Y EKSEN DERLİN YATAK', 'TEK001129', '/images/uploads/urun-gorselleri/y-eksen-derlin-yatak-20260921_183711_b36d8a.jpg', 0, 1, 0 FROM product_categories WHERE slug='tekstil-makinalari-›-iplik-makinalari-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='Y EKSEN DERLİN YATAK', code='TEK001129', image='/images/uploads/urun-gorselleri/y-eksen-derlin-yatak-20260921_183711_b36d8a.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'koromat-hareket-mili', 'KOROMAT HAREKET MİLİ', 'TEK001127', '/images/uploads/urun-gorselleri/koromat-hareket-mili-20260921_183515_3eaaf9.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='KOROMAT HAREKET MİLİ', code='TEK001127', image='/images/uploads/urun-gorselleri/koromat-hareket-mili-20260921_183515_3eaaf9.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'fiber-duz-disli', 'FİBER DÜZ DİŞLİ', 'TEK001420', '/images/uploads/urun-gorselleri/fiber-duz-disli-20260921_183240_aa3b11.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='FİBER DÜZ DİŞLİ', code='TEK001420', image='/images/uploads/urun-gorselleri/fiber-duz-disli-20260921_183240_aa3b11.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-kam-dislisi', 'Open-End KAM DİŞLİSİ', 'TEK005936', '/images/uploads/urun-gorselleri/open-end-kam-dislisi-20260921_182757_4d587f.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-schlft-›-aco' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End KAM DİŞLİSİ', code='TEK005936', image='/images/uploads/urun-gorselleri/open-end-kam-dislisi-20260921_182757_4d587f.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-baglanti-parcasi', 'Open-End BAĞLANTI PARÇASI', 'TEK007173', '/images/uploads/urun-gorselleri/open-end-baglanti-parcasi-20260921_182449_155e73.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-rtr-open-end-›-r70' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End BAĞLANTI PARÇASI', code='TEK007173', image='/images/uploads/urun-gorselleri/open-end-baglanti-parcasi-20260921_182449_155e73.jpg';

INSERT INTO products (category_id, slug, title, code, image, is_featured, is_active, sort_order)
SELECT id, 'open-end-baglanti-saci-2', 'Open-End BAĞLANTI SACI', 'TEK007172', '/images/uploads/urun-gorselleri/open-end-baglanti-saci-20260921_182318_938219.jpg', 0, 1, 0 FROM product_categories WHERE slug='iplik-›-open-end-›-rtr-open-end-›-r70' LIMIT 1
ON DUPLICATE KEY UPDATE title='Open-End BAĞLANTI SACI', code='TEK007172', image='/images/uploads/urun-gorselleri/open-end-baglanti-saci-20260921_182318_938219.jpg';

