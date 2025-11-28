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
    
} catch (Exception $e) {
    // En caso de error, usar ceros
    $stats = [
        'total_usuarios' => 0,
        'total_docentes' => 0, 
        'total_padres' => 0,
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
                <!-- Nuevo: GESTIÓN DE GRADOS -->
                <div id="gestion-grados" class="module-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: #2c3e50;">Gestión de Grados</h3>

                    </div>

                    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; text-align: left;">
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Grado</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Tutor</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaGrados">
                                <tr>
                                    <td colspan="4" style="padding: 20px; text-align: center; color: #7f8c8d;">
                                        Cargando grados...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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


    <div id="modalGrado" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 500px; max-width: 90%;">
            <h3 id="modalTituloGrado">Editar Grado: <span id="gradoNombreDisplay"></span></h3>
            <form id="formGrado">
                <input type="hidden" id="gradoId">
                <input type="hidden" id="gradoNombreCompleto">
                
                <div class="form-group">
                    <label>Tutor (Docente)</label>
                    <select id="selectTutor" required>
                        <option value="">Cargando docentes...</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn-login" onclick="guardarTutorGrado()">💾 Guardar</button>
                    <button type="button" class="btn-logout" onclick="cerrarModalGrado()">❌ Cancelar</button>
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
                'gestion-grados': 'Gestión de Grados',
                'reportes': 'Reportes'
            };
            document.getElementById('moduleTitle').textContent = titles[moduleId] || 'Panel Admin';
            
            // Cargar datos específicos del módulo
            if (moduleId === 'gestion-usuarios') {
                cargarUsuarios();
            }
            if (moduleId === 'gestion-grados') {
                cargarGrados();
                cargarDocentesParaSelect();
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

        // Gestión de Grados
       async function cargarGrados() {
            try {
                const response = await fetch('../../api/administrador/grados.php');
                const data = await response.json();
                if (data.success) {
                    actualizarTablaGrados(data.grados);
                } else {
                    // Colspan se establece en 3 (Grado, Tutor, Acciones)
                    document.getElementById('tablaGrados').innerHTML =
                        '<tr><td colspan="3" style="padding: 20px; text-align: center; color: #e74c3c;">Error al cargar grados</td></tr>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('tablaGrados').innerHTML =
                    '<tr><td colspan="3" style="padding: 20px; text-align: center; color: #e74c3c;">Error de conexión</td></tr>';
            }
        }

        function actualizarTablaGrados(grados) {
            const tbody = document.getElementById('tablaGrados');

            // Actualizar thead para reflejar solo las columnas necesarias
            const thead = document.querySelector('#gestion-grados table thead tr');
            thead.innerHTML = `
                <th style="padding: 15px; text-align: left;">Grado/Sección</th>
                <th style="padding: 15px; text-align: left;">Tutor Asignado</th>
                <th style="padding: 15px; text-align: left;">Acciones</th>
            `;

            if (!grados || grados.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="padding: 20px; text-align: center; color: #7f8c8d;">No hay grados registrados</td></tr>';
                return;
            }

            let html = '';
            grados.forEach(g => {
                // CORRECCIÓN DE DISPLAY: Concatenar correctamente Nivel, Nombre y Sección
                const displayNombre = `${g.nivel} ${g.nombre} - ${g.seccion}`; 
                const tutorNombre = g.tutor_nombres ? `${g.tutor_nombres} ${g.tutor_apellidos}` : 'Sin tutor';

                html += `
                <tr style="border-bottom: 1px solid #f0f2f5;">
                    <td style="padding: 15px; font-weight: 600;">${displayNombre}</td>
                    <td style="padding: 15px;">${tutorNombre}</td>
                    <td style="padding: 15px;">
                        <button onclick="editarGrado(${g.id})" style="padding: 6px 12px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            ✏️ Asignar/Editar Tutor
                        </button>
                    </td>
                </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function cerrarModalGrado() {
            document.getElementById('modalGrado').style.display = 'none';
        }

        async function guardarTutorGrado() { 
            const gradoId = document.getElementById('gradoId').value;
            // Si el valor es "0" (Sin tutor), el backend lo mapeará a NULL
            const tutorId = document.getElementById('selectTutor').value; 
            
            if (!gradoId) {
                alert('Faltan datos del grado.');
                return;
            }

            // Payload solo incluye 'tutor_id'
            const payload = { id: gradoId, tutor_id: tutorId };

            try {
                const response = await fetch('../../api/administrador/actualizar-grado.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (data.success) {
                    alert('✅ ' + data.message);
                    cerrarModalGrado();
                    cargarGrados();
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error de conexión al guardar el tutor del grado');
            }
        }

        // Función principal para abrir el modal de edición
        async function editarGrado(id) {
            try {
                // 1. Obtener los datos del grado actual usando el ID
                const response = await fetch(`../../api/administrador/obtener-grado.php?id=${id}`);
                const data = await response.json();

                if (data.success && data.grado) {
                    const grado = data.grado;
                    
                    // 2. Llenar campos de ID y nombres de display en el modal
                    document.getElementById('gradoId').value = grado.id;
                    // Corrección del display: usa grado.nombre (ej: 1ro)
                    const displayNombre = `${grado.nivel} ${grado.nombre} - ${grado.seccion}`;
                    document.getElementById('gradoNombreDisplay').textContent = displayNombre;
                    document.getElementById('gradoNombreCompleto').value = displayNombre; 
                    
                    // 3. REMOVIDO: Se elimina la asignación de estado activo

                    // 4. Cargar los docentes disponibles y preseleccionar el tutor actual
                    await cargarDocentesParaSelect(grado.tutor_id); 

                    // 5. Mostrar el modal de edición
                    document.getElementById('modalGrado').style.display = 'flex';
                } else {
                    alert('❌ Error al obtener datos del grado: ' + (data.message || 'Desconocido'));
                }
            } catch (error) {
                console.error('Error en editarGrado:', error);
                alert('❌ Error de conexión al cargar datos del grado.');
            }
        }

        // Cargar lista de docentes para asignar como tutores
        async function cargarDocentesParaSelect(selectIdToChoose = null) {
            try {
                const response = await fetch('../../api/administrador/docentes.php');
                const data = await response.json();
                const select = document.getElementById('selectTutor');
                if (data.success && Array.isArray(data.docentes)) {
                    let options = '<option value="0">--- Sin Tutor Asignado ---</option>'; // Opción para asignar NULL
                    data.docentes.forEach(d => {
                        const selected = selectIdToChoose && selectIdToChoose == d.id ? 'selected' : '';
                        options += `<option value="${d.id}" ${selected}>${d.nombres} ${d.apellidos} (${d.username})</option>`;
                    });
                    select.innerHTML = options;
                    
                    if (selectIdToChoose === 0 || selectIdToChoose === null) {
                        select.value = '0';
                    } else if (selectIdToChoose) {
                        select.value = selectIdToChoose;
                    }
                } else {
                    select.innerHTML = '<option value="0">No hay docentes (o error)</option>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('selectTutor').innerHTML = '<option value="0">Error al cargar docentes</option>';
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
        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar datos iniciales del dashboard
            cargarUsuarios();
        });
    </script>
</body>
</html>