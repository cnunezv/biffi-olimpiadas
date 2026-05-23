<?php
require_once 'includes/config.php';
requireDocente();
// Solo docentes del Colegio Biffi y admins pueden editar pruebas
if(!puedeEditarPruebas()){
    header('Location: docente.php?err=sin_permiso'); exit;
}

$ok = ''; $err = '';
$nivel_sel    = $_GET['nivel']  ?? 'basico';
$grupo_sel    = $_GET['grupo']  ?? '10-11';
$tipo_sel     = $_GET['tipo']   ?? 'simulacro';
if(!in_array($nivel_sel,['basico','medio','avanzado'])) $nivel_sel='basico';
if(!in_array($grupo_sel,['4-5','6-7','8-9','10-11']))   $grupo_sel='10-11';
if(!array_key_exists($tipo_sel,tiposPrueba()))           $tipo_sel='simulacro';

// ── ACCIONES POST ─────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'){

    if(isset($_POST['guardar_pregunta'])){
        $id        = intval($_POST['pid']??0);
        $pregunta  = trim($_POST['pregunta']??'');
        $op1       = trim($_POST['op1']??''); $op2=trim($_POST['op2']??'');
        $op3       = trim($_POST['op3']??''); $op4=trim($_POST['op4']??'');
        $correcta  = trim($_POST['correcta']??'');
        $nivel     = $_POST['nivel']??'basico';
        $grupo     = $_POST['grupo_grado']??'10-11';
        $tipo      = $_POST['tipo_prueba']??'simulacro';
        $tema      = trim($_POST['tema']??'General');
        $explic    = trim($_POST['explicacion']??'');
        $img_url   = trim($_POST['img_actual']??'');

        if(!$pregunta||!$op1||!$op2||!$op3||!$correcta){
            $err='Completa los campos obligatorios (pregunta, opciones A-B-C y respuesta correcta).';
        } else {
            if(isset($_FILES['imagen'])&&$_FILES['imagen']['error']===0){
                $ext=strtolower(pathinfo($_FILES['imagen']['name'],PATHINFO_EXTENSION));
                if(in_array($ext,['jpg','jpeg','png','gif','webp','svg'])){
                    $fname='q_'.uniqid().'.'.$ext;
                    if(!is_dir(UPLOAD_IMG)) mkdir(UPLOAD_IMG,0755,true);
                    if(move_uploaded_file($_FILES['imagen']['tmp_name'],UPLOAD_IMG.$fname)){
                        if($img_url&&file_exists(__DIR__.'/'.$img_url)) @unlink(__DIR__.'/'.$img_url);
                        $img_url='uploads/imgs/'.$fname;
                    }
                } else $err='Imagen no válida. Usa JPG, PNG, GIF, WEBP o SVG.';
            }
            if(!$err){
                if($id>0){
                    $pdo->prepare("UPDATE preguntas SET pregunta=?,imagen_url=?,op1=?,op2=?,op3=?,op4=?,correcta=?,nivel=?,grupo_grado=?,tipo_prueba=?,tema=?,explicacion=? WHERE id=?")
                        ->execute([$pregunta,$img_url,$op1,$op2,$op3,$op4,$correcta,$nivel,$grupo,$tipo,$tema,$explic,$id]);
                    $ok='Pregunta #'.$id.' actualizada ✅';
                } else {
                    $pdo->prepare("INSERT INTO preguntas(pregunta,imagen_url,op1,op2,op3,op4,correcta,nivel,grupo_grado,tipo_prueba,tema,explicacion) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$pregunta,$img_url,$op1,$op2,$op3,$op4,$correcta,$nivel,$grupo,$tipo,$tema,$explic]);
                    $ok='Pregunta creada ✅';
                }
                $nivel_sel=$nivel; $grupo_sel=$grupo; $tipo_sel=$tipo;
            }
        }
    }

    if(isset($_POST['eliminar_pregunta'])){
        $id=intval($_POST['pid']);
        $r=$pdo->prepare("SELECT imagen_url FROM preguntas WHERE id=?");
        $r->execute([$id]); $row=$r->fetch();
        if($row&&$row['imagen_url']&&file_exists(__DIR__.'/'.$row['imagen_url'])) @unlink(__DIR__.'/'.$row['imagen_url']);
        $pdo->prepare("DELETE FROM preguntas WHERE id=?")->execute([$id]);
        $ok='Pregunta eliminada ✅';
    }

    if(isset($_POST['quitar_imagen'])){
        $id=intval($_POST['pid']);
        $r=$pdo->prepare("SELECT imagen_url FROM preguntas WHERE id=?");
        $r->execute([$id]); $row=$r->fetch();
        if($row&&$row['imagen_url']&&file_exists(__DIR__.'/'.$row['imagen_url'])) @unlink(__DIR__.'/'.$row['imagen_url']);
        $pdo->prepare("UPDATE preguntas SET imagen_url='' WHERE id=?")->execute([$id]);
        $ok='Imagen eliminada ✅';
    }

    // Guardar configuración de prueba (tiempo límite, num preguntas, estado)
    if(isset($_POST['guardar_config'])){
        $tp  = $_POST['tipo_cfg']??'simulacro';
        $gg  = $_POST['grupo_cfg']??'10-11';
        $tl  = $_POST['tiempo_limite']!==''?intval($_POST['tiempo_limite']):null;
        $np  = intval($_POST['num_preguntas']??10);
        $mi  = $_POST['max_intentos']!==''?intval($_POST['max_intentos']):0; // 0 = sin límite
        $hab = intval($_POST['habilitada']??0);
        $ins = trim($_POST['instrucciones']??'');
        $pdo->prepare("INSERT INTO pruebas_config(tipo_prueba,grupo_grado,tiempo_limite_min,num_preguntas,max_intentos,habilitada,instrucciones)
            VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
            tiempo_limite_min=VALUES(tiempo_limite_min),num_preguntas=VALUES(num_preguntas),
            max_intentos=VALUES(max_intentos),habilitada=VALUES(habilitada),instrucciones=VALUES(instrucciones)")
            ->execute([$tp,$gg,$tl,$np,$mi,$hab,$ins]);
        // También sincronizar sección global si aplica
        if(in_array($tp,['clasificatoria','selectiva','final'])){
            $pdo->prepare("UPDATE secciones SET habilitada=? WHERE nombre=?")->execute([$hab,$tp]);
        }
        $ok='Configuración guardada ✅';
    }
}

// Cargar pregunta para editar
$edit_pregunta=null;
if(isset($_GET['edit'])&&is_numeric($_GET['edit'])){
    $s=$pdo->prepare("SELECT * FROM preguntas WHERE id=?");
    $s->execute([intval($_GET['edit'])]); $edit_pregunta=$s->fetch();
    if($edit_pregunta){ $nivel_sel=$edit_pregunta['nivel']; $grupo_sel=$edit_pregunta['grupo_grado']; $tipo_sel=$edit_pregunta['tipo_prueba']??'simulacro'; }
}

// Listado de preguntas con filtros
$lista=$pdo->prepare("SELECT * FROM preguntas WHERE nivel=? AND grupo_grado=? AND tipo_prueba=? ORDER BY id DESC");
$lista->execute([$nivel_sel,$grupo_sel,$tipo_sel]); $lista=$lista->fetchAll();

// Conteos totales por tipo+grupo+nivel
function contarPreg($pdo,$nivel,$grupo,$tipo){
    $c=$pdo->prepare("SELECT COUNT(*) FROM preguntas WHERE nivel=? AND grupo_grado=? AND tipo_prueba=?");
    $c->execute([$nivel,$grupo,$tipo]); return (int)$c->fetchColumn();
}

// Configuración de pruebas
$configs=$pdo->query("SELECT * FROM pruebas_config ORDER BY grupo_grado,tipo_prueba")->fetchAll();
$cfg_map=[];
foreach($configs as $c) $cfg_map[$c['grupo_grado'].'_'.$c['tipo_prueba']]=$c;

$tipos=tiposPrueba();
$page_title='Editor de Pruebas — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<script>
window.MathJax={tex:{inlineMath:[['$','$'],['\\(','\\)']],displayMath:[['$$','$$'],['\\[','\\]']],processEscapes:true},options:{skipHtmlTags:['script','noscript','style','textarea']}};
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js" async></script>
<style>
.ew{display:flex;min-height:calc(100vh - 68px)}
/* ── LEFT PANEL ── */
.pl{width:290px;flex-shrink:0;background:white;border-right:1px solid var(--border);
  display:flex;flex-direction:column;height:calc(100vh - 68px);position:sticky;top:68px;overflow:hidden}
.pl-head{padding:14px;background:linear-gradient(135deg,var(--vd),var(--v));color:white}
.pl-head h2{font-size:14px;font-weight:700;margin-bottom:10px}
/* grupos */
.grp-tabs{display:grid;grid-template-columns:1fr 1fr;gap:3px;margin-bottom:8px}
.gt{padding:5px 6px;border-radius:7px;border:none;font-family:'Sora',sans-serif;
  font-size:10px;font-weight:700;cursor:pointer;text-align:center;text-decoration:none;
  transition:all .2s;display:block}
/* tipos */
.tipo-tabs{display:flex;gap:3px;flex-wrap:wrap}
.tt{flex:1;min-width:60px;padding:5px 4px;border-radius:7px;border:none;font-family:'Sora',sans-serif;
  font-size:10px;font-weight:700;cursor:pointer;text-align:center;text-decoration:none;
  transition:all .2s;display:block;line-height:1.3}
/* nivel */
.nv-tabs{display:flex;gap:3px;margin:8px 0 0}
.nt{flex:1;padding:5px 4px;border-radius:7px;border:none;font-family:'Sora',sans-serif;
  font-size:10px;font-weight:700;cursor:pointer;text-align:center;text-decoration:none;
  background:rgba(255,255,255,.15);color:rgba(255,255,255,.8);transition:all .2s}
.nt.a,.nt:hover{background:white;color:var(--vd)}
.pl-body{flex:1;overflow-y:auto;padding:6px 0}
.pl-body::-webkit-scrollbar{width:4px}
.pl-body::-webkit-scrollbar-thumb{background:#d4a0b0;border-radius:2px}
.pli{display:flex;align-items:flex-start;gap:9px;padding:10px 13px;
  border-bottom:1px solid var(--vp);cursor:pointer;border-left:3px solid transparent;transition:all .15s}
.pli:hover{background:#fdf5f7}.pli.act{background:#faeef1;border-left-color:var(--v)}
.pli-n{width:24px;height:24px;border-radius:50%;background:var(--vp);font-size:10px;
  font-weight:800;color:var(--vd);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.pli-q{font-size:12px;font-weight:600;color:var(--ink);line-height:1.4}
.pli-m{font-size:10.5px;color:#9a6070;margin-top:2px;display:flex;gap:5px;flex-wrap:wrap}
.pl-foot{padding:10px;border-top:1px solid var(--vp)}
.empty-pl{padding:28px 14px;text-align:center;color:#c0a0a8;font-size:12.5px}
.empty-pl span{font-size:36px;display:block;margin-bottom:8px}
/* ── RIGHT PANEL ── */
.pr{flex:1;overflow-y:auto;padding:24px 32px;background:var(--mist)}
.pr-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px}
.pr-head h1{font-family:'DM Serif Display',serif;font-size:22px}
.pr-head p{font-size:12.5px;color:#9a6070;margin-top:3px}
.tabs-pr{display:flex;gap:4px;margin-bottom:20px}
.tab-pr{padding:8px 16px;border-radius:9px;border:1.5px solid var(--border);background:white;
  font-family:'Sora',sans-serif;font-size:12.5px;font-weight:700;cursor:pointer;color:#7a5060;transition:all .2s}
.tab-pr.a,.tab-pr:hover{background:var(--v);color:white;border-color:var(--v)}
.panel-section{display:none;animation:fi .25s ease}.panel-section.a{display:block}
@keyframes fi{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}
/* form */
.eform{background:white;border-radius:16px;padding:24px;box-shadow:var(--sh);border:1.5px solid var(--border)}
.efrow3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px}
.efrow2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.efrow1{margin-bottom:14px}
.ef-label{display:block;font-size:11px;font-weight:700;color:var(--vd);
  text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
.ef-label span{color:#e53935}.ef-label em{font-size:10px;color:#9a6070;font-weight:400;text-transform:none;letter-spacing:0}
/* tipo badge */
.tipo-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:18px;
  font-size:11.5px;font-weight:700;color:white;margin-bottom:14px}
/* math toolbar */
.mtb{display:flex;flex-wrap:wrap;gap:2px;padding:5px 7px;background:#fdf0f3;
  border:1.5px solid var(--border);border-bottom:none;border-radius:8px 8px 0 0}
.mtb button{padding:2px 7px;border:1px solid #e8c0cc;border-radius:4px;background:white;
  font-size:11.5px;cursor:pointer;color:var(--vd);font-weight:600;transition:all .15s;font-family:'JetBrains Mono',monospace}
.mtb button:hover{background:var(--v);color:white;border-color:var(--v)}
.mtb .sep{width:1px;background:#e8c0cc;margin:2px 3px}
.mi{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:0 0 8px 8px;
  font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--ink);background:white;outline:none;
  transition:border-color .2s;resize:vertical;min-height:52px}
.mi:focus{border-color:var(--v)}
.mi.plain{border-radius:8px;min-height:auto;resize:none;font-family:'Sora',sans-serif}
/* opciones */
.opts-g{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.opt-w{position:relative}
.opt-l{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:22px;height:22px;
  border-radius:50%;background:var(--vp);color:var(--vd);font-size:11px;font-weight:800;
  display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:1}
.opt-i{width:100%;padding:9px 10px 9px 38px;border:1.5px solid var(--border);border-radius:8px;
  font-family:'JetBrains Mono',monospace;font-size:12.5px;color:var(--ink);background:white;outline:none;transition:all .2s}
.opt-i:focus{border-color:var(--v)}.opt-w.ok .opt-i{border-color:#4caf50;background:#f1f8e9}.opt-w.ok .opt-l{background:#e8f5e9;color:#2e7d32}
/* upload */
.dz{border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;
  cursor:pointer;transition:all .2s;position:relative;background:var(--mist)}
.dz:hover,.dz.drag{border-color:var(--v);background:#faeef1}
.dz input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
/* preview */
.pv-card{background:white;border-radius:14px;padding:20px;box-shadow:var(--sh);
  border:1.5px solid var(--border);margin-top:18px}
.pv-card h3{font-size:13px;font-weight:700;color:var(--vd);margin-bottom:12px;
  padding-bottom:10px;border-bottom:1px solid var(--vp)}
.pv-q{font-size:14.5px;font-weight:600;color:var(--ink);margin-bottom:12px;line-height:1.6}
.pv-opts{display:flex;flex-direction:column;gap:7px}
.pv-opt{display:flex;align-items:center;gap:9px;padding:9px 13px;border-radius:8px;
  border:1.5px solid var(--border);font-size:13px}
.pv-opt.c{border-color:#4caf50;background:#f1f8e9}
.poc{width:24px;height:24px;border-radius:50%;background:var(--vp);font-size:10px;
  font-weight:800;color:var(--vd);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.pv-opt.c .poc{background:#e8f5e9;color:#2e7d32}
/* config pruebas */
.cfg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.cfg-card{background:white;border-radius:14px;padding:18px;box-shadow:var(--sh);border:1.5px solid var(--border)}
.cfg-card h4{font-size:13px;font-weight:700;color:var(--ink);margin-bottom:12px;
  display:flex;align-items:center;gap:6px}
.cfg-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:14px;
  font-size:10px;font-weight:700;color:white;margin-bottom:10px}
.sw{position:relative;width:44px;height:22px;flex-shrink:0}
.sw input{opacity:0;width:0;height:0}
.swsl{position:absolute;inset:0;background:#ccc;border-radius:22px;cursor:pointer;transition:.3s}
.swsl::before{content:'';position:absolute;width:16px;height:16px;bottom:3px;left:3px;
  background:white;border-radius:50%;transition:.3s}
input:checked+.swsl{background:var(--v)}
input:checked+.swsl::before{transform:translateX(22px)}
/* acciones form */
.factions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid var(--vp)}
.ok-m{background:#e8f5e9;border:1.5px solid #a5d6a7;border-radius:9px;padding:10px 16px;
  color:#2e7d32;font-size:13px;font-weight:700;margin-bottom:14px}
.err-m{background:#fde8e8;border:1.5px solid #ef9a9a;border-radius:9px;padding:10px 16px;
  color:#c0392b;font-size:13px;font-weight:700;margin-bottom:14px}
</style>

<div class="ew">
<!-- ── PANEL IZQUIERDO ──────────────────────────────────────── -->
<aside class="pl">
  <div class="pl-head">
    <h2>📚 Banco de preguntas</h2>
    <!-- GRUPOS -->
    <div class="grp-tabs">
      <?php foreach(['4-5'=>'4°-5°','6-7'=>'6°-7°','8-9'=>'8°-9°','10-11'=>'10°-11°'] as $g=>$gl): ?>
      <a href="editor_simulacro.php?nivel=<?=$nivel_sel?>&grupo=<?=$g?>&tipo=<?=$tipo_sel?>"
         class="gt" style="background:<?=$grupo_sel===$g?colorGrupo($g):'rgba(255,255,255,.15)'?>;
         color:<?=$grupo_sel===$g?'white':'rgba(255,255,255,.75)'?>"><?=$gl?></a>
      <?php endforeach ?>
    </div>
    <!-- TIPOS -->
    <div class="tipo-tabs">
      <?php foreach($tipos as $tk=>[$tic,$tnm,$tdesc]): ?>
      <a href="editor_simulacro.php?nivel=<?=$nivel_sel?>&grupo=<?=$grupo_sel?>&tipo=<?=$tk?>"
         class="tt" style="background:<?=$tipo_sel===$tk?colorTipo($tk):'rgba(255,255,255,.15)'?>;
         color:<?=$tipo_sel===$tk?'white':'rgba(255,255,255,.8)'?>"><?=$tic?><br><?=$tnm?></a>
      <?php endforeach ?>
    </div>
    <!-- NIVEL -->
    <div class="nv-tabs">
      <?php foreach(['basico'=>'Básico','medio'=>'Medio','avanzado'=>'Avanzado'] as $nv=>$nl):
        $cnt=contarPreg($pdo,$nv,$grupo_sel,$tipo_sel); ?>
      <a href="editor_simulacro.php?nivel=<?=$nv?>&grupo=<?=$grupo_sel?>&tipo=<?=$tipo_sel?>"
         class="nt <?=$nivel_sel===$nv?'a':''?>"><?=$nl?><br><span style="font-size:9px;opacity:.7"><?=$cnt?></span></a>
      <?php endforeach ?>
    </div>
  </div>

  <div class="pl-body">
    <?php if(empty($lista)): ?>
    <div class="empty-pl"><span>📭</span>Sin preguntas.<br>¡Crea la primera!</div>
    <?php else: foreach($lista as $i=>$p):
      $act=($edit_pregunta&&$edit_pregunta['id']==$p['id'])?'act':''; ?>
    <div class="pli <?=$act?>" onclick="location.href='editor_simulacro.php?nivel=<?=$nivel_sel?>&grupo=<?=$grupo_sel?>&tipo=<?=$tipo_sel?>&edit=<?=$p['id']?>'">
      <div class="pli-n"><?=$i+1?></div>
      <div>
        <div class="pli-q"><?=sanitize(mb_substr(strip_tags($p['pregunta']),0,55))?>...</div>
        <div class="pli-m">
          <span><?=sanitize($p['tema'])?></span>
          <?php if($p['imagen_url']): ?><span style="color:var(--v)">🖼️</span><?php endif ?>
          <?php if(strpos($p['pregunta'],'$')!==false): ?><span style="color:var(--v)">∑</span><?php endif ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif ?>
  </div>

  <div class="pl-foot">
    <a href="editor_simulacro.php?nivel=<?=$nivel_sel?>&grupo=<?=$grupo_sel?>&tipo=<?=$tipo_sel?>"
       class="btn btn-v" style="width:100%;justify-content:center">➕ Nueva pregunta</a>
  </div>
</aside>

<!-- ── PANEL DERECHO ────────────────────────────────────────── -->
<div class="pr">
  <div class="pr-head">
    <div>
      <h1><?=$edit_pregunta?'✏️ Editar pregunta #'.$edit_pregunta['id']:'➕ Nueva pregunta'?></h1>
      <p>
        <span style="display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;color:white;background:<?=colorGrupo($grupo_sel)?>"><?=etiquetaGrupo($grupo_sel)?></span>
        &nbsp;
        <span style="display:inline-flex;align-items:center;gap:5px;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;color:white;background:<?=colorTipo($tipo_sel)?>"><?=etiquetaTipo($tipo_sel)?></span>
        &nbsp; Nivel: <strong><?=ucfirst($nivel_sel)?></strong>
      </p>
    </div>
    <div style="display:flex;gap:8px">
      <a href="editor_simulacro.php?nivel=<?=$nivel_sel?>&grupo=<?=$grupo_sel?>&tipo=<?=$tipo_sel?>" class="btn btn-outline btn-sm">+ Nueva</a>
      <?php if(isAdmin()): ?><a href="admin.php" class="btn btn-outline btn-sm">← Admin</a><?php else: ?><a href="docente.php" class="btn btn-outline btn-sm">← Docente</a><?php endif ?>
    </div>
  </div>

  <?php if($ok): ?><div class="ok-m">✅ <?=sanitize($ok)?></div><?php endif ?>
  <?php if($err): ?><div class="err-m">⚠️ <?=sanitize($err)?></div><?php endif ?>

  <!-- TABS -->
  <div class="tabs-pr">
    <button class="tab-pr a" onclick="showTab('preguntas',this)">❓ Preguntas</button>
    <button class="tab-pr" onclick="showTab('config',this)">⚙️ Configurar pruebas</button>
  </div>

  <!-- ══ TAB: PREGUNTAS ════════════════════════════════════════ -->
  <div id="tab-preguntas" class="panel-section a">
  <form method="POST" enctype="multipart/form-data" id="eform">
    <input type="hidden" name="guardar_pregunta" value="1">
    <input type="hidden" name="pid" value="<?=$edit_pregunta?$edit_pregunta['id']:0?>">
    <input type="hidden" name="img_actual" value="<?=sanitize($edit_pregunta['imagen_url']??'')?>">

    <div class="eform">

      <!-- FILA 1: grupo / tipo / nivel / tema -->
      <div class="efrow3" style="grid-template-columns:1fr 1fr 1fr 1fr">
        <div>
          <label class="ef-label">Grupo de grados <span>*</span></label>
          <select name="grupo_grado" id="sel-grupo" class="fsel">
            <?php foreach(['4-5'=>'4°-5°','6-7'=>'6°-7°','8-9'=>'8°-9°','10-11'=>'10°-11°'] as $g=>$gl): ?>
            <option value="<?=$g?>" <?=($edit_pregunta['grupo_grado']??$grupo_sel)===$g?'selected':''?>><?=$gl?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="ef-label">Tipo de prueba <span>*</span></label>
          <select name="tipo_prueba" id="sel-tipo" class="fsel">
            <?php foreach($tipos as $tk=>[$tic,$tnm,$tdesc]): ?>
            <option value="<?=$tk?>" <?=($edit_pregunta['tipo_prueba']??$tipo_sel)===$tk?'selected':''?>>
              <?=$tic?> <?=$tnm?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
        <div>
          <label class="ef-label">Nivel <span>*</span></label>
          <select name="nivel" class="fsel">
            <option value="basico" <?=$nivel_sel==='basico'?'selected':''?>>Básico</option>
            <option value="medio"  <?=$nivel_sel==='medio'?'selected':''?>>Medio</option>
            <option value="avanzado" <?=$nivel_sel==='avanzado'?'selected':''?>>Avanzado</option>
          </select>
        </div>
        <div>
          <label class="ef-label">Tema <span>*</span></label>
          <input type="text" name="tema" class="fi plain" list="temas-list"
            value="<?=sanitize($edit_pregunta['tema']??'Aritmética')?>" placeholder="Ej: Álgebra">
          <datalist id="temas-list">
            <option>Aritmética</option><option>Álgebra</option><option>Geometría</option>
            <option>Combinatoria</option><option>Logaritmos</option><option>Fracciones</option>
            <option>Ecuaciones</option><option>Potenciación</option><option>MCD-MCM</option>
            <option>Números</option><option>Porcentajes</option><option>Estadística</option>
          </datalist>
        </div>
      </div>

      <!-- ENUNCIADO -->
      <div class="efrow1">
        <label class="ef-label">Enunciado <span>*</span> <em>· Use $…$ para LaTeX inline, $$…$$ para bloque</em></label>
        <div class="mtb" id="tb-preg">
          <?php
          $btns=[['$\\frac{a}{b}$','a/b'],['$x^{2}$','x²'],['$x_{n}$','xₙ'],['$\\sqrt{x}$','√x'],
                 ['$\\sqrt[n]{x}$','ⁿ√'],['$\\sum_{i=1}^{n}$','Σ'],['$\\int_{a}^{b}$','∫'],
                 ['$\\pi$','π'],['$\\infty$','∞'],['$\\theta$','θ'],['$\\alpha$','α'],
                 ['$\\beta$','β'],['$\\leq$','≤'],['$\\geq$','≥'],['$\\neq$','≠'],
                 ['$\\approx$','≈'],['$\\binom{n}{k}$','C(n,k)'],['$\\log_{b}(x)$','log'],
                 ['$\\lim_{x\\to a}$','lim'],['$\\therefore$','∴']];
          foreach($btns as [$v,$l]): ?>
          <button type="button" onclick="ins('ta-preg','<?=$v?>')" title="<?=$v?>"><?=$l?></button>
          <?php endforeach ?>
          <div class="sep"></div>
          <button type="button" onclick="prvMath()" style="background:var(--vp);color:var(--vd)">👁️ Preview</button>
        </div>
        <textarea id="ta-preg" name="pregunta" class="mi" rows="3"
          oninput="syncPrev()" placeholder="Escribe el enunciado... Ej: ¿Cuánto es $\frac{3}{4} + \frac{1}{2}$?"><?=htmlspecialchars($edit_pregunta['pregunta']??'')?></textarea>
        <div id="pv-box" style="display:none;margin-top:8px;padding:10px 14px;background:#fdf0f3;border-radius:8px;border:1.5px solid var(--border);font-size:14px;line-height:1.7">
          <div style="font-size:10px;font-weight:700;color:var(--vd);margin-bottom:5px;text-transform:uppercase">Vista previa</div>
          <div id="pv-content"></div>
        </div>
      </div>

      <!-- IMAGEN -->
      <div class="efrow1">
        <label class="ef-label">Imagen <em>(opcional — JPG, PNG, SVG, GIF)</em></label>
        <?php if(!empty($edit_pregunta['imagen_url'])): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f1f8e9;border:1.5px solid #a5d6a7;border-radius:9px;margin-bottom:8px">
          <img src="<?=sanitize(SITE_URL.'/'.$edit_pregunta['imagen_url'])?>" style="height:50px;object-fit:contain;border-radius:5px;border:1px solid #ccc">
          <span style="flex:1;font-size:12px;color:#2e7d32;font-weight:600">📎 Imagen guardada</span>
          <form method="POST" style="margin:0">
            <input type="hidden" name="quitar_imagen" value="1">
            <input type="hidden" name="pid" value="<?=$edit_pregunta['id']?>">
            <button type="submit" class="btn btn-red btn-sm" onclick="return confirm('¿Quitar imagen?')">🗑️ Quitar</button>
          </form>
        </div>
        <?php endif ?>
        <div class="dz" id="dz">
          <input type="file" name="imagen" id="img-f" accept="image/*" onchange="prvImg(this)">
          <div>🖼️</div>
          <div style="font-size:13px;font-weight:600;color:var(--vd)">Arrastra o haz clic</div>
          <div id="img-fname" style="font-size:11.5px;color:var(--v);font-weight:700;margin-top:4px"></div>
        </div>
        <img id="img-pv" src="" style="display:none;max-height:140px;max-width:100%;border-radius:8px;margin-top:8px;border:1.5px solid var(--border)">
      </div>

      <!-- OPCIONES -->
      <div class="efrow1">
        <label class="ef-label">Opciones <span>*</span> <em>· Doble clic en una opción para marcarla como correcta</em></label>
        <div class="opts-g" id="opts-g">
          <?php $letras=['A','B','C','D']; $campos=['op1','op2','op3','op4']; $cv=sanitize($edit_pregunta['correcta']??'');
          foreach($campos as $i=>$campo):
            $val=sanitize($edit_pregunta[$campo]??''); $esCor=($val&&$val===$cv); ?>
          <div class="opt-w <?=$esCor?'ok':''?>" id="ow-<?=$i?>">
            <span class="opt-l"><?=$letras[$i]?></span>
            <input type="text" name="<?=$campo?>" id="oi-<?=$i?>" class="opt-i"
              placeholder="Opción <?=$letras[$i]?><?=$i>=2?' (opcional)':' *'?>"
              value="<?=$val?>" oninput="syncPrevOpt()">
          </div>
          <?php endforeach ?>
        </div>
      </div>

      <!-- RESPUESTA CORRECTA -->
      <div class="efrow1">
        <label class="ef-label">Respuesta correcta <span>*</span> <em>· Texto exacto de la opción correcta</em></label>
        <div class="mtb">
          <button type="button" onclick="ins('ta-cor','$\\frac{a}{b}$')">a/b</button>
          <button type="button" onclick="ins('ta-cor','$x^{2}$')">x²</button>
          <button type="button" onclick="ins('ta-cor','$\\sqrt{x}$')">√x</button>
          <button type="button" onclick="ins('ta-cor','$\\pi$')">π</button>
          <div class="sep"></div>
          <span style="font-size:10px;color:#9a6070;padding:0 4px">o doble clic en la opción ↑</span>
        </div>
        <input type="text" id="ta-cor" name="correcta" class="mi plain" style="border-radius:0 0 8px 8px;padding:10px 12px"
          value="<?=$cv?>" placeholder="Escribe la respuesta correcta exactamente igual a la opción">
        <div id="cor-hint" style="font-size:11px;color:#2e7d32;font-weight:600;margin-top:4px">
          <?=$cv?'✅ Respuesta marcada: <strong>'.$cv.'</strong>':''?>
        </div>
      </div>

      <!-- EXPLICACIÓN -->
      <div class="efrow1">
        <label class="ef-label">Explicación / solución <em>(se muestra al revisar)</em></label>
        <div class="mtb">
          <button type="button" onclick="ins('ta-exp','$\\frac{a}{b}$')">a/b</button>
          <button type="button" onclick="ins('ta-exp','$x^{2}$')">x²</button>
          <button type="button" onclick="ins('ta-exp','$\\sqrt{x}$')">√x</button>
          <button type="button" onclick="ins('ta-exp','$\\therefore$')">∴</button>
        </div>
        <textarea id="ta-exp" name="explicacion" class="mi" rows="2"
          placeholder="Explica cómo llegar a la respuesta..."><?=htmlspecialchars($edit_pregunta['explicacion']??'')?></textarea>
      </div>

      <!-- ACCIONES -->
      <div class="factions">
        <button type="submit" class="btn btn-v" style="font-size:13.5px;padding:11px 26px">
          <?=$edit_pregunta?'💾 Guardar cambios':'➕ Crear pregunta'?>
        </button>
        <?php if($edit_pregunta): ?>
        <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar permanentemente?')">
          <input type="hidden" name="eliminar_pregunta" value="1">
          <input type="hidden" name="pid" value="<?=$edit_pregunta['id']?>">
          <button type="submit" class="btn btn-red">🗑️ Eliminar</button>
        </form>
        <?php endif ?>
        <a href="simulacro.php?nivel=<?=$nivel_sel?>&grupo=<?=$grupo_sel?>&tipo=<?=$tipo_sel?>" target="_blank" class="btn btn-outline btn-sm" style="margin-left:auto">👁️ Ver simulacro</a>
      </div>
    </div><!-- /eform -->

    <!-- LIVE PREVIEW -->
    <div class="pv-card">
      <h3>👁️ Vista previa en vivo</h3>
      <div class="pv-q math-render" id="pvq">El enunciado aparecerá aquí...</div>
      <img id="pvimg" src="" style="display:none;max-height:160px;max-width:100%;border-radius:7px;margin-bottom:10px;border:1.5px solid var(--border)">
      <div class="pv-opts" id="pvopts">
        <?php foreach($letras as $i=>$l): ?>
        <div class="pv-opt" id="pvopt-<?=$i?>"><span class="poc"><?=$l?></span><span class="math-render" id="pvot-<?=$i?>">Opción <?=$l?></span></div>
        <?php endforeach ?>
      </div>
    </div>
  </form>
  </div><!-- /tab-preguntas -->

  <!-- ══ TAB: CONFIGURACIÓN DE PRUEBAS ════════════════════════ -->
  <div id="tab-config" class="panel-section">
    <div style="margin-bottom:20px">
      <h2 style="font-family:'DM Serif Display',serif;font-size:20px;color:var(--ink);margin-bottom:6px">⚙️ Configuración de pruebas</h2>
      <p style="font-size:13px;color:#9a6070">Controla el tiempo límite, número de preguntas y disponibilidad de cada prueba por grupo.</p>
    </div>
    <?php foreach(['4-5','6-7','8-9','10-11'] as $gg): ?>
    <div style="margin-bottom:24px">
      <h3 style="font-size:13px;font-weight:700;color:white;background:<?=colorGrupo($gg)?>;
        display:inline-block;padding:5px 16px;border-radius:20px;margin-bottom:12px">
        🎓 <?=etiquetaGrupo($gg)?>
      </h3>
      <div class="cfg-grid">
        <?php foreach($tipos as $tk=>[$tic,$tnm,$tdesc]):
          $k=$gg.'_'.$tk; $cfg=$cfg_map[$k]??['tiempo_limite_min'=>null,'num_preguntas'=>10,'max_intentos'=>0,'habilitada'=>0,'instrucciones'=>''];
          $total_preg=0;
          foreach(['basico','medio','avanzado'] as $nv){ $total_preg+=contarPreg($pdo,$nv,$gg,$tk); }
        ?>
        <div class="cfg-card">
          <h4><?=$tic?> <?=$tnm?></h4>
          <div class="cfg-badge" style="background:<?=colorTipo($tk)?>"><?=$tdesc?></div>
          <div style="font-size:11.5px;color:#9a6070;margin-bottom:12px">📋 <?=$total_preg?> pregunta(s) cargadas</div>
          <form method="POST">
            <input type="hidden" name="guardar_config" value="1">
            <input type="hidden" name="tipo_cfg" value="<?=$tk?>">
            <input type="hidden" name="grupo_cfg" value="<?=$gg?>">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:10px">
              <div>
                <label class="ef-label" style="font-size:10px">⏱️ Tiempo (min)</label>
                <input type="number" name="tiempo_limite" class="fi" min="0" max="300"
                  placeholder="Sin límite"
                  value="<?=$cfg['tiempo_limite_min']!==null?$cfg['tiempo_limite_min']:''?>"
                  style="padding:7px 9px;font-size:13px">
                <div style="font-size:10px;color:#9a6070;margin-top:2px">Vacío = sin límite</div>
              </div>
              <div>
                <label class="ef-label" style="font-size:10px">❓ Nº preguntas</label>
                <input type="number" name="num_preguntas" class="fi" min="1" max="50"
                  value="<?=intval($cfg['num_preguntas']??10)?>"
                  style="padding:7px 9px;font-size:13px">
              </div>
              <div>
                <label class="ef-label" style="font-size:10px">🔁 Máx. intentos</label>
                <input type="number" name="max_intentos" class="fi" min="0" max="99"
                  placeholder="0"
                  value="<?=intval($cfg['max_intentos']??0)?>"
                  style="padding:7px 9px;font-size:13px">
                <div style="font-size:10px;color:#9a6070;margin-top:2px">0 = ilimitado</div>
              </div>
            </div>
            <div style="margin-bottom:10px">
              <label class="ef-label" style="font-size:10px">📝 Instrucciones (opcional)</label>
              <textarea name="instrucciones" class="fi" rows="2" style="font-size:12px;padding:7px 9px"
                placeholder="Instrucciones para los estudiantes..."><?=sanitize($cfg['instrucciones']??'')?></textarea>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between">
              <div style="display:flex;align-items:center;gap:8px">
                <label class="sw">
                  <input type="checkbox" name="habilitada" value="1" <?=$cfg['habilitada']?'checked':''?>>
                  <span class="swsl"></span>
                </label>
                <span style="font-size:12px;font-weight:700;color:<?=$cfg['habilitada']?'var(--green)':'#9a6070'?>">
                  <?=$cfg['habilitada']?'✅ Habilitada':'⛔ Deshabilitada'?>
                </span>
              </div>
              <button type="submit" class="btn btn-v btn-sm">💾</button>
            </div>
          </form>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endforeach ?>
  </div><!-- /tab-config -->

</div><!-- /pr -->
</div><!-- /ew -->

<script>
function ins(id,txt){const el=document.getElementById(id);if(!el)return;const s=el.selectionStart,e=el.selectionEnd;el.value=el.value.slice(0,s)+txt+el.value.slice(e);el.selectionStart=el.selectionEnd=s+txt.length;el.focus();syncPrev();updatePv();}

function prvMath(){const b=document.getElementById('pv-box'),c=document.getElementById('pv-content');b.style.display='block';c.innerHTML=document.getElementById('ta-preg').value;if(window.MathJax)MathJax.typesetPromise([c]).catch(()=>{});}
function syncPrev(){const b=document.getElementById('pv-box');if(b.style.display==='block')prvMath();updatePv();}

function updatePv(){
  const q=document.getElementById('ta-preg')?.value||'';
  const pq=document.getElementById('pvq');if(pq){pq.innerHTML=q||'El enunciado aparecerá aquí...';}
  const cor=document.getElementById('ta-cor')?.value||'';
  ['oi-0','oi-1','oi-2','oi-3'].forEach((id,i)=>{
    const v=document.getElementById(id)?.value||`Opción ${['A','B','C','D'][i]}`;
    const el=document.getElementById('pvot-'+i);if(el)el.innerHTML=v;
    const opt=document.getElementById('pvopt-'+i);if(opt)opt.classList.toggle('c',cor&&v===cor);
  });
  if(window.MathJax){const pvc=document.getElementById('pv-card')||document.querySelector('.pv-card');if(pvc)MathJax.typesetPromise([pvc]).catch(()=>{});}
}
function syncPrevOpt(){updatePv();}

document.getElementById('ta-cor')?.addEventListener('input',function(){
  const v=this.value;
  document.getElementById('cor-hint').innerHTML=v?`✅ Respuesta marcada: <strong>${v}</strong>`:'';
  document.querySelectorAll('.opt-w').forEach(o=>o.classList.remove('ok'));
  ['oi-0','oi-1','oi-2','oi-3'].forEach((id,i)=>{if(document.getElementById(id)?.value===v)document.getElementById('ow-'+i)?.classList.add('ok');});
  updatePv();
});

// Doble clic en opción = marcar como correcta
['oi-0','oi-1','oi-2','oi-3'].forEach((id,i)=>{
  document.getElementById(id)?.addEventListener('dblclick',function(){
    const v=this.value.trim();if(!v)return;
    document.getElementById('ta-cor').value=v;
    document.getElementById('cor-hint').innerHTML=`✅ Respuesta marcada: <strong>${v}</strong>`;
    document.querySelectorAll('.opt-w').forEach(o=>o.classList.remove('ok'));
    document.getElementById('ow-'+i)?.classList.add('ok');
    updatePv();
  });
});

// Drag & drop imagen
const dz=document.getElementById('dz');
if(dz){['dragenter','dragover'].forEach(e=>dz.addEventListener(e,()=>dz.classList.add('drag')));['dragleave','drop'].forEach(e=>dz.addEventListener(e,()=>dz.classList.remove('drag')));}
function prvImg(inp){const f=inp.files[0];if(!f)return;document.getElementById('img-fname').textContent='📎 '+f.name;const r=new FileReader();r.onload=e=>{const i=document.getElementById('img-pv'),pi=document.getElementById('pvimg');i.src=e.target.result;i.style.display='block';if(pi){pi.src=e.target.result;pi.style.display='block';}};r.readAsDataURL(f);}

function showTab(name,btn){
  document.querySelectorAll('.panel-section').forEach(p=>p.classList.remove('a'));
  document.querySelectorAll('.tab-pr').forEach(b=>b.classList.remove('a'));
  document.getElementById('tab-'+name).classList.add('a');btn.classList.add('a');
}

document.addEventListener('DOMContentLoaded',()=>{
  updatePv();
  <?php if(!empty($edit_pregunta['imagen_url'])): ?>
  const pi=document.getElementById('pvimg');if(pi){pi.src='<?=sanitize(SITE_URL.'/'.$edit_pregunta['imagen_url'])?>'; pi.style.display='block';}
  <?php endif ?>
  const cv='<?=addslashes($cv)?>';
  if(cv){['oi-0','oi-1','oi-2','oi-3'].forEach((id,i)=>{if(document.getElementById(id)?.value===cv)document.getElementById('ow-'+i)?.classList.add('ok');});}
});
<?php if($ok): ?>window.addEventListener('load',()=>st('<?=addslashes(sanitize($ok))?>'));<?php endif ?>
</script>
<?php require_once 'includes/footer.php'; ?>
