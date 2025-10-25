<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'docente') {
    header('Location: ../../index.html');
    exit;
}

require_once '../../includes/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Obtener datos completos del docente
    $stmt = $conn->prepare("
        SELECT u.dni, u.telefono, u.email, d.especialidad 
        FROM usuarios u 
        LEFT JOIN docentes d ON u.id = d.usuario_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $docente_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener grados a cargo del docente
    $stmt = $conn->prepare("
        SELECT g.nombre, g.seccion 
        FROM grados g 
        WHERE g.tutor_id = (
            SELECT id FROM docentes WHERE usuario_id = ?
        )
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $grados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear grados para mostrar
    $grados_texto = '';
    if ($grados) {
        $grados_array = array_map(function($grado) {
            return $grado['nombre'] . ' ' . $grado['seccion'];
        }, $grados);
        $grados_texto = implode(', ', $grados_array);
    } else {
        $grados_texto = 'No asignado';
    }
    
    // Obtener total de estudiantes a cargo
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM estudiantes e 
        WHERE e.grado_id IN (
            SELECT id FROM grados WHERE tutor_id = (
                SELECT id FROM docentes WHERE usuario_id = ?
            )
        )
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total_estudiantes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    // En caso de error, usar datos por defecto
    $docente_data = [
        'dni' => 'No disponible',
        'telefono' => 'No disponible', 
        'email' => $_SESSION['email'] ?? 'No disponible',
        'especialidad' => 'No disponible'
    ];
    $grados_texto = 'No disponible';
    $total_estudiantes = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Docente - I.E Juan Pablo Vizcardo y Guzmán</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <!-- Aplicación principal -->
    <div class="main-app active" id="mainApp">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>📚 Portal Educativo</h3>
                <p>I.E Juan Pablo Vizcardo y Guzmán</p>
            </div>
            
            <div class="sidebar-nav" id="sidebarNav">
                <div class="nav-item active" onclick="loadModule('dashboard', this)">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </div>
                <div class="nav-item" onclick="loadModule('mis-estudiantes', this)">
                    <span class="nav-icon">👥</span>
                    <span>Mis Estudiantes</span>
                </div>
                <div class="nav-item" onclick="loadModule('tareas', this)">
                    <span class="nav-icon">📝</span>
                    <span>Tareas</span>
                </div>
                <div class="nav-item" onclick="loadModule('crear-tarea', this)">
                    <span class="nav-icon">➕</span>
                    <span>Crear Tarea</span>
                </div>
                <div class="nav-item" onclick="loadModule('calendario', this)">
                    <span class="nav-icon">📅</span>
                    <span>Calendario</span>
                </div>
                <div class="nav-item" onclick="loadModule('comunicados', this)">
                    <span class="nav-icon">📢</span>
                    <span>Comunicados</span>
                </div>
                <div class="nav-item" onclick="loadModule('enviar-comunicado', this)">
                    <span class="nav-icon">📨</span>
                    <span>Enviar Comunicado</span>
                </div>
                <div class="nav-item" onclick="loadModule('perfil', this)">
                    <span class="nav-icon">👤</span>
                    <span>Mi Perfil</span>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">👩‍🏫</div>
                    <div class="user-details">
                        <div class="user-name" id="userName"><?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></div>
                        <div class="user-role" id="userRole">Docente</div>
                    </div>
                </div>
                <button class="btn-logout" onclick="handleLogout()">🚪 Cerrar Sesión</button>
            </div>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <div>
                    <h2 id="moduleTitle">Dashboard</h2>
                    <div class="breadcrumb" id="breadcrumb">Docente activo - <?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></div>
                </div>
            </div>
            
            <div class="content-area">
                <!-- DASHBOARD -->
                <div id="dashboard" class="module-content active">
                    <div class="stats-grid">
                        <div class="stat-card warning">
                            <div class="stat-icon">⏰</div>
                            <div class="stat-number">3</div>
                            <div class="stat-label">Tareas Pendientes</div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-number">12</div>
                            <div class="stat-label">Tareas Completadas</div>
                        </div>
                        
                        <div class="stat-card purple">
                            <div class="stat-icon">📆</div>
                            <div class="stat-number">2</div>
                            <div class="stat-label">Días - Próximo Evento</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">📨</div>
                            <div class="stat-number">1</div>
                            <div class="stat-label">Mensajes Nuevos</div>
                        </div>
                    </div>
                    
                    <h3 style="color: #2c3e50; margin-bottom: 20px; font-size: 20px;">📌 Tareas Recientes</h3>
                    <div class="task-list">
                        <div class="task-item pending">
                            <div class="task-info">
                                <h3>📐 Matemática - Suma y Resta</h3>
                                <p>Resolver los ejercicios de la página 45 del libro</p>
                                <div class="task-meta">📅 Vence: 12 Oct 2025 | 👨‍🏫 Prof. María García</div>
                            </div>
                            <span class="task-status status-pending">PENDIENTE</span>
                        </div>
                        
                        <div class="task-item pending">
                            <div class="task-info">
                                <h3>📖 Comunicación - Lectura</h3>
                                <p>Leer el cuento "El león y el ratón" y responder preguntas</p>
                                <div class="task-meta">📅 Vence: 13 Oct 2025 | 👨‍🏫 Prof. Carlos Ramos</div>
                            </div>
                            <span class="task-status status-pending">PENDIENTE</span>
                        </div>
                    </div>
                </div>
                
                <!-- MIS ESTUDIANTES -->
                <div id="mis-estudiantes" class="module-content">
                    <div class="stats-grid" style="margin-bottom: 25px;">
                        <div class="stat-card">
                            <div class="stat-icon">👥</div>
                            <div class="stat-number">28</div>
                            <div class="stat-label">Total Estudiantes</div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-icon">✅</div>
                            <div class="stat-number">24</div>
                            <div class="stat-label">Tareas al día</div>
                        </div>
                        <div class="stat-card warning">
                            <div class="stat-icon">⚠️</div>
                            <div class="stat-number">4</div>
                            <div class="stat-label">Con tareas atrasadas</div>
                        </div>
                    </div>
                    
                    <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Lista de Estudiantes - 3ro A</h3>
                    
                    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; text-align: left;">
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">#</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">Estudiante</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">Tareas Completadas</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">Tareas Pendientes</th>
                                    <th style="padding: 15px; border-bottom: 2px solid #e9ecef; font-weight: 600;">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom: 1px solid #f0f2f5;">
                                    <td style="padding: 15px;">1</td>
                                    <td style="padding: 15px; font-weight: 600;">Ana María González</td>
                                    <td style="padding: 15px;">15</td>
                                    <td style="padding: 15px;">0</td>
                                    <td style="padding: 15px;"><span style="background: #d4edda; color: #155724; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 600;">AL DÍA</span></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f0f2f5;">
                                    <td style="padding: 15px;">2</td>
                                    <td style="padding: 15px; font-weight: 600;">Carlos Ramírez Torres</td>
                                    <td style="padding: 15px;">14</td>
                                    <td style="padding: 15px;">1</td>
                                    <td style="padding: 15px;"><span style="background: #d4edda; color: #155724; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 600;">AL DÍA</span></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f0f2f5;">
                                    <td style="padding: 15px;">3</td>
                                    <td style="padding: 15px; font-weight: 600;">María Pérez López</td>
                                    <td style="padding: 15px;">12</td>
                                    <td style="padding: 15px;">3</td>
                                    <td style="padding: 15px;"><span style="background: #fff3cd; color: #856404; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: 600;">ATRASADO</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- TAREAS -->
                <div id="tareas" class="module-content">
                    <div style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap;">
                        <button class="calendar-btn" onclick="filtrarTareas('todas')">Todas</button>
                        <button class="calendar-btn" style="background: #f39c12;" onclick="filtrarTareas('pendientes')">Pendientes</button>
                        <button class="calendar-btn" style="background: #2ecc71;" onclick="filtrarTareas('completadas')">Completadas</button>
                    </div>
                    
                    <div class="task-list">
                        <div class="task-item pending">
                            <div class="task-info">
                                <h3>📐 Matemática - Geometría</h3>
                                <p>Dibujar 5 figuras geométricas y escribir sus nombres</p>
                                <div class="task-meta">📅 Vence: 12 Oct 2025 | 👨‍🏫 Prof. María García | 📝 3ro A</div>
                            </div>
                            <span class="task-status status-pending">PENDIENTE</span>
                        </div>
                        
                        <div class="task-item pending">
                            <div class="task-info">
                                <h3>📖 Comunicación - Comprensión Lectora</h3>
                                <p>Leer "El patito feo" y responder preguntas de la página 20</p>
                                <div class="task-meta">📅 Vence: 13 Oct 2025 | 👨‍🏫 Prof. Carlos Ramos | 📝 3ro A</div>
                            </div>
                            <span class="task-status status-pending">PENDIENTE</span>
                        </div>
                        
                        <div class="task-item completed">
                            <div class="task-info">
                                <h3>🔬 Ciencia - Los Animales</h3>
                                <p>Clasificar 10 animales según su alimentación</p>
                                <div class="task-meta">📅 Completada: 08 Oct 2025 | 👨‍🏫 Prof. Luis Torres | 📝 3ro A</div>
                            </div>
                            <span class="task-status status-completed">COMPLETADA</span>
                        </div>
                    </div>
                </div>
                
                <!-- CREAR TAREA -->
                <div id="crear-tarea" class="module-content">
                    <div class="profile-card" style="max-width: 800px;">
                        <h3 style="color: #2c3e50; margin-bottom: 25px;">➕ Crear Nueva Tarea</h3>
                        <form id="formCrearTarea">
                            <div class="form-group">
                                <label>Grado y Sección</label>
                                <select id="gradoTarea" required>
                                    <option value="">Seleccionar grado...</option>
                                    <option value="1">1ro A - Primaria</option>
                                    <option value="2">1ro B - Primaria</option>
                                    <option value="3">2do A - Primaria</option>
                                    <option value="4">2do B - Primaria</option>
                                    <option value="5">3ro A - Primaria</option>
                                    <option value="6">3ro B - Primaria</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Materia/Curso</label>
                                <select id="materiaTarea" required>
                                    <option value="">Seleccionar materia...</option>
                                    <option value="1">Matemática</option>
                                    <option value="2">Comunicación</option>
                                    <option value="3">Ciencia y Tecnología</option>
                                    <option value="4">Personal Social</option>
                                    <option value="5">Arte y Cultura</option>
                                    <option value="6">Educación Física</option>
                                    <option value="7">Inglés</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Título de la Tarea</label>
                                <input type="text" id="tituloTarea" placeholder="Ej: Suma y resta de números naturales" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea id="descripcionTarea" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; min-height: 120px; font-family: inherit;" placeholder="Describe las instrucciones de la tarea..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Fecha de Entrega</label>
                                <input type="date" id="fechaTarea" required>
                            </div>
                            
                            <button type="button" class="btn-login" style="margin-top: 20px;" onclick="crearTarea()">📤 Publicar Tarea</button>
                        </form>
                    </div>
                </div>
                
                <!-- CALENDARIO -->
                <div id="calendario" class="module-content">
                    <div class="calendar-wrapper">
                        <div class="calendar-box">
                            <div class="calendar-header">
                                <h3 id="mesActual">Octubre 2025</h3>
                                <div class="calendar-nav">
                                    <button class="calendar-btn" onclick="cambiarMes(-1)">◄</button>
                                    <button class="calendar-btn" onclick="cambiarMes(1)">►</button>
                                </div>
                            </div>
                            
                            <div class="calendar-grid" id="calendarioGrid">
                                <!-- Generado por JavaScript -->
                            </div>
                        </div>
                        
                        <div class="calendar-box">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">📅 Próximos Eventos</h3>
                            <div class="events-list">
                                <div class="event-item">
                                    <div class="event-title">Reunión de Padres</div>
                                    <div class="event-detail">📅 09 Oct 2025 - 3:00 PM</div>
                                    <div class="event-detail">📍 Aula 3ro A</div>
                                </div>
                                
                                <div class="event-item" style="border-left-color: #f39c12;">
                                    <div class="event-title">Examen de Matemática</div>
                                    <div class="event-detail">📅 15 Oct 2025</div>
                                    <div class="event-detail">📚 Unidad 3</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- COMUNICADOS -->
                <div id="comunicados" class="module-content">
                    <div class="message-list">
                        <div class="message-card">
                            <div class="message-header">
                                <div>
                                    <div class="message-sender">🏫 Dirección</div>
                                    <div class="message-date">07 Oct 2025 - 10:30 AM</div>
                                </div>
                                <span class="message-badge">NUEVO</span>
                            </div>
                            <h3 class="message-title">Reunión de Padres de Familia</h3>
                            <p class="message-text">
                                Estimados docentes, les recordamos que el día 09 de octubre a las 3:00 PM 
                                tendremos la reunión bimestral con los padres de familia.
                            </p>
                        </div>
                        
                        <div class="message-card">
                            <div class="message-header">
                                <div>
                                    <div class="message-sender">👨‍🏫 Coordinación Académica</div>
                                    <div class="message-date">05 Oct 2025 - 2:15 PM</div>
                                </div>
                            </div>
                            <h3 class="message-title">Entrega de Planificaciones</h3>
                            <p class="message-text">
                                Docentes, por favor entregar las planificaciones del siguiente bimestre 
                                antes del 15 de octubre en coordinación académica.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- ENVIAR COMUNICADO -->
                <div id="enviar-comunicado" class="module-content">
                    <div class="profile-card" style="max-width: 800px;">
                        <h3 style="color: #2c3e50; margin-bottom: 25px;">📨 Enviar Nuevo Comunicado</h3>
                        <form id="formComunicado">
                            <div class="form-group">
                                <label>Destinatarios</label>
                                <select id="destinatariosComunicado" required>
                                    <option value="">Seleccionar destinatarios...</option>
                                    <option value="1">Todos los Padres de 1ro A</option>
                                    <option value="2">Todos los Padres de 1ro B</option>
                                    <option value="3">Todos los Padres de 2do A</option>
                                    <option value="4">Todos los Padres de 2do B</option>
                                    <option value="5">Todos los Padres de 3ro A</option>
                                    <option value="6">Todos los Padres de 3ro B</option>
                                    <option value="todos">Toda la institución</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Asunto del Comunicado</label>
                                <input type="text" id="asuntoComunicado" placeholder="Ej: Reunión de padres - 3er bimestre" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Mensaje</label>
                                <textarea id="mensajeComunicado" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; min-height: 180px; font-family: inherit;" placeholder="Escribe el contenido del comunicado..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" id="urgenteComunicado">
                                    <span>Marcar como urgente</span>
                                </label>
                            </div>
                            
                            <button type="button" class="btn-login" style="margin-top: 20px;" onclick="enviarComunicado()">📤 Enviar Comunicado</button>
                        </form>
                    </div>
                </div>
                
                <!-- PERFIL -->
                <div id="perfil" class="module-content">
                    <div class="profile-container">
                        <div class="profile-card">
                            <div class="profile-header">
                                <div class="profile-avatar-large">👩‍🏫</div>
                                <div class="profile-info">
                                    <h2 id="nombreDocente"><?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></h2>
                                    <p>Docente</p>
                                </div>
                            </div>
                            
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Información Personal</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">DNI</div>
                                    <div class="info-value" id="dniDocente"><?php echo htmlspecialchars($docente_data['dni'] ?? 'No disponible'); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Correo Electrónico</div>
                                    <div class="info-value" id="emailDocente"><?php echo htmlspecialchars($docente_data['email'] ?? 'No disponible'); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Teléfono</div>
                                    <div class="info-value" id="telefonoDocente"><?php echo htmlspecialchars($docente_data['telefono'] ?? 'No disponible'); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Especialidad</div>
                                    <div class="info-value" id="especialidadDocente"><?php echo htmlspecialchars($docente_data['especialidad'] ?? 'No disponible'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-card">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">🏫 Información Académica</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Grados a Cargo</div>
                                    <div class="info-value" id="gradosDocente"><?php echo htmlspecialchars($grados_texto); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Total Estudiantes</div>
                                    <div class="info-value" id="totalEstudiantes"><?php echo $total_estudiantes; ?> estudiantes</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Turno</div>
                                    <div class="info-value" id="turnoDocente">Mañana</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Años de Servicio</div>
                                    <div class="info-value" id="experienciaDocente">8 años</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                'dashboard': 'Dashboard',
                'mis-estudiantes': 'Mis Estudiantes',
                'tareas': 'Tareas',
                'crear-tarea': 'Crear Nueva Tarea',
                'calendario': 'Calendario',
                'comunicados': 'Comunicados',
                'enviar-comunicado': 'Enviar Comunicado',
                'perfil': 'Mi Perfil'
            };
            document.getElementById('moduleTitle').textContent = titles[moduleId] || 'Portal Docente';
            
            // Si es calendario, generarlo
            if (moduleId === 'calendario') {
                generarCalendario();
            }
        }
        
        // Función de logout
        function handleLogout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = '../../api/auth/logout.php';
            }
        }
        
        // Filtrar tareas
        function filtrarTareas(filtro) {
            const tareas = document.querySelectorAll('#tareas .task-item');
            tareas.forEach(tarea => {
                const esCompletada = tarea.classList.contains('completed');
                const esPendiente = tarea.classList.contains('pending');
                
                switch(filtro) {
                    case 'todas':
                        tarea.style.display = 'flex';
                        break;
                    case 'pendientes':
                        tarea.style.display = esPendiente ? 'flex' : 'none';
                        break;
                    case 'completadas':
                        tarea.style.display = esCompletada ? 'flex' : 'none';
                        break;
                }
            });
        }
        
        // Generar calendario
        function generarCalendario() {
            const calendarioGrid = document.getElementById('calendarioGrid');
            const diasSemana = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
            
            let html = '';
            
            // Encabezados de días
            diasSemana.forEach(dia => {
                html += `<div class="calendar-day-label">${dia}</div>`;
            });
            
            // Días del mes (ejemplo simplificado)
            for (let i = 1; i <= 31; i++) {
                let clase = 'calendar-day';
                if (i === 7) clase += ' today';
                if ([9, 15, 20].includes(i)) clase += ' has-event';
                
                html += `<div class="${clase}" onclick="seleccionarDia(${i})">${i}</div>`;
            }
            
            calendarioGrid.innerHTML = html;
        }
        
        function seleccionarDia(dia) {
            alert(`Día ${dia} seleccionado`);
        }
        
        function cambiarMes(direccion) {
            alert('Funcionalidad de cambio de mes');
        }
        
        function crearTarea() {
            alert('Funcionalidad de crear tarea - Conectar con backend');
        }
        
        function enviarComunicado() {
            alert('Funcionalidad de enviar comunicado - Conectar con backend');
        }
        
        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Asegurar que el dashboard esté activo al inicio
            loadModule('dashboard', document.querySelector('.nav-item.active'));
        });
    </script>
</body>
</html>