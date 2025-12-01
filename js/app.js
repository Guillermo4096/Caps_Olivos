let currentUserType = '';

// Login handler
function handleLogin(event) {
    event.preventDefault();
    
    // Obtener el tipo de usuario seleccionado
    currentUserType = document.getElementById('userType').value;
    const username = document.getElementById('username').value;
    
    // Ocultar login y mostrar app
    document.getElementById('loginScreen').style.display = 'none';
    document.getElementById('mainApp').classList.add('active');
    
    // Cargar el menú según el tipo de usuario
    loadSidebarMenu(currentUserType);
    
    // Actualizar información del usuario
    updateUserInfo(currentUserType, username);
    
    /* *** NOTA PARA EL EQUIPO DE BACK-END/FRONT-END ***
    Aquí se debe integrar la llamada a la API de autenticación.
    Los datos de 'username' y 'password' se enviarían al servidor (e.g., usando fetch/axios).
    Si el login es exitoso, se ejecuta el código de interfaz de arriba.
    */
    
    return false; // Previene el envío tradicional del formulario
}

// Cargar menú del sidebar según tipo de usuario
function loadSidebarMenu(userType) {
    const sidebarNav = document.getElementById('sidebarNav');
    
    if (userType === 'padre') {
        // Menú para PADRES DE FAMILIA
        sidebarNav.innerHTML = `
            <div class="nav-item active" onclick="showModule('dashboard', event)">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </div>
            <div class="nav-item" onclick="showModule('tareas', event)">
                <span class="nav-icon">📝</span>
                <span>Tareas</span>
            </div>
            <div class="nav-item" onclick="showModule('calendario', event)">
                <span class="nav-icon">📅</span>
                <span>Calendario</span>
            </div>
            <div class="nav-item" onclick="showModule('comunicados', event)">
                <span class="nav-icon">📢</span>
                <span>Comunicados</span>
            </div>
            <div class="nav-item" onclick="showModule('perfil', event)">
                <span class="nav-icon">👤</span>
                <span>Mi Perfil</span>
            </div>
        `;
        
        // Mostrar dashboard y cargar perfil de padre
        showModule('dashboard', { currentTarget: sidebarNav.querySelector('.active') }); // Simula el click
        loadParentProfile();
        
    } else if (userType === 'docente') {
        // Menú para DOCENTES
        sidebarNav.innerHTML = `
            <div class="nav-item active" onclick="showModule('dashboard', event)">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </div>
            <div class="nav-item" onclick="showModule('mis-estudiantes', event)">
                <span class="nav-icon">👥</span>
                <span>Mis Estudiantes</span>
            </div>
            <div class="nav-item" onclick="showModule('crear-tarea', event)">
                <span class="nav-icon">➕</span>
                <span>Crear Tarea</span>
            </div>
            <div class="nav-item" onclick="showModule('tareas', event)">
                <span class="nav-icon">📝</span>
                <span>Ver Tareas</span>
            </div>
            <div class="nav-item" onclick="showModule('calendario', event)">
                <span class="nav-icon">📅</span>
                <span>Calendario</span>
            </div>
            <div class="nav-item" onclick="showModule('enviar-comunicado', event)">
                <span class="nav-icon">📨</span>
                <span>Enviar Comunicado</span>
            </div>
            <div class="nav-item" onclick="showModule('comunicados', event)">
                <span class="nav-icon">📢</span>
                <span>Ver Comunicados</span>
            </div>
            <div class="nav-item" onclick="showModule('perfil', event)">
                <span class="nav-icon">👤</span>
                <span>Mi Perfil</span>
            </div>
        `;
        
        // Mostrar dashboard y cargar perfil de docente
        showModule('dashboard', { currentTarget: sidebarNav.querySelector('.active') }); // Simula el click
        loadTeacherProfile();
    }
}

// Actualizar información del usuario en el sidebar
function updateUserInfo(userType, username) {
    const userName = document.getElementById('userName');
    const userRole = document.getElementById('userRole');
    const userAvatar = document.querySelector('.user-avatar');
    
    // Se usa el 'username' del formulario como placeholder si no hay datos reales
    if (userType === 'padre') {
        userName.textContent = username || 'Juan Pérez';
        userRole.textContent = 'Padre de Familia';
        userAvatar.textContent = '👨';
    } else if (userType === 'docente') {
        userName.textContent = username || 'Prof. María García';
        userRole.textContent = 'Docente';
        userAvatar.textContent = '👩‍🏫';
    }
}

// Cargar el contenido HTML para el perfil de padre
function loadParentProfile() {
    const profileContent = document.getElementById('profileContent');
    profileContent.innerHTML = `
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar-large">👨</div>
                <div class="profile-info">
                    <h2>Juan Pérez Martínez</h2>
                    <p>Padre de Familia</p>
                </div>
            </div>
            
            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Información Personal</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">DNI</div>
                    <div class="info-value">45678912</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Correo Electrónico</div>
                    <div class="info-value">juan.perez@email.com</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Teléfono</div>
                    <div class="info-value">987 654 321</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Dirección</div>
                    <div class="info-value">Av. Lima 456, Lima</div>
                </div>
            </div>
        </div>
        
        <div class="profile-card">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">👧 Información del Estudiante</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Nombre Completo</div>
                    <div class="info-value">María Pérez López</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Grado</div>
                    <div class="info-value">3ro de Primaria</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Sección</div>
                    <div class="info-value">A</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Tutor</div>
                    <div class="info-value">Prof. María García</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Turno</div>
                    <div class="info-value">Mañana</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Año Académico</div>
                    <div class="info-value">2025</div>
                </div>
            </div>
        </div>
    `;
}

// Cargar el contenido HTML para el perfil de docente
function loadTeacherProfile() {
    const profileContent = document.getElementById('profileContent');
    profileContent.innerHTML = `
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar-large">👩‍🏫</div>
                <div class="profile-info">
                    <h2>María García Rodríguez</h2>
                    <p>Docente - Matemática</p>
                </div>
            </div>
            
            <h3 style="color: #2c3e50; margin-bottom: 20px;">📋 Información Personal</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">DNI</div>
                    <div class="info-value">12345678</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Correo Electrónico</div>
                    <div class="info-value">maria.garcia@iefsc.edu.pe</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Teléfono</div>
                    <div class="info-value">987 123 456</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Especialidad</div>
                    <div class="info-value">Matemática</div>
                </div>
            </div>
        </div>
        
        <div class="profile-card">
            <h3 style="color: #2c3e50; margin-bottom: 20px;">🏫 Información Académica</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Grados a Cargo</div>
                    <div class="info-value">3ro A, 3ro B</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Total Estudiantes</div>
                    <div class="info-value">56 estudiantes</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Turno</div>
                    <div class="info-value">Mañana</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Años de Servicio</div>
                    <div class="info-value">8 años</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Horario</div>
                    <div class="info-value">8:00 AM - 1:00 PM</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Cargo Adicional</div>
                    <div class="info-value">Tutora 3ro A</div>
                </div>
            </div>
        </div>
    `;
}

// Logout handler
function handleLogout() {
    document.getElementById('mainApp').classList.remove('active');
    document.getElementById('loginScreen').style.display = 'flex';
    document.getElementById('loginForm').reset();
    currentUserType = '';
}

// Module navigation (Muestra/Oculta contenido principal)
function showModule(moduleName, event) {
    // Hide all modules
    const modules = document.querySelectorAll('.module-content');
    modules.forEach(module => module.classList.remove('active'));
    
    // Show selected module
    const selectedModule = document.getElementById(moduleName);
    if (selectedModule) {
        selectedModule.classList.add('active');
    }
    
    // Update nav items
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }
    
    // Update title
    const titles = {
        'dashboard': 'Dashboard',
        'tareas': 'Tareas',
        'calendario': 'Calendario',
        'comunicados': 'Comunicados',
        'perfil': 'Mi Perfil',
        'crear-tarea': 'Crear Nueva Tarea',
        'mis-estudiantes': 'Mis Estudiantes',
        'enviar-comunicado': 'Enviar Comunicado'
    };
    document.getElementById('moduleTitle').textContent = titles[moduleName] || moduleName;
    
    /* *** NOTA PARA EL EQUIPO DE BACK-END ***
    Aquí es donde se harían las llamadas a la API (API calls)
    para cargar los datos específicos de cada módulo
    (ej: fetch('/api/tareas'), fetch('/api/eventos')).
    */
}