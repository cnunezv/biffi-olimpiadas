<?php
require_once 'includes/config.php';

$payload = $_GET['payload'] ?? '';
$sig = $_GET['sig'] ?? '';
$next = $_GET['next'] ?? 'pruebas.php';

if ($payload === '' || $sig === '') {
    http_response_code(400);
    exit('Solicitud incompleta.');
}

$expected = hash_hmac('sha256', $payload, LEGACY_BRIDGE_SECRET);
if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    exit('Firma inválida.');
}

$normalized = strtr($payload, '-_', '+/');
$padding = strlen($normalized) % 4;
if ($padding > 0) {
    $normalized .= str_repeat('=', 4 - $padding);
}
$json = base64_decode($normalized, true);
if ($json === false) {
    http_response_code(400);
    exit('Payload inválido.');
}

$data = json_decode($json, true);
if (!is_array($data) || empty($data['user_id'])) {
    http_response_code(400);
    exit('Datos de sesión inválidos.');
}

$_SESSION['user_id'] = (int)$data['user_id'];
$_SESSION['nombre'] = $data['nombre'] ?? '';
$_SESSION['apellido'] = $data['apellido'] ?? '';
$_SESSION['usuario'] = $data['usuario'] ?? '';
$_SESSION['rol'] = $data['rol'] ?? 'estudiante';
$_SESSION['grado'] = isset($data['grado']) ? (int)$data['grado'] : null;
$_SESSION['institucion_id'] = isset($data['institucion_id']) ? (int)$data['institucion_id'] : null;

$next = ltrim($next, '/');
if ($next === '' || preg_match('/^(https?:)?\/\//i', $next)) {
    $next = 'pruebas.php';
}

header('Location: ' . $next);
exit;
