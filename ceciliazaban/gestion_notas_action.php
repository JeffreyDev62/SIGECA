<?php
session_start();
require_once 'includes/db.php';

// Control de Acceso: Solo docentes y administradores
if (!isset($_SESSION['user_id']) || ($_SESSION['rol'] !== 'docente' && $_SESSION['rol'] !== 'admin')) {
    header('Location: login.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_rol = $_SESSION['rol'];

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aula_id = $_POST['aula_id'] ?? null;
    $posted_docente_id = $_POST['docente_id'] ?? null; // Docente ID enviado desde el formulario
    $anio_escolar_id = $_POST['anio_escolar_id'] ?? null;
    $notas_data = $_POST['notas'] ?? [];

    if (empty($aula_id) || empty($posted_docente_id) || empty($anio_escolar_id) || empty($notas_data)) {
        $_SESSION['message'] = "Datos incompletos para guardar las notas.";
        $_SESSION['message_type'] = 'danger';
        header('Location: gestion_notas.php?aula_id=' . urlencode($aula_id));
        exit();
    }

    // Seguridad: Asegurarse de que el docente que envía el formulario esté realmente asignado a esta aula
    // o que sea un admin.
    if ($current_user_rol === 'docente' && $current_user_id != $posted_docente_id) {
        $_SESSION['message'] = "Error de seguridad: ID de docente no coincide con el usuario logueado.";
        $_SESSION['message_type'] = 'danger';
        header('Location: gestion_notas.php?aula_id=' . urlencode($aula_id));
        exit();
    }
    
    // Si es docente, verificar que tenga asignada esta aula
    if ($current_user_rol === 'docente') {
        try {
            $stmtCheckAssignment = $pdo->prepare("SELECT COUNT(*) FROM asignaciones_aula WHERE id = :aula_id AND docente_id = :docente_id AND activo = 1 AND anio_escolar_id = :anio_escolar_id");
            $stmtCheckAssignment->execute([
                ':aula_id' => $aula_id,
                ':docente_id' => $current_user_id,
                ':anio_escolar_id' => $anio_escolar_id
            ]);
            if ($stmtCheckAssignment->fetchColumn() == 0) {
                $_SESSION['message'] = "Acceso denegado: No estás asignado a esta aula para el año escolar activo.";
                $_SESSION['message_type'] = 'danger';
                header('Location: gestion_notas.php?aula_id=' . urlencode($aula_id));
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error de base de datos al verificar asignación: " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            header('Location: gestion_notas.php'); // Redirigir sin aula_id si hay un error grave
            exit();
        }
    }


    try {
        $pdo->beginTransaction();
        $valid_literals = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach ($notas_data as $inscripcion_id => $nota_values) {
            $nota_id = $nota_values['nota_id'] ?? null;
            
            // Momento 1, 2, 3 son ahora descripciones de texto
            $momento1 = trim($nota_values['momento1_nota'] ?? '');
            $momento2 = trim($nota_values['momento2_nota'] ?? '');
            $momento3 = trim($nota_values['momento3_nota'] ?? '');
            $literal = strtoupper(trim($nota_values['literal'] ?? '')); // Convertir a mayúsculas para validación

            // Si las descripciones están vacías, guardarlas como NULL
            $momento1 = (empty($momento1)) ? NULL : $momento1;
            $momento2 = (empty($momento2)) ? NULL : $momento2;
            $momento3 = (empty($momento3)) ? NULL : $momento3;
            
            // Validar literal: debe ser uno de los valores permitidos o NULL
            if (!empty($literal) && !in_array($literal, $valid_literals)) {
                // Puedes optar por ignorar el literal inválido o mostrar un error.
                // Aquí, lo estableceremos a NULL para no guardar un valor inválido.
                $literal = NULL; 
                // Opcional: registrar un error o agregar un mensaje al usuario
                // $_SESSION['message'] .= "El literal para el estudiante con inscripción ID $inscripcion_id no es válido y ha sido ignorado. ";
                // $_SESSION['message_type'] = 'warning';
            } elseif (empty($literal)) {
                $literal = NULL;
            }

            if ($nota_id) {
                // Actualizar nota existente
                $stmt = $pdo->prepare("UPDATE notas SET momento1_nota = :m1, momento2_nota = :m2, momento3_nota = :m3, literal = :literal, docente_id = :docente_id WHERE id = :id");
                $stmt->execute([
                    ':m1' => $momento1,
                    ':m2' => $momento2,
                    ':m3' => $momento3,
                    ':literal' => $literal,
                    ':docente_id' => $current_user_id, // Usar el ID del docente logueado para registrar quién hizo el último cambio
                    ':id' => $nota_id
                ]);
            } else {
                // Insertar nueva nota
                // Solo si al menos un campo de nota o literal no es nulo
                if ($momento1 !== NULL || $momento2 !== NULL || $momento3 !== NULL || $literal !== NULL) {
                    $stmt = $pdo->prepare("INSERT INTO notas (inscripcion_id, momento1_nota, momento2_nota, momento3_nota, literal, docente_id) VALUES (:inscripcion_id, :m1, :m2, :m3, :literal, :docente_id)");
                    $stmt->execute([
                        ':inscripcion_id' => $inscripcion_id,
                        ':m1' => $momento1,
                        ':m2' => $momento2,
                        ':m3' => $momento3,
                        ':literal' => $literal,
                        ':docente_id' => $current_user_id
                    ]);
                }
            }
        }

        $pdo->commit();
        $_SESSION['message'] = "Notas guardadas correctamente.";
        $_SESSION['message_type'] = 'success';

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
             $_SESSION['message'] = "Error: Intentaste crear una nota duplicada para una inscripción. Asegúrate de editar las notas existentes en lugar de intentar añadir una nueva.";
        } else {
            $_SESSION['message'] = "Error al guardar las notas: " . $e->getMessage();
        }
        $_SESSION['message_type'] = 'danger';
        // error_log("Error al guardar notas: " . $e->getMessage()); // Para depuración
    }
} else {
    $_SESSION['message'] = "Acceso inválido.";
    $_SESSION['message_type'] = 'danger';
}

header('Location: gestion_notas.php?aula_id=' . urlencode($aula_id ?? ''));
exit();