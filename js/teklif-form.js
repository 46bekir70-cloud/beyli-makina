/**
 * Teklif formu — bireysel / kurumsal seçimi, telefon düzeni ve vergi no kontrolü.
 *
 * Bu dosyadaki her şey "yardımcı"dır: JavaScript kapalı olsa bile form çalışır,
 * bütün kontroller sunucu tarafında (teklif-gonder.php) tekrar yapılır.
 */
(function () {
  'use strict';

  var form = document.getElementById('orderForm');
  if (!form) return;

  /* ------------------------------------------------------------------
     1) BİREYSEL / KURUMSAL SEKMELERİ
     ------------------------------------------------------------------ */
  var turAlani   = form.querySelector('#musteriTuru');            // gizli input
  var sekmeler   = form.querySelectorAll('.tur-sekme');
  var kurumsalKt = form.querySelector('#kurumsalAlanlar');

  /** Zorunlu alanları açıp kapatır (kapalıyken tarayıcı uyarı vermesin diye) */
  function zorunluGuncelle(kurumsalMi) {
    if (!kurumsalKt) return;
    var alanlar = kurumsalKt.querySelectorAll('[data-zorunlu="1"]');
    for (var i = 0; i < alanlar.length; i++) {
      if (kurumsalMi) {
        alanlar[i].setAttribute('required', 'required');
      } else {
        alanlar[i].removeAttribute('required');
      }
    }
  }

  function turSec(tur) {
    var kurumsalMi = (tur === 'kurumsal');
    if (turAlani) turAlani.value = kurumsalMi ? 'kurumsal' : 'bireysel';

    for (var i = 0; i < sekmeler.length; i++) {
      var aktif = sekmeler[i].getAttribute('data-tur') === (kurumsalMi ? 'kurumsal' : 'bireysel');
      sekmeler[i].classList.toggle('aktif', aktif);
      sekmeler[i].setAttribute('aria-selected', aktif ? 'true' : 'false');
    }

    if (kurumsalKt) kurumsalKt.hidden = !kurumsalMi;
    zorunluGuncelle(kurumsalMi);

    // Adet / termin gibi ek alanlar bireyselde gizliyse onları da ayarla
    var ekler = form.querySelectorAll('[data-sadece-kurumsal="1"]');
    for (var j = 0; j < ekler.length; j++) ekler[j].hidden = !kurumsalMi;
  }

  for (var s = 0; s < sekmeler.length; s++) {
    sekmeler[s].addEventListener('click', function () {
      turSec(this.getAttribute('data-tur'));
    });
  }
  // Sayfa açılırken: hatalı gönderim sonrası kullanıcının seçimi korunur
  turSec(turAlani && turAlani.value === 'kurumsal' ? 'kurumsal' : 'bireysel');

  /* ------------------------------------------------------------------
     2) TELEFON ALANI — sadece rakam, ülkeye göre biçimleme
     ------------------------------------------------------------------ */
  var telefon    = form.querySelector('#phone');
  var ulkeKodu   = form.querySelector('#phoneCountry');
  var telNotu    = form.querySelector('#phoneNote');

  /** Seçili ülkenin ISO kodu */
  function seciliUlke() {
    if (!ulkeKodu) return 'TR';
    return (ulkeKodu.value || 'TR').toUpperCase();
  }

  /** Türkiye için 5XX XXX XX XX, diğerlerinde üçerli gruplama */
  function telBicimle(rakamlar, iso) {
    if (iso === 'TR') {
      var p = [];
      if (rakamlar.length > 0) p.push(rakamlar.substring(0, 3));
      if (rakamlar.length > 3) p.push(rakamlar.substring(3, 6));
      if (rakamlar.length > 6) p.push(rakamlar.substring(6, 8));
      if (rakamlar.length > 8) p.push(rakamlar.substring(8, 10));
      return p.join(' ');
    }
    return rakamlar.replace(/(.{3})/g, '$1 ').trim();
  }

  function telTemizle() {
    if (!telefon) return;
    var iso = seciliUlke();
    var rakamlar = (telefon.value || '').replace(/\D/g, '');

    // Baştaki 0'lar ve yanlışlıkla yazılan ülke kodu atılır
    var secenek = (ulkeKodu && ulkeKodu.selectedIndex >= 0) ? ulkeKodu.options[ulkeKodu.selectedIndex] : null;
    var dial = secenek ? (secenek.getAttribute('data-dial') || '') : '';
    if (dial && rakamlar.indexOf('00' + dial) === 0) rakamlar = rakamlar.substring(dial.length + 2);
    rakamlar = rakamlar.replace(/^0+/, '');

    var enFazla = (iso === 'TR') ? 10 : 14;
    if (rakamlar.length > enFazla) rakamlar = rakamlar.substring(0, enFazla);

    telefon.value = telBicimle(rakamlar, iso);

    // Uyarı: engellemez, sadece bilgilendirir
    if (telNotu) {
      var eksik = rakamlar.length > 0 && ((iso === 'TR' && rakamlar.length !== 10) || rakamlar.length < 4);
      telNotu.hidden = !eksik;
    }
  }

  if (telefon) {
    telefon.addEventListener('input', telTemizle);
    telefon.addEventListener('blur', telTemizle);
    telTemizle();
  }
  if (ulkeKodu) {
    ulkeKodu.addEventListener('change', function () {
      telTemizle();
      if (telefon) telefon.focus();
    });
  }

  /* ------------------------------------------------------------------
     3) VERGİ NO / TCKN — yalnızca uyarır, gönderimi engellemez
     ------------------------------------------------------------------ */
  var vergiNo    = form.querySelector('#taxno');
  var vergiUyari = form.querySelector('#taxnoUyari');
  var adresUlke  = form.querySelector('#companyCountry');
  var vergiLabel = form.querySelector('#taxnoLabel');

  function vknGecerli(no) {
    if (!/^\d{10}$/.test(no) || Number(no) === 0) return false;
    var toplam = 0;
    for (var i = 0; i < 9; i++) {
      var rakam = Number(no.charAt(i));
      var q = (rakam + (10 - (i + 1))) % 10;
      if (q === 0) { toplam += q; continue; }
      var ara = (q * Math.pow(2, 10 - (i + 1))) % 9;
      toplam += (ara === 0) ? 9 : ara;
    }
    return ((10 - (toplam % 10)) % 10) === Number(no.charAt(9));
  }

  function tcknGecerli(no) {
    if (!/^\d{11}$/.test(no) || no.charAt(0) === '0') return false;
    var tek = 0, cift = 0, tumu = 0, i;
    for (i = 0; i < 9; i += 2) tek += Number(no.charAt(i));
    for (i = 1; i < 9; i += 2) cift += Number(no.charAt(i));
    var onuncu = ((tek * 7) - cift) % 10;
    if (onuncu < 0) onuncu += 10;
    if (onuncu !== Number(no.charAt(9))) return false;
    for (i = 0; i < 10; i++) tumu += Number(no.charAt(i));
    return (tumu % 10) === Number(no.charAt(10));
  }

  /** Yurt dışı firmada Türkiye algoritması uygulanmaz */
  function yurtIciMi() {
    if (!adresUlke) return true;
    return (adresUlke.value || 'TR').toUpperCase() === 'TR';
  }

  function vergiKontrol() {
    if (!vergiNo || !vergiUyari) return;
    var no = (vergiNo.value || '').replace(/\D/g, '');
    if (!yurtIciMi() || no === '') { vergiUyari.hidden = true; return; }
    var gecerli = (no.length === 10) ? vknGecerli(no)
                : (no.length === 11) ? tcknGecerli(no)
                : false;
    vergiUyari.hidden = gecerli;
  }

  if (vergiNo) {
    // Yazılırken rakama indirger, yazmayı bitirince uyarır
    vergiNo.addEventListener('input', function () {
      var temiz = (vergiNo.value || '').replace(/\D/g, '').substring(0, 11);
      if (vergiNo.value !== temiz) vergiNo.value = temiz;
      if (!vergiUyari.hidden) vergiKontrol();
    });
    vergiNo.addEventListener('blur', vergiKontrol);
  }

  if (adresUlke) {
    adresUlke.addEventListener('change', function () {
      // Yurt dışı firma seçilirse alan adı "Vergi / VAT Numarası"na döner
      if (vergiLabel) {
        var yeni = yurtIciMi() ? vergiLabel.getAttribute('data-tr') : vergiLabel.getAttribute('data-yd');
        if (yeni) vergiLabel.firstChild.nodeValue = yeni + ' ';
      }
      if (vergiNo) {
        vergiNo.setAttribute('inputmode', yurtIciMi() ? 'numeric' : 'text');
      }
      vergiKontrol();
    });
  }
})();

/* ====================================================================
   ÜLKE SEÇİCİ + TARİH KUTUSU (görünüm iyileştirmeleri)

   Sayfada normal bir <select> duruyor. Burası onu gizleyip yerine
   "bayrak + kod" düğmesi ve aramalı bir liste koyuyor. JavaScript
   çalışmazsa <select> olduğu gibi görünür ve form yine çalışır.
   ==================================================================== */
(function () {
  'use strict';

  /** Karşılaştırma için Türkçe harfleri sadeleştirir (Türkiye -> turkiye) */
  function sade(metin) {
    return (metin || '').toString().toLowerCase()
      .replace(/\u0307/g, '')          // toLowerCase('İ') sonrası kalan nokta
      .replace(/ı/g, 'i').replace(/ş/g, 's').replace(/ğ/g, 'g')
      .replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ç/g, 'c')
      .replace(/â/g, 'a').replace(/î/g, 'i').replace(/û/g, 'u');
  }

  function ulkeSeciciKur(sarmal) {
    var select = sarmal.querySelector('select.tel-ulke-select');
    if (!select || !select.options.length) return;

    var araYazi = sarmal.getAttribute('data-ara')  || 'Ülke ara…';
    var bosYazi = sarmal.getAttribute('data-bos')  || 'Sonuç bulunamadı';

    // Seçenekleri bir kez okuyup hafızada tutuyoruz (her aramada DOM taranmasın)
    var kayitlar = [];
    for (var i = 0; i < select.options.length; i++) {
      var o = select.options[i];
      var ad = o.getAttribute('data-name') || o.text;
      var dial = o.getAttribute('data-dial') || '';
      kayitlar.push({
        iso: o.value,
        ad: ad,
        dial: dial,
        arama: sade(ad + ' ' + o.value + ' ' + dial)
      });
    }

    // Yerli listeyi gizle (gizli alan da form ile birlikte gönderilir)
    select.hidden = true;
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    var dugme = document.createElement('button');
    dugme.type = 'button';
    dugme.className = 'ulke-btn';
    dugme.setAttribute('aria-expanded', 'false');
    dugme.setAttribute('aria-haspopup', 'listbox');
    dugme.setAttribute('aria-label', select.getAttribute('aria-label') || 'Ülke kodu');
    dugme.innerHTML = '<span class="kod"></span><span class="ok"></span>';

    var panel = document.createElement('div');
    panel.className = 'ulke-panel';
    panel.hidden = true;

    var ara = document.createElement('input');
    ara.type = 'text';
    ara.className = 'ulke-ara';
    ara.setAttribute('autocomplete', 'off');
    ara.placeholder = araYazi;

    var liste = document.createElement('ul');
    liste.className = 'ulke-liste';
    liste.setAttribute('role', 'listbox');

    panel.appendChild(ara);
    panel.appendChild(liste);
    sarmal.insertBefore(dugme, select);
    sarmal.appendChild(panel);

    var vurgulu = -1;   // klavye ile gezilen satır
    var gorunen = [];   // o an listede duran kayıtlar

    /** Düğmedeki bayrak + kodu günceller */
    function dugmeYaz() {
      var secili = null;
      for (var i = 0; i < kayitlar.length; i++) {
        if (kayitlar[i].iso === select.value) { secili = kayitlar[i]; break; }
      }
      if (!secili) secili = kayitlar[0];
      dugme.querySelector('.kod').textContent = '+' + secili.dial;
      dugme.setAttribute('title', secili.ad + ' (+' + secili.dial + ')');
    }

    /** Listeyi (aramaya göre) yeniden çizer */
    function listeYaz(filtre) {
      var anahtar = sade(filtre).replace(/^\+/, '').trim();
      gorunen = [];
      liste.innerHTML = '';

      for (var i = 0; i < kayitlar.length; i++) {
        if (anahtar === '' || kayitlar[i].arama.indexOf(anahtar) !== -1) gorunen.push(kayitlar[i]);
      }

      if (!gorunen.length) {
        var bos = document.createElement('li');
        bos.className = 'ulke-bos';
        bos.textContent = bosYazi;
        liste.appendChild(bos);
        vurgulu = -1;
        return;
      }

      for (var j = 0; j < gorunen.length; j++) {
        var k = gorunen[j];
        var satir = document.createElement('li');
        satir.className = 'ulke-sec' + (k.iso === select.value ? ' secili' : '');
        satir.setAttribute('role', 'option');
        satir.setAttribute('data-iso', k.iso);
        satir.setAttribute('aria-selected', k.iso === select.value ? 'true' : 'false');
        satir.innerHTML = '<span class="ad"></span><span class="kod"></span>';
        satir.querySelector('.ad').textContent = k.ad;
        satir.querySelector('.kod').textContent = '+' + k.dial;
        liste.appendChild(satir);
      }

      vurgulu = 0;
      vurguYaz();
    }

    /** Klavye ile gezilen satırı işaretler ve görünür alana kaydırır */
    function vurguYaz() {
      var satirlar = liste.querySelectorAll('.ulke-sec');
      for (var i = 0; i < satirlar.length; i++) {
        if (i === vurgulu) {
          satirlar[i].classList.add('vurgu');
          if (satirlar[i].scrollIntoView) satirlar[i].scrollIntoView({ block: 'nearest' });
        } else {
          satirlar[i].classList.remove('vurgu');
        }
      }
    }

    function ac() {
      panel.hidden = false;
      dugme.setAttribute('aria-expanded', 'true');
      ara.value = '';
      listeYaz('');
      // Seçili ülkeyi görünür kıl
      var seciliSatir = liste.querySelector('.ulke-sec.secili');
      if (seciliSatir && seciliSatir.scrollIntoView) seciliSatir.scrollIntoView({ block: 'center' });
      ara.focus();
    }

    function kapa(odakDugmeye) {
      panel.hidden = true;
      dugme.setAttribute('aria-expanded', 'false');
      if (odakDugmeye) dugme.focus();
    }

    /** Ülkeyi seçer; <select> değiştiği için telefon maskesi de güncellenir */
    function sec(iso) {
      if (!iso) return;
      select.value = iso;
      if (typeof Event === 'function') {
        select.dispatchEvent(new Event('change', { bubbles: true }));
      } else if (document.createEvent) {
        var ev = document.createEvent('HTMLEvents');
        ev.initEvent('change', true, false);
        select.dispatchEvent(ev);
      }
      dugmeYaz();
      kapa(true);
      var numara = sarmal.querySelector('input[type="tel"]');
      if (numara) numara.focus();
    }

    dugme.addEventListener('click', function () {
      if (panel.hidden) ac(); else kapa(true);
    });

    ara.addEventListener('input', function () { listeYaz(ara.value); });

    ara.addEventListener('keydown', function (olay) {
      var satirSayisi = liste.querySelectorAll('.ulke-sec').length;
      if (olay.key === 'ArrowDown' || olay.key === 'ArrowUp') {
        olay.preventDefault();
        if (!satirSayisi) return;
        vurgulu += (olay.key === 'ArrowDown' ? 1 : -1);
        if (vurgulu < 0) vurgulu = satirSayisi - 1;
        if (vurgulu >= satirSayisi) vurgulu = 0;
        vurguYaz();
      } else if (olay.key === 'Enter') {
        olay.preventDefault();
        if (gorunen[vurgulu]) sec(gorunen[vurgulu].iso);
      } else if (olay.key === 'Escape') {
        olay.preventDefault();
        kapa(true);
      }
    });

    liste.addEventListener('click', function (olay) {
      var satir = olay.target;
      while (satir && satir !== liste && !satir.classList.contains('ulke-sec')) satir = satir.parentNode;
      if (satir && satir.classList && satir.classList.contains('ulke-sec')) sec(satir.getAttribute('data-iso'));
    });

    // Dışarı tıklanınca kapansın
    document.addEventListener('click', function (olay) {
      if (panel.hidden) return;
      if (!sarmal.contains(olay.target)) kapa(false);
    });

    dugmeYaz();
  }

  var gruplar = document.querySelectorAll('[data-tel-grup]');
  for (var g = 0; g < gruplar.length; g++) ulkeSeciciKur(gruplar[g]);

  /* --------------------------------------------------------------
     Tarih kutusundaki takvim düğmesi
     -------------------------------------------------------------- */
  var takvimler = document.querySelectorAll('.tarih-dugme');
  for (var t = 0; t < takvimler.length; t++) {
    (function (btn) {
      btn.addEventListener('click', function () {
        var alan = btn.parentNode.querySelector('input[type="date"]');
        if (!alan) return;
        alan.focus();
        if (typeof alan.showPicker === 'function') {
          try { alan.showPicker(); } catch (hata) { /* tarayıcı izin vermezse sorun değil */ }
        }
      });
    })(takvimler[t]);
  }
})();
