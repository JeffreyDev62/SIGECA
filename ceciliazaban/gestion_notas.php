<?php
session_start();
require_once 'includes/db.php';

// Control de Acceso: Solo docentes y administradores
if (!isset($_SESSION['user_id']) || ($_SESSION['rol'] !== 'docente' && $_SESSION['rol'] !== 'admin')) {
    header('Location: login.php');
    exit();
}

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$current_user_id = $_SESSION['user_id'];
$current_user_rol = $_SESSION['rol'];

$aulas_asignadas = [];
$estudiantes_con_notas = [];
$selected_aula_id = $_GET['aula_id'] ?? null;
$selected_anio_escolar_id = null; // Para guardar el ID del año escolar del aula seleccionada

try {
    // Obtener el año escolar activo actual
    $stmtAnioActivo = $pdo->query("SELECT id, nombre FROM anios_escolares WHERE activo = 1 LIMIT 1");
    $anio_escolar_activo = $stmtAnioActivo->fetch(PDO::FETCH_ASSOC);

    if (!$anio_escolar_activo) {
        $message = "No hay un año escolar activo configurado. Las notas no pueden ser gestionadas.";
        $message_type = 'warning';
    } else {
        $selected_anio_escolar_id = $anio_escolar_activo['id'];

        // Obtener las aulas asignadas al docente actual (o todas si es admin)
        $sqlAulas = "
            SELECT aa.id, aa.nombre_aula, aa.turno, ae.nombre AS anio_escolar_nombre
            FROM asignaciones_aula aa
            JOIN anios_escolares ae ON aa.anio_escolar_id = ae.id
            WHERE aa.activo = 1 AND ae.activo = 1
        ";
        $paramsAulas = [];

        if ($current_user_rol === 'docente') {
            $sqlAulas .= " AND aa.docente_id = :docente_id";
            $paramsAulas[':docente_id'] = $current_user_id;
        }
        $sqlAulas .= " ORDER BY ae.nombre DESC, aa.nombre_aula, aa.turno";

        $stmtAulas = $pdo->prepare($sqlAulas);
        $stmtAulas->execute($paramsAulas);
        $aulas_asignadas = $stmtAulas->fetchAll();

        // Si se seleccionó un aula, cargar la lista de estudiantes con sus notas
        if ($selected_aula_id && $selected_anio_escolar_id) {
            $sqlEstudiantesNotas = "
                SELECT
                    e.id AS estudiante_id,
                    e.cedula,
                    e.nombre AS estudiante_nombre,
                    e.apellido AS estudiante_apellido,
                    i.id AS inscripcion_id,
                    n.id AS nota_id,
                    n.momento1_nota,
                    n.momento2_nota,
                    n.momento3_nota,
                    n.literal
                FROM
                    inscripciones i
                JOIN
                    estudiantes e ON i.estudiante_id = e.id
                LEFT JOIN
                    notas n ON i.id = n.inscripcion_id AND n.activo = 1
                WHERE
                    i.aula_id = :aula_id AND i.anio_escolar_id = :anio_escolar_id AND i.activo = 1
                ORDER BY
                    e.apellido, e.nombre
            ";
            $stmtEstudiantesNotas = $pdo->prepare($sqlEstudiantesNotas);
            $stmtEstudiantesNotas->execute([
                ':aula_id' => $selected_aula_id,
                ':anio_escolar_id' => $selected_anio_escolar_id
            ]);
            $estudiantes_con_notas = $stmtEstudiantesNotas->fetchAll();

            // Verificar si el aula seleccionada pertenece al docente actual (solo para docentes)
            $is_assigned_to_selected_aula = false;
            foreach ($aulas_asignadas as $aula) {
                if ($aula['id'] == $selected_aula_id) {
                    $is_assigned_to_selected_aula = true;
                    break;
                }
            }

            if ($current_user_rol === 'docente' && !$is_assigned_to_selected_aula) {
                $message = "Acceso denegado: No tienes permiso para gestionar notas en esta aula.";
                $message_type = 'danger';
                $estudiantes_con_notas = []; // Limpiar estudiantes si no hay permiso
            }
        }

    }

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
    <title>Gestión de Notas - Cecilia Bazán de Segura</title>
    <link rel="stylesheet" href="bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/PlantillaCss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .notes-table textarea.form-control {
            max-width: 250px; /* Ancho para las descripciones */
            min-height: 60px; /* Altura mínima para las descripciones */
            font-size: 0.85rem;
        }
        .notes-table input[type="text"].form-control {
            max-width: 80px; /* Ancho para el literal */
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="container-fluid bg-light shadow-sm py-2">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="dashboard_<?php echo $_SESSION['rol']; ?>.php">
                    <img src="img/MPPEducacion.png" alt="Institución Bolivariana" class="img-fluid" style="max-height: 50px;" />
                    <span class="ms-3 h4 mb-0 text-primary d-none d-md-block">Cecilia Bazán de Segura</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav">
                        <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard_docente.php">Mis Asignaciones</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="gestion_notas.php">Notas</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="perfil_docente.php">Mi Perfil</a> </li>
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
        <h1 class="text-primary mb-4">Gestión de Notas</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!$anio_escolar_activo): ?>
            <div class="alert alert-info" role="alert">
                No hay un año escolar activo para gestionar notas.
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    Seleccionar Aula (Año Escolar Activo: <?php echo htmlspecialchars($anio_escolar_activo['nombre']); ?>)
                </div>
                <div class="card-body">
                    <form action="gestion_notas.php" method="GET" class="mb-3">
                        <div class="row align-items-end">
                            <div class="col-md-6 mb-3">
                                <label for="aulaSelect" class="form-label">Mis Aulas Asignadas:</label>
                                <select class="form-select" id="aulaSelect" name="aula_id" required onchange="this.form.submit()">
                                    <option value="">-- Seleccione un Aula --</option>
                                    <?php foreach ($aulas_asignadas as $aula): ?>
                                        <option value="<?php echo $aula['id']; ?>"
                                            <?php echo ($selected_aula_id == $aula['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($aula['nombre_aula'] . ' - Turno ' . $aula['turno'] . ' (Año: ' . $aula['anio_escolar_nombre'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_aula_id && $estudiantes_con_notas): ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        Estudiantes en Aula Seleccionada
                    </div>
                    <div class="card-body">
                        <form action="gestion_notas_action.php" method="POST">
                            <input type="hidden" name="aula_id" value="<?php echo htmlspecialchars($selected_aula_id); ?>">
                            <input type="hidden" name="docente_id" value="<?php echo htmlspecialchars($current_user_id); ?>">
                            <input type="hidden" name="anio_escolar_id" value="<?php echo htmlspecialchars($selected_anio_escolar_id); ?>">

                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle notes-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cédula</th>
                                            <th>Estudiante</th>
                                            <th>Momento 1 (Descripción)</th>
                                            <th>Momento 2 (Descripción)</th>
                                            <th>Momento 3 (Descripción)</th>
                                            <th>Literal (A-F)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($estudiantes_con_notas) > 0): ?>
                                            <?php foreach ($estudiantes_con_notas as $estudiante): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($estudiante['cedula']); ?></td>
                                                    <td><?php echo htmlspecialchars($estudiante['estudiante_nombre'] . ' ' . $estudiante['estudiante_apellido']); ?></td>
                                                    <input type="hidden" name="notas[<?php echo $estudiante['inscripcion_id']; ?>][inscripcion_id]" value="<?php echo $estudiante['inscripcion_id']; ?>">
                                                    <input type="hidden" name="notas[<?php echo $estudiante['inscripcion_id']; ?>][nota_id]" value="<?php echo $estudiante['nota_id']; ?>">

                                                    <td>
                                                        <textarea class="form-control form-control-sm" 
                                                                  name="notas[<?php echo $estudiante['inscripcion_id']; ?>][momento1_nota]" 
                                                                  rows="3" placeholder="Descripción M1"><?php echo htmlspecialchars($estudiante['momento1_nota'] ?? ''); ?></textarea>
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control form-control-sm" 
                                                                  name="notas[<?php echo $estudiante['inscripcion_id']; ?>][momento2_nota]" 
                                                                  rows="3" placeholder="Descripción M2"><?php echo htmlspecialchars($estudiante['momento2_nota'] ?? ''); ?></textarea>
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control form-control-sm" 
                                                                  name="notas[<?php echo $estudiante['inscripcion_id']; ?>][momento3_nota]" 
                                                                  rows="3" placeholder="Descripción M3"><?php echo htmlspecialchars($estudiante['momento3_nota'] ?? ''); ?></textarea>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" 
                                                               name="notas[<?php echo $estudiante['inscripcion_id']; ?>][literal]" 
                                                               value="<?php echo htmlspecialchars($estudiante['literal'] ?? ''); ?>" 
                                                               maxlength="1" placeholder="Ej: A">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No hay estudiantes inscritos en esta aula o no tienes permiso para verlos.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Notas</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($selected_aula_id && empty($estudiantes_con_notas) && (!isset($message_type) || $message_type !== 'danger')): ?>
                <div class="alert alert-info" role="alert">
                    No hay estudiantes inscritos en esta aula para el año escolar activo.
                </div>
            <?php elseif (empty($selected_aula_id) && count($aulas_asignadas) > 0): ?>
                <div class="alert alert-info" role="alert">
                    Selecciona un aula de la lista para gestionar sus notas.
                </div>
            <?php elseif (empty($selected_aula_id) && count($aulas_asignadas) == 0 && $current_user_rol === 'docente'): ?>
                <div class="alert alert-warning" role="alert">
                    No tienes aulas asignadas para el año escolar activo. Contacta al administrador.
                </div>
            <?php endif; ?>

        <?php endif; // Fin del if ($anio_escolar_activo) ?>

    </main>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your_fontawesome_kit_code.js" crossorigin="anonymous"></script>
</body>
</html>