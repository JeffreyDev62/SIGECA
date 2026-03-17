<?php
// includes/db.php

$host = 'localhost';
$db   = 'gestion_ceciliazaban';
$user = 'root';
$pass = ''; // Tu contraseña de MySQL, que indicaste que está vacía

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo "Conexión a la base de datos exitosa!"; // Solo para probar, puedes comentarlo o borrarlo
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
