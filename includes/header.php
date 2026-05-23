<?php
$pagina    = basename($_SERVER['PHP_SELF'],'.php');
$no_msg    = isLogged() ? noMsgLeidos($pdo) : 0;
$rol_actual= $_SESSION['rol'] ?? '';
// Ruta base dinámica — funciona sin importar el nombre de la carpeta
$base_url = rtrim(str_replace('\\','/','http://'.$_SERVER['HTTP_HOST'].dirname(str_replace($_SERVER['DOCUMENT_ROOT'],'',$_SERVER['SCRIPT_FILENAME']))),'/');
if(basename(dirname($_SERVER['SCRIPT_FILENAME']))==='includes') $base_url=dirname($base_url);
$_nombre = sanitize($_SESSION['nombre']??'');
$_iniciales = strtoupper(substr($_SESSION['nombre']??'U',0,1).substr($_SESSION['apellido']??'',0,1));
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $page_title ?? 'Biffi Olimpiadas' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Serif+Display:ital@0;1&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{
  --v:#7C1F30;--vd:#4A0F1C;--vl:#A84358;--vp:#F5E8EB;
  --gold:#C8A050;--ink:#1A0A0F;--mist:#FAF5F6;--white:#fff;
  --green:#2E7D52;--border:#E8D0D6;
  --sh:0 4px 24px rgba(74,15,28,.10);--shh:0 12px 40px rgba(124,31,48,.20);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Sora',sans-serif;background:var(--mist);color:var(--ink);min-height:100vh}
a{text-decoration:none;color:inherit}

/* ─── NAVBAR ─────────────────────────────────────── */
.navbar{
  position:sticky;top:0;z-index:200;height:64px;
  background:linear-gradient(90deg,var(--vd) 0%,var(--v) 55%,var(--vl) 100%);
  display:flex;align-items:center;padding:0 16px;
  box-shadow:0 4px 20px rgba(0,0,0,.28);gap:0;
}

/* Logos */
.nav-brand{display:flex;align-items:center;gap:0;flex-shrink:0}
.nav-logo{height:48px;width:48px;border-radius:9px;overflow:hidden;flex-shrink:0;
  background:white;display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 10px rgba(0,0,0,.25);border:2px solid rgba(255,255,255,.3)}
.nav-logo img{width:100%;height:100%;object-fit:contain;padding:3px}
.nav-sep{width:1.5px;height:32px;background:rgba(255,255,255,.22);margin:0 10px;flex-shrink:0}
.nav-brand-text{margin-left:10px;display:flex;flex-direction:column;justify-content:center}
.nav-title{font-family:'DM Serif Display',serif;font-size:15px;color:white;line-height:1.2;white-space:nowrap}
.nav-sub{font-family:'Sora',sans-serif;font-size:9.5px;color:rgba(255,255,255,.55);font-weight:400;letter-spacing:.03em}

/* Links principales */
.nav-links{display:flex;gap:1px;margin-left:18px;list-style:none;flex-shrink:0}
.nav-links > li{position:relative}
.nav-links a.nl{
  display:flex;align-items:center;gap:5px;
  padding:5px 11px;border-radius:7px;
  color:rgba(255,255,255,.82);font-size:12.5px;font-weight:500;
  transition:background .15s;white-space:nowrap;cursor:pointer;
}
.nav-links a.nl:hover,.nav-links a.nl.on{background:rgba(255,255,255,.16);color:white}
.nav-links a.nl .arr{font-size:8px;opacity:.6;margin-left:2px}

/* Dropdown */
.nav-dd{
  display:none;position:absolute;top:100%;left:0;
  background:white;border-radius:12px;min-width:210px;
  box-shadow:0 14px 40px rgba(0,0,0,.18);border:1.5px solid var(--border);
  z-index:300;
  /* invisible bridge: prevents gap between button and menu */
  padding:16px 0 6px;
  margin-top:-8px;
}
.nav-dd::before{
  content:'';position:absolute;top:0;left:0;right:0;height:12px;
  background:transparent;
}
.nav-dd.open{display:block;animation:ddIn .18s ease}
@keyframes ddIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.nav-links li .nl.open{background:rgba(255,255,255,.2);color:white}
.nav-dd a{
  display:flex;align-items:center;gap:10px;
  padding:9px 16px;font-size:13px;color:var(--ink);font-weight:500;
  transition:background .15s;white-space:nowrap;
}
.nav-dd a:hover{background:#fdf5f7;color:var(--vd)}
.nav-dd a.on{background:var(--vp);color:var(--vd);font-weight:700}
.nav-dd-sep{height:1px;background:var(--border);margin:4px 0}
.nav-dd a .ico{width:20px;text-align:center;flex-shrink:0}

/* Spacer */
.ns{flex:1}

/* Derecha */
.nav-right{display:flex;align-items:center;gap:6px;flex-shrink:0}
.ni{
  position:relative;width:34px;height:34px;border-radius:8px;
  background:rgba(255,255,255,.12);border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  color:white;font-size:15px;transition:background .2s;text-decoration:none;
}
.ni:hover{background:rgba(255,255,255,.22)}
.nbadge{
  position:absolute;top:2px;right:2px;background:#e53935;color:white;
  font-size:9px;font-weight:700;min-width:14px;height:14px;border-radius:7px;
  display:flex;align-items:center;justify-content:center;padding:0 3px;
}
.nav-user{
  display:flex;align-items:center;gap:8px;
  padding:4px 10px 4px 6px;border-radius:8px;cursor:pointer;
  background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);
  transition:background .2s;text-decoration:none;
}
.nav-user:hover{background:rgba(255,255,255,.2)}
.nav-av{
  width:30px;height:30px;border-radius:6px;
  background:rgba(255,255,255,.25);
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:800;color:white;flex-shrink:0;
}
.nav-user-info{display:flex;flex-direction:column}
.nav-user-name{font-size:12px;font-weight:700;color:white;line-height:1.2;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nav-user-rol{font-size:9.5px;color:rgba(255,255,255,.55);font-weight:500}

/* TOAST */
.toast{position:fixed;bottom:22px;right:22px;background:var(--vd);color:white;
  padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;
  box-shadow:0 8px 32px rgba(0,0,0,.25);transform:translateY(80px);opacity:0;
  transition:all .4s cubic-bezier(.34,1.56,.64,1);z-index:9999;display:flex;align-items:center;gap:8px}
.toast.show{transform:translateY(0);opacity:1}

/* UTILS GLOBALES */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:9px;
  font-family:'Sora',sans-serif;font-size:13px;font-weight:700;cursor:pointer;border:none;transition:all .2s}
.btn-v{background:var(--v);color:white;box-shadow:0 4px 14px rgba(124,31,48,.3)}
.btn-v:hover{background:var(--vd);transform:translateY(-2px)}
.btn-outline{background:transparent;color:var(--v);border:2px solid var(--v)}
.btn-outline:hover{background:var(--vp)}
.btn-green{background:var(--green);color:white}.btn-green:hover{background:#1b5e38}
.btn-red{background:#e53935;color:white}.btn-red:hover{background:#b71c1c}
.btn-sm{padding:6px 14px;font-size:12px}
.badge{display:inline-flex;align-items:center;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px}
.b-v{background:var(--vp);color:var(--vd)}.b-g{background:#e8f5e9;color:#2e7d32}
.b-gold{background:#fff3cd;color:#7a5200}.b-gray{background:#f0f0f0;color:#777}
.b-red{background:#fde8e8;color:#c0392b}.b-blue{background:#e3f2fd;color:#1565c0}
.b-green{background:#e8f5e9;color:#2e7d32}
.card{background:white;border-radius:16px;padding:22px;box-shadow:var(--sh);border:1.5px solid var(--border)}
.card h3{font-size:16px;font-weight:700;color:var(--ink);margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--vp)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.fg{display:flex;flex-direction:column;gap:5px}
.fl{font-size:11px;font-weight:700;color:var(--vd);text-transform:uppercase;letter-spacing:.07em}
.fi,.fsel{padding:10px 13px;border:1.5px solid var(--border);border-radius:9px;
  font-family:'Sora',sans-serif;font-size:13px;color:var(--ink);background:white;outline:none;transition:border-color .2s;width:100%}
.fi:focus,.fsel:focus{border-color:var(--v)}
textarea.fi{resize:vertical;min-height:80px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:var(--vd);color:white;padding:10px 13px;text-align:left;font-size:11.5px;letter-spacing:.04em;white-space:nowrap}
td{padding:10px 13px;border-bottom:1px solid var(--vp);color:var(--ink);vertical-align:middle}
tr:nth-child(even) td{background:#fdf8f9}
tr:hover td{background:#faeef1}
.page-wrap{max-width:1100px;margin:0 auto;padding:32px 24px}
</style>
</head>
<body>
<div class="toast" id="toast">✅ <span id="tmsg"></span></div>
<nav class="navbar">
  <!-- MARCA -->
  <div class="nav-brand">
    <div class="nav-logo">
      <img src="<?=$base_url?>/assets/logo_biffi.png" alt="Biffi" onerror="this.style.display='none'">
    </div>
    <div class="nav-sep"></div>
    <div class="nav-logo">
      <img src="<?=$base_url?>/assets/logo_apostol_math.png" alt="Math" onerror="this.style.display='none'">
    </div>
    <div class="nav-brand-text">
      <span class="nav-title">Biffi Olimpiadas</span>
      <span class="nav-sub">XVIII Olimpiadas de Matemáticas 2026</span>
    </div>
  </div>

  <!-- LINKS -->
  <ul class="nav-links">
    <!-- Inicio -->
    <li>
      <a href="dashboard.php" class="nl <?=$pagina==='dashboard'?'on':''?>">🏠 Inicio</a>
    </li>

    <!-- Estudiante: Mi Olimpiada dropdown -->
    <li>
      <a class="nl <?=in_array($pagina,['curso','pruebas','simulacro','biblioteca','forms'])?'on':''?>">
        🏆 Mi Olimpiada <span class="arr">▼</span>
      </a>
      <div class="nav-dd">
        <a href="curso.php" class="<?=$pagina==='curso'?'on':''?>"><span class="ico">📚</span> Mi curso</a>
        <a href="pruebas.php?tipo=simulacro" class="<?=($pagina==='pruebas'&&($_GET['tipo']??'')==='simulacro')?'on':''?>"><span class="ico">📝</span> Simulacros</a>
        <a href="pruebas.php?tipo=clasificatoria" class="<?=($pagina==='pruebas'&&($_GET['tipo']??'')==='clasificatoria')?'on':''?>"><span class="ico">🎯</span> Clasificatoria</a>
        <a href="forms.php" class="<?=$pagina==='forms'?'on':''?>"><span class="ico">📋</span> Formularios</a>
        <div class="nav-dd-sep"></div>
        <a href="biblioteca.php" class="<?=$pagina==='biblioteca'?'on':''?>"><span class="ico">📄</span> Biblioteca</a>
      </div>
    </li>

    <!-- Mensajes -->
    <li>
      <a href="mensajes.php" class="nl <?=$pagina==='mensajes'?'on':''?>">
        ✉️ Mensajes<?php if($no_msg>0): ?> <span style="background:#e53935;color:white;border-radius:10px;font-size:10px;font-weight:700;padding:1px 6px"><?=$no_msg?></span><?php endif ?>
      </a>
    </li>

    <?php if(isDocente()): ?>
    <!-- Docente dropdown -->
    <li>
      <a class="nl <?=in_array($pagina,['docente','editor_simulacro','forms_resultados'])?'on':''?>">
        👩‍🏫 Docente <span class="arr">▼</span>
      </a>
      <div class="nav-dd">
        <a href="docente.php" class="<?=$pagina==='docente'?'on':''?>"><span class="ico">📊</span> Panel docente</a>
        <?php if(puedeEditarPruebas()): ?>
        <a href="editor_simulacro.php" class="<?=$pagina==='editor_simulacro'?'on':''?>"><span class="ico">✏️</span> Editor de pruebas</a>
        <a href="forms_resultados.php" class="<?=$pagina==='forms_resultados'?'on':''?>"><span class="ico">📋</span> Google Forms</a>
        <?php endif ?>
        <div class="nav-dd-sep"></div>
        <a href="biblioteca.php" class="<?=$pagina==='biblioteca'?'on':''?>"><span class="ico">📚</span> Biblioteca</a>
      </div>
    </li>
    <?php endif ?>

    <?php if(isAdmin()): ?>
    <!-- Admin dropdown -->
    <li>
      <a class="nl <?=$pagina==='admin'?'on':''?>">
        ⚙️ Admin <span class="arr">▼</span>
      </a>
      <div class="nav-dd">
        <a href="admin.php" class="<?=$pagina==='admin'?'on':''?>"><span class="ico">🏠</span> Panel admin</a>
        <a href="admin.php" onclick="setTimeout(()=>sp('instituciones',null),100)"><span class="ico">🏫</span> Instituciones</a>
        <a href="admin.php" onclick="setTimeout(()=>sp('usuarios',null),100)"><span class="ico">👥</span> Usuarios</a>
        <div class="nav-dd-sep"></div>
        <a href="admin.php" onclick="setTimeout(()=>sp('resultados',null),100)"><span class="ico">📊</span> Resultados</a>
      </div>
    </li>
    <?php endif ?>
  </ul>

  <div class="ns"></div>

  <!-- DERECHA -->
  <div class="nav-right">
    <a href="logout.php" class="nav-user" title="Cerrar sesión">
      <div class="nav-av"><?=$_iniciales?></div>
      <div class="nav-user-info">
        <span class="nav-user-name"><?=$_nombre?></span>
        <span class="nav-user-rol"><?=ucfirst($rol_actual)?></span>
      </div>
    </a>
  </div>
</nav>
<script>
function st(msg){const t=document.getElementById('toast');document.getElementById('tmsg').textContent=msg;
  t.classList.add('show');clearTimeout(window._tt);window._tt=setTimeout(()=>t.classList.remove('show'),3200)}

// ── DROPDOWNS ─────────────────────────────────────────────────
(function(){
  const DELAY = 500; // ms before closing — generous to avoid accidental close
  let closeTimers = new WeakMap();

  document.querySelectorAll('.nav-links > li').forEach(li => {
    const btn = li.querySelector('.nl');
    const dd  = li.querySelector('.nav-dd');
    if(!btn || !dd) return;

    function openDD(){
      // Close all others first
      document.querySelectorAll('.nav-links > li').forEach(other => {
        if(other !== li){
          const ob = other.querySelector('.nl');
          const od = other.querySelector('.nav-dd');
          if(ob && od){ ob.classList.remove('open'); od.classList.remove('open'); }
        }
      });
      clearTimeout(closeTimers.get(li));
      dd.classList.add('open');
      btn.classList.add('open');
    }
    function scheduleClose(){
      closeTimers.set(li, setTimeout(()=>{
        dd.classList.remove('open');
        btn.classList.remove('open');
      }, DELAY));
    }
    function cancelClose(){ clearTimeout(closeTimers.get(li)); }

    // Open on hover (mouseenter on li = covers button + dropdown)
    li.addEventListener('mouseenter', openDD);
    li.addEventListener('mouseleave', scheduleClose);

    // Also toggle on click (for touch devices)
    btn.addEventListener('click', e => {
      e.stopPropagation();
      if(dd.classList.contains('open')){ scheduleClose(); }
      else { openDD(); }
    });

    // Keep open when mouse enters dropdown
    dd.addEventListener('mouseenter', cancelClose);
    dd.addEventListener('mouseleave', scheduleClose);
  });

  // Click outside = close all
  document.addEventListener('click', () => {
    document.querySelectorAll('.nav-dd.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.nl.open').forEach(b => b.classList.remove('open'));
  });
})();
</script>
<style>
/* ─── RESPONSIVE GLOBAL FIXES ────────────────────────────── */
/* Prevent any flex row from overflowing its container */
.panel-top, .bib-head, .doc-wrap > div:first-child,
.adm-panel .panel-top {
  flex-wrap: wrap !important;
}
/* Buttons never overflow */
.btn { flex-shrink: 0; white-space: nowrap; }
/* Tables always scrollable */
.table-wrap, .resp-table-wrap { overflow-x: auto !important; }
/* Modals always fit screen */
.modal { max-width: min(580px, 95vw) !important; }
/* Forms grid collapsing on small screens */
@media (max-width: 680px) {
  .form-row, .form-grid, .efrow2, .efrow3 { grid-template-columns: 1fr !important; }
  .stats-row, .stat-c, .inst-ranking, .inst-grid { grid-template-columns: 1fr 1fr !important; }
  .panel-top { flex-direction: column; }
  .panel-top .btn { width: 100%; justify-content: center; }
  .nav-brand-text { display: none; }
  .filtros { flex-direction: column; }
  .filtros select, .filtros input { width: 100%; }
}
@media (max-width: 480px) {
  .stats-row, .inst-ranking, .inst-grid { grid-template-columns: 1fr !important; }
}
</style>
