<?php
// Banner helper functions

function getBannerSettings($conn) {
    $banner_settings = [
        'text' => '',
        'enabled' => false,
        'type' => 'info'
    ];
    
    // Check if site_settings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'site_settings'");
    if ($table_check && $table_check->num_rows > 0) {
        $settings_sql = "SELECT setting_name, setting_value FROM site_settings 
                         WHERE setting_name IN ('banner_text', 'banner_enabled', 'banner_type')";
        $settings_result = $conn->query($settings_sql);
        
        if ($settings_result) {
            while ($row = $settings_result->fetch_assoc()) {
                switch ($row['setting_name']) {
                    case 'banner_text':
                        $banner_settings['text'] = $row['setting_value'];
                        break;
                    case 'banner_enabled':
                        $banner_settings['enabled'] = ($row['setting_value'] === '1');
                        break;
                    case 'banner_type':
                        $banner_settings['type'] = $row['setting_value'];
                        break;
                }
            }
        }
    }
    
    return $banner_settings;
}

function renderBanner($conn, $additional_classes = '') {
    $banner = getBannerSettings($conn);
    
    if ($banner['enabled'] && !empty($banner['text'])) {
        $type_icons = [
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'success' => '✅',
            'error' => '❌'
        ];
        
        $icon = $type_icons[$banner['type']] ?? 'ℹ️';
        $class = 'banner banner-' . htmlspecialchars($banner['type']);
        if ($additional_classes) {
            $class .= ' ' . $additional_classes;
        }
        
        echo '<div class="' . $class . '">';
        echo '<span class="banner-icon">' . $icon . '</span>';
        echo '<span class="banner-text">' . htmlspecialchars($banner['text']) . '</span>';
        echo '</div>';
        
        return true; // Banner was displayed
    }
    
    return false; // No banner displayed
}

function getBannerCSS() {
    return '
        .banner {
            padding: 15px 20px;
            margin: 20px auto;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            max-width: 800px;
            border: 2px solid;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: bannerSlideIn 0.5s ease-out;
        }
        
        .banner-info {
            background-color: #cce7ff;
            color: #0066cc;
            border-color: #99d6ff;
        }
        
        .banner-warning {
            background-color: #fff3cd;
            color: #cc6600;
            border-color: #ffe066;
        }
        
        .banner-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .banner-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .banner-icon {
            font-size: 1.2em;
        }
        
        .banner-text {
            flex: 1;
        }
        
        @keyframes bannerSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .banner {
                margin: 15px;
                padding: 12px 15px;
                font-size: 0.9rem;
            }
        }
    ';
}
?>
