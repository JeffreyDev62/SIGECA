<?php
// Incluimos el archivo de conexión a la base de datos
require_once 'includes/db.php';

// Iniciar la sesión
session_start();
// --- INICIO CÓDIGO NUEVO: FUNCIÓN DE VALIDACIÓN ---
function validarPassword($password) {
    return (strlen($password) >= 8);
}
// --- FIN CÓDIGO NUEVO ---
// Si el usuario ya está logueado, redirigir a su dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['rol'] === 'admin') {
        header('Location: dashboard_admin.php');
    } elseif ($_SESSION['rol'] === 'docente') {
        header('Location: dashboard_docente.php');
    }
    exit();
}

$error_message = ''; // Variable para almacenar mensajes de error

// Procesar el formulario cuando se envía (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($correo) || empty($password)) {
        $error_message = "Por favor, ingresa tu correo y contraseña.";
    } 
    // --- INICIO CÓDIGO NUEVO: VALIDACIÓN DE REGLA ---
    elseif (!validarPassword($password)) {
        $error_message = "Introduzca contraseña válida con minimo de 8 caracteres";
    } 
    // --- FIN CÓDIGO NUEVO ---
    else {
        try {
            // Preparar la consulta SQL para evitar inyecciones SQL
            $stmt = $pdo->prepare("SELECT id, nombre, apellido, password, rol, activo FROM usuarios WHERE correo = :correo");
            $stmt->execute(['correo' => $correo]);
            $user = $stmt->fetch();

            // Verificar si se encontró el usuario y la contraseña es correcta
            if ($user && password_verify($password, $user['password'])) {
                // *** NUEVA VERIFICACIÓN DE ESTADO ACTIVO ***
                if ($user['activo'] == 0) {
                    $error_message = "Tu cuenta ha sido desactivada. Por favor, contacta al administrador.";
                } else {
                    // Contraseña válida y usuario activo, iniciar sesión
                    $_SESSION['user_id'] = $user['id'];
                    // Puedes guardar el nombre completo si lo necesitas
                    $_SESSION['nombre_usuario'] = $user['nombre'] . ' ' . $user['apellido'];
                    $_SESSION['rol'] = $user['rol'];

                    // Redirigir al dashboard según el rol
                    if ($user['rol'] === 'admin') {
                        header('Location: dashboard_admin.php');
                    } elseif ($user['rol'] === 'docente') {
                        header('Location: dashboard_docente.php');
                    }
                    exit();
                }
            } else {
                $error_message = "Correo electrónico o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            // En un entorno de producción, loggea el error en lugar de mostrarlo
            $error_message = "Error en la base de datos. Por favor, inténtalo de nuevo más tarde.";
            // error_log($e->getMessage()); // Descomentar en producción para registrar errores
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Cecilia Bazán de Segura - Iniciar Sesión</title>
    <link rel="stylesheet" href="bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/PlantillaCss.css">
    <style>
        /* Estilos específicos para la página de login */
        body {
            background-color: #f8f9fa; /* Color de fondo claro */
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .login-container img {
            max-width: 120px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container text-center">
            <h2 class="mb-4 text-primary">SIGECA</h2>
            <img src="img/LogoCeciliaBazanSegura.png" alt="Logo Institución" class="img-fluid mb-4">
            <h2 class="mb-4 text-primary">Inicio de Sesión</h2>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-3 text-start">
                    <label for="correo" class="form-label">Correo Electrónico:</label>
                    <input type="email" class="form-control" id="correo" name="correo" required autocomplete="email">
                </div>
                <div class="mb-4 text-start">
                    <label for="password" class="form-label">Contraseña:</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>
            <p class="mt-3 text-muted">Bienvenido de Nuevo</p>
        </div>
    </div>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>