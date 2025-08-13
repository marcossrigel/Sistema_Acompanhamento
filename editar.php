<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/config.php';
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Recife');

function null_if_empty($v) {
  $v = isset($v) ? trim($v) : null;
  return ($v === '' ? null : $v);
}
function time_to_input($t) {
  if (!$t) return '';
  return substr($t, 0, 5);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: listar_solicitacoes.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id               = (int)($_POST['id'] ?? 0);
  $demanda          = $_POST['demanda'] ?? '';
  $sei              = $_POST['sei'] ?? '';
  $codigo           = $_POST['codigo'] ?? '';
  $setor            = $_POST['setor'] ?? '';
  $responsavel      = $_POST['responsavel'] ?? '';
  $data_solicitacao = $_POST['data_solicitacao'] ?? '';
  $data_liberacao   = $_POST['data_liberacao'] ?? '';
  $tempo_medio      = $_POST['tempo_medio'] ?? '';
  $tempo_real       = $_POST['tempo_real'] ?? '';

  $erros = [];
  if ($demanda === '')          $erros[] = 'Demanda é obrigatória.';
  if ($sei === '')              $erros[] = 'SEI é obrigatório.';
  if ($codigo === '')           $erros[] = 'Código é obrigatório.';
  if ($setor === '')            $erros[] = 'Setor é obrigatório.';
  if ($responsavel === '')      $erros[] = 'Responsável é obrigatório.';
  if ($data_solicitacao === '') $erros[] = 'Data de Solicitação é obrigatória.';

  if ($erros) {
    $msg = urlencode(implode(' ', $erros));
    header("Location: editar.php?id={$id}&mensagem=erro&detalhe={$msg}");
    exit;
  }

  $data_liberacao = null_if_empty($data_liberacao);
  $tempo_medio    = null_if_empty($tempo_medio);
  $tempo_real     = null_if_empty($tempo_real);

  $sql = "UPDATE solicitacoes
          SET demanda=?, sei=?, codigo=?, setor=?, responsavel=?,
              data_solicitacao=?, data_liberacao=?, tempo_medio=?, tempo_real=?
          WHERE id=?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $msg = urlencode('Falha ao preparar o UPDATE: ' . $conn->error);
    header("Location: editar.php?id={$id}&mensagem=erro&detalhe={$msg}");
    exit;
}

$stmt->bind_param(
    "sssssssssi",
    $demanda,
    $sei,
    $codigo,
    $setor,
    $responsavel,
    $data_solicitacao,
    $data_liberacao,
    $tempo_medio,
    $tempo_real,
    $id
);

  if ($stmt->execute()) {
    header("Location: visualizar.php?mensagem=atualizado");
    exit;
  } else {
    $msg = urlencode('Erro ao atualizar: ' . $stmt->error);
    header("Location: editar.php?id={$id}&mensagem=erro&detalhe={$msg}");
    exit;
  }
}

    $stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $sol = $stmt->get_result()->fetch_assoc();
    if (!$sol) { header('Location: listar_solicitacoes.php'); exit; }
    $stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Editar Solicitação</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="./assets/css/editar.css">

</head>
<body>
<div class="wrap">
  <h1>Editar Solicitação</h1>

  <?php if (isset($_GET['mensagem'])): ?>
    <div class="alert <?php echo $_GET['mensagem']==='erro' ? 'error' : ''; ?>" style="display:block;">
      <?php echo $_GET['mensagem']==='erro' ? 'Erro: ' . htmlspecialchars($_GET['detalhe'] ?? '') : 'Atualizado com sucesso.'; ?>
    </div>
  <?php endif; ?>

  <form class="card" method="post" action="editar.php?id=<?php echo (int)$sol['id']; ?>">
    <input type="hidden" name="id" value="<?php echo (int)$sol['id']; ?>">

    <div class="linha">
      <div class="campo">
        <label>Demanda</label>
        <input type="text" name="demanda" required value="<?php echo htmlspecialchars($sol['demanda']); ?>">
      </div>
    </div>

    <div class="linha">
      <div class="campo">
        <label>SEI</label>
        <input type="text" name="sei" required value="<?php echo htmlspecialchars($sol['sei']); ?>">
      </div>
      <div class="campo">
        <label>Código</label>
        <input type="text" name="codigo" required value="<?php echo htmlspecialchars($sol['codigo']); ?>">
      </div>
      <div class="campo">
        <label>Setor</label>
        <select name="setor" required>
          <?php
            $opts = ['Gabinete','DAF','Gecomp','GOP','CPL','Jurídico','Gefin'];
            $atual = $sol['setor'];
            foreach ($opts as $o){
              $sel = ($o === $atual) ? 'selected' : '';
              echo "<option value=\"".htmlspecialchars($o)."\" $sel>".htmlspecialchars($o)."</option>";
            }
          ?>
        </select>
      </div>
    </div>

    <div class="linha">
      <div class="campo">
        <label>Responsável</label>
        <input type="text" name="responsavel" required value="<?php echo htmlspecialchars($sol['responsavel']); ?>">
      </div>
      <div class="campo">
        <label>Data Solicitação</label>
        <input type="date" name="data_solicitacao" required value="<?php echo htmlspecialchars($sol['data_solicitacao']); ?>">
      </div>
      <div class="campo">
        <label>Data Liberação</label>
        <input type="date" name="data_liberacao" value="<?php echo htmlspecialchars($sol['data_liberacao']); ?>">
      </div>
    </div>

    <div class="linha">
      <div class="campo">
        <label>Tempo Médio (hh:mm)</label>
        <input type="time" name="tempo_medio" value="<?php echo htmlspecialchars(time_to_input($sol['tempo_medio'])); ?>">
      </div>
      <div class="campo">
        <label>Tempo Real (Data)</label>
        <input type="date" name="tempo_real" value="<?php echo htmlspecialchars($sol['tempo_real']); ?>">
      </div>
    </div>

    <div class="acoes">
      <button class="btn primary" type="submit">Salvar</button>
      <a class="btn ghost" href="visualizar.php">&lt; Voltar</a>
    </div>
  </form>
</div>
</body>
</html>
