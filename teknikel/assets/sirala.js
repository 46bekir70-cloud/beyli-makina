/* Sıra düzenleme (↑ ↓) — panelin tamamında ortak.

   Panelde iki tür sıralanabilir liste var:

   A) Sırası ekrandaki dizilişten okunan listeler (.repeat-row / .spec-row).
      Kaydedilirken alanlar name="...[]" olarak belge sırasıyla gidiyor, yani
      satırı yukarı taşımak tek başına yeterli.

   B) Sırası bir sayı kutusundan okunan listeler (tablolardaki Sıra alanı,
      kategori ağacındaki Sıra kutusu). Burada satırı taşımak yetmez, sayıların
      da yer değiştirmesi gerekir.

   Bu dosya iki durumu da kendisi ayırt eder; sayfalara tek tek düğme eklemek
   gerekmiyor. Sonradan JS ile eklenen satırlar da MutationObserver ile yakalanır.

   Not: satırlar taşınırken innerHTML'e dokunulmuyor, düğümün kendisi
   insertBefore ile yer değiştiriyor — böylece görsel seçici, odak noktası
   seçicisi gibi kurulmuş parçalar bozulmadan taşınıyor. */
(function () {
  'use strict';

  // Sıra sayısı tutan kutuların adları
  var SAYI_ADI = /^(sort_order|nav_order|sira)(\[|$)/;

  /* ---------- yardımcılar ---------- */

  function satirTuru(el) {
    if (el.classList.contains('kat-dugum')) return 'kat';
    if (el.tagName === 'TR') return 'tr';
    if (el.classList.contains('spec-row')) return 'spec';
    if (el.classList.contains('repeat-row')) return 'repeat';
    return '';
  }

  // Aynı kapsayıcıdaki, aynı türden komşu satırlar
  function kardesler(row) {
    var tur = satirTuru(row);
    var liste = [];
    var kap = row.parentElement;
    if (!kap) return liste;
    for (var i = 0; i < kap.children.length; i++) {
      var c = kap.children[i];
      if (satirTuru(c) === tur) liste.push(c);
    }
    return liste;
  }

  // Satırın sıra sayısı kutusu (yoksa null → sıra dizilişten okunuyor demektir)
  function sayiKutusu(row) {
    // Kategori satırı: kendi kutusu (alt kategorilerinki değil)
    var k = row.querySelector(':scope > .kat-satir input.kat-sira');
    if (k) return k;
    var adaylar = row.querySelectorAll('input[type="number"]');
    for (var i = 0; i < adaylar.length; i++) {
      // Kategori ağacında iç içe liste var: sadece kendi satırındakini al
      if (adaylar[i].closest('.kat-dugum, tr, .repeat-row, .spec-row') !== row) continue;
      if (SAYI_ADI.test(adaylar[i].getAttribute('name') || '')) return adaylar[i];
    }
    return null;
  }

  function formBul(el) {
    return el.closest('form') || document.querySelector('form');
  }

  /* ---------- düğmeleri yerleştir ---------- */

  function dugmeKutusu() {
    var kutu = document.createElement('span');
    kutu.className = 'row-move';
    kutu.innerHTML =
      '<button type="button" class="row-up" title="Yukarı taşı" aria-label="Yukarı taşı">↑</button>' +
      '<button type="button" class="row-down" title="Aşağı taşı" aria-label="Aşağı taşı">↓</button>';
    return kutu;
  }

  function kur(row) {
    if (row.dataset.siraKuruldu) return;
    var tur = satirTuru(row);
    if (!tur) return;
    row.dataset.siraKuruldu = '1';

    var kutu = dugmeKutusu();

    if (tur === 'kat') {
      // Kategori satırı: işlem düğmelerinin başına
      var islem = row.querySelector(':scope > .kat-satir > .kat-islem');
      if (!islem) { delete row.dataset.siraKuruldu; return; }
      kutu.classList.add('row-move-inline');
      islem.insertBefore(kutu, islem.firstChild);

    } else if (tur === 'tr') {
      // Tablo satırı: Sıra kutusunun yanına
      var sayi = sayiKutusu(row);
      if (!sayi) { delete row.dataset.siraKuruldu; return; }
      kutu.classList.add('row-move-inline');
      var hucre = sayi.closest('td');
      if (hucre) { hucre.classList.add('sira-hucre'); hucre.appendChild(kutu); }
      else sayi.parentNode.insertBefore(kutu, sayi.nextSibling);

    } else if (tur === 'spec') {
      // Tek satırlık alanlar: silme düğmesinden önce
      kutu.classList.add('row-move-inline');
      var sil = row.querySelector('.row-del, .type-del');
      if (sil) row.insertBefore(kutu, sil);
      else row.appendChild(kutu);

    } else {
      // Kutu şeklindeki satırlar: sağ üstteki ✕ düğmesinin soluna
      kutu.classList.add('row-move-abs');
      row.appendChild(kutu);
    }

    durumGuncelle(kardesler(row));
  }

  // İlk satırda ↑, son satırda ↓ pasif olsun; tek satırlıysa hiç görünmesin
  function durumGuncelle(liste) {
    liste.forEach(function (row, i) {
      var kutu = row.querySelector(':scope > .row-move, :scope > .kat-satir > .kat-islem > .row-move, :scope > td > .row-move');
      if (!kutu) return;
      kutu.classList.toggle('tek', liste.length < 2);
      var yukari = kutu.querySelector('.row-up');
      var asagi = kutu.querySelector('.row-down');
      if (yukari) yukari.disabled = (i === 0);
      if (asagi) asagi.disabled = (i === liste.length - 1);
    });
  }

  /* ---------- taşıma ---------- */

  /* Sayı kutulu listelerde sayıları yeniden yaz.
     Sayılar birbirinden farklıysa mevcut sayılar korunur, sadece satırlar
     arasında yer değiştirir (başka sayfalardaki kayıtlarla çakışmasın diye).
     Aynı sayılar varsa (ör. hepsi 0) küçükten başlayarak 10'ar 10'ar dağıtılır. */
  function sayilariYaz(liste) {
    var kutular = liste.map(sayiKutusu);
    if (kutular.indexOf(null) !== -1) return;

    var degerler = kutular.map(function (k) { return parseInt(k.value, 10) || 0; });
    var sirali = degerler.slice().sort(function (a, b) { return a - b; });

    var tekrarVar = false;
    for (var i = 1; i < sirali.length; i++) if (sirali[i] === sirali[i - 1]) tekrarVar = true;

    kutular.forEach(function (k, idx) {
      k.value = tekrarVar ? (sirali[0] + idx * 10) : sirali[idx];
    });
  }

  function uyariGoster(row) {
    var kap = row.parentElement;
    if (!kap || kap.dataset.siraUyari) return;
    kap.dataset.siraUyari = '1';
    var not = document.createElement('div');
    not.className = 'sira-uyari';
    not.textContent = 'Sıra değişti — kaydetmeyi unutma.';
    if (kap.tagName === 'TBODY') {
      var tablo = kap.closest('table');
      var sarmal = tablo && tablo.parentElement;
      if (sarmal) sarmal.parentNode.insertBefore(not, sarmal.nextSibling);
    } else {
      kap.parentNode.insertBefore(not, kap.nextSibling);
    }
  }

  function tasi(row, yon) {
    var liste = kardesler(row);
    var i = liste.indexOf(row);
    var hedef = i + yon;
    if (i < 0 || hedef < 0 || hedef >= liste.length) return;

    var kap = row.parentElement;
    if (yon < 0) kap.insertBefore(row, liste[hedef]);
    else kap.insertBefore(liste[hedef], row);

    var yeniListe = kardesler(row);
    if (sayiKutusu(row)) sayilariYaz(yeniListe);
    durumGuncelle(yeniListe);
    uyariGoster(row);

    // Önizleme paneli açıksa yenilensin
    var form = formBul(row);
    if (form) form.dispatchEvent(new Event('change', { bubbles: true }));

    // Taşınan satır gözden kaçmasın
    var odak = row.querySelector('.row-up, .row-down');
    if (odak && !odak.disabled) odak.focus();
    else {
      var digeri = row.querySelector('.row-up:not([disabled]), .row-down:not([disabled])');
      if (digeri) digeri.focus();
    }
  }

  /* ---------- kurulum ---------- */

  function tara() {
    document.querySelectorAll('.repeat-row, .spec-row, .kat-dugum, table.admin-table tbody tr')
      .forEach(kur);
    // Satır silindiğinde/eklendiğinde uç düğmelerin durumu değişir
    document.querySelectorAll('.row-move').forEach(function (kutu) {
      var row = kutu.closest('.repeat-row, .spec-row, .kat-dugum, tr');
      if (row) durumGuncelle(kardesler(row));
    });
  }

  document.addEventListener('click', function (e) {
    var d = e.target.closest && e.target.closest('.row-up, .row-down');
    if (!d || d.disabled) return;
    e.preventDefault();
    var row = d.closest('.repeat-row, .spec-row, .kat-dugum, tr');
    if (row) tasi(row, d.classList.contains('row-up') ? -1 : 1);
  });

  function baslat() {
    tara();
    var bekle = null;
    new MutationObserver(function () {
      clearTimeout(bekle);
      bekle = setTimeout(tara, 60);
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', baslat);
  else baslat();
})();
