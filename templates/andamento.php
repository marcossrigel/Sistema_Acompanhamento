<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$_SESSION['tipo_usuario'] = $_SESSION['tipo_usuario'] ?? 'admin'; // exemplo

require_once __DIR__ . '/config.php';
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Recife');

function null_if_empty($v){ $v = isset($v)?trim($v):null; return ($v===''?null:$v); }
function time_to_input($t){ return $t ? substr($t,0,5) : ''; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: listar_solicitacoes.php'); exit; }

// carrega a linha
$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$sol = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sol){ echo "<h2 style='color:red;text-align:center'>Solicitação não encontrada.</h2>"; exit; }

// Salvar edições do setor (não é liberação!)
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $id               = (int)($_POST['id'] ?? 0);
  $demanda          = $_POST['demanda'] ?? '';
  $sei              = $_POST['sei'] ?? '';
  $codigo           = $_POST['codigo'] ?? '';
  $setor            = $_POST['setor'] ?? '';
  $responsavel      = $_POST['responsavel'] ?? '';
  $data_solicitacao = $_POST['data_solicitacao'] ?? '';

  // Data liberação NÃO é editável aqui; tempo_medio/tempo_real opcionais p/ admin
  $tempo_medio = null_if_empty($_POST['tempo_medio'] ?? null);
  $tempo_real  = null_if_empty($_POST['tempo_real']  ?? null);

  $erros=[];
  if($demanda==='')          $erros[]='Demanda é obrigatória.';
  if($sei==='')              $erros[]='SEI é obrigatório.';
  if($codigo==='')           $erros[]='Código é obrigatório.';
  if($setor==='')            $erros[]='Setor é obrigatório.';
  if($responsavel==='')      $erros[]='Responsável é obrigatório.';
  if($data_solicitacao==='') $erros[]='Data de Solicitação é obrigatória.';
  if($erros){
    $msg=urlencode(implode(' ',$erros));
    header("Location: andamento.php?id={$id}&mensagem=erro&detalhe={$msg}");
    exit;
  }

  // Atualiza a própria linha (sem mexer em data_liberacao)
  $sql="UPDATE solicitacoes
        SET demanda=?, sei=?, codigo=?, setor=?, responsavel=?,
            data_solicitacao=?, tempo_medio=?, tempo_real=?
        WHERE id=?";
  $stmt=$conn->prepare($sql);
  $stmt->bind_param("ssssssssi",
    $demanda,$sei,$codigo,$setor,$responsavel,
    $data_solicitacao,$tempo_medio,$tempo_real,$id
  );
  if($stmt->execute()){
    header("Location: andamento.php?id={$id}&mensagem=ok");
    exit;
  }else{
    $msg=urlencode('Erro ao atualizar: '.$stmt->error);
    header("Location: andamento.php?id={$id}&mensagem=erro&detalhe={$msg}");
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Processo Andamento</title>
<link rel="stylesheet" href="./assets/css/editar.css">
</head>
<body>
<div class="wrap">
  <h1>Processo Andamento</h1>

  <?php if (isset($_GET['mensagem'])): ?>
    <div class="alert <?= $_GET['mensagem']==='erro' ? 'error':'' ?>" style="display:block;">
      <?= $_GET['mensagem']==='erro' ? 'Erro: '.htmlspecialchars($_GET['detalhe'] ?? '') : 'Salvo com sucesso.' ?>
    </div>
  <?php endif; ?>

  <form class="card" method="post" action="andamento.php?id=<?= (int)$sol['id'] ?>">
    <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">

    <div class="linha">
      <div class="campo">
        <label>Demanda</label>
        <input type="text" name="demanda" required value="<?= htmlspecialchars($sol['demanda']) ?>">
      </div>
    </div>

    <div class="linha">
      <div class="campo">
        <label>SEI</label>
        <input type="text" name="sei" required value="<?= htmlspecialchars($sol['sei']) ?>">
      </div>
      <div class="campo">
        <label>Código</label>
        <input type="text" name="codigo" required value="<?= htmlspecialchars($sol['codigo']) ?>">
      </div>
      <div class="campo">
        <label>Setor</label>
        <select name="setor" required>
          <?php
          $opts=['Gabinete','DAF','Gecomp','GOP','CPL','Jurídico','Gefin'];
          $atual=$sol['setor'];
          foreach($opts as $o){
            $sel = ($o===$atual)?'selected':'';
            echo "<option value=\"".htmlspecialchars($o)."\" $sel>".htmlspecialchars($o)."</option>";
          }
          ?>
        </select>
      </div>
    </div>

    <div class="linha">
      <div class="campo">
        <label>Responsável</label>
        <input type="text" name="responsavel" required value="<?= htmlspecialchars($sol['responsavel']) ?>">
      </div>
      <div class="campo">
        <label>Data Solicitação</label>
        <input type="date" name="data_solicitacao" required value="<?= htmlspecialchars($sol['data_solicitacao']) ?>">
      </div>
      <div class="campo">
        <label>Data Liberação</label>
        <input type="date" value="<?= htmlspecialchars($sol['data_liberacao']) ?>" readonly>
      </div>
    </div>

    <?php if (($_SESSION['tipo_usuario'] ?? '') === 'admin'): ?>
    <div class="linha">
      <div class="campo">
        <label>Tempo Médio (hh:mm)</label>
        <input type="time" name="tempo_medio" value="<?= htmlspecialchars(time_to_input($sol['tempo_medio'])) ?>">
      </div>
      <div class="campo">
        <label>Tempo Real (Data)</label>
        <input type="date" name="tempo_real" value="<?= htmlspecialchars($sol['tempo_real']) ?>">
      </div>
    </div>
    <?php endif; ?>

    <div class="acoes">
      <button class="btn primary" type="submit">Salvar</button>
      <a class="btn ghost" href="javascript:history.back()">&lt; Voltar</a>
    </div>
  </form>
</div>
</body>
</html>
