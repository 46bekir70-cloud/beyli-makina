/**
 * Görsel odak noktası seçici.
 *
 * Sorun: sayfa başlığı bandı geniş ve kısa bir alan; yüklenen fotoğraf oraya
 * kırpılarak sığdırılıyor ve varsayılan olarak merkezi gösteriliyor. Önemli
 * kısım (makine, tabela, insan) kenarda kalıyorsa kırpılıp gidiyor.
 *
 * Çözüm: kullanıcı görselin üstüne tıklayıp "burası ortada kalsın" diyor.
 * Seçilen nokta yüzde olarak gizli alana yazılıyor, site de onu
 * background-position olarak kullanıyor.
 *
 * Kullanım:
 *   <div class="focal-picker" data-focal-input="alanIdsi" data-focal-src="/images/x.jpg"></div>
 * Görsel yolu alanı değişince önizleme de kendini günceller.
 */
function bmInitFocalPickers() {
  document.querySelectorAll('.focal-picker').forEach(function (box) {
    if (box.dataset.ready === '1') return;
    box.dataset.ready = '1';

    var input = document.getElementById(box.dataset.focalInput);
    var srcInput = box.dataset.focalSrcInput
      ? document.getElementById(box.dataset.focalSrcInput)
      : null;
    if (!input) return;

    // Yakınlaştırma alanı bağlanmışsa çubuk da basılıyor
    var zoomInput = box.dataset.focalZoomInput
      ? document.getElementById(box.dataset.focalZoomInput)
      : null;

    box.innerHTML =
      '<div class="focal-stage">' +
        '<img alt="" class="focal-img">' +
        '<span class="focal-dot"></span>' +
        '<span class="focal-guide focal-guide-x"></span>' +
        '<span class="focal-guide focal-guide-y"></span>' +
      '</div>' +
      '<div class="focal-bar">' +
        '<span class="focal-val"></span>' +
        '<button type="button" class="focal-center">Ortala</button>' +
      '</div>' +
      (zoomInput
        ? '<div class="focal-zoom">' +
            '<label>Yakınlaştırma <span class="focal-zoom-val"></span></label>' +
            '<input type="range" class="focal-zoom-range" min="100" max="250" step="5">' +
            '<button type="button" class="focal-zoom-reset">Sıfırla</button>' +
          '</div>'
        : '') +
      '<p class="focal-hint"></p>';
    // Aynı seçici hem sayfa başlığı bandında hem hizmet kartında kullanılıyor;
    // ipucu metni çağıran yere göre değişebilsin.
    box.querySelector('.focal-hint').textContent = box.dataset.focalHint
      || 'Görselin üstüne tıkla — o nokta sayfa başlığında ortada kalır.';

    var stage = box.querySelector('.focal-stage');
    // Önizleme alanı, görselin konacağı yerle aynı orana sahip olmalı; yoksa
    // "ortaladım" dediği nokta sitede başka yere denk geliyor.
    if (box.dataset.focalRatio) stage.style.aspectRatio = box.dataset.focalRatio;
    var img   = box.querySelector('.focal-img');
    var dot   = box.querySelector('.focal-dot');
    var gx    = box.querySelector('.focal-guide-x');
    var gy    = box.querySelector('.focal-guide-y');
    var val   = box.querySelector('.focal-val');

    function oku() {
      var m = /^(\d{1,3}(?:\.\d+)?)%\s+(\d{1,3}(?:\.\d+)?)%$/.exec((input.value || '').trim());
      return m ? { x: parseFloat(m[1]), y: parseFloat(m[2]) } : { x: 50, y: 50 };
    }

    var zoomRange = box.querySelector('.focal-zoom-range');
    var zoomVal   = box.querySelector('.focal-zoom-val');

    /** Yakınlaştırma oranı (yüzde) */
    function zoomOku() {
      if (!zoomInput) return 100;
      var z = parseInt(zoomInput.value, 10);
      if (isNaN(z)) z = 100;
      return Math.min(250, Math.max(100, z));
    }

    function ciz(p) {
      dot.style.left = p.x + '%';
      dot.style.top  = p.y + '%';
      gx.style.left  = p.x + '%';
      gy.style.top   = p.y + '%';
      val.textContent = Math.round(p.x) + '% / ' + Math.round(p.y) + '%';

      // Önizleme sitedeki hâlin aynısı olsun: kırpma + odak + yakınlaştırma.
      // Büyütme merkezi odak noktası, böylece yakınlaştırınca seçilen yer kaçmıyor.
      var z = zoomOku();
      img.style.objectPosition = p.x + '% ' + p.y + '%';
      img.style.transformOrigin = p.x + '% ' + p.y + '%';
      img.style.transform = z > 100 ? 'scale(' + (z / 100) + ')' : '';
      if (zoomVal) zoomVal.textContent = z + '%';
      if (zoomRange && String(z) !== zoomRange.value) zoomRange.value = z;
    }

    function yaz(p) {
      input.value = Math.round(p.x) + '% ' + Math.round(p.y) + '%';
      ciz(p);
      // Önizleme paneli varsa yenilenmesi için formu tetikle
      var form = input.closest('form');
      if (form) form.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function kaynakGuncelle() {
      var src = (srcInput ? srcInput.value : box.dataset.focalSrc || '').trim();
      if (src) {
        img.src = src;
        box.classList.remove('is-empty');
      } else {
        img.removeAttribute('src');
        box.classList.add('is-empty');
      }
    }

    function tikla(ev) {
      var r = stage.getBoundingClientRect();
      if (!r.width || !r.height) return;
      var cx = (ev.touches ? ev.touches[0].clientX : ev.clientX) - r.left;
      var cy = (ev.touches ? ev.touches[0].clientY : ev.clientY) - r.top;
      // Kenar dışına taşan tıklamaları 0-100 arasına sıkıştır
      var x = Math.min(100, Math.max(0, (cx / r.width) * 100));
      var y = Math.min(100, Math.max(0, (cy / r.height) * 100));
      yaz({ x: x, y: y });
    }

    stage.addEventListener('click', tikla);
    // Sürükleyerek de ayarlanabilsin
    var basili = false;
    stage.addEventListener('mousedown', function () { basili = true; });
    document.addEventListener('mouseup', function () { basili = false; });
    stage.addEventListener('mousemove', function (ev) { if (basili) tikla(ev); });

    box.querySelector('.focal-center').addEventListener('click', function () {
      yaz({ x: 50, y: 50 });
    });

    if (zoomInput && zoomRange) {
      zoomRange.addEventListener('input', function () {
        zoomInput.value = zoomRange.value;
        ciz(oku());
        var form = zoomInput.closest('form');
        if (form) form.dispatchEvent(new Event('change', { bubbles: true }));
      });
      box.querySelector('.focal-zoom-reset').addEventListener('click', function () {
        zoomInput.value = 100;
        zoomRange.value = 100;
        ciz(oku());
        var form = zoomInput.closest('form');
        if (form) form.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }

    if (srcInput) srcInput.addEventListener('input', kaynakGuncelle);
    kaynakGuncelle();
    ciz(oku());
  });
}

/**
 * Kart görseli ayar çubukları + canlı kart önizlemesi.
 *
 * Hizmet kartında yazı fotoğrafın önünde duruyor; okunup okunmadığı ancak
 * gerçek kartta belli oluyor. Burada sitedeki kartın birebir kopyası duruyor ve
 * bulanıklık/parlaklık çubukları oynatıldıkça anında güncelleniyor — kaydetmeye
 * gerek kalmadan doğru değeri bulmak için.
 */
function bmInitCardTuners() {
  document.querySelectorAll('.card-tuner').forEach(function (box) {
    if (box.dataset.ready === '1') return;
    box.dataset.ready = '1';

    var d = box.dataset;
    var src    = document.getElementById(d.tunerSrc);
    var focus  = document.getElementById(d.tunerFocus);
    var blur   = document.getElementById(d.tunerBlur);
    var bright = document.getElementById(d.tunerBright);
    var title  = d.tunerTitle ? document.getElementById(d.tunerTitle) : null;
    var desc   = d.tunerDesc ? document.getElementById(d.tunerDesc) : null;
    if (!src || !blur || !bright) return;

    box.innerHTML =
      '<div class="tuner-card"><span class="tuner-bg"></span><h4></h4><p></p></div>' +
      '<p class="tuner-note">Kartın sitedeki görünümü.</p>';

    var bg = box.querySelector('.tuner-bg');
    var h  = box.querySelector('h4');
    var p  = box.querySelector('p');

    function val(el, min, max, def) {
      var n = parseInt(el.value, 10);
      if (isNaN(n)) return def;
      return Math.min(max, Math.max(min, n));
    }

    function ciz() {
      var yol = (src.value || '').trim();
      // Görsel yoksa önizlemenin gösterecek bir şeyi yok
      box.classList.toggle('is-empty', yol === '');
      if (yol === '') return;

      var odak = /^\d{1,3}(\.\d+)?%\s+\d{1,3}(\.\d+)?%$/.test((focus && focus.value || '').trim())
        ? focus.value.trim() : '50% 50%';

      bg.style.backgroundImage = "url('" + yol.replace(/'/g, "%27") + "')";
      bg.style.backgroundPosition = odak;
      bg.style.filter = 'blur(' + val(blur, 0, 20, 0) + 'px) brightness(' + val(bright, 20, 130, 100) + '%)';

      h.textContent = title ? (title.value || 'Hizmet adı') : 'Hizmet adı';
      p.textContent = desc ? (desc.value || '') : '';
    }

    function etiket(el) {
      var span = document.querySelector('[data-tuner-val="' + el.id + '"]');
      if (!span) return;
      span.textContent = el === blur
        ? val(el, 0, 20, 0) + ' px'
        : val(el, 20, 130, 100) + '%';
    }

    [blur, bright].forEach(function (el) {
      el.addEventListener('input', function () { etiket(el); ciz(); });
      etiket(el);
    });
    [src, focus, title, desc].forEach(function (el) {
      if (el) el.addEventListener('input', ciz);
    });
    // Odak noktası seçici gizli alanı JS ile değiştiriyor: 'input' tetiklemiyor,
    // formu 'change' ile uyarıyor. Oradan yakalıyoruz.
    var form = src.closest('form');
    if (form) form.addEventListener('change', ciz);

    ciz();
  });
}

document.addEventListener('DOMContentLoaded', function () {
  bmInitFocalPickers();
  bmInitCardTuners();
});
