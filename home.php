<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
date_default_timezone_set('America/Recife');

if (!isset($_SESSION['id_usuario'])) {
  header('Location: index.php');
  exit;
}

$dbRemoto = $connRemoto ?? ($conexao2 ?? null);

$portalId = (int)($_SESSION['id_usuario_cehab_online'] ?? 0);

$nomeUsuario = $_SESSION['nome'] ?? 'Usuário';

if ($portalId > 0 && $dbRemoto) {
  if ($st = $dbRemoto->prepare("SELECT u_nome_completo FROM users WHERE g_id = ? LIMIT 1")) {
    $st->bind_param('i', $portalId);
    if ($st->execute()) {
      $r = $st->get_result();
      if ($r && ($row = $r->fetch_assoc()) && !empty($row['u_nome_completo'])) {
        $nomeUsuario = $row['u_nome_completo'];
      }
    }
    $st->close();
  }
}

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sistemas - Portal CEHAB</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{ --bg:#edf3f7; --card:#fff; --text:#1d2129; --muted:#6b7280; --shadow:0 8px 20px rgba(0,0,0,.08); --primary:#2563eb; --danger:#ef4444;}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;color:var(--text);}
.wrap{width:100%;max-width:800px;padding:32px 20px 48px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:28px;min-height:100vh;}
.title{width:100%;text-align:center;font-weight:700;font-size:28px;}
.grid{display:flex;justify-content:center;gap:22px;flex-wrap:wrap;}
.card{width:220px;height:140px;background:var(--card);border-radius:16px;box-shadow:var(--shadow);padding:18px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-decoration:none;transition:transform .15s, box-shadow .15s, border-color .15s;border:1px solid rgba(0,0,0,.04);}
.card:hover,.card:focus{transform:translateY(-2px);box-shadow:0 12px 28px rgba(0,0,0,.12);outline:none;}
.card .icon{font-size:32px;line-height:1;margin-bottom:12px;}
.card .name{text-align:center;font-weight:700;font-size:18px;color:var(--text);}
.card .desc{margin-top:6px;font-size:12px;color:var(--muted);text-align:center;}
.footer{margin-top:20px;}
.logout{border:0;cursor:pointer;padding:10px 16px;border-radius:999px;color:#fff;background:var(--danger);font-weight:700;box-shadow:var(--shadow);transition:filter .15s;}
.logout:hover{filter:brightness(.95);}
</style>
</head>
<body>
  <main class="wrap">
    <h1 class="title">Olá, <?= e($nomeUsuario) ?> 👋</h1>
    <h1 class="title">Seja Bem-Vindo ao Acompanhamento SEI</h1>

    <section class="grid" aria-label="Lista de sistemas">
      <a class="card" href="formulario.php">
        <div class="icon">🧾</div>
        <div class="name">Nova Demanda</div>
        <div class="desc">Inicie um novo processo</div>
      </a>

      <a class="card" href="visualizar.php">
        <div class="icon">🔍</div>
        <div class="name">Acompanhamento</div>
        <div class="desc">Status e Atualizações</div>
      </a>
    </section>

    <div class="footer">
      <a href="https://www.getic.pe.gov.br/?p=home" class="logout">Sair</a>
    </div>
  </main>
</body>
</html>
