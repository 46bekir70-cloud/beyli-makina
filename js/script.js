/* =========================================================
   BEYLİ MAKİNA — Ortak JavaScript
   index.html, about.html, blog.html tarafından ortak kullanılır.
   Her blok, ilgili elemanın sayfada bulunup bulunmadığını kontrol eder,
   böylece hangi sayfada çalıştırılırsa çalıştırılsın hata vermez.
   ========================================================= */

// ---- Teklif formu ----
// Form artık doğrudan sunucuya (teklif-gonder.php) POST ediliyor; mail dosya
// ekiyle beraber PHP tarafından gönderiliyor. Gmail penceresi açma yöntemi
// kaldırıldı — o yöntemle maile dosya eklenemiyordu.
const orderForm = document.getElementById('orderForm');

if (orderForm) {
  // Gönder butonunu çift tıklamaya karşı kilitle (dosya yüklemesi sürebiliyor)
  orderForm.addEventListener('submit', function () {
    const btn = orderForm.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Gönderiliyor…';
    }
  });

  // Ürün detay sayfasından "Bu Parça İçin Teklif İste" ile gelindiyse
  // hangi ürün olduğunu forma taşı.
  const productField = document.getElementById('productField');
  const detailsField = document.getElementById('details');
  const urunParam = new URLSearchParams(window.location.search).get('urun');
  if (urunParam && productField) {
    productField.value = urunParam;
    if (detailsField && detailsField.value.trim() === '') {
      // "Malzeme" satırı bilerek yok: katalogdaki parçanın malzemesi zaten
      // belli olduğu için müşteriye gereksiz bir soru olarak görünüyordu.
      detailsField.value = urunParam + ' için teklif istiyorum.\n\nAdet: \nNotlar: ';
    }
  }

  // Sonuç mesajı varsa forma kaydır
  const flash = orderForm.querySelector('.form-flash');
  if (flash) {
    flash.scrollIntoView({ block: 'center' });
  }
}

// NOT: Eski katalog filtresi (JS ile kart gizleme) kaldırıldı. Ürünler artık
// veritabanından geliyor ve filtreleme /urunler sayfasında sunucu tarafında
// yapılıyor — 1000+ ürünün tamamını sayfaya basıp JS ile gizlemek mümkün değil.

// ---- Scroll ile beliren elemanlar (.reveal) ----
// Gizleme CSS'te .js sınıfına bağlı; bu dosya çalışmazsa içerik yine görünür.
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      observer.unobserve(entry.target); // bir kez göster, tekrar tetiklenmesin
    }
  });
}, {
  // Öğe ekranın biraz altındayken başlasın; kullanıcı oraya varana kadar
  // animasyon tamamlanmış olur, "geç kalmış" hissi vermez.
  threshold: 0,
  // Tetikleme anı: öğenin üst kenarı ekranın alt kenarına değdiği an (40px tampon).
  // İki uç da yanlış: -10% → kart ekrana girdikten sonra başlıyor ("zıplıyor"),
  // +22% → kart gelmeden animasyon bitiyor ("animasyon yok" hissi).
  rootMargin: '0px 0px 40px 0px'
});

// Geçiş süresi: görülecek kadar uzun, bekletmeyecek kadar kısa.
(function () {
  const s = document.createElement('style');
  s.textContent = '.js .reveal{transition-duration:.5s;}';
  document.head.appendChild(s);
})();

document.querySelectorAll('.reveal').forEach(el => {
  // Aynı kapsayıcıdaki kardeşler sırayla gelsin (kademeli gecikme).
  // Gecikme 6 adımda duruyor — 24 ürünlük listede son kart 1.5 saniye
  // beklemesin diye.
  const parent = el.parentElement;
  if (parent) {
    const siblings = Array.prototype.filter.call(
      parent.children, c => c.classList.contains('reveal')
    );
    if (siblings.length > 1) {
      const index = siblings.indexOf(el);
      // 6 x 0.06s (0.36s) yerine 4 x 0.05s (0.20s): son kart beklemesin
      el.style.setProperty('--bm-delay', (Math.min(index, 4) * 0.05) + 's');
    }
  }
  observer.observe(el);
});

// ---- Mobil menü aç/kapa ----
const navToggle = document.getElementById('navToggle');
const navLinks = document.getElementById('navLinks');
const navBackdrop = document.getElementById('navBackdrop');

function setNavOpen(open) {
  if (!navLinks) return;
  navLinks.classList.toggle('open', open);
  if (navBackdrop) navBackdrop.classList.toggle('show', open);
  // Menü açıkken arkadaki sayfa kaymasın
  document.body.classList.toggle('nav-open', open);
  if (navToggle) navToggle.textContent = open ? '✕' : '☰';
  if (!open) {
    document.querySelectorAll('.nav-links > li.open').forEach(li => li.classList.remove('open'));
  }
}

if (navToggle && navLinks) {
  navToggle.addEventListener('click', function () {
    setNavOpen(!navLinks.classList.contains('open'));
  });
}
if (navBackdrop) {
  navBackdrop.addEventListener('click', function () { setNavOpen(false); });
}

// ---- Dokunmatik cihazlarda dropdown menüler: tıklayınca aç/kapa ----
// Fare olan cihazlarda CSS hover üzerinden zaten açılıyor. Dokunmatik bir tablet
// 960px'den geniş olabildiği için sadece genişliğe bakmak yetmiyor — hover
// desteğini de kontrol ediyoruz, yoksa o cihazlarda menü hiç açılmaz.
function bmTouchNav() {
  return window.innerWidth <= 960 || window.matchMedia('(hover: none)').matches;
}

document.querySelectorAll('.nav-links > li').forEach(li => {
  const link = li.querySelector(':scope > a');
  const dropdown = li.querySelector(':scope > .dropdown');
  if (!link || !dropdown) return;
  link.addEventListener('click', function (e) {
    if (bmTouchNav()) {
      e.preventDefault();
      const wasOpen = li.classList.contains('open');
      document.querySelectorAll('.nav-links > li.open').forEach(other => other.classList.remove('open'));
      li.classList.toggle('open', !wasOpen);
    }
  });
});

// Menüdeki normal bir bağlantıya tıklandığında menü kapansın
// (dropdown başlıkları hariç — onlar yukarıda alt menüyü açıp kapatıyor)
document.querySelectorAll('.nav-links a').forEach(a => {
  const parent = a.parentElement;
  const isDropdownToggle = parent.tagName === 'LI' && parent.querySelector(':scope > .dropdown');
  if (isDropdownToggle) return;
  a.addEventListener('click', function () {
    if (window.innerWidth <= 960) setNavOpen(false);
  });
});

// Dokunmatik masaüstü/tablet: dışarı tıklanınca açık dropdown'ı kapat
document.addEventListener('click', function (e) {
  if (window.innerWidth <= 960) return; // mobil panelin kendi kapatma mantığı var
  if (!e.target.closest('.nav-links > li')) {
    document.querySelectorAll('.nav-links > li.open').forEach(li => li.classList.remove('open'));
  }
});

// Sayfa dışına tıklayınca mobil menüyü kapat
document.addEventListener('click', function (e) {
  if (!navLinks) return;
  if (window.innerWidth <= 960 && navLinks.classList.contains('open')) {
    if (!navLinks.contains(e.target) && e.target !== navToggle) {
      setNavOpen(false);
    }
  }
});

// ESC ile kapat
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' && navLinks && navLinks.classList.contains('open')) setNavOpen(false);
});

// ---- Masaüstünde: menüye sığmayan öğeleri otomatik "Diğer" klasörüne taşı ----
// Ürünlerimiz, Hizmetlerimiz ve İletişim her zaman görünür kalır (öncelikli
// menüler); sığmadığında yalnızca panelden eklenen sayfalar (Hakkımızda, Blog…)
// "Diğer" dropdown'una taşınır.
// Alanda yer açılınca (pencere genişleyince) taşınan öğeler otomatik geri döner.
(function () {
  const navMore = document.getElementById('navMore');
  const moreDropdown = document.getElementById('moreDropdown');
  if (!navLinks || !navMore || !moreDropdown) return;

  // Taşan aday öğeler: yalnızca panelden eklenen sayfalar. Listeyi tersine
  // çeviriyoruz ki menünün en sonundaki (en az öncelikli) öğe ilk taşınsın.
  const overflowOrder = Array.from(document.querySelectorAll('.nav-links > li.nav-overflowable')).reverse();

  function resetOverflow() {
    moreDropdown.innerHTML = '';
    overflowOrder.forEach(li => { li.style.display = ''; });
    navMore.style.display = 'none';
  }

  function isWrapped() {
    // Yalnızca gerçekten ekranda olan öğeler sayılır. offsetParent kontrolü
    // şart: menü panelinin yalnız mobilde görünen öğeleri (logo başlığı ve alt
    // buton bloğu) masaüstünde CSS ile gizli ve offsetTop'ları 0 dönüyor —
    // satır kontrolü onları da hesaba katınca menü her genişlikte "taşıyor"
    // görünüyor ve sayfalar boşu boşuna "Diğer" listesine giriyordu.
    const items = Array.from(navLinks.children)
      .filter(li => li.style.display !== 'none' && li.offsetParent !== null);
    if (items.length < 2) return false;
    const firstTop = items[0].offsetTop;
    return items.some(li => li.offsetTop > firstTop + 5);
  }

  function moveToMore(li) {
    // Grup başlığı olan öğelerin kendi bağlantısı yok (javascript:void(0));
    // "Diğer" listesine grubun içindeki sayfaları taşıyoruz, yoksa tıklanınca
    // hiçbir yere gitmeyen ölü bir satır oluşuyor.
    const grup = li.querySelector(':scope > .dropdown');
    if (grup) {
      grup.querySelectorAll(':scope > a').forEach(function (a) {
        const c = a.cloneNode(true);
        c.classList.remove('active');
        moreDropdown.appendChild(c);
      });
    } else {
      const clone = li.querySelector(':scope > a').cloneNode(true);
      clone.classList.remove('active');
      moreDropdown.appendChild(clone);
    }
    li.style.display = 'none';
    navMore.style.display = '';
  }

  function fitNav() {
    if (window.innerWidth <= 960) { resetOverflow(); return; }
    resetOverflow();
    let guard = 0;
    while (isWrapped() && guard < overflowOrder.length) {
      const li = overflowOrder[guard];
      if (li.style.display !== 'none') moveToMore(li);
      guard++;
    }
  }

  fitNav();
  window.addEventListener('load', fitNav);

  let resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(fitNav, 150);
  });
})();

// ---- Ana sayfa hero slayt gösterisi ----
// Görseller yatay kayar, altındaki noktalar hangi slaytta olduğunu gösterir.
// JS çalışmazsa ilk görsel sabit kalır — içerik her koşulda görünür durumda.
(function () {
  const kutu = document.querySelector('[data-hero-slider]');
  if (!kutu) return;

  const track = kutu.querySelector('.hero-track');
  const slaytlar = kutu.querySelectorAll('.hero-slide');
  const noktalar = Array.from(document.querySelectorAll('[data-hero-dots] .hero-dot'));
  if (!track || slaytlar.length < 2) return;

  const sure = Math.max(2000, parseInt(kutu.dataset.interval, 10) || 6000);
  const azHareket = window.matchMedia('(prefers-reduced-motion: reduce)');
  let sira = 0;
  let zaman = null;

  function goster(i) {
    sira = (i + slaytlar.length) % slaytlar.length;
    track.style.transform = 'translateX(' + (-100 * sira) + '%)';
    noktalar.forEach(function (d, n) { d.classList.toggle('active', n === sira); });
  }

  // Fare hero'nun üstündeyken duraklatma, sekme görünürlüğünden bağımsız
  // tutuluyor: aksi hâlde başka sekmeden dönünce imleç hâlâ hero'nun üstünde
  // olsa bile slayt kendiliğinden dönmeye başlıyordu.
  let beklet = false;

  function basla() {
    dur();
    // Hareketi azaltma tercihi açıksa kendiliğinden geçiş yapmıyoruz;
    // kullanıcı noktalara basarak yine gezinebilir.
    // document.hidden'a bakmıyoruz: sayfa arka plandaki bir sekmede açıldığında
    // visibilitychange hiç tetiklenmezse slayt kalıcı olarak durmuş kalırdı.
    // Arka plandaki sekmelerde tarayıcı zamanlayıcıları kendisi yavaşlatıyor.
    if (azHareket.matches || beklet) return;
    zaman = setInterval(function () { goster(sira + 1); }, sure);
  }
  function dur() { if (zaman) { clearInterval(zaman); zaman = null; } }

  noktalar.forEach(function (d) {
    d.addEventListener('click', function () {
      goster(parseInt(d.dataset.go, 10) || 0);
      basla(); // elle geçişten sonra sayacı sıfırla
    });
  });

  // Sekme arka plandayken boşa dönmesin
  document.addEventListener('visibilitychange', function () {
    document.hidden ? dur() : basla();
  });
  // Fareyle üstüne gelince beklesin — okumak isteyen okuyabilsin
  const heroEl = kutu.closest('.hero');
  heroEl.addEventListener('mouseenter', function () { beklet = true; dur(); });
  heroEl.addEventListener('mouseleave', function () { beklet = false; basla(); });

  goster(0);
  basla();
})();

// ---- Ürünler sayfası: filtre paneli telefonda kapalı başlasın ----
// <details> etiketinin "open" durumunu CSS ile yönetmek mümkün değil; küçük
// ekranda panel açık kalırsa ürün listesi ekranın çok altına düşüyor.
// JS çalışmazsa panel açık kalır — içerik yine erişilebilir.
(function () {
  const panel = document.getElementById('filtrePaneli');
  if (!panel) return;
  const uygula = () => {
    if (window.innerWidth <= 900) panel.removeAttribute('open');
    else panel.setAttribute('open', '');
  };
  uygula();
  let zaman;
  window.addEventListener('resize', function () {
    clearTimeout(zaman);
    zaman = setTimeout(uygula, 150);
  });
})();

/* =========================================================
   KAYDIRMA PERFORMANSI + AKICI KAYDIRMA (Lenis 1.3.26)
   ---------------------------------------------------------
   1) AĞIR KATMANLAR: blur/mask'lı dekoratif katmanlar kendi katmanına
      alınır; kaydırmada her karede yeniden çizilmezler.
   2) GÖRSEL ÇÖZME: tüm görseller arka planda çözülür (decoding=async),
      ekranın çok altındakiler tembel yüklenir.
   3) HAFİF MOD: yalnızca ölçülen kare hızı düşükse devreye girer;
      pahalı efektleri kapatır. Konsoldan: bmHafifMod(true/false)
   4) LENIS: akıcı kaydırma; yerel dosyadan yüklenir, yoksa CDN'den.
   ========================================================= */
(function () {
  const kok = document.documentElement;

  function stil(metin) {
    const s = document.createElement('style');
    s.textContent = metin;
    document.head.appendChild(s);
  }

  /* ---------------------------------------------------------
     1) AĞIR KATMANLAR (normal modda)
     --------------------------------------------------------- */
  stil(
    'html:not(.bm-hafif) .hero::before,' +
    'html:not(.bm-hafif) .hero::after,' +
    'html:not(.bm-hafif) .page-hero::after,' +
    'html:not(.bm-hafif) .about-visual::after,' +
    'html:not(.bm-hafif) .order-section::before,' +
    'html:not(.bm-hafif) .home-cta::before{' +
      'will-change:transform;transform:translateZ(0);}' +
    'html:not(.bm-hafif) .service-card .card-bg{' +
      'will-change:transform;backface-visibility:hidden;}' +
    'html:not(.bm-hafif) header{will-change:transform;transform:translateZ(0);}' +
    'html:not(.bm-hafif) .catalog-side{will-change:transform;}' +
    /* Kart gölgelerinin yeniden çizimi kartın içinde kalsın */
    '.service-card,.part-card,.blog-card{contain:layout paint style;}' +
    /* Dokunmatikte en pahalı iki efekt gereksiz */
    '@media (hover:none){' +
      '.hero .tag-pill{backdrop-filter:none;-webkit-backdrop-filter:none;' +
        'background:rgba(255,255,255,.18);}' +
      '.hero .tag-pill .dot{animation:none;}' +
    '}'
  );

  /* ---------------------------------------------------------
     2) HAFİF MOD KURALLARI
     Görünüm neredeyse aynı kalır: gradyanlar, renkler, düzen yerinde;
     yalnızca dekoratif bulanıklıklar ve ızgara deseni çizilmez.
     --------------------------------------------------------- */
  stil(
    'html.bm-hafif .hero::before,' +
    'html.bm-hafif .hero::after,' +
    'html.bm-hafif .page-hero::after,' +
    'html.bm-hafif .about-visual::after,' +
    'html.bm-hafif .order-section::before,' +
    'html.bm-hafif .home-cta::before{display:none !important;}' +
    'html.bm-hafif .hero .tag-pill{backdrop-filter:none;-webkit-backdrop-filter:none;' +
      'background:rgba(255,255,255,.18);}' +
    'html.bm-hafif .hero .tag-pill .dot{animation:none;}' +
    /* blur kalkar, panelden gelen karartma korunur (varsayılan %65) */
    'html.bm-hafif .service-card .card-bg{filter:brightness(65%) !important;}' +
    'html.bm-hafif .about-visual span{filter:none;}' +
    'html.bm-hafif .reveal{transition-duration:.35s;}' +
    'html.bm-hafif .service-card .card-bg,' +
    'html.bm-hafif header,' +
    'html.bm-hafif .catalog-side{will-change:auto !important;}'
  );

  function hafifAc(hatirla) {
    kok.classList.add('bm-hafif');
    if (hatirla) { try { localStorage.setItem('bmHafif', '1'); } catch (e) {} }
  }
  function hafifKapat() {
    kok.classList.remove('bm-hafif');
    try { localStorage.removeItem('bmHafif'); } catch (e) {}
  }
  // Elle kontrol (yalnızca F12 konsolundan): bmHafifMod(true) / bmHafifMod(false)
  window.bmHafifMod = function (ac) {
    if (ac === false) { hafifKapat(); } else { hafifAc(true); }
    return kok.classList.contains('bm-hafif');
  };

  // Hafif mod yalnızca ÖLÇÜLEN kare hızına göre açılır (aşağıdaki nöbetçi).
  // Cihaz özelliğine (çekirdek sayısı / RAM) bakıp açma kaldırıldı: 4 çekirdek
  // bugün çok yaygın ve o cihazların çoğu efektleri sorunsuz çeviriyor.
  // Bir tarayıcıda açılmışsa hatırlanır; kapatmak için: bmHafifMod(false)
  try { if (localStorage.getItem('bmHafif') === '1') hafifAc(false); } catch (e) {}

  /* ---------------------------------------------------------
     3) GÖRSEL ÇÖZME (decode)
     Görseller ilk kez çözülürken iş ana iş parçacığını meşgul eder ve
     kaydırma o sırada durur. decoding="async" bunu arka plana alır.
     --------------------------------------------------------- */
  function gorseller() {
    const hepsi = document.querySelectorAll('img:not([data-bm-dec])');
    for (let i = 0; i < hepsi.length; i++) {
      const g = hepsi[i];
      g.setAttribute('data-bm-dec', '');
      if (!g.getAttribute('decoding')) g.setAttribute('decoding', 'async');
      // Zaten loading değeri olanlara dokunulmaz (hero görseli gibi öncelikli
      // olanlar bozulmasın); yalnızca çok aşağıda kalanlar tembelleşir.
      if (!g.getAttribute('loading')) {
        const k = g.getBoundingClientRect();
        if (k.top > window.innerHeight * 1.2) g.setAttribute('loading', 'lazy');
      }
    }
  }
  gorseller();
  window.addEventListener('load', gorseller);

  /* ---------------------------------------------------------
     4) LENIS
     --------------------------------------------------------- */
  const KAYNAKLAR = [
    '/js/lib/lenis.min.js',
    'https://cdn.jsdelivr.net/npm/lenis@1.3.26/dist/lenis.min.js'
  ];

  if (window.self !== window.top) return;  // panel önizlemesi (iframe)
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (!('requestAnimationFrame' in window) || !('ResizeObserver' in window)) return;

  function yukle(i) {
    if (i >= KAYNAKLAR.length) return;
    const s = document.createElement('script');
    s.src = KAYNAKLAR[i];
    s.async = true;
    s.onload = function () { window.Lenis ? basla() : yukle(i + 1); };
    s.onerror = function () { yukle(i + 1); };
    document.head.appendChild(s);
  }

  function lenisStil() {
    stil(
      'html.lenis,html.lenis body{height:auto;}' +
      '.lenis.lenis-smooth{scroll-behavior:auto !important;}' +
      '.lenis.lenis-smooth [data-lenis-prevent]{overscroll-behavior:contain;}' +
      '.lenis.lenis-stopped{overflow:hidden;}' +
      '.lenis.lenis-scrolling iframe{pointer-events:none;}'
    );
  }

  // İçinde kendi kaydırma çubuğu olan alanlar korunur
  function koruma() {
    const liste = document.querySelectorAll(
      '.ulke-panel, .nav-links, .dropdown, .preview-frame-wrap, [data-kaydir-koru]'
    );
    for (let i = 0; i < liste.length; i++) liste[i].setAttribute('data-lenis-prevent', '');
  }

  function basla() {
    lenisStil();
    koruma();

    const lenis = new window.Lenis({
      lerp: 0.1,                 // çerçeve tabanlı yumuşatma
      smoothWheel: true,
      syncTouch: false,          // telefon/tablet kendi ivmesini korur
      gestureOrientation: 'vertical',
      wheelMultiplier: 1,
      touchMultiplier: 1.6,
      autoRaf: false,
      anchors: false
    });
    window.bmLenis = lenis;

    /* ---- KADEMELİ GERİ ÇEKİLME ----
       Kaydırmada kareler sürekli 34ms'yi (≈30 FPS) aşıyorsa:
         1. aşama → hafif mod açılır (pahalı efektler kapanır)
         2. aşama → akıcı kaydırma kapanır (tarayıcının normal kaydırması)
       En kötü durumda eski davranış; kalıcı kasma olmaz. */
    let onceki = 0, yavas = 0, asama = 0;

    function kapat() {
      if (asama >= 2) return;
      asama = 2;
      try { lenis.destroy(); } catch (e) {}
      kok.classList.remove('lenis', 'lenis-smooth', 'lenis-scrolling', 'lenis-stopped');
      window.bmLenis = null;
      if (window.console && console.info) {
        console.info('[Beyli] Akıcı kaydırma kapatıldı (2. aşama): kare hızı yetersiz.');
      }
    }
    window.bmKaydirmaKapat = kapat;

    function tik(zaman) {
      lenis.raf(zaman);
      if (asama >= 2) return;
      if (onceki && lenis.isScrolling) {
        const gecen = zaman - onceki;
        if (gecen > 34) {
          yavas++;
          if (yavas > 40) {
            yavas = 0;
            if (asama === 0) {
              asama = 1;
              hafifAc(true);
              if (window.console && console.info) {
                console.info('[Beyli] Hafif mod açıldı (1. aşama): ağır görsel efektler kapatıldı.');
              }
            } else {
              kapat();
              return;
            }
          }
        } else if (yavas > 0) {
          yavas--;
        }
      }
      onceki = zaman;
      requestAnimationFrame(tik);
    }
    requestAnimationFrame(tik);

    // Sayfa içi çapa bağlantıları
    document.addEventListener('click', function (e) {
      const a = e.target.closest('a[href^="#"]');
      if (!a) return;
      const href = a.getAttribute('href');
      if (!href || href === '#' || href.length < 2) return;
      let hedef = null;
      try { hedef = document.querySelector(href); } catch (hata) { return; }
      if (!hedef) return;
      e.preventDefault();
      lenis.scrollTo(hedef, { offset: -90 });
      if (history.pushState) history.pushState(null, '', href);
    });

    // Mobil menü açıkken arkadaki sayfa kaymasın
    const govde = document.body;
    function menuDurumu() {
      govde.classList.contains('nav-open') ? lenis.stop() : lenis.start();
    }
    new MutationObserver(menuDurumu).observe(govde, { attributes: true, attributeFilter: ['class'] });
    menuDurumu();

    window.addEventListener('load', koruma);
    document.addEventListener('focusin', koruma);

    // #bölüm adresiyle açıldıysa doğru yere otur
    if (window.location.hash && window.location.hash.length > 1) {
      let ilk = null;
      try { ilk = document.querySelector(window.location.hash); } catch (e) { ilk = null; }
      if (ilk) setTimeout(function () { lenis.scrollTo(ilk, { offset: -90, immediate: true }); }, 60);
    }

    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && window.bmLenis) lenis.resize();
    });
  }

  yukle(0);
})();
