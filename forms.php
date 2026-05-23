<?php
require_once 'includes/config.php';
requireLogin();

$mi_grupo = miGrupo();
$ver_id   = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;

// Cargar formularios disponibles para este grupo
$q = $pdo->prepare("SELECT * FROM forms_google
  WHERE habilitada=1
    AND (grupo_grado=? OR grupo_grado='todos')
    AND (fecha_inicio IS NULL OR fecha_inicio <= NOW())
    AND (fecha_cierre IS NULL OR fecha_cierre >= NOW())
  ORDER BY creado_en DESC");
$q->execute([$mi_grupo]);
$forms = $q->fetchAll();

// Formulario activo
$form_activo = null;
if($ver_id){
    $sf = $pdo->prepare("SELECT * FROM forms_google WHERE id=? AND habilitada=1");
    $sf->execute([$ver_id]); $form_activo = $sf->fetch();
    // Verificar acceso del grupo
    if($form_activo && $form_activo['grupo_grado'] !== 'todos' && $form_activo['grupo_grado'] !== $mi_grupo){
        $form_activo = null;
    }
}

// Helper: convertir URL de Forms a URL embed
function formEmbedUrl(string $url): string {
    // https://forms.gle/xxxx  →  ya es corta, expandir
    // https://docs.google.com/forms/d/e/XXXX/viewform  →  /viewform?embedded=true
    $url = trim($url);
    // Quitar parámetros existentes salvo usp
    $url = preg_replace('/[?&]embedded=[^&]*/','', $url);
    $url = preg_replace('/[?&]usp=[^&]*/','', $url);
    // Agregar embedded=true
    $sep = str_contains($url,'?') ? '&' : '?';
    return $url . $sep . 'embedded=true';
}

// Tiempo restante si hay límite
$tiempo_limite = $form_activo['tiempo_limite_min'] ?? null;

$page_title = 'Formularios — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
.fw { display:flex; min-height:calc(100vh - 68px); }
/* ── LISTA IZQUIERDA ────── */
.fl {
  width:300px; flex-shrink:0; background:white;
  border-right:1px solid var(--border);
  display:flex; flex-direction:column;
  height:calc(100vh - 68px); position:sticky; top:68px; overflow:hidden;
}
.fl-head { padding:16px; border-bottom:1px solid var(--vp);
  background:linear-gradient(135deg,var(--vd),var(--v)); }
.fl-head h2 { font-size:15px; font-weight:700; color:white; }
.fl-head p  { font-size:11.5px; color:rgba(255,255,255,.6); margin-top:3px; }
.fl-body { flex:1; overflow-y:auto; padding:8px 0; }
.fl-body::-webkit-scrollbar { width:4px; }
.fl-body::-webkit-scrollbar-thumb { background:#d4a0b0; border-radius:2px; }
.fi { display:block; padding:13px 16px; border-bottom:1px solid var(--vp);
  cursor:pointer; text-decoration:none; color:inherit; transition:background .15s;
  border-left:3px solid transparent; }
.fi:hover { background:#fdf5f7; }
.fi.act { background:#faeef1; border-left-color:var(--v); }
.fi-title { font-size:13.5px; font-weight:700; color:var(--ink); margin-bottom:4px; line-height:1.4; }
.fi-meta  { display:flex; gap:7px; flex-wrap:wrap; }
.fl-empty { padding:40px 16px; text-align:center; color:#c0a0a8; font-size:13px; }
.fl-empty span { font-size:42px; display:block; margin-bottom:8px; }
/* ── CONTENIDO DERECHO ─── */
.fd { flex:1; display:flex; flex-direction:column; background:var(--mist); overflow:hidden; }
/* Barra superior del form activo */
.fd-bar {
  background:white; border-bottom:1px solid var(--border);
  padding:14px 24px; display:flex; align-items:center;
  justify-content:space-between; flex-wrap:wrap; gap:10px;
  position:sticky; top:68px; z-index:5;
}
.fd-bar h2 { font-size:16px; font-weight:700; color:var(--ink); }
.fd-bar p  { font-size:12.5px; color:#9a6070; margin-top:2px; }
.fd-timer {
  background:var(--vd); color:white; border-radius:10px;
  padding:8px 16px; text-align:center; min-width:80px;
}
.fd-timer strong { display:block; font-size:20px; font-family:'JetBrains Mono',monospace; }
.fd-timer span   { font-size:10px; opacity:.7; }
.fd-timer.warn   { background:#e53935; animation:pulse .8s infinite; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.7} }
/* Iframe del form */
.form-frame-wrap { flex:1; position:relative; }
.form-frame-wrap iframe {
  width:100%; height:100%; border:none; min-height:calc(100vh - 140px);
}
/* Estado vacío / bienvenida */
.fd-welcome {
  flex:1; display:flex; flex-direction:column;
  align-items:center; justify-content:center;
  padding:40px; color:#c0a0a8; text-align:center;
}
.fd-welcome span { font-size:64px; margin-bottom:16px; }
.fd-welcome h3 { font-size:18px; font-weight:700; color:var(--vd); margin-bottom:8px; }
.fd-welcome p  { font-size:14px; color:#9a6070; max-width:360px; }
/* Tiempo agotado overlay */
.tiempo-overlay {
  display:none; position:absolute; inset:0;
  background:rgba(74,15,28,.85); backdrop-filter:blur(6px);
  align-items:center; justify-content:center; z-index:20;
}
.tiempo-overlay.show { display:flex; }
.tiempo-msg {
  background:white; border-radius:18px; padding:36px 40px;
  text-align:center; max-width:380px; box-shadow:0 24px 60px rgba(0,0,0,.3);
}
</style>

<div class="fw">
<!-- ── PANEL IZQUIERDO ────────────────────────── -->
<aside class="fl">
  <div class="fl-head">
    <h2>📋 Formularios Externos</h2>
    <p>🎓 <?=etiquetaGrupo($mi_grupo)?></p>
  </div>
  <div class="fl-body">
    <?php if(empty($forms)): ?>
    <div class="fl-empty">
      <span>📭</span>
      <p>No hay formularios disponibles para tu grupo aún.</p>
    </div>
    <?php else: foreach($forms as $f):
      $act = ($form_activo && $form_activo['id']==$f['id']) ? 'act' : '';
      $tipos_info = tiposPrueba() + ['taller'=>['📝','Taller',''],'evaluacion'=>['📋','Evaluación','']];
      $ti = $tipos_info[$f['tipo_prueba']] ?? ['📋','Evaluación',''];
      $cerrado = $f['fecha_cierre'] && strtotime($f['fecha_cierre']) < time();
    ?>
    <a href="forms.php?id=<?=$f['id']?>" class="fi <?=$act?>">
      <div class="fi-title"><?=sanitize($f['titulo'])?></div>
      <div class="fi-meta">
        <span class="badge" style="background:<?=colorTipo($f['tipo_prueba']??'evaluacion')?>;color:white;font-size:10px">
          <?=$ti[0]?> <?=$ti[1]?>
        </span>
        <?php if($f['grupo_grado']==='todos'): ?>
        <span class="badge b-gray">Todos los grados</span>
        <?php endif ?>
        <?php if($f['tiempo_limite_min']): ?>
        <span class="badge b-red">⏱️ <?=$f['tiempo_limite_min']?> min</span>
        <?php endif ?>
        <?php if($cerrado): ?>
        <span class="badge b-gray">Cerrado</span>
        <?php endif ?>
      </div>
    </a>
    <?php endforeach; endif ?>
  </div>
</aside>

<!-- ── PANEL DERECHO ──────────────────────────── -->
<div class="fd">
<?php if($form_activo):
  $embed_url = (str_contains(strtolower($form_activo['form_url']), 'forms.office.com') || str_contains(strtolower($form_activo['form_url']), 'forms.microsoft.com'))
    ? trim($form_activo['form_url'])
    : formEmbedUrl($form_activo['form_url']);
  $cerrado = $form_activo['fecha_cierre'] && strtotime($form_activo['fecha_cierre']) < time();
?>
  <!-- Barra superior -->
  <div class="fd-bar">
    <div>
      <h2><?=sanitize($form_activo['titulo'])?></h2>
      <p>
        <?php if($form_activo['descripcion']): ?><?=sanitize($form_activo['descripcion'])?> &nbsp;·&nbsp; <?php endif ?>
        🎓 <?=etiquetaGrupo($mi_grupo)?>
        <?php if($form_activo['fecha_cierre']): ?> &nbsp;·&nbsp; 📅 Cierra: <?=date('d/m/Y H:i',strtotime($form_activo['fecha_cierre']))?><?php endif ?>
      </p>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <?php if($tiempo_limite): ?>
      <div class="fd-timer" id="fd-timer">
        <strong id="timer-display"><?=sprintf('%02d:00',$tiempo_limite)?></strong>
        <span>Tiempo restante</span>
      </div>
      <?php endif ?>
      <a href="forms.php" class="btn btn-outline btn-sm">← Lista</a>
    </div>
  </div>

  <?php if($cerrado): ?>
  <div class="fd-welcome">
    <span>🔒</span>
    <h3>Formulario cerrado</h3>
    <p>Este formulario ya no acepta respuestas. La fecha límite venció el <?=date('d/m/Y H:i',strtotime($form_activo['fecha_cierre']))?></p>
    <a href="forms.php" class="btn btn-v" style="margin-top:16px">← Volver</a>
  </div>
  <?php else: ?>
  <!-- Iframe con el formulario -->
  <div class="form-frame-wrap" id="frame-wrap">
    <iframe
      src="<?=htmlspecialchars($embed_url)?>"
      title="<?=sanitize($form_activo['titulo'])?>"
      allowfullscreen
      loading="lazy">
    </iframe>
    <!-- Overlay tiempo agotado -->
    <div class="tiempo-overlay" id="tiempo-overlay">
      <div class="tiempo-msg">
        <div style="font-size:52px;margin-bottom:12px">⏱️</div>
        <h3 style="font-size:18px;font-weight:700;color:var(--vd);margin-bottom:8px">¡Tiempo agotado!</h3>
        <p style="font-size:13.5px;color:#9a6070;margin-bottom:20px">El tiempo para completar este formulario ha terminado.</p>
        <a href="forms.php" class="btn btn-v">← Volver al inicio</a>
      </div>
    </div>
  </div>
  <?php endif ?>

<?php else: ?>
  <div class="fd-welcome">
    <span>📋</span>
    <h3>Formularios externos</h3>
    <p>Selecciona un formulario de la lista para comenzar. Los formularios están diseñados especialmente para <?=etiquetaGrupo($mi_grupo)?>.</p>
  </div>
<?php endif ?>
</div>
</div>

<?php if($tiempo_limite && $form_activo && !($form_activo['fecha_cierre'] && strtotime($form_activo['fecha_cierre']) < time())): ?>
<script>
const LIMITE_SEG = <?=$tiempo_limite * 60?>;
let sec = 0;
const disp = document.getElementById('timer-display');
const box  = document.getElementById('fd-timer');
const overlay = document.getElementById('tiempo-overlay');

const iv = setInterval(()=>{
  sec++;
  const rest = Math.max(0, LIMITE_SEG - sec);
  const m = Math.floor(rest/60), s = rest%60;
  if(disp) disp.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  if(rest <= 60 && box)  box.classList.add('warn');
  if(rest <= 0){
    clearInterval(iv);
    if(overlay) overlay.classList.add('show');
  }
}, 1000);
</script>
<?php endif ?>

<?php require_once 'includes/footer.php'; ?>
