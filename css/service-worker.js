// Service Worker for Zewed AI Career Assistant
// Version: 2.0.0
// Cache Strategy: Cache First, Network Fallback with Stale-While-Revalidate

const CACHE_NAME = 'zewed-ai-v2.0.0';
const DYNAMIC_CACHE_NAME = 'zewed-dynamic-v1.0.0';

// Assets to cache on install
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/ai-assistant.html',
  '/job-listings.html',
  '/resources.html',
  '/profile.html',
  '/login.html',
  '/signup.html',
  '/css/main.css',
  '/css/ai-chat.css',
  '/js/ai-integration.js',
  '/js/chat.js',
  '/manifest.json',
  '/assets/icons/icon-72x72.png',
  '/assets/icons/icon-192x192.png',
  '/assets/icons/icon-512x512.png',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        console.log('[Service Worker] Skip waiting');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('[Service Worker] Installation failed:', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== DYNAMIC_CACHE_NAME) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('[Service Worker] Claiming clients');
      return self.clients.claim();
    })
  );
});

// Fetch event - network first for API, cache first for assets
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests and browser extensions
  if (request.method !== 'GET' || url.protocol === 'chrome-extension:') {
    return;
  }

  // API requests - Network First
  if (url.pathname.startsWith('/api/') || url.pathname.includes('api.')) {
    event.respondWith(networkFirst(request));
    return;
  }

  // Static assets - Cache First
  if (isStaticAsset(request)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // HTML pages - Network First with Cache Fallback
  if (request.headers.get('Accept').includes('text/html')) {
    event.respondWith(networkFirst(request));
    return;
  }

  // Default: Network First with Cache Fallback
  event.respondWith(networkFirst(request));
});

// Cache First Strategy
async function cacheFirst(request) {
  const cachedResponse = await caches.match(request);
  
  if (cachedResponse) {
    // Update cache in background
    event.waitUntil(updateCache(request));
    return cachedResponse;
  }

  // If not in cache, try network
  try {
    const networkResponse = await fetch(request);
    
    // Cache the new response for future use
    if (networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    // Network failed - return offline page for HTML requests
    if (request.headers.get('Accept').includes('text/html')) {
      return caches.match('/offline.html');
    }
    
    // For other requests, return error
    return new Response('Network error occurred', {
      status: 408,
      headers: { 'Content-Type': 'text/plain' }
    });
  }
}

// Network First Strategy
async function networkFirst(request) {
  try {
    const networkResponse = await fetch(request);
    
    // Update cache with fresh response
    if (networkResponse.ok) {
      const cache = await caches.open(DYNAMIC_CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.log('[Service Worker] Network failed, serving from cache:', error);
    
    // Try cache
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Return offline page for HTML requests
    if (request.headers.get('Accept').includes('text/html')) {
      const offlinePage = await caches.match('/offline.html');
      if (offlinePage) return offlinePage;
    }
    
    // Return generic offline response
    return new Response(JSON.stringify({
      error: 'You are offline',
      message: 'Please check your internet connection',
      timestamp: new Date().toISOString()
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// Update cache in background (Stale-While-Revalidate)
async function updateCache(request) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
  } catch (error) {
    console.log('[Service Worker] Background update failed:', error);
  }
}

// Helper function to check if asset is static
function isStaticAsset(request) {
  const url = new URL(request.url);
  return (
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.woff') ||
    url.pathname.endsWith('.woff2') ||
    url.pathname.endsWith('.ttf')
  );
}

// Background Sync for chat messages
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-chat-messages') {
    console.log('[Service Worker] Background sync for chat messages');
    event.waitUntil(syncChatMessages());
  }
});

// Periodic background sync for content updates
self.addEventListener('periodicsync', (event) => {
  if (event.tag === 'update-career-resources') {
    console.log('[Service Worker] Periodic sync for career resources');
    event.waitUntil(updateCareerResources());
  }
});

// Sync chat messages when back online
async function syncChatMessages() {
  const db = await openChatDatabase();
  const unsyncedMessages = await getUnsyncedMessages(db);
  
  for (const message of unsyncedMessages) {
    try {
      await fetch('/api/chat/messages', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(message)
      });
      
      await markMessageAsSynced(db, message.id);
    } catch (error) {
      console.error('[Service Worker] Failed to sync message:', error);
    }
  }
}

// Update career resources in background
async function updateCareerResources() {
  try {
    const response = await fetch('/api/resources/latest');
    const resources = await response.json();
    
    const cache = await caches.open(DYNAMIC_CACHE_NAME);
    await cache.put('/api/resources/latest', new Response(JSON.stringify(resources)));
    
    // Send notification if new resources available
    if (resources.length > 0) {
      self.registration.showNotification('New Career Resources', {
        body: `${resources.length} new resources available`,
        icon: '/assets/icons/icon-192x192.png',
        tag: 'resources-update'
      });
    }
  } catch (error) {
    console.error('[Service Worker] Failed to update resources:', error);
  }
}

// Push notifications
self.addEventListener('push', (event) => {
  console.log('[Service Worker] Push received');
  
  let data = {
    title: 'New Message',
    body: 'You have a new message from Career Assistant',
    icon: '/assets/icons/icon-192x192.png',
    badge: '/assets/icons/badge-72x72.png'
  };
  
  if (event.data) {
    try {
      data = event.data.json();
    } catch (error) {
      console.log('[Service Worker] Push data parsing error:', error);
    }
  }
  
  const options = {
    body: data.body,
    icon: data.icon || '/assets/icons/icon-192x192.png',
    badge: data.badge || '/assets/icons/badge-72x72.png',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/',
      timestamp: Date.now()
    },
    actions: [
      {
        action: 'open',
        title: 'Open App'
      },
      {
        action: 'dismiss',
        title: 'Dismiss'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  console.log('[Service Worker] Notification click');
  
  event.notification.close();
  
  if (event.action === 'dismiss') {
    return;
  }
  
  const urlToOpen = event.notification.data.url || '/';
  
  event.waitUntil(
    clients.matchAll({
      type: 'window',
      includeUncontrolled: true
    }).then((clientList) => {
      // Check if there's already a window open
      for (const client of clientList) {
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      
      // Open new window if none exists
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

// Message handler for communication with main thread
self.addEventListener('message', (event) => {
  console.log('[Service Worker] Message received:', event.data);
  
  switch (event.data.type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;
      
    case 'GET_CACHE_INFO':
      event.ports[0].postMessage({
        type: 'CACHE_INFO',
        cacheNames: Array.from(caches.keys())
      });
      break;
      
    case 'CLEAR_CACHE':
      caches.delete(CACHE_NAME).then(() => {
        event.ports[0].postMessage({ type: 'CACHE_CLEARED' });
      });
      break;
      
    case 'UPDATE_ASSETS':
      updateAssets(event.data.urls);
      break;
  }
});

// Update specific assets in cache
async function updateAssets(urls) {
  const cache = await caches.open(CACHE_NAME);
  for (const url of urls) {
    try {
      const response = await fetch(url);
      if (response.ok) {
        await cache.put(url, response);
      }
    } catch (error) {
      console.error(`[Service Worker] Failed to update ${url}:`, error);
    }
  }
}

// Database helper functions for chat messages
async function openChatDatabase() {
  // In a real implementation, this would open IndexedDB
  return Promise.resolve({
    messages: []
  });
}

async function getUnsyncedMessages(db) {
  return db.messages.filter(msg => !msg.synced);
}

async function markMessageAsSynced(db, messageId) {
  const message = db.messages.find(msg => msg.id === messageId);
  if (message) {
    message.synced = true;
  }
}

// Add to homescreen prompt handling
self.addEventListener('beforeinstallprompt', (event) => {
  console.log('[Service Worker] Before install prompt');
  event.preventDefault();
  self.deferredPrompt = event;
  
  // Send message to client to show install button
  clients.matchAll().then((clients) => {
    clients.forEach((client) => {
      client.postMessage({
        type: 'CAN_INSTALL',
        promptEvent: true
      });
    });
  });
});

// Service worker update check
self.addEventListener('controllerchange', () => {
  console.log('[Service Worker] Controller changed');
  window.location.reload();
});
