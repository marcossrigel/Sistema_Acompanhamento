<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php'); exit;
}
if (($_SESSION['tipo'] ?? '') !== 'admin') {
  http_response_code(403);
  echo "Acesso restrito a administradores."; exit;
}

$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
$nome  = htmlspecialchars($_SESSION['nome']  ?? '',  ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CEHAB - TODOS (Admin)</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body{font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f7f8fb}
    .container{max-width:1100px;margin:0 auto;padding:24px;}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;}
    .chip{background:#eef2ff;color:#4f46e5;padding:4px 10px;border-radius:999px;font-weight:600}
    .item{border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;background:#fff}
    .item + .item { margin-top: 10px; }
    .muted{color:#6b7280}
    .badge{font-size:.75rem;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:999px;padding:2px 8px;font-weight:700}
    .btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:9px 12px;font-weight:600}
    .btn--muted{background:#f3f4f6}
    .btn--primary{background:#2563eb;color:#fff}
    .list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px;
      margin-top: 12px;
    }
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:16px}
    .hidden{display:none}
    .modal{background:#fff;border-radius:14px;max-width:1000px;width:100%;max-height:90vh;overflow:auto;border:1px solid #e5e7eb}
    .modal__header{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid #e5e7eb}
    .modal__body{padding:16px}
    .flow-row{display:grid;grid-template-columns:60px 1fr 140px 140px;gap:8px;border:1px solid #e5e7eb;border-radius:10px;padding:10px;margin-bottom:8px}
    .flow-head{font-weight:700;background:#f9fafb}
    .flow-card {
  border: 1px solid #bbf7d0;
  background: #ecfdf5;
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 12px;
}

.flow-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1rem;
  font-weight: 600;
  color: #065f46;
}
  </style>
</head>
<body>
  <header class="bg-white border-b border-gray-200">
    <div class="container flex items-center justify-between">
      <a href="../templates/home.php" class="flex items-center gap-3">
        <i class="fas fa-sitemap text-blue-600 text-2xl"></i>
        <h1 class="text-lg font-bold">CEHAB - Acompanhamento de Processos</h1>
      </a>
      <div class="flex items-center gap-2">
        <span class="chip"><i class="fa-solid fa-user-shield mr-1"></i> Admin • <?= $nome ?></span>
        <a href="../templates/home.php" class="btn btn--muted"><i class="fa-solid fa-chevron-left"></i> Voltar</a>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="card">
      <div class="flex items-center gap-2">
        <i class="fas fa-layer-group text-purple-600"></i>
        <h2 class="text-xl font-bold">Todos os Processos</h2>
      </div>

      <form class="flex items-center gap-2 mt-3">
        <div class="flex items-center w-full max-w-3xl border rounded-full pl-4 pr-2 py-2 bg-white">
          <i class="fa-solid fa-magnifying-glass mr-2 opacity-70"></i>
          <input id="filterNum" type="text" class="w-full outline-none"
                 placeholder="Filtrar por nº do processo (local)">
          <button id="btnClear" class="ml-2 rounded-full px-4 py-2 bg-gray-100 hover:bg-gray-200" type="button">Limpar</button>
        </div>
        <button id="btnReload" class="btn btn--primary" type="button"><i class="fa-solid fa-rotate"></i> Recarregar</button>
      </form>

      <div id="list" class="list"></div>
    </div>
  </main>

  <div id="detailsModal" class="modal-backdrop hidden">
    <div class="modal">
      <div class="modal__header">
        <h3 class="text-lg font-bold">Detalhes do Processo</h3>
        <button id="closeModal" class="btn btn--muted"><i class="fa-solid fa-xmark"></i> Fechar</button>
      </div>
      <div class="modal__body">
        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <h4 class="font-bold mb-2">Fluxo</h4>
            <div id="flowBox"></div>
          </div>
          <aside>
            <h4 class="font-bold mb-2">Informações</h4>
            <p><b>Número:</b> <span id="d_num"></span></p>
            <p><b>Nome do Processo:</b> <span id="d_nome"></span></p>
            <p><b>Setor Demandante:</b> <span id="d_setor"></span></p>
            <p><b>Enviar para:</b> <span id="d_dest"></span></p>
            <p><b>Tipos:</b> <span id="d_tipos"></span></p>
            <p><b>Descrição:</b> <span id="d_desc"></span></p>
            <p><b>Criado em:</b> <span id="d_dt"></span></p>
          </aside>
        </div>
      </div>
    </div>
  </div>

<script>
const el = id => document.getElementById(id);
const list = el('list');
let DATA = []; // memória local para filtrar

const fmt = iso => {
  if (!iso) return '—';
  const d = new Date(iso.replace(' ','T'));
  if (isNaN(d)) return iso;
  const dd = String(d.getDate()).padStart(2,'0');
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const yy = d.getFullYear();
  const hh = String(d.getHours()).padStart(2,'0');
  const mi = String(d.getMinutes()).padStart(2,'0');
  return `${dd}/${mm}/${yy} ${hh}:${mi}`;
};

function calcularTempo(ini, fim) {
  if (!ini || !fim) return '—';
  const start = new Date(ini);
  const end = new Date(fim);
  const diff = (end - start) / 60000; // minutos
  if (diff < 1) return 'menos de 1 min';
  if (diff < 60) return `${Math.round(diff)} min`;
  const horas = Math.floor(diff / 60);
  const min = Math.round(diff % 60);
  return `${horas}h ${min}min`;
}

function card(p) {
  const r = p.registro;
  const ultimo = (p.fluxo && p.fluxo.length) ? p.fluxo[p.fluxo.length-1] : null;
  const status = ultimo ? (ultimo.status || '') : '';
  return `
    <div class="item">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm muted">Nº</div>
          <div class="text-lg font-extrabold">${r.numero_processo}</div>
        </div>
        <div>${status ? `<span class="badge">${status.toUpperCase()}</span>` : ''}</div>
      </div>
      <div class="muted mt-1">${r.descricao || ''}</div>
      <div class="muted text-sm mt-1">${fmt(r.data_registro)}</div>
      <div class="mt-2 text-right">
        <button class="btn btn--muted" data-num="${r.numero_processo}" data-id="${r.id}" onclick="openDetails('${r.id}')">
          <i class="fa-regular fa-eye"></i> Detalhes
        </button>
      </div>
    </div>
  `;
}

function render(listData) {
  if (!listData.length) {
    list.innerHTML = `<div class="text-center muted p-8 border rounded-xl">Nenhum processo encontrado.</div>`;
    return;
  }
  list.innerHTML = listData.map(card).join('');
}

async function loadAll() {
  list.innerHTML = `<div class="p-6 text-center muted">Carregando…</div>`;
  const res = await fetch('svc_admin_todos.php');
  const data = await res.json();
  if (!data.ok) {
    list.innerHTML = `<div class="p-6 text-center text-red-600">Erro ao carregar.</div>`;
    return;
  }
  DATA = data.processos || [];
  render(DATA);
}

function openDetails(id) {
  const p = DATA.find(x => String(x.registro.id) === String(id));
  if (!p) return;
  el('d_nome').textContent = p.registro.nome_processo || '—';
  el('d_num').textContent   = p.registro.numero_processo || '—';
  el('d_setor').textContent = p.registro.setor_demandante || '—';
  el('d_dest').textContent  = p.registro.setor_destino || p.registro.enviar_para || '—';
  el('d_tipos').textContent = p.registro.tipos || '—';
  el('d_desc').textContent  = p.registro.descricao || '—';
  el('d_dt').textContent    = fmt(p.registro.criado_em || p.registro.data_registro);

  const flow = (p.fluxo || []).map(f => {
  const entrada = fmt(f.data_registro);
  const saida = fmt(f.data_fim);
  const tempo = f.tempo_estimado || calcularTempo(f.data_registro, f.data_fim);
  const concluido = (f.status || '').toLowerCase().includes('conclu');
  const icone = concluido ? 'fa-circle-check text-green-500' : 'fa-hourglass-half text-yellow-500';
  return `
    <div class="flow-card">
      <div class="flow-header">
        <i class="fa-solid ${icone}"></i>
        <strong>${f.setor || '—'}</strong>
        <span class="text-sm text-gray-500 ml-2">${f.status || '—'}</span>
      </div>
      <div class="flow-body text-sm text-gray-700 mt-1">
        <p><b>Entrada:</b> ${entrada} → <b>Saída:</b> ${saida}</p>
        <p><b>Tempo:</b> ${tempo}</p>
        ${f.acao_finalizadora ? `<p><b>Ação:</b> ${f.acao_finalizadora}</p>` : ''}
      </div>
    </div>
  `;
}).join('');

  el('flowBox').innerHTML = flow || `<div class="muted">Sem fluxo.</div>`;

  el('detailsModal').classList.remove('hidden');
}
window.openDetails = openDetails;

el('closeModal').addEventListener('click', () => el('detailsModal').classList.add('hidden'));
el('btnReload').addEventListener('click', loadAll);
el('btnClear').addEventListener('click', () => { el('filterNum').value=''; render(DATA); });
el('filterNum').addEventListener('input', e => {
  const q = e.target.value.trim();
  if (!q) return render(DATA);
  const f = DATA.filter(p => {
  const num = (p.registro.numero_processo || '').toLowerCase();
  const nome = (p.registro.nome_processo || '').toLowerCase();
  return num.includes(q.toLowerCase()) || nome.includes(q.toLowerCase());
});
  render(f);
});

// carrega automaticamente
loadAll();
</script>
</body>
</html>
