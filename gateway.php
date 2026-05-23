<?php
declare(strict_types=1);

$projectDir = __DIR__;
$backendBase = 'http://127.0.0.1:5050';
$mountPath = '/biffi-olimpiadas';
$healthUrl = $backendBase . '/healthz';
$logFile = $projectDir . DIRECTORY_SEPARATOR . 'instance' . DIRECTORY_SEPARATOR . 'xampp-flask.log';

function try_start_backend(string $projectDir, string $logFile): void
{
    if (!is_dir(dirname($logFile))) {
        @mkdir(dirname($logFile), 0777, true);
    }

    $commands = [
        'cmd /c start "" /B py serve_xampp.py >> "' . $logFile . '" 2>&1',
        'cmd /c start "" /B python serve_xampp.py >> "' . $logFile . '" 2>&1',
    ];

    foreach ($commands as $command) {
        @pclose(@popen('cd /d "' . $projectDir . '" && ' . $command, 'r'));
        usleep(300000);
    }
}

function backend_alive(string $healthUrl): bool
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 1,
            'ignore_errors' => true,
        ],
    ]);
    $result = @file_get_contents($healthUrl, false, $context);
    return $result !== false;
}

if (!backend_alive($healthUrl)) {
    try_start_backend($projectDir, $logFile);
    $ok = false;
    for ($i = 0; $i < 10; $i++) {
        if (backend_alive($healthUrl)) {
            $ok = true;
            break;
        }
        usleep(500000);
    }
    if (!$ok) {
        http_response_code(503);
        ?>
        <!doctype html>
        <html lang="es">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Biffi Olimpiadas · Backend no disponible</title>
          <style>
            body{font-family:Segoe UI,Arial,sans-serif;background:#f8f1f3;color:#271519;padding:40px}
            .box{max-width:820px;margin:auto;background:#fff;border:1px solid #e3cfd5;border-radius:18px;padding:28px;box-shadow:0 20px 45px rgba(124,31,48,.12)}
            h1{color:#7C1F30;margin-top:0}
            code,pre{background:#f4e7eb;padding:2px 6px;border-radius:8px}
            pre{display:block;padding:14px;overflow:auto}
          </style>
        </head>
        <body>
          <div class="box">
            <h1>No se pudo iniciar Flask desde XAMPP</h1>
            <p>La integración Apache/PHP ya está preparada, pero faltó levantar el backend Python.</p>
            <p>Ejecuta una sola vez:</p>
            <pre>cd C:\xampp\htdocs\biffi-olimpiadas
py -m pip install -r requirements.txt</pre>
            <p>Luego vuelve a abrir <code>http://localhost/biffi-olimpiadas</code>.</p>
            <p>Log de arranque:</p>
            <pre><?= htmlspecialchars($logFile, ENT_QUOTES, 'UTF-8') ?></pre>
          </div>
        </body>
        </html>
        <?php
        exit;
    }
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsed = parse_url($requestUri);
$path = $parsed['path'] ?? '/';
$query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
$forwardPath = preg_replace('#^' . preg_quote($mountPath, '#') . '#', '', $path) ?: '/';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $forwardPath === '/') {
    $forwardPath = '/login';
}
$targetUrl = $backendBase . $forwardPath . $query;

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD'] ?? 'GET');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$hasUploads = !empty($_FILES);

$headers = [
    'X-Forwarded-Proto: http',
    'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'X-Forwarded-Port: 80',
    'X-Forwarded-Prefix: ' . $mountPath,
];

if (!$hasUploads && !empty($_SERVER['CONTENT_TYPE'])) {
    $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
}

if (!empty($_SERVER['HTTP_COOKIE'])) {
    $headers[] = 'Cookie: ' . $_SERVER['HTTP_COOKIE'];
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    if ($hasUploads) {
        $postFields = [];
        foreach ($_POST as $key => $value) {
            $postFields[$key] = $value;
        }
        foreach ($_FILES as $field => $file) {
            if (is_array($file['tmp_name'])) {
                continue;
            }
            if (!empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
                $mime = $file['type'] ?: 'application/octet-stream';
                $postFields[$field] = curl_file_create($file['tmp_name'], $mime, $file['name']);
            }
        }
        if (!empty($postFields)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }
    } else {
        $body = file_get_contents('php://input');
        if ($body !== false) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }
}

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    echo 'Error al conectar con el backend Flask.';
    exit;
}

$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$rawHeaders = substr($response, 0, $headerSize);
$rawBody = substr($response, $headerSize);
curl_close($ch);

http_response_code($statusCode);
foreach (explode("\r\n", $rawHeaders) as $headerLine) {
    if (!$headerLine || str_starts_with(strtolower($headerLine), 'http/')) {
        continue;
    }
    [$name, $value] = array_pad(explode(':', $headerLine, 2), 2, '');
    $nameTrim = trim($name);
    $valueTrim = trim($value);
    if ($nameTrim === '' || in_array(strtolower($nameTrim), ['transfer-encoding', 'connection', 'content-length'], true)) {
        continue;
    }
    if (strtolower($nameTrim) === 'location') {
        if (str_starts_with($valueTrim, $mountPath . '/')) {
            $valueTrim = $valueTrim;
        } elseif (str_starts_with($valueTrim, '/')) {
            $valueTrim = $mountPath . $valueTrim;
        } elseif (str_starts_with($valueTrim, $backendBase)) {
            $suffix = substr($valueTrim, strlen($backendBase));
            $valueTrim = str_starts_with($suffix, $mountPath . '/') ? $suffix : $mountPath . $suffix;
        }
    }
    header($nameTrim . ': ' . $valueTrim, false);
}

echo $rawBody;
