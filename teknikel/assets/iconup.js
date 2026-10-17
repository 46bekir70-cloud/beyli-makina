/**
 * Buton ikonu yükleyici.
 *
 * Panelde bir butonun yanındaki "Yükle" düğmesi dosya seçtiriyor, dosya
 * doğrudan images/uploads içine gidiyor ve dönen yol alana yazılıyor. Böylece
 * Görseller sayfasına gidip yol kopyalamak gerekmiyor.
 *
 * Olaylar belgeye bağlanıyor (delegation): buton satırları "+ Ekle" ile sonradan
 * da üretiliyor, o satırların ayrıca ilklendirilmesi gerekmesin.
 */
(function () {
  var dosyaSecici = null;
  var hedef = null; // yüklemeyi başlatan alan

  function secici() {
    if (dosyaSecici) return dosyaSecici;
    dosyaSecici = document.createElement('input');
    dosyaSecici.type = 'file';
    dosyaSecici.accept = 'image/png,image/webp,image/jpeg,image/gif';
    dosyaSecici.style.display = 'none';
    document.body.appendChild(dosyaSecici);
    dosyaSecici.addEventListener('change', function () {
      var f = dosyaSecici.files && dosyaSecici.files[0];
      dosyaSecici.value = ''; // aynı dosya tekrar seçilebilsin
      if (f && hedef) yukle(hedef, f);
    });
    return dosyaSecici;
  }

  function not(box, metin, hata) {
    var el = box.querySelector('.icon-up-note');
    if (!el) return;
    el.textContent = metin || '';
    el.classList.toggle('is-err', !!hata);
  }

  function yaz(box, url) {
    var path = box.querySelector('.icon-up-path');
    var img = box.querySelector('.icon-up-prev img');
    path.value = url;
    if (url) img.src = url; else img.removeAttribute('src');
    box.classList.toggle('is-empty', !url);
    // Yola bağlı diğer araçlar (odak noktası seçici) 'input' olayını dinliyor
    path.dispatchEvent(new Event('input', { bubbles: true }));
    // Sayfadaki canlı önizlemeler formun 'change' olayını dinliyor
    var form = path.closest('form');
    if (form) form.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function yukle(box, file) {
    if (!window.BM_CSRF) { not(box, 'Oturum bilgisi okunamadı, sayfayı yenile.', true); return; }
    if (file.size > 6 * 1024 * 1024) { not(box, 'Dosya 6 MB\'ı geçmemeli.', true); return; }

    box.classList.add('is-busy');
    not(box, 'Yükleniyor…');

    // Dosya adına ipucu: alan bir başlık alanına bağlanmışsa (ör. ürün adı)
    // yüklenen dosya o adla başlıyor, böylece kütüphanede aranabilir oluyor.
    var ad = '';
    if (box.dataset.nameFrom) {
      var kaynak = document.getElementById(box.dataset.nameFrom);
      if (kaynak) ad = (kaynak.value || '').trim();
    }

    fetch('/teknikel/upload-handler.php?raw=1&csrf=' + encodeURIComponent(window.BM_CSRF)
          + (ad ? '&ad=' + encodeURIComponent(ad) : ''), {
      method: 'POST',
      headers: { 'Content-Type': file.type || 'application/octet-stream' },
      body: file
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        box.classList.remove('is-busy');
        if (j && j.ok && j.url) { yaz(box, j.url); not(box, 'Yüklendi.'); }
        else not(box, (j && j.error) || 'Yükleme başarısız.', true);
      })
      .catch(function () {
        box.classList.remove('is-busy');
        not(box, 'Yükleme sırasında bağlantı hatası oluştu.', true);
      });
  }

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;
    var pick = ev.target.closest('.icon-up-pick');
    if (pick) {
      hedef = pick.closest('[data-icon-up]');
      if (hedef) secici().click();
      return;
    }
    var clear = ev.target.closest('.icon-up-clear');
    if (clear) {
      var box = clear.closest('[data-icon-up]');
      if (box) { yaz(box, ''); not(box, ''); }
    }
  });

  // Yol elle yazıldığında/yapıştırıldığında da küçük önizleme güncellensin
  document.addEventListener('input', function (ev) {
    if (!ev.target.closest) return;
    var path = ev.target.closest('.icon-up-path');
    if (!path) return;
    var box = path.closest('[data-icon-up]');
    if (!box) return;
    var img = box.querySelector('.icon-up-prev img');
    var v = path.value.trim();
    if (v) { img.src = v; box.classList.remove('is-empty'); }
    else { img.removeAttribute('src'); box.classList.add('is-empty'); }
  });
})();
