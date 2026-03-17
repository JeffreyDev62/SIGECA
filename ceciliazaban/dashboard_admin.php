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

// Lógica para definir el nombre a mostrar en la bienvenida
$welcome_display_name = '';

if (isset($_SESSION['nombre']) && !empty($_SESSION['nombre'])) {
    $welcome_display_name = $_SESSION['nombre'];
    if (isset($_SESSION['apellido']) && !empty($_SESSION['apellido'])) {
        $welcome_display_name .= ' ' . $_SESSION['apellido'];
    }
}

// Si el nombre y apellido no se pudieron obtener, o están vacíos, usar el rol
if (empty($welcome_display_name) && isset($_SESSION['rol']) && !empty($_SESSION['rol'])) {
    $welcome_display_name = ucfirst($_SESSION['rol']); // Pone la primera letra en mayúscula (Ej: "admin" -> "Admin")
} elseif (empty($welcome_display_name)) {
    $welcome_display_name = 'Usuario'; // Fallback genérico si no hay nada
}


// Obtener datos para las estadísticas
$total_usuarios = 0;
$total_estudiantes = 0;
$total_aulas_activas = 0;
$total_anios_escolares_activos = 0;
$total_inscripciones = 0;

try {
    // Total de Usuarios Activos
    $stmt = $pdo->query("SELECT COUNT(id) FROM usuarios WHERE activo = 1");
    $total_usuarios = $stmt->fetchColumn();

    // Total de Estudiantes Activos
    $stmt = $pdo->query("SELECT COUNT(id) FROM estudiantes WHERE activo = 1");
    $total_estudiantes = $stmt->fetchColumn();

    // Total de Aulas Activas
    $stmt = $pdo->query("SELECT COUNT(id) FROM asignaciones_aula WHERE activo = 1");
    $total_aulas_activas = $stmt->fetchColumn();

    // Total de Años Escolares Activos
    $stmt = $pdo->query("SELECT COUNT(id) FROM anios_escolares WHERE activo = 1");
    $total_anios_escolares_activos = $stmt->fetchColumn();

    // Total de Inscripciones
    $stmt = $pdo->query("SELECT COUNT(id) FROM inscripciones WHERE activo = 1");
    $total_inscripciones = $stmt->fetchColumn();

} catch (PDOException $e) {
    $message = "Error al cargar estadísticas: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración en un entorno de producción
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Administrador - Cecilia Bazán de Segura</title>
    <link rel="stylesheet" href="bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/PlantillaCss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .stat-card {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .stat-card .icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 2.5em;
            font-weight: bold;
        }
        .stat-card .label {
            font-size: 1.1em;
            opacity: 0.9;
        }
        .stat-card.bg-primary-gradient { background: linear-gradient(45deg, #007bff, #0056b3); } /* Azul */
        .stat-card.bg-success-gradient { background: linear-gradient(45deg, #28a745, #1e7e34); } /* Verde */
        .stat-card.bg-info-gradient { background: linear-gradient(45deg, #17a2b8, #117a8b); } /* Cian */
        .stat-card.bg-warning-gradient { background: linear-gradient(45deg, #ffc107, #d39e00); } /* Amarillo */
        .stat-card.bg-danger-gradient { background: linear-gradient(45deg, #dc3545, #bd2130); } /* Rojo */

        .quick-links .btn {
            margin: 10px;
            padding: 15px 30px;
            font-size: 1.1em;
            border-radius: 8px;
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

                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'reporte_academico.php') !== false) ? 'active' : ''; ?>" href="reporte_academico.php">Reporte Académico</a>
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
        <h1 class="text-primary mb-4">Bienvenido, <?php echo htmlspecialchars($welcome_display_name); ?>!</h1>
        <p class="lead">Dashboard de Administración</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <section class="statistics mb-5">
            <h2 class="text-secondary mb-4">Estadísticas Clave</h2>
            <div class="row g-4">
                <div class="col-md-4 col-sm-6">
                    <div class="stat-card bg-primary-gradient">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="value"><?php echo $total_usuarios; ?></div>
                        <div class="label">Usuarios Activos</div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="stat-card bg-success-gradient">
                        <div class="icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="value"><?php echo $total_estudiantes; ?></div>
                        <div class="label">Estudiantes Activos</div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="stat-card bg-info-gradient">
                        <div class="icon"><i class="fas fa-chalkboard"></i></div>
                        <div class="value"><?php echo $total_aulas_activas; ?></div>
                        <div class="label">Aulas / Secciones Activas</div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="stat-card bg-warning-gradient">
                        <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="value"><?php echo $total_anios_escolares_activos; ?></div>
                        <div class="label">Años Escolares Activos</div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="stat-card bg-danger-gradient">
                        <div class="icon"><i class="fas fa-file-invoice"></i></div>
                        <div class="value"><?php echo $total_inscripciones; ?></div>
                        <div class="label">Inscripciones Registradas</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="quick-links mb-5">
            <h2 class="text-secondary mb-4">Acciones Rápidas</h2>
            <div class="d-flex flex-wrap justify-content-center">
                <a href="gestion_usuarios.php" class="btn btn-outline-primary btn-lg"><i class="fas fa-user-cog me-2"></i> Gestión de Usuarios</a>
                <a href="gestion_estudiantes.php" class="btn btn-outline-success btn-lg"><i class="fas fa-users-class me-2"></i> Gestión de Estudiantes</a>
                <a href="gestion_asignaciones.php" class="btn btn-outline-info btn-lg"><i class="fas fa-book me-2"></i> Asignación de Aulas</a>
                <a href="reporte_academico.php" class="btn btn-outline-dark btn-lg"><i class="fas fa-chart-bar me-2"></i> Reporte Académico</a>
                <a href="gestion_anios_escolares.php" class="btn btn-outline-warning btn-lg"><i class="fas fa-calendar-check me-2"></i> Años Escolares</a>
            </div>
        </section>

    </main>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>