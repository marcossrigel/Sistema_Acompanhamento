<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;

$nomeSetorPainel = "Solicitações Registradas";

$sql = "SELECT id, demanda, sei, codigo, setor, setor_original, responsavel,
               data_solicitacao, data_liberacao, tempo_medio, tempo_real, data_registro
        FROM solicitacoes
        ORDER BY data_solicitacao DESC, id DESC";

$res = $conn->query($sql);

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
  <style>
    :root {
      --bg: #edf3f7;
      --card: #fff;
      --text: #1d1d1d;
      --muted: #6b7280;
      --line: #e0e0e0;
      --primary: #0a6be2;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg);
      margin: 0;
      padding: 30px 15px;
      color: var(--text);
    }

    .botao-minimalista {
      background-color: #0058dbff;
      color: white;
      border: none;
      padding: 6px 14px;
      font-size: 14px;
      border-radius: 6px;
      cursor: pointer;
      margin-right: 8px;
      transition: background-color 0.2s ease;
      font-family: 'Poppins', sans-serif;
      text-decoration: none; /* <-- REMOVE O SUBLINHADO */
      display: inline-block;
    }
    .botao-minimalista:hover {
      background-color: #0b5ed7;
    }

    .botao-minimalista:focus {
      outline: none;
    }

    .container {
      max-width: 800px;
      margin: 0 auto;
    }

    h1 {
      text-align: center;
      font-weight: 700;
      font-size: 26px;
      margin-bottom: 24px;
      color: var(--text);
    }

    .accordion {
      background-color: var(--card);
      color: var(--text);
      cursor: pointer;
      padding: 14px 18px;
      width: 100%;
      border: none;
      text-align: left;
      font-size: 17px;
      font-weight: 600;
      border-radius: 12px;
      margin-bottom: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: background-color 0.2s ease;
    }

    .accordion.active {
      background-color: #f0f4f9;
    }

    .seta {
      font-size: 18px;
      transition: transform 0.2s;
    }

    .accordion.active .seta {
      transform: rotate(180deg);
    }

    .panel {
      padding: 14px 20px;
      display: none;
      background-color: #fff;
      border-radius: 0 0 12px 12px;
      border-top: 1px solid var(--line);
      box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
      margin-top: -10px;
      margin-bottom: 18px;
    }

    .panel p {
      margin: 6px 0;
      font-size: 14px;
      color: #333;
      line-height: 1.4;
    }

    .panel span.rot {
      color: var(--muted);
      font-weight: 500;
    }

    .vazio {
      text-align: center;
      background: #fff;
      padding: 20px;
      border-radius: 10px;
      color: var(--muted);
      font-size: 16px;
      border: 1px dashed var(--line);
    }

    @media (max-width: 600px) {
      h1 {
        font-size: 22px;
      }
      .accordion {
        font-size: 15px;
      }
      .panel {
        font-size: 13.5px;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <h1><?= htmlspecialchars($nomeSetorPainel) ?></h1>

  <?php if (!$res || $res->num_rows === 0): ?>
    <div class="vazio">Nenhuma solicitação cadastrada.</div>
  <?php else: ?>
    <div id="lista-solicitacoes">
      <?php
        $demandas_exibidas = []; // novo array de controle

        while ($row = $res->fetch_assoc()):
            $demanda = trim(mb_strtolower($row['demanda'])); // normaliza para evitar variações
            
            if (in_array($demanda, $demandas_exibidas)) {
                continue; // já exibido, pula
            }

            $demandas_exibidas[] = $demanda; // adiciona como exibido
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

              <a href="acompanhamento.php?id=<?= (int)$row['id'] ?>" class="botao-minimalista">Acompanhar</a>
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
      localStorage.setItem('solicitacaoAbertaVisualizar', id);
    } else {
      localStorage.removeItem('solicitacaoAbertaVisualizar');
    }
  });
});
const abertaId = localStorage.getItem('solicitacaoAbertaVisualizar');
if (abertaId) {
  const btn = document.querySelector(`.accordion[data-id='${abertaId}']`);
  const panel = document.getElementById(`panel-${abertaId}`);
  if (btn && panel) {
    btn.classList.add('active');
    panel.style.display = 'block';
  }
}

</script>
</body>
</html>