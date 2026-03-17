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
    // [NUEVO CÓDIGO] Obtener el año escolar activo actual
    $stmtAnioActivo = $pdo->query("SELECT id FROM anios_escolares WHERE activo = 1 LIMIT 1");
    $anio_escolar_activo_id = $stmtAnioActivo->fetchColumn();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $anio_escolar_id = $_POST['anio_escolar_id'] ?? null;
        $nombre_aula = trim($_POST['nombre_aula'] ?? '');
        $turno = $_POST['turno'] ?? '';
        $docente_id = $_POST['docente_id'] ?? null;
        $activo = isset($_POST['activo']) ? 1 : 0;

        // Validaciones básicas
        if (empty($anio_escolar_id) || empty($nombre_aula) || empty($turno) || empty($docente_id)) {
            $message = "Todos los campos obligatorios deben ser rellenados.";
            $message_type = 'danger';

        } else {
            // Verificar si ya existe una asignación para el mismo año, aula y turno
            $sqlCheck = "SELECT COUNT(*) FROM asignaciones_aula WHERE anio_escolar_id = :anio_escolar_id AND nombre_aula = :nombre_aula AND turno = :turno";
            $paramsCheck = [
                ':anio_escolar_id' => $anio_escolar_id,
                ':nombre_aula' => $nombre_aula,
                ':turno' => $turno
            ];
            if ($id) {
                $sqlCheck .= " AND id != :id";
                $paramsCheck[':id'] = $id;
            }
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute($paramsCheck);

            if ($stmtCheck->fetchColumn() > 0) {
                $message = "Ya existe una asignación para esta aula y turno en el año escolar seleccionado.";
                $message_type = 'danger';
            } else {
                if ($id) {
                    // Editar Asignación
                    $stmt = $pdo->prepare("UPDATE asignaciones_aula SET anio_escolar_id = :anio_escolar_id, nombre_aula = :nombre_aula, turno = :turno, docente_id = :docente_id, activo = :activo WHERE id = :id");
                    $stmt->execute([
                        ':anio_escolar_id' => $anio_escolar_id,
                        ':nombre_aula' => $nombre_aula,
                        ':turno' => $turno,
                        ':docente_id' => $docente_id,
                        ':activo' => $activo,
                        ':id' => $id
                    ]);
                    $message = "Asignación de aula actualizada correctamente.";
                    $message_type = 'success';
                } else {
                    // Añadir Nueva Asignación
                    $stmt = $pdo->prepare("INSERT INTO asignaciones_aula (anio_escolar_id, nombre_aula, turno, docente_id, activo) VALUES (:anio_escolar_id, :nombre_aula, :turno, :docente_id, 1)"); // Por defecto activa
                    $stmt->execute([
                        ':anio_escolar_id' => $anio_escolar_id,
                        ':nombre_aula' => $nombre_aula,
                        ':turno' => $turno,
                        ':docente_id' => $docente_id
                    ]);
                    $message = "Asignación de aula añadida correctamente.";
                    $message_type = 'success';
                }
            }
        }
    } elseif ($action === 'toggle_status' && $id) {
        $new_status = $_GET['status'] ?? null;

        if ($new_status === '0' || $new_status === '1') {
            $stmt = $pdo->prepare("UPDATE asignaciones_aula SET activo = :activo WHERE id = :id");
            $stmt->execute([':activo' => $new_status, ':id' => $id]);
            $message = "Estado de la asignación de aula actualizado correctamente.";
            $message_type = 'success';
        } else {
            $message = "Acción inválida para cambiar el estado de la asignación.";
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
header('Location: gestion_asignaciones.php');
exit();
?>