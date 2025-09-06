<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once 'db.php';

const QR_SECRET = 'cambia_esto';

function sign_token($kind, $entityId, $nonce, $ts) {
    $base = "LF1.$kind.$entityId.$nonce.$ts";
    return $base . '.' . hash_hmac('sha256', $base, QR_SECRET);
}
function parse_token($token) {
    if (!preg_match('/^LF1\.(ORDER|DN)\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]{6,})\.(\d{10})\.([A-Fa-f0-9]{32,})$/', $token, $m)) return null;
    return ['kind' => $m[1], 'entityId' => $m[2], 'nonce' => $m[3], 'ts' => $m[4], 'sig' => $m[5]];
}
function verify_token($token) {
    $p = parse_token($token); if (!$p) return false;
    return hash_equals(sign_token($p['kind'], $p['entityId'], $p['nonce'], $p['ts']), $token);
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method == 'OPTIONS') { http_response_code(200); exit(); }

// ---------- POST ----------
if ($method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['userId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Falta userId']);
        exit;
    }

    // Legacy format
    if (isset($data['order'], $data['datetime'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO scans (userId, orderNumber, datetimeQR, scannedAt) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['userId'], $data['order'], $data['datetime'], date('c')]);
            $pdo->prepare("INSERT OR IGNORE INTO orders (orderNumber, status) VALUES (?, 'EN_RECOGIDA')")
                ->execute([$data['order']]);
            echo json_encode(['success' => true, 'message' => 'Escaneo guardado (legacy).']);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $stmt = $pdo->prepare("SELECT userId, scannedAt FROM scans WHERE orderNumber=? AND datetimeQR=?");
                $stmt->execute([$data['order'], $data['datetime']]);
                $ex = $stmt->fetch(PDO::FETCH_ASSOC);
                $msg = "QR ya escaneado por {$ex['userId']} el " . date('d/m/Y H:i', strtotime($ex['scannedAt']));
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => $msg]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error BD']);
            }
        }
        exit;
    }

    // Token + acción
    if (!isset($data['token'], $data['role'], $data['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
        exit;
    }
    if (!verify_token($data['token'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }
    $p = parse_token($data['token']);

    try {
        $pdo->beginTransaction();
        if ($p['kind'] === 'ORDER') {
            $pdo->prepare("INSERT OR IGNORE INTO orders (id, orderNumber, status) VALUES (?, ?, 'EN_RECOGIDA')")
                ->execute([$p['entityId'], $p['entityId']]);
        }
        $payload = isset($data['payload']) ? json_encode($data['payload']) : null;
        $pdo->prepare("INSERT INTO scan_events (userId, role, kind, entity_id, action, payload_json) VALUES (?,?,?,?,?,?)")
            ->execute([$data['userId'], $data['role'], $p['kind'], $p['entityId'], $data['action'], $payload]);
        $resp = ['success' => true, 'message' => 'Acción registrada'];
        if ($data['action'] === 'PICKING' && $p['kind'] === 'ORDER') {
            $pdo->prepare("UPDATE orders SET status='EN_RECOGIDA' WHERE id=?")
                ->execute([$p['entityId']]);
            $resp['message'] = 'Picking registrado';
        }
        if ($data['action'] === 'TO_DN' && $p['kind'] === 'ORDER') {
            $pdo->prepare("INSERT INTO delivery_notes (order_id, status) VALUES (?, 'EN_PACKING')")
                ->execute([$p['entityId']]);
            $dnId = $pdo->lastInsertId();
            $nonce = bin2hex(random_bytes(6));
            $ts = time();
            $dnToken = sign_token('DN', $dnId, $nonce, $ts);
            $pdo->prepare("INSERT INTO qr_tokens (kind, entity_id, token) VALUES ('DN', ?, ?)")
                ->execute([$dnId, $dnToken]);
            $pdo->prepare("UPDATE orders SET status='EN_PACKING' WHERE id=?")
                ->execute([$p['entityId']]);
            $resp['message'] = 'Pedido pasado a albarán';
            $resp['dnToken'] = $dnToken;
        }
        if ($data['action'] === 'PACKING' && $p['kind'] === 'DN') {
            $pdo->prepare("UPDATE delivery_notes SET status='EN_PACKING' WHERE id=?")
                ->execute([$p['entityId']]);
            $resp['message'] = 'Packing registrado';
        }
        if ($data['action'] === 'LOG_INPUT' && $p['kind'] === 'DN') {
            $pdo->prepare("UPDATE delivery_notes SET status='ENVIADO' WHERE id=?")
                ->execute([$p['entityId']]);
            $resp['message'] = 'Datos de logística guardados';
        }
        $pdo->commit();
        echo json_encode($resp);
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Esta acción ya fue registrada para este QR.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
        }
    }
    exit;
}

// ---------- GET ----------
if ($method == 'GET') {
    try {
        if (isset($_GET['userId'])) {
            $stmt = $pdo->prepare("SELECT * FROM scans WHERE userId = ? ORDER BY scannedAt DESC");
            $stmt->execute([$_GET['userId']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM scans ORDER BY scannedAt DESC");
            $stmt->execute();
        }
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($scans);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener los datos']);
    }
}

// ---------- DELETE ----------
if ($method == 'DELETE') {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Falta el ID del escaneo a eliminar.']);
        exit;
    }
    $requestBody = json_decode(file_get_contents('php://input'), true);
    if (!isset($requestBody['userId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Falta la identificación del usuario.']);
        exit;
    }
    $scanIdToDelete = $_GET['id'];
    $requestingUserId = $requestBody['userId'];
    try {
        $stmt = $pdo->prepare("SELECT userId FROM scans WHERE id = ?");
        $stmt->execute([$scanIdToDelete]);
        $scan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$scan) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'El escaneo no existe.']);
            exit;
        }
        if ($scan['userId'] !== $requestingUserId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para borrar este escaneo.']);
            exit;
        }
        $deleteStmt = $pdo->prepare("DELETE FROM scans WHERE id = ?");
        $deleteStmt->execute([$scanIdToDelete]);
        echo json_encode(['success' => true, 'message' => 'Escaneo eliminado correctamente.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de base de datos al eliminar.']);
    }
}
?>

