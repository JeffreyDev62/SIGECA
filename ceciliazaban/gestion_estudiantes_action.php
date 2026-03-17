<?php
session_start();
require_once 'includes/db.php';

// Control de Acceso: Solo administradores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$action = $_REQUEST['action'] ?? '';
$id = $_REQUEST['id'] ?? null;
$message = '';
$message_type = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $estudiante_id = $_POST['id'] ?? null;

        // Datos del Estudiante
        $cedula_estudiante = trim($_POST['cedula_estudiante'] ?? '');
        $nombre_estudiante = trim($_POST['nombre_estudiante'] ?? '');
        $apellido_estudiante = trim($_POST['apellido_estudiante'] ?? '');
        $edad = $_POST['edad_estudiante'] ?? null; // Recibimos la edad calculada
        $genero = $_POST['genero'] ?? null;
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
        $lugar_nacimiento = trim($_POST['lugar_nacimiento'] ?? '');
        $estudiante_activo = isset($_POST['activo']) ? 1 : 0;

        // Datos del Representante (pueden ser existentes o nuevos)
        $representante_id = $_POST['representante_id'] ?? null;

        $rep_cedula = trim($_POST['rep_cedula'] ?? '');
        $rep_nombre = trim($_POST['rep_nombre'] ?? '');
        $rep_apellido = trim($_POST['rep_apellido'] ?? '');
        $rep_telefono = trim($_POST['rep_telefono'] ?? '');
        $rep_ocupacion = trim($_POST['rep_ocupacion'] ?? '');

        // Validaciones de Estudiante
        if (empty($cedula_estudiante) || empty($nombre_estudiante) || empty($apellido_estudiante)) {
            $message = "Los campos de Cédula, Nombre y Apellido del Estudiante son obligatorios.";
            $message_type = 'danger';
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = $message_type;
            header('Location: gestion_estudiantes.php');
            exit();
        }

        // Validaciones para el Representante
        if (empty($representante_id)) { // Si no se seleccionó un representante existente, se debe crear uno nuevo
            if (empty($rep_cedula) || empty($rep_nombre) || empty($rep_apellido)) {
                $message = "Si no selecciona un representante existente, la Cédula, Nombre y Apellido del nuevo Representante son obligatorios.";
                $message_type = 'danger';
                $_SESSION['message'] = $message;
                $_SESSION['message_type'] = $message_type;
                header('Location: gestion_estudiantes.php');
                exit();
            }
        }

        $pdo->beginTransaction(); // Iniciar transacción para asegurar la consistencia

        // 1. Manejar el Representante
        if (!empty($representante_id)) {
            // Se seleccionó un representante existente.
            // En este caso, no se modifica el representante.
        } else {
            // Se va a crear un nuevo representante (o usar uno existente si la cédula coincide)
            // Verificar si el representante ya existe por cédula
            $stmtCheckRep = $pdo->prepare("SELECT id FROM representantes WHERE cedula = :cedula");
            $stmtCheckRep->execute([':cedula' => $rep_cedula]);
            $existing_rep = $stmtCheckRep->fetchColumn();

            if ($existing_rep) {
                $representante_id = $existing_rep;
                // Si existe, se podría considerar actualizar sus datos (nombre, apellido, etc.) aquí si fuera necesario.
                // Por ahora, solo usamos el ID existente.
            } else {
                // Insertar nuevo representante
                $stmtInsertRep = $pdo->prepare("INSERT INTO representantes (cedula, nombre, apellido, telefono, ocupacion) VALUES (:cedula, :nombre, :apellido, :telefono, :ocupacion)");
                $stmtInsertRep->execute([
                    ':cedula' => $rep_cedula,
                    ':nombre' => $rep_nombre,
                    ':apellido' => $rep_apellido,
                    ':telefono' => $rep_telefono,
                    ':ocupacion' => $rep_ocupacion
                ]);
                $representante_id = $pdo->lastInsertId();
            }
        }

        // 2. Manejar el Estudiante
        // Verificar si la cédula del estudiante ya existe
        $sqlCheckEstudiante = "SELECT COUNT(*) FROM estudiantes WHERE cedula = :cedula";
        $paramsCheckEstudiante = [':cedula' => $cedula_estudiante];
        if ($estudiante_id) {
            $sqlCheckEstudiante .= " AND id != :id";
            $paramsCheckEstudiante[':id'] = $estudiante_id;
        }
        $stmtCheckEstudiante = $pdo->prepare($sqlCheckEstudiante);
        $stmtCheckEstudiante->execute($paramsCheckEstudiante);

        if ($stmtCheckEstudiante->fetchColumn() > 0) {
            $pdo->rollBack(); // Revertir si la cédula del estudiante ya existe
            $message = "Ya existe un estudiante con esa cédula.";
            $message_type = 'danger';
        } else {
            if ($estudiante_id) {
                // Editar Estudiante
                $stmt = $pdo->prepare("UPDATE estudiantes SET cedula = :cedula, nombre = :nombre, apellido = :apellido, edad = :edad, genero = :genero, fecha_nacimiento = :fecha_nacimiento, lugar_nacimiento = :lugar_nacimiento, representante_id = :representante_id, activo = :activo WHERE id = :id");
                $stmt->execute([
                    ':cedula' => $cedula_estudiante,
                    ':nombre' => $nombre_estudiante,
                    ':apellido' => $apellido_estudiante,
                    ':edad' => $edad,
                    ':genero' => $genero,
                    ':fecha_nacimiento' => $fecha_nacimiento,
                    ':lugar_nacimiento' => $lugar_nacimiento,
                    ':representante_id' => $representante_id,
                    ':activo' => $estudiante_activo,
                    ':id' => $estudiante_id
                ]);
                $message = "Estudiante actualizado correctamente.";
                $message_type = 'success';
            } else {
                // Añadir Nuevo Estudiante
                $stmt = $pdo->prepare("INSERT INTO estudiantes (cedula, nombre, apellido, edad, genero, fecha_nacimiento, lugar_nacimiento, representante_id, activo) VALUES (:cedula, :nombre, :apellido, :edad, :genero, :fecha_nacimiento, :lugar_nacimiento, :representante_id, 1)"); // Por defecto activo
                $stmt->execute([
                    ':cedula' => $cedula_estudiante,
                    ':nombre' => $nombre_estudiante,
                    ':apellido' => $apellido_estudiante,
                    ':edad' => $edad,
                    ':genero' => $genero,
                    ':fecha_nacimiento' => $fecha_nacimiento,
                    ':lugar_nacimiento' => $lugar_nacimiento,
                    ':representante_id' => $representante_id
                ]);
                $message = "Estudiante añadido correctamente.";
                $message_type = 'success';
            }
            $pdo->commit(); // Confirmar la transacción
        }

    } elseif ($action === 'toggle_status' && $id) {
        $new_status = $_GET['status'] ?? null;

        if ($new_status === '0' || $new_status === '1') {
            $stmt = $pdo->prepare("UPDATE estudiantes SET activo = :activo WHERE id = :id");
            $stmt->execute([':activo' => $new_status, ':id' => $id]);
            $message = "Estado del estudiante actualizado correctamente.";
            $message_type = 'success';
        } else {
            $message = "Acción inválida para cambiar el estado del estudiante.";
            $message_type = 'danger';
        }
    }
} catch (PDOException $e) {
    $pdo->rollBack(); // Revertir en caso de error
    $message = "Error en la operación de base de datos: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración en producción
}

$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: gestion_estudiantes.php');
exit();
?>