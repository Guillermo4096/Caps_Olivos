<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    header('Location: ../../index.html');
    exit;
}

// Cargar estadísticas reales desde la BD
require_once '../../includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener estadísticas
    $stats = [
        'total_usuarios' => 0,
        'total_docentes' => 0,
        'total_padres' => 0,
        'total_estudiantes' => 0
    ];
    
    // Total usuarios por tipo
    $stmt = $conn->query("SELECT tipo, COUNT(*) as total FROM usuarios WHERE activo = 1 GROUP BY tipo");
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($resultados as $fila) {
        switch($fila['tipo']) {
            case 'docente':
                $stats['total_docentes'] = $fila['total'];
                break;
            case 'padre':
                $stats['total_padres'] = $fila['total'];
                break;
            case 'administrador':
                // No contar administradores en el total general
                break;
        }
        $stats['total_usuarios'] += $fila['total'];
    }
    
    // Total estudiantes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM estudiantes");
    $stats['total_estudiantes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    // En caso de error, usar ceros
    $stats = [
        'total_usuarios' => 0,
        'total_docentes' => 0, 
        'total_padres' => 0,
        'total_estudiantes' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - I.E José Faustino Sánchez Carrión</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <div class="main-app active">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>⚙️ Panel Admin</h3>
                <p>I.E José Faustino Sánchez C.</p>
            </div>
            
            <div class="sidebar-nav">
                <div class="nav-item active" onclick="loadModule('dashboard', this)">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item" onclick="loadModule('gestion-usuarios', this)">
                    <span class="nav-icon">👥</span>
                    <span>Gestión de Usuarios</span>
                </div>
                <div class="nav-item" onclick="loadModule('gestion-estudiantes', this)">
                    <span class="nav-icon">🎓</span>
                    <span>Gestión de Estudiantes</span>
                </div>
                <div class="nav-item" onclick="loadModule('gestion-grados', this)">
                    <span class="nav-icon">🏫</span>
                    <span>Gestión de Grados</span>
                </div>
                <div class="nav-item" onclick="loadModule('reportes', this)">
                    <span class="nav-icon">📈</span>
                    <span>Reportes</span>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">👨‍💼</div>
                    <div class="user-details">
                        <div class="user-name"><?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></div>
                        <div class="user-role">Administrador</div>
                    </div>
                </div>
                <button class="btn-logout" onclick="handleLogout()">🚪 Cerrar Sesión</button>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <div>
                    <h2 id="moduleTitle">Dashboard Admin</h2>
                    <div class="breadcrumb">Panel de administración del sistema</div>
                </div>
            </div>
            
            <div class="content-area">
                <!-- DASHBOARD ADMIN -->
                <div id="dashboard" class="module-content active">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-number" id="totalUsuarios"><?php echo $stats['total_usuarios']; ?></div>
                            <div class="stat-label">Total Usuarios</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">👨‍🏫</div>
                            <div class="stat-number" id="totalDocentes"><?php echo $stats['total_docentes']; ?></div>
                            <div class="stat-label">Docentes</div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-icon">👨</div>
                            <div class="stat-number" id="totalPadres"><?php echo $stats['total_padres']; ?></div>
                            <div class="stat-label">Padres</div>
                        </div>
                        
                        <div class="stat-card purple">
                            <div class="stat-icon">🎓</div>
                            <div class="stat-number" id="totalEstudiantes"><?php echo $stats['total_estudiantes']; ?></div>
                            <div class="stat-label">Estudiantes</div>
                        </div>
                    </div>
                </div>
                
                <!-- GESTIÓN DE USUARIOS -->
                <div id="gestion-usuarios" class="module-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: #2c3e50;">Gestión de Usuarios</h3>
                        <button class="btn-login" onclick="mostrarModalCrearUsuario()">➕ Crear Usuario</button>
                    </div>
                    
                    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; text-align: left;">
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Usuario</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Nombre</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Tipo</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Email</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Estado</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaUsuarios">
                                <!-- Los usuarios se cargarán con JavaScript -->
                                <tr>
                                    <td colspan="6" style="padding: 20px; text-align: center; color: #7f8c8d;">
                                        Cargando usuarios...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Los otros módulos irían aquí -->
            </div>
        </div>
    </div>

    <!-- Modal para crear/editar usuario -->
    <div id="modalUsuario" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 500px; max-width: 90%;">
            <h3 id="modalTitulo">Crear Usuario</h3>
            <form id="formUsuario">
                <input type="hidden" id="usuarioId">
                
                <div class="form-group">
                    <label>Tipo de Usuario</label>
                    <select id="tipoUsuario" required>
                        <option value="">Seleccionar tipo...</option>
                        <option value="docente">Docente</option>
                        <option value="padre">Padre de Familia</option>
                        <option value="administrador">Administrador</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="username" required>
                </div>
                
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" id="password" required>
                </div>
                
                <div class="form-group">
                    <label>Nombres</label>
                    <input type="text" id="nombres" required>
                </div>
                
                <div class="form-group">
                    <label>Apellidos</label>
                    <input type="text" id="apellidos" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email">
                </div>

                <div class="form-group">
                    <label>DNI</label>
                    <input type="text" id="dni" maxlength="8">
                </div>

                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" id="telefono">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn-login" onclick="guardarUsuario()">💾 Guardar</button>
                    <button type="button" class="btn-logout" onclick="cerrarModal()">❌ Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Función para cargar módulos
        function loadModule(moduleId, clickedElement) {
            // Ocultar todos los módulos
            document.querySelectorAll('.module-content').forEach(module => {
                module.classList.remove('active');
            });
            
            // Remover activo de todos los items del menú
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Mostrar módulo seleccionado y activar item del menú
            document.getElementById(moduleId).classList.add('active');
            clickedElement.classList.add('active');
            
            // Actualizar título
            const titles = {
                'dashboard': 'Dashboard Admin',
                'gestion-usuarios': 'Gestión de Usuarios',
                'gestion-estudiantes': 'Gestión de Estudiantes',
                'gestion-grados': 'Gestión de Grados',
                'reportes': 'Reportes'
            };
            document.getElementById('moduleTitle').textContent = titles[moduleId] || 'Panel Admin';
            
            // Cargar datos específicos del módulo
            if (moduleId === 'gestion-usuarios') {
                cargarUsuarios();
            }
        }
        
        // Función de logout
        function handleLogout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = '../../api/auth/logout.php';
            }
        }
        
        // Funciones para gestión de usuarios
        async function cargarUsuarios() {
            try {
                const response = await fetch('../../api/administrador/usuarios.php');
                const data = await response.json();
                
                if (data.success) {
                    actualizarTablaUsuarios(data.usuarios);
                } else {
                    document.getElementById('tablaUsuarios').innerHTML = 
                        '<tr><td colspan="6" style="padding: 20px; text-align: center; color: #e74c3c;">Error al cargar usuarios</td></tr>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('tablaUsuarios').innerHTML = 
                    '<tr><td colspan="6" style="padding: 20px; text-align: center; color: #e74c3c;">Error de conexión</td></tr>';
            }
        }
        
        function actualizarTablaUsuarios(usuarios) {
            const tbody = document.getElementById('tablaUsuarios');
            
            if (usuarios.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: #7f8c8d;">No hay usuarios registrados</td></tr>';
                return;
            }
            
            let html = '';
            usuarios.forEach(usuario => {
                const estadoColor = usuario.activo ? '#2ecc71' : '#e74c3c';
                const estadoTexto = usuario.activo ? 'Activo' : 'Inactivo';
                
                html += `
                <tr style="border-bottom: 1px solid #f0f2f5;">
                    <td style="padding: 15px; font-weight: 600;">${usuario.username}</td>
                    <td style="padding: 15px;">${usuario.nombres} ${usuario.apellidos}</td>
                    <td style="padding: 15px;">
                        <span style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; 
                              background: ${usuario.tipo === 'docente' ? '#3498db' : usuario.tipo === 'padre' ? '#2ecc71' : '#e74c3c'}; 
                              color: white;">
                            ${usuario.tipo}
                        </span>
                    </td>
                    <td style="padding: 15px;">${usuario.email || 'No especificado'}</td>
                    <td style="padding: 15px;">
                        <span style="color: ${estadoColor}; font-weight: 600;">${estadoTexto}</span>
                    </td>
                    <td style="padding: 15px;">
                        <button onclick="editarUsuario(${usuario.id})" style="padding: 6px 12px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                            ✏️ Editar
                        </button>
                        <button onclick="eliminarUsuario(${usuario.id})" style="padding: 6px 12px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            🗑️ Eliminar
                        </button>
                    </td>
                </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        function mostrarModalCrearUsuario() {
            document.getElementById('modalTitulo').textContent = 'Crear Usuario';
            document.getElementById('formUsuario').reset();
            document.getElementById('usuarioId').value = '';
            document.getElementById('modalUsuario').style.display = 'flex';
        }
        
        function cerrarModal() {
            document.getElementById('modalUsuario').style.display = 'none';
        }
        
        async function guardarUsuario() {
            const formData = {
                usuarioId: document.getElementById('usuarioId').value,
                tipo: document.getElementById('tipoUsuario').value,
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
                nombres: document.getElementById('nombres').value,
                apellidos: document.getElementById('apellidos').value,
                email: document.getElementById('email').value,
                dni: document.getElementById('dni').value,
                telefono: document.getElementById('telefono').value
            };
            
            try {
                const response = await fetch('../../api/administrador/guardar-usuario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ ' + data.message);
                    cerrarModal();
                    cargarUsuarios(); // Recargar la tabla
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error de conexión');
            }
        }
        
        function editarUsuario(usuarioId) {
            alert('Editar usuario: ' + usuarioId + ' - Esta funcionalidad se implementará próximamente');
        }
        
        function eliminarUsuario(usuarioId) {
            if (confirm('¿Estás seguro de que quieres eliminar este usuario?')) {
                alert('Eliminar usuario: ' + usuarioId + ' - Esta funcionalidad se implementará próximamente');
            }
        }
        
        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar datos iniciales del dashboard
            cargarUsuarios();
        });
    </script>
</body>
</html>