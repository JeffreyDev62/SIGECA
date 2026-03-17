<?php
session_start();
require_once 'includes/db.php';

// Función de validación de contraseña (REUTILIZABLE)
function validarPassword($password) {
    // Verifica que la longitud de la contraseña sea de al menos 8 caracteres.
    return (strlen($password) >= 8);
}

// --- Control de Acceso: Solo docentes ---
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'docente') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $especialidad = trim($_POST['especialidad'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validaciones básicas
    if (empty($nombre) || empty($apellido) || empty($correo)) {
        $message = "Nombre, apellido y correo electrónico son obligatorios.";
        $message_type = 'danger';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $message = "El correo electrónico no es válido.";
        $message_type = 'danger';
    } elseif (!empty($password) && $password !== $confirm_password) {
        $message = "Las contraseñas no coinciden.";
        $message_type = 'danger';
    } 
     // --- INICIO CÓDIGO NUEVO: VALIDACIÓN DE REGLA ---
    elseif (!empty($password) && !validarPassword($password)) {
        $message = "Introduzca contraseña valida con minimo de 8 caracteres";
        $message_type = 'danger';
    } 
    // --- FIN CÓDIGO NUEVO ---
    else {
        try {
            // Verificar si el nuevo correo ya existe para otro usuario
            $sqlCheckCorreo = "SELECT COUNT(*) FROM usuarios WHERE correo = :correo AND id != :id";
            $stmtCheckCorreo = $pdo->prepare($sqlCheckCorreo);
            $stmtCheckCorreo->execute([':correo' => $correo, ':id' => $user_id]);
            if ($stmtCheckCorreo->fetchColumn() > 0) {
                $message = "El correo electrónico ya está registrado por otro usuario.";
                $message_type = 'danger';
            } else {
                // Construir la consulta de actualización
                $sql = "UPDATE usuarios SET nombre = :nombre, apellido = :apellido, correo = :correo, telefono = :telefono, direccion = :direccion, especialidad = :especialidad";
                $params = [
                    ':nombre' => $nombre,
                    ':apellido' => $apellido,
                    ':correo' => $correo,
                    ':telefono' => $telefono,
                    ':direccion' => $direccion,
                    ':especialidad' => $especialidad, // La especialidad es editable para el docente
                    ':id' => $user_id
                ];

                if (!empty($password)) {
                    $sql .= ", password = :password";
                    $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql .= " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Actualizar la sesión si el nombre o apellido cambiaron
                $_SESSION['nombre_usuario'] = $nombre . ' ' . $apellido; // Actualizar el nombre mostrado en el header si es necesario

                $message = "Tu perfil ha sido actualizado correctamente.";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = "Error al actualizar tu perfil: " . $e->getMessage();
            $message_type = 'danger';
            // error_log($e->getMessage()); // Para depuración en producción
        }
    }
} else {
    $message = "Método de solicitud no permitido.";
    $message_type = 'danger';
}

$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: perfil_docente.php'); // Redirigir de vuelta al perfil
exit();
?>