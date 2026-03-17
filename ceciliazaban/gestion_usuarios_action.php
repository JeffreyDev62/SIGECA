<?php
session_start();
require_once 'includes/db.php';

// Función de validación de contraseña (REUTILIZABLE)
function validarPassword($password) {
    // Verifica que la longitud de la contraseña sea de al menos 8 caracteres.
    return (strlen($password) >= 8);
}

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$action = $_REQUEST['action'] ?? ''; // Puede venir de POST o GET (para eliminar/toggle_status)
$id = $_REQUEST['id'] ?? null;
$message = '';
$message_type = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- Lógica para Añadir/Editar Usuario ---
        $id = $_POST['id'] ?? null;
        $cedula = trim($_POST['cedula'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $especialidad = trim($_POST['especialidad'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'docente'; // Por defecto, es 'docente'

        // Validaciones básicas
        if (empty($cedula) || empty($nombre) || empty($apellido) || empty($correo) || empty($rol)) {
            $message = "Todos los campos obligatorios deben ser rellenados.";
            $message_type = 'danger';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $message = "El correo electrónico no es válido.";
            $message_type = 'danger';
        } else {
            // Verificar si el correo o cédula ya existen (excluyendo al usuario actual si es una edición)
            $sqlCheck = "SELECT COUNT(*) FROM usuarios WHERE (correo = :correo OR cedula = :cedula)";
            if ($id) { // Si es una edición, excluye el ID actual
                $sqlCheck .= " AND id != :id";
            }
            
            // --- INICIO CÓDIGO NUEVO: VALIDACIÓN DE CONTRASEÑA POR REGLA ---
    if (empty($message)) { // Solo si no hay errores previos
        if (!$id) { // Es una nueva inserción, la contraseña es obligatoria y debe ser válida
            if (empty($password)) {
                $message = "La contraseña es obligatoria para un nuevo usuario.";
                $message_type = 'danger';
            } elseif (!validarPassword($password)) {
                $message = "Introduzca contraseña valida con minimo de 8 caracteres";
                $message_type = 'danger';
            }
        } elseif ($id && !empty($password)) { // Es una edición y se está cambiando la contraseña (opcional)
            if (!validarPassword($password)) {
                $message = "Introduzca contraseña valida con minimo de 8 caracteres";
                $message_type = 'danger';
            }
        }
    }
    // --- FIN CÓDIGO NUEVO ---

            $stmtCheck = $pdo->prepare($sqlCheck);
            $paramsCheck = [':correo' => $correo, ':cedula' => $cedula];
            if ($id) {
                $paramsCheck[':id'] = $id;
            }
            $stmtCheck->execute($paramsCheck);
            if ($stmtCheck->fetchColumn() > 0) {
                $message = "La cédula o el correo electrónico ya están registrados.";
                $message_type = 'danger';
            } else {
                if ($id) {
                    // --- Editar Usuario ---
                    $sql = "UPDATE usuarios SET cedula = :cedula, nombre = :nombre, apellido = :apellido, correo = :correo, telefono = :telefono, direccion = :direccion, especialidad = :especialidad, rol = :rol";
                    if (!empty($password)) {
                        $sql .= ", password = :password"; // Añadir campo de password si se va a cambiar
                    }
                    $sql .= " WHERE id = :id";
                    $stmt = $pdo->prepare($sql);

                    $params = [
                        ':cedula' => $cedula,
                        ':nombre' => $nombre,
                        ':apellido' => $apellido,
                        ':correo' => $correo,
                        ':telefono' => $telefono,
                        ':direccion' => $direccion,
                        ':especialidad' => ($rol === 'docente' ? $especialidad : NULL),
                        ':rol' => $rol,
                        ':id' => $id
                    ];
                    if (!empty($password)) {
                        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $stmt->execute($params);
                    $message = "Usuario actualizado correctamente.";
                    $message_type = 'success';
                } else {
                    // --- Añadir Nuevo Usuario ---
                    if (empty($password)) {
                        $message = "La contraseña es obligatoria para un nuevo usuario.";
                        $message_type = 'danger';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO usuarios (cedula, nombre, apellido, correo, telefono, direccion, especialidad, password, rol, activo) VALUES (:cedula, :nombre, :apellido, :correo, :telefono, :direccion, :especialidad, :password, :rol, 1)"); // Por defecto activo
                        $stmt->execute([
                            ':cedula' => $cedula,
                            ':nombre' => $nombre,
                            ':apellido' => $apellido,
                            ':correo' => $correo,
                            ':telefono' => $telefono,
                            ':direccion' => $direccion,
                            ':especialidad' => ($rol === 'docente' ? $especialidad : NULL),
                            ':password' => $hashed_password,
                            ':rol' => $rol
                        ]);
                        $message = "Usuario añadido correctamente.";
                        $message_type = 'success';
                    }
                }
            }
        }
    } elseif ($action === 'toggle_status' && $id) {
        // --- Lógica para Activar/Desactivar Usuario ---
        $new_status = $_GET['status'] ?? null; // Obtener el nuevo estado (0 o 1)

        // Validar que el estado sea 0 o 1 y que no sea el propio usuario logueado
        if (($new_status === '0' || $new_status === '1') && $_SESSION['user_id'] != $id) {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = :activo WHERE id = :id");
            $stmt->execute([':activo' => $new_status, ':id' => $id]);
            $message = "Estado del usuario actualizado correctamente.";
            $message_type = 'success';

            // Si el usuario desactivado era el actualmente logueado (aunque lo evitamos, es buena práctica),
            // se podría forzar un logout. Esto es una medida preventiva adicional.
            // Actualmente, el if($_SESSION['user_id'] != $id) ya lo maneja.
        } elseif ($_SESSION['user_id'] == $id) {
            $message = "No puedes cambiar tu propio estado de actividad desde aquí.";
            $message_type = 'danger';
        } else {
            $message = "Acción inválida para cambiar el estado del usuario.";
            $message_type = 'danger';
        }
    }
    // La acción de eliminación física del usuario ya no se recomienda tan enfáticamente.
    // Si la necesitas, se mantendría aquí como una opción separada de `toggle_status`.
    // Por ahora, eliminamos la lógica de `delete` si se usa `toggle_status` como alternativa.
    /*
    elseif ($action === 'delete' && $id) {
        if ($_SESSION['user_id'] == $id) {
            $message = "No puedes eliminar tu propia cuenta.";
            $message_type = 'danger';
        } else {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = "Usuario eliminado correctamente.";
            $message_type = 'success';
        }
    }
    */
} catch (PDOException $e) {
    $message = "Error en la operación de base de datos: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración en producción
}

$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: gestion_usuarios.php');
exit();
?>