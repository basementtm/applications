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
 * Shows a privacy notification popup to the user and hides the form
 * @param {Object} notification - The notification object containing id, title, and message
 */
function showPrivacyNotification(notification) {
    // Hide the application form if it exists
    const applicationForm = document.getElementById('applicationForm');
    const statusContainer = document.getElementById('status-container');
    
    if (applicationForm) {
        applicationForm.style.display = 'none';
    }
    
    if (statusContainer) {
        statusContainer.style.display = 'none';
    }
    
    // Create notification container
    const notificationContainer = document.createElement('div');
    notificationContainer.className = 'privacy-notification';
    notificationContainer.innerHTML = `
        <div class="privacy-notification-content">
            <h3>🔔 ${notification.title}</h3>
            <div class="privacy-notification-message">${notification.message}</div>
            <div class="privacy-notification-actions">
                <a href="privacy-policy.html" target="_blank" class="privacy-notification-button view-policy">View Privacy Policy</a>
                <button type="button" class="privacy-notification-button dismiss">Dismiss & Continue</button>
            </div>
        </div>
    `;
    
    // Find the right container to append to
    const mainContainer = document.querySelector('.main-container') || document.body;
    mainContainer.prepend(notificationContainer);
    
    // Add CSS styles
    addPrivacyNotificationStyles();
    
    // Handle dismiss button click
    const dismissButton = notificationContainer.querySelector('.dismiss');
    dismissButton.addEventListener('click', function() {
        // Store the notification ID in localStorage to mark it as seen
        localStorage.setItem('lastSeenPrivacyNotificationId', notification.id);
        
        // Try to record the dismissal on the server
        try {
            fetch('admin/api-record-dismissal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notification_id: notification.id
                })
            }).catch(error => {
                // Silently fail - localStorage will still work as fallback
                console.log('Error recording dismissal:', error);
            });
        } catch (e) {
            // Silently fail
            console.log('Error sending dismissal request:', e);
        }
        
        // Remove the notification from DOM with animation
        notificationContainer.classList.add('closing');
        setTimeout(() => {
            notificationContainer.remove();
            
            // Show the application form if it exists
            const applicationForm = document.getElementById('applicationForm');
            if (applicationForm) {
                applicationForm.style.display = 'block';
            }
            
            // Show the status container if it exists
            const statusContainer = document.getElementById('status-container');
            if (statusContainer) {
                statusContainer.style.display = 'block';
            }
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
            max-width: 600px;
            margin: 20px auto;
            background-color: var(--container-bg, #fff0f5);
            border-radius: 15px;
            box-shadow: 0 4px 15px var(--shadow-color, rgba(0, 0, 0, 0.1));
            border: 3px solid var(--border-color, #ccc);
            z-index: 9999;
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .privacy-notification.closing {
            animation: fadeOut 0.3s ease-in forwards;
        }
        
        .privacy-notification-content {
            padding: 25px;
        }
        
        .privacy-notification h3 {
            margin: 0 0 15px 0;
            color: var(--primary-pink, #ff1493);
            font-size: 1.5rem;
            text-align: center;
        }
        
        .privacy-notification-message {
            margin-bottom: 20px;
            color: var(--text-color, #333);
            font-size: 1rem;
            line-height: 1.6;
            background-color: rgba(255, 255, 255, 0.5);
            padding: 15px;
            border-radius: 10px;
        }
        
        .privacy-notification-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .privacy-notification-button {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .privacy-notification-button.dismiss {
            background-color: var(--primary-pink, #ff1493);
            color: white;
        }
        
        .privacy-notification-button.view-policy {
            background-color: transparent;
            color: var(--primary-pink, #ff1493);
            border: 2px solid var(--primary-pink, #ff1493);
        }
        
        .privacy-notification-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
        
        /* Dark mode support */
        [data-theme="dark"] .privacy-notification {
            background-color: var(--container-bg, #3d2b3e);
            border-color: var(--border-color, #666);
        }
        
        [data-theme="dark"] .privacy-notification h3 {
            color: var(--primary-pink, #ff6bb3);
        }
        
        [data-theme="dark"] .privacy-notification-message {
            color: var(--text-color, #e0d0e0);
            background-color: rgba(0, 0, 0, 0.2);
        }
        
        [data-theme="dark"] .privacy-notification-button.view-policy {
            color: var(--primary-pink, #ff6bb3);
            border-color: var(--primary-pink, #ff6bb3);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .privacy-notification {
                margin: 15px;
                width: auto;
            }
            
            .privacy-notification-content {
                padding: 20px;
            }
            
            .privacy-notification h3 {
                font-size: 1.3rem;
            }
            
            .privacy-notification-button {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
        }
    
    `;
    
    document.head.appendChild(styleEl);
}
