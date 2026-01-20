<?php
/**
 * Sistema de Caché Simple (File-based)
 * 
 * Almacena datos en archivos para mejorar rendimiento
 */

class Cache {
    private static $instance = null;
    private $cacheDir;
    private $enabled;
    private $defaultDuration;
    
    private function __construct() {
        $config = require __DIR__ . '/env.php';
        $this->cacheDir = $config['CACHE_DIR'];
        $this->enabled = $config['CACHE_ENABLED'];
        $this->defaultDuration = $config['CACHE_DURATION'];
        
        // Crear directorio de caché si no existe
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtiene un valor del caché
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }
        
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Verificar expiración
        if ($data === null || !isset($data['expires']) || time() > $data['expires']) {
            $this->delete($key);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Guarda un valor en el caché
     */
    public function set($key, $value, $duration = null) {
        if (!$this->enabled) {
            return false;
        }
        
        $duration = $duration ?? $this->defaultDuration;
        $file = $this->getCacheFile($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $duration,
            'created' => time()
        ];
        
        return file_put_contents($file, json_encode($data), LOCK_EX) !== false;
    }
    
    /**
     * Elimina un valor del caché
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            unlink($file);
            return true;
        }
        return false;
    }
    
    /**
     * Limpia todo el caché
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Limpia caché expirado
     */
    public function cleanExpired() {
        $files = glob($this->cacheDir . '*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data === null || !isset($data['expires']) || time() > $data['expires']) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Obtiene la ruta del archivo de caché
     */
    private function getCacheFile($key) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
        return $this->cacheDir . $safeKey . '.cache';
    }
}
