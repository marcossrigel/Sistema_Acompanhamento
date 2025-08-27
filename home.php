<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
date_default_timezone_set('America/Recife');

$dbLocal  = $connLocal;
$dbRemoto = $connRemoto;

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// 1) Tenta usar a sessão; se não houver, tenta resolver pelo token da URL
$tokenGet = trim($_GET['access_dinamic'] ?? '');
$gId = (int)($_SESSION['id_portal'] ?? $_SESSION['id_usuario_cehab_online'] ?? 0);
$nomeUsuario = $_SESSION['nome'] ?? null;

if ($gId <= 0 && $tokenGet !== '' && $dbRemoto) {
  // Resolve g_id e nome a partir do token remoto
  $sql = "
    SELECT ts.g_id, u.u_nome_completo
    FROM token_sessao ts
    LEFT JOIN users u ON u.g_id = ts.g_id
    WHERE ts.token = ?
    ORDER BY ts.id DESC
    LIMIT 1";
  if ($st = $dbRemoto->prepare($sql)) {
    $st->bind_param('s', $tokenGet);
    $st->execute();
    if ($res = $st->get_result()) {
      if ($row = $res->fetch_assoc()) {
        $gId = (int)$row['g_id'];
        $nomeUsuario = $row['u_nome_completo'] ?? $nomeUsuario;
        // Preenche sessão mínima para o fluxo de solicitante
        $_SESSION['id_portal']                = $gId;
        $_SESSION['id_usuario_cehab_online']  = $gId;
        $_SESSION['nome']                     = $nomeUsuario;
        $_SESSION['setor']                    = $_SESSION['setor'] ?? 'Solicitante';
        $_SESSION['tipo_usuario']             = $_SESSION['tipo_usuario'] ?? 'solicitante';
      }
    }
    $st->close();
  }
}

// 2) Se ainda assim não tiver gId, bloqueia
if ($gId <= 0) {
  http_response_code(401);
  echo 'Sessão inválida ou token ausente.';
  exit;
}

// 3) Garante nome atualizado (caso a sessão exista mas o nome esteja vazio)
if ((!$nomeUsuario || $nomeUsuario === '') && $dbRemoto) {
  if ($st = $dbRemoto->prepare("SELECT u_nome_completo FROM users WHERE g_id = ? LIMIT 1")) {
    $st->bind_param('i', $gId);
    if ($st->execute()) {
      if ($r = $st->get_result()->fetch_assoc()) {
        $nomeUsuario = $r['u_nome_completo'] ?: ($nomeUsuario ?? 'Usuário');
        $_SESSION['nome'] = $nomeUsuario;
      }
    }
    $st->close();
  }
}

// Valor final do nome
$nomeUsuario = $nomeUsuario ?: 'Usuário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sistemas - Portal CEHAB</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link href="assets/css/home.css" rel="stylesheet">

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
