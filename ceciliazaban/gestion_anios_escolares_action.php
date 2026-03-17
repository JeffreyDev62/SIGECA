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
        $id = $_POST['id'] ?? null;
        $nombre = trim($_POST['nombre'] ?? '');
        $fecha_inicio = $_POST['fecha_inicio'] ?? null;
        $fecha_fin = $_POST['fecha_fin'] ?? null;
        $activo = isset($_POST['activo']) ? 1 : 0; // Si el checkbox está marcado, es 1, sino 0

        if (empty($nombre)) {
            $message = "El nombre del Año Escolar es obligatorio.";
            $message_type = 'danger';
        } else {
            // Verificar si el nombre del año ya existe
            $sqlCheck = "SELECT COUNT(*) FROM anios_escolares WHERE nombre = :nombre";
            $paramsCheck = [':nombre' => $nombre];
            if ($id) {
                $sqlCheck .= " AND id != :id";
                $paramsCheck[':id'] = $id;
            }
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute($paramsCheck);

            if ($stmtCheck->fetchColumn() > 0) {
                $message = "Ya existe un Año Escolar con ese nombre.";
                $message_type = 'danger';
            } else {
                if ($id) {
                    // Lógica para UPDATE (Edición)

                // ====================================================================
                // [NUEVO CÓDIGO] REGLA DE NEGOCIO: Si se activa este año, desactivar los demás.
                // ====================================================================
                if ($activo == 1) {
                    $stmt_desactivar = $pdo->prepare("UPDATE anios_escolares SET activo = 0 WHERE activo = 1 AND id != :current_id");
                    $stmt_desactivar->execute([':current_id' => $id]);
                }
                // ====================================================================

                $sql = "UPDATE anios_escolares SET nombre = :nombre, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, activo = :activo WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':fecha_inicio' => $fecha_inicio,
                    ':fecha_fin' => $fecha_fin,
                    ':activo' => $activo,
                    ':id' => $id
                ]);

                $message = "Año Escolar actualizado correctamente.";
                if ($activo == 1) {
                    $message .= " Este año ha sido activado y los otros, desactivados.";
                }
                $message_type = 'success';

               } else { // Es una inserción (NUEVO AÑO ESCOLAR)
            
            // ==========================================================
            // [NUEVO CÓDIGO] 1. Desactivar todos los años escolares existentes
            // ==========================================================
            $stmt_desactivar = $pdo->prepare("UPDATE anios_escolares SET activo = 0 WHERE activo = 1");
            $stmt_desactivar->execute();
            // ==========================================================

            // 2. Insertar el nuevo año escolar y establecerlo como activo
            $stmt = $pdo->prepare("INSERT INTO anios_escolares (nombre, fecha_inicio, fecha_fin, activo) VALUES (:nombre, :fecha_inicio, :fecha_fin, 1)"); 
            $stmt->execute([
                ':nombre' => $nombre,
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => $fecha_fin
            ]);
            
            $message = "Año Escolar añadido y establecido como activo. Los años anteriores han sido desactivados.";
            $message_type = 'success';
        }
            }
        }
   } elseif ($action === 'toggle_status' && $id) {
        $new_status = $_GET['status'] ?? null;

        if ($new_status === '0' || $new_status === '1') {
            
            // ====================================================================
            // [NUEVO CÓDIGO] REGLA DE NEGOCIO: Si se está activando, desactivar los demás.
            // ====================================================================
            if ($new_status === '1') {
                // Desactivar todos los años que actualmente estén activos y que no sean el ID actual
                $stmt_desactivar = $pdo->prepare("UPDATE anios_escolares SET activo = 0 WHERE activo = 1 AND id != :current_id");
                $stmt_desactivar->execute([':current_id' => $id]);
            }
            // ====================================================================

            // 1. Actualizar el estado del año escolar seleccionado
            $stmt = $pdo->prepare("UPDATE anios_escolares SET activo = :activo WHERE id = :id");
            $stmt->execute([':activo' => $new_status, ':id' => $id]);
            
            $message = "Estado del Año Escolar actualizado correctamente.";
            if ($new_status === '1') {
                $message .= " Este año ha sido activado y los otros, desactivados.";
            }
            $message_type = 'success';
        } else {
            $message = "Acción inválida para cambiar el estado del año escolar.";
            $message_type = 'danger';
        }
    }
} catch (PDOException $e) {
    $message = "Error en la operación de base de datos: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración en producción
}

$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: gestion_anios_escolares.php');
exit();
?>