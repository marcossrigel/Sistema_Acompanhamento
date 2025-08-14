<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;

$nomeSetorPainel = "Painel Ainda nao funcionando "; 

$token = $_GET['access_dinamic'] ?? '';
$token = $connRemoto->real_escape_string($token);

if (empty($token)) {
    echo "<h2 style='color: red; text-align: center;'>Token de acesso nulo ou não identificado.</h2>";
    exit;
}

$g_id = null;
$consultaToken = $connRemoto->query("SELECT g_id FROM token_sessao WHERE token = '$token' LIMIT 1");
if ($consultaToken && $consultaToken->num_rows > 0) {
    $g_id = $consultaToken->fetch_assoc()['g_id'];
}

if ($g_id) {
    $consultaSetor = $connLocal->query("SELECT setor FROM usuarios WHERE id_usuario_cehab_online = '$g_id' LIMIT 1");
    if ($consultaSetor && $consultaSetor->num_rows > 0) {
        $setor = $consultaSetor->fetch_assoc()['setor'];
        if ($setor) {
            $nomeSetorPainel = $setor;
        }
    }
}

$sql = "SELECT id, demanda, sei, codigo, setor, responsavel,
               data_solicitacao, data_liberacao, tempo_medio, tempo_real, data_registro,
               setor_responsavel
        FROM solicitacoes
        WHERE setor_responsavel = '$setor'
        ORDER BY data_solicitacao DESC, id DESC";

$res = $connLocal->query($sql);

function show($v) {
    return $v !== null && $v !== '' ? htmlspecialchars($v) : '—';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($nomeSetorPainel) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="./assets/css/visualizar.css">
</head>
<body>
<div class="container">
  <h1><?= htmlspecialchars($nomeSetorPainel) ?></h1>

  <?php if (!$res || $res->num_rows === 0): ?>
    <div class="vazio">Nenhuma solicitação recebida.</div>
  <?php else: ?>
    <div id="lista-solicitacoes">
      <?php while ($row = $res->fetch_assoc()): ?>
        <div class="item">
          <button class="accordion" data-id="<?= (int)$row['id'] ?>">
            <span class="titulo"><?= show($row['demanda']) ?></span>
            <span class="seta">⌄</span>
          </button>
          <div class="panel" id="panel-<?= (int)$row['id'] ?>">
            <p><span class="rot">SEI:</span> <?= show($row['sei']) ?> &nbsp; | &nbsp;
               <span class="rot">Código:</span> <?= show($row['codigo']) ?> &nbsp; | &nbsp;
               <span class="rot">Setor:</span> <?= show($row['setor']) ?></p>
            <p><span class="rot">Responsável:</span> <?= show($row['responsavel']) ?></p>
            <p><span class="rot">Data Solicitação:</span> <?= show($row['data_solicitacao']) ?> &nbsp; | &nbsp;
               <span class="rot">Data Liberação:</span> <?= show($row['data_liberacao']) ?></p>
            <p><span class="rot">Tempo Médio:</span> <?= show($row['tempo_medio']) ?> &nbsp; | &nbsp;
               <span class="rot">Tempo Real (Data):</span> <?= show($row['tempo_real']) ?></p>
            <p><span class="rot">Registrado em:</span> <?= show($row['data_registro']) ?></p>
            
            <div class="toolbar">
            <button onclick="window.location.href='andamento.php?id=<?= (int)$row['id'] ?>'">Andamento do Setor</button>
            <?php if ($row['setor_responsavel'] === $setor): ?>
              <form method="get" action="encaminhar.php" style="display:inline;">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="access_dinamic" value="<?= htmlspecialchars($_GET['access_dinamic']) ?>">
                <button type="submit">Encaminhar</button> <!-- herda .toolbar button -->
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

</div>

<script>

document.querySelectorAll('.accordion').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const panel = document.getElementById('panel-' + id);
    const isOpen = btn.classList.contains('active');
    document.querySelectorAll('.accordion').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.style.display = 'none');
    if (!isOpen) {
      btn.classList.add('active');
      panel.style.display = 'block';
      localStorage.setItem('solicitacaoAbertaPainel', id);
    } else {
      localStorage.removeItem('solicitacaoAbertaPainel');
    }
  });
});
const abertaId = localStorage.getItem('solicitacaoAbertaPainel');
if (abertaId) {
  const btn = document.querySelector(`.accordion[data-id='${abertaId}']`);
  const panel = document.getElementById(`panel-${abertaId}`);
  if (btn && panel) {
    btn.classList.add('active');
    panel.style.display = 'block';
  }
}

function andamentoSetor(id){
  window.location.href = 'andamento.php?id=' + id;
}

function encaminhar(id) {
  if (!confirm('Encaminhar para o próximo setor?')) return;
  window.location.href = 'liberar.php?id=' + id;
}

function encaminhar(id) {
  if (!confirm('Encaminhar para o próximo setor?')) return;
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('access_dinamic');
  window.location.href = 'liberar.php?id=' + id + '&access_dinamic=' + encodeURIComponent(token);
}
</script>
</body>
</html>
