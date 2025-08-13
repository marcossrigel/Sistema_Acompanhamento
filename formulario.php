<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/config.php';
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Recife');

$perfil = $_SESSION['tipo_usuario'] ?? ($_GET['perfil'] ?? 'solicitante');
$TEMPO_MEDIO_PADRAO = defined('TEMPO_MEDIO_PADRAO') ? TEMPO_MEDIO_PADRAO : '00:30';

$mensagem = '';
$detalhe = '';

function null_if_empty($v) {
  $v = isset($v) ? trim($v) : null;
  return ($v === '' ? null : $v);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $demanda          = $_POST['demanda'] ?? null;
  $sei              = $_POST['sei'] ?? '';
  $codigo           = $_POST['codigo'] ?? '';
  $setor            = $_POST['setor'] ?? '';
  $responsavel      = $_POST['responsavel'] ?? '';
  $data_solicitacao = $_POST['data_solicitacao'] ?? '';
  $data_liberacao   = $_POST['data_liberacao'] ?? '';
  $tempo_medio      = $_POST['tempo_medio'] ?? '';
  $tempo_real_dias  = isset($_POST['tempo_real_dias']) ? trim($_POST['tempo_real_dias']) : null;
  $tempo_real_form  = $_POST['tempo_real'] ?? null;

  $erros = [];
  if ($sei === '')              { $erros[] = 'SEI é obrigatório.'; }
  if ($codigo === '')           { $erros[] = 'Código é obrigatório.'; }
  if ($setor === '')            { $erros[] = 'Setor é obrigatório.'; }
  if ($responsavel === '')      { $erros[] = 'Responsável é obrigatório.'; }
  if ($data_solicitacao === '') { $erros[] = 'Data de Solicitação é obrigatória.'; }

  if ($erros) {
    $mensagem = 'erro';
    $detalhe = implode('<br>', $erros);
  } else {
    $demanda         = null_if_empty($demanda);
    $data_liberacao  = null_if_empty($data_liberacao);
    $tempo_medio     = null_if_empty($tempo_medio);

    if (strtolower($perfil) === 'solicitante') {
      $data_liberacao = date('Y-m-d');
      $tempo_medio = $TEMPO_MEDIO_PADRAO;
      $tempo_real = null;

      if ($data_solicitacao) {
        $t1 = new DateTime($data_solicitacao);
        $t2 = new DateTime($data_liberacao);
        $diff = $t1->diff($t2)->days;
        $tempo_real = (int) max(0, $diff);
      }
    } else {
      if (!empty($tempo_real_form)) {
        $t1 = new DateTime($data_solicitacao);
        $t2 = new DateTime($tempo_real_form);
        $tempo_real = (int) max(0, $t1->diff($t2)->days);
      } else {
        $tempo_real = null;
      }
    }

    $sql = "INSERT INTO solicitacoes
            (sei, codigo, setor, responsavel, data_solicitacao, data_liberacao, tempo_medio, tempo_real, demanda, data_registro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param("sssssssis", $sei, $codigo, $setor, $responsavel, $data_solicitacao, $data_liberacao, $tempo_medio, $tempo_real, $demanda);
      if ($stmt->execute()) {
        $mensagem = 'sucesso';
      } else {
        $mensagem = 'erro';
        $detalhe = 'Erro ao inserir: ' . $stmt->error;
      }
    } else {
      $mensagem = 'erro';
      $detalhe = 'Erro de preparação: ' . $conn->error;
    }
  }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Solicitação</title>
  <link rel="stylesheet" href="assets/css/formulario.css">
</head>
<body>
<div class="pagina-formulario">
<form class="formulario" method="post"
      data-perfil="<?= htmlspecialchars($perfil) ?>"
      data-tempo-medio="<?= htmlspecialchars($TEMPO_MEDIO_PADRAO) ?>">

  <h1 class="main-title">Nova Solicitação</h1>

  <div class="linha">
    <div class="campo-longo">
      <label class="label">Demanda</label>
      <input type="text" name="demanda" class="campo" placeholder="Descrição da demanda">
    </div>
    <div class="campo-pequeno">
      <label class="label">SEI</label>
      <input type="text" name="sei" class="campo" required placeholder="Ex: 1234567.000000/2025-00">
    </div>
    <div class="campo-pequeno">
      <label class="label">Código</label>
      <input type="text" name="codigo" class="campo" required placeholder="Ex: 001/2025">
    </div>
    <div class="campo-pequeno">
      <label class="label">Setor</label>
      <select name="setor" class="campo" required>
        <option value="">Selecione...</option>
        <option>Gabinete</option>
        <option>DAF</option>
        <option>Gecomp</option>
        <option>GOP</option>
        <option>CPL</option>
        <option>Jurídico</option>
        <option>Gefin</option>
      </select>
    </div>
  </div>

  <div class="linha">
    <div class="campo-pequeno">
      <label class="label">Responsável</label>
      <input type="text" name="responsavel" class="campo" required placeholder="Nome do responsável">
    </div>
    <div class="campo-pequeno" id="grupo-liberacao">
      <label class="label">Data Liberação</label>
      <input type="date" name="data_liberacao" id="data_liberacao" class="campo">
    </div>
  </div>

  <div class="linha">
    <div class="campo-pequeno" id="grupo-tempo-medio">
      <label class="label">Tempo Médio (hh:mm)</label>
      <input type="time" name="tempo_medio" id="tempo_medio" class="campo">
    </div>
    <div class="campo-pequeno" id="grupo-tempo-real">
      <label class="label">Tempo Real (Data)</label>
      <input type="date" name="tempo_real" id="tempo_real_date" class="campo">
    </div>
    <input type="hidden" name="tempo_real_dias" id="tempo_real_dias">
    <input type="hidden" name="data_solicitacao" id="data_solicitacao">
  </div>

  <button type="submit" class="btn btn-create-account">Salvar</button>
  <a href="home.php" class="texto-login">Cancelar</a>

</form>
</div>

<script>

document.addEventListener('DOMContentLoaded', function () {
  const perfil = document.querySelector('form').dataset.perfil;
  const campoLiberacao = document.getElementById('data_liberacao');
  const campoTempoMedio = document.getElementById('grupo-tempo-medio');
  const campoTempoReal = document.getElementById('grupo-tempo-real');

  if (campoTempoMedio) campoTempoMedio.style.display = 'none';
  if (campoTempoReal) campoTempoReal.style.display = 'none';

  if (perfil.toLowerCase() === 'solicitante') {
    const hoje = new Date();
    const yyyy = hoje.getFullYear();
    const mm = String(hoje.getMonth() + 1).padStart(2, '0');
    const dd = String(hoje.getDate()).padStart(2, '0');
    campoLiberacao.value = `${yyyy}-${mm}-${dd}`;
  }
});

(function () {
  const form = document.querySelector('form.formulario');
  const perfil = (form.dataset.perfil || '').toLowerCase();
  const isSolicitante = perfil === 'solicitante';

  const dataSolic = document.getElementById('data_solicitacao');
  const dataLib   = document.getElementById('data_liberacao');
  const tempoMed  = document.getElementById('tempo_medio');
  const tempoRealDias = document.getElementById('tempo_real_dias');
  const tempoMedioPadrao = form.dataset.tempoMedio || '00:30';

  const grupoLiberacao  = document.getElementById('grupo-liberacao');
  const grupoTempoMedio = document.getElementById('grupo-tempo-medio');
  const grupoTempoReal  = document.getElementById('grupo-tempo-real');

  function hojeLocalISO() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;;
  }

  function parseISO(v) {
    if (!v) return null;
    const [y,m,d] = v.split('-').map(Number);
    if (!y || !m || !d) return null;
    return new Date(Date.UTC(y, m - 1, d));
  }

  function diffDays(a, b) {
    if (!a || !b) return '';
    const ms = b.getTime() - a.getTime();
    return Math.max(0, Math.round(ms / 86400000));
  }

  function atualizarTempoReal() {
    const a = parseISO(dataSolic.value);
    const b = parseISO(dataLib.value);
    const dias = diffDays(a, b);
    if (isSolicitante) tempoRealDias.value = dias === '' ? '' : String(dias);
  }

  if (isSolicitante) {
    grupoTempoMedio?.classList.add('hidden');
    grupoTempoReal?.classList.add('hidden');

    if (dataLib)  dataLib.value = hojeLocalISO();
    if (tempoMed) tempoMed.value = tempoMedioPadrao;
    if (dataSolic) dataSolic.value = hojeLocalISO();

    dataLib.addEventListener('change', atualizarTempoReal);
    dataSolic.addEventListener('change', atualizarTempoReal);
    atualizarTempoReal();
  } else {
    grupoTempoMedio?.classList.remove('hidden');
    grupoTempoReal?.classList.remove('hidden');
  }
})();

function fecharModal() {
  document.getElementById('modal-sucesso').style.display = 'none';
  window.history.replaceState({}, document.title, window.location.pathname);
}

function hojeLocalISO() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function fecharModal() {
  const sucesso = document.getElementById('modal-sucesso');
  const erro = document.getElementById('modal-erro');
  if (sucesso) sucesso.style.display = 'none';
  if (erro) erro.style.display = 'none';

  // Remove os parâmetros da URL (ex: ?perfil=solicitante)
  window.history.replaceState({}, document.title, window.location.pathname);
}

</script>

<?php if ($mensagem === 'sucesso'): ?>
<div class="modal-overlay" id="modal-sucesso">
  <div class="modal">
    <h2>✅ Solicitação enviada com sucesso!</h2>
    <button onclick="fecharModal()">OK</button>
  </div>
</div>
<?php elseif ($mensagem === 'erro'): ?>
<div class="modal-overlay" id="modal-erro">
  <div class="modal">
    <h2 style="color:red">❌ Erro ao enviar solicitação</h2>
    <p><?= $detalhe ?></p>
    <button onclick="fecharModal()">OK</button>
  </div>
</div>
<?php endif; ?>


</body>
</html>
