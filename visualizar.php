<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/config.php';
$conn->set_charset('utf8mb4');

$sql = "SELECT id, demanda, sei, codigo, setor, responsavel, 
               data_solicitacao, data_liberacao, tempo_medio, tempo_real, data_registro
        FROM solicitacoes
        ORDER BY data_solicitacao DESC, id DESC";
$res = $conn->query($sql);

function show($v) { return $v !== null && $v !== '' ? htmlspecialchars($v) : '—'; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Solicitações</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  :root{
    --bg:#edf3f7; --card:#fff; --text:#1d2129; --muted:#6b7280; --line:#e5e7eb;
    --primary:#0a6be2; --ok:#16a34a;
  }
  body{
    font-family:Arial, sans-serif;
    background:var(--bg);
    margin:0;
    padding:20px;
    color:var(--text)
  }
.container {
  max-width: 800px;
  margin: 0 auto;
}

  h1{
    text-align:center;
    margin:0 0 14px 0;
    font-size:28px;
    font-weight:700;
  }

  .botao-voltar {
    display: flex;
    justify-content: center;
    margin-top: 20px;
    }

    .botao-voltar a {
        background-color: var(--primary);
        color: white;
        padding: 10px 20px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: bold;
        font-size: 14px;
        transition: background 0.3s;
    }

    .botao-voltar a:hover {
        background-color: #084a9a;
    }


  .item{
    background:var(--card);
    border-radius:10px;
    box-shadow:0 6px 14px rgba(0,0,0,.06);
    margin-bottom:10px;
    overflow:hidden;
    border:1px solid var(--line)
  }

  .accordion{
    width: 100%;
    text-align: left;
    padding: 12px 16px;
    border: 0;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 700;
    font-size: 18px;
    line-height: 1.2;
  }
  .accordion .titulo{
    font-size: 20px;
    font-weight: 600;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:90%
  }
  .accordion .seta{
    transition:transform .2s ease
}
  .accordion.active .seta{
    transform:rotate(180deg)
}

  .panel{
    display:none;
    padding:10px 14px;
    background:linear-gradient(#fff,#fbfbfb)
  }
  .panel p{
    margin:4px 0;           
    color:#111;
    line-height:1.35;       
    font-size:14px;         
  }
  .panel p span.rot{color:var(--muted)}

  .toolbar{
    display:flex;
    gap:6px;
    margin-top:10px;
    flex-wrap:wrap
  }
  .toolbar button{
    padding:6px 10px;
    border-radius:8px;
    border:1px solid var(--line);
    background:#fff;
    cursor:pointer;
    font-size:14px;
  }

  .vazio{
    background:#fff;
    border:1px dashed var(--line);
    border-radius:10px;
    padding:16px;
    text-align:center;
    color:var(--muted)
  }

  @media (max-width: 640px){
    h1{font-size:24px}
    .accordion{font-size:14px;padding:9px 12px}
    .panel{padding:9px 12px}
    .panel p{font-size:13.5px}
  }
</style>

</head>
<body>
<div class="container">
  <h1>Solicitações</h1>

  <?php if (!$res || $res->num_rows === 0): ?>
    <div class="vazio">Nenhuma solicitação cadastrada.</div>
  <?php else: ?>
    <div id="lista-solicitacoes">
      <?php while ($row = $res->fetch_assoc()): ?>
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
