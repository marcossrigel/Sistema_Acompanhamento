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
  // limpa a sessão
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();

  // decide para onde ir
  $go = $_GET['go'] ?? '';
  if ($go === 'getic') {
    header('Location: https://www.getic.pe.gov.br/?p=home');
  } else {
    header('Location: ./');
  }
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
    .step{transition:all .3s ease}
    .step-completed .step-circle{background:#16a34a;border-color:#16a34a}
    .step-completed .step-line{background:#16a34a}
    .step-current .step-circle{background:#2563eb;border-color:#2563eb;animation:pulse 2s infinite}
    .step-pending .step-circle{background:#fff;border-color:#d1d5db}
    .step-pending .step-line{background:#d1d5db}
    @keyframes pulse{
      0%,100%{box-shadow:0 0 0 0 rgba(37,99,235,.4)}
      70%{box-shadow:0 0 0 10px rgba(37,99,235,0)}
    }
    .ping-ativo {
      animation: ping 1s infinite;
    }

    @keyframes ping {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.2); opacity: 0.6; }
      100% { transform: scale(1); opacity: 1; }
    }
  </style>
</head>
<body class="antialiased">
<?php $isEnc = basename($_SERVER['PHP_SELF']) === 'encaminhado.php'; ?>
  <!-- HEADER -->
<header class="bg-white shadow-md">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
    <a href="index.php" class="flex items-center group">
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

      <a href="?logout=1&go=getic"
        class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-300 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center">
        <i class="fa-solid fa-right-from-bracket mr-2"></i> Sair
      </a>

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
          <input id="requestingSector"
                type="text"
                value="<?= $setor ?>"
                readonly
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700 cursor-not-allowed sm:text-sm">
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

<!-- MODAL DETALHES DO PROCESSO -->
<div id="fluxoModal" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center">
  <div class="bg-white rounded-lg shadow-2xl m-4 max-w-5xl w-full max-h-[90vh] overflow-hidden flex flex-col">
    <!-- header -->
    <div class="p-6 border-b flex justify-between items-start">
      <div>
        <h3 class="text-2xl font-semibold text-gray-800">Detalhes do Processo</h3>
        <p id="detNum" class="text-sm text-gray-500"></p>
      </div>
      <button id="closeDetails" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-times text-2xl"></i>
      </button>
    </div>

    <!-- body -->
    <div class="p-6 overflow-y-auto flex-1">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
        <!-- fluxo -->
        <div class="md:col-span-3">
          <h4 class="text-lg font-semibold mb-4 text-gray-700">Histórico e Fluxo do Processo</h4>
          <div id="fluxoEtapasContainer" class="space-y-0"></div> <!-- id correto -->
        </div>


        <!-- info & ações -->
        <div class="md:col-span-2">
          <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <h4 class="text-lg font-semibold mb-3 text-gray-700">Informações Gerais</h4>
            <p class="text-sm"><strong>Setor Demandante:</strong> <span id="detSetor"></span></p>
            <p class="text-sm"><strong>Descrição:</strong> <span id="detDesc"></span></p>
            <p class="text-sm"><strong>Criado em:</strong> <span id="detCriado"></span></p>
          </div>

          <div>
            <h4 class="text-lg font-semibold mb-3 text-gray-700">Ações</h4>
            <p id="detStatusText" class="mb-4 text-gray-600"></p>
            <button id="detAvancar" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg shadow-md transition duration-300 flex items-center justify-center text-lg">
              <span id="detAvancarTxt">Avançar</span> <i class="fas fa-arrow-right ml-2"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
    <button id="abrirAcaoInterna" class="w-full bg-white border border-gray-300 hover:bg-gray-100 text-gray-800 font-bold py-2 px-4 rounded-lg mt-4 transition duration-300 flex items-center justify-center">
      <i class="fas fa-plus mr-2"></i> Adicionar Ação Interna
    </button>
  </div>
</div>

<div id="acaoInternaModal" class="fixed inset-0 z-50 hidden modal-backdrop flex items-center justify-center bg-black bg-opacity-30">
  <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Registrar Ação Interna</h3>
    <textarea id="descricaoAcao" rows="4" class="w-full p-3 border border-gray-300 rounded-md resize-none" placeholder="Ex: Solicitar orçamento para SEPLAG."></textarea>
    <div class="flex justify-end gap-3 mt-4">
      <button id="cancelarAcaoBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">Cancelar</button>
      <button id="salvarAcaoBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Salvar Ação</button>
    </div>
  </div>
</div>

<script>
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
  const modalNew   = document.getElementById('processModal');
  const openBtn    = document.getElementById('newProcessBtn');
  const closeBtn   = document.getElementById('closeModalBtn');
  const form       = document.getElementById('processForm');
  const list       = document.getElementById('processList');

  // filtros (atenção: o campo de setor na faixa de filtros tem id="requestingSector")
  const fNum   = document.getElementById('filterProcessNumber');
  const fSet   = document.getElementById('requestingSector');    // readOnly com o setor do usuário
  const fDesc  = document.getElementById('filterDescription');
  const fStat  = document.getElementById('filterStatus');         // ainda não usamos status do BD
  const fClear = document.getElementById('clearFiltersBtn');
  const fReport= document.getElementById('generateReportBtn');

  // modal de detalhes
  const detailsModal  = document.getElementById('detailsModal');
  const detNumero     = document.getElementById('det-numero');
  const detSetor      = document.getElementById('det-setor');
  const detDescricao  = document.getElementById('det-descricao');
  const detData       = document.getElementById('det-data');
  const closeDet1     = document.getElementById('closeDetailsBtn');
  const closeDet2     = document.getElementById('closeDetailsBtn2');

  let PROCESSOS = []; // será preenchido pelo listar_processos.php

  // =========================
  // HELPERS
  // =========================
  function openModal(){ modalNew.classList.remove('hidden'); }
  function closeModal(){ modalNew.classList.add('hidden'); form.reset(); }

  function openDetails(p){
    detNumero.textContent   = p.numero_processo || '—';
    detSetor.textContent    = p.setor_demandante || '—';
    detDescricao.textContent= p.descricao || '—';
    detData.textContent     = formatDateBR(p.data_registro);
    document.getElementById('fluxoModal').classList.remove('hidden');
    gerarFluxoDoProcesso("Análise e Autorização");
  }
  function closeDetails(){
    detailsModal.classList.add('hidden');
  }

  function pad(n){ return String(n).padStart(2,'0'); }
  function formatDateBR(iso){
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d)) return '—';
    return d.toLocaleDateString('pt-BR');
  }

  // =========================
  // BUSCA NO BACKEND
  // =========================
  async function fetchProcessos(){
    try{
      const resp = await fetch('templates/listar_processos.php', { credentials: 'same-origin' });
      const json = await resp.json();
      if(!resp.ok || !json.ok) throw new Error(json.error || 'Erro ao listar processos.');
      PROCESSOS = json.data || [];
      render();
    }catch(err){
      console.error(err);
      list.innerHTML = '<p class="col-span-full text-center text-red-500">Falha ao carregar processos.</p>';
    }
  }

  // =========================
  // RENDERIZAÇÃO DOS CARDS
  // =========================
  function render(){
    // termos dos filtros
    const termNum  = (fNum.value  || '').toLowerCase();
    const termSet  = (fSet.value  || '').toLowerCase();
    const termDesc = (fDesc.value || '').toLowerCase();
    // const statSel  = fStat.value; // reservado p/ futuro

    let data = PROCESSOS.slice();

    if(termNum)  data = data.filter(p => (p.numero_processo  || '').toLowerCase().includes(termNum));
    if(termSet)  data = data.filter(p => (p.setor_demandante || '').toLowerCase().includes(termSet));
    if(termDesc) data = data.filter(p => (p.descricao        || '').toLowerCase().includes(termDesc));

    // ordena por id desc (mais novo primeiro)
    data.sort((a,b) => (b.id || 0) - (a.id || 0));

    if(!data.length){
      list.innerHTML = `<p class="col-span-full text-center text-gray-500">
        Nenhum processo encontrado${(termNum||termSet||termDesc)?' com os filtros aplicados.':'. Crie um novo para começar.'}
      </p>`;
      return;
    }

    list.innerHTML = '';
    data.forEach(p => {
      const card = document.createElement('div');
      card.className = 'bg-white p-5 rounded-lg border border-gray-200 hover:shadow-xl hover:border-blue-500 transition-all duration-300 cursor-pointer';
      card.innerHTML = `
        <div class="flex justify-between items-start mb-2">
          <h3 class="text-lg font-bold text-gray-800 truncate" title="${p.numero_processo}">${p.numero_processo}</h3>
          <span class="text-xs font-semibold px-2 py-1 rounded-full bg-blue-100 text-blue-800">${p.setor_demandante || '—'}</span>
        </div>
        <p class="text-sm text-gray-600 mb-1"><strong>Setor:</strong> ${p.setor_demandante || '—'}</p>
        <p class="text-sm text-gray-500">${p.descricao || ''}</p>
        <div class="mt-4 pt-4 border-t text-right">
          <span class="text-xs text-gray-400">Criado em: ${formatDateBR(p.data_registro)}</span>
        </div>
      `;
      card.addEventListener('click', () => openDetails(p));
      list.appendChild(card);
    });
  }

  // =========================
  // EVENTOS – NOVO PROCESSO
  // =========================
  openBtn.addEventListener('click', openModal);
  document.getElementById('processModal').addEventListener('click', (e)=>{ if(e.target.id==='processModal') closeModal(); });
  closeBtn.addEventListener('click', closeModal);

  form.addEventListener('submit', async (e)=>{
    e.preventDefault();

    const processNumber = document.getElementById('processNumber').value.trim();
    const description   = document.getElementById('description').value.trim();

    if(!processNumber || !description){
      alert('Preencha os campos obrigatórios.');
      return;
    }

    // data/hora da MÁQUINA do usuário (YYYY-MM-DD HH:MM:SS)
    const now = new Date();
    const dataLocal = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

    try{
      const resp = await fetch('templates/salvar_processo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          numero_processo: processNumber,
          descricao: description,
          data_registro_client: dataLocal
        })
      });
      const json = await resp.json();
      if(!resp.ok || !json.ok) throw new Error(json.error || 'Erro ao salvar processo.');

      alert('Processo salvo com sucesso!');
      closeModal();
      await fetchProcessos(); // recarrega a grade com o novo registro
    }catch(err){
      console.error(err);
      alert('Falha ao salvar o processo. Tente novamente.');
    }
  });

  // =========================
  // EVENTOS – DETALHES
  // =========================
  if (closeDet1) closeDet1.addEventListener('click', closeDetails);
  if (closeDet2) closeDet2.addEventListener('click', closeDetails);
  if (detailsModal) detailsModal.addEventListener('click', (e)=>{ if(e.target.id==='detailsModal') closeDetails(); });

  // =========================
  // EVENTOS – FILTROS
  // =========================
  fNum.addEventListener('input', render);
  fSet.addEventListener('input', render);     // é readOnly, mas mantemos por consistência
  fDesc.addEventListener('input', render);
  fStat.addEventListener('change', render);

  fClear.addEventListener('click', ()=>{
    fNum.value  = '';
    // mantém o setor do usuário no filtro:
    // (se quiser zerar, troque para string vazia)
    // fSet.value = '';
    fDesc.value = '';
    fStat.value = '';
    render();
  });

  // =========================
  // INICIALIZAÇÃO
  // =========================
  // se você tiver opções reais de "status", popule aqui:
  function populateStatusFilter(){
    fStat.innerHTML = '<option value="">Todos os Status</option>';
  }

  function gerarFluxoDoProcesso(statusAtual) {
    const etapas = [
      { setor: "Diretoria Administrativa e Financeira (DAF)",         etapa: "Análise e Autorização", subtitulo: "Aguardando análise e autorização da diretoria." },
      { setor: "Superintendência e Planejamento e Orçamento (SUPLAN)", etapa: "Análise de Planejamento e Orçamento", subtitulo: "Analisando o impacto e alinhamento estratégico." },
      { setor: "Gerência de Planejamento e Orçamento (GOP)",           etapa: "Emissão de Dotação Orçamentária (DDO)", subtitulo: "Verificando disponibilidade e emitindo a DDO." },
      { setor: "Setor Demandante",                                     etapa: "Homologação", subtitulo: "Aguardando validação e homologação do setor demandante." },
      { setor: "Gerência de Planejamento e Orçamento (GOP)",           etapa: "Liberação de Programação Financeira (PF)", subtitulo: "Verificando cota e liberando a PF." },
      { setor: "Superintendência Financeira (SUFIN)",                  etapa: "Ciência e Encaminhamento", subtitulo: "Dando ciência e encaminhando para a GEFIN." },
      { setor: "Gerência Financeira (GEFIN)",                          etapa: "Emissão do Empenho", subtitulo: "Preparando e emitindo a nota de empenho." },
      { setor: "Superintendência de Apoio Jurídico (SUJUR)",           etapa: "Formalização do Contrato", subtitulo: "Analisando e formalizando o contrato." },
      { setor: "Setor Demandante",                                     etapa: "Emissão da Ordem de Serviço (OS)", subtitulo: "Preparando e emitindo a OS." },
      { setor: "Gerência de Planejamento e Orçamento (GOP)",           etapa: "Liberação para Pagamento", subtitulo: "Conferindo e liberando o processo para pagamento." },
      { setor: "Gerência Financeira (GEFIN)",                          etapa: "Liquidação (LE)", subtitulo: "Realizando a liquidação do empenho." },
      { setor: "Gerência Financeira (GEFIN)",                          etapa: "Previsão de Desembolso (PD)", subtitulo: "Emitindo a previsão de desembolso." },
      { setor: "Gerência Financeira (GEFIN)",                          etapa: "Ordem Bancária (OB)", subtitulo: "Emitindo a ordem bancária." },
      { setor: "Gerência Financeira (GEFIN)",                          etapa: "Remessa (RE)", subtitulo: "Enviando a remessa ao banco." },
    ];

    let html = '';
    etapas.forEach((item, i) => {
      const numeroEtapa = i + 1;
      const ativo = (item.etapa === statusAtual);

      html += `
        <div style="margin-bottom: 1.5em;">
          <div style="display: flex; align-items: center; gap: 0.5em;">
            <div style="
              width: 24px; height: 24px;
              border-radius: 50%;
              background: ${ativo ? '#2563eb' : '#e5e7eb'};
              color: ${ativo ? '#fff' : '#6b7280'};
              text-align: center; line-height: 24px;
              font-weight: bold;">${numeroEtapa}</div>
            <div>
              <strong>${item.setor}</strong><br>
              <span style="color:#111827">${item.etapa}</span><br>
              <small style="color:#6b7280">${item.subtitulo}</small>
            </div>
          </div>
        </div>
      `;
    });

    document.getElementById('fluxoEtapasContainer').innerHTML = html;
  }

  function openDetails(p){
    document.getElementById('detNum').textContent     = p.numero_processo || '—';
    document.getElementById('detSetor').textContent   = p.setor_demandante || '—';
    document.getElementById('detDesc').textContent    = p.descricao || '—';
    document.getElementById('detCriado').textContent  = formatDateBR(p.data_registro);
    gerarFluxoDoProcesso("Análise e Autorização");
    document.getElementById('fluxoModal').classList.remove('hidden'); // ✅ novo modal!
  }

  populateStatusFilter();
  fetchProcessos();

  document.getElementById('closeDetails').addEventListener('click', () => {
    document.getElementById('fluxoModal').classList.add('hidden');
  });

  document.getElementById('salvarAcaoBtn').addEventListener('click', async () => {
    const texto = document.getElementById('descricaoAcao').value.trim();
    if (!texto) return alert('Descreva a ação.');

    const resp = await fetch('templates/salvar_acao_interna.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `texto=${encodeURIComponent(texto)}`
    });

    const data = await resp.json();
    if (data.success) {
      alert('Ação interna registrada com sucesso!');
      document.getElementById('acaoInternaModal').classList.add('hidden');
      document.getElementById('descricaoAcao').value = '';
    } else {
      alert('Erro ao salvar: ' + (data.error || 'Erro desconhecido'));
    }
  });

  document.getElementById('abrirAcaoInterna').addEventListener('click', () => {
    document.getElementById('acaoInternaModal').classList.remove('hidden');
  });

  document.getElementById('cancelarAcaoBtn').addEventListener('click', () => {
    document.getElementById('acaoInternaModal').classList.add('hidden');
  });

  </script>

</body>
</html>