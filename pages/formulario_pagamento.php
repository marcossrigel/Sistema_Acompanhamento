<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php'); exit;
}

$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
$nome  = htmlspecialchars($_SESSION['nome']  ?? '',  ENT_QUOTES, 'UTF-8');

// ==============================
// CONFIG PLANILHA
// ==============================
$SPREADSHEET_ID = '1sa08Jk3NDm3qyZBzoBBIxO4pji6YwExqXmIaZnW7otY';
$GID            = '130227330';
$csvUrl = "https://docs.google.com/spreadsheets/d/{$SPREADSHEET_ID}/export?format=csv&gid={$GID}";

// ==============================
// FUNÇÃO DE FETCH + CACHE
// ==============================
function fetchCsv(string $url): string {
  $cacheFile = sys_get_temp_dir() . "/cehab_pagamento_".md5($url).".csv";
  $ttl = 300; // 5 min

  if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
    return file_get_contents($cacheFile) ?: '';
  }

  $ctx = stream_context_create([
    'http' => [
      'timeout' => 15,
      'header'  => "User-Agent: CEHAB-System\r\n",
    ]
  ]);

  $csv = @file_get_contents($url, false, $ctx);
  if ($csv === false || trim($csv) === '') {
    return file_exists($cacheFile) ? (file_get_contents($cacheFile) ?: '') : '';
  }

  // se veio HTML (login), considera falha
  if (stripos($csv, '<html') !== false || stripos($csv, '<!doctype') !== false) {
    return '';
  }

  file_put_contents($cacheFile, $csv);
  return $csv;
}

// ==============================
// helpers
// ==============================
function normHeader(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $s = str_replace(["\u{00A0}"], ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  // troca alguns símbolos comuns
  $s = str_replace(['º','°'], 'o', $s);
  return $s;
}

function normContrato(string $s): string {
  $s = trim($s);
  // remove espaços e caracteres “estranhos”, mas mantém / e -
  $s = preg_replace('/\s+/', '', $s);
  return $s;
}

function parseDateMaybe(string $s): int {
  $s = trim($s);
  if ($s === '') return 0;

  // formatos comuns no Google Forms/Sheets:
  // "18/02/2025 16:34:05"
  $ts = strtotime(str_replace('/', '-', $s));
  return $ts ?: 0;
}

// ==============================
// LÊ CSV e AGRUPA POR N° CONTRATO
// ==============================
$csvRaw = fetchCsv($csvUrl);

$groups = []; // contrato => ['contrato'=>..., 'obj_def'=>..., 'count'=>..., 'rows'=>[...]]
$erroPlanilha = '';

if ($csvRaw !== '') {
  $fp = fopen("php://memory", "r+");
  fwrite($fp, $csvRaw);
  rewind($fp);

  $header = fgetcsv($fp);
  if ($header) {
    $H = array_map(fn($h) => normHeader((string)$h), $header);

    // tenta encontrar colunas por nomes possíveis
    $idx = [
      'carimbo'   => array_search('carimbo de data/hora', $H, true),
      'objeto'    => array_search('objeto do contrato', $H, true),
      'contrato'  => array_search('n contrato', $H, true),
      'empresa'   => array_search('empresa', $H, true),
      'local'     => array_search('local da obra ou servico', $H, true),
      'ano'       => array_search('ano da despesa', $H, true),
      'solicit'   => array_search('nome do solicitante', $H, true),
      'origem'    => array_search('origem da demanda / setor', $H, true),
      'email'     => array_search('endereco de e-mail', $H, true),

      // extras que aparecem na sua planilha (pelo print):
      'bm'        => array_search('bm no', $H, true),
      'valor'     => array_search('valor', $H, true),
      'fonte'     => array_search('fonte de recursos do pagamento', $H, true),
      'sei'       => array_search('n sei', $H, true),
      'licenca'   => array_search('tem licenca ambie', $H, true),
      'inicio'    => array_search('inicio', $H, true),
      'fim'       => array_search('fim', $H, true),
      'status'    => array_search('status', $H, true),
      'liber'     => array_search('data da liberacao', $H, true),
      'fonte_det' => array_search('fonte detalhada', $H, true),
    ];

    // fallback: algumas planilhas vêm como "nº contrato" / "n° contrato" etc
    if ($idx['contrato'] === false) {
      foreach ($H as $k => $v) {
        if (preg_match('/^(n|no|n o)\s*contrato$/', $v)) { $idx['contrato'] = $k; break; }
        if (preg_match('/^n(úmero|umero)\s*do?\s*contrato$/', $v)) { $idx['contrato'] = $k; break; }
      }
    }

    if ($idx['objeto'] === false || $idx['contrato'] === false) {
      $erroPlanilha = 'Não achei as colunas "Objeto do contrato" e/ou "N° Contrato" no CSV.';
    } else {
      while (($row = fgetcsv($fp)) !== false) {
        $contr = normContrato((string)($row[$idx['contrato']] ?? ''));
        $obj   = trim((string)($row[$idx['objeto']] ?? ''));

        if ($contr === '' || $obj === '') continue;

        if (!isset($groups[$contr])) {
          $groups[$contr] = [
            'contrato' => $contr,
            // 👇 OBJETO DEFINITIVO = primeira ocorrência daquele contrato
            'obj_def'  => $obj,
            'count'    => 0,
            'rows'     => []
          ];
        }

        $item = [
          'carimbo_raw' => (string)($idx['carimbo'] !== false ? ($row[$idx['carimbo']] ?? '') : ''),
          'carimbo_ts'  => $idx['carimbo'] !== false ? parseDateMaybe((string)($row[$idx['carimbo']] ?? '')) : 0,

          'objeto'   => $obj,
          'ano'      => (string)($idx['ano'] !== false ? ($row[$idx['ano']] ?? '') : ''),
          'local'    => (string)($idx['local'] !== false ? ($row[$idx['local']] ?? '') : ''),
          'empresa'  => (string)($idx['empresa'] !== false ? ($row[$idx['empresa']] ?? '') : ''),
          'bm'       => (string)($idx['bm'] !== false ? ($row[$idx['bm']] ?? '') : ''),
          'valor'    => (string)($idx['valor'] !== false ? ($row[$idx['valor']] ?? '') : ''),
          'fonte'    => (string)($idx['fonte'] !== false ? ($row[$idx['fonte']] ?? '') : ''),
          'sei'      => (string)($idx['sei'] !== false ? ($row[$idx['sei']] ?? '') : ''),
          'licenca'  => (string)($idx['licenca'] !== false ? ($row[$idx['licenca']] ?? '') : ''),
          'inicio'   => (string)($idx['inicio'] !== false ? ($row[$idx['inicio']] ?? '') : ''),
          'fim'      => (string)($idx['fim'] !== false ? ($row[$idx['fim']] ?? '') : ''),
          'status'   => (string)($idx['status'] !== false ? ($row[$idx['status']] ?? '') : ''),
          'liber'    => (string)($idx['liber'] !== false ? ($row[$idx['liber']] ?? '') : ''),
          'fonte_det'=> (string)($idx['fonte_det'] !== false ? ($row[$idx['fonte_det']] ?? '') : ''),

          'solicit'  => (string)($idx['solicit'] !== false ? ($row[$idx['solicit']] ?? '') : ''),
          'origem'   => (string)($idx['origem'] !== false ? ($row[$idx['origem']] ?? '') : ''),
          'email'    => (string)($idx['email'] !== false ? ($row[$idx['email']] ?? '') : ''),
        ];

        $groups[$contr]['rows'][] = $item;
        $groups[$contr]['count']++;
      }

      // ordena histórico de cada contrato por data (mais recente primeiro)
      foreach ($groups as $c => $g) {
        usort($groups[$c]['rows'], fn($a,$b) => ($b['carimbo_ts'] <=> $a['carimbo_ts']));
      }

      // ordena cards pelo nome do objeto definitivo
      uasort($groups, fn($a,$b) => strnatcasecmp($a['obj_def'], $b['obj_def']));
    }
  }
  fclose($fp);
} else {
  $erroPlanilha = 'Não consegui ler o CSV agora (link/permite leitura?).';
}

// ==============================
// ENDPOINT AJAX: detalhes por contrato
// ==============================
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  header('Content-Type: application/json; charset=utf-8');
  $contr = normContrato((string)($_GET['contrato'] ?? ''));
  if ($contr === '' || !isset($groups[$contr])) {
    http_response_code(404);
    echo json_encode(['ok'=>false, 'msg'=>'Contrato não encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $g = $groups[$contr];

  // estado atual = linha mais recente
  $latest = $g['rows'][0] ?? [];

  echo json_encode([
    'ok' => true,
    'contrato' => $g['contrato'],
    'obj_def'  => $g['obj_def'],
    'count'    => $g['count'],
    'latest'   => $latest,
    'rows'     => $g['rows'],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CEHAB - Solicitações de Pagamento</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <!-- reutiliza encaminhado.css para manter padrão -->
  <link rel="stylesheet" href="../assets/css/encaminhado.css">

  <style>
    .card-obj{
      border:1px solid #e5e7eb;
      background:#fff;
      border-radius:12px;
      padding:14px;
      box-shadow:0 1px 2px rgba(0,0,0,.04);
      transition:.18s ease;
      cursor:pointer;
    }
    .card-obj:hover{
      transform: translateY(-2px);
      box-shadow:0 10px 24px rgba(0,0,0,.08);
      border-color:#cbd5e1;
    }
    .card-obj__top{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .card-obj__label{
      font-size:11px; color:#6b7280; letter-spacing:.02em; text-transform:uppercase; margin:0 0 4px;
    }
    .card-obj__title{
      font-weight:800; color:#111827; margin:0; line-height:1.25; font-size:14px;
    }
    .card-obj__sub{ font-size:12px; color:#6b7280; margin-top:6px; }
    .mini-pill{
      font-size:11px; padding:3px 8px; border-radius:9999px;
      background:#eff6ff; color:#2563eb; font-weight:700;
      border:1px solid #bfdbfe; white-space:nowrap; height:fit-content;
    }
    .empty-state{
      border:1px dashed #d1d5db; border-radius:12px; padding:18px;
      text-align:center; color:#6b7280; background:#fff;
    }

    /* “histórico” parecido com flow */
    .hist-item{
      border:1px solid #e5e7eb;
      border-radius:12px;
      padding:12px;
      background:#fff;
    }
    .hist-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .hist-title{ font-weight:800; color:#111827; margin:0; font-size:13px; line-height:1.25; }
    .hist-meta{ font-size:12px; color:#6b7280; margin-top:6px; display:flex; gap:10px; flex-wrap:wrap; }
    .pill-status{
      font-size:11px; padding:3px 8px; border-radius:9999px;
      border:1px solid #e5e7eb; background:#f9fafb; color:#111827;
      font-weight:700; white-space:nowrap;
    }
    .pill-green{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    .pill-red{ background:#fef2f2; border-color:#fecaca; color:#991b1b; }

    .break-words{ word-wrap:break-word; overflow-wrap:anywhere; }
  </style>
</head>
<body>

<header class="site-header">
  <div class="container site-header__row">
    <a href="../templates/home.php" class="brand">
      <i class="fas fa-sitemap" style="font-size:28px; color:#2563eb;"></i>
      <h1 class="brand__title">CEHAB - Acompanhamento de Processos</h1>
    </a>

    <div class="header-actions">
      <span class="badge-primary">
        <i class="fa-solid fa-file-invoice-dollar"></i> Solicitações de Pagamento
      </span>

      <a href="../templates/home.php" class="btn">
        <i class="fa-solid fa-arrow-left"></i> Voltar
      </a>
    </div>
  </div>
</header>

<main class="section">
  <div class="container">
    <div class="card">

      <div class="user-sector">
        <i class="fas fa-building" style="color:#6b7280;"></i>
        <span>Setor do usuário:</span>
        <span class="chip"><?= $setor ?></span>
      </div>

      <h2 class="title">Objetos do Contrato (por Nº Contrato)</h2>

      <!-- BUSCA -->
      <form class="flex items-center gap-2 mb-4" onsubmit="return false;">
        <div class="flex items-center w-full max-w-3xl border rounded-full pl-4 pr-2 py-2 bg-white">
          <i class="fa-solid fa-magnifying-glass mr-2 opacity-70"></i>
          <input
            id="searchObj"
            type="text"
            placeholder="Buscar (ex.: UBS Vila Claudete, 114/2022)"
            class="w-full outline-none"
            autocomplete="off"
          />
          <button id="btnBuscarObj" class="ml-2 rounded-full px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 transition" type="button">
            Pesquisar
          </button>
        </div>
        <button id="btnLimparObj" class="btn" type="button" title="Limpar">Limpar</button>
      </form>

      <div id="objList" class="grid">
        <?php if (!empty($erroPlanilha) || empty($groups)): ?>
          <div class="empty-state" style="grid-column: 1 / -1;">
            <?= htmlspecialchars($erroPlanilha ?: 'Sem dados.', ENT_QUOTES, 'UTF-8') ?><br><br>
            <b>Prováveis motivos:</b><br>
            • Planilha não está com acesso de leitura “qualquer pessoa com o link”<br>
            • Bloqueio momentâneo do Google
          </div>
        <?php else: ?>
          <?php foreach ($groups as $contr => $g): ?>
            <?php
              $title = $g['obj_def'];
              $count = (int)$g['count'];
              $contrato = $g['contrato'];
              $searchText = mb_strtolower($title.' '.$contrato, 'UTF-8');
            ?>
            <div class="card-obj obj-item"
                 data-contrato="<?= htmlspecialchars($contrato, ENT_QUOTES, 'UTF-8') ?>"
                 data-text="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
              <div class="card-obj__top">
                <div style="min-width:0;">
                  <p class="card-obj__label">Objeto do contrato</p>
                  <p class="card-obj__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></p>
                  <div class="card-obj__sub">
                    <b>Nº Contrato:</b> <?= htmlspecialchars($contrato, ENT_QUOTES, 'UTF-8') ?>
                  </div>
                </div>
                <span class="mini-pill"><?= $count ?>x</span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</main>

<!-- MODAL DETALHES (mesmo padrão do encaminhado) -->
<div id="detailsModal" class="modal-backdrop hidden">
  <div class="modal">
    <div class="modal__header">
      <h3 class="modal__title">Detalhes do Objeto / Contrato</h3>
      <button id="closeDetails" class="modal__close" aria-label="Fechar">
        <i class="fa-solid fa-xmark" style="font-size:20px;"></i>
      </button>
    </div>

    <div class="modal__body">
      <div class="modal-grid">
        <div class="flow-col">
          <h4 class="flow-title">Histórico / Atualizações (linhas da planilha)</h4>
          <div id="histList" class="flow-list"></div>
        </div>

        <aside>
          <div class="sidebar-box">
            <h5 class="sidebar-title">Informações Gerais</h5>
            <div class="info-list">
              <p><span class="info-label">Nº Contrato:</span> <span id="d_contrato" class="font-medium">—</span></p>
              <p><span class="info-label">Objeto definitivo:</span> <span id="d_obj" class="font-medium break-words">—</span></p>
              <p><span class="info-label">Empresa:</span> <span id="d_empresa" class="font-medium break-words">—</span></p>
              <p><span class="info-label">Local:</span> <span id="d_local" class="font-medium break-words">—</span></p>
              <p><span class="info-label">Status (mais recente):</span> <span id="d_status" class="font-medium">—</span></p>
              <p><span class="info-label">Nº SEI (mais recente):</span> <span id="d_sei" class="font-medium break-words">—</span></p>
              <p><span class="info-label">Fonte (mais recente):</span> <span id="d_fonte" class="font-medium break-words">—</span></p>
            </div>
          </div>

          <div class="modal__footer" style="padding:0; border:0; text-align:right; margin-top:16px;">
            <button id="okDetails" class="btn--ok" type="button">OK</button>
          </div>
        </aside>
      </div>
    </div>
  </div>
</div>

<script>
  const input = document.getElementById('searchObj');
  const btnBuscar = document.getElementById('btnBuscarObj');
  const btnLimpar = document.getElementById('btnLimparObj');

  function filtrar(){
    const q = (input.value || '').trim().toLowerCase();
    document.querySelectorAll('.obj-item').forEach(el => {
      const t = (el.getAttribute('data-text') || '').toLowerCase();
      el.style.display = (q === '' || t.includes(q)) ? '' : 'none';
    });
  }

  btnBuscar?.addEventListener('click', filtrar);
  btnLimpar?.addEventListener('click', () => { input.value=''; filtrar(); });
  input?.addEventListener('input', filtrar);

  // modal
  const modal = document.getElementById('detailsModal');
  const closeBtn = document.getElementById('closeDetails');
  const okBtn = document.getElementById('okDetails');

  function openModal(){ modal.classList.remove('hidden'); modal.style.display='flex'; }
  function closeModal(){ modal.classList.add('hidden'); modal.style.display='none'; }

  closeBtn?.addEventListener('click', closeModal);
  okBtn?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

  function pillByStatus(s){
    const x = (s || '').toLowerCase();
    if (x.includes('enviado') || x.includes('aprov') || x.includes('ok')) return 'pill-status pill-green';
    if (x.includes('pend') || x.includes('reprov') || x.includes('erro')) return 'pill-status pill-red';
    return 'pill-status';
  }

  function esc(s){
    return (s ?? '').toString()
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  async function carregarDetalhes(contrato){
    const r = await fetch(`formulario_pagamento.php?ajax=1&contrato=${encodeURIComponent(contrato)}`, { cache: 'no-store' });
    if (!r.ok) throw new Error('Falha ao carregar detalhes');
    return r.json();
  }

  function renderDetalhes(data){
    // sidebar (linha mais recente)
    const latest = data.latest || {};
    document.getElementById('d_contrato').textContent = data.contrato || '—';
    document.getElementById('d_obj').textContent = data.obj_def || '—';
    document.getElementById('d_empresa').textContent = latest.empresa || '—';
    document.getElementById('d_local').textContent = latest.local || '—';
    document.getElementById('d_status').textContent = latest.status || '—';
    document.getElementById('d_sei').textContent = latest.sei || '—';
    document.getElementById('d_fonte').textContent = latest.fonte || '—';

    // histórico
    const box = document.getElementById('histList');
    const rows = data.rows || [];
    if (!rows.length){
      box.innerHTML = `<div class="empty-state">Sem histórico para esse contrato.</div>`;
      return;
    }

    box.innerHTML = rows.map(r => {
      const st = (r.status || '').trim();
      const bm = (r.bm || '').trim();
      const valor = (r.valor || '').trim();
      const carimbo = (r.carimbo_raw || '').trim();

      const meta = [];
      if (bm) meta.push(`<span><b>BM:</b> ${esc(bm)}</span>`);
      if (valor) meta.push(`<span><b>Valor:</b> ${esc(valor)}</span>`);
      if (r.ano) meta.push(`<span><b>Ano:</b> ${esc(r.ano)}</span>`);
      if (r.solicit) meta.push(`<span><b>Solicitante:</b> ${esc(r.solicit)}</span>`);
      if (r.origem) meta.push(`<span><b>Origem:</b> ${esc(r.origem)}</span>`);

      return `
        <div class="hist-item">
          <div class="hist-top">
            <div style="min-width:0;">
              <p class="hist-title">${esc(r.objeto || '')}</p>
              <div class="hist-meta">
                ${meta.join('')}
              </div>
            </div>
            <div style="text-align:right; min-width:140px;">
              ${st ? `<span class="${pillByStatus(st)}">${esc(st)}</span>` : ''}
              <div style="margin-top:6px; font-size:12px; color:#6b7280;">${esc(carimbo)}</div>
            </div>
          </div>

          <div class="hist-meta" style="margin-top:10px;">
            ${r.sei ? `<span><b>SEI:</b> ${esc(r.sei)}</span>` : ''}
            ${r.fonte ? `<span><b>Fonte:</b> ${esc(r.fonte)}</span>` : ''}
            ${r.fonte_det ? `<span><b>Fonte detalhada:</b> ${esc(r.fonte_det)}</span>` : ''}
            ${r.inicio ? `<span><b>Início:</b> ${esc(r.inicio)}</span>` : ''}
            ${r.fim ? `<span><b>Fim:</b> ${esc(r.fim)}</span>` : ''}
            ${r.liber ? `<span><b>Liberação:</b> ${esc(r.liber)}</span>` : ''}
          </div>
        </div>
      `;
    }).join('');
  }

  document.querySelectorAll('.obj-item').forEach(el => {
    el.addEventListener('click', async () => {
      const contrato = el.getAttribute('data-contrato') || '';
      if (!contrato) return;

      // abre logo e carrega
      openModal();
      document.getElementById('histList').innerHTML = `<div class="empty-state">Carregando…</div>`;

      try{
        const data = await carregarDetalhes(contrato);
        if (!data.ok) throw new Error(data.msg || 'Erro');
        renderDetalhes(data);
      }catch(e){
        document.getElementById('histList').innerHTML =
          `<div class="empty-state">Erro ao carregar detalhes desse contrato.</div>`;
      }
    });
  });
</script>

</body>
</html>
