<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
date_default_timezone_set('America/Recife');

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$token = trim($_GET['access_dinamic'] ?? '');
if ($token === '') {
  http_response_code(401);
  exit('Token inválido ou ausente.');
}

$nome  = $_SESSION['nome']  ?? 'Usuário';
$setor = $_SESSION['setor'] ?? '—';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Área do Colaborador</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/home.css" rel="stylesheet">
  <style>
  .sub { 
    font-size: 14px; 
    color:#6b7280; 
    margin-top:-6px; 
  }
  .grid .card .name{ 
    margin-top:2px; 
  }

  a.logout,
  a.logout:visited,
  a.logout:hover,
  a.logout:focus {
    text-decoration: none;
  }
  </style>
</head>
<body>
  <main class="wrap">
    <h1 class="title">Olá, <?= e($nome) ?>👋</h1>
    <p class="sub">Setor: <strong><?= e($setor) ?></strong></p>

    <section class="grid" aria-label="Ações rápidas">
      <a class="card" href="formulario.php?access_dinamic=<?= urlencode($token) ?>">
        <div class="icon">🧾</div>
        <div class="name">Nova Demanda</div>
        <div class="desc">Abrir um novo processo</div>
      </a>
      
      <a class="card" href="minhas_demandas.php?access_dinamic=<?= urlencode($token) ?>">
        <div class="icon">🔎</div>
        <div class="name">Acompanhamento</div>
        <div class="desc">Visualizar o status e andamento</div>
      </a>
      
      <a class="card" href="painel.php?access_dinamic=<?= urlencode($token) ?>">
        <div class="icon">📦</div>
        <div class="name">Demandas</div>
        <div class="desc">Abrir as demandas recebidas</div>
      </a>

      <a class="card" href="historico.php?access_dinamic=<?= urlencode($token) ?>">
        <div class="icon">🗂️</div>
        <div class="name">Histórico</div>
        <div class="desc">Processos encaminhados </div>
      </a>
    </section>

    <div class="footer">
      <a href="https://www.getic.pe.gov.br/?p=home" class="logout">Sair</a>
    </div>
  </main>
</body>
</html>
