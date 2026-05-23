<?php
require_once 'includes/config.php';
requireDocente();

// ── RESTRICCIÓN POR INSTITUCIÓN ───────────────────────────────
// Admin ve todo. Docente solo ve su propia institución.
$es_admin = isAdmin();
$mi_inst_id = null; // null = ver todo (solo admins)
if(!$es_admin){
    // Buscar la institución del docente actual
    $si=$pdo->prepare("SELECT institucion_id FROM usuarios WHERE id=?");
    $si->execute([$_SESSION['user_id']]); $mi_inst_id=(int)($si->fetchColumn()?:0);
}

// Filtros (docentes externos solo pueden filtrar dentro de su institución)
$nivel_f = $_GET['nivel'] ?? '';
$inst_f  = $es_admin ? intval($_GET['inst']??0) : $mi_inst_id;
$grupo_f = $_GET['grupo'] ?? '';
$buscar  = trim($_GET['q'] ?? '');

// WHERE base según rol
function instWhere(bool $esAdmin, ?int $miInstId, string $alias='u'): array {
    if($esAdmin) return ['','[]'];
    return [" AND {$alias}.institucion_id=".intval($miInstId), ''];
}
$inst_restrict = $es_admin ? '' : " AND u.institucion_id=".intval($mi_inst_id);
$inst_restrict_r = $es_admin ? '' : " AND u2.institucion_id=".intval($mi_inst_id);

// Estadísticas (filtradas por institución si docente externo)
$stats = $pdo->query("SELECT
  COUNT(DISTINCT u.id)       total_estudiantes,
  COUNT(DISTINCT r.id)       total_simulacros,
  ROUND(AVG(r.puntaje/r.total*100),1) promedio_pct,
  COUNT(DISTINCT u.institucion_id) total_instituciones
FROM usuarios u
LEFT JOIN resultados r ON r.usuario_id=u.id AND r.total>0
WHERE u.rol='estudiante' AND u.activo=1
$inst_restrict")->fetch();

// Instituciones (admin ve todas, docente solo la suya para el filtro)
try {
    if($es_admin)
        $instituciones = $pdo->query("SELECT * FROM instituciones WHERE activa=1 ORDER BY nombre")->fetchAll();
    else {
        $sq=$pdo->prepare("SELECT * FROM instituciones WHERE id=?");
        $sq->execute([$mi_inst_id]); $instituciones=$sq->fetchAll();
    }
} catch(\Exception $e){ $instituciones=[]; }

// Ranking por institución (solo admin lo ve completo)
$rank_inst = [];
if($es_admin){
    try {
        $rank_inst = $pdo->query("SELECT i.id,i.nombre,i.color,
          COUNT(DISTINCT u.id) estudiantes,
          COUNT(r.id) intentos,
          ROUND(AVG(r.puntaje/r.total*100),1) promedio,
          ROUND(MAX(r.puntaje/r.total*100),0) mejor
          FROM instituciones i
          LEFT JOIN usuarios u ON u.institucion_id=i.id AND u.rol='estudiante' AND u.activo=1
          LEFT JOIN resultados r ON r.usuario_id=u.id AND r.total>0
          WHERE i.activa=1
          GROUP BY i.id
          ORDER BY promedio DESC")->fetchAll();
    } catch(\Exception $e){}
}

// Ranking individual
$where  = "WHERE u.rol='estudiante' AND u.activo=1".$inst_restrict;
$params = [];
if($nivel_f) { $where.=" AND r.nivel LIKE ?"; $params[]="%{$nivel_f}%"; }
if($es_admin && $inst_f){ $where.=" AND u.institucion_id=?"; $params[]=$inst_f; }
if($buscar)  { $where.=" AND (u.nombre LIKE ? OR u.apellido LIKE ?)";
               $b="%$buscar%"; $params[]=$b; $params[]=$b; }
$sq = $pdo->prepare("SELECT u.id,u.nombre,u.apellido,u.grado,u.curso,u.nivel,
  COALESCE(i.nombre,'—') inst_nom, COALESCE(i.color,'#9e9e9e') inst_col,
  COUNT(r.id) intentos,
  MAX(r.puntaje/r.total*100) mejor_pct,
  AVG(r.puntaje/r.total*100) prom_pct
  FROM usuarios u
  LEFT JOIN resultados r ON r.usuario_id=u.id AND r.total>0
  LEFT JOIN instituciones i ON i.id=u.institucion_id
  $where GROUP BY u.id ORDER BY mejor_pct DESC");
$sq->execute($params); $ranking=$sq->fetchAll();

// Resultados detallados
$rq = $pdo->prepare("SELECT r.*,u.nombre,u.apellido,u.grado,u.curso,
  COALESCE(i.nombre,'—') inst_nom, COALESCE(i.color,'#9e9e9e') inst_col
  FROM resultados r
  JOIN usuarios u ON u.id=r.usuario_id
  LEFT JOIN instituciones i ON i.id=u.institucion_id
  $where ORDER BY r.fecha DESC LIMIT 120");
$rq->execute($params); $resultados=$rq->fetchAll();

// Mensajes recibidos (solo los que corresponden)
$msj_q=$pdo->prepare("SELECT m.*,u.nombre,u.apellido,u.curso,u.institucion_id
  FROM mensajes m JOIN usuarios u ON u.id=m.de_id
  WHERE m.para_id=? AND m.eliminado_para=0
  ".(!$es_admin && $mi_inst_id ? "AND u.institucion_id=".intval($mi_inst_id) : "")."
  ORDER BY m.enviado_en DESC LIMIT 40");
$msj_q->execute([$_SESSION['user_id']]); $mensajes_doc=$msj_q->fetchAll();

$page_title='Panel Docente — Biffi Olimpiadas';
require_once 'includes/header.php';
?>
<style>
.doc-wrap{max-width:1160px;margin:0 auto;padding:26px 24px}
.doc-head{margin-bottom:22px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px}
.doc-head h1{font-family:'DM Serif Display',serif;font-size:26px;color:var(--ink)}
.doc-head p{font-size:13px;color:#9a6070;margin-top:3px}
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:22px}
.stat{background:white;border-radius:13px;padding:18px;text-align:center;box-shadow:var(--sh);border:1.5px solid var(--border)}
.sn{font-family:'DM Serif Display',serif;font-size:30px;color:var(--vd)}
.sl{font-size:11.5px;color:#9a6070;font-weight:600;margin-top:3px}
/* Tabs */
.tabs-doc{display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap;border-bottom:1.5px solid var(--border);padding-bottom:0}
.td{padding:11px 18px;font-family:'Sora',sans-serif;font-size:13px;font-weight:600;
  cursor:pointer;color:#9a6070;background:none;border:none;border-bottom:3px solid transparent;
  transition:all .2s;text-decoration:none}
.td:hover{color:var(--v)}
.td.a{color:var(--vd);border-bottom-color:var(--v)}
.panel{display:none;animation:fi .25s ease}.panel.a{display:block}
@keyframes fi{from{opacity:0}to{opacity:1}}
/* Ranking */
.rank-row{display:flex;align-items:center;gap:12px;padding:12px 16px;background:white;
  border-radius:12px;margin-bottom:8px;box-shadow:var(--sh);border:1.5px solid var(--border);transition:all .2s}
.rank-row:hover{box-shadow:var(--shh);border-color:var(--border)}
.rank-pos{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;
  justify-content:center;font-size:13px;font-weight:800;color:white;flex-shrink:0}
.rank-info{flex:1;min-width:0}
.rank-name{font-size:14px;font-weight:700;color:var(--ink)}
.rank-sub{font-size:12px;color:#9a6070;margin-top:3px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.rank-inst-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.rank-score{text-align:right}
.rank-pct{font-family:'DM Serif Display',serif;font-size:22px;color:var(--vd)}
.rank-detail{font-size:11px;color:#9a6070}
.pct-bar{width:80px;height:6px;background:var(--vp);border-radius:3px;margin-top:5px;overflow:hidden}
.pct-fill{height:100%;background:linear-gradient(90deg,var(--v),var(--vl));border-radius:3px}
/* Institución ranking card */
.inst-rank-card{background:white;border-radius:14px;padding:18px;box-shadow:var(--sh);
  border:1.5px solid var(--border);border-top:4px solid var(--v)}
.irc-top{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.irc-logo{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;
  justify-content:center;font-weight:800;color:white;font-size:13px;flex-shrink:0}
/* Filtros */
.filtros{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
.filtros input,.filtros select{padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;
  font-family:'Sora',sans-serif;font-size:13px;outline:none;background:white}
.filtros input:focus,.filtros select:focus{border-color:var(--v)}
.no-data{text-align:center;padding:48px;color:#c0a0a8;background:white;border-radius:14px;box-shadow:var(--sh)}
.no-data span{font-size:48px;display:block;margin-bottom:12px}
</style>

<div class="doc-wrap">
  <div class="doc-head">
    <div>
      <h1>📊 Panel Docente</h1>
      <?php if(!$es_admin && $mi_inst_id && !empty($instituciones)): ?>
      <p>
        <span style="display:inline-flex;align-items:center;gap:7px;padding:4px 14px;border-radius:16px;
          font-size:12px;font-weight:700;color:white;background:<?=sanitize($instituciones[0]['color']??'var(--v)')?>">
          🏫 <?=sanitize($instituciones[0]['nombre']??'')?>
        </span>
      </p>
      <?php else: ?>
      <p>XVIII Olimpiadas de Matemáticas — Cartagena 2026</p>
      <?php endif ?>
    </div>
    <?php if(isAdmin()): ?>
    <?php if(puedeEditarPruebas()): ?>
    <a href="editor_simulacro.php" class="btn btn-v btn-sm">✏️ Editor de pruebas</a>
    <?php endif ?>
    <?php endif ?>
  </div>

  <!-- Estadísticas -->
  <div class="stats-row">
    <div class="stat"><div class="sn"><?=(int)$stats['total_estudiantes']?></div><div class="sl"><?=$es_admin?'Estudiantes':'Tus estudiantes'?></div></div>
    <?php if($es_admin): ?>
    <div class="stat"><div class="sn"><?=(int)$stats['total_instituciones']?></div><div class="sl">Instituciones</div></div>
    <?php endif ?>
    <div class="stat"><div class="sn"><?=(int)$stats['total_simulacros']?></div><div class="sl">Simulacros</div></div>
    <div class="stat"><div class="sn"><?=number_format((float)($stats['promedio_pct']??0),1)?>%</div><div class="sl">Promedio</div></div>
  </div>

  <!-- Tabs -->
  <div class="tabs-doc">
    <?php if($es_admin): ?>
    <button class="td a" onclick="showPanel('instituciones',this)">🏫 Por Institución</button>
    <?php endif ?>
    <button class="td <?=!$es_admin?'a':''?>" onclick="showPanel('ranking',this)">🏆 Ranking</button>
    <button class="td" onclick="showPanel('resultados',this)">📋 Resultados</button>
    <button class="td" onclick="showPanel('mensajes',this)">✉️ Mensajes</button>
  </div>

  <!-- ══ POR INSTITUCIÓN ════════════════════════════════════ -->
  <div id="panel-instituciones" class="panel <?=$es_admin?'a':''?>">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:28px">
      <?php foreach($rank_inst as $pos=>$ri): ?>
      <div class="inst-rank-card" style="border-top-color:<?=sanitize($ri['color'])?>">
        <div class="irc-top">
          <div style="width:36px;height:36px;font-size:14px;font-weight:800;border-radius:50%;
            display:flex;align-items:center;justify-content:center;color:white;
            background:<?=['#C8A050','#9E9E9E','#CD7F32'][$pos]??sanitize($ri['color'])?>">
            <?=$pos+1?>
          </div>
          <div class="irc-logo" style="background:<?=sanitize($ri['color'])?>"><?=strtoupper(substr($ri['nombre'],0,2))?></div>
          <div>
            <div style="font-size:13.5px;font-weight:700;color:var(--ink);line-height:1.3"><?=sanitize($ri['nombre'])?></div>
            <div style="font-size:11px;color:#9a6070"><?=(int)$ri['estudiantes']?> participantes</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center;padding:12px 0;border-top:1px solid var(--vp);border-bottom:1px solid var(--vp);margin-bottom:12px">
          <div><div style="font-family:'DM Serif Display',serif;font-size:20px;color:var(--vd)"><?=(int)$ri['intentos']?></div><div style="font-size:10.5px;color:#9a6070">Intentos</div></div>
          <div><div style="font-family:'DM Serif Display',serif;font-size:20px;color:var(--vd)"><?=number_format((float)($ri['promedio']??0),1)?>%</div><div style="font-size:10.5px;color:#9a6070">Promedio</div></div>
          <div><div style="font-family:'DM Serif Display',serif;font-size:20px;color:var(--vd)"><?=(int)($ri['mejor']??0)?>%</div><div style="font-size:10.5px;color:#9a6070">Mejor</div></div>
        </div>
        <!-- Top 3 de esa institución -->
        <?php
        $top=$pdo->prepare("SELECT u.nombre,u.apellido,u.grado,MAX(r.puntaje/r.total*100) mejor
          FROM usuarios u JOIN resultados r ON r.usuario_id=u.id
          WHERE u.institucion_id=? AND r.total>0 AND u.rol='estudiante'
          GROUP BY u.id ORDER BY mejor DESC LIMIT 3");
        $top->execute([$ri['id']]); $toplist=$top->fetchAll();
        foreach($toplist as $ti=>$tv): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--vp)">
          <span style="font-size:13px"><?=['🥇','🥈','🥉'][$ti]?></span>
          <span style="font-size:12.5px;font-weight:600;flex:1;color:var(--ink)"><?=sanitize($tv['nombre'].' '.$tv['apellido'])?><?=$tv['grado']?' ('.$tv['grado'].'°)':''?></span>
          <span style="font-size:12px;font-weight:700;color:var(--vd)"><?=round($tv['mejor'])?>%</span>
        </div>
        <?php endforeach ?>
      </div>
      <?php endforeach ?>
      <?php if(empty($rank_inst)): ?>
      <div class="no-data" style="grid-column:1/-1"><span>🏁</span>Sin datos aún</div>
      <?php endif ?>
    </div>
  </div>

  <!-- ══ RANKING INDIVIDUAL ════════════════════════════════= -->
  <div id="panel-ranking" class="panel <?=!$es_admin?'a':''?>">
    <?php if(empty($ranking)): ?>
    <div class="no-data"><span>🏁</span>Sin resultados aún</div>
    <?php else: foreach($ranking as $i=>$r):
      $pct  = round($r['prom_pct']??0);
      $best = round($r['mejor_pct']??0);
      $col  = ['#C8A050','#9E9E9E','#CD7F32'][$i]??'var(--vl)';
      $grp  = $r['grado']?grupoDeGrado((int)$r['grado']):null;
    ?>
    <div class="rank-row">
      <div class="rank-pos" style="background:<?=$col?>"><?=$i+1?></div>
      <?php if($r['inst_col']): ?>
      <div style="width:8px;height:48px;border-radius:4px;background:<?=sanitize($r['inst_col'])?>;flex-shrink:0"></div>
      <?php endif ?>
      <div class="rank-info">
        <div class="rank-name"><?=sanitize($r['nombre'].' '.$r['apellido'])?></div>
        <div class="rank-sub">
          <?php if($r['inst_nom']): ?>
          <span style="display:flex;align-items:center;gap:4px">
            <span class="rank-inst-dot" style="background:<?=sanitize($r['inst_col']??'#999')?>"></span>
            <?=sanitize($r['inst_nom'])?>
          </span>
          <?php endif ?>
          <?php if($r['grado']): ?><span class="badge" style="background:<?=colorGrupo($grp??'10-11')?>;color:white;font-size:9px">Grado <?=$r['grado']?>°</span><?php endif ?>
          <?php if($r['curso']): ?><span class="badge b-gray" style="font-size:9px"><?=sanitize($r['curso'])?></span><?php endif ?>
          <span class="badge b-v" style="font-size:9px"><?=ucfirst($r['nivel'])?></span>
          <span style="font-size:11px;color:#9a6070"><?=$r['intentos']?> intento(s)</span>
        </div>
        <div class="pct-bar"><div class="pct-fill" style="width:<?=$best?>%"></div></div>
      </div>
      <div class="rank-score">
        <div class="rank-pct"><?=$best>0?$best.'%':'—'?></div>
        <div class="rank-detail">mejor resultado</div>
        <?php if($pct>0): ?><div class="rank-detail">Prom: <?=$pct?>%</div><?php endif ?>
      </div>
    </div>
    <?php endforeach; endif ?>
  </div>

  <!-- ══ RESULTADOS DETALLADOS ══════════════════════════════ -->
  <div id="panel-resultados" class="panel">
    <form class="filtros" method="GET">
      <input type="text" name="q" placeholder="🔍 Buscar estudiante o colegio..." value="<?=sanitize($buscar)?>" style="min-width:220px">
      <select name="nivel">
        <option value="">Todos los niveles</option>
        <option value="basico" <?=$nivel_f==='basico'?'selected':''?>>Básico</option>
        <option value="medio" <?=$nivel_f==='medio'?'selected':''?>>Medio</option>
        <option value="avanzado" <?=$nivel_f==='avanzado'?'selected':''?>>Avanzado</option>
      </select>
      <select name="inst">
        <option value="0">Todas las instituciones</option>
        <?php foreach($instituciones as $i): ?>
        <option value="<?=$i['id']?>" <?=$inst_f==$i['id']?'selected':''?>><?=sanitize($i['nombre'])?></option>
        <?php endforeach ?>
      </select>
      <button type="submit" class="btn btn-v btn-sm">Filtrar</button>
      <a href="docente.php" class="btn btn-outline btn-sm">✕ Limpiar</a>
    </form>
    <?php if(empty($resultados)): ?>
    <div class="no-data"><span>📭</span>No hay resultados con ese filtro</div>
    <?php else: ?>
    <div style="overflow-x:auto;background:white;border-radius:14px;box-shadow:var(--sh);border:1.5px solid var(--border)">
      <table>
        <tr><th>Estudiante</th><th>Institución</th><th>Grado</th><th>Nivel</th><th>Tipo</th><th>Puntaje</th><th>%</th><th>Tiempo</th><th>Fecha</th></tr>
        <?php foreach($resultados as $r):
          $pct  = $r['total']>0 ? round($r['puntaje']/$r['total']*100) : 0;
          $col  = $pct>=80?'#2e7d32':($pct>=60?'#f57c00':'#c0392b');
          $parts= explode('_',$r['nivel']);
          $tipos_lbl=['simulacro'=>'Simulacro','clasificatoria'=>'Clasificatoria','selectiva'=>'Selectiva','final'=>'Final','evaluacion'=>'Evaluación'];
        ?>
        <tr>
          <td style="font-weight:700"><?=sanitize($r['nombre'].' '.$r['apellido'])?></td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px">
              <?php if($r['inst_col']): ?><span style="width:10px;height:10px;border-radius:50%;background:<?=sanitize($r['inst_col'])?>;flex-shrink:0"></span><?php endif ?>
              <?=sanitize($r['inst_nom']??'—')?>
            </span>
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
    <p style="font-size:12px;color:#9a6070;margin-top:8px"><?=count($resultados)?> resultado(s) mostrados</p>
    <?php endif ?>
  </div>

  <!-- ══ MENSAJES ══════════════════════════════════════════= -->
  <div id="panel-mensajes" class="panel">
    <?php
    $msgs=$pdo->prepare("SELECT m.*,u.nombre,u.apellido,u.curso,i.nombre inst_nom,i.color inst_col
      FROM mensajes m JOIN usuarios u ON u.id=m.de_id
      LEFT JOIN instituciones i ON i.id=u.institucion_id
      WHERE m.para_id=? AND m.eliminado_para=0 ORDER BY m.enviado_en DESC LIMIT 40");
    $msgs->execute([$_SESSION['user_id']]); $msgs_list=$msgs->fetchAll();
    if(empty($msgs_list)):
    ?>
    <div class="no-data"><span>📭</span>No hay mensajes recibidos</div>
    <?php else: ?>
    <div style="background:white;border-radius:14px;box-shadow:var(--sh);border:1.5px solid var(--border);overflow:hidden">
      <?php foreach($msgs_list as $m): ?>
      <div style="padding:14px 18px;border-bottom:1px solid var(--vp);display:flex;align-items:flex-start;gap:14px">
        <div style="width:40px;height:40px;border-radius:50%;background:<?=sanitize($m['inst_col']??'var(--vp)')?>;
          display:flex;align-items:center;justify-content:center;font-weight:800;color:white;font-size:13px;flex-shrink:0">
          <?=strtoupper(substr($m['nombre'],0,1).substr($m['apellido'],0,1))?>
        </div>
        <div style="flex:1">
          <div style="font-size:13.5px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <?=sanitize($m['nombre'].' '.$m['apellido'])?>
            <?php if($m['inst_nom']): ?>
            <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#9a6070">
              <span style="width:8px;height:8px;border-radius:50%;background:<?=sanitize($m['inst_col']??'#999')?>"></span>
              <?=sanitize($m['inst_nom'])?>
            </span>
            <?php endif ?>
            <?php if(!$m['leido']): ?><span class="badge b-v" style="font-size:9px">NUEVO</span><?php endif ?>
          </div>
          <div style="font-size:13px;font-weight:700;color:var(--v);margin-top:3px"><?=sanitize($m['asunto'])?></div>
          <div style="font-size:13px;color:#7a5060;margin-top:5px;line-height:1.6"><?=nl2br(sanitize($m['cuerpo']))?></div>
          <div style="font-size:11px;color:#bbb;margin-top:6px"><?=date('d/m/Y H:i',strtotime($m['enviado_en']))?></div>
        </div>
        <a href="mensajes.php?box=recibidos" class="btn btn-outline btn-sm">↩️ Responder</a>
      </div>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>
</div>

<script>
function showPanel(name,btn){
  document.querySelectorAll('.panel').forEach(p=>p.classList.remove('a'));
  document.querySelectorAll('.td').forEach(b=>b.classList.remove('a'));
  document.getElementById('panel-'+name).classList.add('a');
  if(btn) btn.classList.add('a');
}
</script>
<?php require_once 'includes/footer.php'; ?>
