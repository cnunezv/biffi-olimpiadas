<?php
require_once 'includes/config.php';
requireLogin();
$uid=$_SESSION['user_id'];

// ── ACCIONES ─────────────────────────────────────────────────────
$ok=''; $err='';

// Mensaje masivo (solo admins)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['enviar_masivo']) && isAdmin()){
    $asunto=trim($_POST['asunto']??''); $cuerpo=trim($_POST['cuerpo']??'');
    $destino=$_POST['destino_masivo']??'todos';
    if($asunto && $cuerpo){
        $qwhere = "WHERE activo=1 AND id!=?";
        $qparams = [$uid];
        if($destino==='estudiantes')    { $qwhere.=" AND rol='estudiante'"; }
        elseif($destino==='docentes')   { $qwhere.=" AND rol='docente'"; }
        elseif($destino==='biffi')      { $qwhere.=" AND institucion_id=1"; }
        $dest_list=$pdo->prepare("SELECT id FROM usuarios $qwhere");
        $dest_list->execute($qparams);
        $ids=$dest_list->fetchAll(PDO::FETCH_COLUMN);
        $stmt=$pdo->prepare("INSERT INTO mensajes(de_id,para_id,asunto,cuerpo) VALUES(?,?,?,?)");
        foreach($ids as $pid) $stmt->execute([$uid,$pid,$asunto,$cuerpo]);
        $ok='Mensaje enviado a '.count($ids).' usuario(s) ✅';
    } else $err='Completa asunto y mensaje.';
}

// Enviar mensaje individual
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['enviar'])){
    $para=intval($_POST['para_id']);
    $asunto=trim($_POST['asunto']); $cuerpo=trim($_POST['cuerpo']);
    if($para && $asunto && $cuerpo){
        $chk=$pdo->prepare("SELECT id FROM usuarios WHERE id=? AND activo=1");
        $chk->execute([$para]);
        if($chk->fetch()){
            $pdo->prepare("INSERT INTO mensajes(de_id,para_id,asunto,cuerpo) VALUES(?,?,?,?)")
                ->execute([$uid,$para,$asunto,$cuerpo]);
            $ok='Mensaje enviado correctamente ✅';
        } else $err='Destinatario inválido.';
    } else $err='Completa todos los campos.';
}

// Eliminar mensaje
if(isset($_GET['del']) && is_numeric($_GET['del'])){
    $mid=intval($_GET['del']); $box=$_GET['box']??'recibidos';
    if($box==='recibidos')
        $pdo->prepare("UPDATE mensajes SET eliminado_para=1 WHERE id=? AND para_id=?")->execute([$mid,$uid]);
    else
        $pdo->prepare("UPDATE mensajes SET eliminado_de=1 WHERE id=? AND de_id=?")->execute([$mid,$uid]);
    header('Location: mensajes.php?box='.$box); exit;
}

// Marcar como leído
if(isset($_GET['ver']) && is_numeric($_GET['ver'])){
    $mid=intval($_GET['ver']);
    $pdo->prepare("UPDATE mensajes SET leido=1 WHERE id=? AND para_id=?")->execute([$mid,$uid]);
}

$box=$_GET['box']??'recibidos';
$ver_id=isset($_GET['ver'])?intval($_GET['ver']):0;

// Obtener mensajes
if($box==='recibidos'){
    $msgs=$pdo->prepare("SELECT m.*,u.nombre,u.apellido,u.rol FROM mensajes m
        JOIN usuarios u ON u.id=m.de_id
        WHERE m.para_id=? AND m.eliminado_para=0 ORDER BY m.enviado_en DESC");
} else {
    $msgs=$pdo->prepare("SELECT m.*,u.nombre,u.apellido,u.rol FROM mensajes m
        JOIN usuarios u ON u.id=m.para_id
        WHERE m.de_id=? AND m.eliminado_de=0 ORDER BY m.enviado_en DESC");
}
$msgs->execute([$uid]);
$lista=$msgs->fetchAll();

// Mensaje activo
$activo=null;
if($ver_id){
    $s=$pdo->prepare("SELECT m.*,ud.nombre dn,ud.apellido da,ud.rol dr,
        up.nombre pn,up.apellido pa FROM mensajes m
        JOIN usuarios ud ON ud.id=m.de_id JOIN usuarios up ON up.id=m.para_id
        WHERE m.id=? AND (m.de_id=? OR m.para_id=?)");
    $s->execute([$ver_id,$uid,$uid]);
    $activo=$s->fetch();
}

// Destinatarios disponibles (docentes + admin si soy estudiante, o estudiantes si soy docente/admin)
$dest_query = "SELECT id,nombre,apellido,rol,curso FROM usuarios WHERE id!=? AND activo=1 ORDER BY rol,nombre";
$ds=$pdo->prepare($dest_query); $ds->execute([$uid]);
$destinatarios=$ds->fetchAll();

$page_title='Mensajes — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
.msg-wrap{display:flex;height:calc(100vh - 68px);overflow:hidden}
.msg-left{width:320px;flex-shrink:0;background:white;border-right:1px solid var(--border);
  display:flex;flex-direction:column}
.msg-top{padding:16px 18px;border-bottom:1px solid var(--vp);display:flex;align-items:center;justify-content:space-between}
.msg-top h2{font-size:16px;font-weight:700;color:var(--ink)}
.msg-tabs{display:flex;border-bottom:1px solid var(--border)}
.mt{flex:1;padding:11px;text-align:center;font-size:12.5px;font-weight:700;
  cursor:pointer;color:#9a6070;border-bottom:2.5px solid transparent;transition:all .2s}
.mt.active,.mt:hover{color:var(--v);border-bottom-color:var(--v)}
.msg-list{flex:1;overflow-y:auto;padding:8px 0}
.msg-list::-webkit-scrollbar{width:4px}
.msg-list::-webkit-scrollbar-thumb{background:#d4a0b0;border-radius:2px}
.mi{display:block;padding:13px 16px;border-bottom:1px solid var(--vp);cursor:pointer;
  transition:background .2s;text-decoration:none;color:inherit;border-left:3px solid transparent}
.mi:hover{background:#fdf5f7}
.mi.active{background:#faeef1;border-left-color:var(--v)}
.mi.unread{background:#fdf0f3}
.mi.unread .mi-asunto{font-weight:700;color:var(--vd)}
.mi-who{font-size:12.5px;font-weight:600;color:var(--ink);display:flex;align-items:center;justify-content:space-between}
.mi-asunto{font-size:12.5px;color:#7a5060;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mi-date{font-size:10px;color:#bbb}
.mi-unread-dot{width:8px;height:8px;background:var(--v);border-radius:50%;flex-shrink:0}
.new-btn{margin:12px 14px 0;display:block}

/* DETAIL */
.msg-detail{flex:1;display:flex;flex-direction:column;overflow:hidden}
.md-head{padding:18px 24px;background:white;border-bottom:1px solid var(--border)}
.md-asunto{font-size:18px;font-weight:700;color:var(--ink);margin-bottom:6px}
.md-meta{font-size:12.5px;color:#9a6070;display:flex;gap:16px;flex-wrap:wrap}
.md-body{flex:1;overflow-y:auto;padding:24px;background:var(--mist)}
.msg-bubble{background:white;border-radius:14px;padding:20px 24px;max-width:700px;
  box-shadow:var(--sh);border:1.5px solid var(--border);line-height:1.7;font-size:14px;white-space:pre-wrap}
.md-actions{padding:16px 24px;background:white;border-top:1px solid var(--border);display:flex;gap:10px}

/* EMPTY */
.empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
  color:#c0a0a8;font-size:14px;gap:10px}
.empty span{font-size:52px}

/* COMPOSE MODAL */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(26,10,15,.6);
  backdrop-filter:blur(4px);z-index:500;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal{background:white;border-radius:18px;padding:28px;width:520px;max-width:94vw;
  box-shadow:0 24px 60px rgba(0,0,0,.3)}
.modal h3{font-size:17px;font-weight:700;color:var(--ink);margin-bottom:18px;
  padding-bottom:12px;border-bottom:1px solid var(--vp)}
.modal-close{float:right;background:none;border:none;font-size:20px;cursor:pointer;color:#9a6070;margin-top:-4px}
</style>

<div class="msg-wrap">
  <!-- PANEL IZQUIERDO -->
  <div class="msg-left">
    <div class="msg-top">
      <h2>Mensajes</h2>
      <div style="display:flex;gap:6px">
        <?php if(isAdmin()): ?>
        <button class="btn btn-outline btn-sm" onclick="openModal('modal-masivo')" title="Enviar a todos">📢</button>
        <?php endif ?>
        <button class="btn btn-v btn-sm" onclick="openModal('modal-nuevo')">✏️ Nuevo</button>
      </div>
    </div>
    <div class="msg-tabs">
      <a href="mensajes.php?box=recibidos" class="mt <?=$box==='recibidos'?'active':''?>">📥 Recibidos</a>
      <a href="mensajes.php?box=enviados" class="mt <?=$box==='enviados'?'active':''?>">📤 Enviados</a>
    </div>
    <div class="msg-list">
      <?php if(empty($lista)): ?>
        <p style="padding:24px;text-align:center;font-size:13px;color:#bbb">Sin mensajes</p>
      <?php else: foreach($lista as $m):
        $unread=($box==='recibidos' && !$m['leido']);
        $who=sanitize($m['nombre'].' '.$m['apellido']);
        $fecha=date('d/m H:i',strtotime($m['enviado_en']));
      ?>
      <a href="mensajes.php?box=<?=$box?>&ver=<?=$m['id']?>" 
         class="mi <?=$m['id']===$ver_id?'active':''?> <?=$unread?'unread':''?>">
        <div class="mi-who">
          <span><?=$who?> <span class="badge b-v" style="margin-left:4px;font-size:9px"><?=sanitize($m['rol'])?></span></span>
          <?php if($unread): ?><span class="mi-unread-dot"></span><?php endif ?>
        </div>
        <div class="mi-asunto"><?=sanitize($m['asunto'])?></div>
        <div class="mi-date"><?=$fecha?></div>
      </a>
      <?php endforeach; endif ?>
    </div>
  </div>

  <!-- PANEL DERECHO -->
  <div class="msg-detail">
    <?php if($activo): ?>
      <?php
        $es_mio=($activo['de_id']==$uid);
        $otro_nom=sanitize($es_mio?$activo['pn'].' '.$activo['pa']:$activo['dn'].' '.$activo['da']);
      ?>
      <div class="md-head">
        <div class="md-asunto"><?=sanitize($activo['asunto'])?></div>
        <div class="md-meta">
          <span>👤 De: <?=sanitize($activo['dn'].' '.$activo['da'])?> (<?=sanitize($activo['dr'])?>)</span>
          <span>📬 Para: <?=sanitize($activo['pn'].' '.$activo['pa'])?></span>
          <span>📅 <?=date('d/m/Y H:i',strtotime($activo['enviado_en']))?></span>
        </div>
      </div>
      <div class="md-body">
        <?php if($ok): ?><div style="background:#e8f5e9;border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:700;color:#2e7d32"><?=$ok?></div><?php endif ?>
        <div class="msg-bubble"><?=sanitize($activo['cuerpo'])?></div>
      </div>
      <div class="md-actions">
        <button class="btn btn-v" onclick="openModal(<?=$activo['para_id']!==$uid?$activo['de_id']:$activo['para_id']?>, 'Re: <?=addslashes(sanitize($activo['asunto']))?>')">↩️ Responder</button>
        <a href="mensajes.php?del=<?=$activo['id']?>&box=<?=$box?>" class="btn btn-red btn-sm"
           onclick="return confirm('¿Eliminar este mensaje?')">🗑️ Eliminar</a>
        <a href="mensajes.php?box=<?=$box?>" class="btn btn-outline btn-sm">← Volver</a>
      </div>
    <?php else: ?>
      <?php if($ok): ?><div style="background:#e8f5e9;border-radius:10px;padding:14px 20px;margin:20px;font-size:13px;font-weight:700;color:#2e7d32"><?=$ok?></div><?php endif ?>
      <?php if($err): ?><div style="background:#fde8e8;border-radius:10px;padding:14px 20px;margin:20px;font-size:13px;font-weight:700;color:#c0392b"><?=$err?></div><?php endif ?>
      <div class="empty">
        <span>✉️</span>
        <p>Selecciona un mensaje para leerlo</p>
        <button class="btn btn-v" onclick="openModal()">✏️ Escribir nuevo mensaje</button>
      </div>
    <?php endif ?>
  </div>
</div>

<!-- MODAL NUEVO MENSAJE INDIVIDUAL -->
<div class="modal-bg" id="modal-nuevo">
  <div class="modal">
    <h3>✏️ Nuevo mensaje <button class="modal-close" onclick="closeModal('modal-nuevo')">✕</button></h3>
    <?php if($err&&!$activo): ?><div style="background:#fde8e8;border-radius:8px;padding:9px 13px;margin-bottom:12px;font-size:12.5px;color:#c0392b">⚠️ <?=$err?></div><?php endif ?>
    <form method="POST">
      <input type="hidden" name="enviar" value="1">
      <div class="fg" style="margin-bottom:12px">
        <label class="fl">Destinatario</label>
        <select name="para_id" id="modal-dest" class="fsel" required>
          <option value="">— Selecciona destinatario —</option>
          <?php
          $grupos=['admin'=>'👑 Administrador','docente'=>'👩‍🏫 Docentes','estudiante'=>'🎓 Estudiantes'];
          $actual_grupo='';
          foreach($destinatarios as $d):
            if($d['rol']!==$actual_grupo){
              if($actual_grupo) echo "</optgroup>";
              echo "<optgroup label='".$grupos[$d['rol']]."'>";
              $actual_grupo=$d['rol'];
            }
            $sel=isset($_POST['para_id'])&&$_POST['para_id']==$d['id']?'selected':'';
            echo "<option value='{$d['id']}' $sel>".sanitize($d['nombre'].' '.$d['apellido']).($d['curso']?' ('.$d['curso'].')':'')."</option>";
          endforeach;
          if($actual_grupo) echo "</optgroup>";
          ?>
        </select>
      </div>
      <div class="fg" style="margin-bottom:12px">
        <label class="fl">Asunto</label>
        <input type="text" name="asunto" id="modal-asunto" class="fi" required placeholder="Asunto del mensaje" value="<?=sanitize($_POST['asunto']??'')?>">
      </div>
      <div class="fg" style="margin-bottom:16px">
        <label class="fl">Mensaje</label>
        <textarea name="cuerpo" class="fi" rows="5" required placeholder="Escribe tu mensaje aquí..."><?=sanitize($_POST['cuerpo']??'')?></textarea>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-v">📤 Enviar mensaje</button>
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-nuevo')">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<?php if(isAdmin()): ?>
<!-- MODAL MENSAJE MASIVO -->
<div class="modal-bg" id="modal-masivo">
  <div class="modal">
    <h3>📢 Mensaje masivo <button class="modal-close" onclick="closeModal('modal-masivo')">✕</button></h3>
    <div style="background:#fff3cd;border:1.5px solid #f0c040;border-radius:10px;padding:11px 14px;margin-bottom:16px;font-size:12.5px;color:#7a5200">
      ⚠️ Este mensaje se enviará a <strong>múltiples usuarios</strong> a la vez. Úsalo con cuidado.
    </div>
    <form method="POST">
      <input type="hidden" name="enviar_masivo" value="1">
      <div class="fg" style="margin-bottom:12px">
        <label class="fl">Enviar a</label>
        <select name="destino_masivo" class="fsel">
          <option value="todos">👥 Todos los usuarios</option>
          <option value="estudiantes">🎓 Solo estudiantes</option>
          <option value="docentes">👩‍🏫 Solo docentes</option>
          <option value="biffi">🏫 Solo Colegio Biffi</option>
        </select>
      </div>
      <div class="fg" style="margin-bottom:12px">
        <label class="fl">Asunto</label>
        <input type="text" name="asunto" class="fi" required placeholder="Ej: Recordatorio — Clasificatoria 2026">
      </div>
      <div class="fg" style="margin-bottom:16px">
        <label class="fl">Mensaje</label>
        <textarea name="cuerpo" class="fi" rows="6" required placeholder="Escribe el mensaje para todos los destinatarios..."></textarea>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-v" onclick="return confirm('¿Enviar este mensaje a todos los destinatarios seleccionados?')">📢 Enviar a todos</button>
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-masivo')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<?php endif ?>

<script>
function openModal(id, destId='', asunto=''){
  // Legacy: openModal() sin argumento abre el nuevo mensaje
  if(!id || id==='' || typeof id === 'number'){
    // called old-style: openModal(destId, asunto)
    destId = id; asunto = destId;
    id = 'modal-nuevo';
  }
  document.getElementById(id)?.classList.add('open');
  if(destId && document.getElementById('modal-dest')) document.getElementById('modal-dest').value=destId;
  if(asunto && document.getElementById('modal-asunto')) document.getElementById('modal-asunto').value=asunto;
}
function closeModal(id){ document.getElementById(id)?.classList.remove('open'); }
document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open')}));
<?php if($err && !$activo): ?>window.onload=()=>openModal('modal-nuevo');<?php endif ?>
</script>
<?php require_once 'includes/footer.php'; ?>
