<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adjust path depending on where this file is included from
// Using __DIR__ ensures we find config/db.php correctly relative to templates/
require_once __DIR__ . '/../config/db.php';

// 1. Initialize Default Configuration
$appConfig = [
    'nombre_empresa' => 'Investor MLP',
    'logo_url'       => 'assets/img/shield_logo.png', // Default 'Shield' fallback
    'color_primario' => '#0d6efd'
];

// 2. Fetch Overrides from Database
try {
    $stmtConfig = $pdo->query("SELECT nombre_empresa, logo_url, color_primario FROM config_empresa WHERE id_config = 1");
    $dbConfig = $stmtConfig->fetch(PDO::FETCH_ASSOC);
    
    if ($dbConfig) {
        // Merge DB values into appConfig (only non-empty values if you prefer)
        if (!empty($dbConfig['nombre_empresa'])) $appConfig['nombre_empresa'] = $dbConfig['nombre_empresa'];
        if (!empty($dbConfig['logo_url']))       $appConfig['logo_url'] = $dbConfig['logo_url'];
        if (!empty($dbConfig['color_primario'])) $appConfig['color_primario'] = $dbConfig['color_primario'];
    }
} catch (Exception $e) {
    // If DB fails, we silently fall back to defaults defined above
    error_log("Config Fetch Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Critical for mobile -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($appConfig['color_primario']) ?>;
        }
        
        /* Global styles */
        body { background-color: #f0f2f5; }
        
        /* --- Styles for Top Bar - EVE 360 Design --- */
        .top-banner {
            height: 70px;
            background: linear-gradient(135deg, #082d6e 0%, #073a56 100%);
            box-shadow: 0 4px 12px rgba(11, 60, 138, 0.4);
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            position: relative;
        }

        .top-bar-left { 
            display: flex; 
            align-items: center; 
            gap: 1rem;
        }
        
        .back-button {
            display: flex; 
            align-items: center; 
            gap: 8px;
            font-size: 0.9rem; 
            font-weight: 500; 
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none; 
            padding: 8px 12px; 
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .back-button:hover { 
            background: rgba(255, 255, 255, 0.2); 
            color: #ffffff;
            transform: translateX(-3px);
        }
        .back-button i {
            font-size: 1rem;
        }

        .app-brand,
        .navbar-brand { 
            font-size: 1.3rem; 
            font-weight: 700; 
            color: #ffffff; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            text-decoration: none;
            padding: 0;
        }
        .app-brand:hover,
        .navbar-brand:hover { 
            color: #2ED1FF;
            transform: scale(1.02);
        }
        .app-brand img,
        .navbar-brand img {
            height: 45px !important;
            /* Logo mantiene su color natural */
        }

        .user-actions { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }

        .notif-icon { 
            position: relative; 
            cursor: pointer; 
            color: rgba(255, 255, 255, 0.9); 
            font-size: 1.4rem;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .notif-icon:hover { 
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            transform: scale(1.1);
        }

        .notif-badge { 
            position: absolute; 
            top: -4px; 
            right: -4px; 
            background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);
            color: white; 
            font-size: 0.7rem; 
            font-weight: 700;
            padding: 3px 6px; 
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
            border: 2px solid #0B3C8A;
            display: none; 
        }

        .user-profile { 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            padding: 6px 12px; 
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .user-profile:hover { 
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .user-avatar { 
            width: 36px; 
            height: 36px; 
            border-radius: 50%; 
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .user-name { 
            font-size: 0.95rem; 
            font-weight: 600; 
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .user-name i {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .restricted { display: none !important; }

        /* Mobile adjustments for Top Bar */
        @media (max-width: 768px) {
            .top-banner {
                padding: 0 1rem;
                height: 65px;
            }

            .app-brand span,
            .navbar-brand span {
                font-size: 1.1rem;
            }

            .user-name {
                display: none;
            }

            .user-profile {
                padding: 6px;
            }

            .notif-icon {
                width: 38px;
                height: 38px;
                font-size: 1.2rem;
            }
        }

        @media (max-width: 576px) {
            .top-banner {
                padding: 0 0.75rem;
                height: 60px;
            }

            .app-brand span,
            .navbar-brand span {
                display: none;
            }

            .back-button span { 
                display: none; 
            }
            .back-button { 
                padding: 6px 8px;
                gap: 0;
            }

            .user-actions {
                gap: 10px;
            }

            .notif-icon {
                width: 36px;
                height: 36px;
                font-size: 1.1rem;
            }
        }

        /* Notification Panel Styles - Responsive */
        .notification-dropdown {
            position: absolute; 
            top: 70px; 
            right: 20px; 
            width: 450px; 
            max-width: 90vw;
            background: white; 
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(11, 60, 138, 0.25); 
            z-index: 2000;
            display: none; 
            border: 1px solid rgba(199, 205, 214, 0.3);
            max-height: 80vh; 
            overflow-y: auto;
        }
        .notification-dropdown::-webkit-scrollbar { width: 8px; }
        .notification-dropdown::-webkit-scrollbar-track { background: #f1f1f1; }
        .notification-dropdown::-webkit-scrollbar-thumb { 
            background: linear-gradient(135deg, var(--primary-color) 0%, #0B3C8A 100%);
            border-radius: 4px; 
        }
        
        @media (max-width: 768px) {
            .notification-dropdown {
                top: 65px;
            }
        }

        @media (max-width: 576px) {
            .notification-dropdown {
                top: 60px;
                right: 0; 
                left: 0;
                width: 100%;
                border-radius: 0;
                max-width: 100vw;
            }
        }

        /* Dropdown menu mejorado */
        .dropdown-menu {
            border-radius: 12px !important;
            border: 1px solid rgba(199, 205, 214, 0.3) !important;
            box-shadow: 0 12px 40px rgba(11, 60, 138, 0.2) !important;
            padding: 0.5rem 0 !important;
        }

        .dropdown-item {
            padding: 0.75rem 1.25rem !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dropdown-item:hover {
            background: rgba(27, 143, 234, 0.1) !important;
            color: var(--primary-color) !important;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
        }

        .dropdown-header {
            padding: 0.5rem 1.25rem !important;
            font-size: 0.75rem !important;
            text-transform: uppercase !important;
            letter-spacing: 1px;
            color: #6c757d !important;
        }
    </style>
    <!-- Page-specific <title> and <style> will go after this include -->