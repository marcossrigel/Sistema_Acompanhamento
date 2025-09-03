<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
date_default_timezone_set('America/Recife');

$conn = $connLocal;

$token = trim($_GET['access_dinamic'] ?? '');
if ($token === '') { http_response_code(401); exit('Token inválido.'); }

$idPortal = (int)($_SESSION['id_portal'] ?? $_SESSION['id_usuario_cehab_online'] ?? 0);
if ($idPortal <= 0) { http_response_code(401); exit('Sessão inválida.'); }

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function d($v){ return ($v && $v!=='0000-00-00') ? date('d/m/Y', strtotime($v)) : '—'; }

$sql = "
  SELECT s.id, s.demanda, s.sei, s.codigo, s.setor, s.responsavel,
         s.data_solicitacao, s.data_liberacao, s.tempo_medio, s.tempo_real,
         s.enviado_para, s.setor_responsavel, s.data_registro
  FROM solicitacoes s
  WHERE s.id_usuario = ?
  ORDER BY s.id DESC
";
$st = $conn->prepare($sql);
$st->bind_param('i', $idPortal);
$st->execute();
$rs = $st->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Minhas Demandas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/painel.css" rel="stylesheet">
</head>
<body>
<div class="container">
  <h1>Minhas Demandas</h1>

  <?php if ($rs->num_rows === 0): ?>
    <div class="vazio">Você ainda não abriu nenhuma demanda.</div>
  <?php else: ?>
    <div id="lista-solicitacoes">
      <?php while($row = $rs->fetch_assoc()): ?>
        <div class="item">
          <button class="accordion">
            <span class="titulo"><?= e($row['demanda'] ?: '(sem título)') ?></span>
            <span class="seta">⌄</span>
          </button>
          <div class="panel">
            <p><span class="rot">SEI:</span> <?= e($row['sei']) ?> &nbsp; | &nbsp;
               <span class="rot">Código:</span> <?= e($row['codigo']) ?> &nbsp; | &nbsp;
               <span class="rot">Aberto em:</span> <?= e(d($row['data_solicitacao'])) ?></p>
            <p><span class="rot">Enviado para:</span> <?= e($row['enviado_para']) ?> &nbsp; | &nbsp;
               <span class="rot">Setor Responsável:</span> <?= e($row['setor_responsavel']) ?></p>
            <p><span class="rot">Tempo Médio:</span> <?= e($row['tempo_medio']) ?> &nbsp; | &nbsp;
               <span class="rot">Tempo Real (dias):</span> <?= e($row['tempo_real'] ?? '—') ?></p>

            <div class="toolbar">
              <a class="btn-like" href="formulario_comum.php?id=<?= (int)$row['id'] ?>">Formulário</a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>

<script src="../js/painel.js"></script>
</body>
</html>
