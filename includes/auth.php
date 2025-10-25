<?php
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function login($username, $password) {
        $pdo = $this->db->getConnection();
        
        // Consulta SIMPLIFICADA - solo tabla usuarios
        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, tipo, nombres, apellidos, dni, telefono, email
            FROM usuarios 
            WHERE username = ? AND activo = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // DEBUG
        error_log("Usuario encontrado: " . ($user ? 'SÍ' : 'NO'));
        if ($user) {
            error_log("Tipo de usuario: " . $user['tipo']);
            error_log("Verificación password: " . (password_verify($password, $user['password_hash']) ? 'CORRECTA' : 'INCORRECTA'));
        }
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return [
                'success' => true,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'user_type' => $user['tipo'],
                'nombres' => $user['nombres'],
                'apellidos' => $user['apellidos'],
                'dni' => $user['dni'],
                'telefono' => $user['telefono'],
                'email' => $user['email']
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ];
        }
    }
    
    public function checkAuth() {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header('Location: /plataforma-escolar/');
            exit;
        }
        return $_SESSION;
    }
    
    public function logout() {
        session_start();
        session_destroy();
        header('Location: /plataforma-escolar/');
        exit;
    }
    
    // Método adicional para obtener datos específicos según el tipo de usuario
    public function getUserProfile($user_id, $user_type) {
        $pdo = $this->db->getConnection();
        
        if ($user_type === 'docente') {
            // Si mantienes la tabla docentes para datos específicos
            $stmt = $pdo->prepare("
                SELECT u.*, d.especialidad 
                FROM usuarios u 
                LEFT JOIN docentes d ON u.id = d.usuario_id 
                WHERE u.id = ?
            ");
        } else if ($user_type === 'padre') {
            // Si mantienes la tabla padres para datos específicos
            $stmt = $pdo->prepare("
                SELECT u.*, p.direccion 
                FROM usuarios u 
                LEFT JOIN padres p ON u.id = p.usuario_id 
                WHERE u.id = ?
            ");
        } else {
            // Para administradores o tipos básicos
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        }
        
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>