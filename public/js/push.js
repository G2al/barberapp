(() => {
  'use strict';
  let registration, config, dialog, toggle, busy = false, userId, enabled = false;
  const read = key => { try { return localStorage.getItem(key); } catch { return null; } };
  const write = (key, value) => { try { localStorage.setItem(key, value); } catch { /* Private mode. */ } };
  const seenKey = () => 'gc-push-intro-v1:' + userId;
  const supported = () => window.isSecureContext && 'PushManager' in window && 'Notification' in window;
  const iosBrowser = () => (/iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) &&
    !(navigator.standalone || matchMedia('(display-mode: standalone)').matches);

  async function api(path, method = 'GET', body) {
    const token = read('token');
    if (!token) throw new Error('Accedi nuovamente per gestire le notifiche.');
    const response = await fetch('/api/push/' + path, {
      method, cache: 'no-store', signal: AbortSignal.timeout(10000),
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
      ...(body ? { body: JSON.stringify(body) } : {}),
    });
    if (!response.ok) throw new Error('Non riesco ad aggiornare le notifiche. Controlla la connessione e riprova.');
    return response.json();
  }
  function updateBell() {
    toggle.dataset.enabled = String(enabled);
    toggle.title = enabled ? 'Notifiche attive' : 'Attiva notifiche';
    toggle.setAttribute('aria-label', toggle.title);
    toggle.firstElementChild.className = enabled ? 'bi bi-bell-fill' : 'bi bi-bell';
  }
  function message(text) { dialog.querySelector('p').textContent = text; }
  function openDialog() {
    const button = dialog.querySelector('.gc-push-primary');
    button.hidden = false;
    button.textContent = enabled ? 'Disattiva su questo dispositivo' : 'Attiva notifiche';
    dialog.querySelector('.gc-push-later').textContent = enabled ? 'Chiudi' : 'Non ora';
    message(enabled
      ? 'Ricevi conferme, annullamenti e promemoria dei tuoi appuntamenti su questo dispositivo.'
      : 'Sono arrivate le notifiche! Attivale per ricevere conferme, annullamenti e promemoria dei tuoi appuntamenti.');
    if (!config?.enabled) {
      message('Le notifiche non sono al momento disponibili. Riprova piu tardi.');
      button.hidden = true;
    } else if (iosBrowser()) {
      message('Per attivare le notifiche su iPhone, apri questa app dalla schermata Home. Se non e presente, aggiungila da Condividi in Safari.');
      button.hidden = true;
    } else if (!supported()) {
      message('Questo browser non supporta le notifiche. Prova da un dispositivo o browser aggiornato.');
      button.hidden = true;
    } else if (Notification.permission === 'denied') {
      message('Le notifiche sono bloccate. Abilitale nelle impostazioni del dispositivo o del browser, poi riapri questa app.');
      button.hidden = true;
    }
    if (!dialog.open) dialog.showModal();
  }
  function publicKey(value) {
    const raw = atob(value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4));
    return Uint8Array.from(raw, char => char.charCodeAt(0));
  }
  async function changeSubscription() {
    if (busy) return;
    busy = true;
    const buttons = dialog.querySelectorAll('button');
    buttons.forEach(button => button.disabled = true);
    try {
      if (enabled) {
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
          await api('subscriptions', 'DELETE', { endpoint: subscription.endpoint });
          await subscription.unsubscribe();
        }
        enabled = false;
        write('gc-push-owner', '');
        write(seenKey(), '1');
      } else {
        // Keep the native permission request inside the user's click gesture on iOS.
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
          write(seenKey(), '1');
          message('Notifiche non attivate. Puoi gestirle dalla campanella e dalle impostazioni del dispositivo.');
          return;
        }
        const subscription = await registration.pushManager.getSubscription() ||
          await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: publicKey(config.public_key) });
        await api('subscriptions', 'POST', subscription.toJSON());
        enabled = true;
        write('gc-push-owner', String(userId));
        write(seenKey(), '1');
      }
      updateBell();
      dialog.close();
    } catch {
      message('Non e stato possibile aggiornare le notifiche. Controlla connessione e permessi, poi riprova.');
    } finally {
      busy = false;
      buttons.forEach(button => button.disabled = false);
    }
  }
  async function init() {
    if (!('serviceWorker' in navigator)) return;
    const css = document.createElement('link');
    css.rel = 'stylesheet'; css.href = '/css/push.css?v=2'; document.head.append(css);
    try {
      registration = await navigator.serviceWorker.register('/service-worker.js', { updateViaCache: 'none' });
      registration.update().catch(() => {});
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') registration.update().catch(() => {});
      });
      window.addEventListener('online', () => registration.update().catch(() => {}));
      await navigator.serviceWorker.ready;
    } catch { return; }
    const favorite = document.getElementById('favoritesToggle');
    if (!favorite || !read('token')) return;
    try { userId = JSON.parse(read('user') || '{}').id; } catch { return; }
    if (!userId) return;
    toggle = document.createElement('button');
    toggle.type = 'button'; toggle.id = 'pushToggle'; toggle.className = 'gc-action-btn';
    toggle.innerHTML = '<i class="bi bi-bell" aria-hidden="true"></i>';
    favorite.after(toggle);
    dialog = document.createElement('dialog');
    dialog.className = 'gc-push-dialog'; dialog.setAttribute('aria-labelledby', 'gc-push-title');
    dialog.innerHTML = '<i class="bi bi-bell" aria-hidden="true"></i><h2 id="gc-push-title">Le tue notifiche</h2><p role="status"></p><div class="gc-push-buttons"><button type="button" class="gc-push-primary">Attiva notifiche</button><button type="button" class="gc-push-later">Non ora</button></div>';
    document.body.append(dialog);
    toggle.onclick = async () => {
      try { config = await api('config'); } catch { config = null; }
      openDialog();
    };
    dialog.querySelector('.gc-push-primary').onclick = changeSubscription;
    dialog.querySelector('.gc-push-later').onclick = () => { write(seenKey(), '1'); dialog.close(); };
    dialog.addEventListener('cancel', event => {
      if (busy) event.preventDefault(); else write(seenKey(), '1');
    });
    updateBell();
    try {
      config = await api('config');
      if (!config.enabled) return;
      if (supported()) {
        let subscription = await registration.pushManager.getSubscription();
        // Do not keep a subscription belonging to a previous account on a shared device.
        if (subscription && read('gc-push-owner') !== String(userId)) {
          await subscription.unsubscribe();
          subscription = null;
        }
        if (subscription && Notification.permission === 'granted') {
          await api('subscriptions', 'POST', subscription.toJSON());
          enabled = true; updateBell(); write(seenKey(), '1');
        }
      }
      if (!enabled && !read(seenKey())) openDialog();
    } catch { /* The bell lets the user retry without interrupting booking. */ }
  }
  window.GCDisablePushOnLogout = async () => {
    if (!('serviceWorker' in navigator)) return;
    const reg = await navigator.serviceWorker.getRegistration('/');
    const subscription = await reg?.pushManager?.getSubscription();
    if (subscription) {
      try { await api('subscriptions', 'DELETE', { endpoint: subscription.endpoint }); }
      finally { await subscription.unsubscribe(); }
    }
    write('gc-push-owner', '');
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
