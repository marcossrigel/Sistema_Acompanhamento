<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CEHAB - Acompanhamento de Processos</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; background:#f0f2f5; }
  </style>
</head>
<body class="antialiased">
  <!-- HEADER / TOP BAR -->
  <header class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
      <div class="flex items-center">
        <i class="fas fa-sitemap text-3xl text-blue-600 mr-3"></i>
        <h1 class="text-2xl font-bold text-gray-800">CEHAB - Acompanhamento de Processos</h1>
      </div>
      <div class="flex items-center gap-2">
        <a href="encaminhado.php"
          class="bg-white border border-blue-600 text-blue-600 hover:bg-blue-50 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center">
          <i class="fa-regular fa-share-from-square mr-2"></i> Encaminhados
        </a>

        <!-- ABRIR MODAL -->
        <button id="newProcessBtn"
          class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
          <i class="fas fa-plus mr-2"></i> Novo Processo
        </button>

        <!-- Sair: faz logout e redireciona para GETIC -->
        <a href="../index.php?logout=1&go=getic"
           class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-300 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center">
          <i class="fa-solid fa-right-from-bracket mr-2"></i> Sair
        </a>
      </div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white p-6 rounded-lg shadow-lg">
      <!-- Linha do setor do usuário -->
      <div class="mb-3 flex items-center gap-2 text-sm text-gray-700">
        <i class="fas fa-building text-gray-500"></i>
        <span>Setor do usuário:</span>
        <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 font-semibold">
          <?= $setor ?>
        </span>
      </div>

      <h2 class="text-xl font-semibold text-gray-700 mb-4">Processos em Andamento</h2>

      <div id="processList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
    </div>
  </main>

  <!-- MODAL: NOVO PROCESSO -->
  <div id="processModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-30 items-center justify-center">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg m-4 overflow-hidden">
      <div class="p-6 border-b">
        <h3 class="text-2xl font-semibold text-gray-800">Novo Processo</h3>
      </div>
      <form id="processForm" class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Número do Processo</label>
          <input id="processNumber" type="text" required
                 class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Setor Demandante</label>
          <input id="requestingSectorModal" type="text" value="<?= $setor ?>" readonly
                 class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 cursor-not-allowed">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Enviar para</label>
          <select id="destSector" required
                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            <option value="" selected disabled>Selecione o setor...</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de processo</label>

          <label class="flex items-center gap-2 cursor-pointer mb-2">
            <input type="checkbox" name="tipo_proc" value="nova licitação/aquisição" class="h-4 w-4">
            <span>nova licitação/aquisição</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer mb-2">
            <input type="checkbox" name="tipo_proc" value="solicitação de pagamento" class="h-4 w-4">
            <span>solicitação de pagamento</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer mb-2">
            <input id="tipoOutrosCheck" type="checkbox" name="tipo_proc" value="outros" class="h-4 w-4">
            <span>outros</span>
          </label>

          <input id="tipoOutrosInput" type="text" placeholder="Descreva o tipo…"
                 class="hidden w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Descrição</label>
          <textarea id="description" rows="3" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Descreva o processo..."></textarea>
        </div>

        <div class="flex justify-end pt-4 gap-2">
          <button type="button" id="closeModalBtn"
                  class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">
            Cancelar
          </button>
          <button type="submit"
                  class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
            Salvar Processo
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL DE SUCESSO -->
<div id="successModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-30 items-center justify-center">
  <div class="bg-white rounded-lg shadow-2xl w-full max-w-sm m-4 overflow-hidden">
    <div class="p-6 text-center">
      <h3 class="text-xl font-semibold text-gray-800 mb-4">Processo salvo com sucesso!</h3>
      <button id="successOkBtn"
              class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
        OK
      </button>
    </div>
  </div>
</div>

<!-- MODAL DETALHES (com fluxo) -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden bg-black/40 items-center justify-center">
  <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl m-4">
    <div class="p-5 border-b flex justify-between items-center">
      <h3 class="text-xl font-semibold">Detalhes do Processo</h3>
      <button id="closeDetails" class="text-gray-500 hover:text-gray-700">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <div class="p-5">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Coluna esquerda: Fluxo -->
        <div class="lg:col-span-2">
          <h4 class="text-base font-semibold text-gray-800 mb-3">Histórico e Fluxo do Processo</h4>
          <ol id="flowList" class="space-y-3"></ol>
        </div>

        <!-- Coluna direita: Informações Gerais -->
        <aside class="lg:col-span-1">
          <div class="bg-gray-50 border rounded-lg p-4">
            <h5 class="font-semibold text-gray-800 mb-3">Informações Gerais</h5>
            <div class="space-y-2 text-sm">
              <p><span class="text-gray-500">Número:</span> <span id="d_num" class="font-medium"></span></p>
              <p><span class="text-gray-500">Setor Demandante:</span> <span id="d_setor" class="font-medium"></span></p>
              <p><span class="text-gray-500">Enviar para:</span> <span id="d_dest" class="font-medium"></span></p>
              <p><span class="text-gray-500">Tipos:</span> <span id="d_tipos" class="font-medium"></span></p>
              <p id="d_outros_row" class="hidden">
                <span class="text-gray-500">Outros:</span> <span id="d_outros" class="font-medium"></span>
              </p>
              <p><span class="text-gray-500">Descrição:</span> <span id="d_desc" class="font-medium"></span></p>
              <p><span class="text-gray-500">Criado em:</span> <span id="d_dt" class="font-medium"></span></p>
            </div>
          </div>

          <div class="mt-4 text-right">
            <button id="okDetails"
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
              OK
            </button>
          </div>
        </aside>
      </div>
    </div>
  </div>
</div>


  <script>

    // lista de setores destino
    const SECTORS_DEST = [
      'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS',
      'GECOMP','DDO','CPL','DAF - HOMOLOGACAO','PARECER JUR',
      'GEFIN NE INICIAL','REMESSA','GOP PF (SEFAZ)','GEFIN NE DEFINITIVO',
      'LIQ','PD (SEFAZ)','OB'
    ];

    const openBtn  = document.getElementById('newProcessBtn');
    const modal    = document.getElementById('processModal');
    const closeBtn = document.getElementById('closeModalBtn');
    const form     = document.getElementById('processForm');

    const destSelect      = document.getElementById('destSector');
    const tipoOutrosCheck = document.getElementById('tipoOutrosCheck');
    const tipoOutrosInput = document.getElementById('tipoOutrosInput');

    function populateDest() {
      // limpa mantendo placeholder
      destSelect.innerHTML = '<option value="" selected disabled>Selecione o setor...</option>';
      SECTORS_DEST.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        destSelect.appendChild(opt);
      });
    }

    function openModal() {
      populateDest();
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.getElementById('processNumber').focus();
    }
    function closeModal() {
      form.reset();
      tipoOutrosInput.classList.add('hidden');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    // Outros → abre/fecha input
    tipoOutrosCheck.addEventListener('change', () => {
      if (tipoOutrosCheck.checked) {
        tipoOutrosInput.classList.remove('hidden');
        tipoOutrosInput.focus();
      } else {
        tipoOutrosInput.classList.add('hidden');
        tipoOutrosInput.value = '';
      }
    });

    // submit
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const numero = document.getElementById('processNumber').value.trim();
      const descricao = document.getElementById('description').value.trim();
      const enviarPara = destSelect.value;

      const tipos = Array.from(document.querySelectorAll('input[name="tipo_proc"]:checked')).map(i => i.value);
      let outrosTxt = '';
      if (tipos.includes('outros')) {
        outrosTxt = (tipoOutrosInput.value || '').trim();
        if (!outrosTxt) { alert('Descreva o tipo em "outros" ou desmarque.'); return; }
      }

      if (!numero || !descricao || !enviarPara || tipos.length === 0) {
        alert('Preencha número, descrição, “enviar para” e selecione ao menos um tipo.');
        return;
      }

      try {
        const resp = await fetch('salvar_processo.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            numero_processo: numero,
            enviar_para: enviarPara,
            tipos_processo: tipos,
            tipo_outros: outrosTxt,
            descricao: descricao
          })
        });
        const json = await resp.json();
        if (!resp.ok || !json.ok) throw new Error(json.error || 'Erro ao salvar.');
        // Exibe modal de sucesso
        document.getElementById('successModal').classList.remove('hidden');
        document.getElementById('successModal').classList.add('flex');
        closeModal();
      } catch (err) {
        console.error(err);
        alert('Falha ao salvar o processo. Tente novamente.');
      }
    });

    document.getElementById('successOkBtn').addEventListener('click', () => {
      const modal = document.getElementById('successModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    });

    // helpers
  const brDate = iso => {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleDateString('pt-BR')+' '+d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
  };
  const parseTipos = j => {
    try { const arr = JSON.parse(j||'[]'); return Array.isArray(arr) ? arr.join(', ') : ''; }
    catch(e){ return ''; }
  };

  // carregar processos CRIADOS PELO MEU SETOR (home)
  async function loadMyProcesses(){
    const wrap = document.getElementById('processList');
    wrap.innerHTML = '<div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">Carregando…</div>';
    try {
      const r = await fetch('listar_processos.php', { credentials:'same-origin' })
      const j = await r.json();
      if(!r.ok || !j.ok) throw new Error(j.error||'erro');
      const data = j.data||[];
      if (!data.length){
        wrap.innerHTML = '<div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">Nenhum processo encontrado.</div>';
        return;
      }
      wrap.innerHTML='';
      data.forEach(p=>{
        const card = document.createElement('div');
        card.className='bg-white border rounded-lg p-4 hover:shadow-md transition cursor-pointer';
        card.innerHTML = `
          <div class="flex justify-between items-start">
            <div>
              <div class="text-sm text-gray-500">Nº</div>
              <div class="font-semibold text-gray-800">${p.numero_processo||'—'}</div>
            </div>
            <span class="text-xs px-2 py-1 rounded-full bg-indigo-50 text-indigo-700">${p.enviar_para||'—'}</span>
          </div>
          <div class="mt-3 text-sm text-gray-600 line-clamp-2">${p.descricao||''}</div>
          <div class="mt-3 text-right text-xs text-gray-400">${brDate(p.data_registro)}</div>
        `;
        card.addEventListener('click', ()=>openDetails(p));
        wrap.appendChild(card);
      });
    } catch(e){
      wrap.innerHTML = '<div class="col-span-full text-red-500 border border-red-200 rounded-lg p-8 text-center">Erro ao carregar.</div>';
      console.error(e);
    }
  }

  // modal detalhes
  const md = document.getElementById('detailsModal');
  function openDetails(p){
    document.getElementById('d_num').textContent   = p.numero_processo||'—';
    document.getElementById('d_setor').textContent = p.setor_demandante||'—';
    document.getElementById('d_dest').textContent  = p.enviar_para||'—';

    const tipos = parseTipos(p.tipos_processo_json);
    document.getElementById('d_tipos').textContent = tipos||'—';

    const hasOutros = (p.tipo_outros||'').trim() !== '';
    document.getElementById('d_outros_row').classList.toggle('hidden', !hasOutros);
    document.getElementById('d_outros').textContent = p.tipo_outros||'';

    document.getElementById('d_desc').textContent  = p.descricao||'';
    document.getElementById('d_dt').textContent    = brDate(p.data_registro);

    // <<< NOVO: desenha o fluxo
    buildFlow(p);

    document.getElementById('detailsModal').classList.remove('hidden');
    document.getElementById('detailsModal').classList.add('flex');
  }

  function closeDetails(){ md.classList.add('hidden'); md.classList.remove('flex'); }
  document.getElementById('closeDetails').addEventListener('click', closeDetails);
  document.getElementById('okDetails').addEventListener('click', closeDetails);
  md.addEventListener('click', e=>{ if(e.target===md) closeDetails(); });

  // dispara o load da home
  loadMyProcesses();
  const MY_SETOR = <?= json_encode($_SESSION['setor'] ?? '') ?>;

// desenha um “passo” do fluxo
function makeStep(idx, title, subtitle, active=false) {
  const bullet =
    active
      ? `<span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white font-bold mr-3">${idx}</span>`
      : `<span class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-500 font-bold mr-3">${idx}</span>`;

  const wrap = document.createElement('li');
  wrap.className = `flex items-start p-3 rounded-lg border ${
    active ? 'border-blue-200 bg-blue-50/60' : 'border-gray-200 bg-white'
  }`;

  wrap.innerHTML = `
    ${bullet}
    <div class="min-w-0">
      <div class="font-semibold ${active ? 'text-blue-800' : 'text-gray-800'}">${title}</div>
      ${subtitle ? `<div class="text-xs ${active ? 'text-blue-700' : 'text-gray-500'}">${subtitle}</div>` : ''}
    </div>
  `;
  return wrap;
}

// monta o fluxo simples: 1) Demandante → 2) Destino
function buildFlow(p) {
  const flow = document.getElementById('flowList');
  flow.innerHTML = '';

  // qual passo está “ativo”?
  // - se o setor logado aparecer em algum ponto do fluxo, esse é o atual;
  // - senão, marcamos o “enviar_para” como atual (ex.: quem criou quer ver onde está).
  const current =
    (MY_SETOR && [p.setor_demandante, p.enviar_para].includes(MY_SETOR))
      ? MY_SETOR
      : (p.enviar_para || '');

  const steps = [
    { t: p.setor_demandante || '—', sub: 'Setor demandante' },
    { t: p.enviar_para      || '—', sub: 'Destino atual'   },
  ];

  steps.forEach((s, i) => {
    const active = (s.t === current);
    flow.appendChild(makeStep(i+1, s.t, s.sub, active));
  });
}
  </script>

</body>
</html>
