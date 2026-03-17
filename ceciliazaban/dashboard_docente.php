<?php
session_start();
require_once 'includes/db.php';

// Control de Acceso: Solo docentes
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header('Location: login.php');
    exit();
}

$docente_id = $_SESSION['user_id'];
$docente_nombre = $_SESSION['nombre'] ?? 'Docente';
$docente_apellido = $_SESSION['apellido'] ?? '';

$asignaciones = [];
$anios_escolares = []; // Para el filtro de años escolares

try {
    // Obtener años escolares activos para el filtro (opcional para el docente)
    $stmtAnios = $pdo->query("SELECT id, nombre FROM anios_escolares WHERE activo = 1 ORDER BY nombre DESC");
    $anios_escolares = $stmtAnios->fetchAll();

    // Lógica para cargar asignaciones del docente (filtrar por año escolar si se selecciona)
    $selected_anio_id = $_GET['anio_id'] ?? null; // No seleccionar el primero por defecto, que el docente elija o vea todos

    $sqlAsignaciones = "
        SELECT
            aa.id,
            ae.nombre AS anio_nombre,
            aa.nombre_aula,
            aa.turno,
            aa.activo
        FROM
            asignaciones_aula aa
        JOIN
            anios_escolares ae ON aa.anio_escolar_id = ae.id
        WHERE
            aa.docente_id = :docente_id
    ";
    $paramsAsignaciones = [':docente_id' => $docente_id];

    if ($selected_anio_id && is_numeric($selected_anio_id)) {
        $sqlAsignaciones .= " AND aa.anio_escolar_id = :anio_id";
        $paramsAsignaciones[':anio_id'] = $selected_anio_id;
    }
    // Solo mostrar asignaciones activas al docente
    $sqlAsignaciones .= " AND aa.activo = 1 AND ae.activo = 1"; // Asegurarse que la asignación y el año escolar estén activos

    $sqlAsignaciones .= " ORDER BY ae.nombre DESC, aa.nombre_aula, aa.turno";

    $stmtAsignaciones = $pdo->prepare($sqlAsignaciones);
    $stmtAsignaciones->execute($paramsAsignaciones);
    $asignaciones = $stmtAsignaciones->fetchAll();

} catch (PDOException $e) {
    $message = "Error al cargar tus asignaciones: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración en producción
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Panel de Docente - Cecilia Bazán de Segura</title>
    <link rel="stylesheet" href="bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/PlantillaCss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="container-fluid bg-light shadow-sm py-2">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="dashboard_docente.php">
                    <img src="img/MPPEducacion.png" alt="Institución Bolivariana" class="img-fluid" style="max-height: 50px;" />
                    <span class="ms-3 h4 mb-0 text-primary d-none d-md-block">Cecilia Bazán de Segura</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
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
        <h1 class="text-primary">Bienvenido, <?php echo htmlspecialchars($docente_nombre . ' ' . $docente_apellido); ?></h1>
        <p class="lead">Aquí puedes ver tus asignaciones de aula.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <form action="dashboard_docente.php" method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="filterAnio" class="col-form-label">Filtrar por Año Escolar:</label>
                </div>
                <div class="col-auto">
                    <select class="form-select" id="filterAnio" name="anio_id" onchange="this.form.submit()">
                        <option value="">Todos los Años</option>
                        <?php foreach ($anios_escolares as $anio): ?>
                            <option value="<?php echo $anio['id']; ?>" <?php echo ($selected_anio_id == $anio['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($anio['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <h2 class="text-secondary mb-3">Mis Asignaciones de Aula</h2>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Año Escolar</th>
                        <th>Aula Asignada</th>
                        <th>Turno</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($asignaciones) > 0): ?>
                        <?php foreach ($asignaciones as $asignacion): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($asignacion['anio_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['nombre_aula']); ?></td>
                                <td><?php echo htmlspecialchars($asignacion['turno']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No tienes asignaciones de aula activas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your_fontawesome_kit_code.js" crossorigin="anonymous"></script>
</body>
</html>