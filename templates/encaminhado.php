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
    #detailsModal #flowList ul { padding-left: 0; }
    #detailsModal #flowList li { text-indent: 0; }
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
      <!-- Informações gerais -->
<aside class="lg:col-span-1">
  <div class="bg-gray-50 border rounded-lg p-4">
    <h4 class="font-semibold text-gray-800 mb-3">Informações Gerais</h4>

    <!-- labels e valores em linha, igual ao home.php -->
    <div class="space-y-2 text-sm">
      <p><span class="text-gray-500">Número:</span> <span id="d_num" class="font-medium">—</span></p>
      <p><span class="text-gray-500">Setor Demandante:</span> <span id="d_setor" class="font-medium">—</span></p>
      <p><span class="text-gray-500">Tipos:</span> <span id="d_tipos" class="font-medium">—</span></p>
      <p id="d_outros_row" class="hidden">
        <span class="text-gray-500">Outros:</span> <span id="d_outros" class="font-medium">—</span>
      </p>
      <p><span class="text-gray-500">Descrição:</span> <span id="d_desc" class="font-medium break-words">—</span></p>
      <p><span class="text-gray-500">Criado em:</span> <span id="d_dt" class="font-medium">—</span></p>
    </div>
  </div>

  <!-- “Enviar para” separado (não faz parte do box de Informações Gerais) -->
  <div class="mt-3 text-sm">
    <p>
      <span class="text-gray-500">Enviar para:</span>
      <span id="d_dest" class="font-medium">—</span>
    </p>
  </div>

  <!-- Encaminhar (já existente) -->
    <div id="encBlock" class="mt-4 border-t pt-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">Encaminhar para</label>
      <select id="nextSector" class="w-full border rounded-md px-3 py-2">
        <option value="" selected disabled>Selecione o próximo setor...</option>
        <option>GECOMP</option><option>DDO</option><option>CPL</option>
        <option>DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS</option>
        <option>PARECER JUR</option><option>GEFIN NE INICIAL</option><option>REMESSA</option>
        <option>GOP PF (SEFAZ)</option><option>GEFIN NE DEFINITIVO</option><option>LIQ</option>
        <option>PD (SEFAZ)</option><option>OB</option>
      </select>
      <button id="btnEncaminhar" class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-md">
        Encaminhar
      </button>
    </div>

    <!-- Modal Ações Internas (inalterado) -->
    <div id="acoesModal" class="fixed inset-0 hidden bg-black/40 items-center justify-center z-[60]">
      <div class="bg-white rounded-lg p-6 w-full max-w-xl shadow-2xl">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Ações Internas do Setor</h2>
          <button id="fecharAcoes" class="text-gray-500 hover:text-gray-700"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <ul id="acoesList" class="space-y-3 max-h-64 overflow-auto mb-4"></ul>
        <label class="block text-sm text-gray-600 mb-1">Nova ação (visível a todos):</label>
        <textarea id="acaoTexto" class="w-full border rounded p-2" rows="3" placeholder="Ex.: Tive um problema com tal emenda"></textarea>
        <div class="flex justify-end gap-2 mt-3">
          <button id="cancelarAcoes" class="px-4 py-2 rounded bg-gray-200">Cancelar</button>
          <button id="salvarAcao" class="px-4 py-2 rounded bg-blue-600 text-white">Salvar ação</button>
        </div>
      </div>
    </div>
    <button id="btnAcoes" class="mt-2 w-full border bg-white hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2 rounded-md">
      Ações internas
    </button>
  </aside>

    </div>

    <div class="p-5 border-t text-right">
      <button id="okDetails" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">OK</button>
    </div>
  </div>
</div>

<!-- Modal Finalizar Etapa -->
<div id="finalizarModal" class="fixed inset-0 hidden bg-black bg-opacity-40 items-center justify-center z-50">
  <div class="bg-white rounded-lg p-6 w-full max-w-md shadow-lg">
    <h2 class="text-lg font-semibold mb-4">Finalizar Etapa</h2>
    <label class="block text-sm text-gray-600 mb-1">Descreva a ação finalizadora:</label>
    <textarea id="acaoFinalizadora" class="w-full border rounded p-2 mb-4" rows="3"
      placeholder="Ex: GECOMP analisou e encaminhou para GEFIN NE INICIAL"></textarea>
    
    <div class="flex justify-end gap-2">
      <button id="cancelarFinalizar" class="px-4 py-2 rounded bg-gray-200">Cancelar</button>
      <button id="confirmarFinalizar" class="px-4 py-2 rounded bg-blue-600 text-white">Confirmar e Avançar</button>
    </div>
  </div>
</div>

<script>

/* ======== AÇÕES INTERNAS ======== */
const acoesModal   = document.getElementById('acoesModal');
const btnAcoes     = document.getElementById('btnAcoes');
const fecharAcoes  = document.getElementById('fecharAcoes');
const cancelarAcoes= document.getElementById('cancelarAcoes');
const salvarAcao   = document.getElementById('salvarAcao');
const acoesList    = document.getElementById('acoesList');
const acaoTexto    = document.getElementById('acaoTexto');

function renderAcoesItem(a){
  const li = document.createElement('li');
  li.className = "p-3 border rounded-lg bg-gray-50";
  li.innerHTML = `
    <div class="text-sm text-gray-800 whitespace-pre-wrap break-words">${a.texto}</div>
    <div class="mt-1 text-xs text-gray-500">
      <span class="font-medium">${a.setor}</span>
      ${a.usuario ? ` • ${a.usuario}` : ''} • ${brDate(a.data_registro)}
    </div>`;
  return li;
}

async function loadAcoes(){
  if (!currentProcess) return;
  acoesList.innerHTML = `<li class="text-center text-gray-400 p-3">Carregando…</li>`;
  try{
    const r = await fetch(`listar_acoes_internas.php?id=${encodeURIComponent(currentProcess.id)}`, { credentials:'same-origin' });
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.error||'erro');
    const data = j.data || [];
    if (!data.length) {
      acoesList.innerHTML = `<li class="text-center text-gray-400 p-3">Nenhuma ação interna ainda.</li>`;
      return;
    }
    acoesList.innerHTML = "";
    data.forEach(a => acoesList.appendChild(renderAcoesItem(a)));
  }catch(e){
    console.error(e);
    acoesList.innerHTML = `<li class="text-center text-red-500 p-3">Falha ao carregar.</li>`;
  }
}

function openAcoes(){
  acaoTexto.value = '';
  acoesModal.classList.remove('hidden');
  acoesModal.classList.add('flex');
  loadAcoes();
}
function closeAcoes(){
  acoesModal.classList.add('hidden');
  acoesModal.classList.remove('flex');
}

btnAcoes?.addEventListener('click', openAcoes);
fecharAcoes?.addEventListener('click', closeAcoes);
cancelarAcoes?.addEventListener('click', closeAcoes);
acoesModal?.addEventListener('click', (e)=>{ if (e.target===acoesModal) closeAcoes(); });

salvarAcao?.addEventListener('click', async ()=>{
  const txt = (acaoTexto.value||'').trim();
  if (!txt) { alert('Descreva a ação.'); return; }
  try{
    const r = await fetch('salvar_acao_interna.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify({ processo_id: currentProcess.id, texto: txt })
    });
    const raw = await r.text();
    let j; try { j = JSON.parse(raw); } catch { throw new Error('Resposta não-JSON: '+raw); }
    if (!r.ok || !j.ok) throw new Error(j.error||'Falha ao salvar');

    acaoTexto.value = '';
    await loadAcoes();       // recarrega a lista
    await renderFlow(currentProcess.id);
  }catch(e){
    alert('Erro: ' + (e.message||e));
    console.error(e);
  }
});


/* ========= helpers ========= */
const MY_SETOR = <?= json_encode($_SESSION['setor'] ?? '') ?>;

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m =>
  ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#39;'}[m])
);

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

    const isMine = (s) => String(s || '').toLowerCase() === String(MY_SETOR || '').toLowerCase();

    const data = (j.data || []).filter(p => isMine(p.enviar_para) && !isMine(p.setor_demandante));

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
  const canAct = String(p.enviar_para||'').toLowerCase() === String(MY_SETOR||'').toLowerCase();

  const encBlock = document.getElementById('encBlock');
  const btnAcoesEl = document.getElementById('btnAcoes');
  if (encBlock)  encBlock.classList.toggle('hidden', !canAct);
  if (btnAcoesEl) btnAcoesEl.classList.toggle('hidden', !canAct);

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

function flowItem({ordem, setor, status, acao_finalizadora, acoes}) {
  const isDone = status === 'concluido';
  const isNow  = status === 'ativo' || status === 'atual';

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

  // === Ações internas como UL compacta, com bullet colado no texto (list-inside)
  const acoesHtml = (acoes || []).length
  ? (() => {
      const items = (acoes || []).map(a =>
        `<li class="text-xs leading-snug text-gray-700 break-words">${esc(a.texto)}</li>`
      ).join('');
      return `<ul class="mt-2 list-disc list-inside space-y-1">${items}</ul>`;
    })()
  : '';

  return `
    <div class="flex items-start gap-3 p-4 rounded-lg border ${boxCls}">
      ${badge}
      <div class="flex-1">
        <div class="font-semibold">${esc(setor || '—')}</div>
        ${sub ? `<div class="text-xs text-gray-500">${sub}</div>` : ''}
        ${isDone && acao_finalizadora ? `<div class="text-xs text-gray-600">Ação: ${esc(acao_finalizadora)}</div>` : ''}
        ${acoesHtml}
      </div>
    </div>`;
}

async function renderFlow(processoId){
  const wrap = document.getElementById('flowList');
  wrap.innerHTML = '<div class="text-gray-400">Carregando fluxo…</div>';

  try{
    // busca em paralelo
    const [rf, ra] = await Promise.all([
      fetch(`listar_fluxo.php?id=${encodeURIComponent(processoId)}`, { credentials:'same-origin' }),
      fetch(`listar_acoes_internas.php?id=${encodeURIComponent(processoId)}`, { credentials:'same-origin' })
    ]);

    const jf = await rf.json();
    const ja = await ra.json();

    if (!rf.ok || !jf.ok) throw new Error(jf.error || 'Falha ao listar fluxo');
    if (!ra.ok || !ja.ok) throw new Error(ja.error || 'Falha ao listar ações internas');

    const fluxo = jf.data || [];
    const acoes = ja.data || [];

    // agrupa ações por setor (case-insensitive)
    const mapAcoes = acoes.reduce((acc, a) => {
      const k = String(a.setor || '').toLowerCase();
      (acc[k] ||= []).push(a);
      return acc;
    }, {});

    // injeta as ações correspondentes em cada etapa do fluxo
    wrap.innerHTML = fluxo.map(f => {
      const key = String(f.setor || '').toLowerCase();
      return flowItem({
        ordem: f.ordem,
        setor: f.setor,
        status: f.status,
        acao_finalizadora: f.acao_finalizadora,
        acoes: mapAcoes[key] || []   // << aqui entram as ações do setor
      });
    }).join('');

  }catch(e){
    console.error(e);
    wrap.innerHTML = '<div class="text-red-500">Erro ao carregar fluxo.</div>';
  }
}

const btnEncaminhar = document.getElementById('btnEncaminhar'); 
const finalizarModal = document.getElementById('finalizarModal');
const cancelarFinalizar = document.getElementById('cancelarFinalizar');
const confirmarFinalizar = document.getElementById('confirmarFinalizar');

btnEncaminhar.addEventListener('click', () => {
  finalizarModal.classList.remove('hidden');
  finalizarModal.classList.add('flex');
});

cancelarFinalizar.addEventListener('click', () => {
  finalizarModal.classList.add('hidden');
  finalizarModal.classList.remove('flex');
});

confirmarFinalizar.addEventListener('click', async () => {
  const acao = document.getElementById('acaoFinalizadora').value.trim();
  const proxSetor = document.getElementById('nextSector').value;
  if (!acao || !proxSetor) { alert("Preencha a ação e selecione o setor."); return; }

  try {
    const resp = await fetch('encaminhar_processo.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin', // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
      body: JSON.stringify({
        id_processo: currentProcess.id,
        setor_origem: MY_SETOR,
        setor_destino: proxSetor,
        acao_finalizadora: acao
      })
    });

    const raw = await resp.text();  // lê como texto para diagnosticar
    let j;
    try { j = JSON.parse(raw); }
    catch { throw new Error('Resposta não-JSON do servidor: ' + raw); }

    if (!resp.ok || !j.ok) throw new Error(j.error || 'Falha ao encaminhar');

    // atualizar UI
    await renderFlow(currentProcess.id);
    await loadIncoming();
    document.getElementById('finalizarModal').classList.add('hidden');
    document.getElementById('finalizarModal').classList.remove('flex');

  } catch (e) {
    alert('Erro: ' + (e.message || e));
    console.error(e);
  }
});

/* ========= começo ========= */
loadIncoming();
</script>

</body>
</html>
