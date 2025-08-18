<?php

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (!isset($_SESSION['id_usuario'])) {
    echo "<p style='color: red;'>Erro: Sessão de usuário não iniciada ou ID não disponível.</p>";
    exit;
}

require_once __DIR__ . '/config.php';
$conn->set_charset('utf8mb4');

$idUsuario = $_SESSION['id_usuario'] ?? 0;

$PRIMEIRO_SETOR = 'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS';

$sql = "
SELECT s.id, s.demanda, s.sei, s.codigo, s.setor, s.responsavel,
       s.data_solicitacao, s.data_liberacao, s.tempo_medio, s.tempo_real, s.data_registro
FROM solicitacoes s
WHERE s.id_usuario = ?
  AND s.setor_responsavel = ?
  AND s.id = (
        SELECT MIN(s2.id)
        FROM solicitacoes s2
        WHERE s2.id_usuario = s.id_usuario
          AND s2.sei = s.sei
          AND s2.codigo = s.codigo
          AND s2.setor_responsavel = s.setor_responsavel
  )
ORDER BY s.data_solicitacao DESC, s.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $idUsuario, $PRIMEIRO_SETOR);
$stmt->execute();
$result = $stmt->get_result();

function show($v) { return $v !== null && $v !== '' ? htmlspecialchars($v) : '—'; }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Solicitações</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="./assets/css/visualizar.css">
</head>
<body>
<div class="container">
  <h1>Solicitações</h1>

<?php if (!$result || $result->num_rows === 0): ?>
  <div class="vazio">Nenhuma solicitação cadastrada.</div>
<?php else: ?>
  <div id="lista-solicitacoes">
    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="item">
          <button class="accordion" data-id="<?php echo (int)$row['id']; ?>">
            <span class="titulo"><?php echo show($row['demanda']); ?></span>
            <span class="seta">⌄</span>
          </button>
          <div class="panel" id="panel-<?php echo (int)$row['id']; ?>">
            <p><span class="rot">SEI:</span> <?php echo show($row['sei']); ?> &nbsp; | &nbsp;
               <span class="rot">Código:</span> <?php echo show($row['codigo']); ?> &nbsp; | &nbsp;
               <span class="rot">Setor:</span> <?php echo show($row['setor']); ?></p>

            <p><span class="rot">Responsável:</span> <?php echo show($row['responsavel']); ?></p>

            <p><span class="rot">Data Solicitação:</span> <?php echo show($row['data_solicitacao']); ?> &nbsp; | &nbsp;
               <span class="rot">Data Liberação:</span> <?php echo show($row['data_liberacao']); ?></p>

            <p><span class="rot">Tempo Médio:</span> <?php echo show($row['tempo_medio']); ?> &nbsp; | &nbsp;
               <span class="rot">Tempo Real (Data):</span> <?php echo show($row['tempo_real']); ?></p>

            <p><span class="rot">Registrado em:</span> <?php echo show($row['data_registro']); ?></p>

            <div class="toolbar">
                <button onclick="editar(<?php echo (int)$row['id']; ?>)">Editar</button>
                <button onclick="excluir(<?php echo (int)$row['id']; ?>)">Excluir</button>
                <button onclick="acompanhamento(<?php echo (int)$row['id']; ?>)">Acompanhamento</button>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>

  <div class="botao-voltar">
    <a href="home.php">&lt; Voltar</a>
</div>

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
      localStorage.setItem('solicitacaoAberta', id);
    } else {
      localStorage.removeItem('solicitacaoAberta');
    }
  });
});

const abertaId = localStorage.getItem('solicitacaoAberta');
if (abertaId) {
  const btn = document.querySelector(`.accordion[data-id='${abertaId}']`);
  const panel = document.getElementById(`panel-${abertaId}`);
  if (btn && panel) {
    btn.classList.add('active');
    panel.style.display = 'block';
  }
}

function editar(id){ window.location.href = 'editar_solicitacao.php?id=' + id; }
function excluir(id){
  if (!confirm('Confirma a exclusão desta solicitação?')) return;
  document.getElementById('excluir-id').value = id;
  document.getElementById('form-excluir').submit();
}

function acompanhamento(id){
  window.location.href = 'acompanhamento.php?id=' + id;
}

function editar(id) {
    window.location.href = 'editar.php?id=' + id;
}
</script>
</body>

<form id="form-excluir" action="excluir_solicitacao.php" method="post" style="display:none;">
  <input type="hidden" name="id" id="excluir-id">
</form>

</html>
