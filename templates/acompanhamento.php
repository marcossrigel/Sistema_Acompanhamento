<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';

$conn = $connLocal ?? $conn ?? $conexao ?? null;
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Recife');

$token    = trim($_GET['access_dinamic'] ?? '');
$idPortal = (int)($_SESSION['id_portal'] ?? $_SESSION['id_usuario_cehab_online'] ?? 0);

$isColaborador = false;
if ($idPortal > 0 && $conn) {
  if ($st = $conn->prepare("SELECT setor FROM usuarios WHERE id_usuario_cehab_online = ? LIMIT 1")) {
    $st->bind_param('i', $idPortal);
    if ($st->execute()) {
      $r = $st->get_result()->fetch_assoc();
      $isColaborador = $r && !empty($r['setor']);
    }
    $st->close();
  }
}
$backPage = $isColaborador ? 'minhas_demandas.php' : 'visualizar.php';
$backUrl  = $backPage . ($token !== '' ? ('?access_dinamic=' . urlencode($token)) : '');

function show($v){ return $v !== null && $v !== '' ? htmlspecialchars($v) : '—'; }
function d($v){
  if (!$v || $v==='0000-00-00' || $v==='0000-00-00 00:00:00') return '—';
  return date('d/m/Y', strtotime($v));
}
function label_setor(string $s): string {
  static $map = [
    'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS' => 'DAF',
    'DAF - HOMOLOGACAO' => 'Homologação',
    'PARECER JUR' => 'Parecer Jur.',
    'GEFIN NE INICIAL' => 'NE (Inicial)',
    'GOP PF (SEFAZ)' => 'PF',
    'GEFIN NE DEFINITIVO' => 'NE (Definitivo)',
    'PD (SEFAZ)' => 'PD',
  ];
  return $map[$s] ?? $s;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'ID inválido.'; exit; }

/* 1) pega a raiz da demanda e dados principais */
$st = $conn->prepare("SELECT id, demanda, id_original, data_solicitacao FROM solicitacoes WHERE id=?");
$st->bind_param("i", $id);
$st->execute();
$base = $st->get_result()->fetch_assoc();
$st->close();
if (!$base) { echo 'Solicitação não encontrada.'; exit; }

$demanda   = $base['demanda'];
$rootId    = (int)($base['id_original'] ?: $base['id']);
$dataIni   = $base['data_solicitacao'];

/* 2) busca o “caminho” na tabela de encaminhamentos, e junta datas de recebimento/conclusão da tabela solicitacoes */
$q = $conn->prepare("
  SELECT
    e.setor_origem,
    e.setor_destino,
    e.status        AS st_enc,
    e.data_encaminhamento,
    s.data_solicitacao AS data_recebido,
    s.data_liberacao    AS data_concluido
  FROM encaminhamentos e
  LEFT JOIN solicitacoes s
         ON s.id_original = ? AND s.setor_responsavel = e.setor_destino
  WHERE e.id_demanda = ?
  ORDER BY e.data_encaminhamento ASC, e.id ASC
");
$q->bind_param("ii", $rootId, $rootId);
$q->execute();
$passos = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

if (!$passos) { echo 'Sem histórico para esta demanda.'; exit; }

/* 3) monta os cards */
$cards = [];

/* Demandante sempre primeiro */
$cards[] = [
  'label'  => 'Demandante',
  'status' => 'done',
  'small'  => 'Concluído • ' . d($dataIni),
];

$temAtual = false;
$done = 1; // já contamos o Demandante

foreach ($passos as $p) {
  $label = label_setor($p['setor_destino']);

  // datas
  $dtReceb = $p['data_recebido'] ?: substr((string)$p['data_encaminhamento'], 0, 10);
  $dtConc  = $p['data_concluido'] ?: null;

  // status visual pela própria tabela de encaminhamentos
  $status = (strcasecmp($p['st_enc'], 'Finalizado') === 0) ? 'done' : 'current';

  $small = ($status === 'done')
             ? ('Concluído • ' . d($dtConc ?: $dtReceb))
             : ('Recebido • ' . d($dtReceb));

  if ($status === 'done') $done++;
  if ($status === 'current') $temAtual = true;

  $cards[] = [
    'label'  => $label,
    'status' => $status,
    'small'  => $small,
  ];
}

/* 4) progresso e situação */
$total = max(count($cards), 1);
$progressPct = round(($done / $total) * 100);
$situacao    = $temAtual ? 'EM ANDAMENTO' : 'CONCLUÍDO';
$corSit      = $temAtual ? 'style="color:#f59e0b"' : 'style="color:#16a34a"';

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
    <h2 class="title"><?= show($demanda) ?></h2>
    <div class="subtitle">Situação: <strong <?= $corSit ?>><?= $situacao ?></strong></div>

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
      <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-back">‹ Voltar</a>
    </div>
  </main>
</body>
</html>
