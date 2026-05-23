<?php
require_once 'includes/config.php';
requireLogin();

$uid = $_SESSION['user_id'];

// Resultados del usuario
$stmt = $pdo->prepare("SELECT nivel,puntaje,total,fecha FROM resultados WHERE usuario_id=? ORDER BY fecha DESC");
$stmt->execute([$uid]); $mis_res = $stmt->fetchAll();
$completados = count($mis_res);
$mejor_pct = 0;
foreach($mis_res as $r) if($r['total']>0) $mejor_pct=max($mejor_pct,round($r['puntaje']/$r['total']*100));

// Institución del usuario
$inst = null;
if($_SESSION['institucion_id']??null){
    $si = $pdo->prepare("SELECT * FROM instituciones WHERE id=?");
    $si->execute([$_SESSION['institucion_id']]); $inst=$si->fetch();
}

// Mensajes no leídos
$no_msg = noMsgLeidos($pdo);

// Mi grupo
$mi_grupo = miGrupo();

// Últimas noticias (recursos recientes)
$noticias = $pdo->query("SELECT titulo,tipo,creado_en FROM recursos WHERE visible=1 ORDER BY creado_en DESC LIMIT 3")->fetchAll();

$page_title = 'Inicio — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
.db-wrap { max-width:1160px; margin:0 auto; padding:30px 24px 42px; }

/* Hero bienvenida */
.db-hero {
  background:
    radial-gradient(circle at top right, rgba(255,255,255,.16), transparent 26%),
    radial-gradient(circle at bottom left, rgba(200,160,80,.16), transparent 28%),
    linear-gradient(135deg,#53101f 0%, #7C1F30 52%, #b04b62 100%);
  border-radius:28px; padding:34px 34px 30px;
  display:grid; grid-template-columns:minmax(0,1.4fr) minmax(220px,.6fr);
  gap:24px; margin-bottom:28px;
  box-shadow:0 24px 60px rgba(86,24,40,.18); position:relative; overflow:hidden;
  border:1px solid rgba(255,255,255,.12);
}
.db-hero::before {
  content:''; position:absolute; inset:0; pointer-events:none; opacity:.9;
  background-image:
    linear-gradient(120deg, rgba(255,255,255,.05) 0, transparent 40%),
    repeating-linear-gradient(60deg,rgba(255,255,255,.05) 0,rgba(255,255,255,.05) 1px,transparent 1px,transparent 42px),
    repeating-linear-gradient(-60deg,rgba(255,255,255,.05) 0,rgba(255,255,255,.05) 1px,transparent 1px,transparent 42px);
}
.db-hero::after{
  content:''; position:absolute; width:240px; height:240px; right:-60px; top:-50px;
  background:radial-gradient(circle, rgba(255,255,255,.14), transparent 68%);
  pointer-events:none;
}
.db-hero-text { position:relative; z-index:1; }
.db-kicker{
  display:inline-flex; align-items:center; gap:8px; margin-bottom:14px;
  padding:8px 14px; border-radius:999px; background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.18); color:#fff; font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase;
}
.db-hero-text h1 { font-family:'DM Serif Display',serif; font-size:40px; color:white; margin-bottom:10px; line-height:1.05; }
.db-hero-text p  { font-size:15px; color:rgba(255,255,255,.78); max-width:620px; line-height:1.7; }
.db-hero-meta{display:flex; gap:10px; flex-wrap:wrap; margin-top:18px}
.db-chip{
  display:inline-flex; align-items:center; gap:8px; padding:9px 14px; border-radius:999px;
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); color:white; font-size:12px; font-weight:700;
}
.db-hero-inst {
  position:relative; z-index:1; align-self:stretch;
  background:linear-gradient(180deg,rgba(255,255,255,.16),rgba(255,255,255,.08));
  border:1.5px solid rgba(255,255,255,.22); border-radius:22px;
  padding:22px 22px 18px; text-align:center; min-width:190px;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.08);
}
.db-hero-inst .inst-logo{
  width:58px;height:58px;border-radius:16px;margin:0 auto 14px;
  display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:white;
  box-shadow:0 10px 24px rgba(0,0,0,.18)
}
.db-hero-inst .inst-name { font-size:21px; font-weight:800; color:white; margin-bottom:6px; }
.db-hero-inst .inst-sub  { font-size:13px; color:rgba(255,255,255,.74); }
.db-hero-inst .inst-mini{
  margin-top:16px; padding-top:14px; border-top:1px solid rgba(255,255,255,.16);
  font-size:11px; color:rgba(255,255,255,.66); letter-spacing:.06em; text-transform:uppercase;
}

/* Stats rápidas */
.quick-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:30px; }
.qs-card {
  background:linear-gradient(180deg,#fff,#fffafb);
  border-radius:20px; padding:20px 18px; text-align:left;
  box-shadow:0 14px 34px rgba(80,38,55,.07); border:1.5px solid #ead8df; transition:all .24s ease;
  position:relative; overflow:hidden;
}
.qs-card::before{
  content:''; position:absolute; inset:auto -20px -35px auto; width:92px; height:92px; border-radius:50%;
  background:radial-gradient(circle, rgba(124,31,48,.08), transparent 70%);
}
.qs-card:hover { box-shadow:0 18px 42px rgba(80,38,55,.12); transform:translateY(-3px); border-color:#d9bcc6; }
.qs-top{display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px}
.qs-card .ico {
  width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;
  font-size:22px;background:#f9eef1; box-shadow:inset 0 0 0 1px rgba(124,31,48,.08)
}
.qs-trend{font-size:11px; font-weight:800; color:#a46e7b; text-transform:uppercase; letter-spacing:.08em}
.qs-card .val { font-family:'DM Serif Display',serif; font-size:36px; color:var(--vd); line-height:1; margin-bottom:6px; }
.qs-card .lbl { font-size:12px; color:#8e6170; font-weight:700; }

/* Grid de accesos */
.db-grid { display:grid; grid-template-columns:minmax(0,1fr) 330px; gap:24px; align-items:start; }
@media(max-width:980px){ .db-grid{grid-template-columns:1fr;} .quick-stats{grid-template-columns:repeat(2,minmax(0,1fr));} .db-hero{grid-template-columns:1fr;} }
@media(max-width:620px){ .quick-stats{grid-template-columns:1fr;} .db-wrap{padding:22px 16px 36px;} .db-hero{padding:26px 20px 22px;} .db-hero-text h1{font-size:32px;} }

/* Sección */
.sec-title { font-size:11.5px; font-weight:700; text-transform:uppercase;
  letter-spacing:.08em; color:#9a6070;
  display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.sec-title::after { content:''; flex:1; height:1px; background:var(--border); }

/* Curso card */
.curso-card {
  background:white; border-radius:22px; overflow:hidden;
  box-shadow:0 16px 38px rgba(80,38,55,.08); border:1.5px solid #ead8df;
  transition:all .3s cubic-bezier(.34,1.56,.64,1); cursor:pointer; text-decoration:none; display:block;
}
.curso-card:hover { transform:translateY(-5px) scale(1.01); box-shadow:0 22px 48px rgba(80,38,55,.14); border-color:var(--vl); }
.cc-banner {
  height:138px; background:linear-gradient(135deg,#571021,#7C1F30,#bc5c73);
  position:relative; overflow:hidden;
}
.cc-banner::before {
  content:''; position:absolute; inset:0;
  background-image:
    linear-gradient(120deg, rgba(255,255,255,.08), transparent 44%),
    repeating-linear-gradient(45deg,rgba(255,255,255,.06) 0,rgba(255,255,255,.06) 1px,transparent 1px,transparent 24px);
}
.cc-banner .emoji { position:absolute; bottom:12px; right:18px; font-size:44px; opacity:.26; }
.cc-tag { position:absolute; top:10px; left:10px; background:rgba(0,0,0,.3);
  backdrop-filter:blur(6px); color:white; font-size:10px; font-weight:700;
  padding:3px 10px; border-radius:18px; border:1px solid rgba(255,255,255,.2); }
.cc-body { padding:20px 18px 18px; }
.cc-title { font-size:20px; font-weight:800; color:var(--vd); margin-bottom:8px; line-height:1.3; }
.cc-desc { font-size:13px; color:#8e6170; line-height:1.7; margin-bottom:12px; }
.cc-tags  { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; }
.cc-prog  { display:flex; align-items:center; gap:8px; }
.cc-prog-bar { flex:1; height:6px; background:var(--vp); border-radius:3px; overflow:hidden; }
.cc-prog-fill { height:100%; background:linear-gradient(90deg,var(--v),var(--vl)); border-radius:3px; transition:width 1.2s; }
.cc-prog-pct { font-size:12px; font-weight:700; color:var(--v); white-space:nowrap; }

/* Accesos rápidos */
.quick-access { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.qa-item { display:flex; align-items:flex-start; gap:14px; padding:18px 18px;
  background:white; border-radius:18px; box-shadow:0 12px 28px rgba(80,38,55,.06);
  border:1.5px solid #ead8df; cursor:pointer; text-decoration:none;
  color:inherit; transition:all .2s; position:relative; min-height:94px; }
.qa-item:hover { background:#fff8fa; border-color:var(--vl); transform:translateY(-3px); }
.qa-ico { width:40px; height:40px; border-radius:11px;
  display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.qa-text .ttl { font-size:14px; font-weight:700; color:var(--ink); }
.qa-text .sub { font-size:12px; color:#9a6070; margin-top:4px; line-height:1.55; }
.qa-badge { position:absolute; right:14px; }
@media(max-width:720px){ .quick-access{grid-template-columns:1fr;} }

/* Columna lateral */
.side-card { background:white; border-radius:20px; padding:18px 18px 14px;
  box-shadow:0 14px 32px rgba(80,38,55,.06); border:1.5px solid #ead8df; margin-bottom:16px; }
.side-card h3 { font-size:14px; font-weight:700; color:var(--ink); margin-bottom:14px;
  padding-bottom:10px; border-bottom:1px solid var(--vp); display:flex; align-items:center; gap:6px; }

/* Últimos resultados */
.res-item { display:flex; align-items:center; gap:10px;
  padding:9px 0; border-bottom:1px solid var(--vp); }
.res-item:last-child { border-bottom:none; }
.res-pct { font-family:'DM Serif Display',serif; font-size:22px; min-width:52px; text-align:right; }
.res-info .tipo { font-size:12px; font-weight:700; color:var(--ink); }
.res-info .fecha { font-size:11px; color:#9a6070; margin-top:1px; }

/* Progreso grupal */
.grp-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 14px;
  border-radius:20px; font-size:12px; font-weight:700; color:white; margin-bottom:12px; }
</style>

<div class="db-wrap">

  <!-- HERO ──────────────────────────────────────────────────── -->
  <div class="db-hero">
    <div class="db-hero-text">
      <span class="db-kicker">Panel principal - Biffi Olimpiadas</span>
      <h1>Hola, <?=sanitize($_SESSION['nombre'])?> 👋</h1>
      <p>
        <?php if($mi_grupo): ?>
        <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);
          border:1px solid rgba(255,255,255,.25);border-radius:20px;padding:3px 12px;font-size:12px;font-weight:700;color:white">
          🎓 <?=etiquetaGrupo($mi_grupo)?>
        </span>
        <?php endif ?>
        &nbsp;·&nbsp; XVIII Olimpiadas de Matemáticas Biffi 2026
      </p>
      <div class="db-hero-meta">
        <?php if($mi_grupo): ?>
        <span class="db-chip">Grupo: <?=etiquetaGrupo($mi_grupo)?></span>
        <?php endif ?>
        <span class="db-chip">Competencia oficial 2026</span>
      </div>
    </div>
    <?php if($inst): ?>
    <div class="db-hero-inst">
      <div class="inst-logo" style="width:36px;height:36px;border-radius:9px;margin:0 auto 8px;
        background:<?=sanitize($inst['color'])?>;display:flex;align-items:center;
        justify-content:center;font-size:16px;font-weight:800;color:white">
        <?=mb_substr($inst['nombre'],0,1)?>
      </div>
      <div class="inst-name"><?=sanitize($inst['nombre'])?></div>
      <div class="inst-sub">📍 <?=sanitize($inst['ciudad'])?></div>
    </div>
    <?php endif ?>
  </div>

  <!-- STATS RÁPIDAS ────────────────────────────────────────── -->
  <div class="quick-stats">
    <div class="qs-card"><div class="ico">📊</div><div class="val"><?=$completados?></div><div class="lbl">Pruebas realizadas</div></div>
    <div class="qs-card"><div class="ico">🏆</div><div class="val"><?=$mejor_pct>0?$mejor_pct.'%':'—'?></div><div class="lbl">Mejor resultado</div></div>
    <div class="qs-card"><div class="ico">✉️</div><div class="val"><?=$no_msg?></div><div class="lbl">Mensajes nuevos</div></div>
    <div class="qs-card"><div class="ico">📚</div>
      <div class="val"><?=(int)$pdo->query("SELECT COUNT(*) FROM recursos WHERE visible=1")->fetchColumn()?></div>
      <div class="lbl">Recursos</div>
    </div>
  </div>

  <div class="db-grid">

    <!-- ── COLUMNA PRINCIPAL ─────────────────────────────── -->
    <div>
      <div class="sec-title">Mis cursos</div>

      <!-- Card del curso -->
      <a href="curso.php" class="curso-card" style="margin-bottom:20px">
        <div class="cc-banner">
          <span class="cc-tag">⚡ Activo</span>
          <span class="emoji">🏆</span>
        </div>
        <div class="cc-body">
          <div class="cc-title">XVIII Olimpiadas de Matemáticas Biffi — Secundaria 2026</div>
          <div class="cc-tags">
            <span class="badge b-v">Matemáticas</span>
            <span class="badge b-gold">2026</span>
            <?php if($mi_grupo): ?>
            <span class="badge" style="background:<?=colorGrupo($mi_grupo)?>;color:white;font-size:10px"><?=etiquetaGrupo($mi_grupo)?></span>
            <?php endif ?>
          </div>
          <div class="cc-prog">
            <div class="cc-prog-bar"><div class="cc-prog-fill" style="width:<?=min($mejor_pct,100)?>%"></div></div>
            <span class="cc-prog-pct"><?=$mejor_pct?>%</span>
          </div>
        </div>
      </a>

      <!-- Accesos rápidos -->
      <div class="sec-title">Accesos rápidos</div>
      <div class="quick-access">
        <a href="curso.php" class="qa-item">
          <div class="qa-ico" style="background:#faeef1">📝</div>
          <div class="qa-text"><div class="ttl">Simulacros de práctica</div><div class="sub">Practica por nivel — Básico, Medio y Avanzado</div></div>
          <span class="qa-badge"><span class="badge b-g">Disponible</span></span>
        </a>
        <a href="mensajes.php" class="qa-item">
          <div class="qa-ico" style="background:#e3f2fd">✉️</div>
          <div class="qa-text"><div class="ttl">Mensajes</div><div class="sub">Comunícate con tus docentes</div></div>
          <?php if($no_msg>0): ?>
          <span class="qa-badge"><span class="badge b-v"><?=$no_msg?> nuevo(s)</span></span>
          <?php endif ?>
        </a>
        <a href="biblioteca.php" class="qa-item">
          <div class="qa-ico" style="background:#fde8e8">📄</div>
          <div class="qa-text"><div class="ttl">Biblioteca</div><div class="sub">PDFs y material de apoyo</div></div>
        </a>
        <a href="forms.php" class="qa-item">
          <div class="qa-ico" style="background:#e8f5e9">📋</div>
          <div class="qa-text"><div class="ttl">Formularios de evaluación</div><div class="sub">Exámenes en Google Forms</div></div>
        </a>
        <?php if(isDocente()): ?>
        <a href="docente.php" class="qa-item">
          <div class="qa-ico" style="background:#fff3cd">📊</div>
          <div class="qa-text"><div class="ttl">Panel Docente</div><div class="sub">Rankings y resultados por institución</div></div>
        </a>
        <?php endif ?>
        <?php if(isAdmin()): ?>
        <a href="admin.php" class="qa-item">
          <div class="qa-ico" style="background:var(--vp)">⚙️</div>
          <div class="qa-text"><div class="ttl">Administración</div><div class="sub">Gestión completa de la plataforma</div></div>
        </a>
        <?php endif ?>
      </div>
    </div>

    <!-- ── COLUMNA LATERAL ───────────────────────────────── -->
    <div>

      <!-- Mis últimos resultados -->
      <div class="side-card">
        <h3>📊 Mis últimos resultados</h3>
        <?php if(empty($mis_res)): ?>
        <p style="font-size:13px;color:#9a6070;text-align:center;padding:16px 0">
          Aún no has completado ninguna prueba.<br>¡Comienza con los simulacros!
        </p>
        <a href="curso.php" class="btn btn-v btn-sm" style="width:100%;justify-content:center;margin-top:4px">Ir a simulacros →</a>
        <?php else: foreach(array_slice($mis_res,0,5) as $r):
          $pct=$r['total']>0?round($r['puntaje']/$r['total']*100):0;
          $c=$pct>=80?'#2e7d32':($pct>=60?'#f57c00':'#c0392b');
          $parts=explode('_',$r['nivel']);
          $tipo=count($parts)>=3?ucfirst($parts[2]):'Simulacro';
        ?>
        <div class="res-item">
          <div class="res-pct" style="color:<?=$c?>"><?=$pct?>%</div>
          <div class="res-info">
            <div class="tipo"><?=$tipo?> — <?=ucfirst($parts[0]??'')?>
              <span style="font-size:10px;font-weight:400;color:#9a6070">(<?=$r['puntaje']?>/<?=$r['total']?>)</span>
            </div>
            <div class="fecha"><?=date('d/m/Y H:i',strtotime($r['fecha']))?></div>
          </div>
        </div>
        <?php endforeach ?>
        <a href="curso.php" style="display:block;text-align:center;margin-top:10px;font-size:12.5px;font-weight:700;color:var(--v);text-decoration:none">Ver calificaciones completas →</a>
        <?php endif ?>
      </div>

      <!-- Novedades / recursos recientes -->
      <?php if(!empty($noticias)): ?>
      <div class="side-card">
        <h3>📢 Novedades en Biblioteca</h3>
        <?php foreach($noticias as $n):
          $ico=['pdf'=>'📄','video'=>'🎥','zip'=>'📦','enlace'=>'🔗','imagen'=>'🖼️'][$n['tipo']]??'📄';
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--vp)">
          <span style="font-size:18px"><?=$ico?></span>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--ink)"><?=sanitize($n['titulo'])?></div>
            <div style="font-size:11px;color:#9a6070"><?=date('d/m/Y',strtotime($n['creado_en']))?></div>
          </div>
        </div>
        <?php endforeach ?>
        <a href="biblioteca.php" style="display:block;text-align:center;margin-top:10px;font-size:12.5px;font-weight:700;color:var(--v);text-decoration:none">Ir a la biblioteca →</a>
      </div>
      <?php endif ?>

    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
