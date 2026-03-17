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

$asignaciones = [];
$anios_escolares = [];
$docentes = [];

try {
    // [NUEVO CÓDIGO] Obtener el año escolar activo actual (si existe)
    $stmtAnioActivo = $pdo->query("SELECT id FROM anios_escolares WHERE activo = 1 LIMIT 1");
    $anio_activo = $stmtAnioActivo->fetch(PDO::FETCH_ASSOC);
    $anio_escolar_activo_id = $anio_activo['id'] ?? null;
    $anio_escolar_activo_nombre = $anio_activo['nombre'] ?? '';
    // Obtener años escolares activos para el filtro y el modal
    $stmtAnios = $pdo->query("SELECT id, nombre FROM anios_escolares WHERE activo = 1 ORDER BY nombre DESC");
    $anios_escolares = $stmtAnios->fetchAll();

    // Obtener docentes activos para el modal
    $stmtDocentes = $pdo->query("SELECT id, nombre, apellido, correo FROM usuarios WHERE rol = 'docente' AND activo = 1 ORDER BY apellido, nombre");
    $docentes = $stmtDocentes->fetchAll();

    // Lógica para cargar asignaciones (filtrar por año escolar si se selecciona)
    $selected_anio_id = $_GET['anio_id'] ?? $anio_escolar_activo_id; 

    $sqlAsignaciones = "
        SELECT
            aa.id,
            ae.nombre AS anio_nombre,
            aa.nombre_aula,
            aa.turno,
            u.nombre AS docente_nombre,
            u.apellido AS docente_apellido,
            u.id AS docente_id,
            aa.activo,
            aa.anio_escolar_id
        FROM
            asignaciones_aula aa
        JOIN
            anios_escolares ae ON aa.anio_escolar_id = ae.id
        JOIN
            usuarios u ON aa.docente_id = u.id
    ";

    $params = [];
    $where_clauses = [];

    // [NUEVO CÓDIGO] Aplicar el filtro si hay un año seleccionado (el activo por defecto)
    if ($selected_anio_id) {
        $where_clauses[] = "aa.anio_escolar_id = :anio_id";
        $params[':anio_id'] = $selected_anio_id;
    }
    
    // Si hay cláusulas WHERE, las concatenamos
    if (!empty($where_clauses)) {
        $sqlAsignaciones .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $sqlAsignaciones .= " ORDER BY anio_nombre DESC, nombre_aula";

    $stmt = $pdo->prepare($sqlAsignaciones);
    $stmt->execute($params);
    $asignaciones = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = "Error al cargar datos: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración en producción
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestión de Asignaciones de Aulas - Cecilia Bazán de Segura</title>
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
            <h1 class="text-primary">Gestión de Asignaciones de Aulas</h1>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#asignacionModal" id="addAsignacionBtn">
                <i class="fas fa-plus"></i> Nueva Asignación
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <form action="gestion_asignaciones.php" method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="filterAnio" class="col-form-label">Filtrar por Año Escolar:</label>
                </div>
                <div class="col-auto">
                   <select name="anio_id" id="anio_id_filter" class="form-select me-2" onchange="this.form.submit()">
    <option value="">Todos los Años Escolares</option>
    <?php foreach ($anios_escolares as $anio): ?>
        <option value="<?php echo htmlspecialchars($anio['id']); ?>" <?php echo ($anio['id'] == $selected_anio_id) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($anio['nombre']); ?>
        </option>
    <?php endforeach; ?>
</select>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Año Escolar</th>
                        <th>Aula</th>
                        <th>Turno</th>
                        <th>Docente Responsable</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($asignaciones) > 0): ?>
                        <?php foreach ($asignaciones as $asignacion): ?>
                            <tr class="<?php echo ($asignacion['activo'] == 0) ? 'table-secondary text-muted' : ''; ?>">
                                <td><?php echo htmlspecialchars($asignacion['anio_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['nombre_aula']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['turno']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['docente_nombre'] . ' ' . $asignacion['docente_apellido']); ?></td>
                                <td>
                                    <?php if ($asignacion['activo'] == 1): ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-asignacion-btn"
                                            data-id="<?php echo $asignacion['id']; ?>"
                                            data-anio_id="<?php echo htmlspecialchars($asignacion['anio_escolar_id']); ?>"
                                            data-nombre_aula="<?php echo htmlspecialchars($asignacion['nombre_aula']); ?>"
                                            data-turno="<?php echo htmlspecialchars($asignacion['turno']); ?>"
                                            data-docente_id="<?php echo htmlspecialchars($asignacion['docente_id']); ?>"
                                            data-activo="<?php echo htmlspecialchars($asignacion['activo']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#asignacionModal">
                                        Editar
                                    </button>
                                    <?php if ($asignacion['activo'] == 1): ?>
                                        <button class="btn btn-sm btn-outline-warning toggle-asignacion-status-btn" data-id="<?php echo $asignacion['id']; ?>" data-status="0">
                                            <i class="fas fa-ban"></i> Desactivar
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success toggle-asignacion-status-btn" data-id="<?php echo $asignacion['id']; ?>" data-status="1">
                                            <i class="fas fa-check-circle"></i> Activar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No hay asignaciones de aulas para el año seleccionado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="asignacionModal" tabindex="-1" aria-labelledby="asignacionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="asignacionModalLabel">Nueva Asignación de Aula</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="asignacionForm" action="gestion_asignaciones_action.php" method="POST">
                        <input type="hidden" id="asignacionId" name="id">

                        <div class="mb-3">
                            <label for="anioEscolar" class="form-label requerido">Año Escolar:</label>
                            <select class="form-select" id="anioEscolar" name="anio_escolar_id" required>
                                <option value="">Seleccione un Año Escolar</option>
                                <?php foreach ($anios_escolares as $anio): ?>
                                    <option value="<?php echo $anio['id']; ?>"><?php echo htmlspecialchars($anio['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="nombreAula" class="form-label requerido">Nombre del Aula:</label>
                            <input type="text" class="form-control" id="nombreAula" name="nombre_aula" required>
                        </div>
                        <div class="mb-3">
                            <label for="turno" class="form-label requerido">Turno:</label>
                            <select class="form-select" id="turno" name="turno" required>
                                <option value="">Seleccione un Turno</option>
                                <option value="Mañana">Mañana</option>
                                <option value="Tarde">Tarde</option>
                                <option value="Noche">Noche</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="docenteResponsable" class="form-label requerido">Docente Responsable:</label>
                            <select class="form-select" id="docenteResponsable" name="docente_id" required>
                                <option value="">Seleccione un Docente</option>
                                <?php foreach ($docentes as $docente): ?>
                                    <option value="<?php echo $docente['id']; ?>"><?php echo htmlspecialchars($docente['nombre'] . ' ' . $docente['apellido'] . ' (' . $docente['correo'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check" id="asignacionActivaCheckGroup">
                            <input type="checkbox" class="form-check-input" id="asignacionActiva" name="activo" value="1" checked>
                            <label class="form-check-label" for="asignacionActiva">Activa</label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary">Guardar Asignación</button>
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
            const asignacionModal = document.getElementById('asignacionModal');
            const asignacionModalLabel = document.getElementById('asignacionModalLabel');
            const asignacionForm = document.getElementById('asignacionForm');
            const addAsignacionBtn = document.getElementById('addAsignacionBtn');

            const asignacionIdInput = document.getElementById('asignacionId');
            const anioEscolarSelect = document.getElementById('anioEscolar');
            const nombreAulaInput = document.getElementById('nombreAula');
            const turnoSelect = document.getElementById('turno');
            const docenteResponsableSelect = document.getElementById('docenteResponsable');
            const asignacionActivaCheck = document.getElementById('asignacionActiva');
            const asignacionActivaCheckGroup = document.getElementById('asignacionActivaCheckGroup');

            // Limpiar el formulario al abrir para añadir
            addAsignacionBtn.addEventListener('click', function() {
                asignacionModalLabel.textContent = 'Nueva Asignación de Aula';
                asignacionForm.reset();
                asignacionIdInput.value = '';
                asignacionActivaCheck.checked = true; // Por defecto activa
                asignacionActivaCheckGroup.style.display = 'none'; // Ocultar el checkbox "activo" en añadir
            });

            // Llenar el formulario al abrir para editar
            document.querySelectorAll('.edit-asignacion-btn').forEach(button => {
                button.addEventListener('click', function() {
                    asignacionModalLabel.textContent = 'Editar Asignación de Aula';
                    asignacionIdInput.value = this.dataset.id;
                    anioEscolarSelect.value = this.dataset.anio_id;
                    nombreAulaInput.value = this.dataset.nombre_aula;
                    turnoSelect.value = this.dataset.turno;
                    docenteResponsableSelect.value = this.dataset.docente_id;
                    asignacionActivaCheck.checked = (this.dataset.activo == '1');
                    asignacionActivaCheckGroup.style.display = 'block'; // Mostrar el checkbox "activo" en editar
                });
            });

            // Lógica para alternar estado (activar/desactivar)
            document.querySelectorAll('.toggle-asignacion-status-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const asignacionId = this.dataset.id;
                    const newStatus = this.dataset.status;
                    const actionText = (newStatus == '0') ? 'desactivar' : 'activar';
                    if (confirm(`¿Estás seguro de que quieres ${actionText} esta Asignación de Aula?`)) {
                        window.location.href = `gestion_asignaciones_action.php?action=toggle_status&id=${asignacionId}&status=${newStatus}`;
                    }
                });
            });
        });
    </script>
</body>
</html>