/* Quill zengin metin editörlerinde resim yükleme butonunu
   /teknikel/upload-handler.php endpoint'ine bağlayan ortak yardımcı. */
function bmAttachImageHandler(quill) {
  quill.getModule('toolbar').addHandler('image', function () {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');
    input.click();
    input.onchange = function () {
      const file = input.files[0];
      if (!file) return;
      fetch('/teknikel/upload-handler.php?raw=1&csrf=' + encodeURIComponent(window.BM_CSRF || ''), {
        method: 'POST',
        headers: { 'Content-Type': file.type || 'application/octet-stream' },
        body: file
      })
        .then(res => res.json())
        .then(data => {
          if (data.ok) {
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', data.url);
            quill.setSelection(range.index + 1);
          } else {
            alert('Görsel yüklenemedi: ' + (data.error || 'bilinmeyen hata'));
          }
        })
        .catch(() => alert('Görsel yüklenirken bir hata oluştu.'));
    };
  });
}
