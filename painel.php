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

if (empty($setor)) {
    echo "<h2 style='color: red; text-align: center;'>Setor do usuário não encontrado.</h2>";
    exit;
}

$stmt = $connLocal->prepare("
  SELECT
    s.id, s.demanda, s.sei, s.codigo, s.setor, s.responsavel,
    s.data_solicitacao, s.data_liberacao, s.tempo_medio, s.tempo_real,
    s.data_registro, s.setor_responsavel,
    MAX(e.data_encaminhamento) AS ultimo_movto
  FROM solicitacoes s
  JOIN encaminhamentos e
    ON e.id_demanda = s.id
   AND e.setor_destino = ?
  GROUP BY
    s.id, s.demanda, s.sei, s.codigo, s.setor, s.responsavel,
    s.data_solicitacao, s.data_liberacao, s.tempo_medio, s.tempo_real,
    s.data_registro, s.setor_responsavel
  ORDER BY ultimo_movto DESC, s.id DESC
");
$stmt->bind_param("s", $setor);
$stmt->execute();
$res = $stmt->get_result();

function show($v) {
    return $v !== null && $v !== '' ? htmlspecialchars($v) : '—';
}

function d($v){
    return ($v && $v !== '0000-00-00')
        ? htmlspecialchars(date('d/m/Y', strtotime($v)))
        : '—';
}

function dt($v){
    return ($v && $v !== '0000-00-00 00:00:00')
        ? htmlspecialchars(date('d/m/Y H:i:s', strtotime($v)))
        : '—';
}

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

    .obs-wrap{ margin-top:12px; max-width:620px; }
    .obs-title{
      display:block;
      font-weight:600;
      font-size:13px;
      color:#111827;
      margin-bottom:6px;
    }
    .obs-text{
      width:100%;
      min-height:80px;
      padding:10px 12px;
      border:1px solid var(--line);
      border-radius:8px;
      font-family:'Poppins',sans-serif;
      line-height:1.35;
      resize:vertical;
      background:#fff;
    }

    .toolbar{
      display:flex;
      gap:12px;
      flex-wrap:wrap;
      align-items:center;
    }
    .toolbar form{ display:flex; align-items:center; gap:8px; }

    .toolbar button,
    .toolbar form button {
      background-color: var(--primary);
      color: #fff;
      padding: 8px 14px;
      font-size: 13px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .toolbar button:hover,
    .toolbar form button:hover {
      background-color: #084a9a;
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

        /* SELECT bonito */
    .select-wrap {
      position: relative;
      display: inline-block;
    }

  .select-wrap select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 10px 44px 10px 12px;
    font-size: 14px;
    color: #1f2937;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
    transition: border-color .2s, box-shadow .2s;
  }

  .select-wrap select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10,107,226,.12);
  }

  /* seta do select */
  .select-wrap::after {
    content: "▾";
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    color: #6b7280;
    pointer-events: none;
  }

  .checklist{
    display:inline-flex;
    align-items:center;
    gap:12px;
    margin:0;
    padding:8px 12px;
  }
.checklist-row{
  display:flex;
  align-items:center;
  gap:12px;
  margin-top:12px;
}
.checklist-title{
  font-weight:600;
  font-size:13px;
  color:#111827;
}
.chk{ display:inline-flex; align-items:center; gap:6px; margin-right:14px; }
.chk input{ width:16px; height:16px; }

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
            <p><span class="rot">Data Solicitação:</span> <?= d($row['data_solicitacao']) ?> &nbsp; | &nbsp;
                <span class="rot">Data Liberação:</span> <?= d($row['data_liberacao']) ?></p>
            <p><span class="rot">Tempo Médio:</span> <?= show($row['tempo_medio']) ?> &nbsp; | &nbsp;
               <span class="rot">Tempo Real (Data):</span> <?= show($row['tempo_real']) ?></p>
            <p><span class="rot">Registrado em:</span> <?= dt($row['data_registro']) ?></p>
            
        <div class="toolbar">
  <button onclick="window.location.href='andamento.php?id=<?= (int)$row['id'] ?>'">
    Andamento do Setor
  </button>

  <?php if ($row['setor_responsavel'] === 'GECOMP'): ?>
    <!-- Select + Encaminhar (GECOMP) -->
    <form method="post" action="encaminhar.php" style="display:inline;">
      <input type="hidden" name="id_demanda" value="<?= (int)$row['id'] ?>">
      <input type="hidden" name="setor_origem" value="GECOMP">
      <input type="hidden" name="access_dinamic" value="<?= htmlspecialchars($_GET['access_dinamic']) ?>">
      <span class="select-wrap">
        <select name="setor_destino" required>
          <option value="" disabled selected>Escolher próximo setor</option>
          <option value="DDO">DDO</option>
          <option value="CPL">CPL</option>
        </select>
      </span>

      <button type="submit">Encaminhar</button>

    </form>
  <?php elseif ($row['setor_responsavel'] === $setor): ?>
    <!-- Fluxo normal dos demais setores -->
    <form method="get" action="liberar.php" style="display:inline;">
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
      <input type="hidden" name="access_dinamic" value="<?= htmlspecialchars($_GET['access_dinamic']) ?>">
      <?php
        $mapaProximo = [
          'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS' => 'GECOMP',
          'GECOMP' => 'DDO', 
          'DDO' => 'CPL', 
          'CPL' => 'DAF - HOMOLOGACAO',
          'DAF - HOMOLOGACAO' => 'PARECER JUR', 
          'PARECER JUR' => 'GEFIN NE INICIAL',
          'GEFIN NE' => 'GOP PF (SEFAZ)', 
          'GOP PF (SEFAZ)' => 'GEFIN NE DEFINITIVO',
          'GEFIN NE DEFINITIVO' => 'LIQ', 
          'LIQ' => 'PD (SEFAZ)', 
          'PD (SEFAZ)' => 'OB', 
          'OB' => 'REMESSA'
        ];
        $destino = $mapaProximo[$row['setor_responsavel']] ?? '';
      ?>
      <button type="submit">Encaminhar</button>
      <input type="hidden" name="setor_destino" value="<?= htmlspecialchars($destino) ?>">
    </form>
  <?php endif; ?>
</div>

<?php if ($row['setor_responsavel'] === 'GECOMP'): ?>
  <!-- Checklist -->
  <div class="checklist-row">
    <span class="checklist-title">Checklist (GECOMP)</span>
    <label class="chk">
      <input type="checkbox" class="gecomp-chk" data-id="<?= (int)$row['id'] ?>" data-field="tr"> TR
    </label>
    <label class="chk">
      <input type="checkbox" class="gecomp-chk" data-id="<?= (int)$row['id'] ?>" data-field="etp"> ETP
    </label>
    <label class="chk">
      <input type="checkbox" class="gecomp-chk" data-id="<?= (int)$row['id'] ?>" data-field="cotacao"> Cotação
    </label>
  </div>

  <!-- Observações ABAIXO -->
  <div class="obs-wrap">
    <label class="obs-title" for="obs-<?= (int)$row['id'] ?>">Observações (GECOMP)</label>
    <textarea
      id="obs-<?= (int)$row['id'] ?>"
      class="obs-text gecomp-obs"
      data-id="<?= (int)$row['id'] ?>"
      placeholder="Ex.: pendências, contatos realizados, links de arquivos, etc."></textarea>
  </div>
<?php endif; ?>


</div>
  <?php endwhile; ?>
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
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('access_dinamic');
  window.location.href = 'liberar.php?id=' + id + '&access_dinamic=' + encodeURIComponent(token);
}

(function(){
  function key(id){ return 'gecomp_chk_' + id; }
  function load(id){
    try { return JSON.parse(localStorage.getItem(key(id))) || {}; }
    catch(e){ return {}; }
  }
  function save(id, obj){
    localStorage.setItem(key(id), JSON.stringify(obj));
  }

  // inicia checkboxes a partir do localStorage
  document.querySelectorAll('.gecomp-chk').forEach(el => {
    const id = el.dataset.id;
    const field = el.dataset.field;
    const data = load(id);
    el.checked = !!data[field];

    el.addEventListener('change', () => {
      const obj = load(id);
      obj[field] = el.checked;
      save(id, obj);
    });
  });
})();

(function(){
  function key(id){ return 'gecomp_chk_' + id; }   // já existe
  function load(id){
    try { return JSON.parse(localStorage.getItem(key(id))) || {}; }
    catch(e){ return {}; }
  }
  function save(id, obj){
    localStorage.setItem(key(id), JSON.stringify(obj));
  }

  // ✅ iniciar checkboxes a partir do localStorage (já existia)
  document.querySelectorAll('.gecomp-chk').forEach(el => {
    const id = el.dataset.id;
    const field = el.dataset.field;
    const data = load(id);
    el.checked = !!data[field];

    el.addEventListener('change', () => {
      const obj = load(id);
      obj[field] = el.checked;
      save(id, obj);
    });
  });

  // ✅ iniciar textarea de observações a partir do localStorage
  document.querySelectorAll('.gecomp-obs').forEach(el => {
    const id = el.dataset.id;
    const data = load(id);
    if (typeof data.obs === 'string') el.value = data.obs;

    // salva a cada digitação
    el.addEventListener('input', () => {
      const obj = load(id);
      obj.obs = el.value;
      save(id, obj);
    });
  });
})();

</script>

</body>
</html>