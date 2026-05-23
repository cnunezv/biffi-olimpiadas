<?php
require_once 'includes/config.php';
requireLogin();
$ok=''; $err='';

// Subir archivo (docentes y admin)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['subir']) && isDocente()){
    $titulo=trim($_POST['titulo']??'');
    $desc=trim($_POST['descripcion']??'');
    $tipo=$_POST['tipo']??'pdf';

    // Asegurar que la carpeta de uploads exista
    if(!is_dir(UPLOAD_PDF)) mkdir(UPLOAD_PDF,0755,true);

    if($titulo && isset($_FILES['archivo']) && $_FILES['archivo']['error']===0){
        $ext=strtolower(pathinfo($_FILES['archivo']['name'],PATHINFO_EXTENSION));
        $allowed=['pdf','zip','png','jpg','jpeg','gif','mp4','docx','pptx','xlsx'];
        if(in_array($ext,$allowed)){
            $fname=uniqid('biffi_').'.'.$ext;
            $dest=UPLOAD_PDF.$fname;
            if(move_uploaded_file($_FILES['archivo']['tmp_name'],$dest)){
                // Determinar tipo automáticamente por extensión si es 'pdf' genérico
                $tipo_auto=['pdf'=>'pdf','zip'=>'zip','mp4'=>'video',
                            'docx'=>'enlace','pptx'=>'enlace','xlsx'=>'enlace',
                            'png'=>'imagen','jpg'=>'imagen','jpeg'=>'imagen','gif'=>'imagen'][$ext]??'pdf';
                if($_POST['tipo']==='pdf') $tipo=$tipo_auto;
                $pdo->prepare("INSERT INTO recursos(titulo,descripcion,tipo,archivo,subido_por,visible) VALUES(?,?,?,?,?,1)")
                    ->execute([$titulo,$desc,$tipo,'uploads/pdfs/'.$fname,$_SESSION['user_id']]);
                $ok='Archivo "'.$_FILES['archivo']['name'].'" subido correctamente ✅';
            } else {
                $err='Error al guardar el archivo. Verifica permisos de la carpeta uploads/pdfs/';
            }
        } else $err='Tipo de archivo no permitido. Usa: PDF, ZIP, imágenes, DOC, PPT.';
    } elseif($titulo && isset($_POST['url']) && trim($_POST['url'])){
        $pdo->prepare("INSERT INTO recursos(titulo,descripcion,tipo,archivo,subido_por,visible) VALUES(?,?,?,?,?,1)")
            ->execute([$titulo,$desc,'enlace',trim($_POST['url']),$_SESSION['user_id']]);
        $ok='Enlace agregado correctamente ✅';
    } else {
        if(!$titulo) $err='El título es obligatorio.';
        else $err='Selecciona un archivo o ingresa una URL.';
    }
}

// Eliminar recurso
if(isset($_GET['del']) && is_numeric($_GET['del']) && isDocente()){
    $rid=intval($_GET['del']);
    $r=$pdo->prepare("SELECT archivo FROM recursos WHERE id=?");
    $r->execute([$rid]); $rec=$r->fetch();
    if($rec && file_exists(__DIR__.'/'.$rec['archivo'])) @unlink(__DIR__.'/'.$rec['archivo']);
    $pdo->prepare("DELETE FROM recursos WHERE id=?")->execute([$rid]);
    header('Location: biblioteca.php'); exit;
}

// Contar descarga
if(isset($_GET['dl']) && is_numeric($_GET['dl'])){
    $pdo->prepare("UPDATE recursos SET descargas=descargas+1 WHERE id=?")->execute([intval($_GET['dl'])]);
}

// Buscar
$buscar=trim($_GET['q']??'');
if($buscar){
    $recursos=$pdo->prepare("SELECT r.*,u.nombre,u.apellido FROM recursos r LEFT JOIN usuarios u ON u.id=r.subido_por WHERE r.visible=1 AND (r.titulo LIKE ? OR r.descripcion LIKE ?) ORDER BY r.creado_en DESC");
    $recursos->execute(["%$buscar%","%$buscar%"]);
} else {
    $recursos=$pdo->query("SELECT r.*,u.nombre,u.apellido FROM recursos r LEFT JOIN usuarios u ON u.id=r.subido_por WHERE r.visible=1 ORDER BY r.creado_en DESC");
}
$lista=$recursos->fetchAll();

$page_title='Biblioteca — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
.bib-wrap{max-width:1100px;margin:0 auto;padding:28px 24px}
.bib-head{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:24px}
.bib-head h1{font-family:'DM Serif Display',serif;font-size:26px}
.bib-head p{font-size:13px;color:#9a6070;margin-top:3px}
.search-bar{display:flex;gap:10px;margin-bottom:22px}
.search-bar input{flex:1;padding:10px 16px;border:1.5px solid var(--border);border-radius:10px;
  font-family:'Sora',sans-serif;font-size:13px;outline:none}
.search-bar input:focus{border-color:var(--v)}

.upload-card{background:white;border-radius:16px;padding:22px;margin-bottom:24px;
  box-shadow:var(--sh);border:2px dashed var(--vl);display:none}
.upload-card.show{display:block}
.upload-card h3{font-size:15px;font-weight:700;color:var(--vd);margin-bottom:16px}
.tab-tipo{display:flex;gap:8px;margin-bottom:16px}
.tt{padding:7px 16px;border-radius:8px;border:1.5px solid var(--border);
  font-size:12.5px;font-weight:700;cursor:pointer;background:white;color:#9a6070;transition:all .2s}
.tt.active{background:var(--v);color:white;border-color:var(--v)}
.drop-zone{border:2px dashed var(--border);border-radius:12px;padding:32px;text-align:center;
  cursor:pointer;transition:all .2s;position:relative;background:var(--mist)}
.drop-zone:hover,.drop-zone.drag{border-color:var(--v);background:#faeef1}
.drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.drop-icon{font-size:38px;margin-bottom:8px}
.drop-text{font-size:13.5px;font-weight:600;color:var(--vd)}
.drop-sub{font-size:12px;color:#9a6070;margin-top:4px}
#file-name{font-size:12px;color:var(--v);font-weight:700;margin-top:8px}

.grid-lib{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.rc{background:white;border-radius:14px;padding:18px;box-shadow:var(--sh);
  border:1.5px solid var(--border);transition:all .25s;display:flex;flex-direction:column;gap:10px}
.rc:hover{transform:translateY(-3px);box-shadow:var(--shh);border-color:var(--vl)}
.rc-icon-row{display:flex;align-items:center;justify-content:space-between}
.rc-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px}
.rc-type{font-size:10px;font-weight:700;padding:3px 8px;border-radius:16px}
.rc-title{font-size:14px;font-weight:700;color:var(--ink);line-height:1.4}
.rc-desc{font-size:12px;color:#9a6070;line-height:1.5;flex:1}
.rc-meta{font-size:11px;color:#bbb;display:flex;justify-content:space-between}
.rc-actions{display:flex;gap:8px}
.empty-lib{text-align:center;padding:60px;color:#c0a0a8}
.empty-lib span{font-size:52px;display:block;margin-bottom:12px}

.ok-msg{background:#e8f5e9;border:1.5px solid #a5d6a7;border-radius:10px;
  padding:12px 18px;color:#2e7d32;font-size:13px;font-weight:700;margin-bottom:18px}
.err-msg{background:#fde8e8;border:1.5px solid #ef9a9a;border-radius:10px;
  padding:12px 18px;color:#c0392b;font-size:13px;font-weight:700;margin-bottom:18px}
</style>

<div class="bib-wrap">
  <div class="bib-head">
    <div>
      <h1>📚 Biblioteca</h1>
      <p>Material de estudio, PDFs y recursos para las olimpiadas</p>
    </div>
    <?php if(isDocente()): ?>
    <button class="btn btn-v" onclick="toggleUpload()">📤 Subir material</button>
    <?php endif ?>
  </div>

  <?php if($ok): ?><div class="ok-msg"><?=$ok?></div><?php endif ?>
  <?php if($err): ?><div class="err-msg">⚠️ <?=$err?></div><?php endif ?>

  <!-- PANEL SUBIR -->
  <?php if(isDocente()): ?>
  <div class="upload-card" id="upload-panel">
    <h3>📤 Subir nuevo material</h3>
    <div class="tab-tipo">
      <button type="button" class="tt active" onclick="setTab('file',this)">📁 Archivo</button>
      <button type="button" class="tt" onclick="setTab('link',this)">🔗 Enlace</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="subir" value="1">
      <div class="form-row" style="margin-bottom:14px">
        <div class="fg">
          <label class="fl">Título *</label>
          <input type="text" name="titulo" class="fi" required placeholder="Ej: Guía de Álgebra">
        </div>
        <div class="fg">
          <label class="fl">Tipo</label>
          <select name="tipo" class="fsel">
            <option value="pdf">PDF</option><option value="zip">ZIP</option>
            <option value="imagen">Imagen</option><option value="video">Video/Enlace</option>
            <option value="enlace">Enlace web</option>
          </select>
        </div>
      </div>
      <div class="fg" style="margin-bottom:14px">
        <label class="fl">Descripción</label>
        <input type="text" name="descripcion" class="fi" placeholder="Breve descripción del contenido">
      </div>

      <div id="tab-file">
        <div class="drop-zone" id="drop-zone">
          <input type="file" name="archivo" id="file-input" accept=".pdf,.zip,.png,.jpg,.jpeg,.gif,.mp4"
            onchange="document.getElementById('file-name').textContent=this.files[0]?.name||''">
          <div class="drop-icon">📂</div>
          <div class="drop-text">Arrastra tu archivo aquí o haz clic</div>
          <div class="drop-sub">PDF, ZIP, imágenes — máx 20MB</div>
          <div id="file-name"></div>
        </div>
      </div>

      <div id="tab-link" style="display:none">
        <div class="fg">
          <label class="fl">URL del recurso</label>
          <input type="text" name="url" class="fi" placeholder="https://...">
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:16px">
        <button type="submit" class="btn btn-v">📤 Subir</button>
        <button type="button" class="btn btn-outline" onclick="toggleUpload()">Cancelar</button>
      </div>
    </form>
  </div>
  <?php endif ?>

  <!-- BUSCADOR -->
  <form class="search-bar" method="GET">
    <input type="text" name="q" placeholder="🔍  Buscar en la biblioteca..." value="<?=sanitize($buscar)?>">
    <button type="submit" class="btn btn-v">Buscar</button>
    <?php if($buscar): ?><a href="biblioteca.php" class="btn btn-outline">✕ Limpiar</a><?php endif ?>
  </form>

  <!-- LISTADO -->
  <?php
  $iconos=['pdf'=>['📄','#fde8e8','b-red'],'zip'=>['📦','#fff3cd','b-gold'],
    'video'=>['🎥','#e3f2fd','b-blue'],'enlace'=>['🔗','#e8f5e9','b-g'],'imagen'=>['🖼️','#f5e8ff','b-v']];
  if(empty($lista)):
  ?>
  <div class="empty-lib"><span>📭</span>
    <p><?=$buscar?"No se encontraron resultados para \"$buscar\".":"La biblioteca está vacía. ¡Sube el primer recurso!"?></p>
  </div>
  <?php else: ?>
  <div class="grid-lib">
    <?php foreach($lista as $r):
      [$ico,$bg,$bc]=$iconos[$r['tipo']]??['📄','#fde8e8','b-red'];
      $url=str_starts_with($r['archivo'],'http')?$r['archivo']:SITE_URL.'/'.$r['archivo'];
      $fecha=date('d/m/Y',strtotime($r['creado_en']));
    ?>
    <div class="rc">
      <div class="rc-icon-row">
        <div class="rc-icon" style="background:<?=$bg?>"><?=$ico?></div>
        <span class="badge <?=$bc?> rc-type"><?=strtoupper($r['tipo'])?></span>
      </div>
      <div class="rc-title"><?=sanitize($r['titulo'])?></div>
      <?php if($r['descripcion']): ?>
      <div class="rc-desc"><?=sanitize($r['descripcion'])?></div>
      <?php endif ?>
      <div class="rc-meta">
        <span>📅 <?=$fecha?></span>
        <span>⬇️ <?=$r['descargas']?> descargas</span>
      </div>
      <?php if($r['nombre']): ?>
      <div style="font-size:11px;color:#9a6070">👤 <?=sanitize($r['nombre'].' '.$r['apellido'])?></div>
      <?php endif ?>
      <div class="rc-actions">
        <a href="<?=$url?>" target="_blank" onclick="fetch('biblioteca.php?dl=<?=$r['id']?>')"
           class="btn btn-v btn-sm">⬇️ Descargar</a>
        <?php if(isDocente()): ?>
        <a href="biblioteca.php?del=<?=$r['id']?>" class="btn btn-red btn-sm"
           onclick="return confirm('¿Eliminar este recurso?')">🗑️</a>
        <?php endif ?>
      </div>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>
</div>

<script>
function toggleUpload(){
  const p=document.getElementById('upload-panel');
  p.classList.toggle('show');
  if(p.classList.contains('show')) p.scrollIntoView({behavior:'smooth'});
}
let tabActual='file';
function setTab(tab,btn){
  tabActual=tab;
  document.getElementById('tab-file').style.display=tab==='file'?'block':'none';
  document.getElementById('tab-link').style.display=tab==='link'?'block':'none';
  document.querySelectorAll('.tt').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}
// Drag & drop visual
const dz=document.getElementById('drop-zone');
if(dz){
  ['dragenter','dragover'].forEach(e=>dz.addEventListener(e,()=>dz.classList.add('drag')));
  ['dragleave','drop'].forEach(e=>dz.addEventListener(e,()=>dz.classList.remove('drag')));
}
<?php if($ok): ?>window.onload=()=>st('<?=addslashes($ok)?>');<?php endif ?>
</script>
<?php require_once 'includes/footer.php'; ?>
