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
    $usuario_id = $_SESSION['user_id'];
    
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
        SELECT g.id, g.nombre, g.seccion 
        FROM grados g 
        WHERE g.tutor_id = (
            SELECT id FROM docentes WHERE usuario_id = ?
        )
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $grados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener lista de materias desde la BD
    $stmt = $conn->prepare("SELECT id, nombre FROM materias ORDER BY nombre ASC");
    $stmt->execute();
    $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    
    // Obtener eventos del calendario para el docente
    $stmt = $conn->prepare("
        SELECT titulo, descripcion, fecha_evento, tipo 
        FROM eventos 
        WHERE docente_id = ? OR destinatario = 'todos'
        ORDER BY fecha_evento ASC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 💡 INICIO DE LA MODIFICACIÓN: OBTENER TAREAS Y FUSIONAR CON EVENTOS
    $stmt_docente = $conn->prepare("SELECT id FROM docentes WHERE usuario_id = ?");
    $stmt_docente->execute([$_SESSION['user_id']]);
    $docente_data_id = $stmt_docente->fetch(PDO::FETCH_ASSOC);
    $docente_id = $docente_data_id['id'] ?? 0;

    if ($docente_id) {
        $stmt_tareas = $conn->prepare("
            SELECT 
                t.titulo, 
                t.descripcion, 
                t.fecha_entrega AS fecha_evento, 
                m.nombre AS materia_nombre,
                g.nombre AS grado_nombre,
                g.seccion
            FROM tareas t
            JOIN materias m ON t.materia_id = m.id
            JOIN grados g ON t.grado_id = g.id
            WHERE t.docente_id = ?
            ORDER BY t.fecha_entrega ASC
        ");
        $stmt_tareas->execute([$docente_id]);
        $tareas_calendario = $stmt_tareas->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear y fusionar las tareas con los eventos
        $eventos = array_merge($eventos, array_map(function($tarea) {
            $tarea['titulo'] = "TAREA: " . $tarea['titulo'];
            $tarea['descripcion'] = $tarea['descripcion'] . " (Grado: {$tarea['grado_nombre']} {$tarea['seccion']} - Materia: {$tarea['materia_nombre']})";
            $tarea['tipo'] = 'evento_tarea'; // Tipo específico para tareas
            unset($tarea['materia_nombre'], $tarea['grado_nombre'], $tarea['seccion']);
            return $tarea;
        }, $tareas_calendario));
    }
    // 💡 FIN DE LA MODIFICACIÓN
    
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
    $eventos = [];
}

// Obtener fecha actual para el calendario
$fecha_actual = new DateTime();
$mes_actual = $fecha_actual->format('n');
$ano_actual = $fecha_actual->format('Y');
$dia_actual = $fecha_actual->format('j');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Docente - I.E Juan Pablo Vizcardo y Guzmán</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .calendar-wrapper {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-top: 20px;
        }
        
        .calendar-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between; /* Corregido: de 'between' a 'space-between' */
            align-items: center;
            margin-bottom: 20px;
        }
        
        .calendar-nav {
            display: flex;
            gap: 10px;
        }
        
        .calendar-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .calendar-btn:hover {
            background: #2980b9;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        
        .calendar-day-label {
            text-align: center;
            font-weight: 600;
            color: #7f8c8d;
            padding: 10px;
            font-size: 14px;
        }
        
        .calendar-day {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center; /* Centrar día y puntos */
        }
        
        .calendar-day:hover {
            background: #e3f2fd;
            border-color: #3498db;
        }
        
        .calendar-day.today {
            background: #3498db !important;
            color: white !important;
            border-color: #3498db !important;
            font-weight: bold;
        }
        
        .calendar-day.has-event {
            background: #fff5f5;
        }
        
        /* 💡 NUEVAS CLASES PARA TAREAS */
        .calendar-day.has-task {
            border-color: #3498db; 
            background: #f0f8ff;
        }

        /* Deshabilitar el after genérico para usar los dots en su lugar */
        .calendar-day.has-event::after {
            content: none; 
        }
        
        .calendar-day.inactive {
            background: #f8f9fa;
            color: #bdc3c7;
            cursor: not-allowed;
        }
        
        .calendar-day.inactive:hover {
            background: #f8f9fa;
            border-color: #e9ecef;
        }
        
        /* Contenedor de puntos para múltiples indicadores */
        .dot-container {
            display: flex;
            gap: 4px;
            justify-content: center;
            margin-top: 5px;
        }

        /* Puntos de Evento */
        .event-dot {
            width: 6px;
            height: 6px;
            background: #e74c3c; /* Rojo para evento normal */
            border-radius: 50%;
            display: block;
        }
        
        /* Puntos de Tarea */
        .task-dot {
            width: 6px;
            height: 6px;
            background: #3498db; /* Azul para tarea */
            border-radius: 50%;
            display: block;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .event-item {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.2s ease;
        }
        
        .event-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }
        
        .event-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .event-detail {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .event-urgent {
            border-left-color: #e74c3c;
        }
        
        @media (max-width: 768px) {
            .calendar-wrapper {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-app active" id="mainApp">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>📚 Portal Educativo</h3>
                <p>I.E Juan Pablo Vizcardo y Guzmán</p>
            </div>
            
            <div class="sidebar-nav" id="sidebarNav">
                <div class="nav-item active" onclick="loadModule('tareas', this)">
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
                
                <div id="tareas" class="module-content active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap;">
                        <h3 style="color: #2c3e50; margin: 0;">📝 Mis Tareas Creadas</h3>
                        <button class="btn-login" onclick="cargarTareas()">🔄 Actualizar Lista</button>
                    </div>
                    
                    <div id="loadingTareas" style="text-align: center; padding: 20px; display: none;">
                        <div style="color: #3498db; font-size: 16px;">Cargando tareas...</div>
                    </div>
                    
                    <div id="listaTareasReal" class="task-list">
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            <div style="font-size: 48px; margin-bottom: 10px;">📚</div>
                            <p>No hay tareas creadas aún.</p>
                        </div>
                    </div>
                </div>
                
                <div id="crear-tarea" class="module-content">
                    <div class="profile-card" style="max-width: 800px;">
                        <h3 style="color: #2c3e50; margin-bottom: 25px;">➕ Crear Nueva Tarea</h3>
                        <form id="formCrearTarea">
                            <div class="form-group">
                                <label>Grado y Sección</label>
                                <select id="gradoTarea" required>
                                    <option value="">Seleccionar grado...</option>
                                    <?php if (!empty($grados)): ?>
                                        <?php foreach ($grados as $grado): ?>
                                            <option value="<?php echo htmlspecialchars($grado['id'], ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars($grado['nombre'] . ' ' . $grado['seccion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay grados asignados</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Materia/Curso</label>
                                <select id="materiaTarea" required>
                                    <option value="">Seleccionar materia...</option>
                                    <?php if (!empty($materias)): ?>
                                        <?php foreach ($materias as $materia): ?>
                                            <option value="<?php echo htmlspecialchars($materia['id'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($materia['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay materias disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Título de la Tarea</label>
                                <input type="text" id="tituloTarea" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea id="descripcionTarea" style="width: 100%; padding: 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; min-height: 120px; font-family: inherit;" placeholder="Describe las instrucciones de la tarea..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Fecha de Entrega</label>
                                <input type="date" id="fechaTarea" required>
                            </div>
                            
                            <button id="btnPublicarTarea" type="button" class="btn-login" style="margin-top: 20px;" onclick="crearTarea()">📤 Publicar Tarea</button>
                        </form>
                    </div>
                </div>
                
                <div id="calendario" class="module-content">
                    <div class="calendar-wrapper">
                        <div class="calendar-box">
                            <div class="calendar-header">
                                <h3 id="mesActual"><?php echo date('F Y'); ?></h3>
                                <div class="calendar-nav">
                                    <button class="calendar-btn" onclick="cambiarMes(-1)">◄</button>
                                    <button class="calendar-btn" onclick="cambiarMes(1)">►</button>
                                </div>
                            </div>
                            
                            <div class="calendar-grid" id="calendarioGrid">
                                </div>
                        </div>
                        
                        <div class="calendar-box">
                            <h3 style="color: #2c3e50; margin-bottom: 20px;">📅 Próximos Eventos</h3>
                            <div class="events-list" id="listaEventos">
                                <?php if (!empty($eventos)): ?>
                                    <?php foreach($eventos as $evento): ?>
                                        <?php if (!isset($evento['tipo']) || $evento['tipo'] !== 'evento_tarea'): // Excluir tareas de la lista lateral ?>
                                            <div class="event-item <?php echo $evento['tipo'] === 'urgente' ? 'event-urgent' : ''; ?>">
                                                <div class="event-title"><?php echo htmlspecialchars($evento['titulo']); ?></div>
                                                <div class="event-detail">📅 <?php echo date('d M Y', strtotime($evento['fecha_evento'])); ?></div>
                                                <div class="event-detail">📝 <?php echo htmlspecialchars($evento['descripcion']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="event-item">
                                        <div class="event-title">No hay eventos próximos</div>
                                        <div class="event-detail">No hay eventos programados para los próximos días</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="nav-item" onclick="loadModule('comunicados', this)">
                    <i class="fas fa-bullhorn"></i>
                    <span>Comunicados</span>
                </div>

                <div id="module-comunicados" class="main-module" style="display: none;">
                    <h2><i class="fas fa-bullhorn"></i> Gestión de Comunicados</h2>

                    <div style="margin-bottom: 20px;">
                        <button class="btn btn-secondary" onclick="showComunicadoView('listado')">Ver Listado</button>
                        <button class="btn btn-secondary" onclick="showComunicadoView('enviar')">Enviar Nuevo</button>
                    </div>
                    
                    <div id="comunicados" class="module-content" style="display: block;">
                        <h3 style="color: #2c3e50; margin-bottom: 25px;">Lista de Comunicados Enviados</h3>
                        <div class="message-list">
                            <p style="text-align: center; color: #7f8c8d;">Cargando comunicados...</p>
                        </div>
                    </div>
                    
                    <div id="enviar-comunicado" class="module-content" style="display: none;">
                        <div class="profile-card" style="max-width: 800px;">
                            <h3 style="color: #2c3e50; margin-bottom: 25px;">📨 Enviar Nuevo Comunicado</h3>
                            <form id="formComunicado">
                                <div class="form-group">
                                    <label>Destinatarios</label>
                                    <select id="destinatariosComunicado" required>
                                        <option value="0">Todos mis grados (General)</option>
                                        <?php 
                                        if (!empty($grados)) {
                                            foreach ($grados as $grado) {
                                                // El value es el grado_id (que es el requerido por la API)
                                                echo "<option value='{$grado['id']}'>Todos los Padres de {$grado['nombre']} {$grado['seccion']}</option>";
                                            }
                                        }
                                        ?>
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
                                <div id="comunicado-message" class="alert-message mt-2"></div>
                            </form>
                        </div>
                    </div>
                </div>
                
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
        // Variable global para guardar la carga académica
            let cargaAcademicaGlobal = [];

            // Variables globales para el calendario
            let currentDate = new Date(<?php echo $ano_actual; ?>, <?php echo $mes_actual - 1; ?>, 1);
            // El array eventosCalendario ahora contiene las tareas
            const eventosCalendario = <?php echo json_encode($eventos); ?>;
            
            function showComunicadoView(view) {
                const listado = document.getElementById('comunicados');
                const enviar = document.getElementById('enviar-comunicado');
                
                if (view === 'listado') {
                    listado.style.display = 'block';
                    enviar.style.display = 'none';
                    cargarComunicados(); // Recarga la lista al volver a ella
                } else if (view === 'enviar') {
                    listado.style.display = 'none';
                    enviar.style.display = 'block';
                }
            }

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
                    'tareas': 'Tareas',
                    'crear-tarea': 'Crear Nueva Tarea',
                    'calendario': 'Calendario',
                    'comunicados': 'Comunicados',
                    'enviar-comunicado': 'Enviar Comunicado',
                    'perfil': 'Mi Perfil'
                };
                document.getElementById('moduleTitle').textContent = titles[moduleId] || 'Portal Docente';
                
                // Si es calendario, generarlo (con la nueva lógica de tareas)
                if (moduleId === 'calendario') {
                    generarCalendario();
                }
                
                // Si es tareas, cargarlas
                if (moduleId === 'tareas') {
                    cargarTareas();
                }

                document.querySelectorAll('.main-module').forEach(el => el.style.display = 'none');
                document.getElementById(`module-${moduleId}`).style.display = 'block';

                document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
                element.classList.add('active');

                if (element) {
                    element.classList.add('active');
                }

                if (moduleName === 'comunicados') {
                    showComunicadoView('listado'); // Muestra la vista de listado y carga los datos
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

            // 💡 MODIFICACIÓN: Generar calendario mejorado para incluir tareas
            function generarCalendario() {
                const calendarioGrid = document.getElementById('calendarioGrid');
                const diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
                const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                const today = new Date();
                today.setHours(0, 0, 0, 0); // Limpiar la hora de 'hoy'
                
                // Actualizar título del mes
                document.getElementById('mesActual').textContent = `${monthNames[month]} ${year}`;
                
                let html = '';
                
                // Encabezados de días
                diasSemana.forEach(dia => {
                    html += `<div class="calendar-day-label">${dia}</div>`;
                });
                
                // Primer día del mes
                const firstDay = new Date(year, month, 1);
                // Días en el mes
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                // Día de la semana del primer día (0 = Domingo, 1 = Lunes, ...)
                const firstDayOfWeek = (firstDay.getDay() + 6) % 7; // Ajuste para que empiece en Lunes
                
                // Días vacíos al inicio
                for (let i = 0; i < firstDayOfWeek; i++) {
                    html += `<div class="calendar-day inactive"></div>`;
                }
                
                // Días del mes
                for (let day = 1; day <= daysInMonth; day++) {
                    const currentDay = new Date(year, month, day);
                    currentDay.setHours(0, 0, 0, 0); // Limpiar la hora del día actual en el bucle
                    let clase = 'calendar-day';
                    
                    // Verificar si es hoy
                    if (currentDay.getTime() === today.getTime()) {
                        clase += ' today';
                    }
                    
                    // Buscar eventos para este día
                    const eventosDia = eventosCalendario.filter(evento => { 
                        const eventoDate = new Date(evento.fecha_evento);
                        eventoDate.setHours(0, 0, 0, 0); 
                        return eventoDate.getTime() === currentDay.getTime();
                    });
                    
                    const tieneEventosNormales = eventosDia.some(e => e.tipo !== 'evento_tarea');
                    const tieneTareas = eventosDia.some(e => e.tipo === 'evento_tarea');
                    
                    // Aplicar clases
                    if (tieneTareas) {
                        clase += ' has-task';
                    }
                    if (tieneEventosNormales) {
                        clase += ' has-event'; 
                    }
                    
                    // Generar puntos de evento
                    let dotsHtml = '';
                    if (tieneTareas) {
                        dotsHtml += '<div class="task-dot"></div>';
                    }
                    if (tieneEventosNormales) {
                        dotsHtml += '<div class="event-dot"></div>';
                    }
                    
                    html += `<div class="${clase}" onclick="seleccionarDia(${day}, ${month}, ${year})">
                        ${day}
                        <div class="dot-container">
                            ${dotsHtml}
                        </div>
                    </div>`;
                }
                
                calendarioGrid.innerHTML = html;
            }

            // 💡 MODIFICACIÓN: Función para seleccionar día y mostrar tareas
            function seleccionarDia(dia, mes, ano) {
                const fecha = new Date(ano, mes, dia);
                fecha.setHours(0, 0, 0, 0); // Normalizar la hora
                const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const fechaFormateada = fecha.toLocaleDateString('es-ES', opciones);
                
                // Buscar eventos para este día
                const eventosDia = eventosCalendario.filter(evento => {
                    const eventoDate = new Date(evento.fecha_evento);
                    eventoDate.setHours(0, 0, 0, 0); 
                    
                    return eventoDate.getTime() === fecha.getTime();
                });
                
                let mensaje = `📅 ${fechaFormateada}`;
                
                if (eventosDia.length > 0) {
                    mensaje += `\n\n📋 Programación para este día:\n`;
                    eventosDia.forEach((evento, index) => {
                        const etiqueta = evento.tipo === 'evento_tarea' ? '✅ TAREA (Entrega)' : 
                                         (evento.tipo === 'urgente' ? '🚨 EVENTO URGENTE' : '📢 EVENTO');
                        mensaje += `\n${index + 1}. ${etiqueta}: ${evento.titulo}\n   📝 ${evento.descripcion}\n`;
                    });
                } else {
                    mensaje += `\n\nNo hay eventos programados para este día.`;
                }
                
                alert(mensaje);
            }

            function cambiarMes(direccion) {
                currentDate.setMonth(currentDate.getMonth() + direccion);
                generarCalendario();
            }

            // Función para crear tarea desde el formulario visible
            async function crearTarea() {
                const form = document.getElementById('formCrearTarea');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                const gradoId = document.getElementById('gradoTarea').value;
                const materiaId = document.getElementById('materiaTarea').value;
                const titulo = document.getElementById('tituloTarea').value;
                const descripcion = document.getElementById('descripcionTarea').value;
                const fechaEntrega = document.getElementById('fechaTarea').value;

                // Validar que se hayan seleccionado grado y materia
                if (!gradoId || !materiaId) {
                    alert('Por favor seleccione un grado y una materia');
                    return;
                }

                // Validar fecha (no puede ser en el pasado)
                const today = new Date().toISOString().split('T')[0];
                if (fechaEntrega < today) {
                    alert('La fecha de entrega no puede ser en el pasado');
                    return;
                }

                const payload = {
                    titulo: titulo,
                    descripcion: descripcion,
                    grado_id: parseInt(gradoId),
                    materia_id: parseInt(materiaId),
                    fecha_entrega: fechaEntrega
                };

                console.log('Enviando datos:', payload); // Para debug

                const btn = document.getElementById('btnPublicarTarea');
                
                try {
                    btn.disabled = true;
                    btn.textContent = 'Publicando...';

                    const response = await fetch('../../api/docente/guardar-tarea.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        alert('✅ Tarea creada exitosamente');
                        form.reset();
                        
                        // Recargar la página de tareas si estamos en esa vista
                        if (document.getElementById('tareas').classList.contains('active')) {
                            cargarTareas();
                        }
                        
                        // Opcional: Recargar el calendario para que la tarea aparezca inmediatamente (requiere recargar la página para el PHP)
                        // Para evitar recargar la página, se debería usar AJAX para obtener los eventos/tareas y actualizar `eventosCalendario`
                        // Si se usa este código, se asume que el usuario recargará la página o cambiará de módulo para ver la tarea.

                    } else {
                        alert('❌ Error: ' + (data.message || 'Error desconocido'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('❌ Error de conexión con el servidor');
                } finally {
                    btn.disabled = false;
                    btn.textContent = '📤 Publicar Tarea';
                }
            }

            // Función para cargar tareas desde la base de datos
            async function cargarTareas() {
                const loadingDiv = document.getElementById('loadingTareas');
                const listaTareas = document.getElementById('listaTareasReal');
                
                loadingDiv.style.display = 'block';
                listaTareas.innerHTML = '<div style="text-align: center; padding: 20px; color: #7f8c8d;">Cargando tareas...</div>';
                
                try {
                    const response = await fetch('../../api/docente/obtener-tareas.php');
                    const data = await response.json();
                    
                    if (data.success) {
                        actualizarListaTareas(data.tareas);
                    } else {
                        listaTareas.innerHTML = `
                            <div style="text-align: center; padding: 20px; color: #e74c3c;">
                                <div style="font-size: 48px; margin-bottom: 10px;">❌</div>
                                <p>Error al cargar las tareas: ${data.message || 'Error desconocido'}</p>
                                <button class="calendar-btn" onclick="cargarTareas()">🔄 Reintentar</button>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    listaTareas.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #e74c3c;">
                            <div style="font-size: 48px; margin-bottom: 10px;">🔌</div>
                            <p>Error de conexión con el servidor</p>
                            <button class="calendar-btn" onclick="cargarTareas()">🔄 Reintentar</button>
                        </div>
                    `;
                } finally {
                    loadingDiv.style.display = 'none';
                }
            }

            // Función para actualizar la lista de tareas en la interfaz
            function actualizarListaTareas(tareas) {
                const listaTareas = document.getElementById('listaTareasReal');
                
                if (!tareas || tareas.length === 0) {
                    listaTareas.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                            <div style="font-size: 48px; margin-bottom: 10px;">📚</div>
                            <p>No hay tareas creadas aún.</p>
                            <button class="calendar-btn" onclick="loadModule('crear-tarea', document.querySelector('[onclick*=\"crear-tarea\"]'))">
                                ➕ Crear Mi Primera Tarea
                            </button>
                        </div>
                    `;
                    return;
                }
                
                let html = '';
                tareas.forEach(tarea => {
                    const fechaCreacion = new Date(tarea.fecha_creacion).toLocaleDateString('es-ES');
                    const fechaEntrega = new Date(tarea.fecha_entrega).toLocaleDateString('es-ES');
                    const hoy = new Date();
                    hoy.setHours(0, 0, 0, 0); // Normalizar hoy
                    const fechaEntregaObj = new Date(tarea.fecha_entrega);
                    fechaEntregaObj.setHours(0, 0, 0, 0); // Normalizar entrega
                    
                    const oneDay = 1000 * 60 * 60 * 24;
                    // Calcular la diferencia en días
                    const diffTime = fechaEntregaObj.getTime() - hoy.getTime();
                    const diasRestantes = Math.ceil(diffTime / oneDay);
                    
                    let estado = 'pending';
                    let textoEstado = 'PENDIENTE';
                    let colorEstado = '#f39c12';
                    
                    if (diasRestantes < 0) {
                        estado = 'expired';
                        textoEstado = 'VENCIDA';
                        colorEstado = '#e74c3c';
                    } else if (diasRestantes === 0) {
                        textoEstado = 'HOY';
                        colorEstado = '#e67e22';
                    } else if (diasRestantes <= 3) {
                        textoEstado = `URGENTE (${diasRestantes}d)`;
                        colorEstado = '#e74c3c';
                    } else {
                        textoEstado = `Faltan ${diasRestantes} días`;
                        colorEstado = '#3498db';
                    }
                    
                    html += `
                    <div class="task-item ${estado}">
                        <div class="task-info">
                            <h3>${tarea.materia_nombre} - ${tarea.titulo}</h3>
                            <p>${tarea.descripcion || 'Sin descripción adicional'}</p>
                            <div class="task-meta">
                                📅 Creada: ${fechaCreacion} | 
                                🎯 Entrega: ${fechaEntrega} | 
                                🏫 ${tarea.grado_nombre} ${tarea.seccion} | 
                                📚 ${tarea.materia_nombre}
                            </div>
                        </div>
                        <span class="task-status" style="background: ${colorEstado}">${textoEstado}</span>
                    </div>
                    `;
                });
                
                listaTareas.innerHTML = html;
            }

            async function enviarComunicado() {
                const titulo = document.getElementById('asuntoComunicado').value.trim();
                const mensaje = document.getElementById('mensajeComunicado').value.trim();
                const grado_id = document.getElementById('destinatariosComunicado').value;
                const urgente = document.getElementById('urgenteComunicado').checked;
                
                let messageElement = document.getElementById('comunicado-message');

                if (!titulo || !mensaje) {
                    messageElement.innerHTML = '<span style="color: #c0392b;">El asunto y el mensaje son obligatorios.</span>';
                    return;
                }

                messageElement.innerHTML = '<span style="color: #2980b9;">Enviando comunicado...</span>';
                
                try {
                    const response = await fetch('/api/docente/guardar-comunicado.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            titulo: titulo,
                            mensaje: mensaje,
                            grado_id: parseInt(grado_id),
                            urgente: urgente ? 1 : 0
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        messageElement.innerHTML = `<span style="color: #27ae60;">${data.message}</span>`;
                        document.getElementById('formComunicado').reset();
                        // Después de enviar, regresa al listado automáticamente
                        setTimeout(() => {
                            showComunicadoView('listado'); 
                        }, 1500);
                    } else {
                        messageElement.innerHTML = `<span style="color: #c0392b;">Error al enviar: ${data.message}</span>`;
                    }

                } catch (error) {
                    console.error('Error:', error);
                    messageElement.innerHTML = '<span style="color: #c0392b;">Error de conexión con el servidor.</span>';
                }
            }

            /**
             * Habilita la funcionalidad de Listar Comunicados Enviados.
             * Conecta con la nueva API: /api/docente/obtener-comunicados.php
             */
            async function cargarComunicados() {
            // Selecciona el contenedor .message-list DENTRO del div id="comunicados"
            const messageListContainer = document.querySelector('#comunicados .message-list');
            messageListContainer.innerHTML = '<p style="text-align: center; color: #7f8c8d;">Cargando lista de comunicados...</p>';

            try {
                const response = await fetch('/api/docente/obtener-comunicados.php');
                const data = await response.json();

                if (!data.success) {
                    messageListContainer.innerHTML = `<p style="color: #c0392b; text-align: center;">Error al cargar: ${data.message}</p>`;
                    return;
                }

                if (data.comunicados.length === 0) {
                    messageListContainer.innerHTML = '<p style="text-align: center; color: #7f8c8d;">No ha enviado comunicados aún.</p>';
                    return;
                }

                let html = '';
                data.comunicados.forEach(comunicado => {
                    // Formatea la fecha de publicación
                    const fechaPublicacion = new Date(comunicado.fecha_publicacion).toLocaleDateString('es-ES', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric', 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });
                    
                    let destinatario = 'Todos mis grados';
                    if (comunicado.grado_nombre) {
                        destinatario = `Padres de ${comunicado.grado_nombre} ${comunicado.seccion}`;
                    }

                    // Etiqueta de Urgente
                    const urgenteTag = comunicado.urgente == 1 
                        ? '<span class="message-badge urgent-badge" style="background-color: #e74c3c; color: white; padding: 5px; border-radius: 4px; font-size: 0.8em; margin-left: 10px;">¡URGENTE!</span>'
                        : '';

                    html += `
                        <div class="message-card">
                            <div class="message-header">
                                <div>
                                    <div class="message-sender">👨‍🏫 ${destinatario}</div>
                                    <div class="message-date">${fechaPublicacion}</div>
                                </div>
                                ${urgenteTag}
                            </div>
                            <h3 class="message-title">${comunicado.titulo}</h3>
                            <p class="message-text">${comunicado.mensaje}</p>
                        </div>
                    `;
                });
                
                messageListContainer.innerHTML = html;

            } catch (error) {
                console.error('Error al obtener comunicados:', error);
                messageListContainer.innerHTML = '<p style="color: #c0392b; text-align: center;">No se pudo conectar con la API de comunicados.</p>';
            }
        }

            // Inicialización al cargar la página
            document.addEventListener('DOMContentLoaded', function() {
                // Cargar módulo de tareas por defecto (se activa en loadModule)
                document.querySelector('.nav-item').classList.add('active'); // Asegurar el primer item activo
                loadModule('tareas', document.querySelector('.nav-item.active'));
                
                // Establecer fecha mínima como hoy para el campo de fecha de tareas
                const fechaInput = document.getElementById('fechaTarea');
                if (fechaInput) {
                    const today = new Date().toISOString().split('T')[0];
                    fechaInput.min = today;
                    fechaInput.value = today; // Opcional: establecer hoy como valor por defecto
                }
            });
    </script>
</body>
</html>