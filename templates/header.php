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
        
        /* --- Styles for Top Bar --- */
        .top-banner {
            height: 60px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            position: relative;
        }
        /* Mobile adjustments for Top Bar */
        @media (max-width: 576px) {
            .top-banner {
                padding: 0 1rem;
            }
            .app-brand span {
                display: none; /* Hide app name on very small screens if needed, or keep it */
            }
            .user-name {
                display: none; /* Hide user name on mobile, show avatar only */
            }
        }

        .app-brand { 
            font-size: 1.25rem; font-weight: 600; color: #333; 
            display: flex; align-items: center; gap: 10px; 
            text-decoration: none; 
        }
        .app-brand:hover { color: #0d6efd; }

        .top-bar-left { display: flex; align-items: center; }
        
        .back-button {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.9rem; font-weight: 500; color: #555;
            text-decoration: none; margin-right: 1.5rem;
            padding: 5px 10px; border-radius: 6px;
        }
        .back-button:hover { background-color: #f0f2f5; color: #000; }
        
        /* Hide "Atrás" text on mobile, show only icon */
        @media (max-width: 576px) {
            .back-button span { display: none; }
            .back-button { margin-right: 0.5rem; }
        }
        
        .user-actions { display: flex; align-items: center; gap: 20px; }
        .notif-icon { position: relative; cursor: pointer; color: #5f6368; font-size: 1.2rem; }
        .notif-badge { position: absolute; top: -5px; right: -5px; background-color: #d93025; color: white; font-size: 0.7rem; padding: 2px 5px; border-radius: 50%; display: none; }
        .user-profile { cursor: pointer; display: flex; align-items: center; gap: 10px; padding: 5px 10px; border-radius: 8px; }
        .user-profile:hover { background-color: #f1f3f4; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .user-name { font-size: 0.9rem; font-weight: 500; color: #333; }
        .restricted { display: none !important; }

        /* Notification Panel Styles - Responsive */
        .notification-dropdown {
            position: absolute; top: 60px; right: 20px; 
            width: 450px; max-width: 90vw; /* Adapt width */
            background: white; border-radius: 0 0 8px 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 2000;
            display: none; border: 1px solid #e0e0e0;
            max-height: 80vh; overflow-y: auto;
        }
        .notification-dropdown::-webkit-scrollbar { width: 8px; }
        .notification-dropdown::-webkit-scrollbar-track { background: #f1f1f1; }
        .notification-dropdown::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        
        @media (max-width: 576px) {
            .notification-dropdown {
                right: 0; left: 0; /* Full width on mobile */
                width: 100%;
                border-radius: 0;
            }
        }
    </style>
    <!-- Page-specific <title> and <style> will go after this include -->