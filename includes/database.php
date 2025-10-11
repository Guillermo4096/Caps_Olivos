<?php
class Database {
    private $db_path;
    
    public function __construct() {
        // Ruta ABSOLUTA para mejor compatibilidad
        $this->db_path = realpath(__DIR__ . '/../database/plataforma_escolar.db');
        
        if ($this->db_path === false) {
            $this->db_path = __DIR__ . '/../database/plataforma_escolar.db';
        }
    }
    
    public function getConnection() {
        try {
            // Verificar que el archivo existe
            if (!file_exists($this->db_path)) {
                throw new Exception("Archivo de BD no encontrado: " . $this->db_path);
            }
            
            $conn = new PDO("sqlite:" . $this->db_path);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Activar claves foráneas en SQLite
            $conn->exec("PRAGMA foreign_keys = ON");
            
            return $conn;
        } catch(PDOException $e) {
            throw new Exception("Error de conexión SQLite: " . $e->getMessage());
        }
    }
}
?>