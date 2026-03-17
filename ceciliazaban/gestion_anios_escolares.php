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

$anios = [];
try {
    $stmt = $pdo->query("SELECT id, nombre, fecha_inicio, fecha_fin, activo FROM anios_escolares ORDER BY nombre DESC");
    $anios = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error al cargar los años escolares: " . $e->getMessage();
    $message_type = 'danger';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestión de Años Escolares - Cecilia Bazán de Segura</title>
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
            <h1 class="text-primary">Gestión de Años Escolares</h1>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#anioModal" id="addAnioBtn">
                <i class="fas fa-plus"></i> Añadir Año Escolar
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Año Escolar</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($anios) > 0): ?>
                        <?php foreach ($anios as $anio): ?>
                            <tr class="<?php echo ($anio['activo'] == 0) ? 'table-secondary text-muted' : ''; ?>">
                                <td><?php echo htmlspecialchars($anio['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($anio['fecha_inicio'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($anio['fecha_fin'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($anio['activo'] == 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-anio-btn"
                                            data-id="<?php echo $anio['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($anio['nombre']); ?>"
                                            data-inicio="<?php echo htmlspecialchars($anio['fecha_inicio'] ?? ''); ?>"
                                            data-fin="<?php echo htmlspecialchars($anio['fecha_fin'] ?? ''); ?>"
                                            data-activo="<?php echo htmlspecialchars($anio['activo']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#anioModal">
                                        Editar
                                    </button>
                                    <?php if ($anio['activo'] == 1): ?>
                                        <button class="btn btn-sm btn-outline-warning toggle-anio-status-btn" data-id="<?php echo $anio['id']; ?>" data-status="0">
                                            <i class="fas fa-ban"></i> Desactivar
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success toggle-anio-status-btn" data-id="<?php echo $anio['id']; ?>" data-status="1">
                                            <i class="fas fa-check-circle"></i> Activar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No hay años escolares registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="anioModal" tabindex="-1" aria-labelledby="anioModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="anioModalLabel">Añadir Año Escolar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="anioForm" action="gestion_anios_escolares_action.php" method="POST">
                        <input type="hidden" id="anioId" name="id">

                        <div class="mb-3">
                            <label for="nombreAnio" class="form-label requerido">Nombre del Año Escolar (Ej: 2025-2026):</label>
                            <input type="text" class="form-control" id="nombreAnio" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="fechaInicio" class="form-label requerido">Fecha de Inicio:</label>
                            <input type="date" class="form-control" id="fechaInicio" name="fecha_inicio" required>
                        </div>
                        <div class="mb-3">
                            <label for="fechaFin" class="form-label requerido">Fecha de Fin:</label>
                            <input type="date" class="form-control" id="fechaFin" name="fecha_fin" required>
                        </div>
                        <div class="mb-3 form-check" id="activoCheckGroup">
                            <input type="checkbox" class="form-check-input" id="anioActivo" name="activo" value="1" checked>
                            <label class="form-check-label" for="anioActivo">Activo</label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary">Guardar Año Escolar</button>
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
            const anioModal = document.getElementById('anioModal');
            const anioModalLabel = document.getElementById('anioModalLabel');
            const anioForm = document.getElementById('anioForm');
            const addAnioBtn = document.getElementById('addAnioBtn');

            const anioIdInput = document.getElementById('anioId');
            const nombreAnioInput = document.getElementById('nombreAnio');
            const fechaInicioInput = document.getElementById('fechaInicio');
            const fechaFinInput = document.getElementById('fechaFin');
            const anioActivoCheck = document.getElementById('anioActivo');
            const activoCheckGroup = document.getElementById('activoCheckGroup'); // Para ocultarlo en añadir

            // Limpiar el formulario al abrir para añadir
            addAnioBtn.addEventListener('click', function() {
                anioModalLabel.textContent = 'Añadir Nuevo Año Escolar';
                anioForm.reset();
                anioIdInput.value = '';
                anioActivoCheck.checked = true; // Por defecto activo
                activoCheckGroup.style.display = 'none'; // Ocultar el checkbox "activo" en añadir
            });

            // Llenar el formulario al abrir para editar
            document.querySelectorAll('.edit-anio-btn').forEach(button => {
                button.addEventListener('click', function() {
                    anioModalLabel.textContent = 'Editar Año Escolar';
                    anioIdInput.value = this.dataset.id;
                    nombreAnioInput.value = this.dataset.nombre;
                    fechaInicioInput.value = this.dataset.inicio;
                    fechaFinInput.value = this.dataset.fin;
                    anioActivoCheck.checked = (this.dataset.activo == '1');
                    activoCheckGroup.style.display = 'block'; // Mostrar el checkbox "activo" en editar
                });
            });

            // Lógica para alternar estado (activar/desactivar)
            document.querySelectorAll('.toggle-anio-status-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const anioId = this.dataset.id;
                    const newStatus = this.dataset.status;
                    const actionText = (newStatus == '0') ? 'desactivar' : 'activar';
                    if (confirm(`¿Estás seguro de que quieres ${actionText} este Año Escolar? Esto afectará a sus asignaciones.`)) {
                        window.location.href = `gestion_anios_escolares_action.php?action=toggle_status&id=${anioId}&status=${newStatus}`;
                    }
                });
            });
        });
    </script>
</body>
</html>