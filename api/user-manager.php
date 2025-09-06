<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// LISTAR USUARIOS (GET)
if ($method == 'GET') {
    try {
        // Seleccionamos todo EXCEPTO la contraseña por seguridad
        $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($users);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener usuarios.']);
    }
}

// CREAR USUARIO (POST)
if ($method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['username']) || !isset($data['password']) || !isset($data['role'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nombre de usuario, contraseña y rol son requeridos.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$data['username'], $data['password'], $data['role']]);
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente.']);
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya existe.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
        }
    }
}
?>