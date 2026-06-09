// Brothers Bets — install-only service worker.
// Its ONLY job is to make the app installable (a registered, fetch-capable SW).
// It deliberately caches NOTHING: no precache, no offline page, no
// stale-while-revalidate. The network is the single source of truth, so there
// is zero risk of serving stale predictions, leaderboards, or live scores.

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// A fetch handler must exist for installability; keep it a pure pass-through
// (no event.respondWith → the browser does its normal network fetch).
self.addEventListener('fetch', () => {});
