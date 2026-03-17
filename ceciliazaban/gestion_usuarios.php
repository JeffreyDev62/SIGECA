<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';    
unset($_SESSION['message'], $_SESSION['message_type']); // Limpiar mensajes después de mostrarlos

$search_query = $_GET['search'] ?? ''; // Obtener el término de búsqueda de la URL

// --- Lógica para Cargar Usuarios con Filtro ---
$users = [];
try {
    $sql = "SELECT id, cedula, nombre, apellido, correo, telefono, direccion, especialidad, rol, fecha_creacion, activo FROM usuarios";
    $params = [];

    if (!empty($search_query)) {
        $sql .= " WHERE nombre LIKE :search_name OR apellido LIKE :search_apellido OR correo LIKE :search_correo OR cedula LIKE :search_cedula";
        $params[':search_name'] = '%' . $search_query . '%';
        $params[':search_apellido'] = '%' . $search_query . '%';
        $params[':search_correo'] = '%' . $search_query . '%';
        $params[':search_cedula'] = '%' . $search_query . '%';
    }

    $sql .= " ORDER BY apellido, nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = "Error al cargar los usuarios: " . $e->getMessage();
    $message_type = 'danger';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestión de Usuarios - Cecilia Bazán de Segura</title>
    <link rel="stylesheet" href="bootstrap-5.3.2-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/PlantillaCss.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .requerido::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <header class="container-fluid bg-light shadow-sm py-2">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="dashboard_admin.php">
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

                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo (strpos($_SERVER['PHP_SELF'], 'gestion_notas.php') !== false || strpos($_SERVER['PHP_SELF'], 'reporte_asistencia_estudiante.php') !== false || strpos($_SERVER['PHP_SELF'], 'reporte_academico.php') !== false) ? 'active' : ''; ?>" href="#" id="academicoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Académico
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="academicoDropdown">
                                    <li>
                                        <a class="dropdown-item <?php echo (strpos($_SERVER['PHP_SELF'], 'reporte_academico.php') !== false) ? 'active' : ''; ?>" href="reporte_academico.php">Reporte Académico</a>
                                    </li>
                                </ul>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary">Gestión de Usuarios</h1>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal" id="addUserBtn">
                <i class="fas fa-plus"></i> Añadir Nuevo Usuario
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <form action="gestion_usuarios.php" method="GET" class="input-group">
                <input type="text" class="form-control" placeholder="Buscar por cédula, nombre, apellido o correo..." name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i> Buscar</button>
                <?php if (!empty($search_query)): ?>
                    <a href="gestion_usuarios.php" class="btn btn-outline-danger"><i class="fas fa-times"></i> Limpiar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Cédula</th>
                        <th>Nombre Completo</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <th>Especialidad</th>
                        <th>Estado</th> <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="<?php echo ($user['activo'] == 0) ? 'table-secondary text-muted' : ''; ?>">
                                <td><?php echo htmlspecialchars($user['cedula'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?></td>
                                <td><?php echo htmlspecialchars($user['correo']); ?></td>
                                <td><?php echo htmlspecialchars($user['telefono'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-<?php echo ($user['rol'] === 'admin' ? 'danger' : 'info'); ?>"><?php echo htmlspecialchars(ucfirst($user['rol'])); ?></span></td>
                                <td><?php echo htmlspecialchars($user['especialidad'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($user['activo'] == 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-user-btn"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-cedula="<?php echo htmlspecialchars($user['cedula'] ?? ''); ?>"
                                            data-nombre="<?php echo htmlspecialchars($user['nombre']); ?>"
                                            data-apellido="<?php echo htmlspecialchars($user['apellido']); ?>"
                                            data-correo="<?php echo htmlspecialchars($user['correo']); ?>"
                                            data-telefono="<?php echo htmlspecialchars($user['telefono'] ?? ''); ?>"
                                            data-direccion="<?php echo htmlspecialchars($user['direccion'] ?? ''); ?>"
                                            data-especialidad="<?php echo htmlspecialchars($user['especialidad'] ?? ''); ?>"
                                            data-rol="<?php echo htmlspecialchars($user['rol']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#userModal">
                                        Editar
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): // Evitar que el admin se desactive a sí mismo ?>
                                        <?php if ($user['activo'] == 1): ?>
                                            <button class="btn btn-sm btn-outline-warning toggle-user-status-btn" data-id="<?php echo $user['id']; ?>" data-status="0">
                                                <i class="fas fa-ban"></i> Desactivar
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-success toggle-user-status-btn" data-id="<?php echo $user['id']; ?>" data-status="1">
                                                <i class="fas fa-check-circle"></i> Activar
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">No hay usuarios registrados que coincidan con la búsqueda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Añadir Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm" action="gestion_usuarios_action.php" method="POST">
                        <input type="hidden" id="userId" name="id">

                        <div class="mb-3">
                            <label for="cedula" class="form-label requerido">Cédula:</label>
                            <input type="text" 
                            class="form-control" 
                            id="cedula" 
                            name="cedula" 
                            required 
                            pattern="[0-9]*" 
                            inputmode="numeric" 
                            oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                        </div>
                        <div class="mb-3">
                            <label for="nombre" class="form-label requerido">Nombre:</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="apellido" class="form-label requerido">Apellido:</label>
                            <input type="text" class="form-control" id="apellido" name="apellido" required>
                        </div>
                        <div class="mb-3">
                            <label for="correo" class="form-label requerido">Correo Electrónico:</label>
                            <input type="email" class="form-control" id="correo" name="correo" required>
                        </div>
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Número Telefónico:</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección:</label>
                            <input type="text" class="form-control" id="direccion" name="direccion">
                        </div>
                        <div class="mb-3">
                            <label for="especialidad" class="form-label requerido">Especialidad (solo para Docentes):</label>
                            <input type="text" class="form-control" id="especialidad" name="especialidad">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña (solo si es nuevo o desea cambiar):</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="8">
                            <small class="form-text text-muted">Mínimo 8 caracteres.</small>
                        </div>
                        <div class="mb-3">
                            <label for="rol" class="form-label requerido">Rol:</label>
                            <select class="form-select" id="rol" name="rol" required>
                                <option value="docente">Docente</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your_fontawesome_kit_code.js" crossorigin="anonymous"></script> <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userModal = document.getElementById('userModal');
            const userModalLabel = document.getElementById('userModalLabel');
            const userForm = document.getElementById('userForm');
            const addUserBtn = document.getElementById('addUserBtn');

            const userIdInput = document.getElementById('userId');
            const cedulaInput = document.getElementById('cedula');
            const nombreInput = document.getElementById('nombre');
            const apellidoInput = document.getElementById('apellido');
            const correoInput = document.getElementById('correo');
            const telefonoInput = document.getElementById('telefono');
            const direccionInput = document.getElementById('direccion');
            const especialidadInput = document.getElementById('especialidad');
            const passwordInput = document.getElementById('password');
            const rolSelect = document.getElementById('rol');

            addUserBtn.addEventListener('click', function() {
                userModalLabel.textContent = 'Añadir Nuevo Usuario';
                userForm.reset();
                userIdInput.value = '';
                passwordInput.setAttribute('required', 'required');
                rolSelect.dispatchEvent(new Event('change')); // Actualizar estado de especialidad
            });

            document.querySelectorAll('.edit-user-btn').forEach(button => {
                button.addEventListener('click', function() {
                    userModalLabel.textContent = 'Editar Usuario';
                    userIdInput.value = this.dataset.id;
                    cedulaInput.value = this.dataset.cedula;
                    nombreInput.value = this.dataset.nombre;
                    apellidoInput.value = this.dataset.apellido;
                    correoInput.value = this.dataset.correo;
                    telefonoInput.value = this.dataset.telefono;
                    direccionInput.value = this.dataset.direccion;
                    especialidadInput.value = this.dataset.especialidad;
                    rolSelect.value = this.dataset.rol;
                    passwordInput.removeAttribute('required');
                    passwordInput.value = '';
                    rolSelect.dispatchEvent(new Event('change')); // Actualizar estado de especialidad
                });
            });

            // Lógica para alternar estado (activar/desactivar)
            document.querySelectorAll('.toggle-user-status-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.id;
                    const newStatus = this.dataset.status; // 0 para desactivar, 1 para activar
                    const actionText = (newStatus == '0') ? 'desactivar' : 'activar';
                    if (confirm(`¿Estás seguro de que quieres ${actionText} a este usuario?`)) {
                        window.location.href = `gestion_usuarios_action.php?action=toggle_status&id=${userId}&status=${newStatus}`;
                    }
                });
            });

            // Lógica para el campo de especialidad
            rolSelect.addEventListener('change', function() {
                if (rolSelect.value === 'docente') {
                    especialidadInput.removeAttribute('disabled');
                    especialidadInput.setAttribute('required', 'required'); // Hacer requerido si es docente
                } else {
                    especialidadInput.setAttribute('disabled', 'disabled');
                    especialidadInput.removeAttribute('required');
                    especialidadInput.value = '';
                }
            });

            rolSelect.dispatchEvent(new Event('change')); // Disparar al cargar para inicializar el estado
        });
    </script>
</body>
</html>