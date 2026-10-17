/**
 * Admin panel canlı önizleme.
 *
 * Formun o anki (henüz kaydedilmemiş) değerlerini POST ile önizleme adresine
 * gönderir; önizleme dosyası siteyi bu geçici değerlerle üretip iframe'e basar.
 * Diske hiçbir şey yazılmaz.
 *
 * Formun kendisini iframe'e yönlendirmiyoruz — bir hata olursa gerçek kaydın
 * önizlemeye gitme riski var. Onun yerine değerler geçici gizli bir forma
 * kopyalanıp o gönderiliyor.
 */
function bmInitPreview(opts) {
  var form = document.getElementById(opts.formId);
  var frame = document.getElementById('bmPreviewFrame');
  var wrap = document.getElementById('previewWrap');
  if (!form || !frame) return;

  function submitPreview() {
    // Zengin metin editörü varsa içeriğini gizli alana yaz
    if (typeof window.bmSyncEditors === 'function') {
      try { window.bmSyncEditors(); } catch (e) { /* önizleme yüzünden sayfa kırılmasın */ }
    }

    var tmp = document.createElement('form');
    tmp.method = 'post';
    tmp.action = opts.url;
    tmp.target = 'bmPreview';
    tmp.style.display = 'none';

    var data = new FormData(form);
    data.forEach(function (value, key) {
      // Dosyalar önizlemeye taşınmıyor (gerek de yok)
      if (value instanceof File) return;
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = key;
      input.value = value;
      tmp.appendChild(input);
    });

    document.body.appendChild(tmp);
    tmp.submit();
    document.body.removeChild(tmp);
  }

  // İlk yükleme
  submitPreview();

  // Alan değişince yenile. 'input' değil 'change' dinliyoruz: her harfte
  // sunucuya istek atmak yerine alandan çıkıldığında bir kez yeniliyor.
  var timer;
  form.addEventListener('change', function () {
    clearTimeout(timer);
    timer = setTimeout(submitPreview, 250);
  });

  var refreshBtn = document.querySelector('.prev-refresh');
  if (refreshBtn) refreshBtn.addEventListener('click', submitPreview);

  /**
   * Ekran genişliği seçimi — masaüstü / tablet / telefon.
   *
   * Önizleme paneli dar (~600px) olduğu için 1280px'lik bir sayfa oraya
   * sığmıyor. Çerçeveyi gerçek genişlikte açıp CSS ile küçültüyoruz: sayfa
   * o genişlikteki MEDIA SORGULARINI görüyor, biz de tamamını görüyoruz.
   * Eskiden "Masaüstü" panelin kendi genişliğini kullanıyordu, yani aslında
   * tablet görünümü gösteriyordu.
   */
  function boyutUygula(hedefGenislik) {
    var kullanilabilir = wrap.clientWidth;
    var oran = Math.min(1, kullanilabilir / hedefGenislik);

    frame.style.width = hedefGenislik + 'px';
    // Küçültme sonrası yükseklik de aynı oranda azalacağı için, çerçeveyi
    // önce oranın tersi kadar uzatıyoruz ki panel dikeyde boş kalmasın.
    frame.style.height = (wrap.clientHeight / oran) + 'px';
    frame.style.transform = oran < 1 ? 'scale(' + oran + ')' : '';
    frame.style.transformOrigin = 'top left';
    // Telefon genişliğinde çerçeve panele sığıyor; ortalanması için işaret
    wrap.classList.toggle('mobile', hedefGenislik <= 480 && oran >= 1);
  }

  var aktifGenislik = 1280;
  document.querySelectorAll('.prev-size').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.prev-size').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      aktifGenislik = parseInt(btn.dataset.w, 10) || 1280;
      boyutUygula(aktifGenislik);
    });
  });

  // Panel genişliği değişince (pencere boyutu) ölçek yeniden hesaplanmalı
  window.addEventListener('resize', function () { boyutUygula(aktifGenislik); });
  boyutUygula(aktifGenislik);
}
