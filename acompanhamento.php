<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal ?? $conn ?? $conexao ?? null;
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Recife');

function show($v){ return $v !== null && $v !== '' ? htmlspecialchars($v) : '—'; }
function d($v){ return $v ? date('Y-m-d', strtotime($v)) : '—'; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'ID inválido.'; exit; }

$st = $conn->prepare("SELECT * FROM solicitacoes WHERE id=?");
$st->bind_param("i", $id);
$st->execute();
$ref = $st->get_result()->fetch_assoc();
$st->close();
if (!$ref) { echo 'Solicitação não encontrada.'; exit; }

function norm($s){ return strtoupper(trim((string)$s)); }

$idUsuario           = (int)($ref['id_usuario'] ?? 0);
$demanda             = $ref['demanda'] ?? '';
$sei                 = trim($ref['sei'] ?? '');
$codigo              = trim($ref['codigo'] ?? '');
$setor               = $ref['setor'] ?? '';
$responsavel         = $ref['responsavel'] ?? '';
$tempo_medio         = $ref['tempo_medio'] ?? '';
$tempo_real          = 0;
$setorResponsavelDAF = 'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS';

$check = $conn->prepare("
  SELECT 1
  FROM solicitacoes
  WHERE sei = ? AND codigo = ? 
    AND setor_responsavel = ?
  LIMIT 1
");
$check->bind_param("sss", $sei, $codigo, $setorResponsavelDAF);
$check->execute();
$existeDAF = $check->get_result()->fetch_assoc();
$check->close();

if (!$existeDAF) {
  $sql = "INSERT INTO solicitacoes (
      id_usuario, demanda, sei, codigo, setor, responsavel,
      data_solicitacao, data_liberacao, tempo_medio, tempo_real,
      data_registro, setor_responsavel
    ) VALUES (
      ?, ?, ?, ?, ?, ?, CURDATE(), NULL, ?, ?, NOW(), ?
    )";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param(
    "issssssis",
    $idUsuario, $demanda, $sei, $codigo, $setor, $responsavel,
    $tempo_medio, $tempo_real, $setorResponsavelDAF
  );
  $stmt->execute();
  $stmt->close();
}

$st = $conn->prepare("SELECT * FROM solicitacoes WHERE sei = ? AND codigo = ? ORDER BY id ASC");
$st->bind_param("ss", $sei, $codigo);
$st->execute();
$hist = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$ultimoPorSetor = [];
$setorAtual = null;
$primeiraLinha = $hist[0] ?? null;

foreach ($hist as $row) {
  $keySetor = norm($row['setor_responsavel']);
  $ultimoPorSetor[$keySetor] = $row;
  if ($row['data_liberacao'] === null) {
    $setorAtual = $keySetor;
  }
}

$steps = [
  ['key' => norm('DEMANDANTE'),  'label' => 'Demandante', 'hint' => 'Recebido'],
  ['key' => norm('DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS'), 'label' => 'DAF', 'hint' => 'Recebido'],
  ['key' => norm('GECOMP'),      'label' => 'GECOMP',     'hint' => 'Análise'],
  ['key' => norm('DDO'),         'label' => 'DDO',        'hint' => 'Execução'],
  ['key' => norm('CPL'),         'label' => 'CPL',        'hint' => 'Aguardando'],
  ['key' => norm('HOMOLOGACAO'), 'label' => 'Homologação','hint' => 'Aguardando'],
  ['key' => norm('PARECER JUR'), 'label' => 'Parecer Jur.','hint' => 'Aguardando'],
  ['key' => norm('NE'),          'label' => 'NE',         'hint' => 'Aguardando'],
  ['key' => norm('PF'),          'label' => 'PF',         'hint' => 'Aguardando'],
  ['key' => norm('NE'),          'label' => 'NE',         'hint' => 'Aguardando'],
  ['key' => norm('LIQ'),         'label' => 'LIQ',        'hint' => 'Aguardando'],
  ['key' => norm('PD'),          'label' => 'PD',         'hint' => 'Aguardando'],
  ['key' => norm('OB'),          'label' => 'OB',         'hint' => 'Aguardando'],
  ['key' => norm('REMESSA'),     'label' => 'Remessa',    'hint' => 'Aguardando'],
];


$currentIdx = -1;
if ($setorAtual !== null) {
  foreach ($steps as $i => $s) {
    if ($s['key'] === $setorAtual) { $currentIdx = $i; break; }
  }
}

$cards = [];
foreach ($steps as $i => $s) {
  $keyNorm = $s['key'];
  $label   = $s['label'];
  $hint    = $s['hint'];

  $stRow = $ultimoPorSetor[$keyNorm] ?? null;

  if ($keyNorm === norm('DEMANDANTE')) $stRow = $primeiraLinha;

  $recebidoEm = $stRow ? d($stRow['data_solicitacao']) : '—';
  $liberadoEm = $stRow ? d($stRow['data_liberacao'])   : '—';

  $status = 'todo';
  if ($keyNorm === norm('DEMANDANTE')) {
    $status = 'done';
  } elseif ($currentIdx >= 0) {
    if     ($i <  $currentIdx) $status = 'done';
    elseif ($i === $currentIdx) $status = 'current';
  } elseif ($stRow && $stRow['data_liberacao'] !== null) {
    $status = 'done';
  }

  if ($keyNorm === norm('DEMANDANTE'))       $small = "Recebido • "   . ($recebidoEm ?? '—');
  elseif ($status === 'done')                $small = "Concluído • " . ($liberadoEm ?? '—');
  elseif ($status === 'current')             $small = $hint . " • "  . ($recebidoEm ?? '—');
  else                                       $small = "Aguardando • —";

  $cards[] = ['label' => $label, 'status' => $status, 'small' => $small];
}

$progressIdx = -1;
if ($currentIdx >= 0) $progressIdx = $currentIdx;
else {
  for ($i=count($cards)-1; $i>=0; $i--) {
    if ($cards[$i]['status'] === 'done') { $progressIdx = $i; break; }
  }
}
$total = max(1, count($cards)-1);
$progressPct = max(0, min(100, ($progressIdx / $total) * 100));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Acompanhamento — Portal CEHAB</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  :root{--bg:#eef3f7;--ink:#223;--muted:#6b7280;--done:#16a34a;--doing:#f59e0b;--todo:#9ca3af;
  --card:#fff;--rail:#e5e7eb;--rail-fill:#2563eb;--shadow:0 8px 24px rgba(0,0,0,.06);--radius:18px}
  *{box-sizing:border-box}body{margin:0;font-family:Poppins,system-ui,-apple-system,Segoe UI;
  background:var(--bg);color:var(--ink)}.wrap{max-width:1080px;margin:24px auto;padding:0 16px;}
  .title{font-size:clamp(22px,2.8vw,34px);text-align:center;margin:6px 0 2px}
  .subtitle{text-align:center;color:var(--muted);margin-bottom:22px}
  .subtitle strong{color:var(--doing)}.timeline{background:var(--card);border-radius:24px;
  box-shadow:var(--shadow);padding:22px;overflow:hidden;}
  .legend{display:flex;gap:18px;align-items:center;justify-content:center;margin-bottom:14px}
  .legend span{display:inline-flex;gap:8px;align-items:center;color:var(--muted);font-size:13px}
  .legend i{display:inline-block;width:10px;height:10px;border-radius:999px}
  .lg-done{background:var(--done)}.lg-current{background:var(--doing)}.lg-todo{background:var(--todo)}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;position:relative;padding-top:24px;}
  .rail{position:absolute;left:0;right:0;top:4px;height:8px;border-radius:999px;background:var(--rail);}
  .progress{height:100%;width:<?= (int)$progressPct ?>%;background:linear-gradient(90deg,var(--rail-fill),#3b82f6);
  border-radius:999px;transition:width .35s ease;}
  .step{border:1px solid #eef;border-radius:var(--radius);padding:16px;background:#fff;
  box-shadow:0 1px 0 rgba(0,0,0,.03);}
  .step h4{margin:0 0 6px;font-size:16px}
  .step small{display:block;color:var(--muted);margin-bottom:10px}
  .pill{display:inline-block;font-weight:600;font-size:12px;letter-spacing:.3px;padding:8px 12px;border-radius:999px;}
  .done .pill{background:rgba(22,163,74,.12);color:var(--done)}
  .current .pill{background:rgba(245,158,11,.14);color:var(--doing)}
  .todo .pill{background:rgba(156,163,175,.16);color:var(--todo)}
  .dots{margin-top:12px;display:flex;align-items:center;gap:6px}
  .dot{width:20px;height:20px;border-radius:999px;display:grid;place-items:center;
  font-size:12px;border:2px solid currentColor;}
  .done .dot{color:var(--done);background:rgba(22,163,74,.07)}
  .current .dot{color:var(--doing);background:rgba(245,158,11,.08)}
  .todo .dot{color:var(--todo);background:transparent}
  .footer{display:flex;justify-content:center;margin:18px 0 6px}
  .btn-back{text-decoration:none;background:#1e40af;color:#fff;padding:10px 18px;border-radius:12px;box-shadow:var(--shadow);}
</style>
</head>
<body>
  <main class="wrap">
    <h1 class="title">Informações sobre o último serviço</h1>
    <?php
      $situacao = ($currentIdx >= 0) ? 'EM ANDAMENTO' : 'CONCLUÍDO';
      $cor = ($currentIdx >= 0) ? 'style="color:#f59e0b"' : 'style="color:#16a34a"';
    ?>
    <div class="subtitle">Situação: <strong <?= $cor ?>><?= $situacao ?></strong></div>

    <section class="timeline">
      <div class="legend" aria-hidden="true">
        <span><i class="lg-done"></i> Concluído</span>
        <span><i class="lg-current"></i> Em andamento</span>
        <span><i class="lg-todo"></i> Pendente</span>
      </div>

      <div class="grid" id="flow">
        <div class="rail"><div class="progress" id="progress"></div></div>
        <?php foreach ($cards as $c): ?>
          <article class="step <?= $c['status'] ?>">
            <h4><?= show($c['label']) ?></h4>
            <small><?= show($c['small']) ?></small>
            <?php if ($c['status']==='done'): ?>
              <span class="pill">CONCLUÍDO</span>
              <div class="dots"><div class="dot">✓</div></div>
            <?php elseif ($c['status']==='current'): ?>
              <span class="pill">EM ANDAMENTO</span>
              <div class="dots"><div class="dot">✓</div></div>
            <?php else: ?>
              <span class="pill">PENDENTE</span>
              <div class="dots"><div class="dot">•</div></div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="footer">
      <a href="visualizar.php" class="btn-back">‹ Voltar</a>
    </div>
  </main>
</body>
</html>
