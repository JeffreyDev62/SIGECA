<?php
session_start();
require_once 'includes/db.php';

// --- Control de Acceso: Solo docentes ---
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']); // Limpiar mensajes después de mostrarlos

$docente_data = [];
try {
    $stmt = $pdo->prepare("SELECT cedula, nombre, apellido, correo, telefono, direccion, especialidad FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $docente_data = $stmt->fetch();

    if (!$docente_data) {
        // Esto no debería pasar si el usuario está logueado, pero es una buena práctica
        $_SESSION['message'] = "No se encontraron tus datos de perfil.";
        $_SESSION['message_type'] = 'danger';
        header('Location: dashboard_docente.php');
        exit();
    }

} catch (PDOException $e) {
    $message = "Error al cargar tus datos de perfil: " . $e->getMessage();
    $message_type = 'danger';
    // En un entorno real, aquí se usaría error_log para registrar el error
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Perfil del Docente - Cecilia Bazán de Segura</title>
    <link rel="stylesheet" href="bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/PlantillaCss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="container-fluid bg-light shadow-sm py-2">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="dashboard_docente.php">
                    <img
                        src="img/MPPEducacion.png"
                        alt="Institución Bolivariana"
                        class="img-fluid"
                        style="max-height: 50px;"
                    />
                    <span class="ms-3 h4 mb-0 text-primary d-none d-md-block">Cecilia Bazán de Segura</span>
                </a>

                <button
                    class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarNav"
                    aria-controls="navbarNav"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                >
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
                        <img
                            src="img/LogoCeciliaBazanSegura.png"
                            alt="Logo Cecilia Bazán Segura"
                            class="img-fluid"
                        />
                    </div>
                </a>
            </div>
        </nav>
    </header>

    <main class="container my-4">
        <h1 class="text-primary mb-4">Mi Perfil</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm p-4">
            <div class="card-body">
                <form action="perfil_docente_action.php" method="POST">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_id); ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cedula" class="form-label">Cédula:</label>
                            <input type="text" class="form-control" id="cedula" value="<?php echo htmlspecialchars($docente_data['cedula'] ?? ''); ?>" readonly>
                            <small class="form-text text-muted">La cédula no puede ser modificada.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre:</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($docente_data['nombre'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="apellido" class="form-label">Apellido:</label>
                            <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo htmlspecialchars($docente_data['apellido'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="correo" class="form-label">Correo Electrónico:</label>
                            <input type="email" class="form-control" id="correo" name="correo" value="<?php echo htmlspecialchars($docente_data['correo'] ?? ''); ?>" required>
                            <small class="form-text text-muted">Tu correo es tu usuario para iniciar sesión.</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Número Telefónico:</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars($docente_data['telefono'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="direccion" class="form-label">Dirección:</label>
                            <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($docente_data['direccion'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="especialidad" class="form-label">Especialidad:</label>
                        <input type="text" class="form-control" id="especialidad" name="especialidad" value="<?php echo htmlspecialchars($docente_data['especialidad'] ?? ''); ?>">
                    </div>

                    <h5 class="mt-4 mb-3">Cambiar Contraseña (opcional)</h5>
                    <div class="mb-3">
    <label for="password" class="form-label">Nueva Contraseña:</label>
    <input type="password" class="form-control" id="password" name="password" minlength="8">
    <small class="form-text text-muted">Dejar vacío si no deseas cambiar tu contraseña. Mínimo 8 caracteres.</small>
</div>
<div class="mb-3">
    <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña:</label>
    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8">
</div>

                    <button type="submit" class="btn btn-primary mt-3">Guardar Cambios</button>
                </form>
            </div>
        </div>
    </main>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your_fontawesome_kit_code.js" crossorigin="anonymous"></script>
</body>
</html>