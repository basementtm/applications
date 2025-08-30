/**
 * Privacy Policy Notification System
 * Displays notifications to users when the privacy policy has been updated
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if we should show a privacy notification
    checkPrivacyNotifications();
});

/**
 * Checks if there are any privacy notifications that should be shown to the user
 */
function checkPrivacyNotifications() {
    // Get the latest seen notification ID from localStorage
    const lastSeenNotificationId = localStorage.getItem('lastSeenPrivacyNotificationId') || 0;
    
    // Fetch active notifications from the server
    fetch('admin/api-privacy-notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications && data.notifications.length > 0) {
                // Find notifications that are newer than the last seen one
                const unseenNotifications = data.notifications.filter(
                    notification => parseInt(notification.id) > parseInt(lastSeenNotificationId)
                );
                
                if (unseenNotifications.length > 0) {
                    // Show the most recent unseen notification
                    showPrivacyNotification(unseenNotifications[0]);
                }
            }
        })
        .catch(error => {
            console.error('Error fetching privacy notifications:', error);
        });
}

/**
 * Shows a privacy notification popup to the user
 * @param {Object} notification - The notification object containing id, title, and message
 */
function showPrivacyNotification(notification) {
    // Create notification container
    const notificationContainer = document.createElement('div');
    notificationContainer.className = 'privacy-notification';
    notificationContainer.innerHTML = `
        <div class="privacy-notification-content">
            <h3>${notification.title}</h3>
            <div class="privacy-notification-message">${notification.message}</div>
            <div class="privacy-notification-actions">
                <a href="privacy-policy.html" target="_blank" class="privacy-notification-button view-policy">View Privacy Policy</a>
                <button type="button" class="privacy-notification-button dismiss">Dismiss</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(notificationContainer);
    
    // Add CSS styles
    addPrivacyNotificationStyles();
    
    // Handle dismiss button click
    const dismissButton = notificationContainer.querySelector('.dismiss');
    dismissButton.addEventListener('click', function() {
        // Store the notification ID in localStorage to mark it as seen
        localStorage.setItem('lastSeenPrivacyNotificationId', notification.id);
        
        // Remove the notification from DOM with animation
        notificationContainer.classList.add('closing');
        setTimeout(() => {
            notificationContainer.remove();
        }, 300);
    });
}

/**
 * Adds the required CSS styles for the notification popup
 */
function addPrivacyNotificationStyles() {
    // Check if styles already exist
    if (document.getElementById('privacy-notification-styles')) {
        return;
    }
    
    const styleEl = document.createElement('style');
    styleEl.id = 'privacy-notification-styles';
    styleEl.textContent = `
        .privacy-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            max-width: 400px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            animation: slideIn 0.3s ease-out forwards;
        }
        
        .privacy-notification.closing {
            animation: slideOut 0.3s ease-in forwards;
        }
        
        .privacy-notification-content {
            padding: 20px;
        }
        
        .privacy-notification h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 18px;
        }
        
        .privacy-notification-message {
            margin-bottom: 15px;
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .privacy-notification-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .privacy-notification-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }
        
        .privacy-notification-button.dismiss {
            background-color: #f1f1f1;
            color: #333;
        }
        
        .privacy-notification-button.view-policy {
            background-color: #ff14a3;
            color: white;
            display: inline-block;
        }
        
        .privacy-notification-button:hover {
            opacity: 0.9;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(100px);
                opacity: 0;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .privacy-notification {
                background-color: #333;
                border: 1px solid #444;
            }
            
            .privacy-notification h3 {
                color: #fff;
            }
            
            .privacy-notification-message {
                color: #ddd;
            }
            
            .privacy-notification-button.dismiss {
                background-color: #555;
                color: #eee;
            }
        }
    `;
    
    document.head.appendChild(styleEl);
}
