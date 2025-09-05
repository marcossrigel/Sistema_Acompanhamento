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

/* === ALTERAÇÃO: incluir campos da GECOMP no SELECT === */
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
  e.data_encaminhamento AS quando_encaminhou,
  s.gecomp_tr, s.gecomp_etp, s.gecomp_cotacao, s.gecomp_obs   -- << aqui
FROM solicitacoes s
LEFT JOIN (
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
  <style>
    .footer-actions{ text-align:center; margin:32px 0 12px; }
    /* === estilos só-leitura para a seção GECOMP === */
    .gecomp-hist .tags { display:flex; gap:8px; flex-wrap:wrap; margin:8px 0 4px; }
    .tag { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; }
    .tag.ok { background:#e6f7ea; color:#2f855a; border:1px solid #c6f0d0; }
    .tag.off{ background:#f1f5f9; color:#64748b; border:1px solid #e5e7eb; }
    .obs-read{ background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px; white-space:pre-wrap; }
    .gecomp-title{ font-weight:600; margin-top:10px; display:block; }
  </style>
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

            <?php
              // Mostra bloco GECOMP apenas quando o histórico é desse setor
              // (como a página já filtra por s.setor_responsavel = $setor)
              // ou quando há algum dado preenchido.
              $temGecomp = ($setor === 'GECOMP') ||
                           ((int)$row['gecomp_tr'] === 1 ||
                            (int)$row['gecomp_etp'] === 1 ||
                            (int)$row['gecomp_cotacao'] === 1 ||
                            trim((string)$row['gecomp_obs']) !== '');
              if ($temGecomp):
            ?>
              <div class="gecomp-hist">
                <span class="gecomp-title">Checklist / Observações — GECOMP (somente leitura)</span>
                <div class="tags">
                  <span class="tag <?= ((int)$row['gecomp_tr']===1?'ok':'off') ?>">TR <?= ((int)$row['gecomp_tr']===1?'✔':'—') ?></span>
                  <span class="tag <?= ((int)$row['gecomp_etp']===1?'ok':'off') ?>">ETP <?= ((int)$row['gecomp_etp']===1?'✔':'—') ?></span>
                  <span class="tag <?= ((int)$row['gecomp_cotacao']===1?'ok':'off') ?>">Cotação <?= ((int)$row['gecomp_cotacao']===1?'✔':'—') ?></span>
                </div>

                <?php if (trim((string)$row['gecomp_obs']) !== ''): ?>
                  <div class="obs-read"><?= nl2br(e($row['gecomp_obs'])) ?></div>
                <?php endif; ?>
              </div>
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
