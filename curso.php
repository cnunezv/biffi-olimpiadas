<?php
require_once 'includes/config.php';
requireLogin();
$mi_grupo = miGrupo();
$secs=$pdo->query("SELECT nombre,etiqueta,habilitada FROM secciones ORDER BY id")->fetchAll();
$secs_map=[]; foreach($secs as $s) $secs_map[$s['nombre']]=$s;
$recursos=$pdo->query("SELECT * FROM recursos WHERE visible=1 ORDER BY creado_en DESC LIMIT 10")->fetchAll();
$stmt=$pdo->prepare("SELECT nivel,puntaje,total FROM resultados WHERE usuario_id=? ORDER BY fecha DESC");
$stmt->execute([$_SESSION['user_id']]);
$res_map=[]; foreach($stmt->fetchAll() as $r) if(!isset($res_map[$r['nivel']])) $res_map[$r['nivel']]=$r;
$page_title='Mi curso — Biffi Olimpiadas';
require_once 'includes/header.php';
// Flash messages from simulacro.php redirects
$flash_err = $_SESSION['flash_error']??''; unset($_SESSION['flash_error']);
$flash_ok  = $_SESSION['flash_ok']??'';   unset($_SESSION['flash_ok']);
?>
<style>
.cw{display:flex;min-height:calc(100vh - 68px)}
.sb{width:265px;flex-shrink:0;background:white;border-right:1px solid var(--border);
  height:calc(100vh - 68px);overflow-y:auto;position:sticky;top:68px;padding:14px 0}
.sb::-webkit-scrollbar{width:4px}
.sb::-webkit-scrollbar-thumb{background:#d4a0b0;border-radius:2px}
.sb-hd{padding:0 16px 12px;border-bottom:1px solid var(--vp);margin-bottom:8px}
.sb-hd a{font-size:11px;font-weight:700;color:#9a6070;text-transform:uppercase;letter-spacing:.06em;
  text-decoration:none;float:right;margin-top:2px}
.sb-cn{font-size:11.5px;font-weight:700;color:var(--vd);opacity:.7;
  text-transform:uppercase;letter-spacing:.06em;padding:0 16px;margin-bottom:6px}
.sh{display:flex;align-items:center;gap:8px;padding:9px 16px;cursor:pointer;transition:background .2s;user-select:none}
.sh:hover,.sh.o{background:#fdf5f7}
.sd{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.slb{font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--ink);flex:1}
.sch{font-size:10px;color:#9a6070;transition:transform .2s}
.sh.o .sch{transform:rotate(180deg)}
.sis{display:none}
.sis.o{display:block}
.si{display:flex;align-items:center;gap:8px;padding:7px 16px 7px 32px;font-size:12.5px;color:#7a5060;
  cursor:pointer;transition:all .2s;border-left:3px solid transparent}
.si:hover{background:#fdf5f7;color:var(--vd)}
.si.a{background:#faeef1;border-left-color:var(--v);color:var(--vd);font-weight:700}
.cm{flex:1;overflow-y:auto}
.hero{background:linear-gradient(135deg,var(--vd),var(--v),var(--vl));padding:32px 40px;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;
  background-image:repeating-linear-gradient(45deg,rgba(200,160,80,.06) 0,rgba(200,160,80,.06) 1px,transparent 1px,transparent 36px),
  repeating-linear-gradient(-45deg,rgba(200,160,80,.06) 0,rgba(200,160,80,.06) 1px,transparent 1px,transparent 36px)}
.hc{position:relative}
.bc{font-size:12px;color:rgba(255,255,255,.6);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.bc a{color:rgba(255,255,255,.7);cursor:pointer;transition:color .2s;text-decoration:none}
.bc a:hover{color:white}
.hero h1{font-family:'DM Serif Display',serif;font-size:30px;color:white;line-height:1.2;margin-bottom:14px;max-width:680px}
.hbdg{display:flex;gap:9px;flex-wrap:wrap}
.hb{padding:5px 13px;border-radius:18px;font-size:11.5px;font-weight:700;color:white;
  background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3)}
.tabs-c{background:white;border-bottom:1px solid var(--border);padding:0 40px;
  display:flex;position:sticky;top:68px;z-index:10}
.tc{padding:14px 17px;font-family:'Sora',sans-serif;font-size:13px;font-weight:600;
  color:#9a6070;background:none;border:none;border-bottom:3px solid transparent;cursor:pointer;transition:all .2s}
.tc:hover{color:var(--v)}
.tc.a{color:var(--vd);border-bottom-color:var(--v)}
.pills{background:white;border-bottom:1px solid var(--border);padding:16px 40px;display:flex;flex-wrap:wrap;gap:9px}
.pill{padding:7px 16px;border:none;border-radius:9px;font-family:'Sora',sans-serif;
  font-size:12px;font-weight:700;cursor:pointer;transition:all .2s;letter-spacing:.03em}
.pill:hover{opacity:.85;transform:translateY(-1px)}
.cont{padding:26px 40px}
.sec{display:none;animation:fi .3s ease}
.sec.a{display:block}
@keyframes fi{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:translateY(0)}}
.ib{background:linear-gradient(135deg,var(--vp),#fdf5f7);border:1.5px solid #e8c0cc;
  border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;gap:14px;align-items:flex-start}
.ib-ico{font-size:26px;flex-shrink:0;margin-top:2px}
.ib-t{font-size:15px;font-weight:700;color:var(--vd);margin-bottom:5px}
.ib-p{font-size:13px;color:#7a5060;line-height:1.6}
.stats-r{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:20px}
.stc{background:white;border-radius:13px;padding:16px 18px;text-align:center;
  box-shadow:var(--sh);border:1.5px solid var(--border)}
.stn{font-family:'DM Serif Display',serif;font-size:28px;color:var(--vd)}
.stl{font-size:11.5px;color:#9a6070;font-weight:600;margin-top:3px}
.al{display:flex;flex-direction:column;gap:9px}
.ai{display:flex;align-items:center;gap:13px;padding:12px 15px;border-radius:12px;
  background:var(--mist);border:1.5px solid var(--border);cursor:pointer;transition:all .25s;text-decoration:none;color:inherit}
.ai:hover{background:#faeef1;border-color:var(--vl);transform:translateX(3px)}
.ai.lk{opacity:.5;cursor:not-allowed}
.ai.lk:hover{transform:none;background:var(--mist);border-color:var(--border)}
.aico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.at{font-size:14px;font-weight:600;color:var(--ink);margin-bottom:2px}
.ad{font-size:12px;color:#9a6070}
.res-chip{display:inline-flex;align-items:center;gap:5px;background:#e8f5e9;color:#2e7d32;
  font-size:11px;font-weight:700;padding:3px 9px;border-radius:18px}
/* Tab content areas */
.tab-content{ display:block }
</style>

<div class="cw">
<aside class="sb">
  <div class="sb-hd">
    <a href="dashboard.php">✕</a>
    <span class="sb-cn">XVIII Olimpiadas Biffi</span>
  </div>
  <?php
  $sbs=[
    ['general','#1976d2','General',[['📋','Información']]],
    ['simulacros','#00897b','Simulacros',[['👋','Bienvenida'],['📗','Nivel Básico'],['📙','Nivel Medio'],['📕','Nivel Avanzado']]],
    ['clasificatoria','#7C1F30','Prueba Clasificatoria',[['🔒','Próximamente']]],
    ['selectiva','#C8A050','Prueba Selectiva',[['🔒','Próximamente']]],
    ['final','#f57c00','Prueba Final',[['🔒','Próximamente']]],
    ['biblioteca','#e53935','Biblioteca',[['📚','Ver material']]],
  ];
  foreach($sbs as [$k,$col,$lab,$items]):
    $o = ($k==='general') ? 'o' : '';
  ?>
  <div>
    <div class="sh <?=$o?>" onclick="this.classList.toggle('o');this.nextElementSibling.classList.toggle('o')">
      <span class="sd" style="background:<?=$col?>"></span>
      <span class="slb"><?=$lab?></span>
      <span class="sch">▼</span>
    </div>
    <div class="sis <?=$o?>">
      <?php foreach($items as [$ico,$lbl]): ?>
      <div class="si <?=$k==='general'&&$lbl==='Información'?'a':''?>"
           onclick="showSec('<?=$k?>',this)"><?=$ico?> <?=sanitize($lbl)?></div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endforeach ?>
</aside>

<main class="cm">
  <div class="hero">
    <div class="hc">
      <div class="bc"><a href="dashboard.php">Mis cursos</a> › <span>XVIII Olimpiadas Biffi 2026</span></div>
      <h1>XVIII Olimpiadas de Matemáticas Biffi — Secundaria 2026</h1>
      <div class="hbdg">
        <span class="hb">🏆 Competencia</span><span class="hb">📅 2026</span>
        <span class="hb">🎓 Secundaria</span><span class="hb">⚡ Activo</span>
      </div>
      <?php if($flash_err): ?>
      <div style="margin-top:12px;background:rgba(229,57,53,.25);border:1.5px solid rgba(229,57,53,.4);
        border-radius:10px;padding:10px 16px;color:white;font-size:13px;font-weight:600">
        ⚠️ <?=sanitize($flash_err)?>
      </div>
      <?php endif ?>
    </div>
  </div>

  <div class="tabs-c">
    <button class="tc a" onclick="showTab('curso',this)">Curso</button>
    <button class="tc" onclick="showTab('participantes',this)">Participantes</button>
    <button class="tc" onclick="showTab('calificaciones',this)">Mis calificaciones</button>
  </div>

  <!-- ─── TAB CURSO ──────────────────────────────────────────── -->
  <div id="tab-curso" class="tab-content">
  <div class="pills">
    <button class="pill" style="background:#1976d2;color:white"    onclick="goSec('general')">General</button>
    <button class="pill" style="background:#00897b;color:white"    onclick="goSec('simulacros')">Simulacros</button>
    <button class="pill" style="background:var(--v);color:white"   onclick="goSec('clasificatoria')">Clasificatoria</button>
    <button class="pill" style="background:var(--gold);color:#1A0A0F" onclick="goSec('selectiva')">Selectiva</button>
    <button class="pill" style="background:#f57c00;color:white"    onclick="goSec('final')">Final</button>
    <button class="pill" style="background:#e53935;color:white"    onclick="goSec('biblioteca')">Biblioteca</button>
  </div>

  <div class="cont">

    <!-- GENERAL -->
    <div id="sec-general" class="sec a">
      <div class="ib"><span class="ib-ico">🏅</span>
        <div><div class="ib-t">¡Bienvenido a las XVIII Olimpiadas!</div>
          <div class="ib-p">Aquí encontrarás simulacros, pruebas y material de estudio. Comunícate con tus docentes desde el menú de Mensajes. ¡Mucho éxito!</div></div></div>
      <div class="stats-r">
        <div class="stc"><div class="stn">3</div><div class="stl">Simulacros</div></div>
        <div class="stc"><div class="stn"><?=count($res_map)?>/3</div><div class="stl">Completados</div></div>
        <div class="stc"><div class="stn">2026</div><div class="stl">Edición</div></div>
        <div class="stc"><div class="stn">XVIII</div><div class="stl">Olimpiada</div></div>
      </div>
    </div>

    <!-- SIMULACROS -->
    <div id="sec-simulacros" class="sec">
      <?php $mi_grupo_local = miGrupo(); ?>
      <div class="ib"><span class="ib-ico">📝</span>
        <div>
          <div class="ib-t">Simulacros de práctica — <?=etiquetaGrupo($mi_grupo_local)?></div>
          <div class="ib-p">Preguntas diseñadas para tu grupo. Practica sin límite de intentos antes de las pruebas oficiales.</div>
        </div>
      </div>
      <div class="al">
        <a href="pruebas.php?tipo=simulacro" class="ai">
          <div class="aico" style="background:#e8f5e9">📝</div>
          <div style="flex:1"><div class="at">Ir a Simulacros</div>
          <div class="ad">Elige tu nivel y comienza a practicar</div></div>
          <span class="badge b-g">Disponible</span>
        </a>
      </div>
    </div>

    <!-- CLASIFICATORIA -->
    <div id="sec-clasificatoria" class="sec">
      <?php $hab_clas = pruebaHabilitada($pdo, 'clasificatoria', miGrupo()); ?>
      <div class="ib" style="border-color:<?=$hab_clas?'#a5d6a7':'#ffcdd2'?>;background:<?=$hab_clas?'linear-gradient(135deg,#e8f5e9,#f1f8e9)':'linear-gradient(135deg,#fde8e8,#fef5f5)'?>">
        <span class="ib-ico"><?=$hab_clas?'🎯':'🔒'?></span>
        <div>
          <div class="ib-t">Prueba Clasificatoria 2026</div>
          <div class="ib-p"><?=$hab_clas?'¡La prueba clasificatoria está activa! Selecciona tu nivel y comienza.':'Esta prueba aún no está disponible. El docente la habilitará en la fecha indicada.'?></div>
        </div>
      </div>
      <div class="al">
        <a href="<?=$hab_clas?'pruebas.php?tipo=clasificatoria':'#'?>" class="ai <?=!$hab_clas?'lk':''?>">
          <div class="aico" style="background:<?=$hab_clas?'#faeef1':'#f0f0f0'?>">🎯</div>
          <div style="flex:1">
            <div class="at">Ir a Clasificatoria</div>
            <div class="ad"><?=$hab_clas?'Elige tu nivel y comienza':'🔒 Prueba no habilitada'?></div>
          </div>
          <span class="badge <?=$hab_clas?'b-g':'b-gray'?>"><?=$hab_clas?'Disponible':'Bloqueado'?></span>
        </a>
      </div>
    </div>

    <!-- SELECTIVA -->
    <div id="sec-selectiva" class="sec">
      <?php $hab_sel = pruebaHabilitada($pdo,'selectiva',miGrupo()); ?>
      <div class="ib" style="border-color:#f0d060;background:linear-gradient(135deg,#fffbea,#fffdf4)">
        <span class="ib-ico"><?=$hab_sel?'⭐':'🔒'?></span>
        <div><div class="ib-t">Prueba Selectiva 2026</div>
          <div class="ib-p"><?=$hab_sel?'¡Prueba selectiva activa! Selecciona tu nivel.':'Solo para clasificados. Sigue practicando con los simulacros.'?></div></div>
      </div>
      <div class="al">
        <a href="<?=$hab_sel?'pruebas.php?tipo=selectiva':'#'?>" class="ai <?=!$hab_sel?'lk':''?>">
          <div class="aico" style="background:<?=$hab_sel?'#fff8e1':'#f0f0f0'?>">⭐</div>
          <div style="flex:1"><div class="at">Ir a Selectiva</div>
            <div class="ad"><?=$hab_sel?'Elige tu nivel y comienza':'🔒 No habilitada'?></div></div>
          <span class="badge <?=$hab_sel?'b-gold':'b-gray'?>"><?=$hab_sel?'Disponible':'Bloqueado'?></span>
        </a>
      </div>
    </div>

    <!-- FINAL -->
    <div id="sec-final" class="sec">
      <?php $hab_fin = pruebaHabilitada($pdo,'final',miGrupo()); ?>
      <div class="ib" style="border-color:#ffcc80;background:linear-gradient(135deg,#fff3e0,#fff8f0)">
        <span class="ib-ico"><?=$hab_fin?'🏆':'🔒'?></span>
        <div><div class="ib-t">Prueba Final 2026</div>
          <div class="ib-p"><?=$hab_fin?'¡La Gran Final está abierta! Selecciona tu nivel.':'La gran final. Solo para finalistas seleccionados.'?></div></div>
      </div>
      <div class="al">
        <a href="<?=$hab_fin?'pruebas.php?tipo=final':'#'?>" class="ai <?=!$hab_fin?'lk':''?>">
          <div class="aico" style="background:<?=$hab_fin?'#fff3e0':'#f0f0f0'?>">🏆</div>
          <div style="flex:1"><div class="at">Ir a Final</div>
            <div class="ad"><?=$hab_fin?'Elige tu nivel y comienza':'🔒 Solo finalistas'?></div></div>
          <span class="badge <?=$hab_fin?'b-red':'b-gray'?>"><?=$hab_fin?'Disponible':'Bloqueado'?></span>
        </a>
      </div>
    </div>

    <!-- BIBLIOTECA -->
    <div id="sec-biblioteca" class="sec">
      <div class="ib" style="border-color:#ffb3b3;background:linear-gradient(135deg,#fde8e8,#fef5f5)">
        <span class="ib-ico">📚</span>
        <div><div class="ib-t">Biblioteca de recursos</div><div class="ib-p">PDFs, videos y material descargable. Los docentes suben nuevo material regularmente.</div></div></div>
      <div class="al">
        <?php foreach($recursos as $rec):
          $ico=['pdf'=>'📄','video'=>'🎥','zip'=>'📦','enlace'=>'🔗','imagen'=>'🖼️'][$rec['tipo']]??'📄';
          $url=str_starts_with($rec['archivo'],'http')?$rec['archivo']:SITE_URL.'/'.$rec['archivo'];
        ?>
        <a href="<?=$url?>" target="_blank" class="ai">
          <div class="aico" style="background:#fde8e8"><?=$ico?></div>
          <div style="flex:1"><div class="at"><?=sanitize($rec['titulo'])?></div>
            <div class="ad"><?=sanitize($rec['descripcion']??'')?></div></div>
          <span class="badge b-red"><?=strtoupper($rec['tipo'])?></span>
        </a>
        <?php endforeach ?>
        <?php if(empty($recursos)): ?>
        <div style="padding:24px;text-align:center;color:#9a6070;font-size:13px">
          La biblioteca está vacía. Los docentes subirán material pronto.<br>
          <a href="biblioteca.php" class="btn btn-v btn-sm" style="margin-top:12px;display:inline-flex">Ver biblioteca completa →</a>
        </div>
        <?php else: ?>
        <a href="biblioteca.php" class="btn btn-outline" style="margin-top:4px;align-self:flex-start">Ver biblioteca completa →</a>
        <?php endif ?>
      </div>
    </div>

  </div></div><!-- /cont /tab-curso -->

  <!-- ─── TAB PARTICIPANTES ──────────────────────────────────── -->
  <div id="tab-participantes" class="tab-content" style="display:none">
  <div class="cont">
    <div class="ib"><span class="ib-ico">👥</span>
      <div><div class="ib-t">Participantes — <?=etiquetaGrupo(miGrupo())?></div>
        <div class="ib-p">Estudiantes inscritos en tu mismo grupo de grados para las XVIII Olimpiadas Biffi 2026.</div></div></div>
    <?php
    $mi_g = miGrupo();
    $grados_grupo = ['4-5'=>[4,5],'6-7'=>[6,7],'8-9'=>[8,9],'10-11'=>[10,11]][$mi_g]??[10,11];
    $partic = $pdo->prepare("SELECT nombre,apellido,grado,curso,nivel FROM usuarios
      WHERE rol='estudiante' AND activo=1 AND grado IN (".implode(',',$grados_grupo).")
      ORDER BY grado,nombre");
    $partic->execute();
    $lista_p=$partic->fetchAll();
    ?>
    <div style="background:white;border-radius:14px;overflow:hidden;box-shadow:var(--sh);border:1.5px solid var(--border)">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="background:var(--vd);color:white">
            <th style="padding:11px 16px;text-align:left;font-size:11.5px;letter-spacing:.04em">#</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Nombre</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Grado</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Salón</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Nivel</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($lista_p)): ?>
          <tr><td colspan="5" style="padding:32px;text-align:center;color:#9a6070">No hay participantes registrados aún.</td></tr>
          <?php else: foreach($lista_p as $i=>$p):
            $nc=['basico'=>'b-v','medio'=>'b-gold','avanzado'=>'b-green'][$p['nivel']??'basico']??'b-v';
          ?>
          <tr style="border-bottom:1px solid var(--vp);<?=($i%2==0)?'background:#fdf8f9':''?>">
            <td style="padding:10px 16px;color:#9a6070;font-weight:700"><?=$i+1?></td>
            <td style="padding:10px 16px">
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--vp);
                  display:flex;align-items:center;justify-content:center;font-weight:800;
                  color:var(--vd);font-size:12px;flex-shrink:0">
                  <?=strtoupper(substr($p['nombre'],0,1).substr($p['apellido'],0,1))?>
                </div>
                <strong><?=sanitize($p['nombre'].' '.$p['apellido'])?></strong>
              </div>
            </td>
            <td style="padding:10px 16px"><?=$p['grado']?>'°':'—'?></td>
            <td style="padding:10px 16px"><?=sanitize($p['curso']??'—')?></td>
            <td style="padding:10px 16px"><span class="badge <?=$nc?>"><?=ucfirst($p['nivel']??'básico')?></span></td>
          </tr>
          <?php endforeach; endif ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:12px;color:#9a6070;margin-top:12px">Total: <?=count($lista_p)?> participante(s) en <?=etiquetaGrupo($mi_g)?>.</p>
  </div>
  </div><!-- /tab-participantes -->

  <!-- ─── TAB CALIFICACIONES ─────────────────────────────────── -->
  <div id="tab-calificaciones" class="tab-content" style="display:none">
  <div class="cont">
    <div class="ib"><span class="ib-ico">📊</span>
      <div><div class="ib-t">Mis calificaciones</div>
        <div class="ib-p">Resumen de todos tus simulacros y pruebas completadas.</div></div></div>
    <?php
    $mis_res=$pdo->prepare("SELECT * FROM resultados WHERE usuario_id=? ORDER BY fecha DESC");
    $mis_res->execute([$_SESSION['user_id']]);
    $todos_res=$mis_res->fetchAll();
    $tipos_lbl=['simulacro'=>'Simulacro','clasificatoria'=>'Clasificatoria','selectiva'=>'Selectiva','final'=>'Final'];
    $niveles_lbl2=['basico'=>'Básico','medio'=>'Medio','avanzado'=>'Avanzado'];
    ?>
    <?php if(empty($todos_res)): ?>
    <div style="background:white;border-radius:14px;padding:48px;text-align:center;
      box-shadow:var(--sh);border:2px dashed var(--border);color:#9a6070">
      <div style="font-size:48px;margin-bottom:12px">🎯</div>
      <p style="font-size:14px">Aún no has completado ninguna prueba.<br>¡Ve a Simulacros y comienza a practicar!</p>
      <button onclick="showTab('curso',document.querySelector('.tc'))" class="btn btn-v" style="margin-top:16px">Ir a simulacros →</button>
    </div>
    <?php else:
      $mejor=max(array_map(fn($r)=>$r['total']>0?round($r['puntaje']/$r['total']*100):0,$todos_res));
      $promedio=round(array_sum(array_map(fn($r)=>$r['total']>0?round($r['puntaje']/$r['total']*100):0,$todos_res))/count($todos_res));
    ?>
    <!-- Resumen rápido -->
    <div class="stats-r" style="margin-bottom:20px">
      <div class="stc"><div class="stn"><?=count($todos_res)?></div><div class="stl">Intentos totales</div></div>
      <div class="stc"><div class="stn"><?=$mejor?>%</div><div class="stl">Mejor resultado</div></div>
      <div class="stc"><div class="stn"><?=$promedio?>%</div><div class="stl">Promedio general</div></div>
    </div>
    <!-- Tabla de resultados -->
    <div style="background:white;border-radius:14px;overflow:hidden;box-shadow:var(--sh);border:1.5px solid var(--border)">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="background:var(--vd);color:white">
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Tipo de prueba</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Nivel</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Puntaje</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">%</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Tiempo</th>
            <th style="padding:11px 16px;text-align:left;font-size:11.5px">Fecha</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($todos_res as $i=>$r):
            // nivel tiene formato "basico_4-5_simulacro" o simplemente "basico"
            $parts=explode('_',$r['nivel']);
            $niv_lbl=ucfirst($parts[0]??$r['nivel']);
            $tipo_lbl='Simulacro';
            if(count($parts)>=3) $tipo_lbl=$tipos_lbl[$parts[2]]??ucfirst($parts[2]);
            $pct=$r['total']>0?round($r['puntaje']/$r['total']*100):0;
            $color=$pct>=80?'#2e7d32':($pct>=60?'#f57c00':'#c0392b');
          ?>
          <tr style="border-bottom:1px solid var(--vp);<?=($i%2==0)?'background:#fdf8f9':''?>">
            <td style="padding:10px 16px">
              <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;
                border-radius:14px;font-size:11px;font-weight:700;color:white;
                background:<?=colorTipo($parts[2]??'simulacro')?>"><?=$tipo_lbl?></span>
            </td>
            <td style="padding:10px 16px"><span class="badge b-v"><?=$niv_lbl?></span></td>
            <td style="padding:10px 16px;font-weight:700;font-family:'JetBrains Mono',monospace"><?=$r['puntaje']?>/<?=$r['total']?></td>
            <td style="padding:10px 16px;font-weight:700;color:<?=$color?>"><?=$pct?>%</td>
            <td style="padding:10px 16px;font-family:'JetBrains Mono',monospace;font-size:12px"><?=gmdate('i:s',$r['tiempo_seg'])?></td>
            <td style="padding:10px 16px;font-size:12px;color:#9a6070"><?=date('d/m/Y H:i',strtotime($r['fecha']))?></td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>
  </div><!-- /tab-calificaciones -->

</main>
</div>

<script>
// goSec: ensure tab-curso is visible, then switch section
function goSec(name) {
  // Make sure Curso tab is active
  document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
  document.querySelectorAll('.tc').forEach(b => b.classList.remove('a'));
  const tabCurso = document.getElementById('tab-curso');
  if(tabCurso){ tabCurso.style.display = 'block'; }
  const tabBtn = document.querySelector('.tc');
  if(tabBtn) tabBtn.classList.add('a');
  // Switch section
  showSec(name, null);
  // Scroll content into view
  const s = document.getElementById('sec-'+name);
  if(s) s.scrollIntoView({behavior:'smooth', block:'start'});
}

function showTab(name, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
  document.querySelectorAll('.tc').forEach(b => b.classList.remove('a'));
  const target = document.getElementById('tab-' + name);
  if(target) target.style.display = 'block';
  if(btn) btn.classList.add('a');
}
function showSec(name, item) {
  document.querySelectorAll('.sec').forEach(s => s.classList.remove('a'));
  const t = document.getElementById('sec-' + name);
  if(t) t.classList.add('a');
  document.querySelectorAll('.si').forEach(i => i.classList.remove('a'));
  if(item) item.classList.add('a');
}
// Sidebar items also use goSec so they ensure the tab is visible
document.querySelectorAll('.si[onclick]').forEach(el => {
  const m = el.getAttribute('onclick').match(/showSec\('([^']+)'/);
  if(m){ el.setAttribute('onclick', `goSec('${m[1]}')`); }
});
</script>
<?php require_once 'includes/footer.php'; ?>
