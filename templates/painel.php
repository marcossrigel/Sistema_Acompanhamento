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
    s.id, s.id_original,
    s.demanda, s.sei, s.codigo, s.setor, s.responsavel,
    s.data_solicitacao, s.hora_solicitacao,
    s.data_liberacao,   s.hora_liberacao,
    s.tempo_medio, s.tempo_real,
    s.data_registro, s.setor_responsavel,
    s.gecomp_tr, s.gecomp_etp, s.gecomp_cotacao, s.gecomp_obs
  FROM solicitacoes s
  WHERE s.setor_responsavel = ?
    AND s.data_liberacao IS NULL
  ORDER BY s.data_registro DESC, s.id DESC
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

function t($v){
  return ($v && $v !== '00:00:00') ? htmlspecialchars(substr($v,0,5)) : '—';
}

function dias($n){
  if ($n === null || $n === '' ) return '—';
  $n = (int)$n;
  return $n === 1 ? '1 dia' : ($n.' dias');
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
          
          <p>
              <span class="rot">Data Solicitação:</span>
              <?= d($row['data_solicitacao']) ?> <?= t($row['hora_solicitacao']) ?>
              &nbsp; | &nbsp;
              <span class="rot">Data Liberação:</span>
              <?= d($row['data_liberacao']) ?> <?= t($row['hora_liberacao']) ?>
            </p>
          
            
          <p><span class="rot">Registrado em:</span> <?= dt($row['data_registro']) ?></p>

          <div class="toolbar">
            <button onclick="window.location.href='formulario_comum.php?id=<?= (int)$row['id'] ?>'">Formulario</button>

            <?php if ($row['setor_responsavel'] === $setor) { 
              $fid = 'f-'.(int)$row['id']; ?>
              <form id="<?= $fid ?>" method="post" action="encaminhar.php" style="display:inline;">
                <input type="hidden" name="id_demanda" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="setor_origem" value="<?= htmlspecialchars($setor) ?>">
                <input type="hidden" name="access_dinamic" value="<?= htmlspecialchars($_GET['access_dinamic']) ?>">

                <input type="hidden" name="gecomp_tr"      id="hid-tr-<?= (int)$row['id'] ?>">
                <input type="hidden" name="gecomp_etp"     id="hid-etp-<?= (int)$row['id'] ?>">
                <input type="hidden" name="gecomp_cotacao" id="hid-cot-<?= (int)$row['id'] ?>">
                <input type="hidden" name="gecomp_obs"     id="hid-obs-<?= (int)$row['id'] ?>">

                <button type="submit">Encaminhar</button>
                <span class="select-wrap">
                  <select name="setor_destino" required>
                    <option value="" disabled selected>Escolher próximo setor</option>
                    <?php foreach ($SETOR_OPCOES as $opt) { if ($opt === $setor) continue;
                      echo '<option value="'.htmlspecialchars($opt).'">'.htmlspecialchars($opt).'</option>';
                    } ?>
                  </select>
                </span>

              </form>
            <?php } ?>
          </div>

          <?php if ($row['setor_responsavel'] === 'GECOMP'): ?>
          <div class="checklist-row">
          <span class="checklist-title">Checklist (GECOMP)</span>

          <label class="chk">
            <input type="checkbox" class="gecomp-chk"
                  data-id="<?= (int)$row['id'] ?>" data-field="tr"
                  data-initial="<?= (int)$row['gecomp_tr'] ?>"
                  <?= ((int)$row['gecomp_tr'] === 1 ? 'checked' : '') ?>> TR
          </label>

          <label class="chk">
            <input type="checkbox" class="gecomp-chk"
                  data-id="<?= (int)$row['id'] ?>" data-field="etp"
                  data-initial="<?= (int)$row['gecomp_etp'] ?>"
                  <?= ((int)$row['gecomp_etp'] === 1 ? 'checked' : '') ?>> ETP
          </label>

          <label class="chk">
            <input type="checkbox" class="gecomp-chk"
                  data-id="<?= (int)$row['id'] ?>" data-field="cotacao"
                  data-initial="<?= (int)$row['gecomp_cotacao'] ?>"
                  <?= ((int)$row['gecomp_cotacao'] === 1 ? 'checked' : '') ?>> Cotação
          </label>
        </div>


          <div class="obs-wrap">
            <label class="obs-title" for="obs-<?= (int)$row['id'] ?>">Observações (GECOMP)</label>
            <textarea id="obs-<?= (int)$row['id'] ?>" class="obs-text gecomp-obs"
              data-id="<?= (int)$row['id'] ?>" placeholder="Ex.: pendências, contatos realizados, links de arquivos, etc."><?= htmlspecialchars((string)$row['gecomp_obs']) ?></textarea>
          </div>
        <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

<?php endif; ?>
<div class="footer-actions">
    <a class="btn-back"
       href="home_setor.php?access_dinamic=<?= urlencode($_GET['access_dinamic'] ?? '') ?>">
       ‹ Voltar
    </a>
  </div>
</div>

<script src="../js/painel.js"></script>

</body>
</html>