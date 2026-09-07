// Run with: node tests/push-browser.cjs (requires local Playwright).
const { chromium } = require('playwright');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');

(async () => {
  const port = await new Promise(resolve => {
    const probe = net.createServer().listen(0, '127.0.0.1', () => {
      const port = probe.address().port;
      probe.close(() => resolve(port));
    });
  });
  const origin = 'http://127.0.0.1:' + port;
  const server = spawn('php', ['-S', '127.0.0.1:' + port, '-t', 'public'], { windowsHide: true, stdio: 'ignore' });
  const browser = await chromium.launch({ headless: true });
  try {
    for (let i = 0; i < 50; i++) {
      try { if ((await fetch(origin + '/dashboard.html')).ok) break; } catch {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    for (const width of [375, 1280]) {
      const context = await browser.newContext({ viewport: { width, height: 850 }, serviceWorkers: 'block' });
      await context.addInitScript(() => {
        localStorage.setItem('token', 'test-browser-token');
        localStorage.setItem('user', JSON.stringify({ id: 123, name: 'Cliente' }));
        window.__posts = 0;
        let subscribed = false;
        const subscription = {
          endpoint: 'https://fcm.googleapis.com/fcm/send/browser-test',
          toJSON: () => ({ endpoint: subscription.endpoint, keys: { p256dh: 'test', auth: 'test' } }),
          unsubscribe: async () => { subscribed = false; return true; },
        };
        Object.defineProperty(window, 'Notification', { configurable: true, value: {
          permission: 'default', requestPermission: async () => { Notification.permission = 'granted'; return 'granted'; },
        } });
        window.PushManager = function () {};
        const reg = { update: async () => {}, pushManager: {
          getSubscription: async () => subscribed ? subscription : null,
          subscribe: async () => { subscribed = true; return subscription; },
        } };
        const sw = new EventTarget();
        sw.controller = {};
        sw.register = async () => reg;
        sw.getRegistration = async () => reg;
        sw.ready = Promise.resolve(reg);
        Object.defineProperty(navigator, 'serviceWorker', { value: sw, configurable: true });
      });
      let posts = 0, deletes = 0;
      await context.route('**/api/**', async route => {
        const url = route.request().url();
        let data = { status: true, staff: [], services: [], bookings: [], products: [], favorites: [], slots: [] };
        if (url.endsWith('/push/config')) data = { enabled: true, public_key: 'AQID' };
        if (url.endsWith('/push/subscriptions')) {
          if (route.request().method() === 'POST') posts++;
          if (route.request().method() === 'DELETE') deletes++;
        }
        await route.fulfill({ json: data });
      });
      const page = await context.newPage();
      await page.goto(origin + '/dashboard.html');
      await page.locator('dialog[open]').waitFor();
      assert.equal(await page.locator('#favoritesToggle + #pushToggle').count(), 1);
      await page.screenshot({ path: path.join(os.tmpdir(), 'gc-push-' + width + '.png'), fullPage: true });
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      await page.getByRole('button', { name: 'Non ora', exact: true }).click();
      await page.reload();
      await page.locator('#pushToggle').waitFor();
      assert.equal(await page.locator('dialog[open]').count(), 0);
      await page.locator('#pushToggle').click();
      await page.locator('.gc-push-primary').click();
      await page.waitForFunction(() => document.querySelector('#pushToggle').dataset.enabled === 'true');
      assert.equal(posts, 1);
      await page.locator('#pushToggle').click();
      await page.locator('.gc-push-primary').click();
      await page.waitForFunction(() => document.querySelector('#pushToggle').dataset.enabled === 'false');
      assert.equal(deletes, 1);
      await page.evaluate(() => navigator.serviceWorker.dispatchEvent(new Event('controllerchange')));
      await page.locator('.gc-update').waitFor();
      for (const section of ['my-bookings', 'products']) {
        await page.goto(origin + '/' + section + '.html');
        await page.locator('#favoritesToggle + #pushToggle').waitFor();
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
      }
      await context.close();
    }
    // Real service worker: private API requests must never enter its cache.
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(origin + '/index.html');
    await page.evaluate(async () => { await navigator.serviceWorker.ready; });
    await page.reload();
    await page.evaluate(async () => {
      await fetch('/api/push/config', { headers: { Authorization: 'Bearer test-only' } });
    });
    const cached = await page.evaluate(async () => {
      const cache = await caches.open('giovannicerino-push-v2');
      return (await cache.keys()).map(request => request.url);
    });
    assert(cached.some(url => url.endsWith('/dashboard.html')));
    assert(cached.some(url => url.endsWith('/js/push.js?v=2')));
    assert(cached.some(url => url.endsWith('/css/push.css?v=2')));
    assert(!cached.some(url => url.includes('/api/')));
    await context.close();
    console.log('PASS: mobile/desktop popup, dismissal, subscribe/unsubscribe, bell, update notice, real SW cache.');
    console.log('Screenshots: ' + path.join(os.tmpdir(), 'gc-push-{375,1280}.png'));
  } finally {
    await browser.close();
    server.kill();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
