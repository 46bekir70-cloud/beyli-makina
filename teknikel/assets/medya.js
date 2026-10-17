/**
 * Görseller ekranı — dosya yöneticisi davranışları.
 *
 * Tek dosyalık işlemler (#tekIslemForm) gizli bir formla gönderiliyor: her
 * kartın içine ayrı form koymak yerine alanları doldurup submit ediyoruz,
 * böylece kartların içinde iç içe form olmuyor.
 */
(function () {
  var tekForm = document.getElementById('tekIslemForm');

  function gonder(action, rel, ad, hedef) {
    document.getElementById('ti_action').value = action;
    document.getElementById('ti_rel').value = rel || '';
    document.getElementById('ti_ad').value = ad || '';
    document.getElementById('ti_hedef').value = hedef || '';
    tekForm.submit();
  }

  window.bmDosyaAdlandir = function (rel, mevcutAd) {
    // Uzantı sunucu tarafında korunuyor; kullanıcıya uzantısız hâlini soruyoruz
    var nokta = mevcutAd.lastIndexOf('.');
    var govde = nokta > 0 ? mevcutAd.slice(0, nokta) : mevcutAd;
    var yeni = prompt('Yeni dosya adı (uzantı otomatik korunur):', govde);
    if (yeni === null) return;
    yeni = yeni.trim();
    if (!yeni) { alert('Dosya adı boş olamaz.'); return; }
    gonder('dosya_adlandir', rel, yeni);
  };

  window.bmKlasorAdlandir = function (rel, mevcutAd) {
    var yeni = prompt('Yeni klasör adı:', mevcutAd);
    if (yeni === null) return;
    yeni = yeni.trim();
    if (!yeni) { alert('Klasör adı boş olamaz.'); return; }
    gonder('klasor_adlandir', rel, yeni);
  };

  window.bmDosyaSil = function (rel, ad, kullaniliyor) {
    var uyari = ad + (kullaniliyor ? '\n\nDİKKAT: bu görsel sitede kullanılıyor!' : '');
    if (!confirm(uyari + '\n\nSilmek istediğine emin misin?')) return;
    gonder('dosya_sil', rel);
  };

  window.bmDosyaTasi = function (rel) {
    var liste = window.BM_KLASORLER || [];
    var satirlar = ['0 = Ana dizin'];
    liste.forEach(function (k, i) { satirlar.push((i + 1) + ' = ' + k.ad); });
    var cevap = prompt('Hangi klasöre taşınsın? Numarasını yaz:\n\n' + satirlar.join('\n'), '0');
    if (cevap === null) return;
    var n = parseInt(cevap, 10);
    if (isNaN(n) || n < 0 || n > liste.length) { alert('Geçersiz numara.'); return; }
    gonder('dosya_tasi', rel, '', n === 0 ? '' : liste[n - 1].rel);
  };

  window.bmYoluKopyala = function (btn, url) {
    var bitir = function (metin) {
      var eski = btn.textContent;
      btn.textContent = metin;
      setTimeout(function () { btn.textContent = eski; }, 1200);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function () { bitir('Kopyalandı'); },
                                              function () { prompt('Kopyala:', url); });
    } else {
      prompt('Kopyala:', url);
    }
  };

  // ---- Çoklu seçim ----
  var kutular = Array.prototype.slice.call(document.querySelectorAll('.dy-kutu'));
  var cubuk = document.getElementById('topluCubuk');
  var sayiEl = document.getElementById('secimSayisi');

  function tazele() {
    var n = kutular.filter(function (k) { return k.checked; }).length;
    if (cubuk) cubuk.hidden = n === 0;
    if (sayiEl) sayiEl.textContent = n + ' dosya seçildi';
    kutular.forEach(function (k) {
      k.closest('.dy-dosya').classList.toggle('secili', k.checked);
    });
  }
  kutular.forEach(function (k) { k.addEventListener('change', tazele); });

  window.bmSecimTemizle = function () {
    kutular.forEach(function (k) { k.checked = false; });
    tazele();
  };

  window.bmTopluOnay = function () {
    var n = kutular.filter(function (k) { return k.checked; }).length;
    var islem = document.getElementById('topluIslem').value;
    if (!n) { alert('Önce dosya seç.'); return false; }
    if (islem === 'sil') {
      return confirm(n + ' dosya SİLİNECEK. Bu geri alınamaz. Emin misin?');
    }
    return confirm(n + ' dosya taşınacak. Devam?');
  };

  // ---- Yükleme (sürükle-bırak + tıkla) ----
  var input = document.getElementById('mediaFileInput');
  var durum = document.getElementById('uploadStatus');
  var alan = document.getElementById('dropAlan');
  if (!input) return;

  // Dosyalar sırayla yükleniyor: aynı anda 20 istek atmak paylaşımlı
  // hostinglerde zaman aşımına düşüyor.
  function yukle(files, i, hatalar) {
    if (i >= files.length) {
      if (hatalar.length) {
        durum.textContent = (files.length - hatalar.length) + ' dosya yüklendi, '
          + hatalar.length + ' tanesi olmadı: ' + hatalar.join(' · ');
      } else {
        durum.textContent = files.length + ' dosya yüklendi, sayfa yenileniyor…';
        setTimeout(function () { location.reload(); }, 600);
      }
      return;
    }
    var f = files[i];
    durum.textContent = 'Yükleniyor (' + (i + 1) + '/' + files.length + '): ' + f.name;
    var adres = '/teknikel/upload-handler.php?raw=1'
      + '&csrf=' + encodeURIComponent(window.BM_CSRF)
      + '&klasor=' + encodeURIComponent(window.BM_KLASOR || '')
      + '&ad=' + encodeURIComponent(f.name.replace(/\.[^.]+$/, ''));
    fetch(adres, {
      method: 'POST',
      headers: { 'Content-Type': f.type || 'application/octet-stream' },
      body: f
    })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j || !j.ok) hatalar.push(f.name); yukle(files, i + 1, hatalar); })
      .catch(function () { hatalar.push(f.name); yukle(files, i + 1, hatalar); });
  }

  input.addEventListener('change', function () {
    var files = Array.prototype.slice.call(this.files);
    this.value = '';
    if (files.length) yukle(files, 0, []);
  });

  if (alan) {
    ['dragenter', 'dragover'].forEach(function (o) {
      alan.addEventListener(o, function (e) { e.preventDefault(); alan.classList.add('uzerinde'); });
    });
    ['dragleave', 'drop'].forEach(function (o) {
      alan.addEventListener(o, function (e) { e.preventDefault(); alan.classList.remove('uzerinde'); });
    });
    alan.addEventListener('drop', function (e) {
      var files = Array.prototype.slice.call(e.dataTransfer.files || [])
        .filter(function (f) { return f.type.indexOf('image/') === 0; });
      if (files.length) yukle(files, 0, []);
      else durum.textContent = 'Yalnızca görsel dosyası yükleyebilirsin.';
    });
  }
})();
