(() => {
  const cfg = window.MM_CARD || {};
  const send = event => {
    if (typeof window.gtag === 'function') window.gtag('event', event, {card_owner: cfg.owner});
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({event, card_owner: cfg.owner});
  };
  const toast = message => {
    const element = document.querySelector('#toast');
    if (!element) return;
    element.textContent = message;
    element.classList.add('show');
    setTimeout(() => element.classList.remove('show'), 2200);
  };

  send('digital_card_view');
  document.querySelectorAll('.track').forEach(element => element.addEventListener('click', () => send(element.dataset.event)));

  document.querySelector('#share')?.addEventListener('click', async () => {
    const shareUrl = cfg.url + (cfg.url.includes('?') ? '&' : '?') + 'share=1';
    try {
      if (navigator.share) await navigator.share({title: cfg.name, text: 'Digitale Visitenkarte von ' + cfg.name, url: shareUrl});
      else {
        await navigator.clipboard.writeText(shareUrl);
        toast('Link kopiert');
      }
    } catch (error) {
      if (error.name !== 'AbortError') toast('Teilen derzeit nicht möglich');
    }
  });

  document.querySelector('#share-whatsapp')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    const shareUrl = cfg.url + (cfg.url.includes('?') ? '&' : '?') + 'share=1';
    const text = encodeURIComponent('Digitale Visitenkarte von ' + cfg.name + '\n' + shareUrl);
    if (button.dataset.app === 'business' && navigator.share) {
      try { await navigator.share({title: cfg.name, text: 'Digitale Visitenkarte von ' + cfg.name, url: shareUrl}); } catch (error) {}
      return;
    }
    window.location.href = 'https://wa.me/?text=' + text;
  });

  let installPrompt = null;
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    installPrompt = event;
  });
  const help = document.querySelector('#install-help');
  document.querySelector('#install')?.addEventListener('click', async () => {
    if (installPrompt) {
      installPrompt.prompt();
      const result = await installPrompt.userChoice;
      if (result.outcome === 'accepted') {
        send('digital_card_homescreen_install');
        installPrompt = null;
      }
      return;
    }
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    help.querySelector('.ios').style.display = ios ? 'block' : 'none';
    help.querySelector('.android').style.display = ios ? 'none' : 'block';
    help.hidden = false;
  });
  help?.querySelector('.close')?.addEventListener('click', () => help.hidden = true);
  if ('serviceWorker' in navigator && cfg.sw) {
    window.addEventListener('load', () => navigator.serviceWorker.register(cfg.sw, {scope: new URL(cfg.url).pathname}).catch(() => {}));
  }
})();
