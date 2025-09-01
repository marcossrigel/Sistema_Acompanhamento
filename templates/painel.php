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
   AND e.status = 'Em andamento'
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

$SETOR_OPCOES = [
  'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS',
  'GECOMP',
  'DDO',
  'CPL',
  'DAF - HOMOLOGACAO',
  'PARECER JUR',
  'GEFIN NE INICIAL',
  'GOP PF (SEFAZ)',
  'GEFIN NE DEFINITIVO',
  'LIQ',
  'PD (SEFAZ)',
  'OB',
  'REMESSA'
];

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($nomeSetorPainel) ?></title>
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/painel.css" rel="stylesheet">
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
            <button onclick="window.location.href='formulario_comum.php?id=<?= (int)$row['id'] ?>'">Formulario</button>

            <?php if ($row['setor_responsavel'] === $setor) { ?>
              <form method="post" action="encaminhar.php" style="display:inline;">
                <input type="hidden" name="id_demanda" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="setor_origem" value="<?= htmlspecialchars($setor) ?>">
                <input type="hidden" name="access_dinamic" value="<?= htmlspecialchars($_GET['access_dinamic']) ?>">

                <span class="select-wrap">
                  <select name="setor_destino" required>
                    <option value="" disabled selected>Escolher próximo setor</option>
                    <?php
                      foreach ($SETOR_OPCOES as $opt) {
                        if ($opt === $setor) continue; // opcional
                        echo '<option value="'.htmlspecialchars($opt).'">'.htmlspecialchars($opt).'</option>';
                      }
                    ?>
                  </select>
                </span>

                <button type="submit">Encaminhar</button>
              </form>
            <?php } ?>
          </div>

          <?php if ($row['setor_responsavel'] === 'GECOMP'): ?>
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

            <div class="obs-wrap">
              <label class="obs-title" for="obs-<?= (int)$row['id'] ?>">Observações (GECOMP)</label>
              <textarea id="obs-<?= (int)$row['id'] ?>" class="obs-text gecomp-obs"
                data-id="<?= (int)$row['id'] ?>" placeholder="Ex.: pendências, contatos realizados, links de arquivos, etc."></textarea>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

<?php endif; ?>
</div>

<script src="../js/painel.js"></script>

</body>
</html>