<?php
session_start();
require_once 'includes/db.php';

// Control de Acceso: Solo administradores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

$estudiantes = [];
$representantes_disponibles = [];

// Obtener el término de búsqueda
$search_term = $_GET['search'] ?? '';
$search_term = trim($search_term); // Limpiar espacios en blanco

try {
    // Obtener la lista de representantes activos para el dropdown en el modal
    $stmtRepresentantes = $pdo->query("SELECT id, cedula, nombre, apellido FROM representantes WHERE activo = 1 ORDER BY apellido, nombre");
    $representantes_disponibles = $stmtRepresentantes->fetchAll();

    // Lógica para cargar todos los estudiantes con sus representantes
    $sqlEstudiantes = "
        SELECT
            e.id,
            e.cedula AS estudiante_cedula,
            e.nombre AS estudiante_nombre,
            e.apellido AS estudiante_apellido,
            e.edad,
            e.genero,
            e.fecha_nacimiento,
            e.lugar_nacimiento,
            e.activo,
            r.id AS representante_id,
            r.nombre AS representante_nombre,
            r.apellido AS representante_apellido,
            r.cedula AS representante_cedula,
            r.telefono AS representante_telefono,
            r.ocupacion AS representante_ocupacion
        FROM
            estudiantes e
        JOIN
            representantes r ON e.representante_id = r.id
    ";

    $params = [];
  // [CÓDIGO CORREGIDO] Usamos $search_term y aplicamos el filtro
    if (!empty($search_term)) {
        $search_param = '%' . $search_term . '%';
        
        $sqlEstudiantes .= " WHERE e.nombre LIKE :search_nombre OR e.apellido LIKE :search_apellido OR e.cedula LIKE :search_cedula";
        
        $params[':search_nombre'] = $search_param;
        $params[':search_apellido'] = $search_param;
        $params[':search_cedula'] = $search_param;
    }

    $sqlEstudiantes .= " ORDER BY e.apellido, e.nombre";

    $stmt = $pdo->prepare($sqlEstudiantes);
    $stmt->execute($params); 
    $estudiantes = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = "Error al cargar los datos: " . $e->getMessage();
    $message_type = 'danger';
    // error_log($e->getMessage()); // Para depuración en producción
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gestión de Estudiantes - Cecilia Bazán de Segura</title>
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
                    <img src="img/MPPEducacion.png" alt="Institución Bolivariana" class="img-fluid" style="max-height: 50px;" />
                    <span class="ms-3 h4 mb-0 text-primary d-none d-md-block">Cecilia Bazán de Segura</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                        <img src="img/LogoCeciliaBazanSegura.png" alt="Logo Cecilia Bazán Segura" class="img-fluid" />
                    </div>
                </a>
            </div>
        </nav>
    </header>

    <main class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="text-primary">Gestión de Estudiantes</h1>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#estudianteModal" id="addEstudianteBtn">
                <i class="fas fa-plus"></i> Añadir Estudiante
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <form action="gestion_estudiantes.php" method="GET" class="d-flex">
                <input type="text" class="form-control me-2" placeholder="Buscar por cédula, nombre o apellido (estudiante/representante)" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
                <?php if (!empty($search_term)): ?>
                    <a href="gestion_estudiantes.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Cédula Est.</th>
                        <th>Estudiante</th>
                        <th>Edad</th>
                        <th>Género</th>
                        <th>Fecha Nac.</th>
                        <th>Lugar Nac.</th>
                        <th>Representante</th>
                        <th>Cédula Rep.</th>
                        <th>Teléfono Rep.</th>
                        <th>Ocupación Rep.</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($estudiantes) > 0): ?>
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <tr class="<?php echo ($estudiante['activo'] == 0) ? 'table-secondary text-muted' : ''; ?>">
                                <td><?php echo htmlspecialchars($estudiante['estudiante_cedula']); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['estudiante_nombre'] . ' ' . $estudiante['estudiante_apellido']); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['edad'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['genero'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['fecha_nacimiento'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['lugar_nacimiento'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['representante_nombre'] . ' ' . $estudiante['representante_apellido']); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['representante_cedula']); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['representante_telefono'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($estudiante['representante_ocupacion'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($estudiante['activo'] == 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-estudiante-btn"
                                            data-id="<?php echo $estudiante['id']; ?>"
                                            data-cedula="<?php echo htmlspecialchars($estudiante['estudiante_cedula']); ?>"
                                            data-nombre="<?php echo htmlspecialchars($estudiante['estudiante_nombre']); ?>"
                                            data-apellido="<?php echo htmlspecialchars($estudiante['estudiante_apellido']); ?>"
                                            data-edad="<?php echo htmlspecialchars($estudiante['edad'] ?? ''); ?>"
                                            data-genero="<?php echo htmlspecialchars($estudiante['genero'] ?? ''); ?>"
                                            data-fecha_nacimiento="<?php echo htmlspecialchars($estudiante['fecha_nacimiento'] ?? ''); ?>"
                                            data-lugar_nacimiento="<?php echo htmlspecialchars($estudiante['lugar_nacimiento'] ?? ''); ?>"
                                            data-representante_id="<?php echo htmlspecialchars($estudiante['representante_id']); ?>"
                                            data-activo="<?php echo htmlspecialchars($estudiante['activo']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#estudianteModal">
                                        Editar
                                    </button>
                                    <?php if ($estudiante['activo'] == 1): ?>
                                        <button class="btn btn-sm btn-outline-warning toggle-estudiante-status-btn" data-id="<?php echo $estudiante['id']; ?>" data-status="0">
                                            <i class="fas fa-ban"></i> Desactivar
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success toggle-estudiante-status-btn" data-id="<?php echo $estudiante['id']; ?>" data-status="1">
                                            <i class="fas fa-check-circle"></i> Activar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted">No hay estudiantes registrados que coincidan con la búsqueda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal fade" id="estudianteModal" tabindex="-1" aria-labelledby="estudianteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="estudianteModalLabel">Añadir Estudiante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="estudianteForm" action="gestion_estudiantes_action.php" method="POST">
                        <input type="hidden" id="estudianteId" name="id">

                        <h6 class="text-primary mb-3">Datos del Estudiante</h6>
                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label for="cedula_estudiante" class="form-label requerido">Cédula del Estudiante:</label>
                                <input type="text" 
                                class="form-control" 
                                id="cedula_estudiante" 
                                name="cedula_estudiante" 
                                required 
                                pattern="[0-9]*" 
                                inputmode="numeric" 
                                oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="estudianteNombre" class="form-label requerido">Nombre del Estudiante:</label>
                                <input type="text" class="form-control" id="estudianteNombre" name="nombre_estudiante" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="estudianteApellido" class="form-label requerido">Apellido del Estudiante:</label>
                                <input type="text" class="form-control" id="estudianteApellido" name="apellido_estudiante" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fechaNacimiento" class="form-label requerido">Fecha de Nacimiento:</label>
                                <input type="date" class="form-control" id="fechaNacimiento" name="fecha_nacimiento" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edadEstudiante" class="form-label">Edad del Estudiante:</label>
                                <input type="number" class="form-control" id="edadEstudiante" name="edad_estudiante" min="0" readonly>
                                <small class="form-text text-muted">Se calcula automáticamente de la fecha de nacimiento.</small>
                            </div>
                           <div class="mb-3 col-md-4">
                                <label for="genero" class="form-label requerido">Género:</label>
                                <select class="form-select" id="genero" name="genero" required>
                                <option value="">Seleccione...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="lugarNacimiento" class="form-label requerido">Lugar de Nacimiento:</label>
                            <input type="text" class="form-control" id="lugarNacimiento" name="lugar_nacimiento" required>
                        </div>


                        <h6 class="text-primary mt-4 mb-3">Datos del Representante</h6>
                        <div class="mb-3">
                            <label for="representanteExistente" class="form-label">Representante Existente:</label>
                            <select class="form-select" id="representanteExistente" name="representante_id">
                                <option value="">Seleccione un Representante (o cree uno nuevo)</option>
                                <?php foreach ($representantes_disponibles as $rep): ?>
                                    <option value="<?php echo $rep['id']; ?>">
                                        <?php echo htmlspecialchars($rep['nombre'] . ' ' . $rep['apellido'] . ' (' . $rep['cedula'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="nuevoRepresentanteFields">
                            <p class="text-muted text-center mt-3 mb-2">--- O Ingrese Datos de Nuevo Representante ---</p>
                            <div class="row">
                                <div class="mb-3 col-md-4">
                                    <label for="rep_cedula" class="form-label requerido">Cédula del Representante:</label>
                                    <input type="text" 
                                    class="form-control" 
                                    id="rep_cedula" 
                                    name="rep_cedula" 
                                    required 
                                    pattern="[0-9]*" 
                                    inputmode="numeric" 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                            </div>
                                <div class="col-md-8 mb-3">
                                    <label for="repNombre" class="form-label requerido">Nombre del Representante:</label>
                                    <input type="text" class="form-control" id="repNombre" name="rep_nombre">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="repApellido" class="form-label requerido">Apellido del Representante:</label>
                                    <input type="text" class="form-control" id="repApellido" name="rep_apellido">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="repTelefono" class="form-label requerido">Teléfono del Representante:</label>
                                    <input type="text" class="form-control" id="repTelefono" name="rep_telefono">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="repOcupacion" class="form-label requerido">Ocupación del Representante:</label>
                                <input type="text" class="form-control" id="repOcupacion" name="rep_ocupacion">
                            </div>
                        </div>

                        <div class="mb-3 form-check" id="estudianteActivoCheckGroup">
                            <input type="checkbox" class="form-check-input" id="estudianteActivo" name="activo" value="1" checked>
                            <label class="form-check-label" for="estudianteActivo">Activo</label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary">Guardar Estudiante</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="bootstrap-5.3.2-dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your_fontawesome_kit_code.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const estudianteModal = document.getElementById('estudianteModal');
            const estudianteModalLabel = document.getElementById('estudianteModalLabel');
            const estudianteForm = document.getElementById('estudianteForm');
            const addEstudianteBtn = document.getElementById('addEstudianteBtn');

            const estudianteIdInput = document.getElementById('estudianteId');
            const estudianteCedulaInput = document.getElementById('estudianteCedula');
            const estudianteNombreInput = document.getElementById('estudianteNombre');
            const estudianteApellidoInput = document.getElementById('estudianteApellido');
            const edadEstudianteInput = document.getElementById('edadEstudiante');
            const generoSelect = document.getElementById('genero');
            const fechaNacimientoInput = document.getElementById('fechaNacimiento');
            const lugarNacimientoInput = document.getElementById('lugarNacimiento');
            const representanteExistenteSelect = document.getElementById('representanteExistente');
            const estudianteActivoCheck = document.getElementById('estudianteActivo');
            const estudianteActivoCheckGroup = document.getElementById('estudianteActivoCheckGroup');

            // Campos del nuevo representante
            const nuevoRepresentanteFields = document.getElementById('nuevoRepresentanteFields');
            const repCedulaInput = document.getElementById('repCedula');
            const repNombreInput = document.getElementById('repNombre');
            const repApellidoInput = document.getElementById('repApellido');
            const repTelefonoInput = document.getElementById('repTelefono');
            const repOcupacionInput = document.getElementById('repOcupacion');

            // Función para calcular la edad
            function calcularEdad() {
                const fechaNacimientoStr = fechaNacimientoInput.value;
                if (fechaNacimientoStr) {
                    const fechaNacimiento = new Date(fechaNacimientoStr + 'T00:00:00'); // Añadir T00:00:00 para evitar problemas de zona horaria
                    const hoy = new Date();
                    let edad = hoy.getFullYear() - fechaNacimiento.getFullYear();
                    const mes = hoy.getMonth() - fechaNacimiento.getMonth();
                    if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNacimiento.getDate())) {
                        edad--;
                    }
                    edadEstudianteInput.value = edad;
                } else {
                    edadEstudianteInput.value = ''; // Limpiar si no hay fecha de nacimiento
                }
            }

            // Escuchar cambios en la fecha de nacimiento para calcular la edad
            fechaNacimientoInput.addEventListener('change', calcularEdad);
            // También llamar al cargar el modal para edición si la fecha ya está presente
            fechaNacimientoInput.addEventListener('input', calcularEdad);


            // Función para alternar la visibilidad y requerimiento de campos de representante
            function toggleRepresentanteFields() {
                if (representanteExistenteSelect.value === '') {
                    // Si no se selecciona un representante existente, los campos del nuevo representante son obligatorios
                    nuevoRepresentanteFields.style.display = 'block';
                    repCedulaInput.setAttribute('required', 'required');
                    repNombreInput.setAttribute('required', 'required');
                    repApellidoInput.setAttribute('required', 'required');
                } else {
                    // Si se selecciona un representante existente, los campos del nuevo representante no son obligatorios y se ocultan
                    nuevoRepresentanteFields.style.display = 'none';
                    repCedulaInput.removeAttribute('required');
                    repNombreInput.removeAttribute('required');
                    repApellidoInput.removeAttribute('required');
                    // Limpiar los campos del nuevo representante para evitar envíos de datos no deseados
                    repCedulaInput.value = '';
                    repNombreInput.value = '';
                    repApellidoInput.value = '';
                    repTelefonoInput.value = '';
                    repOcupacionInput.value = '';
                }
            }

            // Escuchar cambios en el selector de representante existente
            representanteExistenteSelect.addEventListener('change', toggleRepresentanteFields);

            // Limpiar el formulario al abrir para añadir
            addEstudianteBtn.addEventListener('click', function() {
                estudianteModalLabel.textContent = 'Añadir Nuevo Estudiante';
                estudianteForm.reset();
                estudianteIdInput.value = '';
                estudianteActivoCheck.checked = true; // Por defecto activo
                estudianteActivoCheckGroup.style.display = 'none'; // Ocultar el checkbox "activo" en añadir
                edadEstudianteInput.value = ''; // Limpiar la edad
                toggleRepresentanteFields(); // Asegurar que los campos de nuevo representante estén visibles y requeridos
            });

            // Llenar el formulario al abrir para editar
            document.querySelectorAll('.edit-estudiante-btn').forEach(button => {
                button.addEventListener('click', function() {
                    estudianteModalLabel.textContent = 'Editar Estudiante';
                    estudianteIdInput.value = this.dataset.id;
                    estudianteCedulaInput.value = this.dataset.cedula;
                    estudianteNombreInput.value = this.dataset.nombre;
                    estudianteApellidoInput.value = this.dataset.apellido;
                    fechaNacimientoInput.value = this.dataset.fecha_nacimiento;
                    generoSelect.value = this.dataset.genero;
                    lugarNacimientoInput.value = this.dataset.lugar_nacimiento;
                    representanteExistenteSelect.value = this.dataset.representante_id;
                    estudianteActivoCheck.checked = (this.dataset.activo == '1');
                    estudianteActivoCheckGroup.style.display = 'block'; // Mostrar el checkbox "activo" en editar
                    
                    calcularEdad(); // Calcular la edad al cargar los datos de edición
                    toggleRepresentanteFields(); // Ocultar campos de nuevo representante al editar
                });
            });

            // Lógica para alternar estado (activar/desactivar)
            document.querySelectorAll('.toggle-estudiante-status-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const estudianteId = this.dataset.id;
                    const newStatus = this.dataset.status;
                    const actionText = (newStatus == '0') ? 'desactivar' : 'activar';
                    if (confirm(`¿Estás seguro de que quieres ${actionText} este Estudiante?`)) {
                        window.location.href = `gestion_estudiantes_action.php?action=toggle_status&id=${estudianteId}&status=${newStatus}`;
                    }
                });
            });
        });
    </script>
</body>
</html>