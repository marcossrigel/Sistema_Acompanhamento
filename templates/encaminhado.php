<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

// exige login
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php');
  exit;
}

$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
$nome  = htmlspecialchars($_SESSION['nome']  ?? '',  ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CEHAB - Encaminhados</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body{font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial,'Noto Sans';background:#f0f2f5}
  </style>
</head>
<body class="antialiased">
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
        <!-- Encaminhados: ativo -->
        <span class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg shadow-sm flex items-center cursor-default">
          <i class="fa-regular fa-share-from-square mr-2"></i> Encaminhados
        </span>
        <!-- Voltar para home -->
        <a href="home.php"
           class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center">
          <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
        </a>
      </div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white p-6 rounded-lg shadow-lg">
      <!-- Setor do usuário -->
      <div class="mb-3 flex items-center gap-2 text-sm text-gray-700">
        <i class="fas fa-building text-gray-500"></i>
        <span>Setor do usuário:</span>
        <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 font-semibold">
          <?= $setor ?>
        </span>
      </div>

      <!-- Lista -->
  <h2 class="text-xl font-semibold text-gray-700 mb-4">Processos Encaminhados</h2>
  <div id="encList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
  </div> <!-- fecha .bg-white card -->
  </main>

<!-- MODAL: Detalhes + Fluxo dinâmico -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden bg-black/40 items-center justify-center">
  <div class="bg-white rounded-lg shadow-2xl w-full max-w-5xl m-4">
    <div class="p-5 border-b flex justify-between items-center">
      <h3 class="text-xl font-semibold">Detalhes do Processo</h3>
      <button id="closeDetails" class="text-gray-500 hover:text-gray-700">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Fluxo -->
      <div class="lg:col-span-2">
        <h4 class="text-md font-semibold text-gray-800 mb-3">Histórico e Fluxo do Processo</h4>
        <div id="flowList" class="space-y-3"></div>
      </div>

      <!-- Informações gerais -->
      <aside class="lg:col-span-1">
        <div class="bg-gray-50 border rounded-lg p-4">
          <h4 class="font-semibold text-gray-800 mb-3">Informações Gerais</h4>
          <dl class="text-sm space-y-3">
            <div><dt class="text-gray-500">Número:</dt><dd id="d_num" class="font-medium">—</dd></div>
            <div><dt class="text-gray-500">Setor Demandante:</dt><dd id="d_setor" class="font-medium">—</dd></div>
            <div><dt class="text-gray-500">Enviar para:</dt><dd id="d_dest" class="font-medium">—</dd></div>
            <div><dt class="text-gray-500">Tipos:</dt><dd id="d_tipos" class="font-medium">—</dd></div>
            <div id="d_outros_row" class="hidden"><dt class="text-gray-500">Outros:</dt><dd id="d_outros" class="font-medium">—</dd></div>
            <div><dt class="text-gray-500">Descrição:</dt><dd id="d_desc" class="font-medium break-words">—</dd></div>
            <div><dt class="text-gray-500">Criado em:</dt><dd id="d_dt" class="font-medium">—</dd></div>
          </dl>

          <!-- Encaminhar (opcional: já tinha no seu modal) -->
          <div class="mt-5 border-t pt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Encaminhar para</label>
            <select id="nextSector" class="w-full border rounded-md px-3 py-2">
              <option value="" selected disabled>Selecione o próximo setor...</option>
              <option>GECOMP</option><option>DDO</option><option>CPL</option>
              <option>DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS</option>
              <option>PARECER JUR</option><option>GEFIN NE INICIAL</option><option>REMESSA</option>
              <option>GOP PF (SEFAZ)</option><option>GEFIN NE DEFINITIVO</option><option>LIQ</option>
              <option>PD (SEFAZ)</option><option>OB</option>
            </select>
            <button id="sendNextBtn" class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-md">
              Encaminhar
            </button>
          </div>
        </div>
      </aside>
    </div>

    <div class="p-5 border-t text-right">
      <button id="okDetails" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">OK</button>
    </div>
  </div>
</div>


<script>
/* ========= helpers ========= */
const MY_SETOR = <?= json_encode($_SESSION['setor'] ?? '') ?>;

const brDate = iso => {
  if (!iso) return '—';
  const d = new Date(String(iso).replace(' ', 'T'));
  return isNaN(d) ? '—'
                  : d.toLocaleDateString('pt-BR') + ' ' +
                    d.toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit' });
};
const parseTipos = j => {
  try { const a = JSON.parse(j||'[]'); return Array.isArray(a) ? a.join(', ') : ''; }
  catch { return ''; }
};

/* ========= lista: processos encaminhados para meu setor ========= */
async function loadIncoming(){
  const wrap = document.getElementById('encList');
  wrap.innerHTML = `
    <div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">
      Carregando…
    </div>`;

  try {
    const r = await fetch('listar_encaminhados.php', { credentials: 'same-origin' });
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.error || 'Falha ao listar');

    const data = j.data || [];
    if (!data.length) {
      wrap.innerHTML = `
        <div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">
          Nenhum processo encaminhado para o seu setor no momento.
        </div>`;
      return;
    }

    wrap.innerHTML = '';
    data.forEach(p => {
      const card = document.createElement('div');
      card.className = 'bg-white border rounded-lg p-4 hover:shadow-md transition cursor-pointer';
      card.innerHTML = `
        <div class="flex justify-between items-start">
          <div>
            <div class="text-sm text-gray-500">Nº</div>
            <div class="font-semibold text-gray-800">${p.numero_processo || '—'}</div>
          </div>
          <span class="text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700" title="Setor de origem">
            ${p.setor_demandante || '—'}
          </span>
        </div>
        <div class="mt-3 text-sm text-gray-600 line-clamp-2">${p.descricao || ''}</div>
        <div class="mt-3 text-right text-xs text-gray-400">${brDate(p.data_registro)}</div>
      `;
      card.addEventListener('click', () => openDetails(p));
      wrap.appendChild(card);
    });
  } catch (e) {
    console.error(e);
    wrap.innerHTML = `
      <div class="col-span-full text-red-500 border border-red-200 rounded-lg p-8 text-center">
        Erro ao carregar.
      </div>`;
  }
}

/* ========= modal: abrir/fechar ========= */
let currentProcess = null;

function openDetails(p){
  currentProcess = p;

  // informações gerais
  document.getElementById('d_num').textContent   = p.numero_processo || '—';
  document.getElementById('d_setor').textContent = p.setor_demandante || '—';
  document.getElementById('d_dest').textContent  = p.enviar_para || '—';

  const tipos = parseTipos(p.tipos_processo_json);
  document.getElementById('d_tipos').textContent = tipos || '—';

  const hasOutros = (p.tipo_outros || '').trim() !== '';
  document.getElementById('d_outros_row').classList.toggle('hidden', !hasOutros);
  document.getElementById('d_outros').textContent = p.tipo_outros || '';

  document.getElementById('d_desc').textContent = p.descricao || '';
  document.getElementById('d_dt').textContent   = brDate(p.data_registro);

  // fluxo dinâmico
  renderFlow(p.id);

  // abre modal
  const md = document.getElementById('detailsModal');
  md.classList.remove('hidden');
  md.classList.add('flex');

  // selecione próximo setor limpo
  const nextSel = document.getElementById('nextSector');
  if (nextSel) nextSel.value = '';
}

function closeDetails(){
  const md = document.getElementById('detailsModal');
  md.classList.add('hidden');
  md.classList.remove('flex');
}
document.getElementById('closeDetails').addEventListener('click', closeDetails);
document.getElementById('okDetails').addEventListener('click', closeDetails);
document.getElementById('detailsModal').addEventListener('click', (e)=>{
  if (e.target.id === 'detailsModal') closeDetails();
});

/* ========= fluxo dinâmico ========= */
function flowItem({ordem, setor, status}) {
  const isDone = status === 'concluido';
  const isNow  = status === 'atual';

  const boxCls = isDone
    ? 'bg-emerald-50 border-emerald-200'
    : (isNow ? 'bg-blue-50 border-blue-200' : 'bg-white border-gray-200');

  const badge = isDone
    ? `<div class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">
         <i class="fa-solid fa-check text-[11px]"></i>
       </div>`
    : `<div class="w-7 h-7 rounded-full ${isNow?'bg-blue-600':'bg-gray-400'} text-white flex items-center justify-center text-xs font-bold">
         ${ordem}
       </div>`;

  const sub = isDone ? 'Concluído' : (isNow ? 'Destino atual' : '');

  return `
    <div class="flex items-start gap-3 p-4 rounded-lg border ${boxCls}">
      ${badge}
      <div class="flex-1">
        <div class="font-semibold">${setor || '—'}</div>
        ${sub ? `<div class="text-xs text-gray-500">${sub}</div>` : ''}
      </div>
    </div>`;
}

async function renderFlow(processoId){
  const wrap = document.getElementById('flowList');
  wrap.innerHTML = '<div class="text-gray-400">Carregando fluxo…</div>';
  try{
    const r = await fetch(`templates/listar_fluxo.php?id=${processoId}`, { credentials:'same-origin' });
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.error || 'Falha ao listar fluxo');
    const data = j.data || [];
    wrap.innerHTML = data.map(flowItem).join('');
  }catch(e){
    console.error(e);
    wrap.innerHTML = '<div class="text-red-500">Erro ao carregar fluxo.</div>';
  }
}

/* ========= encaminhar para próximo setor ========= */
const sendBtn = document.getElementById('sendNextBtn');
if (sendBtn){
  sendBtn.addEventListener('click', async ()=>{
    if (!currentProcess) return;
    const sel = document.getElementById('nextSector');
    const novo = sel && sel.value ? sel.value.trim() : '';
    if (!novo){ alert('Selecione o próximo setor.'); return; }

    try{
      const r = await fetch('templates/encaminhar_processo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ id: currentProcess.id, novo_setor: novo })
      });
      const j = await r.json();
      if (!r.ok || !j.ok) throw new Error(j.error || 'Falha ao encaminhar');

      // atualiza “enviar_para” localmente p/ refletir nas infos gerais
      currentProcess.enviar_para = novo;

      // re-render fluxo e lista
      await renderFlow(currentProcess.id);
      await loadIncoming();

    }catch(e){
      alert(e.message || 'Erro ao encaminhar.');
      console.error(e);
    }
  });
}

/* ========= começo ========= */
loadIncoming();
</script>

</body>
</html>
