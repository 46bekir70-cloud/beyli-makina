/**
 * Görsel seçici — "Siteden Seç" penceresi.
 *
 * Bilgisayardaki dosya seçme penceresinin karşılığı: solda klasörler, sağda
 * o klasördeki görseller. Bir görsele tıklayınca ilgili form alanına yolu
 * yazar ve önizlemeyi tazeler.
 *
 * Kullanımı: herhangi bir yere şu düğmeyi koymak yeterli —
 *   <button data-secici hedef="input_id">Siteden Seç</button>
 * ya da JS'ten: bmSeciciAc(function (url) { ... });
 */
(function () {
  var pencere = null;
  var geriCagir = null;
  var aktifKlasor = '';
  var aramaZaman = null;
  var secilenUrl = null;   // Onayla'ya basılana kadar forma yazılmaz

  function kur() {
    if (pencere) return pencere;

    pencere = document.createElement('div');
    pencere.className = 'sec-perde';
    pencere.hidden = true;
    pencere.innerHTML =
      '<div class="sec-pencere" role="dialog" aria-modal="true" aria-label="Görsel seç">' +
        '<div class="sec-baslik">' +
          '<strong>Görsel Seç</strong>' +
          '<input type="search" class="sec-ara" placeholder="Bütün klasörlerde ara…">' +
          '<button type="button" class="sec-kapat" aria-label="Kapat" title="Vazgeç ve kapat">✕</button>' +
        '</div>' +
        '<div class="sec-govde">' +
          '<aside class="sec-kenar"></aside>' +
          '<div class="sec-icerik"><div class="sec-durum">Yükleniyor…</div></div>' +
        '</div>' +
        '<div class="sec-alt">' +
          '<span class="sec-ipucu">Bir görsele tıkla, sonra <b>Onayla</b>’ya bas.</span>' +
          '<div class="sec-alt-dugmeler">' +
            '<a href="/teknikel/medya.php" target="_blank" class="kat-mini">Görseller ekranı ↗</a>' +
            '<button type="button" class="kat-mini sec-vazgec">Vazgeç</button>' +
            '<button type="button" class="btn sec-onayla" disabled>Onayla</button>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(pencere);

    pencere.querySelector('.sec-kapat').addEventListener('click', kapat);
    pencere.querySelector('.sec-vazgec').addEventListener('click', kapat);
    pencere.querySelector('.sec-onayla').addEventListener('click', onayla);
    pencere.addEventListener('click', function (e) { if (e.target === pencere) kapat(); });
    document.addEventListener('keydown', function (e) {
      if (!pencere || pencere.hidden) return;
      if (e.key === 'Escape') kapat();
      if (e.key === 'Enter' && secilenUrl) { e.preventDefault(); onayla(); }
    });

    var ara = pencere.querySelector('.sec-ara');
    ara.addEventListener('input', function () {
      clearTimeout(aramaZaman);
      aramaZaman = setTimeout(function () { yukle(aktifKlasor, ara.value.trim()); }, 250);
    });

    return pencere;
  }

  /** Vazgeçerek kapatır — forma hiçbir şey yazılmaz */
  function kapat() {
    if (!pencere) return;
    pencere.hidden = true;
    document.body.style.overflow = '';
    geriCagir = null;
    secilenUrl = null;
  }

  /** Seçimi forma uygular ve kapatır */
  function onayla() {
    if (!secilenUrl) return;
    var url = secilenUrl;
    var fn = geriCagir;
    kapat();
    if (fn) fn(url);
  }

  /** Seçili kartı işaretler, alt çubuğu ve Onayla düğmesini tazeler */
  function secimiTazele() {
    if (!pencere) return;
    pencere.querySelectorAll('.sec-dosya').forEach(function (d) {
      d.classList.toggle('secili', d.getAttribute('data-url') === secilenUrl);
    });
    var onay = pencere.querySelector('.sec-onayla');
    var ipucu = pencere.querySelector('.sec-ipucu');
    onay.disabled = !secilenUrl;
    if (secilenUrl) {
      var ad = secilenUrl.split('/').pop();
      ipucu.innerHTML = 'Seçilen: <b>' + ad.replace(/</g, '&lt;') + '</b>';
    } else {
      ipucu.innerHTML = 'Bir görsele tıkla, sonra <b>Onayla</b>’ya bas.';
    }
  }

  function yukle(klasor, q) {
    aktifKlasor = klasor || '';
    var icerik = pencere.querySelector('.sec-icerik');
    var kenar = pencere.querySelector('.sec-kenar');
    icerik.innerHTML = '<div class="sec-durum">Yükleniyor…</div>';

    fetch('/teknikel/medya-json.php?klasor=' + encodeURIComponent(aktifKlasor)
          + '&q=' + encodeURIComponent(q || ''))
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok) {
          icerik.innerHTML = '<div class="sec-durum">' + ((j && j.hata) || 'Liste alınamadı.') + '</div>';
          return;
        }

        // --- Klasörler ---
        var kh = '<button type="button" class="sec-klasor' + (aktifKlasor === '' ? ' on' : '') +
                 '" data-klasor="">🗂️ Tüm Görseller</button>';
        j.klasorler.forEach(function (k) {
          kh += '<button type="button" class="sec-klasor' + (aktifKlasor === k.rel ? ' on' : '') +
                '" data-klasor="' + k.rel.replace(/"/g, '&quot;') + '"' +
                ' style="padding-left:' + (10 + k.derinlik * 12) + 'px">' +
                (aktifKlasor === k.rel ? '📂' : '📁') + ' ' + k.ad +
                ' <span class="sec-sayi">' + k.sayi + '</span></button>';
        });
        kenar.innerHTML = kh;
        kenar.querySelectorAll('.sec-klasor').forEach(function (b) {
          b.addEventListener('click', function () {
            pencere.querySelector('.sec-ara').value = '';
            yukle(b.getAttribute('data-klasor'), '');
          });
        });

        // --- Dosyalar ---
        if (!j.dosyalar.length) {
          icerik.innerHTML = '<div class="sec-durum">' +
            (q ? 'Bu aramaya uyan görsel yok.' : 'Bu klasör boş.') + '</div>';
          return;
        }
        var dh = '<div class="sec-izgara">';
        j.dosyalar.forEach(function (f) {
          dh += '<button type="button" class="sec-dosya" data-url="' + f.url.replace(/"/g, '&quot;') + '" title="' +
                f.ad.replace(/"/g, '&quot;') + '">' +
                '<span class="sec-kutu"><img src="' + f.url + '" alt="" loading="lazy">' +
                  '<span class="sec-tik" aria-hidden="true">✓</span>' +
                '</span>' +
                '<span class="sec-ad">' + f.ad + '</span>' +
                (q && f.klasor ? '<span class="sec-konum">📁 ' + f.klasor + '</span>' : '') +
                '</button>';
        });
        dh += '</div>';
        icerik.innerHTML = dh;
        icerik.querySelectorAll('.sec-dosya').forEach(function (b) {
          // Tek tık: yalnızca seçer (işareti taşır). Uygulama Onayla ile olur —
          // böylece kullanıcı fikrini değiştirip başka görsele geçebiliyor.
          b.addEventListener('click', function () {
            secilenUrl = b.getAttribute('data-url');
            secimiTazele();
          });
          // Çift tık: seç ve doğrudan onayla (kısayol)
          b.addEventListener('dblclick', function () {
            secilenUrl = b.getAttribute('data-url');
            onayla();
          });
        });
        secimiTazele();   // klasör değiştirildiğinde işaret doğru kartta kalsın
      })
      .catch(function () {
        icerik.innerHTML = '<div class="sec-durum">Bağlantı hatası. Sayfayı yenileyip tekrar dene.</div>';
      });
  }

  /**
   * Seçiciyi açar. Kullanıcı Onayla'ya bastığında fn(url) çağrılır;
   * vazgeçerse hiç çağrılmaz.
   * $mevcut verilirse o görsel açılışta seçili görünür.
   */
  window.bmSeciciAc = function (fn, mevcut) {
    kur();
    geriCagir = fn;
    secilenUrl = mevcut || null;
    pencere.hidden = false;
    document.body.style.overflow = 'hidden';
    pencere.querySelector('.sec-ara').value = '';
    secimiTazele();

    // Alanda zaten bir görsel varsa pencere onun klasöründe açılsın —
    // kullanıcı seçili olanı görmek için klasör aramak zorunda kalmasın.
    var acilisKlasoru = aktifKlasor;
    if (secilenUrl && secilenUrl.indexOf('/images/uploads/') === 0) {
      var kalan = secilenUrl.slice('/images/uploads/'.length);
      var son = kalan.lastIndexOf('/');
      acilisKlasoru = son > 0 ? decodeURIComponent(kalan.slice(0, son)) : '';
    }
    yukle(acilisKlasoru, '');
  };

  /**
   * "Siteden Seç" düğmeleri — tıklama belge seviyesinde yakalanıyor.
   *
   * DİKKAT: düğmeler tek tek bağlanmıyor, bilerek. Mobil menü ve footer
   * ekranlarında "+ Buton Ekle" ile satırlar sayfa açıldıktan SONRA
   * ekleniyor; açılışta bağlanan bir dinleyici o yeni satırları görmüyor ve
   * düğme ölü kalıyordu. Belge üzerinden dinleyince sonradan eklenen her
   * satır kendiliğinden çalışıyor (iconup.js'teki "Yükle" düğmesi de aynı
   * yöntemi kullanıyor).
   */
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-secici]');
    if (!btn) return;
    ev.preventDefault();

    var kutu = btn.closest('.icon-up');
    var alan = kutu ? kutu.querySelector('.icon-up-path')
                    : document.getElementById(btn.getAttribute('data-hedef') || '');

    // Alandaki mevcut görsel pencerede seçili gelsin
    bmSeciciAc(function (url) {
      if (!alan) return;
      alan.value = url;
      // Önizleme ve odak seçici bu olayları dinliyor
      alan.dispatchEvent(new Event('input', { bubbles: true }));
      alan.dispatchEvent(new Event('change', { bubbles: true }));
    }, alan ? alan.value : '');
  });

  // Eski çağrılar hata vermesin diye duruyor; artık bir şey yapmasına gerek yok.
  window.bmSeciciBagla = function () {};
})();
