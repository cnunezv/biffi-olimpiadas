<?php
require_once 'includes/config.php';
requireLogin();

$tipo = $_GET['tipo'] ?? 'clasificatoria';
$grado = isset($_SESSION['grado']) ? intval($_SESSION['grado']) : null;
$grupo = miGrupo();
$exam = examenExternoConfig($tipo, $grado, $grupo);

if(!$exam || (int)($_SESSION['rol'] ?? '') === 'admin'){
    if(!$exam && !isDocente()){
        header('Location: curso.php'); exit;
    }
}

$page_title = ($exam['titulo'] ?? 'Prueba externa') . ' - Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
.ext-wrap{max-width:1100px;margin:0 auto;padding:28px 24px}
.ext-hero{background:linear-gradient(135deg,var(--vd),var(--v) 58%,#a84358);border-radius:24px;padding:28px 30px;color:#fff;box-shadow:0 18px 50px rgba(74,15,28,.28);margin-bottom:22px;position:relative;overflow:hidden}
.ext-hero::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(45deg,rgba(255,255,255,.03) 0,rgba(255,255,255,.03) 1px,transparent 1px,transparent 26px)}
.ext-hero > *{position:relative;z-index:1}
.ext-hero h1{font-family:'DM Serif Display',serif;font-size:34px;margin-bottom:8px}
.ext-hero p{font-size:14px;color:rgba(255,255,255,.78);margin-bottom:14px}
.ext-badges{display:flex;gap:10px;flex-wrap:wrap}
.ext-badge{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);font-size:12px;font-weight:700}
.ext-card{background:#fff;border-radius:20px;padding:24px;box-shadow:var(--sh);border:1.5px solid var(--border)}
.ext-card h2{font-size:18px;font-weight:800;color:var(--ink);margin-bottom:8px}
.ext-card p{font-size:13px;color:#87666f;line-height:1.7}
.ext-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
.ext-frame{margin-top:22px;border-radius:18px;overflow:hidden;border:1.5px solid var(--border);box-shadow:var(--sh)}
.ext-frame iframe{width:100%;height:78vh;border:0;background:#fff}
.ext-note{margin-top:14px;font-size:12px;color:#9a6070}
</style>

<div class="ext-wrap">
  <?php if(!$exam): ?>
  <div class="ext-card">
    <h2>No hay un examen externo configurado para este estudiante.</h2>
    <p>Revisa el grado del estudiante o configura otro examen en el sistema.</p>
    <div class="ext-actions"><a href="curso.php" class="btn btn-v">Volver</a></div>
  </div>
  <?php else: ?>
  <div class="ext-hero">
    <h1><?=sanitize($exam['titulo'])?></h1>
    <p><?=sanitize($exam['descripcion'])?></p>
    <div class="ext-badges">
      <span class="ext-badge">🎓 <?=etiquetaGrupo($grupo)?></span>
      <span class="ext-badge">📘 Grado <?=$grado?></span>
      <span class="ext-badge">⏱️ <?=$exam['duracion']?> min</span>
      <span class="ext-badge">🌐 Microsoft Forms</span>
    </div>
  </div>

  <div class="ext-card">
    <h2>Examen oficial integrado</h2>
    <p>Esta prueba se realiza mediante Microsoft Forms. Puedes responderla aquí mismo o abrirla en una pestaña aparte si tu navegador bloquea la vista embebida.</p>
    <div class="ext-actions">
      <a href="<?=sanitize($exam['url'])?>" target="_blank" rel="noopener" class="btn btn-v">Abrir examen</a>
      <a href="curso.php" class="btn btn-outline">Volver al curso</a>
    </div>
    <div class="ext-frame">
      <iframe src="<?=sanitize($exam['url'])?>" title="<?=sanitize($exam['titulo'])?>"></iframe>
    </div>
    <div class="ext-note">Si el formulario no se muestra dentro de la página, usa el botón "Abrir examen".</div>
  </div>
  <?php endif ?>
</div>

<?php require_once 'includes/footer.php'; ?>
