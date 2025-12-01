<?php
// File: /sesion/docente/tareas.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'docente') {
    header('Location: ../../index.html');
    http_response_code(401);
    exit;
}

$pagina_titulo = 'Gestión de Tareas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pagina_titulo; ?> - Panel Docente</title>
    <link rel="stylesheet" href="../../css/styles.css"> 
    <style>
        .table-responsive { overflow-x: auto; }
        .table-responsive table { min-width: 800px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); justify-content: center; align-items: center; }
        .modal-content { background-color: #fefefe; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 600px; max-width: 90%; }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="main-app active">
        <div class="main-content" style="padding: 20px;">
            <div class="top-bar">
                <h2 id="moduleTitle">📝 <?php echo $pagina_titulo; ?></h2>
                <div class="breadcrumb">Desde aquí puede crear y gestionar las tareas para sus alumnos.</div>
            </div>
            
            <div class="content-area">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>Lista de Tareas Creadas</h3>
                    <button class="btn-login" onclick="mostrarModalCrearTarea()">➕ Crear Nueva Tarea</button>
                </div>
                
                <div class="table-responsive" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; text-align: left;">
                                <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Título</th>
                                <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Grado/Materia</th>
                                <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">F. Creación</th>
                                <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">F. Entrega</th>
                                <th style="padding: 15px; border-bottom: 2px solid #e9ecef;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaTareas">
                            <tr>
                                <td colspan="5" style="padding: 20px; text-align: center; color: #7f8c8d;">
                                    Cargando tareas...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        </div>

    <div id="modalTarea" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="cerrarModalTarea()">&times;</span>
            <h3 id="modalTituloTarea">Crear Nueva Tarea</h3>
            
            <form id="formTarea" onsubmit="event.preventDefault(); guardarTarea();" style="margin-top: 20px;">
                <input type="hidden" id="tareaId">

                <div class="form-group">
                    <label for="selectAsignacion">Grado y Materia</label>
                    <select id="selectAsignacion" required>
                        <option value="">Cargando carga académica...</option>
                    </select>
                    <small>Seleccione la combinación de Grado y Materia a la que asignará esta tarea.</small>
                </div>

                <div class="form-group">
                    <label for="titulo">Título de la Tarea</label>
                    <input type="text" id="titulo" maxlength="200" required>
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="fech_entrega">Fecha Límite de Entrega</label>
                    <input type="date" id="fech_entrega" required>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-login">💾 Guardar Tarea</button>
                    <button type="button" class="btn-logout" onclick="cerrarModalTarea()">❌ Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let cargaAcademicaGlobal = []; // Para guardar las asignaciones válidas

        function cerrarModalTarea() {
            document.getElementById('modalTarea').style.display = 'none';
        }

        async function cargarTareas() {
            try {
                const response = await fetch('../../api/docente/obtener-tareas.php');
                const data = await response.json();
                
                if (data.success) {
                    cargaAcademicaGlobal = data.carga_academica; // Guardar para el modal
                    actualizarTablaTareas(data.tareas);
                    popularSelectAsignacion(data.carga_academica);
                } else {
                    alert('❌ Error al cargar datos: ' + (data.message || 'Desconocido'));
                    document.getElementById('tablaTareas').innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #e74c3c;">Error al cargar tareas.</td></tr>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('tablaTareas').innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #e74c3c;">Error de conexión con el servidor.</td></tr>';
            }
        }

        function actualizarTablaTareas(tareas) {
            const tbody = document.getElementById('tablaTareas');
            
            if (tareas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #7f8c8d;">No ha creado ninguna tarea aún.</td></tr>';
                return;
            }
            
            let html = '';
            tareas.forEach(tarea => {
                const gradoDisplay = `${tarea.grado_nombre} "${tarea.seccion}" (${tarea.nivel})`;
                const fechaEntrega = new Date(tarea.fech_entrega).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
                const fechaCreacion = new Date(tarea.fecha_creacion).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
                
                html += `
                <tr style="border-bottom: 1px solid #f0f2f5;">
                    <td style="padding: 15px; font-weight: 600;">${tarea.titulo}</td>
                    <td style="padding: 15px;">${gradoDisplay} - ${tarea.materia_nombre}</td>
                    <td style="padding: 15px;">${fechaCreacion}</td>
                    <td style="padding: 15px;"><span style="color: #e74c3c; font-weight: 600;">${fechaEntrega}</span></td>
                    <td style="padding: 15px;">
                        <button onclick="verEntregas(${tarea.id})" style="padding: 6px 12px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                            📋 Entregas
                        </button>
                        <button onclick="eliminarTarea(${tarea.id})" style="padding: 6px 12px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            🗑️ Eliminar
                        </button>
                    </td>
                </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }

        function popularSelectAsignacion(carga) {
            const select = document.getElementById('selectAsignacion');
            select.innerHTML = '<option value="">-- Seleccionar Grado y Materia --</option>';

            if (carga.length === 0) {
                select.innerHTML = '<option value="">No hay asignaciones académicas.</option>';
                select.disabled = true;
                return;
            }
            
            // Usamos un valor compuesto (grado_id|materia_id) para la validación del backend
            carga.forEach(item => {
                const display = `${item.grado_nombre} "${item.seccion}" (${item.nivel}) - ${item.materia_nombre}`;
                const value = `${item.grado_id}|${item.materia_id}`; 
                select.innerHTML += `<option value="${value}">${display}</option>`;
            });
            select.disabled = false;
        }

        function mostrarModalCrearTarea() {
            document.getElementById('modalTituloTarea').textContent = 'Crear Nueva Tarea';
            document.getElementById('formTarea').reset();
            document.getElementById('tareaId').value = '';
            document.getElementById('selectAsignacion').value = ''; 
            document.getElementById('modalTarea').style.display = 'flex';
        }

        async function guardarTarea() {
            // Obtener el valor compuesto y separarlo
            const asignacionCompuesta = document.getElementById('selectAsignacion').value;
            if (!asignacionCompuesta) {
                alert('Debe seleccionar un Grado y Materia.');
                return;
            }
            const [gradoId, materiaId] = asignacionCompuesta.split('|').map(Number);
            
            const titulo = document.getElementById('titulo').value;
            const descripcion = document.getElementById('descripcion').value;
            const fech_entrega = document.getElementById('fech_entrega').value;
            
            const payload = {
                titulo: titulo,
                descripcion: descripcion,
                grado_id: gradoId,
                materia_id: materiaId,
                fech_entrega: fech_entrega
            };
            
            try {
                const response = await fetch('../../api/docente/guardar-tarea.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ ' + data.message);
                    cerrarModalTarea();
                    cargarTareas(); // Recargar la tabla
                } else {
                    alert('❌ ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error de conexión al guardar la tarea.');
            }
        }

        function verEntregas(tareaId) {
            alert(`Funcionalidad para ver entregas de la Tarea ID: ${tareaId} - Pendiente de implementar.`);
        }

        async function eliminarTarea(tareaId) {
            // Esta funcionalidad aún no tiene un API, pero la dejamos para el futuro
            if (confirm('¿Está seguro de que desea eliminar esta tarea? Esto no se puede deshacer.')) {
                alert(`API de eliminación de tareas no implementada. Tarea ID: ${tareaId}`);
                // Aquí iría el código para llamar a la API de eliminación
            }
        }


        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            cargarTareas();
        });
    </script>
</body>
</html>