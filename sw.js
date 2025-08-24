// Service Worker for Daily Calendar PWA
// Version 1.0.0

const CACHE_NAME = 'daily-calendar-v1.0.0';
const STATIC_CACHE = 'static-v1';
const DYNAMIC_CACHE = 'dynamic-v1';

// Files to cache for offline functionality
const STATIC_FILES = [
  '/',
  '/index.php',
  '/admin-dashboard.php',
  '/task.php',
  '/assets/js/app.js',
  '/assets/js/global-task-manager.js',
  '/assets/css/style.css',
  'https://cdn.tailwindcss.com/3.3.0',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  '/manifest.json',
  '/assets/icons/icon-192x192.png',
  '/assets/icons/icon-512x512.png'
];

// Install event - cache static files
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('Service Worker: Caching static files');
        return cache.addAll(STATIC_FILES.map(url => new Request(url, { credentials: 'same-origin' })));
      })
      .catch(err => console.log('Cache error:', err))
  );
  
  // Force the waiting service worker to become the active service worker
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
              console.log('Service Worker: Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Activated');
        return self.clients.claim();
      })
  );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests and chrome-extension requests
  if (request.method !== 'GET' || url.protocol === 'chrome-extension:') {
    return;
  }
  
  event.respondWith(
    caches.match(request)
      .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }
        
        return fetch(request)
          .then(response => {
            // Don't cache non-successful responses
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            
            const responseToCache = response.clone();
            
            caches.open(DYNAMIC_CACHE)
              .then(cache => {
                cache.put(request, responseToCache);
              });
            
            return response;
          })
          .catch(() => {
            // Return offline page for navigation requests
            if (request.destination === 'document') {
              return caches.match('/offline.html');
            }
          });
      })
  );
});

// Push event - handle push notifications
self.addEventListener('push', event => {
  console.log('Service Worker: Push event received');
  
  let notificationData = {
    title: 'Daily Calendar Notification',
    body: 'You have a new update',
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/badge-72x72.png',
    tag: 'task-notification',
    requireInteraction: true,
    renotify: true,
    silent: false,
    data: {
      url: '/',
      timestamp: Date.now()
    },
    actions: [
      {
        action: 'open',
        title: 'Open App',
        icon: '/assets/icons/open-24x24.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/assets/icons/dismiss-24x24.png'
      }
    ]
  };
  
  // Parse push data if available
  if (event.data) {
    try {
      const pushData = event.data.json();
      notificationData = {
        ...notificationData,
        ...pushData,
        data: {
          ...notificationData.data,
          ...pushData.data
        }
      };
    } catch (e) {
      console.log('Error parsing push data:', e);
      notificationData.body = event.data.text() || notificationData.body;
    }
  }
  
  event.waitUntil(
    self.registration.showNotification(notificationData.title, notificationData)
  );
});

// Notification click event
self.addEventListener('notificationclick', event => {
  console.log('Service Worker: Notification click received');
  
  event.notification.close();
  
  const notificationData = event.notification.data || {};
  const action = event.action;
  
  if (action === 'dismiss') {
    return;
  }
  
  // Determine URL to open
  let urlToOpen = notificationData.url || '/';
  
  if (notificationData.task_id) {
    urlToOpen = `/task.php?id=${notificationData.task_id}`;
  } else if (action === 'open' || !action) {
    urlToOpen = notificationData.url || '/';
  }
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        // Check if app is already open
        for (let client of clientList) {
          if (client.url.includes(self.location.origin) && 'focus' in client) {
            client.postMessage({
              type: 'NOTIFICATION_CLICKED',
              data: notificationData,
              action: action
            });
            return client.focus();
          }
        }
        
        // Open new window if app is not open
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
  );
});

// Background sync event (for when connection is restored)
self.addEventListener('sync', event => {
  console.log('Service Worker: Background sync triggered');
  
  if (event.tag === 'task-sync') {
    event.waitUntil(syncTasks());
  }
});

// Sync tasks when connection is restored
async function syncTasks() {
  try {
    const response = await fetch('/api/sync-tasks.php', {
      method: 'POST',
      credentials: 'same-origin'
    });
    
    if (response.ok) {
      const result = await response.json();
      console.log('Tasks synced:', result);
      
      // Notify all clients about the sync
      const clients = await self.clients.matchAll();
      clients.forEach(client => {
        client.postMessage({
          type: 'TASKS_SYNCED',
          data: result
        });
      });
    }
  } catch (error) {
    console.log('Sync failed:', error);
  }
}

// Handle messages from the main thread
self.addEventListener('message', event => {
  console.log('Service Worker: Message received', event.data);
  
  const { type, data } = event.data;
  
  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;
      
    case 'GET_VERSION':
      event.ports[0].postMessage({ version: CACHE_NAME });
      break;
      
    case 'CACHE_URLS':
      if (data && data.urls) {
        event.waitUntil(
          caches.open(DYNAMIC_CACHE)
            .then(cache => cache.addAll(data.urls))
        );
      }
      break;
      
    case 'CLEAR_CACHE':
      event.waitUntil(
        caches.keys().then(cacheNames => {
          return Promise.all(
            cacheNames.map(cacheName => caches.delete(cacheName))
          );
        })
      );
      break;
  }
});

// Periodic background sync (if supported)
self.addEventListener('periodicsync', event => {
  if (event.tag === 'task-update') {
    event.waitUntil(syncTasks());
  }
});

// Handle subscription changes
self.addEventListener('pushsubscriptionchange', event => {
  console.log('Service Worker: Push subscription changed');
  
  event.waitUntil(
    fetch('/api/update-subscription.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        oldEndpoint: event.oldSubscription ? event.oldSubscription.endpoint : null,
        newEndpoint: event.newSubscription ? event.newSubscription.endpoint : null,
        newKeys: event.newSubscription ? {
          p256dh: event.newSubscription.keys.p256dh,
          auth: event.newSubscription.keys.auth
        } : null
      }),
      credentials: 'same-origin'
    })
  );
});