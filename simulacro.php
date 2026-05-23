<?php
require_once 'includes/config.php';
requireLogin();

$nivel = $_GET['nivel'] ?? 'basico';
$tipo  = $_GET['tipo']  ?? 'simulacro';
if(!in_array($nivel,['basico','medio','avanzado'])) $nivel='basico';
if(!array_key_exists($tipo,tiposPrueba()))           $tipo='simulacro';

$mi_grupo = miGrupo();
if(isDocente() && isset($_GET['grupo']) && in_array($_GET['grupo'],['4-5','6-7','8-9','10-11']))
    $mi_grupo = $_GET['grupo'];

// Cargar configuración de la prueba
$cfg_row = null;
try {
    $sc = $pdo->prepare("SELECT * FROM pruebas_config WHERE tipo_prueba=? AND grupo_grado=?");
    $sc->execute([$tipo,$mi_grupo]); $cfg_row=$sc->fetch();
} catch(\Exception $e){}

$tiempo_limite  = $cfg_row['tiempo_limite_min'] ?? null;
$num_preg       = $cfg_row['num_preguntas']     ?? 10;
$instrucciones  = $cfg_row['instrucciones']     ?? '';
$max_intentos   = isset($cfg_row['max_intentos']) ? (int)$cfg_row['max_intentos'] : 0;
$habilitada     = pruebaHabilitada($pdo, $tipo, $mi_grupo);
$intentos_rest  = intentosRestantes($pdo, (int)$_SESSION['user_id'], $tipo, $mi_grupo, $nivel);

// Bloquear si no habilitada o sin intentos
if(!$habilitada && !isDocente() && $tipo!=='simulacro'){
    header('Location: curso.php'); exit;
}
if(!isDocente() && $intentos_rest === 0){
    // Mostrar pantalla de bloqueado en lugar de redirigir
    $bloqueado = true;
}

// Cargar preguntas
$preguntas = [];
if(empty($bloqueado)){
    $stmt = $pdo->prepare("SELECT * FROM preguntas WHERE nivel=? AND grupo_grado=? AND tipo_prueba=? ORDER BY RAND() LIMIT ".intval($num_preg));
    $stmt->execute([$nivel,$mi_grupo,$tipo]);
    $preguntas = $stmt->fetchAll();
}

// Procesar respuestas enviadas
$resultado = null;
if(!empty($bloqueado));
elseif($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['respuestas'])){
    $resp=$_POST['respuestas']; $correcto=0; $detalle=[];
    foreach($preguntas as $p){
        $r=$resp[$p['id']]??''; $ok=($r===$p['correcta']);
        if($ok) $correcto++;
        $detalle[]=json_encode(['q'=>$p['pregunta'],'tu'=>$r,'correcta'=>$p['correcta'],'ok'=>$ok,'exp'=>$p['explicacion']]);
    }
    $total=count($preguntas); $tiempo=intval($_POST['tiempo']??0);
    $nivel_key=$nivel.'_'.$mi_grupo.'_'.$tipo;
    $pdo->prepare("INSERT INTO resultados(usuario_id,nivel,puntaje,total,tiempo_seg,detalle) VALUES(?,?,?,?,?,?)")
        ->execute([$_SESSION['user_id'],$nivel_key,$correcto,$total,$tiempo,implode('||',$detalle)]);
    $resultado=['puntaje'=>$correcto,'total'=>$total,'tiempo'=>$tiempo,'detalle'=>$detalle,'preguntas'=>$preguntas];
    // Recalculate intentos_rest after saving
    $intentos_rest = intentosRestantes($pdo, (int)$_SESSION['user_id'], $tipo, $mi_grupo, $nivel);
}

$niveles_lbl=['basico'=>'Básico','medio'=>'Medio','avanzado'=>'Avanzado'];
$tipos_info=tiposPrueba();
$tipo_info=$tipos_info[$tipo]??['🏋️','Simulacro',''];
$page_title=$tipo_info[0].' '.$tipo_info[1].' — '.$niveles_lbl[$nivel].' — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<script>
window.MathJax={tex:{inlineMath:[['$','$'],['\\(','\\)']],displayMath:[['$$','$$'],['\\[','\\]']],processEscapes:true},options:{skipHtmlTags:['script','noscript','style','textarea']}};
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<style>
/* ─── WRAPPER ──────────────────────────────────────────── */
.quiz-wrap { max-width:860px; margin:0 auto; padding:28px 20px; }

/* ─── HEADER PRUEBA ───────────────────────────────────── */
.quiz-header {
  background:linear-gradient(135deg,var(--vd) 0%,#6B1726 50%,var(--v) 100%);
  border-radius:20px; padding:26px 32px; margin-bottom:24px;
  display:flex; justify-content:space-between; align-items:flex-start;
  flex-wrap:wrap; gap:16px;
  box-shadow:0 8px 32px rgba(74,15,28,.3);
  position:relative; overflow:hidden;
}
.quiz-header::before {
  content:''; position:absolute; inset:0;
  background:repeating-linear-gradient(45deg,rgba(255,255,255,.03) 0,rgba(255,255,255,.03) 1px,transparent 1px,transparent 22px),
              repeating-linear-gradient(-45deg,rgba(255,255,255,.03) 0,rgba(255,255,255,.03) 1px,transparent 1px,transparent 22px);
}
.qh-left { position:relative; }
.qh-tipo { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.qh-tipo-badge {
  background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.3);
  color:white; font-size:11px; font-weight:700; padding:3px 12px; border-radius:20px;
  letter-spacing:.04em;
}
.qh-title {
  font-family:'DM Serif Display',serif; font-size:24px; color:white;
  line-height:1.2; margin-bottom:6px;
}
.qh-meta { font-size:13px; color:rgba(255,255,255,.65); display:flex; gap:14px; flex-wrap:wrap; }
.qh-meta span { display:flex; align-items:center; gap:4px; }
.instrucciones {
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2);
  border-radius:10px; padding:10px 14px; font-size:12.5px; color:rgba(255,255,255,.85);
  line-height:1.6; margin-top:10px; max-width:500px;
}
/* Timer */
.timer-box {
  background:rgba(0,0,0,.25); border:1.5px solid rgba(255,255,255,.2);
  border-radius:14px; padding:14px 20px; text-align:center; min-width:100px;
  position:relative; flex-shrink:0;
}
.timer-label { font-size:9px; color:rgba(255,255,255,.55); text-transform:uppercase; letter-spacing:.1em; margin-bottom:4px; }
.timer-display { font-size:30px; font-family:'JetBrains Mono',monospace; font-weight:700; color:white; line-height:1; }
.timer-total { font-size:10px; color:rgba(255,255,255,.5); margin-top:5px; }
.timer-bar-wrap { height:3px; background:rgba(255,255,255,.15); border-radius:2px; overflow:hidden; margin-top:6px; }
.timer-bar-fill { height:100%; background:rgba(255,255,255,.7); border-radius:2px; transition:width 1s linear; }
.timer-box.warn { border-color:rgba(255,100,100,.6); background:rgba(180,30,30,.35); }
.timer-box.warn .timer-display { color:#ff8a80; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.6} }
.timer-box.critical { animation:pulse .6s infinite; }

/* ─── PROGRESS BAR ────────────────────────────────────── */
.prog-wrap { margin-bottom:22px; }
.prog-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.prog-label { font-size:12px; font-weight:700; color:#9a6070; }
.prog-count { font-size:12px; font-weight:700; color:var(--vd); font-family:'JetBrains Mono',monospace; }
.prog-track { height:6px; background:var(--border); border-radius:3px; overflow:hidden; }
.prog-fill { height:100%; background:linear-gradient(90deg,var(--v),var(--vl)); border-radius:3px; transition:width .4s; }
.prog-steps { display:flex; gap:3px; flex-wrap:wrap; margin-top:8px; }
.ps { width:26px; height:6px; border-radius:3px; background:var(--border); transition:background .3s; cursor:pointer; }
.ps.done { background:var(--v); }
.ps.current { background:var(--gold); }
.ps:hover { opacity:.75; }

/* ─── PREGUNTA CARD ───────────────────────────────────── */
.qcard {
  background:white; border-radius:16px; margin-bottom:16px;
  box-shadow:0 2px 16px rgba(74,15,28,.08);
  border:1.5px solid var(--border);
  transition:box-shadow .2s;
  overflow:hidden;
}
.qcard:hover { box-shadow:0 4px 24px rgba(124,31,48,.14); }
.qcard-head {
  padding:14px 22px 0;
  display:flex; align-items:center; gap:10px;
}
.qcard-num {
  width:30px; height:30px; border-radius:8px;
  background:var(--vd); color:white;
  font-size:13px; font-weight:800;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.qcard-meta { flex:1; }
.qcard-num-lbl { font-size:11px; font-weight:700; color:var(--v); text-transform:uppercase; letter-spacing:.08em; }
.qcard-tema { font-size:11px; color:#9a6070; }
.qcard-body { padding:14px 22px 18px; }
.qtext { font-size:15.5px; font-weight:600; color:var(--ink); line-height:1.7; margin-bottom:16px; }
.qimg { width:100%; max-height:280px; object-fit:contain; border-radius:10px; margin-bottom:16px; border:1.5px solid var(--border); background:#fdf8f9; }

/* ─── OPCIONES ────────────────────────────────────────── */
.opts { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media(max-width:600px){ .opts{grid-template-columns:1fr;} }
.opt {
  display:flex; align-items:center; gap:12px;
  padding:12px 16px; border-radius:12px;
  border:2px solid var(--border); cursor:pointer;
  transition:all .2s; background:var(--mist);
}
.opt:hover { background:#faeef1; border-color:var(--vl); transform:translateY(-1px); }
.opt:has(input:checked) {
  background:linear-gradient(135deg,#faeef1,#fdf5f7);
  border-color:var(--v);
  box-shadow:0 0 0 3px rgba(124,31,48,.1);
}
.opt input { display:none; }
.opt-c {
  width:32px; height:32px; border-radius:50%;
  background:white; border:2px solid var(--border);
  display:flex; align-items:center; justify-content:center;
  font-size:12px; font-weight:800; color:var(--vd); flex-shrink:0;
  transition:all .2s;
}
.opt:has(input:checked) .opt-c { background:var(--v); border-color:var(--v); color:white; }
.opt-t { font-size:13.5px; color:var(--ink); line-height:1.5; font-weight:500; }

/* ─── BOTÓN ENVIAR ────────────────────────────────────── */
.submit-wrap {
  text-align:center; padding:20px 0 40px;
  position:sticky; bottom:0;
  background:linear-gradient(to top,var(--mist) 60%,transparent);
}
.btn-submit {
  background:linear-gradient(135deg,var(--v),var(--vd));
  color:white; border:none; border-radius:14px;
  padding:15px 40px; font-family:'Sora',sans-serif;
  font-size:15px; font-weight:700; cursor:pointer;
  box-shadow:0 8px 28px rgba(124,31,48,.35);
  transition:all .25s;
  display:inline-flex; align-items:center; gap:10px;
}
.btn-submit:hover { transform:translateY(-3px); box-shadow:0 12px 36px rgba(124,31,48,.45); }

/* ─── RESULTADO ───────────────────────────────────────── */
.res-wrap { max-width:580px; margin:40px auto; }
.res-hero {
  background:linear-gradient(135deg,var(--vd),var(--v));
  border-radius:20px; padding:36px;
  text-align:center; box-shadow:var(--shh);
  margin-bottom:28px; position:relative; overflow:hidden;
}
.res-hero::before{
  content:''; position:absolute; inset:0;
  background:repeating-linear-gradient(45deg,rgba(255,255,255,.04) 0,rgba(255,255,255,.04) 1px,transparent 1px,transparent 24px);
}
.res-emoji { font-size:64px; margin-bottom:12px; display:block; position:relative; }
.res-score {
  font-family:'DM Serif Display',serif;
  font-size:62px; line-height:1; color:white; font-weight:400;
  position:relative; margin-bottom:4px;
}
.res-pct { font-size:20px; color:rgba(255,255,255,.75); position:relative; margin-bottom:16px; }
.res-msg-box {
  background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25);
  border-radius:12px; padding:12px 18px;
  font-size:13.5px; color:white; font-weight:600; position:relative;
}
.res-blocked {
  background:#fde8e8; border:2px solid #e57373;
  border-radius:14px; padding:24px; text-align:center; margin-bottom:20px;
}
.res-blocked h3 { color:#c0392b; font-size:17px; font-weight:700; margin-bottom:8px; }
.res-blocked p  { color:#7a3030; font-size:13.5px; line-height:1.6; }
.res-actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:20px; }
.res-stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px; }
.res-stat {
  background:white; border-radius:14px; padding:16px; text-align:center;
  box-shadow:var(--sh); border:1.5px solid var(--border);
}
.res-stat .v { font-family:'DM Serif Display',serif; font-size:26px; color:var(--vd); }
.res-stat .l { font-size:11px; color:#9a6070; font-weight:600; margin-top:3px; }

/* ─── REVISIÓN ────────────────────────────────────────── */
.review-title { font-family:'DM Serif Display',serif; font-size:20px; color:var(--ink); margin-bottom:18px; display:flex; align-items:center; gap:10px; }
.review-card {
  background:white; border-radius:14px;
  margin-bottom:14px; box-shadow:var(--sh);
  border:1.5px solid var(--border); overflow:hidden;
}
.rc-head {
  padding:12px 18px; display:flex; align-items:center; gap:10px;
  border-bottom:1px solid var(--border);
}
.rc-badge {
  padding:3px 10px; border-radius:20px;
  font-size:11px; font-weight:800; flex-shrink:0;
}
.rc-ok  { background:#e8f5e9; color:#2e7d32; }
.rc-bad { background:#fde8e8; color:#c0392b; }
.rc-body { padding:16px 18px; }
.rc-q { font-size:14.5px; font-weight:600; color:var(--ink); margin-bottom:12px; line-height:1.6; }
.rc-ans { font-size:13px; margin-bottom:4px; }
.exp-box {
  background:#fffbf0; border-radius:10px; padding:11px 16px;
  margin-top:10px; font-size:13px; color:#7a5060;
  border-left:4px solid var(--gold); line-height:1.6;
}

/* ─── BLOQUEADO ───────────────────────────────────────── */
.bloqueado-card {
  background:white; border-radius:20px; padding:48px 36px;
  text-align:center; box-shadow:var(--shh); border:2px solid #e57373;
  max-width:500px; margin:60px auto;
}
.bloqueado-card .ico { font-size:64px; margin-bottom:16px; display:block; }
.bloqueado-card h2 { font-family:'DM Serif Display',serif; font-size:26px; color:#c0392b; margin-bottom:10px; }
.bloqueado-card p { font-size:14px; color:#9a6070; line-height:1.7; max-width:340px; margin:0 auto 24px; }

/* ─── SIN PREGUNTAS ───────────────────────────────────── */
.no-preg { background:white; border-radius:18px; padding:52px 36px; text-align:center; box-shadow:var(--sh); border:2px dashed var(--border); }
.no-preg span { font-size:56px; display:block; margin-bottom:14px; }
</style>

<div class="quiz-wrap">

<?php if(!empty($bloqueado) && !isset($resultado)): ?>
<!-- ── PRUEBA BLOQUEADA ─────────────────────────────── -->
<div style="max-width:500px;margin:60px auto;background:white;border-radius:20px;
  padding:48px 36px;text-align:center;box-shadow:0 12px 48px rgba(74,15,28,.15);
  border:2px solid #ffcdd2;">
  <div style="font-size:72px;margin-bottom:16px">🔒</div>
  <h2 style="font-family:'DM Serif Display',serif;font-size:26px;color:#c0392b;margin-bottom:10px">
    Prueba bloqueada
  </h2>
  <p style="font-size:14.5px;color:#7a3030;max-width:340px;margin:0 auto 10px;line-height:1.7">
    Ya completaste el número máximo de intentos permitidos para la
    <strong><?=$tipo_info[1]?></strong> nivel <strong><?=$niveles_lbl[$nivel]?></strong>.
  </p>
  <?php if($max_intentos>0): ?>
  <p style="font-size:13px;color:#9a6070;margin-bottom:24px">
    Límite: <strong><?=$max_intentos?> intento<?=$max_intentos!=1?'s':''?></strong>
  </p>
  <?php endif ?>
  <p style="font-size:13px;color:#bbb;margin-bottom:24px">
    Si necesitas más intentos, comunícate con tu docente.
  </p>
  <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
    <a href="mensajes.php" class="btn btn-outline" style="border-color:#e53935;color:#e53935">✉️ Contactar docente</a>
    <a href="pruebas.php?tipo=<?=$tipo?>" class="btn btn-outline">← Cambiar nivel</a>
    <a href="curso.php" class="btn btn-v">← Volver al curso</a>
  </div>
</div>

<?php elseif(isset($resultado)):
  $r=$resultado;
  $pct=$r['total']>0?round($r['puntaje']/$r['total']*100):0;
  $emoji=$pct>=90?'🏆':($pct>=75?'🥇':($pct>=60?'🌟':($pct>=40?'💪':'📚')));
  $msg=$pct>=90?'¡Excelente! Dominio sobresaliente.':($pct>=75?'¡Muy bien! Resultado destacado.':($pct>=60?'Buen trabajo. Sigue practicando.':($pct>=40?'Buen intento. Repasa los temas fallados.':'Sigue estudiando, ¡tú puedes!')));
  $minutos=floor($r['tiempo']/60); $segs=$r['tiempo']%60;
  $ahora_bloqueado = (!isDocente() && $max_intentos > 0 && $intentos_rest === 0 && $tipo !== 'simulacro');
  $intentos_usados_total = ($max_intentos > 0) ? ($max_intentos - max(0,$intentos_rest)) : 0;
?>
<!-- ── RESULTADO ─────────────────────────────────────── -->
<div class="res-wrap">
  <div class="res-hero">
    <span class="res-emoji"><?=$emoji?></span>
    <div class="res-score"><?=$r['puntaje']?>/<?=$r['total']?></div>
    <div class="res-pct"><?=$pct?>% correcto</div>
    <div class="res-msg-box"><?=$msg?></div>
  </div>

  <div class="res-stat-row">
    <div class="res-stat"><div class="v"><?=$r['puntaje']?></div><div class="l">Correctas</div></div>
    <div class="res-stat"><div class="v"><?=$r['total']-$r['puntaje']?></div><div class="l">Incorrectas</div></div>
    <div class="res-stat"><div class="v"><?=sprintf('%02d:%02d',$minutos,$segs)?></div><div class="l">Tiempo</div></div>
    <?php if($max_intentos>0): ?>
    <div class="res-stat"><div class="v"><?=$intentos_usados_total?>/<?=$max_intentos?></div><div class="l">Intentos</div></div>
    <?php endif ?>
  </div>

  <?php if($ahora_bloqueado): ?>
  <!-- BLOQUEO AUTOMÁTICO PROMINENTE -->
  <div style="background:linear-gradient(135deg,#fde8e8,#fff0f0);border:2px solid #e53935;
    border-radius:16px;padding:24px 28px;margin:20px 0;text-align:center;">
    <div style="font-size:48px;margin-bottom:10px">🔒</div>
    <div style="font-size:17px;font-weight:800;color:#c0392b;margin-bottom:8px">
      Prueba bloqueada — intentos agotados
    </div>
    <div style="font-size:13.5px;color:#7a3030;max-width:380px;margin:0 auto 16px;line-height:1.6">
      Usaste los <strong><?=$max_intentos?> intento<?=$max_intentos!=1?'s':''?></strong> permitidos para esta prueba.
      No puedes volver a realizarla.<br>
      Si necesitas más intentos, contacta a tu docente.
    </div>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <a href="mensajes.php" class="btn btn-outline" style="border-color:#e53935;color:#e53935">✉️ Contactar docente</a>
      <a href="curso.php" class="btn btn-v">← Volver al curso</a>
    </div>
  </div>
  <?php endif ?>

  <div class="res-actions">
    <?php if(!$ahora_bloqueado): ?>
      <?php if($tipo==='simulacro' || ($intentos_rest !== 0)): ?>
      <a href="simulacro.php?nivel=<?=$nivel?>&tipo=<?=$tipo?>" class="btn btn-outline">🔄 Reintentar</a>
      <?php endif ?>
    <?php endif ?>
    <a href="pruebas.php?tipo=<?=$tipo?>" class="btn btn-outline">← Cambiar nivel</a>
    <a href="curso.php" class="btn btn-v">← Curso</a>
  </div>

  <div class="review-title" style="margin-top:32px">📋 Revisión detallada</div>
  <?php foreach($r['detalle'] as $i=>$dj):
    $d=json_decode($dj,true); $pq=$r['preguntas'][$i];
  ?>
  <div class="review-card">
    <div class="rc-head">
      <span class="rc-badge <?=$d['ok']?'rc-ok':'rc-bad'?>"><?=$d['ok']?'✅ Correcto':'❌ Incorrecto'?></span>
      <span style="font-size:11.5px;color:#9a6070"><?=sanitize($pq['tema']??'')?></span>
    </div>
    <div class="rc-body">
      <div class="rc-q math-render"><?=htmlspecialchars_decode(htmlspecialchars($d['q'],ENT_NOQUOTES))?></div>
      <?php if(!empty($pq['imagen_url'])): ?>
      <img src="<?=sanitize(SITE_URL.'/'.$pq['imagen_url'])?>" class="qimg" alt="">
      <?php endif ?>
      <?php if(!$d['ok']): ?>
      <div class="rc-ans" style="color:#c0392b">Tu respuesta: <strong class="math-render"><?=$d['tu']?htmlspecialchars_decode(htmlspecialchars($d['tu'],ENT_NOQUOTES)):'(sin respuesta)'?></strong></div>
      <?php endif ?>
      <div class="rc-ans" style="color:#2e7d32">Respuesta correcta: <strong class="math-render"><?=htmlspecialchars_decode(htmlspecialchars($d['correcta'],ENT_NOQUOTES))?></strong></div>
      <?php if($d['exp']): ?>
      <div class="exp-box math-render">💡 <?=htmlspecialchars_decode(htmlspecialchars($d['exp'],ENT_NOQUOTES))?></div>
      <?php endif ?>
    </div>
  </div>
  <?php endforeach ?>
</div>

<?php else: ?>
<!-- ── PRUEBA EN CURSO ───────────────────────────────── -->
<div class="quiz-header">
  <div class="qh-left">
    <div class="qh-tipo">
      <span class="qh-tipo-badge" style="background:<?=colorTipo($tipo)?>"><?=$tipo_info[0]?> <?=$tipo_info[1]?></span>
      <span class="qh-tipo-badge"><?=etiquetaGrupo($mi_grupo)?></span>
      <span class="qh-tipo-badge">Nivel <?=$niveles_lbl[$nivel]?></span>
    </div>
    <h1 class="qh-title"><?=$tipo_info[1]?> de Matemáticas</h1>
    <div class="qh-meta">
      <span>📝 <?=count($preguntas)?> preguntas</span>
      <?php if($tiempo_limite): ?><span>⏱️ <?=$tiempo_limite?> minutos</span><?php endif ?>
      <?php if($intentos_rest > 0): ?><span style="color:rgba(255,220,100,.95);font-weight:700">🔁 <?=$intentos_rest?> intento<?=$intentos_rest!==1?'s':''?> restante<?=$intentos_rest!==1?'s':''?></span>
      <?php elseif($intentos_rest < 0): ?><span>🔁 Sin límite de intentos</span><?php endif ?>
    </div>
    <?php if($instrucciones): ?><div class="instrucciones">ℹ️ <?=sanitize($instrucciones)?></div><?php endif ?>
  </div>
  <div class="timer-box" id="timer-box">
    <div class="timer-label"><?=$tiempo_limite?'Tiempo restante':'Tiempo'?></div>
    <div class="timer-display" id="td"><?=$tiempo_limite?sprintf('%02d:00',$tiempo_limite):'00:00'?></div>
    <?php if($tiempo_limite): ?>
    <div class="timer-bar-wrap"><div class="timer-bar-fill" id="timer-bar"></div></div>
    <div class="timer-total">de <?=$tiempo_limite?> min totales</div>
    <?php endif ?>
  </div>
</div>

<!-- Barra de progreso -->
<div class="prog-wrap">
  <div class="prog-header">
    <span class="prog-label">Progreso</span>
    <span class="prog-count" id="prog-count">0 / <?=count($preguntas)?></span>
  </div>
  <div class="prog-track"><div class="prog-fill" id="prog-fill" style="width:0%"></div></div>
  <div class="prog-steps" id="steps">
    <?php foreach($preguntas as $i=>$p): ?>
    <div class="ps <?=$i===0?'current':''?>" id="step-<?=$i?>" title="Pregunta <?=$i+1?>"></div>
    <?php endforeach ?>
  </div>
</div>

<?php if(empty($preguntas)): ?>
<div class="no-preg">
  <span>📭</span>
  <h3 style="font-size:17px;font-weight:700;color:var(--vd);margin-bottom:8px">Sin preguntas disponibles</h3>
  <p style="font-size:14px;color:#9a6070;margin-bottom:20px">
    No hay preguntas de <strong><?=$tipo_info[1]?></strong> nivel <strong><?=$niveles_lbl[$nivel]?></strong> para <strong><?=etiquetaGrupo($mi_grupo)?></strong>.
    <?php if(puedeEditarPruebas()): ?><br><a href="editor_simulacro.php?grupo=<?=$mi_grupo?>&tipo=<?=$tipo?>" style="color:var(--v);font-weight:700">Agregar preguntas →</a><?php else: ?><br>El docente las agregará pronto.<?php endif ?>
  </p>
  <a href="curso.php" class="btn btn-v">← Volver al curso</a>
</div>

<?php else: ?>
<form method="POST" id="qf">
  <input type="hidden" name="tiempo" id="ti" value="0">
  <?php $letras=['A','B','C','D']; foreach($preguntas as $i=>$p):
    $ops=array_filter([$p['op1'],$p['op2'],$p['op3'],$p['op4']]);
  ?>
  <div class="qcard" id="qc-<?=$i?>">
    <div class="qcard-head">
      <div class="qcard-num"><?=$i+1?></div>
      <div class="qcard-meta">
        <div class="qcard-num-lbl">Pregunta <?=$i+1?> de <?=count($preguntas)?></div>
        <div class="qcard-tema"><?=sanitize($p['tema'])?></div>
      </div>
    </div>
    <div class="qcard-body">
      <div class="qtext math-render"><?=htmlspecialchars_decode(htmlspecialchars($p['pregunta'],ENT_NOQUOTES))?></div>
      <?php if(!empty($p['imagen_url'])): ?>
      <img src="<?=sanitize(SITE_URL.'/'.$p['imagen_url'])?>" class="qimg" alt="" onerror="this.style.display='none'">
      <?php endif ?>
      <div class="opts">
        <?php foreach(array_values($ops) as $j=>$op): ?>
        <label class="opt" onclick="markStep(<?=$i?>)">
          <input type="radio" name="respuestas[<?=$p['id']?>]" value="<?=htmlspecialchars($op,ENT_QUOTES)?>">
          <span class="opt-c"><?=$letras[$j]?></span>
          <span class="opt-t math-render"><?=htmlspecialchars_decode(htmlspecialchars($op,ENT_NOQUOTES))?></span>
        </label>
        <?php endforeach ?>
      </div>
    </div>
  </div>
  <?php endforeach ?>
  <div class="submit-wrap">
    <button type="button" class="btn-submit" onclick="submitQuiz()">
      ✅ Enviar respuestas
    </button>
  </div>
</form>
<?php endif ?>
<?php endif ?>
</div><!-- /quiz-wrap -->

<script>
const MODO_COUNTDOWN = <?=$tiempo_limite?'true':'false'?>;
const LIMITE_SEG     = <?=$tiempo_limite?$tiempo_limite*60:0?>;
const TOTAL_PREG     = <?=count($preguntas??[])?>;
let sec = 0, respondidas = 0;
const tiEl   = document.getElementById('td');
const tiInp  = document.getElementById('ti');
const tBox   = document.getElementById('timer-box');
const tBar   = document.getElementById('timer-bar');

const iv = setInterval(()=>{
  sec++;
  if(tiInp) tiInp.value = sec;
  if(MODO_COUNTDOWN){
    const rest = Math.max(0, LIMITE_SEG - sec);
    const m=Math.floor(rest/60), s=rest%60;
    if(tiEl) tiEl.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    if(tBar) tBar.style.width = Math.max(0,(rest/LIMITE_SEG)*100)+'%';
    if(rest<=30 && tBox){ tBox.classList.add('critical'); }
    else if(rest<=LIMITE_SEG*.2 && tBox){ tBox.classList.remove('critical'); tBox.classList.add('warn'); }
    else if(rest<=LIMITE_SEG*.5 && tBox){ tBox.classList.add('warn'); }
    if(rest<=0){ clearInterval(iv); submitQuiz(true); }
  } else {
    const m=Math.floor(sec/60), s=sec%60;
    if(tiEl) tiEl.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  }
}, 1000);

function updateProgress(){
  respondidas = document.querySelectorAll('.qcard input:checked').length;
  document.getElementById('prog-count').textContent = respondidas+' / '+TOTAL_PREG;
  document.getElementById('prog-fill').style.width = TOTAL_PREG>0?(respondidas/TOTAL_PREG*100)+'%':'0%';
}

function markStep(i){
  const idx=i;
  setTimeout(()=>{
    const card=document.getElementById('qc-'+idx);
    const isAnswered=card&&card.querySelector('input:checked');
    document.getElementById('step-'+idx)?.classList.toggle('done',!!isAnswered);
    document.getElementById('step-'+idx)?.classList.remove('current');
    updateProgress();
    // Move current to next unanswered
    for(let j=0;j<TOTAL_PREG;j++){
      const c=document.getElementById('qc-'+j);
      if(c&&!c.querySelector('input:checked')){
        document.getElementById('step-'+j)?.classList.add('current'); break;
      }
    }
  },50);
}

function submitQuiz(auto=false){
  if(!auto){
    const u=TOTAL_PREG-respondidas;
    if(u>0&&!confirm(`Tienes ${u} pregunta${u!==1?'s':''} sin responder. ¿Enviar de todos modos?`))return;
  } else {
    alert('⏱️ ¡Tiempo agotado! La prueba se envía automáticamente.');
  }
  clearInterval(iv);
  document.getElementById('qf')?.submit();
}

// Init progress
document.querySelectorAll('.opts label').forEach(l=>l.addEventListener('change',()=>updateProgress()));
updateProgress();

window.addEventListener('load',()=>{
  if(window.MathJax)MathJax.typesetPromise(document.querySelectorAll('.math-render')).catch(()=>{});
});
</script>
<?php require_once 'includes/footer.php'; ?>
