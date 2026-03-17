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

$estudiantes = [];
$anios_escolares = [];
$reporte_data = null; // Para almacenar los datos de la boleta

$estudiante_seleccionado_id = $_GET['estudiante_id'] ?? null;
$anio_escolar_seleccionado_id = $_GET['anio_escolar_id'] ?? null;

// [NUEVO] Permitir la selección de aula para el reporte masivo
$aula_seleccionada_id = $_GET['aula_id'] ?? null;

$aulas_disponibles = []; // Nuevo arreglo para almacenar aulas

try {
    // [NUEVO CÓDIGO] Obtener el año escolar activo actual
    $stmtAnioActivo = $pdo->query("SELECT id, nombre FROM anios_escolares WHERE activo = 1 LIMIT 1");
    $anio_activo = $stmtAnioActivo->fetch(PDO::FETCH_ASSOC);
    $anio_activo_id = $anio_activo['id'] ?? null;
    $anio_activo_nombre = $anio_activo['nombre'] ?? 'Ninguno Activo';

    // Obtener solo el año escolar activo para el filtro/selección
    $anios_escolares = $anio_activo ? [$anio_activo] : []; 
    
    // Si hay un año activo, cargamos las aulas asignadas a ese año
    if ($anio_activo_id) {
        $stmtAulas = $pdo->prepare("
            SELECT aa.id, aa.nombre_aula, aa.turno 
            FROM asignaciones_aula aa
            WHERE aa.anio_escolar_id = :anio_id AND aa.activo = 1
            ORDER BY aa.nombre_aula
        ");
        $stmtAulas->execute([':anio_id' => $anio_activo_id]);
        $aulas_disponibles = $stmtAulas->fetchAll();
    }
    
    // Obtener todos los estudiantes activos (para el reporte individual, si se mantiene)
    $stmtEstudiantes = $pdo->query("SELECT id, cedula, nombre, apellido FROM estudiantes WHERE activo = 1 ORDER BY apellido, nombre");
    $estudiantes = $stmtEstudiantes->fetchAll();

// Si se seleccionó un AULA (Reporte Masivo)
if ($aula_seleccionada_id) {
    // Asegurar que el año activo sea el año de la boleta
    $anio_escolar_seleccionado_id = $anio_activo_id;
    
    // Cargar todos los estudiantes inscritos en esa aula y año activo
    $sqlReporteAula = "
        SELECT
            e.id AS estudiante_id,
            e.nombre AS estudiante_nombre,
            e.apellido AS estudiante_apellido,
            e.cedula AS estudiante_cedula,
            i.id AS inscripcion_id,
            r.nombre AS representante_nombre,
            r.apellido AS representante_apellido,
            aa.nombre_aula,
            aa.turno,
            ae.nombre AS anio_nombre,
            n.momento1_nota,
            n.momento2_nota,
            n.momento3_nota,
            n.literal
        FROM
            inscripciones i
        JOIN
            estudiantes e ON i.estudiante_id = e.id
        LEFT JOIN
            representantes r ON e.representante_id = r.id
        JOIN
            asignaciones_aula aa ON i.aula_id = aa.id
        JOIN
            anios_escolares ae ON i.anio_escolar_id = ae.id
        LEFT JOIN
            notas n ON i.id = n.inscripcion_id
        WHERE
            i.aula_id = :aula_id
            AND i.anio_escolar_id = :anio_escolar_id 
        ORDER BY e.apellido, e.nombre
    ";

    $stmtReporte = $pdo->prepare($sqlReporteAula);
    $stmtReporte->execute([
        ':aula_id' => $aula_seleccionada_id,
        ':anio_escolar_id' => $anio_escolar_seleccionado_id
    ]);
    
    // Almacenar todos los reportes, no solo uno
    $reportes_masivos = $stmtReporte->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);

    // Si encontramos datos, reorganizamos para la vista
    if (!empty($reportes_masivos)) {
        // En este caso, $reporte_data será un arreglo de estudiantes. 
        // La vista HTML necesitará iterar sobre $reportes_masivos.
    } else {
        $message = "No hay estudiantes inscritos en el aula seleccionada para el año activo.";
        $message_type = 'info';
    }

}

    if ($estudiante_seleccionado_id && $anio_escolar_seleccionado_id) {
        $sqlReporte = "
            SELECT
                e.nombre AS estudiante_nombre,
                e.apellido AS estudiante_apellido,
                e.cedula,
                ae.nombre AS anio_escolar_nombre,
                aa.nombre_aula AS aula_nombre,
                aa.turno,
                doc.nombre AS docente_nombre,
                doc.apellido AS docente_apellido,
                n.momento1_nota,
                n.momento2_nota,
                n.momento3_nota,
                n.literal,
                i.fecha_inscripcion
            FROM
                inscripciones i
            JOIN
                estudiantes e ON i.estudiante_id = e.id
            JOIN
                anios_escolares ae ON i.anio_escolar_id = ae.id
            JOIN
                asignaciones_aula aa ON i.aula_id = aa.id
            LEFT JOIN
                usuarios doc ON aa.docente_id = doc.id -- LEFT JOIN por si no hay docente asignado aún
            LEFT JOIN
                notas n ON i.id = n.inscripcion_id -- LEFT JOIN por si aún no hay notas
            WHERE
                e.id = :estudiante_id AND ae.id = :anio_escolar_id
                AND i.activo = 1 AND aa.activo = 1 AND ae.activo = 1
            LIMIT 1; -- Solo esperamos una inscripción por estudiante por año y aula
        ";
        $stmtReporte = $pdo->prepare($sqlReporte);
        $stmtReporte->execute([
            ':estudiante_id' => $estudiante_seleccionado_id,
            ':anio_escolar_id' => $anio_escolar_seleccionado_id
        ]);
        $reporte_data = $stmtReporte->fetch(PDO::FETCH_ASSOC);

        if (!$reporte_data) {
            $message = "No se encontró información de inscripción o notas para el estudiante en el año escolar seleccionado.";
            $message_type = 'warning';
        }
    }

} catch (PDOException $e) {
    $message = "Error al cargar los datos: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reporte Académico - Cecilia Bazán de Segura</title>
    <link rel="stylesheet" href="bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/PlantillaCss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos para impresión */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 10pt;
                -webkit-print-color-adjust: exact !important; /* Para imprimir colores de fondo */
                print-color-adjust: exact !important;
            }
            .card, .container, .table {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .report-card-container {
                width: 100%;
                margin: 0 auto;
                border: 1px solid #ccc;
                padding: 20px;
                background-color: #fff;
            }
            .report-card-header, .report-card-section {
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
                margin-bottom: 10px;
            }
            .report-card-header img {
                max-height: 80px;
            }
            .report-card-header h2, .report-card-header h4 {
                color: #0056b3 !important; /* Fuerza el color azul para impresión */
            }
            .grade-box {
                border: 1px solid #ccc;
                padding: 10px;
                margin-bottom: 10px;
            }
            .signature-line {
                border-top: 1px solid #000;
                width: 70%;
                margin: 0 auto;
                margin-top: 50px;
                padding-top: 5px;
                text-align: center;
            }
        }
        /* Estilos en pantalla */
        .report-card-container {
            border: 1px solid #e0e0e0;
            padding: 25px;
            background-color: #fdfdfd;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        .report-card-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .report-card-header img {
            max-height: 100px;
            margin-bottom: 15px;
        }
        .report-card-header h2 {
            font-weight: bold;
            color: #0056b3;
        }
        .report-card-header h4 {
            color: #0056b3;
            margin-top: 10px;
        }
        .student-info, .grades-section {
            margin-bottom: 25px;
            border: 1px solid #eee;
            padding: 15px;
            border-radius: 5px;
            background-color: #fff;
        }
        .student-info p, .grades-section p {
            margin-bottom: 8px;
        }
        .grade-box {
            border: 1px solid #d0d0d0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }
        .grade-box h5 {
            color: #007bff;
            margin-bottom: 10px;
        }
        .grade-box p {
            white-space: pre-wrap; /* Para preservar saltos de línea en descripciones */
        }
        .literal-grade {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
            text-align: center;
            padding: 10px;
            border: 2px solid #28a745;
            border-radius: 5px;
            background-color: #e6ffe6;
        }
        .signature-section {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        .signature-line {
            margin-top: 60px;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <header class="container-fluid bg-light shadow-sm py-2 no-print">
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
        <h1 class="text-primary mb-4 no-print">Generar Reporte Académico</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show no-print" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4 no-print">
            <div class="card-header bg-primary text-white">
                Filtros del Reporte
            </div>
            <div class="card-body">
                <form action="reporte_academico.php" method="GET" class="mb-4 no-print">
                    <h4 class="mb-3 text-secondary">Generar Boleta</h4>
                    
                    <div class="row g-3 align-items-end">
                        
                        <div class="col-md-5">
                            <label for="aula_id" class="form-label">Generar Reporte por Aula Activa:</label>
                            <select name="aula_id" id="aula_id" class="form-select">
                                <option value="">Seleccione un Aula...</option>
                                <?php foreach ($aulas_disponibles as $aula): ?>
                                    <option value="<?php echo htmlspecialchars($aula['id']); ?>" <?php echo ($aula['id'] == $aula_seleccionada_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($aula['nombre_aula'] . ' (' . $aula['turno'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <label for="estudiante_id" class="form-label">Opcional: Estudiante (Individual)</label>
                            <select name="estudiante_id" id="estudiante_id" class="form-select">
                                <option value="">Seleccione un Estudiante...</option>
                                <?php foreach ($estudiantes as $est): ?>
                                    <option value="<?php echo htmlspecialchars($est['id']); ?>" <?php echo ($est['id'] == $estudiante_seleccionado_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($est['apellido'] . ' ' . $est['nombre'] . ' (' . $est['cedula'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2" style="display:none;"> 
                            <label for="anio_escolar_id" class="form-label">Año Escolar (Activo):</label>
                            <select name="anio_escolar_id" id="anio_escolar_id" class="form-select">
                                <?php if ($anio_activo_id): ?>
                                    <option value="<?php echo htmlspecialchars($anio_activo_id); ?>" selected>
                                        <?php echo htmlspecialchars($anio_activo_nombre); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-file-pdf"></i> Generar</button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <?php if ($reporte_data): ?>
            <div class="report-card-container mt-4">
                <div class="report-card-header">
                    <img src="img/MPPEducacion.png" alt="Institución Bolivariana" class="img-fluid" />
                    <h2>Unidad Educativa Bolivariana</h2>
                    <h4>"Cecilia Bazán de Segura"</h4>
                    <p class="mb-0">**BOLETA DE CALIFICACIONES**</p>
                    <p class="mb-0">**Año Escolar: <?php echo htmlspecialchars($reporte_data['anio_escolar_nombre']); ?>**</p>
                </div>

                <div class="student-info">
                    <h5 class="text-primary mb-3">Datos del Estudiante y Aula</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Estudiante:</strong> <?php echo htmlspecialchars($reporte_data['estudiante_nombre'] . ' ' . $reporte_data['estudiante_apellido']); ?></p>
                            <p><strong>Cédula:</strong> <?php echo htmlspecialchars($reporte_data['cedula']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Aula/Sección:</strong> <?php echo htmlspecialchars($reporte_data['aula_nombre']); ?></p>
                            <p><strong>Turno:</strong> <?php echo htmlspecialchars($reporte_data['turno']); ?></p>
                            <p><strong>Docente de Aula:</strong> <?php echo htmlspecialchars($reporte_data['docente_nombre'] . ' ' . $reporte_data['docente_apellido']); ?></p>
                            <p><strong>Fecha de Inscripción:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($reporte_data['fecha_inscripcion']))); ?></p>
                        </div>
                    </div>
                </div>

                <div class="grades-section">
                    <h5 class="text-success mb-3">Calificaciones</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="grade-box">
                                <h5>Momento 1</h5>
                                <p><?php echo htmlspecialchars($reporte_data['momento1_nota'] ?? 'Pendiente'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="grade-box">
                                <h5>Momento 2</h5>
                                <p><?php echo htmlspecialchars($reporte_data['momento2_nota'] ?? 'Pendiente'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="grade-box">
                                <h5>Momento 3</h5>
                                <p><?php echo htmlspecialchars($reporte_data['momento3_nota'] ?? 'Pendiente'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Literal Final:</h5>
                            <div class="literal-grade">
                                <?php echo htmlspecialchars($reporte_data['literal'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="signature-section text-center">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="signature-line">
                                Director(a)
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="signature-line">
                                Docente de Aula
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="signature-line">
                                Representante
                            </div>
                        </div>
                    </div>
                    <p class="mt-4">Fecha de Emisión: <?php echo date('d/m/Y'); ?></p>
                </div>
            </div>
            <div class="text-end mt-4 no-print">
                <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir Boleta</button>
            </div>
        <?php elseif ($estudiante_seleccionado_id && $anio_escolar_seleccionado_id && empty($reporte_data) && empty($message)): ?>
            <div class="alert alert-info mt-4" role="alert">
                No se encontró una inscripción para el estudiante en el año escolar seleccionado.
            </div>
        <?php endif; ?>

    <?php if (!empty($reportes_masivos)): ?>
        <h3 class="text-center mb-4">Reporte Académico por Aula</h3>
        <h4 class="text-center text-primary mb-4"><?php echo htmlspecialchars($reportes_masivos[array_key_first($reportes_masivos)][0]['nombre_aula']); ?> - <?php echo htmlspecialchars($anio_activo_nombre); ?></h4>

        <?php 
        // Iterar sobre cada estudiante dentro del reporte masivo.
        // La clave es el ID del estudiante, el valor es el arreglo de sus datos.
        foreach ($reportes_masivos as $estudiante_id => $data_estudiante): 
            $reporte_data = $data_estudiante[0]; // Tomar el primer registro para datos generales
        ?>
        
            <div class="card shadow-lg mb-5 boleta-container">
                <div class="card-header bg-primary text-white text-center">
                    <h5 class="mb-0">Boleta de Calificaciones</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Estudiante:</strong> <?php echo htmlspecialchars($reporte_data['estudiante_apellido'] . ' ' . $reporte_data['estudiante_nombre']); ?></p>
                            <p><strong>Cédula:</strong> <?php echo htmlspecialchars($reporte_data['estudiante_cedula']); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p><strong>Aula:</strong> <?php echo htmlspecialchars($reporte_data['nombre_aula']); ?> (<?php echo htmlspecialchars($reporte_data['turno']); ?>)</p>
                            <p><strong>Año Escolar:</strong> <?php echo htmlspecialchars($reporte_data['anio_nombre']); ?></p>
                        </div>
                    </div>
                    
                    <table class="table table-bordered table-sm table-notas mt-4">
                        <thead class="table-dark">
                            <tr>
                                <th>Momento</th>
                                <th>Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Momento 1</td>
                                <td><?php echo htmlspecialchars($reporte_data['momento1_nota'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td>Momento 2</td>
                                <td><?php echo htmlspecialchars($reporte_data['momento2_nota'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr>
                                <td>Momento 3</td>
                                <td><?php echo htmlspecialchars($reporte_data['momento3_nota'] ?? 'N/A'); ?></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-info">
                            <tr>
                                <td>**Literal Final**</td>
                                <td>**<?php echo htmlspecialchars($reporte_data['literal'] ?? 'N/A'); ?>**</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="row mt-5">
                        <div class="col-md-4 offset-md-2 text-center">
                            <div class="signature-line">
                                Docente de Aula
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="signature-line">
                                Representante: <?php echo htmlspecialchars($reporte_data['representante_apellido'] . ' ' . $reporte_data['representante_nombre'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4">Fecha de Emisión: <?php echo date('d/m/Y'); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        
        <div class="text-end mt-4 no-print">
            <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir Reporte de Aula</button>
        </div>

    <?php 
    // Si no hay reporte masivo, se verifica si hay reporte individual (código original)
    elseif ($reporte_data): ?>
        <?php else: ?>
        <div class="alert alert-info mt-4" role="alert">
            Seleccione un aula para generar el reporte de boletas, o un estudiante para la boleta individual.
        </div>
    <?php endif; ?>

    </main>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your_fontawesome_kit_code.js" crossorigin="anonymous"></script>
</body>
</html>