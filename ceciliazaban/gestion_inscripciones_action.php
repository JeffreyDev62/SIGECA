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
        $inscripcion_id = $_POST['id'] ?? null;
        $estudiante_id = $_POST['estudiante_id'] ?? null;
        $aula_id = $_POST['aula_id'] ?? null;
        $anio_escolar_id = $_POST['anio_escolar_id'] ?? null;
        $activo = isset($_POST['activo']) ? 1 : 0;

        // Si se está editando, los campos de estudiante y año escolar vienen deshabilitados,
        // pero necesitamos sus valores originales para la verificación UNIQUE.
        // Los tomamos de la BD si es una edición.
        if ($inscripcion_id) {
            $stmt = $pdo->prepare("SELECT estudiante_id, anio_escolar_id FROM inscripciones WHERE id = :id");
            $stmt->execute([':id' => $inscripcion_id]);
            $original_inscripcion_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($original_inscripcion_data) {
                $estudiante_id = $original_inscripcion_data['estudiante_id'];
                $anio_escolar_id = $original_inscripcion_data['anio_escolar_id'];
            }
        }


        if (empty($estudiante_id) || empty($aula_id) || empty($anio_escolar_id)) {
            $message = "Todos los campos son obligatorios para la inscripción.";
            $message_type = 'danger';
        } else {
            // Verificar si el estudiante ya está inscrito en el año escolar seleccionado
            $sqlCheck = "SELECT COUNT(*) FROM inscripciones WHERE estudiante_id = :estudiante_id AND anio_escolar_id = :anio_escolar_id";
            $paramsCheck = [
                ':estudiante_id' => $estudiante_id,
                ':anio_escolar_id' => $anio_escolar_id
            ];

            if ($inscripcion_id) { // Si estamos editando, excluimos la inscripción actual de la verificación
                $sqlCheck .= " AND id != :id";
                $paramsCheck[':id'] = $inscripcion_id;
            }

            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute($paramsCheck);

            if ($stmtCheck->fetchColumn() > 0) {
                $message = "El estudiante ya está inscrito en este año escolar. Un estudiante no puede tener múltiples inscripciones en el mismo año escolar.";
                $message_type = 'danger';
            } else {
                if ($inscripcion_id) {
                    // Editar Inscripción
                    // Solo aula_id y activo pueden ser cambiados directamente en una edición,
                    // estudiante_id y anio_escolar_id se mantienen por la restricción UNIQUE.
                    $stmt = $pdo->prepare("UPDATE inscripciones SET aula_id = :aula_id, activo = :activo WHERE id = :id");
                    $stmt->execute([
                        ':aula_id' => $aula_id,
                        ':activo' => $activo,
                        ':id' => $inscripcion_id
                    ]);
                    $message = "Inscripción actualizada correctamente.";
                    $message_type = 'success';
                } else {
                    // Añadir Nueva Inscripción
                    $stmt = $pdo->prepare("INSERT INTO inscripciones (estudiante_id, aula_id, anio_escolar_id, activo) VALUES (:estudiante_id, :aula_id, :anio_escolar_id, 1)");
                    $stmt->execute([
                        ':estudiante_id' => $estudiante_id,
                        ':aula_id' => $aula_id,
                        ':anio_escolar_id' => $anio_escolar_id
                    ]);
                    $message = "Inscripción añadida correctamente.";
                    $message_type = 'success';
                }
            }
        }
    } elseif ($action === 'toggle_status' && $id) {
        $new_status = $_GET['status'] ?? null;

        if ($new_status === '0' || $new_status === '1') {
            $stmt = $pdo->prepare("UPDATE inscripciones SET activo = :activo WHERE id = :id");
            $stmt->execute([':activo' => $new_status, ':id' => $id]);
            $message = "Estado de la inscripción actualizado correctamente.";
            $message_type = 'success';
        } else {
            $message = "Acción inválida para cambiar el estado de la inscripción.";
            $message_type = 'danger';
        }
    }
} catch (PDOException $e) {
    // Manejo específico para el error de UNIQUE constraint (si no se manejó antes)
    if ($e->getCode() == 23000) { // SQLSTATE para violation de integridad
        $message = "Error: Ya existe una inscripción para este estudiante en el año escolar seleccionado.";
        $message_type = 'danger';
    } else {
        $message = "Error en la operación de base de datos: " . $e->getMessage();
        $message_type = 'danger';
    }
    // error_log($e->getMessage()); // Para depuración en producción
}

$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;
header('Location: gestion_inscripciones.php');
exit();