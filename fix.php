<?php
require_once 'includes/config.php';
echo "<style>body{font-family:sans-serif;max-width:700px;margin:50px auto;padding:20px;background:#fdf5f7}
h2{color:#7C1F30}.ok{background:#e8f5e9;border:2px solid #4caf50;border-radius:9px;padding:12px 16px;margin:8px 0;font-size:13.5px}
.err{background:#fde8e8;border:2px solid #e53935;border-radius:9px;padding:12px 16px;margin:8px 0;font-size:13.5px}
code{background:#f0e4e8;padding:2px 8px;border-radius:4px;font-size:13px}
table{width:100%;border-collapse:collapse;font-size:13px;margin-top:20px}
th{background:#7C1F30;color:white;padding:9px 13px}td{padding:9px 13px;border-bottom:1px solid #f5ecee}
.btn{display:inline-block;margin-top:20px;padding:12px 28px;background:#7C1F30;color:white;
     border-radius:9px;text-decoration:none;font-weight:700;font-size:14px}
.warn{margin-top:20px;font-size:12px;color:#e53935;font-weight:700}
</style>
<h2>🔧 Generador de contraseñas — Biffi Olimpiadas v3</h2>";

$usuarios=[
  ['carlos.nunez',  'admin1234',     'admin'],
  ['fabiana.ariza', 'docente123',    'docente'],
  ['vanessa.berrio','docente123',    'docente'],
  ['andres.martinez','docente123',   'docente'],
  ['maria.perez',   'biffi2026',     'estudiante'],
  ['juan.garcia',   'biffi2026',     'estudiante'],
  ['laura.lopez',   'biffi2026',     'estudiante'],
  ['diego.rodriguez','biffi2026',    'estudiante'],
  ['sofia.hernandez','biffi2026',    'estudiante'],
  ['miguel.torres', 'biffi2026',     'estudiante'],
  ['valentina.vargas','biffi2026',   'estudiante'],
  ['santiago.morales','biffi2026',   'estudiante'],
  ['isabella.diaz', 'biffi2026',     'estudiante'],
  ['sebastian.ruiz','biffi2026',     'estudiante'],
];

$ok=0; $fail=0;
foreach($usuarios as [$u,$p,$r]){
  $hash=password_hash($p,PASSWORD_DEFAULT);
  $chk=$pdo->prepare("SELECT id FROM usuarios WHERE usuario=?");
  $chk->execute([$u]); $ex=$chk->fetch();
  if($ex){
    $pdo->prepare("UPDATE usuarios SET contrasena=? WHERE usuario=?")->execute([$hash,$u]);
    // Asignar Colegio Biffi (id=1) si aún no tiene institución
    try { $pdo->prepare("UPDATE usuarios SET institucion_id=1 WHERE usuario=? AND (institucion_id IS NULL OR institucion_id=0)")->execute([$u]); } catch(\Exception $e){}
    echo "<div class='ok'>✅ <strong>$u</strong> actualizado · contraseña: <code>$p</code> · rol: <code>$r</code></div>";
    $ok++;
  } else {
    echo "<div class='err'>⚠️ Usuario <strong>$u</strong> no encontrado. Verifica que importaste el SQL.</div>";
    $fail++;
  }
}

echo "<hr style='margin:20px 0'>
<p style='font-size:14px;color:#4a0f1c'><strong>✅ $ok actualizados · $fail no encontrados.</strong></p>
<table>
<tr><th>Usuario</th><th>Contraseña</th><th>Rol</th></tr>";
foreach($usuarios as [$u,$p,$r])
  echo "<tr><td><code>$u</code></td><td><code>$p</code></td><td>$r</td></tr>";
echo "</table>
<a class='btn' href='index.php'>→ Ir al Login</a>
<p class='warn'>⚠️ BORRA ESTE ARCHIVO (fix.php) después de usarlo.</p>";
?>
