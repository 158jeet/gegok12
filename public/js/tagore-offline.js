(() => {
  const DB_NAME = 'tagorek12-offline';
  const DB_VERSION = 1;
  const EVENT_STORE = 'outbox';
  const DATA_STORE = 'bootstrap';
  const DEVICE_KEY = 'tagorek12-device-id';

  const deviceId = localStorage.getItem(DEVICE_KEY) || (() => {
    const id = crypto.randomUUID();
    localStorage.setItem(DEVICE_KEY, id);
    return id;
  })();

  const openDb = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains(EVENT_STORE)) db.createObjectStore(EVENT_STORE, { keyPath: 'event_uuid' });
      if (!db.objectStoreNames.contains(DATA_STORE)) db.createObjectStore(DATA_STORE);
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });

  const tx = async (store, mode, fn) => {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const t = db.transaction(store, mode);
      const s = t.objectStore(store);
      const result = fn(s);
      t.oncomplete = () => resolve(result);
      t.onerror = () => reject(t.error);
    });
  };

  async function queue(entity_type, operation, payload) {
    const event = {
      event_uuid: crypto.randomUUID(),
      device_id: deviceId,
      entity_type,
      operation,
      payload,
      occurred_at: new Date().toISOString()
    };
    await tx(EVENT_STORE, 'readwrite', s => s.put(event));
    await sync();
    return event.event_uuid;
  }

  async function pending() {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const request = db.transaction(EVENT_STORE).objectStore(EVENT_STORE).getAll();
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async function sync() {
    if (!navigator.onLine) return;
    const events = await pending();
    if (!events.length) return;
    try {
      const response = await fetch('/api/v2/tagore/sync', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({events})
      });
      if (!response.ok) return;
      const result = await response.json();
      const ok = new Set((result.accepted || []).map(x => x.event_uuid));
      for (const id of ok) await tx(EVENT_STORE, 'readwrite', s => s.delete(id));
      window.dispatchEvent(new CustomEvent('tagore-sync', {detail: result}));
    } catch (_) {}
  }

  async function cacheBootstrap(data) {
    await tx(DATA_STORE, 'readwrite', s => s.put(data, 'current'));
  }

  async function getBootstrap() {
    return tx(DATA_STORE, 'readonly', s => {
      const r = s.get('current');
      return new Promise(resolve => { r.onsuccess = () => resolve(r.result); });
    });
  }

  async function refreshBootstrap() {
    if (!navigator.onLine) return getBootstrap();
    try {
      const response = await fetch('/api/v2/tagore/offline/bootstrap', {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
      if (!response.ok) return getBootstrap();
      const data = await response.json();
      await cacheBootstrap(data);
      return data;
    } catch (_) {
      return getBootstrap();
    }
  }

  async function register() {
    if (!navigator.onLine) return;
    fetch('/api/v2/tagore/offline/device', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
      body: JSON.stringify({device_id:deviceId, platform:'web-pwa', app_version:'1.0.0'})
    }).catch(() => {});
  }

  window.TagoreOffline = {deviceId, queue, sync, pending, refreshBootstrap, getBootstrap};

  const update = async () => {
    const count = (await pending()).length;
    document.querySelectorAll('[data-tagore-sync-count]').forEach(el => el.textContent = count);
    document.querySelectorAll('[data-tagore-connection]').forEach(el => {
      el.textContent = navigator.onLine ? (count ? 'Online • syncing' : 'Online • synced') : 'Offline • saved locally';
      el.dataset.state = navigator.onLine ? 'online' : 'offline';
    });
  };

  window.addEventListener('online', () => { register(); sync().finally(update); });
  window.addEventListener('offline', update);
  window.addEventListener('tagore-sync', update);
  document.addEventListener('DOMContentLoaded', async () => {
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/service-worker.js').catch(() => {});
    register();
    refreshBootstrap().finally(update);
    setInterval(() => sync().finally(update), 15000);
    update();
  });
})();
