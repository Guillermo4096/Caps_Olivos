<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'administrador') {
    header('Location: ../../index.html');
    http_response_code(401);
    echo json_encode('Acceso restringido');
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

                <div id="gestion-estudiantes" class="module-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Gestión de Estudiantes</h2>
                        <button onclick="mostrarModalCrearEstudiante()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                            ➕ Nuevo Estudiante
                        </button>
                    </div>
                    
                    <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                    <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600;">Nombres</th>
                                    <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600;">Apellidos</th>
                                    <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600;">DNI</th>
                                    <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600;">Grado/Sección</th>
                                    <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600;">Estado</th>
                                    <th style="padding: 15px; text-align: center; color: #495057; font-weight: 600;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaEstudiantes">
                                <tr><td colspan="8" style="padding: 20px; text-align: center; color: #7f8c8d;">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                
                <div id="modalEstudiante" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; padding: 20px 0;">
                    <div style="background: white; padding: 30px; border-radius: 12px; width: 600px; max-width: 90%; margin: 50px auto;">
                        <h3 id="modalTituloEstudiante">Crear Estudiante</h3>
                        <form id="formEstudiante" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <input type="hidden" id="estudianteId" value="">
                            
                            <div style="grid-column: 1;">
                                <label style="display: block; margin-bottom: 5px; color: #333; font-weight: 500;">Nombres *</label>
                                <input type="text" id="est_nombres" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                            
                            <div style="grid-column: 2;">
                                <label style="display: block; margin-bottom: 5px; color: #333; font-weight: 500;">Apellidos *</label>
                                <input type="text" id="est_apellidos" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 5px; color: #333; font-weight: 500;">DNI *</label>
                                <input type="text" id="est_dni" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            </div>
                            
                            <div style="grid-column: 1;">
                                <label style="display: block; margin-bottom: 5px; color: #333; font-weight: 500;">Grado *</label>
                                <select id="est_grado" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                    <option value="">Seleccionar...</option>
                                    <option value="1">4to Grado Primaria</option>
                                    <option value="2">5to Grado Primaria</option>
                                    <option value="3">6to Grado Primaria</option>
                                    <option value="4">1er Grado Secundaria</option>
                                    <option value="5">2do Grado Secundaria</option>
                                    <option value="6">3er Grado Secundaria</option>
                                    <option value="7">4to Grado Secundaria</option>
                                    <option value="8">5to Grado Secundaria</option>
                                </select>
                            </div>
                            
                            <div style="grid-column: 2;">
                                <label style="display: block; margin-bottom: 5px; color: #333; font-weight: 500;">Sección *</label>
                                <select id="est_seccion" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                                    <option value="">Seleccionar...</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                </select>
                            </div>
                            
                            <div style="grid-column: 1 / -1; display: flex; gap: 10px; margin-top: 10px;">
                                <button type="button" onclick="guardarEstudiante()" style="flex: 1; padding: 12px; background: #2ecc71; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                    Guardar
                                </button>
                                <button type="button" onclick="cerrarModalEstudiante()" style="flex: 1; padding: 12px; background: #95a5a6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
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
            
            // Colores según tipo de usuario
            let tipoColor, tipoTexto;
            switch(usuario.tipo) {
                case 'docente':
                    tipoColor = '#3498db';
                    tipoTexto = 'Docente';
                    break;
                case 'padre':
                    tipoColor = '#2ecc71';
                    tipoTexto = 'Padre';
                    break;
                case 'administrador':
                    tipoColor = '#e74c3c';
                    tipoTexto = 'Admin';
                    break;
                default:
                    tipoColor = '#7f8c8d';
                    tipoTexto = usuario.tipo;
            }
            
            html += `
            <tr style="border-bottom: 1px solid #f0f2f5;">
                <td style="padding: 15px; font-weight: 600;">${usuario.username}</td>
                <td style="padding: 15px;">${usuario.nombres} ${usuario.apellidos}</td>
                <td style="padding: 15px;">
                    <span style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; 
                        background: ${tipoColor}; 
                        color: white;">
                        ${tipoTexto}
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
                    <button onclick="desactivarUsuario(${usuario.id})" style="padding: 6px 12px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Desactivar/Activar
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
            document.getElementById('password').setAttribute('required', 'required');
            document.getElementById('modalUsuario').style.display = 'flex';
        }
        
        function cerrarModal() {
            document.getElementById('modalUsuario').style.display = 'none';
            document.getElementById('password').setAttribute('required', 'required');
        }
        
        async function guardarUsuario() {
            const usuarioId = document.getElementById('usuarioId').value;
            const esEdicion = usuarioId !== '';
            
            const formData = {
                usuarioId: usuarioId,
                tipo: document.getElementById('tipoUsuario').value,
                username: document.getElementById('username').value,
                nombres: document.getElementById('nombres').value,
                apellidos: document.getElementById('apellidos').value,
                email: document.getElementById('email').value,
                dni: document.getElementById('dni').value,
                telefono: document.getElementById('telefono').value
            };
            
            // Solo incluir password si se está creando o si se proporcionó uno nuevo
            const password = document.getElementById('password').value;
            if (!esEdicion || password) {
                formData.password = password;
            }
            
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
                    
                    // Actualizar estadísticas si es necesario
                    if (window.location.hash === '#dashboard' || document.getElementById('dashboard').classList.contains('active')) {
                        // Recargar estadísticas
                        location.reload();
                    }
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error de conexión');
            }
        }
        
        async function editarUsuario(usuarioId) {
            try {
                // Obtener datos del usuario
                const response = await fetch(`../../api/administrador/obtener-usuario.php?id=${usuarioId}`);
                const data = await response.json();
                
                if (data.success) {
                    const usuario = data.usuario;
                    
                    // Llenar el formulario con los datos del usuario
                    document.getElementById('modalTitulo').textContent = 'Editar Usuario';
                    document.getElementById('usuarioId').value = usuario.id;
                    document.getElementById('tipoUsuario').value = usuario.tipo;
                    document.getElementById('username').value = usuario.username;
                    document.getElementById('password').value = ''; // Dejar vacío para no cambiar
                    document.getElementById('nombres').value = usuario.nombres;
                    document.getElementById('apellidos').value = usuario.apellidos;
                    document.getElementById('email').value = usuario.email || '';
                    document.getElementById('dni').value = usuario.dni || '';
                    document.getElementById('telefono').value = usuario.telefono || '';
                    
                    // Hacer que el campo de contraseña no sea obligatorio en edición
                    document.getElementById('password').removeAttribute('required');
                    
                    // Mostrar el modal
                    document.getElementById('modalUsuario').style.display = 'flex';
                } else {
                    alert('❌ Error al cargar datos del usuario');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error de conexión al cargar usuario');
            }
        }
        
        async function desactivarUsuario(usuarioId) {
            const accion = confirm('¿Estás seguro de que quieres desactivar este usuario? El usuario no podrá acceder al sistema pero se mantendrán sus datos.');
            
            if (!accion) {
                return;
            }
            
            try {
                const response = await fetch('../../api/administrador/eliminar-usuario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: usuarioId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ ' + data.message);
                    cargarUsuarios(); // Recargar tabla
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error de conexión: ' + error.message);
            }
        }

        function agregarEventListeners() {
            // Botones editar
            document.querySelectorAll('.btn-editar').forEach(btn => {
                btn.addEventListener('click', function() {
                    const usuarioId = this.getAttribute('data-id');
                    editarUsuario(usuarioId);
                });
            });
            
            // Botones desactivar/activar
            document.querySelectorAll('.btn-desactivar').forEach(btn => {
                btn.addEventListener('click', function() {
                    const usuarioId = this.getAttribute('data-id');
                    const estaActivo = this.getAttribute('data-activo') === '1';
                    desactivarUsuario(usuarioId);
                });
            });
        }

        // Funciones para gestión de estudiantes
        async function cargarEstudiantes() {
            try {
                const response = await fetch('../../api/administrador/estudiantes.php');
                const data = await response.json();
                
                if (data.success) {
                    actualizarTablaEstudiantes(data.estudiantes);
                } else {
                    alert('Error al cargar estudiantes: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al cargar estudiantes');
            }
        }

        function actualizarTablaEstudiantes(estudiantes) {
            const tbody = document.getElementById('tablaEstudiantes');
            
            if (estudiantes.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="padding: 20px; text-align: center; color: #7f8c8d;">No hay estudiantes registrados</td></tr>';
                return;
            }
            
            let html = '';
            estudiantes.forEach(estudiante => {
                const estadoColor = estudiante.activo ? '#2ecc71' : '#e74c3c';
                const estadoTexto = estudiante.activo ? 'Activo' : 'Inactivo';
                
                html += `
                <tr style="border-bottom: 1px solid #f0f2f5;">
                    <td style="padding: 12px 15px; font-size: 14px;">${estudiante.nombres}</td>
                    <td style="padding: 12px 15px; font-size: 14px;">${estudiante.apellidos}</td>
                    <td style="padding: 12px 15px; font-size: 14px;">${estudiante.dni}</td>
                    <td style="padding: 12px 15px; font-size: 14px;">${estudiante.grado}° ${estudiante.seccion}</td>
                    <td style="padding: 12px 15px;">
                        <span style="background: ${estadoColor}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                            ${estadoTexto}
                        </span>
                    </td>
                    <td style="padding: 12px 15px; text-align: center;">
                        <button onclick="editarEstudiante(${estudiante.id})" style="padding: 6px 12px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                            ✏️ Editar
                        </button>
                        <button onclick="desactivarEstudiante(${estudiante.id})" style="padding: 6px 12px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            🗑️ Eliminar
                        </button>
                    </td>
                </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function mostrarModalCrearEstudiante() {
            document.getElementById('modalTituloEstudiante').textContent = 'Crear Estudiante';
            document.getElementById('formEstudiante').reset();
            document.getElementById('estudianteId').value = '';
            document.getElementById('modalEstudiante').style.display = 'flex';
        }

        function cerrarModalEstudiante() {
            document.getElementById('modalEstudiante').style.display = 'none';
        }

        async function guardarEstudiante() {
            const estudianteId = document.getElementById('estudianteId').value;
            const esEdicion = estudianteId !== '';
            
            const formData = {
                nombres: document.getElementById('est_nombres').value,
                apellidos: document.getElementById('est_apellidos').value,
                dni: document.getElementById('est_dni').value,
                grado: document.getElementById('est_grado').value,
            };
            
            if (esEdicion) {
                formData.estudianteId = estudianteId;
            }
            
            try {
                const response = await fetch('../../api/administrador/guardar-estudiante.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    cerrarModalEstudiante();
                    cargarEstudiantes();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al guardar estudiante');
            }
        }

        async function editarEstudiante(estudianteId) {
            try {
                // Aquí deberías cargar los datos del estudiante
                // Por ahora, se abre el modal en blanco para editar
                document.getElementById('modalTituloEstudiante').textContent = 'Editar Estudiante';
                document.getElementById('estudianteId').value = estudianteId;
                document.getElementById('modalEstudiante').style.display = 'flex';
            } catch (error) {
                console.error('Error:', error);
                alert('Error al cargar estudiante');
            }
        }

        async function desactivarEstudiante(estudianteId) {
            if (!confirm('¿Estás seguro de que deseas eliminar este estudiante?')) {
                return;
            }
            
            try {
                const response = await fetch('../../api/administrador/eliminar-estudiante.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: estudianteId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    cargarEstudiantes();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al eliminar estudiante');
            }
        }

        // Agregar a la función loadModule
        if (moduleId === 'gestion-estudiantes') {
            cargarEstudiantes();
        }
        
        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar datos iniciales del dashboard
            cargarUsuarios();
        });
    </script>
</body>
</html>