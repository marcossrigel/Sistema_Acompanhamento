<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

$cfgPath = __DIR__ . '/templates/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/config.php'; }
if (!file_exists($cfgPath)) {
  http_response_code(500);
  exit('Arquivo de configuração não encontrado (templates/config.php ou config.php).');
}
require_once $cfgPath;

function getTokenRow(mysqli $dbRemote, string $token): ?array {
  $sql = "SELECT g_id, u_rede, data_hora FROM token_sessao WHERE token = ? LIMIT 1";
  $stmt = $dbRemote->prepare($sql);
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_assoc() ?: null;
}
function getCehabUser(mysqli $dbRemote, int $g_id): ?array {
  $sql = "SELECT g_id, u_rede, u_nome_completo AS nome, u_email FROM users WHERE g_id = ? LIMIT 1";
  $stmt = $dbRemote->prepare($sql);
  $stmt->bind_param("i", $g_id);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_assoc() ?: null;
}
function getLocalUser(mysqli $dbLocal, int $g_id): ?array {
  $sql = "SELECT * FROM usuarios WHERE id_usuario_cehab_online = ? LIMIT 1";
  $stmt = $dbLocal->prepare($sql);
  $stmt->bind_param("i", $g_id);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_assoc() ?: null;
}

if (isset($_GET['logout'])) {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
  header('Location: ./');
  exit;
}

function requireAuth(mysqli $dbLocal, mysqli $dbRemote) {

  if (!empty($_SESSION['auth_ok']) && !empty($_SESSION['g_id'])) { return; }
  $token = $_GET['token'] ?? $_GET['t'] ?? '';
  $token = trim($token);

  if ($token === '' || strlen($token) < 32) {
    http_response_code(401);
    exit('<!doctype html><meta charset="utf-8"><title>Acesso negado</title>
      <div style="font-family:Inter,system-ui;padding:40px">
      <h2>Acesso negado</h2><p>Token ausente ou inválido. Use <code>?token=SEU_TOKEN</code>.</p></div>');
  }

  $tk = getTokenRow($dbRemote, $token);
  if (!$tk) {
    http_response_code(401);
    exit('<!doctype html><meta charset="utf-8"><title>Token inválido</title>
      <div style="font-family:Inter,system-ui;padding:40px">
      <h2>Token inválido</h2><p>Não foi possível validar o token informado.</p></div>');
  }

  $g_id  = (int)$tk['g_id'];
  $uRede = $tk['u_rede'] ?? null;
  $uRemote = getCehabUser($dbRemote, $g_id);
  $uLocal  = getLocalUser($dbLocal, $g_id);

  if (!$uRemote) {
    http_response_code(403);
    exit('<!doctype html><meta charset="utf-8"><title>Usuário não encontrado</title>
      <div style="font-family:Inter,system-ui;padding:40px">
      <h2>Usuário CEHAB não encontrado</h2><p>O g_id do token não foi localizado na base remota.</p></div>');
  }

  if (!$uLocal) {
    $query = http_build_query([
      'g_id'   => $uRemote['g_id'],
      'u_rede' => $uRemote['u_rede'] ?? $uRede,
      'nome'   => $uRemote['nome'] ?? '',
      'email'  => $uRemote['u_email'] ?? '',
      'origem' => 'token'
    ]);
    header("Location: cadastro.php?".$query);
    exit;
  }
  $_SESSION['auth_ok']          = true;
  $_SESSION['g_id']             = $uRemote['g_id'];
  $_SESSION['u_rede']           = $uRemote['u_rede'] ?? $uRede;
  $_SESSION['nome']             = $uRemote['nome'] ?? '';
  $_SESSION['email']            = $uRemote['u_email'] ?? '';
  $_SESSION['id_usuario_local'] = $uLocal['id_usuario'] ?? ($uLocal['id'] ?? null);
  $_SESSION['setor']            = $uLocal['setor'] ?? ($uLocal['setor_nome'] ?? '—');
  $_SESSION['token_hash']       = substr(hash('sha256', $token), 0, 16);
}

requireAuth($connLocal, $connRemoto);

$nome  = htmlspecialchars($_SESSION['nome'] ?: ($_SESSION['u_rede'] ?? 'Usuário'), ENT_QUOTES, 'UTF-8');
$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CEHAB - Acompanhamento de Processos</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body{font-family: 'Inter', sans-serif;background:#f0f2f5}
    .modal-backdrop{background:rgba(0,0,0,.5)}
  </style>
</head>
<body class="antialiased">
<?php $isEnc = basename($_SERVER['PHP_SELF']) === 'encaminhado.php'; ?>
  <!-- HEADER -->
<header class="bg-white shadow-md">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
    <a href="home.php" class="flex items-center group">
      <i class="fas fa-sitemap text-3xl text-blue-600 mr-3"></i>
      <h1 class="text-2xl font-bold text-gray-800 group-hover:text-blue-700 transition">
        CEHAB - Acompanhamento de Processos
      </h1>
    </a>
    <div class="flex items-center gap-2">
      <a
        href="encaminhado.php"
        class="<?= $isEnc
          ? 'bg-blue-600 hover:bg-blue-700 text-white'
          : 'bg-white border border-blue-600 text-blue-600 hover:bg-blue-50'
        ?> font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center"
      >
        <i class="fa-regular fa-share-from-square mr-2"></i> Encaminhados
      </a>

      <button id="newProcessBtn"
        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
        <i class="fas fa-plus mr-2"></i> Novo Processo
      </button>
    </div>
  </div>
</header>

  <!-- MAIN -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="mb-3 flex items-center gap-2 text-sm text-gray-700">
        <i class="fas fa-building text-gray-500"></i>
        <span>Setor do usuário:</span>
        <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 font-semibold">
            <?= $setor ?>
        </span>
        </div>
      <h2 class="text-xl font-semibold text-gray-700 mb-4">Processos em Andamento</h2>

      <!-- FILTERS -->
      <div class="bg-gray-50 p-4 rounded-lg mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
        <div>
          <label class="block text-sm font-medium text-gray-700">Número do Processo</label>
          <input id="filterProcessNumber" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Pesquisar...">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Setor Demandante</label>
          <input id="filterRequestingSector" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Pesquisar...">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Descrição</label>
          <input id="filterDescription" type="text" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Pesquisar...">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Status Atual</label>
          <select id="filterStatus" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></select>
        </div>
        <div>
          <button id="clearFiltersBtn" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center text-sm">
            <i class="fas fa-times mr-2"></i> Limpar Filtros
          </button>
        </div>
        <div>
          <button id="generateReportBtn" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 flex items-center justify-center text-sm">
            <i class="fas fa-file-alt mr-2"></i> Gerar Relatório
          </button>
        </div>
      </div>

      <!-- CARDS -->
      <div id="processList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div id="loading" class="text-center col-span-full">
          <i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i>
          <p class="mt-2 text-gray-600">Carregando processos...</p>
        </div>
      </div>
    </div>
  </main>

  <!-- MODAL NOVO PROCESSO -->
  <div id="processModal" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-2xl m-4 max-w-lg w-full max-h-[90vh] overflow-y-auto">
      <div class="p-6 border-b">
        <h3 class="text-2xl font-semibold text-gray-800">Novo Processo</h3>
      </div>
      <form id="processForm" class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Número do Processo</label>
          <input id="processNumber" type="text" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Setor Demandante</label>
          <input id="requestingSector" type="text" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Ex.: DAF HOMOLOGAÇÃO">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Descrição</label>
          <textarea id="description" rows="3" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Descreva o processo..."></textarea>
        </div>
        <div class="flex justify-end pt-4 space-x-2">
          <button type="button" id="closeModalBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition duration-300">Cancelar</button>
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">Salvar Processo</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // --- CONSTANTES / "DEPARTAMENTOS" PARA O BADGE E FILTRO ---
    const DEPARTMENTS = [
      'Diretoria Administrativa e Financeira (DAF)',
      'Superintendência e Planejamento e Orçamento (SUPLAN)',
      'Gerência de Planejamento e Orçamento (GOP)',
      'Superintendência Financeira (SUFIN)',
      'Gerência Financeira (GEFIN)',
      'Superintendência de Apoio Jurídico (SUJUR)',
      'Setor Demandante'
    ];
    const DEFAULT_DEPARTMENT = DEPARTMENTS[0];

    // --- ELEMENTOS ---
    const modal = document.getElementById('processModal');
    const openBtn = document.getElementById('newProcessBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const form = document.getElementById('processForm');
    const list = document.getElementById('processList');
    const loading = document.getElementById('loading');

    const fNum = document.getElementById('filterProcessNumber');
    const fSet = document.getElementById('filterRequestingSector');
    const fDesc = document.getElementById('filterDescription');
    const fStatus = document.getElementById('filterStatus');
    const clearBtn = document.getElementById('clearFiltersBtn');
    const reportBtn = document.getElementById('generateReportBtn');

    // --- STORAGE HELPERS ---
    const LS_KEY = 'cehab_processes_v1';
    const readStore = () => JSON.parse(localStorage.getItem(LS_KEY) || '[]');
    const writeStore = data => localStorage.setItem(LS_KEY, JSON.stringify(data));

    // --- INICIALIZA FILTRO DE STATUS ---
    function populateStatusFilter(){
      fStatus.innerHTML = '<option value="">Todos os Status</option>' +
        DEPARTMENTS.map(d => `<option value="${d}">${d}</option>`).join('');
    }

    // --- MOCK INICIAL (se estiver vazio) ---
    function ensureMock(){
      const data = readStore();
      if(data.length) return;
      const now = Date.now();
      const mock = [
        {
          id: crypto.randomUUID(),
          processNumber: '2025/004-COM',
          requestingSector: 'Comunicação Social',
          description: 'Criação de campanha de divulgação interna sobre novas políticas de segurança.',
          department: 'Diretoria Administrativa e Financeira (DAF)',
          createdAt: now - 24*60*60*1000*1
        },
        {
          id: crypto.randomUUID(),
          processNumber: '2025/003-INFRA',
          requestingSector: 'Gerência de Infraestrutura',
          description: 'Aquisição de 10 novos computadores para o setor de planejamento.',
          department: 'Superintendência de Apoio Jurídico (SUJUR)',
          createdAt: now - 24*60*60*1000*60
        }
      ];
      writeStore(mock);
    }

    // --- RENDER ---
    function render(){
      loading.classList.add('hidden');

      const termNum = (fNum.value||'').toLowerCase();
      const termSet = (fSet.value||'').toLowerCase();
      const termDesc = (fDesc.value||'').toLowerCase();
      const dep = fStatus.value;

      let data = readStore().slice().sort((a,b)=>b.createdAt - a.createdAt);

      if(termNum)  data = data.filter(p => (p.processNumber||'').toLowerCase().includes(termNum));
      if(termSet)  data = data.filter(p => (p.requestingSector||'').toLowerCase().includes(termSet));
      if(termDesc) data = data.filter(p => (p.description||'').toLowerCase().includes(termDesc));
      if(dep)      data = data.filter(p => p.department === dep);

      if(!data.length){
        list.innerHTML = `<p class="col-span-full text-center text-gray-500">Nenhum processo encontrado${(termNum||termSet||termDesc||dep)?' com os filtros aplicados.':'. Crie um novo para começar.'}</p>`;
        return;
      }

      list.innerHTML = '';
      data.forEach(p=>{
        const card = document.createElement('div');
        card.className = 'bg-white p-5 rounded-lg border border-gray-200 hover:shadow-xl hover:border-blue-500 transition-all duration-300';
        card.innerHTML = `
          <div class="flex justify-between items-start mb-2">
            <h3 class="text-lg font-bold text-gray-800 truncate" title="${p.processNumber}">${p.processNumber}</h3>
            <span class="text-xs font-semibold px-2 py-1 rounded-full bg-blue-100 text-blue-800">${p.department}</span>
          </div>
          <p class="text-sm text-gray-600 mb-1"><strong>Setor:</strong> ${p.requestingSector}</p>
          <p class="text-sm text-gray-500">${p.description}</p>
          <div class="mt-4 pt-4 border-t text-right">
            <span class="text-xs text-gray-400">Criado em: ${new Date(p.createdAt).toLocaleDateString('pt-BR')}</span>
          </div>
        `;
        list.appendChild(card);
      });
    }

    // --- MODAL CONTROLES ---
    function openModal(){ modal.classList.remove('hidden'); }
    function closeModal(){ modal.classList.add('hidden'); form.reset(); }

    // --- EVENTOS ---
    openBtn.addEventListener('click', openModal);
    document.getElementById('processModal').addEventListener('click', (e)=>{ if(e.target.id==='processModal') closeModal(); });
    closeBtn.addEventListener('click', closeModal);

    form.addEventListener('submit', (e)=>{
      e.preventDefault();
      const processNumber   = document.getElementById('processNumber').value.trim();
      const requestingSector= document.getElementById('requestingSector').value.trim();
      const description     = document.getElementById('description').value.trim();

      const novo = {
        id: crypto.randomUUID(),
        processNumber,
        requestingSector,
        description,
        department: DEFAULT_DEPARTMENT,     // badge inicial como no print
        createdAt: Date.now()
      };

      const data = readStore();
      data.push(novo);
      writeStore(data);
      closeModal();
      render();
    });

    [fNum, fSet, fDesc].forEach(inp => inp.addEventListener('input', render));
    fStatus.addEventListener('change', render);
    clearBtn.addEventListener('click', ()=>{
      fNum.value=''; fSet.value=''; fDesc.value=''; fStatus.value='';
      render();
    });

    reportBtn.addEventListener('click', ()=>{
      // Gera um relatório simples imprimível com os filtros atuais
      const data = Array.from(list.querySelectorAll('.bg-white.border')).map(card=>{
        const num = card.querySelector('h3')?.textContent.trim() || '';
        const dep = card.querySelector('span')?.textContent.trim() || '';
        const set = card.querySelector('p strong')?.parentElement?.textContent.replace('Setor:','').trim() || '';
        const desc= card.querySelectorAll('p')[1]?.textContent.trim() || '';
        const cri = card.querySelector('.text-right span')?.textContent.replace('Criado em:','').trim() || '';
        return {num, set, desc, dep, cri};
      });

      if(!data.length){ alert('Nenhum processo para gerar o relatório.'); return; }

      const rows = data.map(d=>`
        <tr>
          <td>${d.num}</td><td>${d.set}</td><td>${d.desc}</td><td>${d.dep}</td><td>${d.cri}</td>
        </tr>`).join('');

      const filtersHTML = `
        <ul style="list-style:none;padding:0">
          ${fNum.value?`<li><strong>Número:</strong> ${fNum.value}</li>`:''}
          ${fSet.value?`<li><strong>Setor:</strong> ${fSet.value}</li>`:''}
          ${fDesc.value?`<li><strong>Descrição:</strong> ${fDesc.value}</li>`:''}
          ${fStatus.value?`<li><strong>Status:</strong> ${fStatus.value}</li>`:''}
          ${(!fNum.value && !fSet.value && !fDesc.value && !fStatus.value)?'<li>Nenhum filtro aplicado.</li>':''}
        </ul>`;

      const html = `
        <!DOCTYPE html><html lang="pt-BR"><head>
          <meta charset="UTF-8"><title>Relatório de Processos - CEHAB</title>
          <style>
            body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;margin:2em;color:#333}
            h1{color:#0056b3} .hdr{border-bottom:2px solid #0056b3;padding-bottom:1em;margin-bottom:1em}
            .filters{background:#f0f2f5;border:1px solid #e0e0e0;border-left:5px solid #0056b3;padding:1em;margin:2em 0}
            table{width:100%;border-collapse:collapse;margin-top:1em}
            th,td{border:1px solid #ddd;padding:10px;text-align:left} th{background:#f0f2f5}
            tr:nth-child(even){background:#fafafa}
            @media print {.no-print{display:none}}
          </style></head><body>
          <div class="hdr">
            <h1>Relatório de Processos - CEHAB</h1>
            <p>Gerado em: ${new Date().toLocaleString('pt-BR')}</p>
          </div>
          <div class="filters"><h3>Filtros Aplicados</h3>${filtersHTML}</div>
          <h2>${data.length} Processo(s) Encontrado(s)</h2>
          <table><thead>
            <tr><th>Número</th><th>Setor Demandante</th><th>Descrição</th><th>Status Atual</th><th>Criado em</th></tr>
          </thead><tbody>${rows}</tbody></table>
          <button class="no-print" onclick="window.print()">Imprimir</button>
        </body></html>`;
      const w = window.open('', '_blank'); w.document.write(html); w.document.close();
    });

    // --- BOOT ---
    populateStatusFilter();
    ensureMock();
    render();
  </script>
</body>
</html>
