<?php
require_once 'includes/config.php';
requireAdmin();
$ok = isset($_GET['ok']) ? sanitize(urldecode($_GET['ok'])) : '';
$err='';

// ── INSTITUCIONES ───────────────────────────────────────────────
if(isset($_POST['guardar_inst'])){
    $id=intval($_POST['inst_id']??0);
    $nom=trim($_POST['inst_nombre']??'');
    $ciu=trim($_POST['inst_ciudad']??'Cartagena');
    $col=trim($_POST['inst_color']??'#7C1F30');
    $act=intval($_POST['inst_activa']??1);
    if(!$nom){ $err='El nombre de la institución es obligatorio.'; }
    else {
        if($id>0) $pdo->prepare("UPDATE instituciones SET nombre=?,ciudad=?,color=?,activa=? WHERE id=?")->execute([$nom,$ciu,$col,$act,$id]);
        else $pdo->prepare("INSERT INTO instituciones(nombre,ciudad,color,activa) VALUES(?,?,?,?)")->execute([$nom,$ciu,$col,$act]);
        $ok=$id>0?'Institución actualizada ✅':'Institución creada ✅';
    }
}
if(isset($_POST['eliminar_inst'])){
    $id=intval($_POST['inst_id']);
    $c=$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE institucion_id=?"); $c->execute([$id]);
    if((int)$c->fetchColumn()>0) $err='No se puede eliminar: tiene usuarios asignados.';
    else { $pdo->prepare("DELETE FROM instituciones WHERE id=?")->execute([$id]); $ok='Institución eliminada ✅'; }
}

// ── USUARIOS ────────────────────────────────────────────────────
if(isset($_POST['crear_usuario'])){
    $n=trim($_POST['nombre']??''); $ap=trim($_POST['apellido']??'');
    $u=trim($_POST['usuario']??''); $co=trim($_POST['correo']??'');
    $pw=trim($_POST['contrasena']??''); $rol=$_POST['rol']??'estudiante';
    $niv=$_POST['nivel']??'basico'; $cur=trim($_POST['curso']??'');
    $gr=$_POST['grado']!==''?intval($_POST['grado']):null;
    $inst_id=$_POST['institucion_id']!==''?intval($_POST['institucion_id']):null;
    if(!$n||!$u||!$co||!$pw){ $err='Completa los campos obligatorios.'; }
    else {
        $chk=$pdo->prepare("SELECT id FROM usuarios WHERE usuario=? OR correo=?");
        $chk->execute([$u,$co]);
        if($chk->fetch()) $err='Usuario o correo ya existe.';
        else {
            $hash=password_hash($pw,PASSWORD_DEFAULT);
            $inst_nom=''; if($inst_id){ $r=$pdo->prepare("SELECT nombre FROM instituciones WHERE id=?"); $r->execute([$inst_id]); $inst_nom=$r->fetchColumn()??''; }
            $pdo->prepare("INSERT INTO usuarios(nombre,apellido,usuario,correo,contrasena,rol,nivel,curso,grado,institucion,institucion_id) VALUES(?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$n,$ap,$u,$co,$hash,$rol,$niv,$cur,$gr,$inst_nom,$inst_id]);
            $ok='Usuario @'.$u.' creado ✅';
        }
    }
}
if(isset($_POST['editar_usuario'])){
    $id=intval($_POST['uid']);
    $inst_id=$_POST['institucion_id']!==''?intval($_POST['institucion_id']):null;
    $inst_nom=''; if($inst_id){ $r=$pdo->prepare("SELECT nombre FROM instituciones WHERE id=?"); $r->execute([$inst_id]); $inst_nom=$r->fetchColumn()??''; }
    $gr=$_POST['grado']!==''?intval($_POST['grado']):null;
    $pdo->prepare("UPDATE usuarios SET nombre=?,apellido=?,correo=?,rol=?,nivel=?,curso=?,grado=?,institucion=?,institucion_id=?,activo=? WHERE id=?")
        ->execute([trim($_POST['nombre']),trim($_POST['apellido']),trim($_POST['correo']),$_POST['rol'],$_POST['nivel'],trim($_POST['curso']),$gr,$inst_nom,$inst_id,intval($_POST['activo']),$id]);
    if(trim($_POST['nueva_clave']??''))
        $pdo->prepare("UPDATE usuarios SET contrasena=? WHERE id=?")->execute([password_hash($_POST['nueva_clave'],PASSWORD_DEFAULT),$id]);
    $ok='Usuario actualizado ✅';
}
if(isset($_POST['eliminar_usuario'])){
    $id=intval($_POST['uid']);
    if($id===$_SESSION['user_id']) $err='No puedes eliminarte a ti mismo.';
    else { $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$id]); $ok='Usuario eliminado ✅'; }
}

// ── SECCIONES ───────────────────────────────────────────────────
if(isset($_POST['limpiar_resultados_usuario'])){
    $uid=intval($_POST['uid']??0);
    if($uid<=0){
        $err='Usuario invÃ¡lido para reiniciar resultados.';
    } else {
        $s=$pdo->prepare("SELECT nombre,apellido FROM usuarios WHERE id=? AND rol='estudiante' LIMIT 1");
        $s->execute([$uid]);
        $alumno=$s->fetch();
        if(!$alumno){
            $err='Solo se pueden reiniciar resultados de estudiantes.';
        } else {
            $pdo->prepare("DELETE FROM resultados WHERE usuario_id=?")->execute([$uid]);
            $ok='Resultados reiniciados para '.trim($alumno['nombre'].' '.$alumno['apellido']).' âœ…';
            header("Location: admin.php?panel=resultados&ok=".urlencode($ok));
            exit;
        }
    }
}

if(isset($_POST['toggle_sec'])){
    $sec_name = $_POST['seccion']??'';
    $sec_hab  = intval($_POST['habilitada']);
    $pdo->prepare("UPDATE secciones SET habilitada=? WHERE nombre=?")->execute([$sec_hab,$sec_name]);
    // Sync pruebas_config so all groups follow the global toggle
    if(in_array($sec_name,['clasificatoria','selectiva','final'])){
        try {
            $pdo->prepare("UPDATE pruebas_config SET habilitada=? WHERE tipo_prueba=?")
                ->execute([$sec_hab,$sec_name]);
        } catch(\Exception $e){}
    }
    // Redirect to avoid double-submit on refresh
    $estado = $sec_hab ? 'activada' : 'desactivada';
    header("Location: admin.php?panel=secciones&ok=".urlencode("Sección «$sec_name» $estado ✅"));
    exit;
}

// ── PREGUNTAS ───────────────────────────────────────────────────
if(isset($_POST['crear_pregunta'])){
    $img='';
    if(isset($_FILES['img_pregunta'])&&$_FILES['img_pregunta']['error']===0){
        [$okUpload,$uploadInfo]=validarUpload($_FILES['img_pregunta'], ['jpg','jpeg','png','gif','webp'], 5*1024*1024);
        if($okUpload){ $ext=$uploadInfo; $f='q_'.uniqid().'.'.$ext; if(move_uploaded_file($_FILES['img_pregunta']['tmp_name'],UPLOAD_IMG.$f)) $img='uploads/imgs/'.$f; }
    }
    $pdo->prepare("INSERT INTO preguntas(pregunta,imagen_url,op1,op2,op3,op4,correcta,nivel,tema,explicacion) VALUES(?,?,?,?,?,?,?,?,?,?)")
        ->execute([trim($_POST['pregunta']),$img,trim($_POST['op1']),trim($_POST['op2']),trim($_POST['op3']),trim($_POST['op4']??''),trim($_POST['correcta']),$_POST['nivel'],trim($_POST['tema']),trim($_POST['explicacion']??'')]);
    $ok='Pregunta agregada ✅';
}
if(isset($_POST['del_pregunta'])){
    $pid=intval($_POST['pid']);
    $r=$pdo->prepare("SELECT imagen_url FROM preguntas WHERE id=?"); $r->execute([$pid]); $row=$r->fetch();
    if($row&&$row['imagen_url']&&file_exists(__DIR__.'/'.$row['imagen_url'])) @unlink(__DIR__.'/'.$row['imagen_url']);
    $pdo->prepare("DELETE FROM preguntas WHERE id=?")->execute([$pid]);
    $ok='Pregunta eliminada ✅';
}

// ── CARGAR DATOS ────────────────────────────────────────────────
$instituciones = getInstituciones($pdo);
$usuarios      = $pdo->query("SELECT u.*,i.nombre inst_nombre,i.color inst_color
  FROM usuarios u LEFT JOIN instituciones i ON i.id=u.institucion_id
  ORDER BY i.nombre,u.rol,u.apellido")->fetchAll();
$preguntas     = $pdo->query("SELECT * FROM preguntas ORDER BY nivel,id")->fetchAll();
$secciones     = $pdo->query("SELECT * FROM secciones ORDER BY id")->fetchAll();
$stats = [
    'usuarios'   => count($usuarios),
    'insts'      => count($instituciones),
    'preguntas'  => count($preguntas),
    'resultados' => (int)$pdo->query("SELECT COUNT(*) FROM resultados")->fetchColumn(),
    'mensajes'   => (int)$pdo->query("SELECT COUNT(*) FROM mensajes")->fetchColumn(),
    'recursos'   => (int)$pdo->query("SELECT COUNT(*) FROM recursos")->fetchColumn(),
];

// Editar institución
$edit_inst=null;
if(isset($_GET['edit_inst'])&&is_numeric($_GET['edit_inst'])){ $s=$pdo->prepare("SELECT * FROM instituciones WHERE id=?"); $s->execute([intval($_GET['edit_inst'])]); $edit_inst=$s->fetch(); }

// Filtro institución para usuarios
$inst_filtro=intval($_GET['inst']??0);

$page_title='Administración — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
/* ── LAYOUT ── */
.adm-wrap{display:flex;min-height:calc(100vh - 68px)}
/* Sidebar */
.adm-side{width:220px;flex-shrink:0;background:white;border-right:1px solid var(--border);
  display:flex;flex-direction:column;height:calc(100vh - 68px);position:sticky;top:68px;overflow-y:auto}
.adm-side-head{padding:20px 16px 14px;border-bottom:1px solid var(--vp);
  background:linear-gradient(135deg,var(--vd),var(--v))}
.adm-side-head h2{font-size:14px;font-weight:700;color:white;margin-bottom:2px}
.adm-side-head p{font-size:11px;color:rgba(255,255,255,.6)}
.adm-nav{padding:10px 0;flex:1}
.adm-nav-item{display:flex;align-items:center;gap:10px;padding:11px 18px;font-size:13px;
  font-weight:600;color:#7a5060;cursor:pointer;transition:all .2s;border-left:3px solid transparent;
  text-decoration:none}
.adm-nav-item:hover{background:#fdf5f7;color:var(--vd);border-left-color:var(--border)}
.adm-nav-item.a{background:#faeef1;color:var(--vd);border-left-color:var(--v);font-weight:700}
.adm-nav-item .ni{font-size:16px;width:20px;text-align:center}
.adm-nav-sep{padding:6px 18px;font-size:10px;font-weight:800;color:#c0a0a8;
  text-transform:uppercase;letter-spacing:.1em;margin-top:6px}
.adm-nav-badge{margin-left:auto;background:var(--v);color:white;font-size:10px;
  font-weight:700;padding:2px 7px;border-radius:10px}
/* Main content */
.adm-main{flex:1;overflow-y:auto;background:var(--mist)}
.adm-section{display:none;animation:fi .25s ease}
.adm-section.a{display:block}
@keyframes fi{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
.adm-top{background:white;border-bottom:1px solid var(--border);padding:20px 28px;
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.adm-top h1{font-family:'DM Serif Display',serif;font-size:22px;color:var(--ink)}
.adm-top p{font-size:12.5px;color:#9a6070;margin-top:2px}
.adm-body{padding:22px 28px}
/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:22px}
.stat{background:white;border-radius:13px;padding:18px;text-align:center;
  box-shadow:var(--sh);border:1.5px solid var(--border)}
.sn{font-family:'DM Serif Display',serif;font-size:30px;color:var(--vd)}
.sl{font-size:11.5px;color:#9a6070;font-weight:600;margin-top:3px}
/* Institución cards */
.inst-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-bottom:20px}
.inst-card{background:white;border-radius:14px;padding:18px;box-shadow:var(--sh);
  border:1.5px solid var(--border);border-left:5px solid var(--v);transition:all .2s}
.inst-card:hover{box-shadow:var(--shh);transform:translateY(-2px)}
.ic-top{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.ic-dot{width:40px;height:40px;border-radius:10px;flex-shrink:0;display:flex;
  align-items:center;justify-content:center;font-size:18px;color:white;font-weight:800}
.ic-name{font-size:14px;font-weight:700;color:var(--ink);line-height:1.3}
.ic-city{font-size:12px;color:#9a6070;margin-top:1px}
.ic-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.ic-actions{display:flex;gap:7px;margin-top:12px}
/* Usuarios por institución */
.inst-section{margin-bottom:28px}
.inst-section-head{display:flex;align-items:center;gap:12px;padding:12px 16px;
  border-radius:12px 12px 0 0;color:white;font-weight:700;font-size:14px}
.inst-section-body{background:white;border-radius:0 0 12px 12px;border:1.5px solid var(--border);
  border-top:none;overflow:hidden}
/* User row */
.user-row{display:flex;align-items:center;gap:14px;padding:12px 16px;
  border-bottom:1px solid var(--vp);transition:background .15s}
.user-row:last-child{border-bottom:none}
.user-row:hover{background:#fdf5f7}
.user-av{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-weight:800;color:white;font-size:13px;flex-shrink:0}
.user-info{flex:1;min-width:0}
.user-name{font-size:13.5px;font-weight:700;color:var(--ink)}
.user-meta{font-size:11.5px;color:#9a6070;margin-top:2px;display:flex;gap:8px;flex-wrap:wrap}
.user-actions{display:flex;gap:6px;flex-shrink:0}
/* Formulario */
.form-card{background:white;border-radius:16px;padding:24px;box-shadow:var(--sh);
  border:1.5px solid var(--border);margin-bottom:20px}
.form-card h3{font-size:15px;font-weight:700;color:var(--ink);margin-bottom:18px;
  padding-bottom:12px;border-bottom:1px solid var(--vp)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px}
.form-grid-4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-bottom:14px}
/* Mensajes */
.ok-m{background:#e8f5e9;border:1.5px solid #a5d6a7;border-radius:9px;padding:11px 16px;color:#2e7d32;font-size:13px;font-weight:700;margin-bottom:16px}
.err-m{background:#fde8e8;border:1.5px solid #ef9a9a;border-radius:9px;padding:11px 16px;color:#c0392b;font-size:13px;font-weight:700;margin-bottom:16px}
/* Switch */
.sw{position:relative;width:44px;height:22px;flex-shrink:0}
.sw input{opacity:0;width:0;height:0}
.swsl{position:absolute;inset:0;background:#ccc;border-radius:22px;cursor:pointer;transition:.3s}
.swsl::before{content:'';position:absolute;width:16px;height:16px;bottom:3px;left:3px;background:white;border-radius:50%;transition:.3s}
input:checked+.swsl{background:var(--v)}
input:checked+.swsl::before{transform:translateX(22px)}
/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(26,10,15,.6);
  backdrop-filter:blur(4px);z-index:500;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal{background:white;border-radius:18px;padding:26px;width:560px;max-width:95vw;
  max-height:92vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.3)}
.modal h3{font-size:16px;font-weight:700;margin-bottom:16px;padding-bottom:12px;
  border-bottom:1px solid var(--vp);color:var(--ink);display:flex;align-items:center;justify-content:space-between}
.mclose{background:none;border:none;font-size:20px;cursor:pointer;color:#9a6070;padding:0}
</style>

<div class="adm-wrap">

<!-- ── SIDEBAR ─────────────────────────────────────────────── -->
<nav class="adm-side">
  <div class="adm-side-head">
    <h2>⚙️ Administración</h2>
    <p>Biffi Olimpiadas 2026</p>
  </div>
  <div class="adm-nav">
    <div class="adm-nav-sep">General</div>
    <a class="adm-nav-item a" onclick="showAdm('dashboard',this)" href="#">
      <span class="ni">📊</span> Dashboard
    </a>
    <div class="adm-nav-sep">Participantes</div>
    <a class="adm-nav-item" onclick="showAdm('instituciones',this)" href="#">
      <span class="ni">🏫</span> Instituciones
      <span class="adm-nav-badge"><?=count($instituciones)?></span>
    </a>
    <a class="adm-nav-item" onclick="showAdm('usuarios',this)" href="#">
      <span class="ni">👥</span> Usuarios
      <span class="adm-nav-badge"><?=count($usuarios)?></span>
    </a>
    <div class="adm-nav-sep">Pruebas</div>
    <a class="adm-nav-item" onclick="showAdm('preguntas',this)" href="#">
      <span class="ni">❓</span> Preguntas
      <span class="adm-nav-badge"><?=count($preguntas)?></span>
    </a>
    <a class="adm-nav-item" href="editor_simulacro.php">
      <span class="ni">✏️</span> Editor simulacros
    </a>
    <a class="adm-nav-item" href="forms_resultados.php">
      <span class="ni">📋</span> Google Forms
    </a>
    <div class="adm-nav-sep">Plataforma</div>
    <a class="adm-nav-item" onclick="showAdm('secciones',this)" href="#">
      <span class="ni">🔓</span> Secciones
    </a>
    <a class="adm-nav-item" onclick="showAdm('resultados',this)" href="#">
      <span class="ni">🏆</span> Resultados
    </a>
    <a class="adm-nav-item" href="biblioteca.php">
      <span class="ni">📚</span> Biblioteca
    </a>
  </div>
</nav>

<!-- ── CONTENIDO PRINCIPAL ──────────────────────────────────── -->
<main class="adm-main">
<?php if($ok): ?><div class="ok-m" style="margin:16px 28px 0">✅ <?=sanitize($ok)?></div><?php endif ?>
<?php if($err): ?><div class="err-m" style="margin:16px 28px 0">⚠️ <?=sanitize($err)?></div><?php endif ?>

<!-- ═══ DASHBOARD ═══════════════════════════════════════════ -->
<div id="sec-dashboard" class="adm-section a">
  <div class="adm-top">
    <div><h1>📊 Dashboard</h1><p>Resumen general de la plataforma</p></div>
  </div>
  <div class="adm-body">
    <div class="stats-row">
      <div class="stat"><div class="sn"><?=$stats['usuarios']?></div><div class="sl">Usuarios</div></div>
      <div class="stat"><div class="sn"><?=$stats['insts']?></div><div class="sl">Instituciones</div></div>
      <div class="stat"><div class="sn"><?=$stats['preguntas']?></div><div class="sl">Preguntas</div></div>
      <div class="stat"><div class="sn"><?=$stats['resultados']?></div><div class="sl">Simulacros</div></div>
      <div class="stat"><div class="sn"><?=$stats['mensajes']?></div><div class="sl">Mensajes</div></div>
      <div class="stat"><div class="sn"><?=$stats['recursos']?></div><div class="sl">Recursos</div></div>
    </div>
    <!-- Accesos rápidos -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px">
      <?php foreach([
        ['showAdm(\'instituciones\',null)','🏫','Gestionar Instituciones','Agrega, edita y organiza colegios'],
        ['showAdm(\'usuarios\',null)','👥','Gestionar Usuarios','Crea y administra cuentas'],
        ['editor_simulacro.php','✏️','Editor de Simulacros','Diseña preguntas con LaTeX e imágenes'],
        ['forms_resultados.php','📋','Google Forms','Examina formularios y respuestas'],
        ['showAdm(\'secciones\',null)','🔓','Control de Secciones','Activa/desactiva pruebas'],
        ['showAdm(\'resultados\',null)','🏆','Ver Resultados','Ranking por institución'],
      ] as [$act,$ico,$tit,$sub]):
        $isLink=str_ends_with($act,'.php');
      ?>
      <<?=$isLink?'a href="'.$act.'"':'div onclick="'.$act.'"'?> style="background:white;border-radius:14px;padding:18px;box-shadow:var(--sh);border:1.5px solid var(--border);cursor:pointer;transition:all .2s;text-decoration:none;color:inherit;display:block" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
        <div style="font-size:28px;margin-bottom:8px"><?=$ico?></div>
        <div style="font-size:13.5px;font-weight:700;color:var(--ink);margin-bottom:3px"><?=$tit?></div>
        <div style="font-size:12px;color:#9a6070"><?=$sub?></div>
      </<?=$isLink?'a':'div'?>>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- ═══ INSTITUCIONES ════════════════════════════════════════ -->
<div id="sec-instituciones" class="adm-section">
  <div class="adm-top">
    <div><h1>🏫 Instituciones Educativas</h1><p>Colegios participantes en las Olimpiadas Biffi 2026</p></div>
    <button class="btn btn-v" onclick="openModal('modal-inst')">➕ Nueva institución</button>
  </div>
  <div class="adm-body">
    <div class="inst-grid">
      <?php foreach($instituciones as $inst):
        // Contar usuarios
        $cu=$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE institucion_id=? AND rol='estudiante' AND activo=1");
        $cu->execute([$inst['id']]); $nestud=(int)$cu->fetchColumn();
        $cd=$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE institucion_id=? AND rol='docente' AND activo=1");
        $cd->execute([$inst['id']]); $ndoc=(int)$cd->fetchColumn();
        // Mejor puntaje de la institución
        $mp=$pdo->prepare("SELECT ROUND(AVG(r.puntaje/r.total*100),0) FROM resultados r JOIN usuarios u ON u.id=r.usuario_id WHERE u.institucion_id=? AND r.total>0");
        $mp->execute([$inst['id']]); $prom=(int)$mp->fetchColumn();
      ?>
      <div class="inst-card" style="border-left-color:<?=sanitize($inst['color'])?>">
        <div class="ic-top">
          <div class="ic-dot" style="background:<?=sanitize($inst['color'])?>"><?=strtoupper(substr($inst['nombre'],0,2))?></div>
          <div>
            <div class="ic-name"><?=sanitize($inst['nombre'])?></div>
            <div class="ic-city">📍 <?=sanitize($inst['ciudad'])?></div>
          </div>
        </div>
        <div class="ic-meta">
          <span class="badge b-v">🎓 <?=$nestud?> estudiantes</span>
          <span class="badge b-gold">👩‍🏫 <?=$ndoc?> docentes</span>
          <?php if($prom>0): ?><span class="badge b-g">📊 <?=$prom?>% prom.</span><?php endif ?>
          <span class="badge <?=$inst['activa']?'b-g':'b-gray'?>"><?=$inst['activa']?'Activa':'Inactiva'?></span>
        </div>
        <div class="ic-actions">
          <button class="btn btn-outline btn-sm" onclick='openEditInst(<?=json_encode($inst)?>)'>✏️ Editar</button>
          <a href="admin.php?inst=<?=$inst['id']?>#usuarios" class="btn btn-v btn-sm" onclick="showAdm('usuarios',null)">👥 Ver usuarios</a>
          <?php if($nestud===0&&$ndoc===0): ?>
          <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar <?=sanitize($inst['nombre'])?>?')">
            <input type="hidden" name="inst_id" value="<?=$inst['id']?>">
            <button type="submit" name="eliminar_inst" class="btn btn-red btn-sm">🗑️</button>
          </form>
          <?php endif ?>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- ═══ USUARIOS ═════════════════════════════════════════════ -->
<div id="sec-usuarios" class="adm-section">
  <div class="adm-top">
    <div><h1>👥 Usuarios</h1><p>Organizados por institución y rol</p></div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <!-- Filtro institución -->
      <select id="filtro-inst" class="fsel" style="font-size:12.5px;padding:7px 12px" onchange="filtrarInst(this.value)">
        <option value="0">Todas las instituciones</option>
        <?php foreach($instituciones as $i): ?>
        <option value="<?=$i['id']?>" <?=$inst_filtro==$i['id']?'selected':''?>><?=sanitize($i['nombre'])?></option>
        <?php endforeach ?>
      </select>
      <button class="btn btn-v btn-sm" onclick="openModal('modal-usuario')">➕ Nuevo usuario</button>
    </div>
  </div>
  <div class="adm-body">
    <?php
    // Agrupar por institución
    $por_inst=[]; $sin_inst=[];
    foreach($usuarios as $u){
        if($u['institucion_id']) $por_inst[$u['institucion_id']][]=$u;
        else $sin_inst[]=$u;
    }
    // Mostrar todas o filtrar
    $insts_mostrar = $inst_filtro>0
        ? array_filter($instituciones, fn($i)=>$i['id']==$inst_filtro)
        : $instituciones;

    foreach($insts_mostrar as $inst):
        $lista_inst = $por_inst[$inst['id']] ?? [];
        if(empty($lista_inst)) continue;
    ?>
    <div class="inst-section" data-inst="<?=$inst['id']?>">
      <div class="inst-section-head" style="background:<?=sanitize($inst['color'])?>">
        <div style="width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,.2);
          display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px">
          <?=strtoupper(substr($inst['nombre'],0,2))?>
        </div>
        <?=sanitize($inst['nombre'])?> · <?=sanitize($inst['ciudad'])?>
        <span style="margin-left:auto;background:rgba(255,255,255,.2);padding:3px 10px;
          border-radius:12px;font-size:12px"><?=count($lista_inst)?> usuario(s)</span>
      </div>
      <div class="inst-section-body">
        <?php
        // Sub-agrupar por rol
        $roles=['admin'=>'👑 Admin','docente'=>'👩‍🏫 Docentes','estudiante'=>'🎓 Estudiantes'];
        foreach($roles as $rol=>$rol_lbl):
          $lista_rol=array_filter($lista_inst,fn($u)=>$u['rol']===$rol);
          if(empty($lista_rol)) continue;
        ?>
        <div style="padding:8px 16px;background:#fdf8f9;border-bottom:1px solid var(--vp);
          font-size:11px;font-weight:700;color:#9a6070;text-transform:uppercase;letter-spacing:.07em">
          <?=$rol_lbl?> (<?=count($lista_rol)?>)
        </div>
        <?php foreach($lista_rol as $u):
          $iniciales=strtoupper(substr($u['nombre'],0,1).substr($u['apellido'],0,1));
          $color_av=$u['inst_color']??'#7C1F30';
          $grp=!empty($u['grado'])?grupoDeGrado((int)$u['grado']):null;
        ?>
        <div class="user-row">
          <div class="user-av" style="background:<?=sanitize($color_av)?>"><?=$iniciales?></div>
          <div class="user-info">
            <div class="user-name"><?=sanitize($u['nombre'].' '.$u['apellido'])?></div>
            <div class="user-meta">
              <span>@<?=sanitize($u['usuario'])?></span>
              <span><?=sanitize($u['correo'])?></span>
              <?php if($u['grado']): ?><span class="badge" style="background:<?=colorGrupo($grp??'10-11')?>;color:white;font-size:9px">Grado <?=$u['grado']?>°</span><?php endif ?>
              <?php if($u['curso']): ?><span class="badge b-gray" style="font-size:9px"><?=sanitize($u['curso'])?></span><?php endif ?>
              <?php if($u['nivel']&&$rol==='estudiante'): ?><span class="badge b-gold" style="font-size:9px"><?=ucfirst($u['nivel'])?></span><?php endif ?>
              <?php if(!$u['activo']): ?><span class="badge b-red" style="font-size:9px">Inactivo</span><?php endif ?>
            </div>
          </div>
          <div class="user-actions">
            <button class="btn btn-outline btn-sm" onclick='openEditUser(<?=json_encode($u)?>)'>✏️</button>
            <?php if($rol==='estudiante'): ?>
            <form method="POST" style="margin:0" onsubmit="return confirm('¿Borrar todos los resultados de <?=sanitize($u['nombre'].' '.$u['apellido'])?> y reiniciar sus intentos?')">
              <input type="hidden" name="uid" value="<?=$u['id']?>">
              <button type="submit" name="limpiar_resultados_usuario" class="btn btn-outline btn-sm">🔁</button>
            </form>
            <?php endif ?>
            <?php if($u['id']!==$_SESSION['user_id']): ?>
            <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar a <?=sanitize($u['nombre'])?>?')">
              <input type="hidden" name="uid" value="<?=$u['id']?>">
              <button type="submit" name="eliminar_usuario" class="btn btn-red btn-sm">🗑️</button>
            </form>
            <?php endif ?>
          </div>
        </div>
        <?php endforeach; endforeach ?>
      </div>
    </div>
    <?php endforeach ?>
    <?php if(!empty($sin_inst) && ($inst_filtro===0)): ?>
    <div class="inst-section">
      <div class="inst-section-head" style="background:#9a6070">Sin institución asignada</div>
      <div class="inst-section-body">
        <?php foreach($sin_inst as $u): ?>
        <div class="user-row">
          <div class="user-av" style="background:#9a6070"><?=strtoupper(substr($u['nombre'],0,1).substr($u['apellido'],0,1))?></div>
          <div class="user-info">
            <div class="user-name"><?=sanitize($u['nombre'].' '.$u['apellido'])?></div>
            <div class="user-meta"><span>@<?=sanitize($u['usuario'])?></span></div>
          </div>
          <div class="user-actions">
            <button class="btn btn-outline btn-sm" onclick='openEditUser(<?=json_encode($u)?>)'>✏️ Asignar inst.</button>
          </div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>
  </div>
</div>

<!-- ═══ PREGUNTAS ════════════════════════════════════════════ -->
<div id="sec-preguntas" class="adm-section">
  <div class="adm-top">
    <div><h1>❓ Banco de preguntas</h1><p>Usa el editor para crear preguntas con LaTeX, imágenes y tipos de prueba</p></div>
    <div style="display:flex;gap:8px">
      <a href="editor_simulacro.php" class="btn btn-v">✏️ Abrir editor completo</a>
      <button class="btn btn-outline" onclick="openModal('modal-preg')">➕ Rápido</button>
    </div>
  </div>
  <div class="adm-body">
    <div style="overflow-x:auto;background:white;border-radius:14px;box-shadow:var(--sh);border:1.5px solid var(--border)">
      <table>
        <tr><th>#</th><th>Pregunta</th><th>Grupo</th><th>Tipo prueba</th><th>Nivel</th><th>Tema</th><th>Img</th><th></th></tr>
        <?php foreach($preguntas as $p): ?>
        <tr>
          <td style="font-weight:700;color:#9a6070"><?=$p['id']?></td>
          <td style="max-width:200px;font-size:12.5px"><?=sanitize(mb_substr(strip_tags($p['pregunta']),0,55))?>...</td>
          <td><span class="badge" style="background:<?=colorGrupo($p['grupo_grado']??'10-11')?>;color:white;font-size:10px"><?=$p['grupo_grado']??'10-11'?></span></td>
          <td><span class="badge b-v" style="font-size:10px"><?=ucfirst($p['tipo_prueba']??'simulacro')?></span></td>
          <td><span class="badge b-gold" style="font-size:10px"><?=ucfirst($p['nivel'])?></span></td>
          <td style="font-size:12px"><?=sanitize($p['tema'])?></td>
          <td><?=$p['imagen_url']?'✅':'—'?></td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="editor_simulacro.php?edit=<?=$p['id']?>" class="btn btn-outline btn-sm">✏️</a>
              <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar?')">
                <input type="hidden" name="pid" value="<?=$p['id']?>">
                <button type="submit" name="del_pregunta" class="btn btn-red btn-sm">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
      </table>
    </div>
  </div>
</div>

<!-- ═══ SECCIONES ════════════════════════════════════════════ -->
<div id="sec-secciones" class="adm-section">
  <div class="adm-top"><div><h1>🔓 Control de secciones</h1><p>Activa o desactiva fases de la competencia</p></div></div>
  <div class="adm-body">
    <div style="max-width:600px;display:flex;flex-direction:column;gap:10px">
      <?php foreach($secciones as $s): ?>
      <form method="POST" style="margin:0">
        <input type="hidden" name="toggle_sec" value="1">
        <input type="hidden" name="seccion" value="<?=$s['nombre']?>">
        <!-- hidden 0 so POST always has habilitada; checkbox overrides to 1 when checked -->
        <input type="hidden" name="habilitada" value="0">
        <div style="background:white;border-radius:12px;padding:16px 20px;box-shadow:var(--sh);
          border:1.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:14px">
          <div>
            <div style="font-size:14px;font-weight:700;color:var(--ink)"><?=sanitize($s['etiqueta'])?></div>
            <div style="font-size:11.5px;color:#9a6070;margin-top:2px">Sección: <code style="background:#f0e4e8;padding:1px 6px;border-radius:4px"><?=$s['nombre']?></code></div>
          </div>
          <div style="display:flex;align-items:center;gap:12px">
            <label class="sw">
              <input type="checkbox" name="habilitada" value="1" <?=$s['habilitada']?'checked':''?>
                onchange="this.form.submit()">
              <span class="swsl"></span>
            </label>
            <span style="font-size:12.5px;font-weight:700;color:<?=$s['habilitada']?'var(--green)':'#9a6070'?>;min-width:70px">
              <?=$s['habilitada']?'✅ Activa':'⛔ Inactiva'?>
            </span>
          </div>
        </div>
      </form>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- ═══ RESULTADOS ════════════════════════════════════════════ -->
<div id="sec-resultados" class="adm-section">
  <div class="adm-top"><div><h1>🏆 Resultados generales</h1><p>Ranking por institución educativa</p></div></div>
  <div class="adm-body">
    <!-- Ranking por institución -->
    <h3 style="font-size:14px;font-weight:700;color:var(--vd);margin-bottom:14px;text-transform:uppercase;letter-spacing:.06em">🏅 Ranking por institución</h3>
    <div class="inst-grid" style="margin-bottom:28px">
      <?php foreach($instituciones as $inst):
        $rs=$pdo->prepare("SELECT COUNT(DISTINCT r.usuario_id) participantes,
          ROUND(AVG(r.puntaje/r.total*100),1) promedio, MAX(r.puntaje/r.total*100) mejor
          FROM resultados r JOIN usuarios u ON u.id=r.usuario_id
          WHERE u.institucion_id=? AND r.total>0");
        $rs->execute([$inst['id']]); $ri=$rs->fetch();
      ?>
      <div style="background:white;border-radius:14px;padding:18px;box-shadow:var(--sh);border:1.5px solid var(--border);border-top:4px solid <?=sanitize($inst['color'])?>">
        <div style="font-size:14px;font-weight:700;color:var(--ink);margin-bottom:10px"><?=sanitize($inst['nombre'])?></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center">
          <div><div style="font-family:'DM Serif Display',serif;font-size:22px;color:var(--vd)"><?=(int)$ri['participantes']?></div><div style="font-size:10.5px;color:#9a6070">Participantes</div></div>
          <div><div style="font-family:'DM Serif Display',serif;font-size:22px;color:var(--vd)"><?=number_format((float)($ri['promedio']??0),1)?>%</div><div style="font-size:10.5px;color:#9a6070">Promedio</div></div>
          <div><div style="font-family:'DM Serif Display',serif;font-size:22px;color:var(--vd)"><?=(int)($ri['mejor']??0)?>%</div><div style="font-size:10.5px;color:#9a6070">Mejor</div></div>
        </div>
      </div>
      <?php endforeach ?>
    </div>
    <!-- Tabla completa -->
    <h3 style="font-size:14px;font-weight:700;color:var(--vd);margin-bottom:14px;text-transform:uppercase;letter-spacing:.06em">📋 Todos los resultados</h3>
    <div style="overflow-x:auto;background:white;border-radius:14px;box-shadow:var(--sh);border:1.5px solid var(--border)">
      <table>
        <tr><th>Estudiante</th><th>Institución</th><th>Grado</th><th>Nivel prueba</th><th>Tipo</th><th>Puntaje</th><th>%</th><th>Tiempo</th><th>Fecha</th></tr>
        <?php
        $todos=$pdo->query("SELECT r.*,u.nombre,u.apellido,u.grado,u.curso,i.nombre inst_nom,i.color inst_col
          FROM resultados r JOIN usuarios u ON u.id=r.usuario_id
          LEFT JOIN instituciones i ON i.id=u.institucion_id
          WHERE u.rol='estudiante' ORDER BY r.fecha DESC LIMIT 150")->fetchAll();
        foreach($todos as $r):
          $pct=$r['total']>0?round($r['puntaje']/$r['total']*100):0;
          $col=$pct>=80?'#2e7d32':($pct>=60?'#f57c00':'#c0392b');
          $parts=explode('_',$r['nivel']);
          $tipos_lbl=['simulacro'=>'Simulacro','clasificatoria'=>'Clasificatoria','selectiva'=>'Selectiva','final'=>'Final','evaluacion'=>'Evaluación'];
        ?>
        <tr>
          <td style="font-weight:700"><?=sanitize($r['nombre'].' '.$r['apellido'])?></td>
          <td>
            <?php if($r['inst_nom']): ?>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600">
              <span style="width:10px;height:10px;border-radius:50%;background:<?=sanitize($r['inst_col']??'#999')?>;flex-shrink:0"></span>
              <?=sanitize($r['inst_nom'])?>
            </span>
            <?php else: ?>—<?php endif ?>
          </td>
          <td><?=$r['grado']?$r['grado'].'°':'—'?></td>
          <td><span class="badge b-v" style="font-size:10px"><?=ucfirst($parts[0]??$r['nivel'])?></span></td>
          <td><span class="badge" style="background:<?=colorTipo($parts[2]??'simulacro')?>;color:white;font-size:10px"><?=$tipos_lbl[$parts[2]??'simulacro']??ucfirst($parts[2]??'')?></span></td>
          <td style="font-family:'JetBrains Mono',monospace;font-weight:700"><?=$r['puntaje']?>/<?=$r['total']?></td>
          <td style="font-weight:700;color:<?=$col?>"><?=$pct?>%</td>
          <td style="font-family:'JetBrains Mono',monospace;font-size:12px"><?=gmdate('i:s',$r['tiempo_seg'])?></td>
          <td style="font-size:11.5px;color:#9a6070"><?=date('d/m/Y H:i',strtotime($r['fecha']))?></td>
        </tr>
        <?php endforeach ?>
      </table>
    </div>
  </div>
</div>

</main>
</div>

<!-- ═══ MODAL: INSTITUCIÓN ═══════════════════════════════════ -->
<div class="modal-bg" id="modal-inst">
<div class="modal">
  <h3>🏫 Institución <button class="mclose" onclick="closeModal('modal-inst')">✕</button></h3>
  <form method="POST">
    <input type="hidden" name="inst_id" id="inst-id" value="0">
    <div class="form-grid" style="margin-bottom:14px">
      <div class="fg"><label class="fl">Nombre *</label><input type="text" name="inst_nombre" id="inst-nombre" class="fi" required></div>
      <div class="fg"><label class="fl">Ciudad</label><input type="text" name="inst_ciudad" id="inst-ciudad" class="fi" value="Cartagena"></div>
      <div class="fg"><label class="fl">Color distintivo</label><input type="color" name="inst_color" id="inst-color" class="fi" style="padding:4px;height:42px" value="#7C1F30"></div>
      <div class="fg"><label class="fl">Estado</label>
        <select name="inst_activa" id="inst-activa" class="fsel">
          <option value="1">✅ Activa</option><option value="0">⛔ Inactiva</option>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <button type="submit" name="guardar_inst" class="btn btn-v">💾 Guardar</button>
      <button type="button" class="btn btn-outline" onclick="closeModal('modal-inst')">Cancelar</button>
    </div>
  </form>
</div></div>

<!-- ═══ MODAL: USUARIO ═══════════════════════════════════════ -->
<div class="modal-bg" id="modal-usuario">
<div class="modal">
  <h3 id="modal-user-title">👤 Nuevo usuario <button class="mclose" onclick="closeModal('modal-usuario')">✕</button></h3>
  <form method="POST" id="form-usuario">
    <input type="hidden" name="crear_usuario" id="action-user" value="crear_usuario">
    <input type="hidden" name="uid" id="uid" value="0">
    <div class="form-grid">
      <div class="fg"><label class="fl">Nombre *</label><input type="text" name="nombre" id="u-nombre" class="fi" required></div>
      <div class="fg"><label class="fl">Apellido *</label><input type="text" name="apellido" id="u-apellido" class="fi" required></div>
      <div class="fg"><label class="fl">Usuario *</label><input type="text" name="usuario" id="u-usuario" class="fi" required placeholder="sin.espacios"></div>
      <div class="fg"><label class="fl">Correo *</label><input type="email" name="correo" id="u-correo" class="fi" required></div>
      <div class="fg"><label class="fl">Contraseña *</label><input type="password" name="contrasena" id="u-clave" class="fi" placeholder="Mín. 8 caracteres"></div>
      <div class="fg"><label class="fl">Nueva contraseña <em style="font-weight:400">(solo al editar)</em></label><input type="password" name="nueva_clave" id="u-nueva-clave" class="fi" placeholder="Vacío = no cambiar"></div>
      <div class="fg"><label class="fl">Rol *</label>
        <select name="rol" id="u-rol" class="fsel">
          <option value="estudiante">🎓 Estudiante</option>
          <option value="docente">👩‍🏫 Docente</option>
          <option value="admin">👑 Admin</option>
        </select>
      </div>
      <div class="fg"><label class="fl">Institución</label>
        <select name="institucion_id" id="u-inst" class="fsel">
          <option value="">— Sin asignar —</option>
          <?php foreach($instituciones as $i): ?>
          <option value="<?=$i['id']?>"><?=sanitize($i['nombre'])?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="fg"><label class="fl">Grado escolar</label>
        <select name="grado" id="u-grado" class="fsel">
          <option value="">— Sin asignar —</option>
          <optgroup label="Grupo 4°-5°"><option value="4">4°</option><option value="5">5°</option></optgroup>
          <optgroup label="Grupo 6°-7°"><option value="6">6°</option><option value="7">7°</option></optgroup>
          <optgroup label="Grupo 8°-9°"><option value="8">8°</option><option value="9">9°</option></optgroup>
          <optgroup label="Grupo 10°-11°"><option value="10">10°</option><option value="11">11°</option></optgroup>
        </select>
      </div>
      <div class="fg"><label class="fl">Salón / Curso</label><input type="text" name="curso" id="u-curso" class="fi" placeholder="Ej: 10-A"></div>
      <div class="fg"><label class="fl">Nivel olimpíadas</label>
        <select name="nivel" id="u-nivel" class="fsel">
          <option value="basico">Básico</option>
          <option value="medio">Medio</option>
          <option value="avanzado">Avanzado</option>
        </select>
      </div>
      <div class="fg"><label class="fl">Estado</label>
        <select name="activo" id="u-activo" class="fsel">
          <option value="1">✅ Activo</option><option value="0">⛔ Inactivo</option>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:6px">
      <button type="submit" id="btn-user-submit" class="btn btn-v">Crear usuario</button>
      <button type="button" class="btn btn-outline" onclick="closeModal('modal-usuario')">Cancelar</button>
    </div>
  </form>
</div></div>

<!-- ═══ MODAL: PREGUNTA RÁPIDA ═══════════════════════════════ -->
<div class="modal-bg" id="modal-preg">
<div class="modal">
  <h3>❓ Pregunta rápida <button class="mclose" onclick="closeModal('modal-preg')">✕</button></h3>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="crear_pregunta" value="1">
    <div class="fg" style="margin-bottom:12px"><label class="fl">Enunciado *</label><textarea name="pregunta" class="fi" rows="3" required></textarea></div>
    <div class="form-grid">
      <div class="fg"><label class="fl">Opción A *</label><input type="text" name="op1" class="fi" required></div>
      <div class="fg"><label class="fl">Opción B *</label><input type="text" name="op2" class="fi" required></div>
      <div class="fg"><label class="fl">Opción C *</label><input type="text" name="op3" class="fi" required></div>
      <div class="fg"><label class="fl">Opción D</label><input type="text" name="op4" class="fi"></div>
      <div class="fg"><label class="fl">Respuesta correcta *</label><input type="text" name="correcta" class="fi" required></div>
      <div class="fg"><label class="fl">Nivel</label>
        <select name="nivel" class="fsel"><option value="basico">Básico</option><option value="medio">Medio</option><option value="avanzado">Avanzado</option></select>
      </div>
      <div class="fg"><label class="fl">Tema</label><input type="text" name="tema" class="fi" value="Aritmética"></div>
      <div class="fg"><label class="fl">Explicación</label><input type="text" name="explicacion" class="fi"></div>
    </div>
    <div class="fg" style="margin-bottom:14px"><label class="fl">Imagen (opcional)</label><input type="file" name="img_pregunta" class="fi" accept="image/*"></div>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-v">Agregar</button>
      <button type="button" class="btn btn-outline" onclick="closeModal('modal-preg')">Cancelar</button>
    </div>
  </form>
</div></div>

<script>
// Navegación del sidebar
function showAdm(name,btn){
  document.querySelectorAll('.adm-section').forEach(s=>s.classList.remove('a'));
  document.querySelectorAll('.adm-nav-item').forEach(b=>b.classList.remove('a'));
  document.getElementById('sec-'+name)?.classList.add('a');
  if(btn) btn.classList.add('a');
}
// Auto-open panel from URL (?panel=xxx)
window.addEventListener('DOMContentLoaded',()=>{
  const urlPanel='<?=htmlspecialchars($_GET['panel']??'')?>';
  if(urlPanel) showAdm(urlPanel, null);
});
// Modales
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open')}));

// Editar institución
function openEditInst(i){
  document.getElementById('inst-id').value=i.id;
  document.getElementById('inst-nombre').value=i.nombre;
  document.getElementById('inst-ciudad').value=i.ciudad||'Cartagena';
  document.getElementById('inst-color').value=i.color||'#7C1F30';
  document.getElementById('inst-activa').value=i.activa;
  openModal('modal-inst');
}

// Crear / editar usuario
function openEditUser(u){
  document.getElementById('modal-user-title').innerHTML='✏️ Editar usuario &nbsp;<button class="mclose" onclick="closeModal(\'modal-usuario\')">✕</button>';
  document.getElementById('action-user').name='editar_usuario';
  document.getElementById('uid').value=u.id;
  document.getElementById('u-nombre').value=u.nombre;
  document.getElementById('u-apellido').value=u.apellido;
  document.getElementById('u-usuario').value=u.usuario;
  document.getElementById('u-correo').value=u.correo;
  document.getElementById('u-clave').required=false;
  document.getElementById('u-clave').style.display='none';
  document.getElementById('u-nueva-clave').style.display='';
  document.getElementById('u-rol').value=u.rol;
  document.getElementById('u-inst').value=u.institucion_id||'';
  document.getElementById('u-grado').value=u.grado||'';
  document.getElementById('u-curso').value=u.curso||'';
  document.getElementById('u-nivel').value=u.nivel||'basico';
  document.getElementById('u-activo').value=u.activo;
  document.getElementById('btn-user-submit').textContent='💾 Guardar cambios';
  openModal('modal-usuario');
}

// Filtrar por institución
function filtrarInst(val){
  document.querySelectorAll('.inst-section').forEach(s=>{
    if(val==='0'||s.dataset.inst===val) s.style.display='';
    else s.style.display='none';
  });
}

<?php if($ok): ?>window.addEventListener('load',()=>st('<?=addslashes(sanitize($ok))?>'));<?php endif ?>
</script>
<?php require_once 'includes/footer.php'; ?>
