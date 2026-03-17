<?php
session_start();
require_once 'includes/db.php';

// Control de Acceso: Solo administradores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$inscripciones = [];
$estudiantes_disponibles = [];
$aulas_disponibles = []; 
$anios_escolares_disponibles = [];

// Obtener el término de búsqueda
$search_term = $_GET['search'] ?? '';
$search_term = trim($search_term);

try {
    // Obtener el año escolar activo actual (si existe)
    $stmtAnioActivo = $pdo->query("SELECT id FROM anios_escolares WHERE activo = 1 LIMIT 1");
    $anio_escolar_activo_id = $stmtAnioActivo->fetchColumn();

    // Obtener estudiantes disponibles (activos y no inscritos en el año escolar activo actual, o ya inscritos si es para edición)
    $sqlEstudiantesDisponibles = "
        SELECT id, cedula, nombre, apellido
        FROM estudiantes
        WHERE activo = 1
    ";
    // Solo si hay un año escolar activo, filtramos los que ya están inscritos en él
    if ($anio_escolar_activo_id) {
        $sqlEstudiantesDisponibles .= " AND id NOT IN (
            SELECT estudiante_id FROM inscripciones
            WHERE anio_escolar_id = :anio_escolar_activo_id AND activo = 1
        )";
        $stmtEstudiantesDisponibles = $pdo->prepare($sqlEstudiantesDisponibles);
        $stmtEstudiantesDisponibles->execute([':anio_escolar_activo_id' => $anio_escolar_activo_id]);
    } else {
        $stmtEstudiantesDisponibles = $pdo->query($sqlEstudiantesDisponibles);
    }
    $estudiantes_disponibles = $stmtEstudiantesDisponibles->fetchAll();

    // Obtener aulas disponibles de la tabla 'asignaciones_aula'
    // CAMBIO: Usando 'ae.nombre' en lugar de 'ae.anio' o 'ae.anio_academico'
    $stmtAulas = $pdo->query("SELECT aa.id, aa.nombre_aula AS seccion, aa.turno, ae.nombre AS anio_display 
                                FROM asignaciones_aula aa
                                JOIN anios_escolares ae ON aa.anio_escolar_id = ae.id
                                WHERE aa.activo = 1 AND ae.activo = 1
                                ORDER BY ae.nombre DESC, aa.nombre_aula, aa.turno"); 
    $aulas_disponibles = $stmtAulas->fetchAll();


    // Obtener años escolares disponibles (activos)
    // CAMBIO: Usando 'nombre' como alias 'anio' para la visualización en el select
    $stmtAniosEscolares = $pdo->query("SELECT id, nombre AS anio FROM anios_escolares WHERE activo = 1 ORDER BY nombre DESC");
    $anios_escolares_disponibles = $stmtAniosEscolares->fetchAll();

   // Lógica para cargar todas las inscripciones (modificada)
// Lógica para cargar todas las inscripciones
    $sqlInscripciones = "
        SELECT
            i.id,
            e.cedula AS estudiante_cedula,
            e.nombre AS estudiante_nombre,
            e.apellido AS estudiante_apellido,
            aa.nombre_aula,
            ae.nombre AS anio_escolar_nombre,
            i.activo,
            i.estudiante_id,
            i.aula_id,
            i.anio_escolar_id,
            i.fecha_inscripcion AS fecha_inscripcion_db
        FROM
            inscripciones i
        JOIN
            estudiantes e ON i.estudiante_id = e.id
        JOIN
            asignaciones_aula aa ON i.aula_id = aa.id
        JOIN
            anios_escolares ae ON i.anio_escolar_id = ae.id
    ";

    $params = [];
    $where_clauses = [];

    // [NUEVO CÓDIGO] Aplicar el filtro de búsqueda
    if (!empty($search_term)) {
        $search_param = '%' . $search_term . '%';
        
        // Usar los alias de la tabla estudiantes (e) para la búsqueda
        $where_clauses[] = " (e.nombre LIKE :search_nombre OR e.apellido LIKE :search_apellido OR e.cedula LIKE :search_cedula) ";
        
        // Asignar parámetros únicos
        $params[':search_nombre'] = $search_param;
        $params[':search_apellido'] = $search_param;
        $params[':search_cedula'] = $search_param;
    }

    // ====================================================================
    // [NUEVO CÓDIGO CRÍTICO] 1. Filtrar por el año escolar activo
    // ====================================================================
    if ($anio_escolar_activo_id) {
        $where_clauses[] = "i.anio_escolar_id = :anio_activo_id";
        $params[':anio_activo_id'] = $anio_escolar_activo_id;
    } else {
        // Si no hay un año escolar activo, no mostramos ninguna inscripción para evitar
        // un listado vacío o confuso de años inactivos.
        $where_clauses[] = "1 = 0"; 
    }
    // ====================================================================


    

    // Si hay cláusulas WHERE, las concatenamos
    if (!empty($where_clauses)) {
        $sqlInscripciones .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $sqlInscripciones .= " ORDER BY e.apellido, e.nombre"; // Ordenar por estudiante
    
    // Preparar y ejecutar la consulta con los filtros
    $stmt = $pdo->prepare($sqlInscripciones);
    $stmt->execute($params); 
    $inscripciones = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = "Error al cargar los datos: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración en producción
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestión de Inscripciones - Cecilia Bazán de Segura</title>
    <link rel="stylesheet" href="bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/PlantillaCss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .requerido::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <header class="container-fluid bg-light shadow-sm py-2">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="dashboard_admin.php">
                    <img src="img/MPPEducacion.png" alt="Institución Bolivariana" class="img-fluid" style="max-height: 50px;" />
                    <span class="ms-3 h4 mb-0 text-primary d-none d-md-block">Cecilia Bazán de Segura</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav">
                        <?php if ($_SESSION['rol'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'dashboard_admin.php') !== false) ? 'active' : ''; ?>" href="dashboard_admin.php">Inicio Admin</a>
                            </li>

                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_usuarios.php') !== false || strpos($_SERVER['PHP_SELF'], 'gestion_anios_escolares.php') !== false || strpos($_SERVER['PHP_SELF'], 'gestion_asignaciones.php') !== false) ? 'active' : ''; ?>" href="#" id="gestionDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Gestión
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="gestionDropdown">
                                    <li><a class="dropdown-item <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_usuarios.php') !== false) ? 'active' : ''; ?>" href="gestion_usuarios.php">Usuarios</a></li>
                                    <li><a class="dropdown-item <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_anios_escolares.php') !== false) ? 'active' : ''; ?>" href="gestion_anios_escolares.php">Años Escolares</a></li>
                                    <li><a class="dropdown-item <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_asignaciones.php') !== false) ? 'active' : ''; ?>" href="gestion_asignaciones.php">Asignación Aulas</a></li>
                                </ul>
                            </li>

                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_estudiantes.php') !== false || strpos($_SERVER['PHP_SELF'], 'gestion_inscripciones.php') !== false) ? 'active' : ''; ?>" href="#" id="estudiantesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Estudiantes
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="estudiantesDropdown">
                                    <li><a class="dropdown-item <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_estudiantes.php') !== false) ? 'active' : ''; ?>" href="gestion_estudiantes.php">Gestión Estudiantes</a></li>
                                    <li><a class="dropdown-item <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_inscripciones.php') !== false) ? 'active' : ''; ?>" href="gestion_inscripciones.php">Registro en Sistema</a></li>
                                </ul>
                            </li>

                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_notas.php') !== false || strpos($_SERVER['PHP_SELF'], 'reporte_asistencia_estudiante.php') !== false || strpos($_SERVER['PHP_SELF'], 'reporte_academico.php') !== false) ? 'active' : ''; ?>" href="#" id="academicoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Académico
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="academicoDropdown">
                                    <li>
                                        <a class="dropdown-item <?php echo (strpos($_SERVER['PHP_SELF'], 'reporte_academico.php') !== false) ? 'active' : ''; ?>" href="reporte_academico.php">Reporte Académico</a>
                                    </li>
                                </ul>
                            </li>

                        <?php elseif ($_SESSION['rol'] === 'docente'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'dashboard_docente.php') !== false) ? 'active' : ''; ?>" href="dashboard_docente.php">Inicio Docente</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_notas.php') !== false) ? 'active' : ''; ?>" aria-current="page" href="gestion_notas.php">Notas</a>
                            </li>
                            <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                        </li>
                    </ul>
                </div>
                <a class="d-none d-lg-block ms-3" href="#">
                    <div class="circle" style="width: 50px; height: 50px; overflow: hidden; border-radius: 50%;">
                        <img src="img/LogoCeciliaBazanSegura.png" alt="Logo Cecilia Bazán Segura" class="img-fluid" />
                    </div>
                </a>
            </div>
        </nav>
    </header>

    <main class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary">Registro de Estudiantes en el Sistema</h1>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#inscripcionModal" id="addInscripcionBtn">
                <i class="fas fa-plus"></i> Añadir Registro
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <form action="gestion_inscripciones.php" method="GET" class="d-flex">
                <input type="text" class="form-control me-2" placeholder="Buscar por cédula, nombre, apellido, aula o año escolar" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="gestion_inscripciones.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Cédula Est.</th>
                        <th>Estudiante</th>
                        <th>Aula</th> 
                        <th>Año Escolar</th>
                        <th>Fecha Inscripción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($inscripciones) > 0): ?>
                        <?php foreach ($inscripciones as $inscripcion): ?>
                            <tr class="<?php echo ($inscripcion['activo'] == 0) ? 'table-secondary text-muted' : ''; ?>">
                                <td><?php echo htmlspecialchars($inscripcion['estudiante_cedula']); ?></td>
                                <td><?php echo htmlspecialchars($inscripcion['estudiante_nombre'] . ' ' . $inscripcion['estudiante_apellido']); ?></td>
                               <td><?php echo htmlspecialchars($inscripcion['nombre_aula']); ?></td>
                                <td><?php echo htmlspecialchars($inscripcion['anio_escolar_nombre']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($inscripcion['fecha_inscripcion_db'])); ?></td>
                                <td>
                                    <?php if ($inscripcion['activo'] == 1): ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-inscripcion-btn"
                                            data-id="<?php echo $inscripcion['id']; ?>"
                                            data-estudiante_id="<?php echo htmlspecialchars($inscripcion['estudiante_id']); ?>"
                                            data-aula_id="<?php echo htmlspecialchars($inscripcion['aula_id']); ?>"
                                            data-anio_escolar_id="<?php echo htmlspecialchars($inscripcion['anio_escolar_id']); ?>"
                                            data-activo="<?php echo htmlspecialchars($inscripcion['activo']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#inscripcionModal">
                                        Editar
                                    </button>
                                    <?php if ($inscripcion['activo'] == 1): ?>
                                        <button class="btn btn-sm btn-outline-warning toggle-inscripcion-status-btn" data-id="<?php echo $inscripcion['id']; ?>" data-status="0">
                                            <i class="fas fa-ban"></i> Desactivar
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success toggle-inscripcion-status-btn" data-id="<?php echo $inscripcion['id']; ?>" data-status="1">
                                            <i class="fas fa-check-circle"></i> Activar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No hay inscripciones registradas que coincidan con la búsqueda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="inscripcionModal" tabindex="-1" aria-labelledby="inscripcionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="inscripcionModalLabel">Añadir Inscripción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="inscripcionForm" action="gestion_inscripciones_action.php" method="POST">
                        <input type="hidden" id="inscripcionId" name="id">

                        <div class="mb-3">
                            <label for="estudianteId" class="form-label requerido">Estudiante:</label>
                            <select class="form-select" id="estudianteId" name="estudiante_id" required>
                                <option value="">Seleccione un estudiante</option>
                                <?php foreach ($estudiantes_disponibles as $est): ?>
                                    <option value="<?php echo $est['id']; ?>">
                                        <?php echo htmlspecialchars($est['nombre'] . ' ' . $est['apellido'] . ' (' . $est['cedula'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="anioEscolarId" class="form-label requerido">Año Escolar:</label>
                            <select class="form-select" id="anioEscolarId" name="anio_escolar_id" required>
                                <option value="">Seleccione un año escolar</option>
                                <?php foreach ($anios_escolares_disponibles as $anio): ?>
                                    <option value="<?php echo $anio['id']; ?>">
                                        <?php echo htmlspecialchars($anio['anio']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="aulaId" class="form-label requerido">Aula (Sección y Turno):</label>
                            <select class="form-select" id="aulaId" name="aula_id" required>
                                <option value="">Seleccione un aula</option>
                                <?php foreach ($aulas_disponibles as $aula): ?>
                                    <option value="<?php echo $aula['id']; ?>">
                                        <?php echo htmlspecialchars('Sección ' . $aula['seccion'] . ' - Turno ' . $aula['turno'] . ' (Año: ' . $aula['anio_display'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 form-check" id="inscripcionActivoCheckGroup">
                            <input type="checkbox" class="form-check-input" id="inscripcionActivo" name="activo" value="1" checked>
                            <label class="form-check-label" for="inscripcionActivo">Activa</label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary">Guardar Inscripción</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your_fontawesome_kit_code.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inscripcionModal = document.getElementById('inscripcionModal');
            const inscripcionModalLabel = document.getElementById('inscripcionModalLabel');
            const inscripcionForm = document.getElementById('inscripcionForm');
            const addInscripcionBtn = document.getElementById('addInscripcionBtn');

            const inscripcionIdInput = document.getElementById('inscripcionId');
            const estudianteIdSelect = document.getElementById('estudianteId');
            const aulaIdSelect = document.getElementById('aulaId');
            const anioEscolarIdSelect = document.getElementById('anioEscolarId');
            const inscripcionActivoCheck = document.getElementById('inscripcionActivo');
            const inscripcionActivoCheckGroup = document.getElementById('inscripcionActivoCheckGroup');

            // Limpiar el formulario al abrir para añadir
            addInscripcionBtn.addEventListener('click', function() {
                inscripcionModalLabel.textContent = 'Añadir Nuevo Registro';
                inscripcionForm.reset();
                inscripcionIdInput.value = '';
                inscripcionActivoCheck.checked = true; // Por defecto activa
                inscripcionActivoCheckGroup.style.display = 'none'; // Ocultar el checkbox "activo" en añadir
                estudianteIdSelect.disabled = false; // Habilitar selección de estudiante para nuevas inscripciones
                anioEscolarIdSelect.disabled = false; // Habilitar selección de año escolar para nuevas inscripciones
            });

            // Llenar el formulario al abrir para editar
            document.querySelectorAll('.edit-inscripcion-btn').forEach(button => {
                button.addEventListener('click', function() {
                    inscripcionModalLabel.textContent = 'Editar Inscripción';
                    inscripcionIdInput.value = this.dataset.id;
                    estudianteIdSelect.value = this.dataset.estudiante_id;
                    aulaIdSelect.value = this.dataset.aula_id;
                    anioEscolarIdSelect.value = this.dataset.anio_escolar_id;
                    inscripcionActivoCheck.checked = (this.dataset.activo == '1');
                    inscripcionActivoCheckGroup.style.display = 'block'; // Mostrar el checkbox "activo" en editar
                    // Se deshabilitan para mantener la unicidad de estudiante por año escolar
                    estudianteIdSelect.disabled = true;
                    anioEscolarIdSelect.disabled = true;
                });
            });

            // Lógica para alternar estado (activar/desactivar)
            document.querySelectorAll('.toggle-inscripcion-status-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const inscripcionId = this.dataset.id;
                    const newStatus = this.dataset.status;
                    const actionText = (newStatus == '0') ? 'desactivar' : 'activar';
                    if (confirm(`¿Estás seguro de que quieres ${actionText} esta Inscripción?`)) {
                        window.location.href = `gestion_inscripciones_action.php?action=toggle_status&id=${inscripcionId}&status=${newStatus}`;
                    }
                });
            });
        });
    </script>
</body>
</html>