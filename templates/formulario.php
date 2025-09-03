<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Recife');

// ===== retorno inteligente p/ o botão Cancelar =====
$token     = trim($_GET['access_dinamic'] ?? '');
$idPortal  = (int)($_SESSION['id_portal'] ?? $_SESSION['id_usuario_cehab_online'] ?? 0);

$isColaborador = false;
$setorUsuario  = ''; // <- NÃO usar $_SESSION['setor'] aqui

if ($idPortal > 0) {
  if ($st = $conn->prepare("SELECT setor FROM usuarios WHERE id_usuario_cehab_online = ? LIMIT 1")) {
    $st->bind_param('i', $idPortal);
    if ($st->execute()) {
      if ($row = $st->get_result()->fetch_assoc()) {
        $setorUsuario  = trim($row['setor'] ?? '');
        $isColaborador = ($setorUsuario !== '');
      }
    }
    $st->close();
  }
}

$cancelPage = $isColaborador ? 'home_setor.php' : 'home.php';
$cancelUrl  = $cancelPage . ($token !== '' ? ('?access_dinamic=' . urlencode($token)) : '');

$SETOR_OPCOES = ['DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS','GECOMP','DDO','CPL','DAF - HOMOLOGACAO','PARECER JUR','GEFIN NE INICIAL','GOP PF (SEFAZ)','GEFIN NE DEFINITIVO','LIQ','PD (SEFAZ)','OB','REMESSA'];

$perfil = $_SESSION['tipo_usuario'] ?? ($_GET['perfil'] ?? 'solicitante');
$TEMPO_MEDIO_PADRAO = defined('TEMPO_MEDIO_PADRAO') ? TEMPO_MEDIO_PADRAO : '00:30:00';

$mensagem = ''; $detalhe = '';

function null_if_empty($v){ $v = isset($v)?trim($v):null; return $v===''?null:$v; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $demanda          = $_POST['demanda'] ?? null;
  $sei              = $_POST['sei'] ?? '';
  $codigo           = $_POST['codigo'] ?? '';
  $setor = $isColaborador ? $setorUsuario : ($_POST['setor'] ?? '');
  $setor_original = $setor;
  $responsavel      = $_POST['responsavel'] ?? '';
  // Vamos sempre definir a data da solicitação no servidor (não precisa vir do form)
  $data_solicitacao = date('Y-m-d');
  $data_liberacao   = $_POST['data_liberacao'] ?? '';
  $tempo_medio      = $_POST['tempo_medio'] ?? '';
  $tempo_real_form  = $_POST['tempo_real'] ?? null;
  $enviado_para     = $_POST['enviado_para'] ?? '';

  $erros = [];
  if ($sei==='') $erros[]='SEI é obrigatório.';
  if ($codigo==='') $erros[]='Código é obrigatório.';
  if ($setor==='') $erros[]='Setor é obrigatório.';
  if ($responsavel==='') $erros[]='Responsável é obrigatório.';
  if ($enviado_para==='') $erros[]='Selecione o setor de destino (Enviar demanda para).';
  if ($enviado_para && !in_array($enviado_para,$SETOR_OPCOES,true)) $erros[]='Destino inválido.';

  if ($erros) {
    $mensagem='erro'; $detalhe=implode('<br>',$erros);
  } else {
    $demanda        = null_if_empty($demanda);
    $data_liberacao = null_if_empty($data_liberacao);
    $data_liberacao_original = $data_liberacao;
    $tempo_medio = null_if_empty($tempo_medio);

    if (strtolower($perfil)==='solicitante') {
      // solicitante: define tempo_medio padrão e tempo_real 0
      $tempo_medio = $TEMPO_MEDIO_PADRAO;
      $tempo_real = 0;
    } else {
      if ($tempo_medio && strlen($tempo_medio)===5) $tempo_medio .= ':00';
      if (!empty($tempo_real_form)) {
        $t1 = new DateTime($data_solicitacao);
        $t2 = new DateTime($tempo_real_form);
        $tempo_real = (int)max(0,$t1->diff($t2)->days);
      } else {
        $tempo_real = null;
      }
    }

    $setor_responsavel = $enviado_para;

    $sql = "INSERT INTO solicitacoes (
              id_usuario, demanda, sei, codigo, setor, setor_original, responsavel,
              data_solicitacao, data_liberacao, data_liberacao_original,
              tempo_medio, tempo_real, enviado_para, data_registro, setor_responsavel
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

    if ($stmt = $conn->prepare($sql)) {
      $idUsuarioPortal = (int)($_SESSION['id_portal'] ?? 0);
      $stmt->bind_param(
        "issssssssssiss",
        $idUsuarioPortal, $demanda, $sei, $codigo, $setor, $setor_original, $responsavel,
        $data_solicitacao, $data_liberacao, $data_liberacao_original,
        $tempo_medio, $tempo_real, $enviado_para, $setor_responsavel
      );

      if ($stmt->execute()) {
        $mensagem = 'sucesso';
        $rootId = $stmt->insert_id;

        $updRoot = $conn->prepare("UPDATE solicitacoes SET id_original = ? WHERE id = ?");
        $updRoot->bind_param("ii", $rootId, $rootId);
        $updRoot->execute();
        $updRoot->close();

        $sqlEnc = "INSERT INTO encaminhamentos
                    (id_demanda, setor_origem, setor_destino, status, data_encaminhamento)
                   VALUES (?, ?, ?, 'Em andamento', NOW())";
        $stmtEnc = $conn->prepare($sqlEnc);
        $setorOrigem = 'DEMANDANTE';
        $stmtEnc->bind_param("iss", $rootId, $setorOrigem, $enviado_para);
        $stmtEnc->execute();
        $stmtEnc->close();
      } else {
        $mensagem='erro'; $detalhe='Erro ao inserir: '.$stmt->error;
      }
      $stmt->close();
    } else {
      $mensagem='erro'; $detalhe='Erro de preparação: '.$conn->error;
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
  <link rel="stylesheet" href="../assets/css/formulario.css">
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
      <label class="label">Código do Processo</label>
      <input type="text" name="codigo" class="campo" required placeholder="Ex: 001/2025">
    </div>

    <div class="campo-pequeno">
    <label class="label">Setor</label>

    <?php if ($isColaborador && $setorUsuario !== ''): ?>
      <input type="text" class="campo" value="<?= htmlspecialchars($setorUsuario) ?>" readonly>
      <input type="hidden" name="setor" value="<?= htmlspecialchars($setorUsuario) ?>">
    <?php else: ?>
      <select name="setor" class="campo" required>
        <option value="">Selecione...</option>
        <option>Gabinete</option>
        <option>DAF</option>
        <option>DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS</option>
        <option>Gecomp</option>
        <option>GOP</option>
        <option>CPL</option>
        <option>Jurídico</option>
        <option>Gefin</option>
      </select>
    <?php endif; ?>
  </div>

  </div>

  <div class="linha">
    <div class="campo-pequeno">
      <label class="label">Responsável</label>
      <input type="text" name="responsavel" class="campo" required placeholder="Nome do responsável">
    </div>
    <div class="campo-pequeno" id="grupo-liberacao">
      <label class="label">Data Liberação</label>
      <input type="date" name="data_liberacao" id="data_liberacao" class="campo" required>
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

    <div class="campo-pequeno">
      <label class="label">Enviar demanda para</label>
      <select name="enviado_para" class="campo" required>
        <option value="">Selecione...</option>
        <?php foreach ($SETOR_OPCOES as $opt): ?>
          <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <button type="submit" class="btn btn-create-account">Salvar</button>
  <a href="<?= htmlspecialchars($cancelUrl) ?>" class="texto-login">Cancelar</a>

</form>
</div>

<script src="../js/formulario.js"></script>
<script>
  // Preenche automaticamente a Data de Liberação com a data local do navegador
  (function () {
    var el = document.getElementById('data_liberacao');
    if (el && !el.value) {
      el.valueAsDate = new Date();
    }
  })();
</script>

<?php if ($mensagem === 'sucesso'): ?>
<div class="modal-overlay" id="modal-sucesso">
  <div class="modal">
    <h2>✅ Solicitação enviada com sucesso!</h2>
    <button onclick="window.location.href='<?= htmlspecialchars($cancelUrl) ?>'">OK</button>
  </div>
</div>
<?php elseif ($mensagem === 'erro'): ?>
<div class="modal-overlay" id="modal-erro">
  <div class="modal">
    <h2 style="color:red">❌ Erro ao enviar solicitação</h2>
    <p><?= $detalhe ?></p>
    <button onclick="document.getElementById('modal-erro').remove()">OK</button>
  </div>
</div>
<?php endif; ?>
</body>
</html>
