<?php
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function login($username, $password) {
        $pdo = $this->db->getConnection();
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.password_hash, u.tipo, 
                   COALESCE(p.nombres, d.nombres, '') as nombres,
                   COALESCE(p.apellidos, d.apellidos, '') as apellidos
            FROM usuarios u
            LEFT JOIN padres p ON u.id = p.usuario_id AND u.tipo = 'padre'
            LEFT JOIN docentes d ON u.id = d.usuario_id AND u.tipo = 'docente'
            WHERE u.username = ? AND u.activo = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return [
                'success' => true,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'user_type' => $user['tipo'],
                'nombres' => $user['nombres'],
                'apellidos' => $user['apellidos']
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
}
?>