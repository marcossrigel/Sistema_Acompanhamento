<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;
date_default_timezone_set('America/Recife');

$token = $_GET['access_dinamic'] ?? '';
if ($token === '') { http_response_code(401); exit('Token inválido.'); }

$token = $connRemoto->real_escape_string($token);
$g_id = null;
if ($q = $connRemoto->query("SELECT g_id FROM token_sessao WHERE token = '$token' LIMIT 1")) {
  if ($q->num_rows) $g_id = $q->fetch_assoc()['g_id'];
}
if (!$g_id) { http_response_code(401); exit('Sessão inválida.'); }

$setor = '';
if ($r = $conn->query("SELECT setor FROM usuarios WHERE id_usuario_cehab_online = '$g_id' LIMIT 1")) {
  if ($r->num_rows) $setor = trim($r->fetch_assoc()['setor'] ?? '');
}
if ($setor === '') { http_response_code(401); exit('Setor do usuário não encontrado.'); }

function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function d($v){ return ($v && $v!=='0000-00-00') ? date('d/m/Y', strtotime($v)) : '—'; }
function dt($v){ return ($v && $v!=='0000-00-00 00:00:00') ? date('d/m/Y H:i:s', strtotime($v)) : '—'; }

$sql = "
SELECT
  s.id,
  s.demanda,
  s.sei,
  s.codigo,
  s.setor,
  s.responsavel,
  s.data_solicitacao,
  s.data_liberacao,
  s.data_registro,
  COALESCE(s.id_original, s.id) AS root_id,
  e.setor_destino AS prox_setor,
  e.data_encaminhamento AS quando_encaminhou
FROM solicitacoes s
LEFT JOIN (
  /* último encaminhamento por (id_demanda, setor_origem) */
  SELECT x.id_demanda, x.setor_origem, x.setor_destino, x.data_encaminhamento
  FROM encaminhamentos x
  JOIN (
    SELECT id_demanda, setor_origem, MAX(id) AS max_id
    FROM encaminhamentos
    GROUP BY id_demanda, setor_origem
  ) ult
    ON ult.id_demanda = x.id_demanda
   AND ult.setor_origem = x.setor_origem
   AND ult.max_id = x.id
) e
  ON e.id_demanda = COALESCE(s.id_original, s.id)
 AND e.setor_origem = s.setor_responsavel
WHERE s.setor_responsavel = ?
  AND s.data_liberacao IS NOT NULL
ORDER BY s.data_liberacao DESC, s.id DESC
";
$st = $conn->prepare($sql);
$st->bind_param('s', $setor);
$st->execute();
$rs = $st->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>Histórico</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/painel.css" rel="stylesheet">
  <style>.footer-actions{ text-align:center; margin:32px 0 12px; }</style>
</head>
<body>
<div class="container">
  <h1>Histórico — <?= e($setor) ?></h1>

  <?php if ($rs->num_rows === 0): ?>
    <div class="vazio">Nenhuma demanda finalizada pelo seu setor ainda.</div>
  <?php else: ?>
    <div id="lista-solicitacoes">
      <?php while($row = $rs->fetch_assoc()): ?>
        <div class="item">
          <button class="accordion" data-id="<?= (int)$row['id'] ?>">
            <span class="titulo"><?= e($row['demanda'] ?: '(sem título)') ?></span>
            <span class="seta">⌄</span>
          </button>

          <div class="panel" id="panel-<?= (int)$row['id'] ?>">
            <p>
              <span class="rot">SEI:</span> <?= e($row['sei']) ?> &nbsp; | &nbsp;
              <span class="rot">Código:</span> <?= e($row['codigo']) ?>
            </p>
            <p>
              <span class="rot">Recebido em:</span> <?= e(d($row['data_solicitacao'])) ?> &nbsp; | &nbsp;
              <span class="rot">Concluído em:</span> <?= e(d($row['data_liberacao'])) ?>
            </p>

            <?php if (!empty($row['prox_setor'])): ?>
              <p>
                <span class="rot">Encaminhada para:</span> <?= e($row['prox_setor']) ?>
                <?php if (!empty($row['quando_encaminhou'])): ?>
                  &nbsp; • &nbsp; <?= e(dt($row['quando_encaminhou'])) ?>
                <?php endif; ?>
              </p>
            <?php endif; ?>

          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

  <div class="footer-actions">
    <a class="btn-back" href="home_setor.php?access_dinamic=<?= urlencode($token) ?>">‹ Voltar</a>
  </div>
</div>

<script src="../js/painel.js"></script>
</body>
</html>
