<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php'); exit;
}

require_once __DIR__ . '/../templates/config.php';

$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
$nome  = htmlspecialchars($_SESSION['nome']  ?? '',  ENT_QUOTES, 'UTF-8');

// ==============================
// CONFIG PLANILHA
// ==============================
$SPREADSHEET_ID = '1sa08Jk3NDm3qyZBzoBBIxO4pji6YwExqXmIaZnW7otY';
$GID            = '130227330'; // Respostas ao formulário 1

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
      'timeout' => 12,
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
// LÊ CSV e MONTA LISTA ÚNICA (COM REGRA PELO Nº CONTRATO)
// ==============================
$csvRaw = fetchCsv($csvUrl);

$objetosUnicos = [];   // key => nome bonitinho (display)
$contagem = [];        // key => qtd de linhas (x)
$contratoToKey = [];   // contrato_normalizado => key do objeto definitivo

function norm_text(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/', ' ', $s);
  $s = mb_strtolower($s, 'UTF-8');

  // remove acentos
  $noAcc = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  if ($noAcc !== false) $s = $noAcc;

  // tira pontuação “solta”, mantém letras/números/espaço
  $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
}

function norm_contrato(string $s): string {
  $s = trim($s);
  // remove espaços
  $s = preg_replace('/\s+/', '', $s);
  // deixa tudo maiúsculo
  $s = strtoupper($s);
  return $s;
}

if ($csvRaw !== '') {
  $fp = fopen("php://memory", "r+");
  fwrite($fp, $csvRaw);
  rewind($fp);

  $header = fgetcsv($fp);
  if ($header) {
    $headerNorm = array_map(fn($h) => norm_text((string)($h ?? '')), $header);

    // tenta achar "objeto do contrato"
    $idxObjeto = array_search(norm_text('Objeto do contrato'), $headerNorm, true);

    // tenta achar "nº contrato" com variações comuns
    $possiveisContrato = [
      'n contrato', 'no contrato', 'numero do contrato', 'nº contrato', 'n° contrato', 'nr contrato', 'contrato',
    ];
    $idxContrato = false;
    foreach ($possiveisContrato as $p) {
      $i = array_search(norm_text($p), $headerNorm, true);
      if ($i !== false) { $idxContrato = $i; break; }
    }

    if ($idxObjeto !== false) {
      while (($row = fgetcsv($fp)) !== false) {
        $objRaw = trim((string)($row[$idxObjeto] ?? ''));
        if ($objRaw === '') continue;

        $contrRaw = ($idxContrato !== false) ? trim((string)($row[$idxContrato] ?? '')) : '';
        $contrKey = ($contrRaw !== '') ? norm_contrato($contrRaw) : '';

        // 1) Decide o "objeto definitivo" baseado no contrato (primeira ocorrência)
        if ($contrKey !== '') {
          if (!isset($contratoToKey[$contrKey])) {
            // primeira vez desse contrato: fixa o nome definitivo
            $objKey = norm_text($objRaw);
            $key = 'o:' . $objKey;

            $contratoToKey[$contrKey] = $key;

            // salva display (primeira ocorrência)
            if (!isset($objetosUnicos[$key])) {
              $objetosUnicos[$key] = $objRaw;
              $contagem[$key] = 0;
            }
          }
          $key = $contratoToKey[$contrKey]; // usa o definitivo
        } else {
          // sem contrato: cai na regra antiga (por texto normalizado)
          $objKey = norm_text($objRaw);
          $key = 'o:' . $objKey;

          if (!isset($objetosUnicos[$key])) {
            $objetosUnicos[$key] = $objRaw;
            $contagem[$key] = 0;
          }
        }

        // 2) Conta ocorrência (x)
        $contagem[$key] = ($contagem[$key] ?? 0) + 1;
      }
    }
  }

  fclose($fp);
}

// ordena por nome
asort($objetosUnicos, SORT_FLAG_CASE | SORT_NATURAL);

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

  <!-- pode reutilizar o encaminhado.css para manter 100% igual -->
  <link rel="stylesheet" href="../assets/css/encaminhado.css">

  <!-- pequenos ajustes só para o card do objeto -->
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
    .card-obj__top{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:10px;
    }
    .card-obj__label{
      font-size:11px;
      color:#6b7280;
      letter-spacing:.02em;
      text-transform:uppercase;
      margin:0 0 4px;
    }
    .card-obj__title{
      font-weight:800;
      color:#111827;
      margin:0;
      line-height:1.25;
      font-size:14px;
    }
    .mini-pill{
      font-size:11px;
      padding:3px 8px;
      border-radius:9999px;
      background:#eff6ff;
      color:#2563eb;
      font-weight:700;
      border:1px solid #bfdbfe;
      white-space:nowrap;
      height: fit-content;
    }
    .empty-state{
      border:1px dashed #d1d5db;
      border-radius:12px;
      padding:18px;
      text-align:center;
      color:#6b7280;
      background:#fff;
    }
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

      <h2 class="title">Objetos do Contrato</h2>

      <!-- BUSCA (igual encaminhado) -->
      <form id="frmBuscaObj" class="flex items-center gap-2 mb-4" action="" method="GET" onsubmit="return false;">
        <div class="flex items-center w-full max-w-3xl border rounded-full pl-4 pr-2 py-2 bg-white">
          <i class="fa-solid fa-magnifying-glass mr-2 opacity-70"></i>
          <input
            id="searchObj"
            type="text"
            placeholder="Buscar objeto do contrato (ex.: UBS Vila Claudete)"
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
        <?php if (empty($objetosUnicos)): ?>
          <div class="empty-state" style="grid-column: 1 / -1;">
            Não consegui ler a planilha agora.<br><br>
            <b>Prováveis motivos:</b><br>
            • Planilha não está com acesso de leitura “qualquer pessoa com o link”<br>
            • Bloqueio momentâneo do Google
          </div>
        <?php else: ?>
          <?php foreach ($objetosUnicos as $key => $nomeObj): ?>
            <div class="card-obj obj-item"
                 data-text="<?= htmlspecialchars(mb_strtolower($nomeObj,'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
              <div class="card-obj__top">
                <div style="min-width:0;">
                  <p class="card-obj__label">Objeto do contrato</p>
                  <p class="card-obj__title"><?= htmlspecialchars($nomeObj, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="mini-pill"><?= (int)($contagem[$key] ?? 1) ?>x</span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</main>

<script>
  const input = document.getElementById('searchObj');
  const btnBuscar = document.getElementById('btnBuscarObj');
  const btnLimpar = document.getElementById('btnLimparObj');

  function filtrar(){
    const q = (input.value || '').trim().toLowerCase();
    document.querySelectorAll('.obj-item').forEach(el => {
      const t = el.getAttribute('data-text') || '';
      el.style.display = (q === '' || t.includes(q)) ? '' : 'none';
    });
  }

  btnBuscar?.addEventListener('click', filtrar);
  btnLimpar?.addEventListener('click', () => { input.value=''; filtrar(); });
  input?.addEventListener('input', filtrar);

  document.querySelectorAll('.obj-item').forEach(el => {
    el.addEventListener('click', () => {
      const nome = el.querySelector('.card-obj__title')?.textContent?.trim() || '';
      alert("Selecionado: " + nome);

    });
  });
</script>

</body>
</html>
