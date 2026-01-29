// Service Worker Registration
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/service-worker.js')
      .then(function(registration) {
        console.log('Service Worker registered with scope:', registration.scope);
        
        // Check for updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          console.log('Service Worker update found!');
          
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              // New update available
              showUpdateNotification();
            }
          });
        });
        
        // Periodic update check (every 24 hours)
        setInterval(() => {
          registration.update();
        }, 24 * 60 * 60 * 1000);
      })
      .catch(function(error) {
        console.log('Service Worker registration failed:', error);
      });
  });
}

// Handle beforeinstallprompt event
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  showInstallPromotion();
});

// Install handler
function installPWA() {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    
    deferredPrompt.userChoice.then((choiceResult) => {
      if (choiceResult.outcome === 'accepted') {
        console.log('User accepted the install prompt');
      } else {
        console.log('User dismissed the install prompt');
      }
      deferredPrompt = null;
    });
  }
}

// Show install promotion
function showInstallPromotion() {
  const installBtn = document.createElement('button');
  installBtn.className = 'install-btn';
  installBtn.innerHTML = '📱 Install App';
  installBtn.onclick = installPWA;
  
  installBtn.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #3182ce;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    z-index: 10000;
  `;
  
  document.body.appendChild(installBtn);
  
  // Auto-hide after 10 seconds
  setTimeout(() => {
    installBtn.style.opacity = '0';
    setTimeout(() => installBtn.remove(), 500);
  }, 10000);
}

// Show update notification
function showUpdateNotification() {
  if (confirm('A new version of Zewed AI is available. Refresh to update?')) {
    window.location.reload();
  }
}

// Check connection status
window.addEventListener('online', () => {
  console.log('You are online');
  document.body.classList.remove('offline');
});

window.addEventListener('offline', () => {
  console.log('You are offline');
  document.body.classList.add('offline');
});

// Initialize connection status
if (!navigator.onLine) {
  document.body.classList.add('offline');
}
