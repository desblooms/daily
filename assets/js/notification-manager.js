// Push Notification Manager for Daily Calendar PWA
// Handles push notifications, PWA installation, and real-time updates

class NotificationManager {
  constructor() {
    this.swRegistration = null;
    this.isSubscribed = false;
    this.pushButton = null;
    this.applicationServerKey = null;
    this.subscription = null;
    this.init();
  }

  async init() {
    console.log('NotificationManager: Initializing...');
    
    if (!('serviceWorker' in navigator)) {
      console.warn('Service workers not supported');
      return;
    }

    if (!('PushManager' in window)) {
      console.warn('Push messaging not supported');
      return;
    }

    try {
      // Register service worker
      this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/'
      });
      
      console.log('Service Worker registered:', this.swRegistration);
      
      // Check if already subscribed
      this.subscription = await this.swRegistration.pushManager.getSubscription();
      this.isSubscribed = !(this.subscription === null);
      
      console.log('User is subscribed:', this.isSubscribed);
      
      // Get application server key from server
      await this.getApplicationServerKey();
      
      // Initialize UI
      this.initializeUI();
      
      // Listen for service worker messages
      this.setupMessageListener();
      
      // Auto-subscribe if not already subscribed
      if (!this.isSubscribed && this.shouldAutoSubscribe()) {
        await this.subscribeUser();
      }
      
    } catch (error) {
      console.error('Service Worker registration failed:', error);
    }
  }

  async getApplicationServerKey() {
    try {
      const response = await fetch('/api/get-vapid-key.php');
      const data = await response.json();
      
      if (data.success && data.publicKey) {
        this.applicationServerKey = this.urlBase64ToUint8Array(data.publicKey);
        console.log('VAPID key retrieved');
      } else {
        console.error('Failed to get VAPID key');
      }
    } catch (error) {
      console.error('Error getting VAPID key:', error);
    }
  }

  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  initializeUI() {
    // Create notification permission button
    this.createNotificationButton();
    
    // Update UI based on subscription status
    this.updateUI();
    
    // Create PWA install button
    this.createInstallButton();
  }

  createNotificationButton() {
    // Check if button already exists
    if (document.getElementById('notification-toggle')) {
      this.pushButton = document.getElementById('notification-toggle');
    } else {
      // Create notification toggle button
      const button = document.createElement('button');
      button.id = 'notification-toggle';
      button.className = 'fixed bottom-4 right-4 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg z-50 transition-all duration-300';
      button.innerHTML = '<i class="fas fa-bell text-lg"></i>';
      button.title = 'Toggle Notifications';
      
      // Add to page
      document.body.appendChild(button);
      this.pushButton = button;
    }
    
    this.pushButton.addEventListener('click', () => {
      this.togglePushSubscription();
    });
  }

  createInstallButton() {
    // PWA install prompt
    let deferredPrompt;
    
    window.addEventListener('beforeinstallprompt', (e) => {
      console.log('PWA install prompt available');
      e.preventDefault();
      deferredPrompt = e;
      
      // Show install button
      this.showInstallButton(deferredPrompt);
    });
    
    window.addEventListener('appinstalled', () => {
      console.log('PWA was installed');
      this.hideInstallButton();
      this.showNotification('App installed successfully!', 'success');
    });
  }

  showInstallButton(deferredPrompt) {
    const installButton = document.createElement('button');
    installButton.id = 'pwa-install-btn';
    installButton.className = 'fixed bottom-4 left-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-lg z-50 transition-all duration-300 text-sm font-medium';
    installButton.innerHTML = '<i class="fas fa-download mr-2"></i>Install App';
    
    installButton.addEventListener('click', async () => {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        console.log('User choice:', outcome);
        deferredPrompt = null;
        this.hideInstallButton();
      }
    });
    
    document.body.appendChild(installButton);
  }

  hideInstallButton() {
    const installButton = document.getElementById('pwa-install-btn');
    if (installButton) {
      installButton.remove();
    }
  }

  updateUI() {
    if (!this.pushButton) return;
    
    if (Notification.permission === 'denied') {
      this.pushButton.innerHTML = '<i class="fas fa-bell-slash text-lg"></i>';
      this.pushButton.className = this.pushButton.className.replace('bg-blue-600 hover:bg-blue-700', 'bg-red-600 hover:bg-red-700');
      this.pushButton.title = 'Notifications Blocked';
      this.pushButton.disabled = true;
    } else if (this.isSubscribed) {
      this.pushButton.innerHTML = '<i class="fas fa-bell text-lg"></i>';
      this.pushButton.className = this.pushButton.className.replace('bg-red-600 hover:bg-red-700', 'bg-green-600 hover:bg-green-700');
      this.pushButton.title = 'Notifications Enabled';
    } else {
      this.pushButton.innerHTML = '<i class="far fa-bell text-lg"></i>';
      this.pushButton.className = this.pushButton.className.replace('bg-green-600 hover:bg-green-700', 'bg-blue-600 hover:bg-blue-700');
      this.pushButton.title = 'Enable Notifications';
    }
  }

  async togglePushSubscription() {
    if (this.isSubscribed) {
      await this.unsubscribeUser();
    } else {
      await this.subscribeUser();
    }
  }

  async subscribeUser() {
    if (!this.applicationServerKey) {
      console.error('No VAPID key available');
      return;
    }

    try {
      // Request notification permission
      const permission = await Notification.requestPermission();
      
      if (permission !== 'granted') {
        console.log('Notification permission denied');
        this.showNotification('Please enable notifications to receive updates', 'warning');
        return;
      }

      // Subscribe to push notifications
      this.subscription = await this.swRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.applicationServerKey
      });

      console.log('User subscribed:', this.subscription);
      
      // Send subscription to server
      await this.sendSubscriptionToServer(this.subscription);
      
      this.isSubscribed = true;
      this.updateUI();
      this.showNotification('Push notifications enabled!', 'success');
      
    } catch (error) {
      console.error('Failed to subscribe user:', error);
      this.showNotification('Failed to enable notifications', 'error');
    }
  }

  async unsubscribeUser() {
    try {
      if (this.subscription) {
        await this.subscription.unsubscribe();
        await this.removeSubscriptionFromServer(this.subscription);
      }
      
      this.subscription = null;
      this.isSubscribed = false;
      this.updateUI();
      this.showNotification('Push notifications disabled', 'info');
      
    } catch (error) {
      console.error('Error unsubscribing:', error);
    }
  }

  async sendSubscriptionToServer(subscription) {
    try {
      const response = await fetch('/api/save-subscription.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          subscription: subscription,
          user_id: window.userId || null
        }),
        credentials: 'same-origin'
      });

      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.message || 'Failed to save subscription');
      }
      
      console.log('Subscription saved to server');
    } catch (error) {
      console.error('Error sending subscription to server:', error);
      throw error;
    }
  }

  async removeSubscriptionFromServer(subscription) {
    try {
      await fetch('/api/remove-subscription.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          endpoint: subscription.endpoint
        }),
        credentials: 'same-origin'
      });
    } catch (error) {
      console.error('Error removing subscription from server:', error);
    }
  }

  setupMessageListener() {
    navigator.serviceWorker.addEventListener('message', event => {
      console.log('Message from service worker:', event.data);
      
      const { type, data } = event.data;
      
      switch (type) {
        case 'NOTIFICATION_CLICKED':
          this.handleNotificationClick(data);
          break;
          
        case 'TASKS_SYNCED':
          this.handleTasksSync(data);
          break;
      }
    });
  }

  handleNotificationClick(data) {
    console.log('Notification clicked:', data);
    
    // Refresh tasks if we're on a task page
    if (typeof refreshTasks === 'function') {
      refreshTasks();
    }
    
    // Show in-app notification
    this.showNotification('Opening task...', 'info');
  }

  handleTasksSync(data) {
    console.log('Tasks synced:', data);
    
    // Refresh the page data
    if (typeof refreshTasks === 'function') {
      refreshTasks();
    }
    
    this.showNotification('Tasks updated', 'success');
  }

  shouldAutoSubscribe() {
    // Auto-subscribe for logged-in users who haven't made a decision
    return window.userId && !localStorage.getItem('notification-permission-asked');
  }

  // Send push notification (for testing)
  async sendTestNotification() {
    try {
      const response = await fetch('/api/send-push.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          title: 'Test Notification',
          body: 'This is a test push notification',
          data: {
            url: '/',
            timestamp: Date.now()
          }
        }),
        credentials: 'same-origin'
      });

      const result = await response.json();
      console.log('Test notification sent:', result);
    } catch (error) {
      console.error('Error sending test notification:', error);
    }
  }

  // In-app notification system
  showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transition-all duration-300 transform translate-x-full`;
    
    const colors = {
      success: 'bg-green-500 text-white',
      error: 'bg-red-500 text-white',
      warning: 'bg-yellow-500 text-black',
      info: 'bg-blue-500 text-white'
    };
    
    const icons = {
      success: 'fas fa-check-circle',
      error: 'fas fa-exclamation-circle',
      warning: 'fas fa-exclamation-triangle',
      info: 'fas fa-info-circle'
    };
    
    notification.className += ` ${colors[type] || colors.info}`;
    notification.innerHTML = `
      <div class="flex items-center">
        <i class="${icons[type] || icons.info} mr-3"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">×</button>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
      notification.classList.remove('translate-x-full');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
      notification.classList.add('translate-x-full');
      setTimeout(() => notification.remove(), 300);
    }, 5000);
  }

  // Real-time task updates
  startRealTimeUpdates() {
    // Set up periodic checks for updates
    setInterval(async () => {
      if (this.isSubscribed) {
        await this.checkForUpdates();
      }
    }, 30000); // Check every 30 seconds
  }

  async checkForUpdates() {
    try {
      const response = await fetch('/api/check-updates.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          last_check: localStorage.getItem('last-update-check') || Date.now()
        }),
        credentials: 'same-origin'
      });

      const data = await response.json();
      
      if (data.hasUpdates) {
        // Trigger background sync
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
          const registration = await navigator.serviceWorker.ready;
          await registration.sync.register('task-sync');
        }
      }
      
      localStorage.setItem('last-update-check', Date.now());
    } catch (error) {
      console.error('Error checking for updates:', error);
    }
  }
}

// Initialize notification manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  window.notificationManager = new NotificationManager();
  
  // Start real-time updates
  setTimeout(() => {
    window.notificationManager.startRealTimeUpdates();
  }, 5000);
});

// Global functions for testing
window.sendTestNotification = () => {
  if (window.notificationManager) {
    window.notificationManager.sendTestNotification();
  }
};

window.toggleNotifications = () => {
  if (window.notificationManager) {
    window.notificationManager.togglePushSubscription();
  }
};