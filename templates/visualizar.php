<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;

if (!isset($_SESSION['id_portal']) || (int)$_SESSION['id_portal'] <= 0) {
  header('Location: index.php');
  exit;
}
$meuIdPortal = (int)$_SESSION['id_portal'];

$nomeSetorPainel = "Minhas Solicitações";

$stmt = $conn->prepare("
  SELECT id, demanda, sei, codigo, setor, setor_original, responsavel,
         data_solicitacao, data_liberacao, tempo_medio, tempo_real, data_registro
    FROM solicitacoes
   WHERE id_usuario = ?
ORDER BY data_solicitacao DESC, id DESC
");
$stmt->bind_param("i", $meuIdPortal);
$stmt->execute();
$res = $stmt->get_result();

function show($v) {
    return $v !== null && $v !== '' ? htmlspecialchars($v) : '—';
}

function d($v){ return ($v && $v !== '0000-00-00') ? htmlspecialchars(date('d/m/Y', strtotime($v))) : '—'; }
function dt($v){ return ($v) ? htmlspecialchars(date('d/m/Y H:i:s', strtotime($v))) : '—'; }

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($nomeSetorPainel) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/visualizar.css" rel="stylesheet">
</head>
<body>
<div class="container">
  <h1><?= htmlspecialchars($nomeSetorPainel) ?></h1>

  <?php if (!$res || $res->num_rows === 0): ?>
    <div class="vazio">Nenhuma solicitação cadastrada.</div>
  <?php else: ?>
    <div id="lista-solicitacoes">
      <?php
        $demandas_exibidas = [];

        while ($row = $res->fetch_assoc()):
            $demanda = trim(mb_strtolower($row['demanda'])); 
            if (in_array($demanda, $demandas_exibidas)) {
                continue;
            }
            $demandas_exibidas[] = $demanda;
        ?>

        <div class="item">
          <button class="accordion" data-id="<?= (int)$row['id'] ?>">
            <span class="titulo"><?= show($row['demanda']) ?></span>
            <span class="seta">⌄</span>
          </button>
          <div class="panel" id="panel-<?= (int)$row['id'] ?>">
            <p><span class="rot">SEI:</span> <?= show($row['sei']) ?> &nbsp; | &nbsp;
               <span class="rot">Código:</span> <?= show($row['codigo']) ?> &nbsp; | &nbsp;
               <span class="rot">Setor:</span> <?= show($row['setor_original']) ?>
            <p><span class="rot">Responsável:</span> <?= show($row['responsavel']) ?></p>
            <p><span class="rot">Data Solicitação:</span> <?= d($row['data_solicitacao']) ?> &nbsp; | &nbsp;
                <span class="rot">Data Liberação:</span> <?= d($row['data_liberacao']) ?></p>
            <p><span class="rot">Tempo Médio:</span> <?= show($row['tempo_medio']) ?> &nbsp; | &nbsp;
               <span class="rot">Tempo Real (Data):</span> <?= show($row['tempo_real']) ?></p>
            <p><span class="rot">Registrado em:</span> <?= dt($row['data_registro']) ?></p>

            <div style="margin: 10px 0 20px 0;">
            <div style="margin-top: 16px;">
              <a href="editar.php?id=<?= (int)$row['id'] ?>" class="botao-minimalista">Editar</a>

              <form method="post" action="excluir.php" onsubmit="return confirm('Deseja realmente excluir esta solicitação?')" style="display:inline;">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="botao-minimalista">Excluir</button>
              </form>

              <a href="../templates/acompanhamento.php?id=<?= (int)$row['id'] ?>" class="botao-minimalista">Acompanhar</a>
            </div>


          </div>
          
        </div>
        
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
    <br>
  <div style="margin-bottom: 20px; text-align: center;">
    <a href="home.php" class="botao-minimalista" style="background-color: #0088ff;">< Voltar</a>
  </div>


</div>

<script src="../js/visualizar.js"></script>
</body>
</html>