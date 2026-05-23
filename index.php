<?php
require_once 'includes/config.php';
if(isLogged()){ header('Location: dashboard.php'); exit; }
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $u=trim($_POST['usuario']??''); $p=trim($_POST['contrasena']??'');
    if($u&&$p){
        $s=$pdo->prepare("SELECT * FROM usuarios WHERE usuario=? AND activo=1");
        $s->execute([$u]); $row=$s->fetch();
        if($row && password_verify($p,$row['contrasena'])){
            $_SESSION['user_id']=$row['id']; $_SESSION['nombre']=$row['nombre'];
            $_SESSION['apellido']=$row['apellido']; $_SESSION['usuario']=$row['usuario'];
	            $_SESSION['rol']=$row['rol']; $_SESSION['grado']=$row['grado']??null;
	            $_SESSION['nivel']=$row['nivel']??'basico'; $_SESSION['curso']=$row['curso']??null;
            $_SESSION['institucion_id']=$row['institucion_id']??null;
            $_SESSION['institucion_id']=$row['institucion_id']??null;
            header('Location: dashboard.php'); exit;
        } else $error='Usuario o contraseña incorrectos.';
    } else $error='Completa todos los campos.';
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ingresar — Biffi Olimpiadas</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap" rel="stylesheet">
<style>
:root{--v:#7C1F30;--vd:#4A0F1C;--vl:#A84358;--gold:#C8A050;--soft:#f7eef1;--ink:#211417}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Sora',sans-serif;min-height:100vh;display:flex;align-items:center;
  justify-content:center;background:
  radial-gradient(circle at top left,rgba(200,160,80,.12),transparent 24%),
  radial-gradient(circle at bottom right,rgba(124,31,48,.18),transparent 28%),
  linear-gradient(135deg,#1A0A0F,#4A0F1C,#2A0818);overflow:hidden;padding:18px}
.bg{position:absolute;inset:0;
  background-image:repeating-linear-gradient(60deg,rgba(200,160,80,.05) 0,rgba(200,160,80,.05) 1px,transparent 1px,transparent 50px),
  repeating-linear-gradient(-60deg,rgba(200,160,80,.05) 0,rgba(200,160,80,.05) 1px,transparent 1px,transparent 50px)}
.glow{position:absolute;width:500px;height:500px;border-radius:50%;
  background:radial-gradient(circle,rgba(124,31,48,.4),transparent 70%);
  top:-100px;right:-100px;animation:gl 6s ease-in-out infinite}
@keyframes gl{0%,100%{transform:scale(1)}50%{transform:scale(1.2)}}
.card{position:relative;width:min(480px,100%);background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(250,244,246,.92));
  border:1px solid rgba(255,255,255,.25);
  border-radius:28px;padding:44px 40px;box-shadow:0 32px 80px rgba(0,0,0,.42);animation:up .6s ease;overflow:hidden}
.card::before{content:'';position:absolute;inset:0 0 auto 0;height:132px;
  background:linear-gradient(135deg,var(--vd),var(--v) 60%,#a84358)}
.card::after{content:'';position:absolute;top:-54px;right:-54px;width:180px;height:180px;border-radius:50%;
  background:radial-gradient(circle,rgba(200,160,80,.22),transparent 68%)}
@keyframes up{from{opacity:0;transform:translateY(26px)}to{opacity:1;transform:translateY(0)}}
.logo{display:flex;align-items:center;gap:0;margin-bottom:30px;position:relative;z-index:1}
.lb{width:56px;height:56px;border-radius:13px;overflow:hidden;
  background:white;display:flex;align-items:center;justify-content:center;
  box-shadow:0 8px 24px rgba(0,0,0,.3);border:2px solid rgba(255,255,255,.25);flex-shrink:0}
.lb img{width:100%;height:100%;object-fit:contain;padding:4px}
.logo-divider{width:1px;height:36px;background:rgba(255,255,255,.2);margin:0 12px;flex-shrink:0}
.lt{margin-left:4px}
.lt strong{display:block;font-size:15px;font-weight:700;color:white;line-height:1.2}
.lt span{font-size:11px;color:rgba(255,255,255,.62)}
.lt{position:relative;z-index:1}
.kicker{display:inline-flex;align-items:center;padding:7px 12px;border-radius:999px;background:var(--soft);
  color:var(--v);font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px;position:relative;z-index:1}
h1{font-family:'DM Serif Display',serif;font-size:34px;color:var(--ink);margin-bottom:8px;position:relative;z-index:1;animation:welcomeIn .7s ease .1s both}
h1::before{content:'Acceso institucional';display:inline-flex;align-items:center;padding:7px 12px;border-radius:999px;background:var(--soft);
  color:var(--v);font-family:'Sora',sans-serif;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px}
h1::before{display:flex;width:max-content}
.wave{display:inline-block;transform-origin:70% 70%;animation:waveHello 2.2s ease-in-out 1s infinite}
@keyframes welcomeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@keyframes waveHello{
  0%,60%,100%{transform:rotate(0deg)}
  10%{transform:rotate(14deg)}
  20%{transform:rotate(-8deg)}
  30%{transform:rotate(14deg)}
  40%{transform:rotate(-4deg)}
  50%{transform:rotate(10deg)}
}
.sub{font-size:13px;color:#7c646b;margin-bottom:24px;line-height:1.7;position:relative;z-index:1}
.err{background:#fff1f1;border:1px solid #f0b7b7;border-radius:12px;
  padding:11px 14px;color:#c23939;font-size:13px;margin-bottom:16px;text-align:center;position:relative;z-index:1}
.grp{margin-bottom:16px}
label{display:block;font-size:11px;font-weight:700;color:#7b6068;
  letter-spacing:.1em;text-transform:uppercase;margin-bottom:7px}
input{width:100%;padding:14px 15px;background:#fff;
  border:1.5px solid #e8d9de;border-radius:14px;
  color:var(--ink);font-family:'Sora',sans-serif;font-size:14px;outline:none;transition:all .25s;position:relative;z-index:1}
input:focus{border-color:var(--gold);box-shadow:0 0 0 4px rgba(200,160,80,.16)}
input::placeholder{color:#b39ca3}
.sub-btn{width:100%;padding:13px;margin-top:6px;
  background:linear-gradient(135deg,var(--vl),var(--vd));border:none;border-radius:10px;
  color:white;font-family:'Sora',sans-serif;font-size:14px;font-weight:700;cursor:pointer;
  transition:all .25s;box-shadow:0 12px 28px rgba(124,31,48,.28);position:relative;z-index:1}
.sub-btn:hover{transform:translateY(-2px);box-shadow:0 16px 36px rgba(124,31,48,.36)}
.helper{margin-top:22px;padding-top:16px;border-top:1px solid #eadde1;position:relative;z-index:1;text-align:center}
.helper strong{display:block;font-size:12px;color:#7b6068;margin-bottom:8px}
.helper small{display:block;font-size:12px;color:#98727c;margin-top:4px}
@media(max-width:560px){
  .card{padding:34px 24px 30px}
  h1{font-size:30px}
}
</style>
</head>
<body>
<div class="bg"></div><div class="glow"></div>
<div class="card">
  <div class="logo">
    <?php $base=rtrim(str_replace('\\','/','http://'.$_SERVER['HTTP_HOST'].dirname(str_replace($_SERVER['DOCUMENT_ROOT'],'',$_SERVER['SCRIPT_FILENAME']))),'/'); ?>
    <div class="lb"><img src="<?=$base?>/assets/logo_biffi.png" alt="Colegio Biffi" onerror="this.style.display='none'"></div>
    <div class="logo-divider"></div>
    <div class="lb"><img src="<?=$base?>/assets/logo_apostol_math.png" alt="Apóstol Math" onerror="this.style.display='none'"></div>
    <div class="lt"><strong>Biffi Olimpiadas</strong><span>Plataforma de Competencia Matemática</span></div>
  </div>
  <h1>Bienvenido <span class="wave">👋</span></h1>
  <p class="sub">Ingresa tus credenciales para acceder</p>
  <?php if($error): ?><div class="err">⚠️ <?=sanitize($error)?></div><?php endif ?>
  <form method="POST">
    <div class="grp"><label>Usuario</label>
      <input type="text" name="usuario" placeholder="tu.usuario" value="<?=sanitize($_POST['usuario']??'')?>" required autofocus></div>
    <div class="grp"><label>Contraseña</label>
      <input type="password" name="contrasena" placeholder="••••••••" required></div>
    <button type="submit" class="sub-btn">Iniciar sesión →</button>
  </form>
  <div class="helper">
    <strong>"Las matemáticas no solo revelan respuestas: enseñan a pensar con precisión, belleza y propósito."</strong>
    <small>XVIII Olimpiadas de Matemáticas 2026 · Colegio Biffi de Cartagena</small>
  </div>

</div>
</body></html>
