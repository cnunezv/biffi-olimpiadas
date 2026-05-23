<?php
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','olimpiadas_pro');
define('SITE_NAME','Biffi Olimpiadas');
define('SITE_URL','http://localhost/biffi-olimpiadas');
define('UPLOAD_PDF', __DIR__.'/../uploads/pdfs/');
define('UPLOAD_IMG', __DIR__.'/../uploads/imgs/');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch(PDOException $e) {
    die("<div style='font-family:sans-serif;padding:40px;background:#fde;border:2px solid #c00;max-width:600px;margin:60px auto;border-radius:12px'>
        <h2>⚠️ Error de conexión</h2><p>".$e->getMessage()."</p>
        <p>Verifica que XAMPP esté activo y que la base de datos <strong>".DB_NAME."</strong> exista.</p></div>");
}

if(session_status() === PHP_SESSION_NONE){
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLogged()  { return !empty($_SESSION['user_id']); }
function isAdmin()   { return ($_SESSION['rol'] ?? '') === 'admin'; }
function isDocente() { return in_array($_SESSION['rol'] ?? '', ['admin','docente']); }
function requireLogin(){ if(!isLogged()){ header('Location: index.php'); exit; } }
function requireAdmin(){ requireLogin(); if(!isAdmin()){ header('Location: dashboard.php'); exit; } }
function requireDocente(){ requireLogin(); if(!isDocente()){ header('Location: dashboard.php'); exit; } }

function noMsgLeidos($pdo){
    if(!isLogged()) return 0;
    $s=$pdo->prepare("SELECT COUNT(*) FROM mensajes WHERE para_id=? AND leido=0 AND eliminado_para=0");
    $s->execute([$_SESSION['user_id']]);
    return (int)$s->fetchColumn();
}

function sanitize($str){ return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8'); }

// ── GRUPOS POR GRADO ──────────────────────────────────────────
// Devuelve el grupo al que pertenece un grado escolar
function grupoDeGrado(?int $grado): string {
    if($grado === null) return '10-11'; // default
    if($grado <= 5)  return '4-5';
    if($grado <= 7)  return '6-7';
    if($grado <= 9)  return '8-9';
    return '10-11';
}

// Grupo del usuario actual en sesión
function miGrupo(): string {
    return grupoDeGrado($_SESSION['grado'] ?? null);
}

// Etiqueta legible del grupo
function etiquetaGrupo(string $g): string {
    return ['4-5'=>'Grados 4°-5°','6-7'=>'Grados 6°-7°',
            '8-9'=>'Grados 8°-9°','10-11'=>'Grados 10°-11°'][$g] ?? $g;
}

// Colores por grupo
function colorGrupo(string $g): string {
    return ['4-5'=>'#1976d2','6-7'=>'#00897b','8-9'=>'#e65100','10-11'=>'#7C1F30'][$g] ?? '#7C1F30';
}

// ── INSTITUCIONES ─────────────────────────────────────────────
function getInstituciones($pdo): array {
    try {
        return $pdo->query("SELECT * FROM instituciones WHERE activa=1 ORDER BY nombre")->fetchAll();
    } catch(\Exception $e){ return []; }
}

function getInstitucion($pdo, int $id): ?array {
    try {
        $s = $pdo->prepare("SELECT * FROM instituciones WHERE id=?");
        $s->execute([$id]); return $s->fetch() ?: null;
    } catch(\Exception $e){ return null; }
}

function miInstitucion($pdo): ?array {
    if(empty($_SESSION['institucion_id'])) return null;
    return getInstitucion($pdo, (int)$_SESSION['institucion_id']);
}

// ── ROLES AVANZADOS ───────────────────────────────────────────
// ID de Colegio Biffi (siempre 1 por la migración)
define('BIFFI_INST_ID', 1);

// ¿Es el usuario de Colegio Biffi?
function isBiffi(): bool {
    $iid = (int)($_SESSION['institucion_id'] ?? 0);
    return $iid === BIFFI_INST_ID || $iid === 0; // 0 = sin asignar = asumir Biffi
}

// ¿Puede editar pruebas? Solo admin + docentes Biffi
function puedeEditarPruebas(): bool {
    return isAdmin() || (isDocente() && isBiffi());
}

// ¿Prueba habilitada? Solo depende de secciones (interruptor global del admin).
// pruebas_config guarda tiempo/intentos pero NO bloquea el acceso.
function pruebaHabilitada($pdo, string $tipo, string $grupo): bool {
    if($tipo === 'simulacro') return true;
    try {
        $s = $pdo->prepare("SELECT habilitada FROM secciones WHERE nombre=? LIMIT 1");
        $s->execute([$tipo]);
        $val = $s->fetchColumn();
        // If no row found in secciones, default to false (not yet configured)
        if($val === false) return false;
        return (int)$val === 1;
    } catch(\Exception $e){ return false; }
}

// ¿Cuántos intentos le quedan al usuario para esta prueba? -1 = sin límite
function intentosRestantes($pdo, int $uid, string $tipo, string $grupo, ?string $nivel=null): int {
    if($tipo === 'simulacro') return -1;
    try {
        $c = $pdo->prepare("SELECT max_intentos FROM pruebas_config WHERE tipo_prueba=? AND grupo_grado=? LIMIT 1");
        $c->execute([$tipo,$grupo]); $max = $c->fetchColumn();
        if($max === false || $max === null || (int)$max === 0) return -1; // 0 = ilimitado

        // Count total attempts for this tipo+grupo (any nivel)
        // Pattern: basico_10-11_clasificatoria, medio_10-11_clasificatoria, etc.
        // Escape _ wildcard: use ESCAPE '\' 
        $gg_escaped = str_replace('_', '\\_', $grupo);
        if($nivel){
            $niv_escaped = str_replace('_', '\\_', $nivel);
            $patron = $niv_escaped.'\\_'.$gg_escaped.'\\_'.$tipo;
        } else {
            $patron = '%\\_'.$gg_escaped.'\\_'.$tipo;
        }
        $usados = $pdo->prepare("SELECT COUNT(*) FROM resultados WHERE usuario_id=? AND nivel LIKE ? ESCAPE '\\\\'");
        $usados->execute([$uid, $patron]);
        $n = (int)$usados->fetchColumn();
        return max(0, (int)$max - $n);
    } catch(\Exception $e){ return -1; }
}
function tiposPrueba(): array {
    return [
        'simulacro'     => ['🏋️',  'Simulacro',           'Práctica libre, sin límite de tiempo'],
        'clasificatoria'=> ['🎯',  'Prueba Clasificatoria','Selecciona a los mejores'],
        'selectiva'     => ['⭐',  'Prueba Selectiva',     'Segunda fase oficial'],
        'final'         => ['🏆',  'Prueba Final',         'Gran final de las olimpiadas'],
    ];
}

function colorTipo(string $t): string {
    return ['simulacro'=>'#00897b','clasificatoria'=>'#7C1F30',
            'selectiva'=>'#C8A050','final'=>'#f57c00'][$t] ?? '#7C1F30';
}

function etiquetaTipo(string $t): string {
    $tp = tiposPrueba();
    return ($tp[$t][0]??'').' '.($tp[$t][1]??$t);
}

function formExternoConfig(PDO $pdo, string $tipo, string $grupo): ?array {
    try {
        $q = $pdo->prepare("SELECT * FROM forms_google
            WHERE habilitada=1
              AND tipo_prueba=?
              AND (grupo_grado=? OR grupo_grado='todos')
              AND (fecha_inicio IS NULL OR fecha_inicio <= NOW())
              AND (fecha_cierre IS NULL OR fecha_cierre >= NOW())
            ORDER BY CASE WHEN grupo_grado=? THEN 0 ELSE 1 END, creado_en DESC
            LIMIT 1");
        $q->execute([$tipo, $grupo, $grupo]);
        $row = $q->fetch();
        return $row ?: null;
    } catch(\Exception $e){
        return null;
    }
}

function examenExternoConfig(string $tipo, ?int $grado, string $grupo): ?array {
    if($tipo !== 'clasificatoria') return null;
    if($grupo !== '6-7') return null;

    $map = [
        6 => [
            'titulo' => 'Prueba Clasificatoria - Sexto',
            'descripcion' => 'Examen oficial para estudiantes de grado sexto.',
            'url' => 'https://forms.office.com/r/DDQ4tigFqR',
            'duracion' => 60,
        ],
        7 => [
            'titulo' => 'Prueba Clasificatoria - Septimo',
            'descripcion' => 'Examen oficial para estudiantes de grado septimo.',
            'url' => 'https://forms.office.com/r/sGRWRXA7r1',
            'duracion' => 60,
        ],
    ];

    return $map[$grado] ?? null;
}
?>
