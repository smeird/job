(function () {
  function renderQrCode() {
    var qrContainer = document.getElementById('qr-code');
    if (!qrContainer) {
      return;
    }

    var code = qrContainer.getAttribute('data-qr-code') || '';
    if (code.trim() === '') {
      qrContainer.textContent = 'QR code unavailable';
      qrContainer.classList.add('bg-slate-800', 'text-slate-300', 'text-xs');
      return;
    }

    if (typeof QRCode === 'undefined') {
      qrContainer.textContent = 'QR code unavailable';
      qrContainer.classList.add('bg-slate-800', 'text-slate-300', 'text-xs');
      return;
    }

    qrContainer.innerHTML = '';
    try {
      new QRCode(qrContainer, {
        text: code,
        width: 192,
        height: 192,
        colorDark: '#0f172a',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M,
      });
    } catch (error) {
      console.error('Unable to render QR code', error);
      qrContainer.textContent = 'QR code unavailable';
      qrContainer.classList.add('bg-slate-800', 'text-slate-300', 'text-xs');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderQrCode);
  } else {
    renderQrCode();
  }
})();
