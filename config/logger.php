<?php
/**
 * Sistema de Logging
 * 
 * Niveles: DEBUG, INFO, WARNING, ERROR
 */

class Logger {
    private static $instance = null;
    private $logDir;
    private $logFile;
    private $logLevel;
    private $maxSize;
    
    private function __construct() {
        $config = require __DIR__ . '/env.php';
        $this->logDir = $config['LOG_DIR'];
        $this->logFile = $config['LOG_FILE'];
        $this->logLevel = $config['LOG_LEVEL'];
        $this->maxSize = $config['LOG_MAX_SIZE'];
        
        // Crear directorio de logs si no existe
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Escribe un log
     */
    public function log($level, $message, $context = []) {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        $configLevel = $levels[$this->logLevel] ?? 3;
        $messageLevel = $levels[strtoupper($level)] ?? 3;
        
        // Si el nivel del mensaje es menor que el configurado, no loguear
        if ($messageLevel < $configLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        $logPath = $this->logDir . $this->logFile;
        
        // Rotar log si excede el tamaño máximo
        if (file_exists($logPath) && filesize($logPath) > $this->maxSize) {
            $this->rotateLog();
        }
        
        file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Métodos de conveniencia
     */
    public function debug($message, $context = []) {
        $this->log('DEBUG', $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Rota el log actual
     */
    private function rotateLog() {
        $logPath = $this->logDir . $this->logFile;
        $backupPath = $this->logDir . $this->logFile . '.' . date('Y-m-d_H-i-s');
        if (file_exists($logPath)) {
            rename($logPath, $backupPath);
        }
    }
    
    /**
     * Limpia logs antiguos (más de 30 días)
     */
    public function cleanOldLogs($days = 30) {
        $files = glob($this->logDir . '*.log.*');
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}
