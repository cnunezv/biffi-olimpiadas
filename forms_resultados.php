<?php
require_once 'includes/config.php';
requireDocente();

$ok = ''; $err = '';
$form_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;

// ── ACCIONES ────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'){

    if(isset($_POST['guardar_form'])){
        $id    = intval($_POST['fid']??0);
        $tit   = trim($_POST['titulo']??'');
        $desc  = trim($_POST['descripcion']??'');
        $furl  = trim($_POST['form_url']??'');
        $surl  = trim($_POST['sheet_csv_url']??'');
        $tipo  = $_POST['tipo_prueba']??'evaluacion';
        $grupo = $_POST['grupo_grado']??'todos';
        $hab   = intval($_POST['habilitada']??1);
        $tl    = $_POST['tiempo_limite_min']!==''?intval($_POST['tiempo_limite_min']):null;
        $fi_d = $_POST['fecha_inicio_d']??''; $fi_t = $_POST['fecha_inicio_t']??'00:00';
        $fc_d = $_POST['fecha_cierre_d']??''; $fc_t = $_POST['fecha_cierre_t']??'23:59';
        $fi   = $fi_d ? $fi_d.' '.$fi_t.':00' : null;
        $fc   = $fc_d ? $fc_d.' '.$fc_t.':00' : null;

        if(!$tit || !$furl){ $err='El título y la URL del formulario son obligatorios.'; }
        else {
            // Normalizar URL de Forms
            $es_google = str_contains($furl,'forms.google.com') || str_contains($furl,'forms.gle') || str_contains($furl,'docs.google.com/forms');
            $es_microsoft = str_contains($furl,'forms.office.com') || str_contains($furl,'forms.microsoft.com');
            if(!$es_google && !$es_microsoft)
                $err='La URL no parece ser ni de Google Forms ni de Microsoft Forms.';
            else {
                if($id>0){
                    $pdo->prepare("UPDATE forms_google SET titulo=?,descripcion=?,form_url=?,
                        sheet_csv_url=?,tipo_prueba=?,grupo_grado=?,habilitada=?,
                        tiempo_limite_min=?,fecha_inicio=?,fecha_cierre=? WHERE id=?")
                        ->execute([$tit,$desc,$furl,$surl?:null,$tipo,$grupo,$hab,$tl,$fi,$fc,$id]);
                    $ok='Formulario actualizado ✅';
                } else {
                    $pdo->prepare("INSERT INTO forms_google(titulo,descripcion,form_url,sheet_csv_url,
                        tipo_prueba,grupo_grado,habilitada,tiempo_limite_min,fecha_inicio,fecha_cierre,creado_por)
                        VALUES(?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$tit,$desc,$furl,$surl?:null,$tipo,$grupo,$hab,$tl,$fi,$fc,$_SESSION['user_id']]);
                    $ok='Formulario agregado ✅'; $form_id=0;
                }
            }
        }
    }

    if(isset($_POST['eliminar_form'])){
        $pdo->prepare("DELETE FROM forms_google WHERE id=?")->execute([intval($_POST['fid'])]);
        $ok='Formulario eliminado ✅'; $form_id=0;
        header('Location: forms_resultados.php'); exit;
    }
}

// Cargar datos
$todos_forms = $pdo->query("SELECT f.*,u.nombre,u.apellido FROM forms_google f
  LEFT JOIN usuarios u ON u.id=f.creado_por ORDER BY f.creado_en DESC")->fetchAll();

$form_edit = null;
if($form_id){
    $se=$pdo->prepare("SELECT * FROM forms_google WHERE id=?");
    $se->execute([$form_id]); $form_edit=$se->fetch();
}

// Leer respuestas desde Google Sheets CSV (si configurado)
$respuestas = []; $col_headers = []; $sheet_error = '';
if($form_edit && $form_edit['sheet_csv_url']){
    $csv_url = trim($form_edit['sheet_csv_url']);
    // Normalizar: si es URL de Google Sheets normal, convertir a CSV export
    if(preg_match('/spreadsheets\/d\/([a-zA-Z0-9_-]+)/',$csv_url,$m)){
        $sid=$m[1];
        // Obtener gid si está presente
        preg_match('/gid=(\d+)/',$csv_url,$gm);
        $gid=$gm[1]??0;
        $csv_url="https://docs.google.com/spreadsheets/d/{$sid}/export?format=csv&gid={$gid}";
    }
    $ctx = stream_context_create(['http'=>[
        'method'=>'GET',
        'header'=>"User-Agent: Mozilla/5.0\r\n",
        'timeout'=>8,
        'follow_location'=>1
    ]]);
    $raw = @file_get_contents($csv_url, false, $ctx);
    if($raw===false){
        $sheet_error='No se pudo conectar a Google Sheets. Verifica que la hoja esté compartida como "Cualquier persona con el enlace puede ver".';
    } else {
        $lines = array_filter(explode("\n", $raw));
        $is_first = true;
        foreach($lines as $line){
            $row = str_getcsv($line);
            if($is_first){ $col_headers=$row; $is_first=false; }
            else $respuestas[]=$row;
        }
    }
}

$tipos_ext = tiposPrueba() + ['taller'=>['📝','Taller',''],'evaluacion'=>['📋','Evaluación','']];
$page_title = 'Formularios Externos — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
.gfw { max-width:1100px; margin:0 auto; padding:28px 24px; }
.gf-head { margin-bottom:22px; }
.gf-head h1 { font-family:'DM Serif Display',serif; font-size:26px; }
.gf-head p  { font-size:13px; color:#9a6070; margin-top:3px; }
.gf-grid { display:grid; grid-template-columns:360px 1fr; gap:22px; align-items:start; }
@media(max-width:800px){ .gf-grid{grid-template-columns:1fr;} }

/* Lista de forms */
.form-card {
  background:white; border-radius:14px; padding:16px;
  box-shadow:var(--sh); border:1.5px solid var(--border);
  cursor:pointer; transition:all .2s; margin-bottom:10px;
  border-left:4px solid var(--border); text-decoration:none; display:block; color:inherit;
}
.form-card:hover { border-left-color:var(--v); box-shadow:var(--shh); }
.form-card.act   { border-left-color:var(--v); background:#faeef1; }
.fc-title { font-size:14px; font-weight:700; color:var(--ink); margin-bottom:6px; line-height:1.4; }
.fc-meta  { display:flex; gap:6px; flex-wrap:wrap; }

/* Formulario de edición */
.ecard { background:white; border-radius:16px; padding:24px; box-shadow:var(--sh); border:1.5px solid var(--border); }
.ecard h3 { font-size:15px; font-weight:700; color:var(--ink); margin-bottom:18px;
  padding-bottom:12px; border-bottom:1px solid var(--vp); }

/* Tabla de respuestas */
.resp-table-wrap { overflow-x:auto; margin-top:16px; border-radius:12px;
  box-shadow:var(--sh); border:1.5px solid var(--border); }
.resp-table { width:100%; border-collapse:collapse; font-size:12.5px; min-width:600px; }
.resp-table th { background:var(--vd); color:white; padding:10px 14px;
  text-align:left; font-size:11.5px; white-space:nowrap; }
.resp-table td { padding:9px 14px; border-bottom:1px solid var(--vp); vertical-align:top; }
.resp-table tr:nth-child(even) td { background:#fdf8f9; }
.resp-table tr:hover td { background:#faeef1; }
.resp-num { color:#9a6070; font-weight:700; font-size:11px; }

.help-box {
  background:#e3f2fd; border:1.5px solid #90caf9; border-radius:12px;
  padding:16px 18px; font-size:13px; color:#1565c0; margin-bottom:16px;
}
.help-box strong { font-weight:700; }
.help-box ol { margin:8px 0 0 16px; }
.help-box ol li { margin-bottom:4px; line-height:1.6; }
.help-step { display:flex; align-items:flex-start; gap:10px; margin-bottom:8px; }
.step-n { width:22px; height:22px; border-radius:50%; background:var(--v); color:white;
  font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
.url-preview { background:#f5f5f5; border-radius:8px; padding:8px 12px; font-family:'JetBrains Mono',monospace;
  font-size:11.5px; color:#333; word-break:break-all; margin-top:6px; border:1px solid #e0e0e0; }

.ok-m { background:#e8f5e9; border:1.5px solid #a5d6a7; border-radius:9px; padding:11px 16px; color:#2e7d32; font-size:13px; font-weight:700; margin-bottom:16px; }
.err-m { background:#fde8e8; border:1.5px solid #ef9a9a; border-radius:9px; padding:11px 16px; color:#c0392b; font-size:13px; font-weight:700; margin-bottom:16px; }
</style>

<div class="gfw">
  <div class="gf-head">
    <h1>📋 Formularios Externos — Gestión</h1>
    <p>Agrega exámenes en Google Forms o Microsoft Forms y visualiza sus respuestas cuando tengas una hoja CSV conectada.</p>
  </div>

  <?php if($ok): ?><div class="ok-m">✅ <?=sanitize($ok)?></div><?php endif ?>
  <?php if($err): ?><div class="err-m">⚠️ <?=sanitize($err)?></div><?php endif ?>

  <div class="gf-grid">

    <!-- ── COLUMNA IZQUIERDA: LISTA + FORMULARIO EDICIÓN ─── -->
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h3 style="font-size:14px;font-weight:700;color:var(--ink)">Formularios (<?=count($todos_forms)?>)</h3>
        <a href="forms_resultados.php" class="btn btn-v btn-sm">➕ Nuevo</a>
      </div>

      <?php if(empty($todos_forms)): ?>
      <div style="padding:32px;text-align:center;background:white;border-radius:14px;color:#c0a0a8;font-size:13px;box-shadow:var(--sh)">
        <div style="font-size:40px;margin-bottom:8px">📋</div>
        Aún no hay formularios. Agrega el primero.
      </div>
      <?php else: foreach($todos_forms as $f):
        $act = ($form_id==$f['id']) ? 'act' : '';
        $ti  = $tipos_ext[$f['tipo_prueba']] ?? ['📋','Evaluación',''];
        $hab = $f['habilitada'];
      ?>
      <a href="forms_resultados.php?id=<?=$f['id']?>" class="form-card <?=$act?>">
        <div class="fc-title"><?=sanitize($f['titulo'])?></div>
        <div class="fc-meta">
          <span class="badge" style="background:<?=colorTipo($f['tipo_prueba']??'evaluacion')?>;color:white;font-size:10px"><?=$ti[0]?> <?=$ti[1]?></span>
          <span class="badge <?=$hab?'b-g':'b-gray'?>"><?=$hab?'Activo':'Inactivo'?></span>
          <?php if($f['grupo_grado']!=='todos'): ?>
          <span class="badge b-v" style="font-size:10px"><?=etiquetaGrupo($f['grupo_grado'])?></span>
          <?php endif ?>
          <?php if($f['sheet_csv_url']): ?>
          <span class="badge b-blue">📊 Sheets</span>
          <?php endif ?>
        </div>
        <?php if($f['nombre']): ?>
        <div style="font-size:11px;color:#9a6070;margin-top:5px">👤 <?=sanitize($f['nombre'].' '.$f['apellido'])?></div>
        <?php endif ?>
      </a>
      <?php endforeach; endif ?>

      <!-- FORMULARIO CREAR/EDITAR -->
      <div class="ecard" style="margin-top:18px">
        <h3><?=$form_edit?'✏️ Editar formulario':'➕ Agregar formulario'?></h3>

        <div class="help-box">
          <strong>¿Cómo conectar un formulario?</strong>
          <div class="help-step"><span class="step-n">1</span><span>Crea tu examen en Google Forms o Microsoft Forms.</span></div>
          <div class="help-step"><span class="step-n">2</span><span>Copia la URL pública del formulario y pégala abajo.</span></div>
          <div class="help-step"><span class="step-n">3</span><span><em>(Opcional para ver respuestas aquí)</em> Si usas Google Forms, conecta también la hoja de Google Sheets pública.</span></div>
        </div>

        <form method="POST">
          <input type="hidden" name="guardar_form" value="1">
          <input type="hidden" name="fid" value="<?=$form_edit?$form_edit['id']:0?>">
          <div class="fg" style="margin-bottom:12px">
            <label class="fl">Título del examen *</label>
            <input type="text" name="titulo" class="fi" required
              value="<?=sanitize($form_edit['titulo']??'')?>"
              placeholder="Ej: Prueba Clasificatoria — Básico 10°-11°">
          </div>
          <div class="fg" style="margin-bottom:12px">
            <label class="fl">Descripción (opcional)</label>
            <input type="text" name="descripcion" class="fi"
              value="<?=sanitize($form_edit['descripcion']??'')?>"
              placeholder="Instrucciones breves para el estudiante">
          </div>
          <div class="fg" style="margin-bottom:12px">
            <label class="fl">URL del formulario *</label>
            <input type="url" name="form_url" class="fi" required
              value="<?=sanitize($form_edit['form_url']??'')?>"
              placeholder="https://forms.gle/...  https://docs.google.com/forms/...  o  https://forms.office.com/...">
            <div style="font-size:11px;color:#9a6070;margin-top:4px">Admite Google Forms y Microsoft Forms.</div>
          </div>
          <div class="fg" style="margin-bottom:12px">
            <label class="fl">URL de respuestas (Google Sheets) <em style="font-weight:400;text-transform:none">(para ver resultados aquí)</em></label>
            <input type="url" name="sheet_csv_url" class="fi"
              value="<?=sanitize($form_edit['sheet_csv_url']??'')?>"
              placeholder="https://docs.google.com/spreadsheets/d/...">
            <div style="font-size:11px;color:#9a6070;margin-top:4px">URL de la hoja de respuestas vinculada al formulario (debe estar pública).</div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div class="fg">
              <label class="fl">Tipo de prueba</label>
              <select name="tipo_prueba" class="fsel">
                <?php foreach($tipos_ext as $tk=>[$tic,$tnm,$td]): ?>
                <option value="<?=$tk?>" <?=($form_edit['tipo_prueba']??'evaluacion')===$tk?'selected':''?>><?=$tic?> <?=$tnm?></option>
                <?php endforeach ?>
              </select>
            </div>
            <div class="fg">
              <label class="fl">Grupo de grados</label>
              <select name="grupo_grado" class="fsel">
                <option value="todos" <?=($form_edit['grupo_grado']??'todos')==='todos'?'selected':''?>>Todos los grados</option>
                <?php foreach(['4-5','6-7','8-9','10-11'] as $g): ?>
                <option value="<?=$g?>" <?=($form_edit['grupo_grado']??'')===$g?'selected':''?>><?=etiquetaGrupo($g)?></option>
                <?php endforeach ?>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div class="fg">
              <label class="fl">⏱️ Tiempo límite (min)</label>
              <input type="number" name="tiempo_limite_min" class="fi" min="0" max="300"
                placeholder="Sin límite"
                value="<?=$form_edit['tiempo_limite_min']!==null&&$form_edit['tiempo_limite_min']!==''?$form_edit['tiempo_limite_min']:''?>">
              <div style="font-size:10.5px;color:#9a6070;margin-top:3px">Dejar vacío = sin límite de tiempo</div>
            </div>
            <div class="fg">
              <label class="fl">Estado</label>
              <select name="habilitada" class="fsel">
                <option value="1" <?=($form_edit['habilitada']??1)?'selected':''?>>✅ Activo</option>
                <option value="0" <?=!($form_edit['habilitada']??1)?'selected':''?>>⛔ Inactivo</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div class="fg">
              <label class="fl">📅 Fecha apertura</label>
              <input type="date" name="fecha_inicio_d" class="fi"
                value="<?= !empty($form_edit['fecha_inicio']) ? date('Y-m-d', strtotime($form_edit['fecha_inicio'])) : '' ?>">
              <input type="time" name="fecha_inicio_t" class="fi" style="margin-top:6px"
                value="<?= !empty($form_edit['fecha_inicio']) ? date('H:i', strtotime($form_edit['fecha_inicio'])) : '' ?>">
            </div>
            <div class="fg">
              <label class="fl">📅 Fecha cierre</label>
              <input type="date" name="fecha_cierre_d" class="fi"
                value="<?= !empty($form_edit['fecha_cierre']) ? date('Y-m-d', strtotime($form_edit['fecha_cierre'])) : '' ?>">
              <input type="time" name="fecha_cierre_t" class="fi" style="margin-top:6px"
                value="<?= !empty($form_edit['fecha_cierre']) ? date('H:i', strtotime($form_edit['fecha_cierre'])) : '' ?>">
            </div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn btn-v"><?=$form_edit?'💾 Guardar cambios':'➕ Agregar'?></button>
            <?php if($form_edit): ?>
            <a href="forms_resultados.php" class="btn btn-outline">+ Nuevo</a>
            <form method="POST" style="margin:0" onsubmit="return confirm('¿Eliminar este formulario?')">
              <input type="hidden" name="eliminar_form" value="1">
              <input type="hidden" name="fid" value="<?=$form_edit['id']?>">
              <button type="submit" class="btn btn-red">🗑️ Eliminar</button>
            </form>
            <?php endif ?>
          </div>
        </form>
      </div>
    </div>

    <!-- ── COLUMNA DERECHA: RESPUESTAS ───────────────────── -->
    <div>
      <?php if($form_edit): ?>
      <div class="ecard">
        <h3>📊 Respuestas — <?=sanitize($form_edit['titulo'])?></h3>

        <?php if(!$form_edit['sheet_csv_url']): ?>
        <!-- Sin Sheets configurado -->
        <div style="text-align:center;padding:36px 24px;color:#c0a0a8">
          <div style="font-size:48px;margin-bottom:12px">📊</div>
          <h4 style="font-size:15px;font-weight:700;color:var(--vd);margin-bottom:8px">Sin hoja de respuestas conectada</h4>
          <p style="font-size:13px;max-width:380px;margin:0 auto 16px">Para ver las respuestas aquí, conecta la hoja de Google Sheets siguiendo estos pasos:</p>
          <div style="text-align:left;background:var(--mist);border-radius:12px;padding:16px 18px;font-size:13px;color:var(--ink)">
            <div class="help-step"><span class="step-n">1</span><span>Abre tu formulario en <strong>forms.google.com</strong></span></div>
            <div class="help-step"><span class="step-n">2</span><span>Ve a la pestaña <strong>Respuestas</strong> → haz clic en el ícono verde de Sheets (<img src="https://ssl.gstatic.com/docs/spreadsheets/images/favicon.ico" style="height:14px;vertical-align:middle">)</span></div>
            <div class="help-step"><span class="step-n">3</span><span>En la hoja de cálculo: clic en <strong>Compartir → Cualquier persona con el enlace puede ver</strong></span></div>
            <div class="help-step"><span class="step-n">4</span><span>Copia la URL y pégala en el campo <strong>"URL de respuestas"</strong> del formulario</span></div>
          </div>
          <a href="forms_resultados.php?id=<?=$form_edit['id']?>" class="btn btn-v" style="margin-top:16px">✏️ Editar formulario</a>
        </div>

        <?php elseif($sheet_error): ?>
        <!-- Error al leer Sheets -->
        <div style="background:#fde8e8;border-radius:12px;padding:18px;margin-bottom:16px;font-size:13px;color:#c0392b">
          <strong>⚠️ Error al leer las respuestas:</strong><br><?=sanitize($sheet_error)?>
        </div>
        <div style="font-size:12.5px;color:#9a6070">
          URL configurada: <span class="url-preview"><?=sanitize($form_edit['sheet_csv_url'])?></span>
        </div>

        <?php else: ?>
        <!-- Tabla de respuestas -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px">
          <div>
            <span class="badge b-g" style="font-size:12px">✅ <?=count($respuestas)?> respuesta(s)</span>
            <span style="font-size:11.5px;color:#9a6070;margin-left:8px">actualizado en tiempo real</span>
          </div>
          <a href="<?=htmlspecialchars($form_edit['sheet_csv_url'])?>" target="_blank" class="btn btn-outline btn-sm">
            📊 Ver en Google Sheets
          </a>
        </div>

        <?php if(empty($respuestas)): ?>
        <div style="text-align:center;padding:32px;color:#c0a0a8;font-size:13px">
          <div style="font-size:40px;margin-bottom:8px">📭</div>
          Aún no hay respuestas registradas.
        </div>
        <?php else: ?>
        <div class="resp-table-wrap">
          <table class="resp-table">
            <thead>
              <tr>
                <th>#</th>
                <?php foreach($col_headers as $h): ?>
                <th><?=sanitize($h)?></th>
                <?php endforeach ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach($respuestas as $i=>$row): ?>
              <tr>
                <td class="resp-num"><?=$i+1?></td>
                <?php foreach($row as $j=>$cell): ?>
                <td style="max-width:240px;<?=$j===0?'font-weight:600;color:var(--vd)':''?>">
                  <?=sanitize($cell)?>
                </td>
                <?php endforeach ?>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
        <p style="font-size:11.5px;color:#9a6070;margin-top:10px">
          📡 Los datos se leen directamente de Google Sheets. Actualiza la página para ver nuevas respuestas.
        </p>
        <?php endif ?>

        <?php endif ?>
      </div>

      <?php else: ?>
      <div style="background:white;border-radius:16px;padding:48px;text-align:center;
        box-shadow:var(--sh);border:1.5px solid var(--border);color:#c0a0a8">
        <div style="font-size:52px;margin-bottom:14px">📋</div>
        <h3 style="font-size:16px;font-weight:700;color:var(--vd);margin-bottom:8px">Selecciona un formulario</h3>
        <p style="font-size:13px">Haz clic en uno de los formularios de la lista para ver sus respuestas o editarlo.</p>
      </div>
      <?php endif ?>
    </div>

  </div><!-- /gf-grid -->
</div>

<?php if($ok): ?><script>window.addEventListener('load',()=>st('<?=addslashes(sanitize($ok))?>'));</script><?php endif ?>
<?php require_once 'includes/footer.php'; ?>
