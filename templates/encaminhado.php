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

      <h2 class="text-xl font-semibold text-gray-700 mb-4">Processos Encaminhados</h2>

      <!-- Placeholder da lista -->
      <div id="encList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>

  </main>

<!-- MODAL DETALHES (mesmo do home) -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden bg-black/40 items-center justify-center">
  <div class="bg-white rounded-lg shadow-2xl w-full max-w-xl m-4">
    <div class="p-5 border-b flex justify-between items-center">
      <h3 class="text-xl font-semibold">Detalhes do Processo</h3>
      <button id="closeDetails" class="text-gray-500 hover:text-gray-700">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>
    <div class="p-5 space-y-2 text-sm">
      <p><strong>Número:</strong> <span id="d_num"></span></p>
      <p><strong>Setor demandante:</strong> <span id="d_setor"></span></p>
      <p><strong>Enviar para:</strong> <span id="d_dest"></span></p>
      <p><strong>Tipos:</strong> <span id="d_tipos"></span></p>
      <p class="hidden" id="d_outros_row"><strong>Outros:</strong> <span id="d_outros"></span></p>
      <p><strong>Descrição:</strong> <span id="d_desc"></span></p>
      <p><strong>Criado em:</strong> <span id="d_dt"></span></p>
    </div>
    <div class="p-5 border-t text-right">
      <button id="okDetails" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">OK</button>
    </div>
  </div>
</div>


<script>
  const brDate = iso => {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleDateString('pt-BR')+' '+d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
  };
  const parseTipos = j => {
    try { const arr = JSON.parse(j||'[]'); return Array.isArray(arr) ? arr.join(', ') : ''; }
    catch(e){ return ''; }
  };

  async function loadIncoming(){
    const wrap = document.getElementById('encList');
    wrap.innerHTML = '<div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">Carregando…</div>';
    try{
      const r = await fetch('listar_encaminhados.php', { credentials:'same-origin' })
      const j = await r.json();
      if(!r.ok || !j.ok) throw new Error(j.error||'erro');
      const data = j.data||[];
      if(!data.length){
        wrap.innerHTML = '<div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">Nenhum processo encaminhado para seu setor.</div>';
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
            <span class="text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700" title="Setor de origem">${p.setor_demandante||'—'}</span>
          </div>
          <div class="mt-3 text-sm text-gray-600 line-clamp-2">${p.descricao||''}</div>
          <div class="mt-3 text-right text-xs text-gray-400">${brDate(p.data_registro)}</div>
        `;
        card.addEventListener('click', ()=>openDetails(p));
        wrap.appendChild(card);
      });
    }catch(e){
      wrap.innerHTML = '<div class="col-span-full text-red-500 border border-red-200 rounded-lg p-8 text-center">Erro ao carregar.</div>';
      console.error(e);
    }
  }

  // modal detalhes (mesmos IDs do da home, inclua o bloco HTML do modal neste arquivo)
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
    md.classList.remove('hidden'); md.classList.add('flex');
  }
  function closeDetails(){ md.classList.add('hidden'); md.classList.remove('flex'); }
  document.getElementById('closeDetails').addEventListener('click', closeDetails);
  document.getElementById('okDetails').addEventListener('click', closeDetails);
  md.addEventListener('click', e=>{ if(e.target===md) closeDetails(); });

  loadIncoming();
</script>

</body>
</html>
