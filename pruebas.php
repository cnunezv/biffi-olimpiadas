<?php
require_once 'includes/config.php';
requireLogin();

$mi_grupo = miGrupo();
$tipo     = $_GET['tipo'] ?? 'simulacro';
if(!array_key_exists($tipo, tiposPrueba())) $tipo = 'simulacro';

$tipos_info = tiposPrueba();
$tipo_info  = $tipos_info[$tipo];
$habilitada = pruebaHabilitada($pdo, $tipo, $mi_grupo);
$form_externo = in_array($tipo, ['clasificatoria','final'], true) ? formExternoConfig($pdo, $tipo, $mi_grupo) : null;
$external_exam = examenExternoConfig($tipo, $_SESSION['grado'] ?? null, $mi_grupo);

if($form_externo && !isDocente()){
    header('Location: forms.php?id='.$form_externo['id']); exit;
}

if($external_exam && !isDocente()){
    header('Location: external_exam.php?tipo='.$tipo); exit;
}

$nivel_estudiante = strtolower($_SESSION['nivel'] ?? '');
if(!$nivel_estudiante && !empty($_SESSION['user_id'])){
    try {
        $sn = $pdo->prepare("SELECT nivel FROM usuarios WHERE id=? LIMIT 1");
        $sn->execute([$_SESSION['user_id']]);
        $nivel_estudiante = strtolower((string)$sn->fetchColumn());
        if($nivel_estudiante) $_SESSION['nivel'] = $nivel_estudiante;
    } catch(\Exception $e){}
}
if(!in_array($nivel_estudiante, ['basico','medio','avanzado'], true)) $nivel_estudiante = 'basico';

$niveles_labels = [
    'basico' => 'BÁSICO',
    'medio' => 'MEDIO',
    'avanzado' => 'AVANZADO',
];
$grupos_labels = [
    '4-5' => '4° y 5°',
    '6-7' => '6° y 7°',
    '8-9' => '8° y 9°',
    '10-11' => '10° y 11°',
];
$nivel_banner = $niveles_labels[$nivel_estudiante] ?? strtoupper($nivel_estudiante);
$grupo_banner = $grupos_labels[$mi_grupo] ?? etiquetaGrupo($mi_grupo);

// Si está bloqueada y no es docente → regresar
if(!$habilitada && !isDocente()){
    header('Location: curso.php'); exit;
}

// Cargar configs por nivel
$niveles = ['basico'=>['📗','Básico','#27AE60'],
            'medio' =>['📙','Medio', '#F57C00'],
            'avanzado'=>['📕','Avanzado','#7C1F30']];

$info_niveles = [];
if($tipo !== 'simulacro' && !isDocente()){
    $niveles = [$nivel_estudiante => $niveles[$nivel_estudiante]];
}
foreach($niveles as $niv=>[$ico,$lbl,$color]){
    $cnt = 0;
    try {
        $c = $pdo->prepare("SELECT COUNT(*) FROM preguntas WHERE tipo_prueba=? AND grupo_grado=? AND nivel=?");
        $c->execute([$tipo,$mi_grupo,$niv]); $cnt=(int)$c->fetchColumn();
    } catch(\Exception $e){}

    $cfg = null;
    try {
        $s = $pdo->prepare("SELECT * FROM pruebas_config WHERE tipo_prueba=? AND grupo_grado=?");
        $s->execute([$tipo,$mi_grupo]); $cfg=$s->fetch();
    } catch(\Exception $e){}

    $intentos_rest = intentosRestantes($pdo,(int)$_SESSION['user_id'],$tipo,$mi_grupo,$niv);
    $max_int       = intval($cfg['max_intentos']??0);
    $usado         = 0;
    if($max_int>0){
        // Count attempts for this exact nivel_grupo_tipo combo
        $niv_e = str_replace('_','\\_',$niv);
        $gg_e  = str_replace('_','\\_',$mi_grupo);
        $patron_niv = $niv_e.'\\_'.$gg_e.'\\_'.$tipo;
        $u=$pdo->prepare("SELECT COUNT(*) FROM resultados WHERE usuario_id=? AND nivel LIKE ? ESCAPE '\\\\'");
        $u->execute([$_SESSION['user_id'],$patron_niv]); $usado=(int)$u->fetchColumn();
    }
    // Blocked = no attempts left (and limit is set). cnt>0 check only for "available" state.
    $bloqueado  = (!isDocente() && $tipo!=='simulacro' && $max_int>0 && $intentos_rest===0);
    $disponible = !$bloqueado; // available even with 0 questions (shows proper message inside)

    $info_niveles[$niv] = compact('ico','lbl','color','cnt','cfg','intentos_rest','max_int','usado','bloqueado','disponible');
}

// Historial reciente del usuario para este tipo
$historial = [];
try {
    // Pattern: %_<grupo>_<tipo> — escape underscores
    $gg_e = str_replace('_', '\\_', $mi_grupo);
    $patron_hist = '%\\_'.$gg_e.'\\_'.$tipo;
    $h=$pdo->prepare("SELECT nivel,puntaje,total,tiempo_seg,fecha FROM resultados
        WHERE usuario_id=? AND nivel LIKE ? ESCAPE '\\\\' ORDER BY fecha DESC LIMIT 9");
    $h->execute([$_SESSION['user_id'], $patron_hist]);
    $historial=$h->fetchAll();
} catch(\Exception $e){}

$page_title = $tipo_info[1].' — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
.sel-page { background:#F7F4F8; min-height:calc(100vh - 64px); }

/* HERO */
.sel-hero {
  background:linear-gradient(135deg, var(--vd) 0%, var(--v) 55%, #A04060 100%);
  padding:36px 24px; position:relative; overflow:hidden;
}
.sel-hero::before {
  content:''; position:absolute; inset:0;
  background-image:radial-gradient(ellipse at 70% 50%, rgba(200,160,80,.15) 0%,transparent 60%),
    repeating-linear-gradient(45deg,rgba(255,255,255,.02) 0,rgba(255,255,255,.02) 1px,transparent 1px,transparent 40px);
}
.sel-hero-inner { max-width:900px; margin:0 auto; position:relative; }
.sel-back { display:inline-flex; align-items:center; gap:6px; color:rgba(255,255,255,.6);
  font-size:12.5px; font-weight:600; margin-bottom:16px; cursor:pointer; text-decoration:none;
  transition:color .2s; }
.sel-back:hover { color:white; }
.sel-hero h1 { font-family:'DM Serif Display',serif; font-size:32px; color:white;
  line-height:1.2; margin-bottom:8px; }
.sel-hero p { font-size:14px; color:rgba(255,255,255,.65); margin-bottom:16px; }
.sel-hero-badges { display:flex; gap:8px; flex-wrap:wrap; }
.shb { display:inline-flex; align-items:center; gap:5px; padding:5px 14px;
  border-radius:20px; font-size:12px; font-weight:700; color:white;
  background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.22); }

/* BODY */
.sel-body { max-width:900px; margin:0 auto; padding:32px 24px; }
.sel-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:20px; margin-bottom:36px; }

/* TARJETA DE NIVEL */
.nivel-card {
  background:white; border-radius:18px; overflow:hidden;
  box-shadow:0 4px 20px rgba(100,50,80,.08);
  border:1.5px solid #E2D5E8;
  transition:all .25s cubic-bezier(.34,1.56,.64,1);
  text-decoration:none; display:block; color:inherit;
}
.nivel-card:hover:not(.blocked) {
  transform:translateY(-6px) scale(1.01);
  box-shadow:0 16px 48px rgba(100,50,80,.18);
  border-color:#c0a0d0;
}
.nivel-card.blocked { opacity:.55; cursor:not-allowed; }

.nc-top {
  padding:28px 24px; position:relative; overflow:hidden;
  display:flex; align-items:flex-end; justify-content:space-between;
}
.nc-top::before {
  content:''; position:absolute; inset:0; opacity:.12;
  background:repeating-linear-gradient(45deg,rgba(255,255,255,.3) 0,rgba(255,255,255,.3) 1px,transparent 1px,transparent 20px);
}
.nc-icon { font-size:48px; position:relative; z-index:1; line-height:1; }
.nc-badge {
  position:relative; z-index:1; padding:5px 13px; border-radius:20px;
  font-size:11px; font-weight:800; color:white;
  background:rgba(0,0,0,.25); backdrop-filter:blur(4px);
}

.nc-body { padding:18px 22px 20px; }
.nc-title { font-size:18px; font-weight:800; color:var(--ink); margin-bottom:6px; }
.nc-sub { font-size:13px; color:#9a6070; line-height:1.5; }

.nc-stats { display:flex; gap:14px; margin:14px 0; flex-wrap:wrap; }
.nc-stat { font-size:12px; color:#9a6070; display:flex; align-items:center; gap:5px; }
.nc-stat strong { color:var(--ink); font-weight:700; }

.nc-cta {
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 22px 18px;
}
.nc-action {
  display:inline-flex; align-items:center; gap:8px;
  padding:10px 22px; border-radius:10px;
  font-size:14px; font-weight:700; color:white;
  border:none; cursor:pointer; transition:all .2s;
}
.nc-action:hover { filter:brightness(1.1); transform:translateY(-2px); }
.nc-blocked-msg {
  font-size:12px; font-weight:700; color:#e53935;
  display:flex; align-items:center; gap:5px;
}
.nc-intentos {
  font-size:11.5px; color:#9a6070; font-weight:600;
  display:flex; align-items:center; gap:5px;
}
/* Barra de intentos */
.intentos-bar { display:flex; gap:4px; align-items:center; }
.int-dot { width:8px; height:8px; border-radius:50%; background:#e0e0e0; flex-shrink:0; }
.int-dot.used { background:#e53935; }
.int-dot.avail { background:#27ae60; }

/* HISTORIAL */
.hist-section h2 { font-family:'DM Serif Display',serif; font-size:20px; color:var(--ink);
  margin-bottom:16px; }
.hist-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px; }
.hist-card {
  background:white; border-radius:12px; padding:16px;
  box-shadow:0 2px 12px rgba(100,50,80,.06); border:1.5px solid #E2D5E8;
  display:flex; flex-direction:column; gap:8px;
}
.hist-pct { font-family:'DM Serif Display',serif; font-size:32px; line-height:1; }
.hist-meta { font-size:12px; color:#9a6070; }
.hist-date { font-size:11px; color:#bbb; }
.hist-bar { height:4px; border-radius:2px; background:#f0e8f5; overflow:hidden; }
.hist-bar-fill { height:100%; border-radius:2px; background:linear-gradient(90deg,var(--v),var(--vl)); }

/* AVISO BLOQUEADO */
.blocked-notice {
  background:white; border-radius:16px; padding:28px 24px;
  border:2px solid #FFCDD2; text-align:center; margin-bottom:28px;
}
.clas-wrap{display:flex;flex-direction:column;gap:24px}
.clas-banner{
  display:flex;align-items:center;gap:14px;background:linear-gradient(90deg,#ff8f00 0%,#ffea00 100%);
  border-radius:18px;padding:14px 20px;box-shadow:0 16px 30px rgba(255,193,7,.2);border:2px solid rgba(255,255,255,.75)
}
.clas-dot{width:12px;height:12px;border-radius:50%;background:#111;flex-shrink:0}
.clas-banner-text{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;color:#111}
.clas-banner-text strong{font-size:15px;font-weight:900;letter-spacing:.05em}
.clas-banner-text span{font-size:14px;letter-spacing:.14em}
.clas-card{
  background:white;border-radius:26px;border:1px solid #e9e9ef;box-shadow:0 20px 44px rgba(57,33,54,.08);overflow:hidden
}
.clas-head{
  background:linear-gradient(135deg,#25ff1c 0%,#14ebb3 100%);padding:34px 28px 30px;border-bottom:4px solid rgba(255,255,255,.65)
}
.clas-head h2{font-size:28px;font-weight:900;letter-spacing:.06em;color:#0e1a11;margin-bottom:6px}
.clas-head p{font-size:14px;color:#0d3a2e;text-transform:uppercase;letter-spacing:.04em}
.clas-body{padding:26px 28px 30px;display:flex;flex-direction:column;gap:28px}
.rule-sec{padding-left:18px;border-left:5px solid #22c55e}
.rule-sec.red{border-left-color:#ef4444}
.rule-sec.teal{border-left-color:#14b8a6}
.rule-sec.blue{border-left-color:#1d4ed8}
.rule-sec.orange{border-left-color:#f97316}
.rule-sec.dark{border-left-color:#111}
.rule-title{display:flex;align-items:center;gap:10px;font-size:22px;font-weight:900;line-height:1.15;margin-bottom:10px}
.rule-title.green{color:#16a34a}
.rule-title.red{color:#ef4444}
.rule-title.teal{color:#0891b2}
.rule-title.blue{color:#0b5ed7}
.rule-title.orange{color:#d95f02}
.rule-title.dark{color:#111}
.rule-text{font-size:15px;line-height:1.75;color:#18212a}
.rule-text strong{font-weight:800}
.tag-list{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.tag-item{
  display:inline-flex;align-items:center;justify-content:center;padding:9px 16px;border-radius:13px;
  border:1.8px solid #27c469;background:#f2fff6;color:#13a255;font-size:13px;font-weight:800
}
.rule-list{margin:10px 0 0 18px;color:#18212a}
.rule-list li{margin-bottom:8px;font-size:15px;line-height:1.65}
.oath-box{
  margin-top:16px;padding:18px 22px;border-radius:22px;border:2px solid #22c55e;
  background:linear-gradient(135deg,#fffef4 0%,#eefcff 100%);font-size:15px;line-height:1.95;
  color:#1b3a24;font-style:italic
}
.score-box{
  margin-top:14px;padding:14px;border-radius:20px;border:2px solid #ffd84f;background:#fff9df;display:grid;gap:10px
}
.score-row{
  display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;border-radius:12px;
  font-size:15px
}
.score-row.gray{background:#eef1f6}
.score-row.green{background:#dcfce7}
.score-row.red{background:#fee2e2}
.score-row.blue{background:#dbeafe}
.score-row strong:last-child{font-size:17px}
.important-box{
  margin-top:16px;background:#1a1a1a;color:white;border:3px solid #ffe600;border-radius:18px;
  padding:20px 22px;box-shadow:0 12px 30px rgba(255,230,0,.15)
}
.important-box h4{font-size:20px;font-weight:900;color:#ffe600;margin-bottom:10px}
.important-box p{font-size:15px;line-height:1.8}
.meta-card,.results-card{
  background:white;border-radius:20px;border:1px solid #e8e6ef;box-shadow:0 12px 30px rgba(57,33,54,.05);padding:22px 24px
}
.meta-card-title,.results-card-title{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:900;margin-bottom:10px}
.meta-card-title{color:#37a10c}
.results-card-title{color:#fff;background:linear-gradient(135deg,#ff1773,#ff6b6b);margin:-22px -24px 16px;padding:16px 22px;border-radius:20px 20px 16px 16px;text-transform:uppercase;letter-spacing:.05em}
.meta-line{font-size:15px;color:#24313f;line-height:1.7}
.start-panel{
  background:white;border-radius:22px;border:1px solid #e6dbe3;box-shadow:0 14px 34px rgba(57,33,54,.08);
  padding:24px;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap
}
.start-copy h3{font-size:21px;font-weight:900;color:#151515;margin-bottom:6px}
.start-copy p{font-size:14px;color:#56606f;line-height:1.7}
.start-btn{
  display:inline-flex;align-items:center;justify-content:center;padding:14px 26px;border-radius:14px;border:none;
  background:linear-gradient(135deg,var(--v),var(--vd));color:white;font-size:15px;font-weight:800;text-decoration:none;
  box-shadow:0 12px 24px rgba(124,31,48,.22);transition:transform .2s ease,box-shadow .2s ease
}
.start-btn:hover{transform:translateY(-2px);box-shadow:0 16px 30px rgba(124,31,48,.28)}
@media(max-width:760px){
  .clas-head h2{font-size:22px}
  .rule-title{font-size:18px}
  .clas-body{padding:22px 18px 24px}
  .clas-head{padding:26px 18px 22px}
  .clas-banner{padding:12px 14px}
  .start-panel{padding:20px 18px}
}
</style>

<div class="sel-page">

  <!-- HERO -->
  <div class="sel-hero">
    <div class="sel-hero-inner">
      <a href="curso.php" class="sel-back">← Volver al curso</a>
      <h1><?=$tipo_info[0]?> <?=$tipo_info[1]?></h1>
      <p><?=$tipo_info[2]?> · <?=etiquetaGrupo($mi_grupo)?></p>
      <div class="sel-hero-badges">
        <span class="shb">🎓 <?=etiquetaGrupo($mi_grupo)?></span>
        <?php if($habilitada): ?>
        <span class="shb" style="background:rgba(39,174,96,.3);border-color:rgba(39,174,96,.4)">✅ Disponible</span>
        <?php else: ?>
        <span class="shb" style="background:rgba(229,57,53,.3);border-color:rgba(229,57,53,.4)">🔒 No disponible</span>
        <?php endif ?>
      </div>
    </div>
  </div>

  <div class="sel-body">

    <?php if(!$habilitada && !isDocente()): ?>
    <div class="blocked-notice">
      <div style="font-size:48px;margin-bottom:12px">🔒</div>
      <h3 style="font-size:17px;font-weight:700;color:var(--vd);margin-bottom:8px">Prueba no habilitada</h3>
      <p style="font-size:13.5px;color:#9a6070;max-width:400px;margin:0 auto 16px">
        Esta prueba aún no está disponible. Tu docente la habilitará cuando llegue el momento.
      </p>
      <a href="curso.php" class="btn btn-v">← Volver al curso</a>
    </div>
    <?php endif ?>

    <?php if($tipo === 'clasificatoria' && !isDocente()): ?>
    <?php $clas_info = $info_niveles[$nivel_estudiante] ?? reset($info_niveles); ?>
    <?php $clas_href = 'simulacro.php?tipo='.$tipo.'&nivel='.$nivel_estudiante; ?>
    <div class="clas-wrap">
      <div class="clas-banner">
        <span class="clas-dot"></span>
        <div class="clas-banner-text">
          <strong>ESTÁS INSCRITO EN EL NIVEL <?=$nivel_banner?></strong>
          <span>| GRADOS <?=$grupo_banner?></span>
        </div>
      </div>

      <div class="clas-card">
        <div class="clas-head">
          <h2>PRUEBA CLASIFICATORIA 2026</h2>
          <p>XVIII OLIMPIADAS DE MATEMÁTICAS UIS - SECUNDARIA</p>
        </div>

        <div class="clas-body">
          <section class="rule-sec">
            <div class="rule-title green">✏️ 1. ELEMENTOS PERMITIDOS</div>
            <div class="rule-text">
              Para la presentación de la Prueba Clasificatoria, <strong>únicamente</strong> está permitido el uso de:
            </div>
            <div class="tag-list">
              <span class="tag-item">Hojas blancas</span>
              <span class="tag-item">Lápiz</span>
              <span class="tag-item">Borrador</span>
              <span class="tag-item">Sacapuntas</span>
            </div>
          </section>

          <section class="rule-sec red">
            <div class="rule-title red">⚠️ 2. CAUSALES DE ANULACIÓN</div>
            <div class="rule-text">La prueba será anulada en los siguientes casos:</div>
            <ul class="rule-list">
              <li>Suplantación de identidad del estudiante.</li>
              <li>Acceso a páginas de internet distintas a la plataforma Moodle.</li>
              <li>Uso de inteligencia artificial, calculadoras, celulares, libros, apuntes o cualquier otro material no autorizado en Elementos permitidos.</li>
            </ul>
            <div class="rule-text">
              En caso de que un estudiante incumpla estas reglas, o las establecidas por el colegio en el marco de la logística para la presentación de la prueba, el docente enlace deberá reportar la falta al correo <strong>olimpiadas.matematicas@uis.edu.co</strong> para proceder con la anulación de la prueba.
            </div>
          </section>

          <section class="rule-sec teal">
            <div class="rule-title teal">🛡️ 3. HONOR OLÍMPICO</div>
            <div class="rule-text">
              El Comité Organizador de las OMU para Secundaria – 2026, presumirá el <strong>honor olímpico de cada uno</strong> de los participantes, así como el de las instituciones que representan, en los términos del juramento que a continuación se presenta:
            </div>
            <div class="oath-box">
              "Al ingresar a la Prueba Clasificatoria, juro abstenerme de compartir o recibir las preguntas o las respuestas del cuestionario, así como recibir ayuda de terceros en su realización, o cualquier otro acto deshonesto que pueda conducir a la trampa, al fraude, perjudique a mis compañeros competidores o lesione el buen nombre de mi colegio y de mi familia, en el marco de las Olimpiadas de Matemáticas UIS."
            </div>
          </section>

          <section class="rule-sec blue">
            <div class="rule-title blue">ℹ️ 4. ESTRUCTURA</div>
            <div class="rule-text">
              Esta prueba consta de <strong>9 preguntas de selección múltiple con única respuesta</strong>. Para contestar una pregunta seleccione la opción que considere correcta. Adicionalmente encontrarás una pregunta sobre el grado de escolaridad que cursas actualmente.
            </div>
          </section>

          <section class="rule-sec orange">
            <div class="rule-title orange">✅ 5. CALIFICACIÓN</div>
            <div class="rule-text">La calificación de este examen es así:</div>
            <div class="score-box">
              <div class="score-row gray"><strong>Base por presentación:</strong><strong>+9 puntos</strong></div>
              <div class="score-row green"><strong>Respuesta correcta:</strong><strong>+5 puntos</strong></div>
              <div class="score-row red"><strong>Respuesta incorrecta:</strong><strong>-1 punto</strong></div>
              <div class="score-row blue"><strong>Opción “No sé”:</strong><strong>0 puntos</strong></div>
            </div>
          </section>

          <section class="rule-sec dark">
            <div class="rule-title dark">⌛ 6. TIEMPO Y CONDICIONES DE ENVÍO</div>
            <div class="rule-text">
              El tiempo límite para contestar esta prueba es de <strong>120 minutos</strong>. Asegúrate de enviar tus respuestas antes de terminar este tiempo.
            </div>
            <div class="important-box">
              <h4>⚠️ ¡IMPORTANTE!</h4>
              <p>
                Para presentar la prueba tendrás un <strong>ÚNICO INTENTO</strong>. Por favor, ponte en contacto con tu profesor de matemáticas, quien te indicará la fecha (entre el 24 y el 27 de marzo) y el horario (de 6 a.m a 5 p.m.) específicos para realizarla. Una vez que se inicie el cuestionario, el tiempo empezará a correr y dispondrás de solo 2 horas para completarlo.
              </p>
            </div>
          </section>
        </div>
      </div>

      <div class="meta-card">
        <div class="meta-card-title">🧾 Prueba Clasificatoria del Nivel <?=$nivel_banner?> (<?=$grupo_banner?>)</div>
        <div class="meta-line"><strong>Abrió:</strong> viernes, 27 de marzo de 2026, 06:00 &nbsp;&nbsp; <strong>Cierra:</strong> viernes, 27 de marzo de 2026, 18:00</div>
      </div>

      <div class="results-card">
        <div class="results-card-title">🏆 Resultados de la Prueba Clasificatoria</div>
        <div class="rule-text">
          A partir del lunes 6 de abril, podrás consultar aquí los resultados oficiales de la Prueba Clasificatoria.
        </div>
        <div class="rule-text">
          Te invitamos a estar atento(a) a esta fecha para conocer tu desempeño y los siguientes pasos del proceso en las olimpiadas.
        </div>
      </div>

      <div class="start-panel">
        <div class="start-copy">
          <h3>Ingreso al cuestionario</h3>
          <p>
            Tu acceso está configurado para el nivel <?=$nivel_banner?> y el grupo <?=$grupo_banner?>.
            Cuando estés listo, podrás iniciar tu único intento desde aquí.
          </p>
        </div>
        <?php if(!empty($clas_info['bloqueado'])): ?>
        <div class="nc-blocked-msg" style="font-size:14px">🔒 Ya agotaste tu intento disponible.</div>
        <?php elseif(empty($clas_info['cnt'])): ?>
        <div class="nc-blocked-msg" style="font-size:14px;color:#9a6070">📭 Esta prueba aún no tiene preguntas cargadas.</div>
        <?php else: ?>
        <a href="<?=$clas_href?>" class="start-btn">Entrar al cuestionario</a>
        <?php endif ?>
      </div>
    </div>

    <?php else: ?>
    <!-- TARJETAS DE NIVEL -->
    <div class="sel-grid">
    <?php foreach($info_niveles as $niv=>$info):
      $cfg   = $info['cfg'];
      $color = $info['color'];
      $tl    = $cfg['tiempo_limite_min']??null;
      $np    = intval($cfg['num_preguntas']??10);
      $mi    = intval($info['max_int']);
      $href  = $info['disponible'] ? 'simulacro.php?tipo='.$tipo.'&nivel='.$niv : '#';
    ?>
    <a href="<?=$href?>" class="nivel-card <?=(!$info['disponible'])?'blocked':''?>">
      <div class="nc-top" style="background:linear-gradient(135deg,<?=$color?>cc,<?=$color?>)">
        <span class="nc-icon"><?=$info['ico']?></span>
        <span class="nc-badge">Nivel <?=$info['lbl']?></span>
      </div>
      <div class="nc-body">
        <div class="nc-title"><?=$info['lbl']?></div>
        <div class="nc-sub">
          <?php if($info['cnt']===0): ?>
            Sin preguntas disponibles aún.
          <?php elseif($info['bloqueado']): ?>
            Has completado el máximo de intentos.
          <?php else: ?>
            <?=$info['cnt']?> pregunta<?=$info['cnt']!=1?'s':''?> disponible<?=$info['cnt']!=1?'s':''?>.
          <?php endif ?>
        </div>
        <div class="nc-stats">
          <?php if($tl): ?>
          <span class="nc-stat">⏱️ <strong><?=$tl?> min</strong></span>
          <?php else: ?>
          <span class="nc-stat">⏱️ Sin límite</span>
          <?php endif ?>
          <span class="nc-stat">❓ <strong><?=$np?> preguntas</strong></span>
          <?php if($mi>0): ?>
          <span class="nc-stat">🔁 <strong><?=$mi?> intento<?=$mi!=1?'s':''?></strong></span>
          <?php endif ?>
        </div>
        <?php if($mi>0): ?>
        <div class="intentos-bar" style="margin-bottom:4px">
          <?php for($d=0;$d<$mi;$d++): ?>
          <div class="int-dot <?=$d<$info['usado']?'used':'avail'?>"></div>
          <?php endfor ?>
          <span style="font-size:11px;color:#9a6070;margin-left:6px"><?=$info['usado']?>/<?=$mi?> usados</span>
        </div>
        <?php endif ?>
      </div>
      <div class="nc-cta">
        <?php if($info['bloqueado']): ?>
          <span class="nc-blocked-msg">🔒 Intentos agotados</span>
        <?php elseif($info['cnt']===0): ?>
          <span class="nc-blocked-msg" style="color:#9a6070">📭 Sin preguntas</span>
        <?php elseif(!$habilitada && !isDocente()): ?>
          <span class="nc-blocked-msg">🔒 No disponible</span>
        <?php else: ?>
          <button class="nc-action" style="background:<?=$color?>" onclick="event.preventDefault();location.href='<?=$href?>'">
            Comenzar →
          </button>
        <?php endif ?>
        <?php if($mi>0 && !$info['bloqueado'] && $info['disponible']): ?>
        <span class="nc-intentos">🔁 <?=max(0,$mi-$info['usado'])?> restante<?=max(0,$mi-$info['usado'])!=1?'s':''?></span>
        <?php endif ?>
      </div>
    </a>
    <?php endforeach ?>
    </div>
    <?php endif ?>

    <!-- HISTORIAL -->
    <?php if(!empty($historial)): ?>
    <div class="hist-section">
      <h2>📋 Tu historial en esta prueba</h2>
      <div class="hist-grid">
        <?php foreach($historial as $h):
          $parts=explode('_',$h['nivel']);
          $niv_lbl=ucfirst($parts[0]??$h['nivel']);
          $pct=$h['total']>0?round($h['puntaje']/$h['total']*100):0;
          $pct_color=$pct>=80?'#27AE60':($pct>=60?'#F57C00':'#E53935');
          $m=floor($h['tiempo_seg']/60); $s=$h['tiempo_seg']%60;
        ?>
        <div class="hist-card">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div class="hist-pct" style="color:<?=$pct_color?>"><?=$pct?>%</div>
            <span class="badge b-v" style="font-size:10.5px"><?=$niv_lbl?></span>
          </div>
          <div class="hist-bar"><div class="hist-bar-fill" style="width:<?=$pct?>%"></div></div>
          <div class="hist-meta"><?=$h['puntaje']?>/<?=$h['total']?> correctas · <?=sprintf('%02d:%02d',$m,$s)?></div>
          <div class="hist-date">📅 <?=date('d/m/Y H:i',strtotime($h['fecha']))?></div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>

  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
