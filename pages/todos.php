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
    .btn--ok{background:#2563eb;color:#fff;border-radius:10px;padding:8px 14px;font-weight:700}
    .list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px;
      margin-top: 12px;
    }

    .proc-num {
      font-size: 0.95rem;   /* ~15px — um pouco menor que text-base */
      line-height: 1.2;
      word-break: break-all; /* evita estourar o card em números longos */
    }

    /* ===== Modal base ===== */
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:16px}
    .hidden{display:none}
    .modal{background:#fff;border-radius:14px;max-width:1000px;width:100%;max-height:90vh;overflow:auto;border:1px solid #e5e7eb}
    .modal__header{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid #e5e7eb}
    .modal__body{padding:16px}

    /* ===== Estilos do modal “lúdico” (iguais ao home/encaminhado) ===== */
    .modal--details{max-width:1000px}
    .modal__body--scroll{max-height:calc(90vh - 64px);overflow:auto}
    .modal-grid{display:grid;grid-template-columns: 1fr 340px; gap:16px}
    @media (max-width: 900px){ .modal-grid{grid-template-columns:1fr;} }

    .flow-col{}
    .flow-title{font-weight:700;margin-bottom:8px}
    .flow-list{display:flex;flex-direction:column;gap:10px}

    .sidebar-box{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fff}
    .sidebar-title{font-weight:700;margin-bottom:8px}
    .info-list p{margin-bottom:6px}
    .info-label{color:#6b7280;margin-right:4px}

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

  <!-- Modal “lúdico” padronizado -->
  <div id="detailsModal" class="modal-backdrop hidden">
    <div class="modal modal--details">
      <div class="modal__header">
        <h3 class="modal__title">Detalhes do Processo</h3>
        <button id="closeDetails" class="modal__close" aria-label="Fechar">
          <i class="fa-solid fa-xmark" style="font-size:20px;"></i>
        </button>
      </div>

      <div class="modal__body modal__body--scroll">
        <div class="modal-grid">
          <div class="flow-col">
            <h4 class="flow-title">Histórico e Fluxo do Processo</h4>
            <div id="flowList" class="flow-list"></div>
          </div>

          <aside>
            <div class="sidebar-box">
              <h5 class="sidebar-title">Informações Gerais</h5>
              <div class="info-list">
                <p><span class="info-label">Número:</span> <span id="d_num" class="font-medium">—</span></p>
                <p><span class="info-label">Nome do processo:</span> <span id="d_nome" class="font-medium">—</span></p>
                <p><span class="info-label">Setor Demandante:</span> <span id="d_setor" class="font-medium">—</span></p>
                <p><span class="info-label">Enviar para:</span> <span id="d_dest" class="font-medium">—</span></p>
                <p><span class="info-label">Tipos:</span> <span id="d_tipos" class="font-medium">—</span></p>
                <p id="d_outros_row" class="hidden">
                  <span class="info-label">Outros:</span> <span id="d_outros" class="font-medium">—</span>
                </p>
                <p><span class="info-label">Descrição:</span> <span id="d_desc" class="font-medium break-words">—</span></p>
                <p><span class="info-label">Criado em:</span> <span id="d_dt" class="font-medium">—</span></p>
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
  const el = id => document.getElementById(id);
  const list = el('list');
  let DATA = []; // memória local para filtrar
  let TEMPO_TIMER = null;

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
    const start = new Date(String(ini).replace(' ', 'T'));
    const end   = new Date(String(fim).replace(' ', 'T'));
    if (isNaN(start) || isNaN(end)) return '—';
    const diff = (end - start) / 60000;
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
            <div class="proc-num font-extrabold">${r.numero_processo}</div>
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

  // diferença humana entre a entrada e "agora"
function tempoNoSetor(entradaIso){
  if (!entradaIso) return '—';
  const start = new Date(String(entradaIso).replace(' ', 'T'));
  if (isNaN(start)) return '—';

  const now   = new Date();
  let diffMin = Math.floor((now - start) / 60000); // minutos

  if (diffMin < 1) return 'menos de 1 min';
  if (diffMin < 60) return `${diffMin} min`;

  const horas = Math.floor(diffMin / 60);
  const min   = diffMin % 60;
  if (horas < 24) return `${horas}h ${min}min`;

  const dias = Math.floor(horas / 24);
  const hRest = horas % 24;
  // mostra “N dias” e, se fizer sentido, as horas restantes
  return hRest ? `${dias} dia${dias>1?'s':''} • ${hRest}h` : `${dias} dia${dias>1?'s':''}`;
}


  // === helpers iguais aos de home/encaminhado ===
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#39;'}[m])
  );

  const brDate = iso => {
    if (!iso) return '—';
    const d = new Date(String(iso).replace(' ', 'T'));
    return isNaN(d)
      ? '—'
      : d.toLocaleDateString('pt-BR') + ' ' +
        d.toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit' });
  };

  const parseTipos = j => {
    try { const a = JSON.parse(j||'[]'); return Array.isArray(a) ? a.join(', ') : (j||''); }
    catch { return j || ''; }
  };

  // === renderer do item do fluxo (mesmo dos outros arquivos) ===
  function flowItem({ordem, setor, status, acao_finalizadora, acoes = [], entrada, saida, tempo, isFirstCreator = false, extraHtml = ''}) {
    const st = String(status||'').trim().toLowerCase();
    const isDone = st === 'concluido' || st.includes('conclu');
    const isNow  = st === 'ativo' || st === 'atual';

    const boxCls = isDone
      ? 'bg-emerald-50 border-emerald-200'
      : (isNow ? 'bg-blue-50 border-blue-200' : 'bg-white border-gray-200');

    const badge = isDone
      ? `<div class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold">
           <i class="fa-solid fa-check text-[11px]"></i>
         </div>`
      : `<div class="w-7 h-7 rounded-full ${isNow?'bg-blue-600':'bg-gray-400'} text-white flex items-center justify-center text-xs font-bold">
           ${ordem ?? ''}
         </div>`;

    const sub = isDone ? 'Concluído' : (isNow ? 'Destino atual' : '');

    const entrou = brDate(entrada);
    const saidaEfetiva = isFirstCreator ? entrada : saida;
    const saiu = (isDone || isFirstCreator) ? brDate(saidaEfetiva) : null;
    const icone  = `<i class="fa-solid fa-arrow-right-long mx-1"></i><i class="fa-solid fa-door-open"></i>`;

    let datasHtml = '';
    if (isFirstCreator) {
      datasHtml = `
        <div class="mt-1 text-xs text-gray-600">
          <span class="text-gray-500">Entrada:</span> ${entrou}
          <span class="mx-2 text-gray-400">${icone}</span>
          <span class="text-gray-500">Saída:</span> ${saiu}
        </div>`;
    } else if (isDone) {
      datasHtml = `
        <div class="mt-1 text-xs text-gray-600">
          <span class="text-gray-500">Entrada:</span> ${entrou}
          <span class="mx-2 text-gray-400">${icone}</span>
          <span class="text-gray-500">Saída:</span> ${saiu}
          <span class="mx-2 text-gray-300">•</span>
          <span class="text-gray-500">Tempo:</span> ${esc(tempo || '—')}
        </div>`;
    } else {
      datasHtml = `
        <div class="mt-1 text-xs text-gray-600">
          <span class="text-gray-500">Entrada:</span> ${entrou}
          <span class="mx-2 text-gray-300">•</span>
          <span class="text-gray-500">Tempo no setor:</span>
          <span class="tempo-no-setor" data-entrada-setor="${entrada}">${esc(tempo || '—')}</span>
        </div>`;
    }

    return `
      <div class="flex items-start gap-3 p-4 rounded-lg border ${boxCls}">
        ${badge}
        <div class="flex-1">
          <div class="font-semibold">${esc(setor || '—')}</div>
          ${sub ? `<div class="text-xs text-gray-500">${sub}</div>` : ''}
          ${datasHtml}
          ${isDone && acao_finalizadora ? `<div class="text-xs text-gray-600">Ação: ${esc(acao_finalizadora)}</div>` : ''}
          ${extraHtml}
        </div>
      </div>`;
  }

  // === abre o modal com o mesmo layout/UX dos outros ===
  function openDetails(id) {

    const p = DATA.find(x => String(x.registro.id) === String(id));
    if (!p) return;

    const r = p.registro || {};
    el('d_num').textContent   = r.numero_processo || '—';
    el('d_nome').textContent  = r.nome_processo   || '—';
    el('d_setor').textContent = r.setor_demandante || '—';
    el('d_dest').textContent  = r.setor_destino || r.enviar_para || '—';

    const tipos = r.tipos_processo_json || r.tipos || '[]';
    const tiposTxt = parseTipos(tipos);
    el('d_tipos').textContent = tiposTxt || '—';

    const hasOutros = (r.tipo_outros || '').trim() !== '';
    document.getElementById('d_outros_row').classList.toggle('hidden', !hasOutros);
    document.getElementById('d_outros').textContent = r.tipo_outros || '';

    el('d_desc').textContent = r.descricao || '';
    el('d_dt').textContent   = brDate(r.criado_em || r.data_registro);

    // --- normalizador base
    const norm = s => String(s || '')
      .normalize?.('NFD')
      .replace(/\p{Diacritic}/gu, '')
      .replace(/\s+/g, ' ')
      .trim()
      .toLowerCase();

    // pega só a parte antes de " - " e normaliza (cobre "DAF - ..." vs "DAF")
    const normKey = s => norm(String(s).split(' - ')[0]);

    // setor demandante (criador)
    const setorCriador = normKey(r.setor_demandante);

    // fluxo (garante array)
    const fluxo = Array.isArray(p.fluxo) ? p.fluxo : [];
    

    // primeira ocorrência do criador no fluxo
    const firstCreatorIdx = fluxo.findIndex(f => normKey(f.setor) === setorCriador);

    // render dos cards do fluxo
    let htmlFlow = '';
    let idxAtual = -1;
    htmlFlow = fluxo.map((f, idx) => {
      const isFirstCreator = (idx === firstCreatorIdx);
      const st = String(f.status||'').trim().toLowerCase();
      const isNow  = st === 'ativo' || st === 'atual';

      // tempo: se já concluiu, calcula entre entrada e saída; se atual, calcula até agora
      const tempoCalc = isNow
        ? tempoNoSetor(f.data_registro)
        : (f.data_registro && f.data_fim ? calcularTempo(f.data_registro, f.data_fim) : '—');

      return flowItem({
        ordem: f.ordem,
        setor: f.setor,
        status: f.status,
        acao_finalizadora: f.acao_finalizadora,
        acoes: [],
        entrada: f.data_registro,
        saida:  f.data_fim,
        tempo:  tempoCalc,
        isFirstCreator,
        extraHtml: isNow ? '<div id="acoes-internas" class="mt-2"></div>' : ''
      });
    }).join('');

document.getElementById('flowList').innerHTML =
  htmlFlow || `<div class="text-gray-400">Sem fluxo.</div>`;

// atualiza imediatamente os spans "tempo-no-setor"
document.querySelectorAll('.tempo-no-setor').forEach(span => {
  const entrada = span.getAttribute('data-entrada-setor');
  span.textContent = tempoNoSetor(entrada);
});

// renova o timer (1 min) enquanto o modal estiver aberto
if (TEMPO_TIMER) clearInterval(TEMPO_TIMER);
TEMPO_TIMER = setInterval(() => {
  document.querySelectorAll('.tempo-no-setor').forEach(span => {
    const entrada = span.getAttribute('data-entrada-setor');
    span.textContent = tempoNoSetor(entrada);
  });
}, 60000);


fetch(`../templates/listar_acoes_internas.php?id=${encodeURIComponent(id)}`, { credentials: 'same-origin' })
  .then(async res => {
    const raw = await res.text();
    let data; try { data = JSON.parse(raw); } catch { throw new Error('Resposta não-JSON: ' + raw); }
    if (!res.ok || !data.ok) throw new Error(data.error || 'Falha ao listar ações internas');

    const acoes = data.data || [];

    const fluxo = Array.isArray(p.fluxo) ? p.fluxo : [];
    const norm = s => String(s||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').replace(/\s+/g,' ').trim().toLowerCase();
    const keyBase = s => norm(String(s||'').split(' - ')[0]);

    let setorAtual = '';
    for (const f of fluxo) {
      const st = String(f.status||'').toLowerCase();
      if (st === 'ativo' || st === 'atual') { setorAtual = f.setor || ''; break; }
    }
    if (!setorAtual && r.enviar_para) setorAtual = r.enviar_para;

    const keyAtual = keyBase(setorAtual);
    let acoesDoSetor = keyAtual ? acoes.filter(a => keyBase(a.setor) === keyAtual) : acoes;
    if (!acoesDoSetor.length) acoesDoSetor = acoes;

    const blocos = acoesDoSetor.map(a => `
      <div class="ml-8 mt-2 text-sm text-gray-700 border-l-4 border-blue-300 pl-3">
        <span class="block text-gray-800">• ${esc(a.texto)}</span>
        <span class="text-xs text-gray-500">
          ${a.setor ? esc(a.setor)+' • ' : ''}${a.usuario ? esc(a.usuario)+' • ' : ''}${brDate(a.data_registro)}
        </span>
      </div>
    `).join('');

    const alvo = document.getElementById('acoes-internas');
    if (alvo) alvo.innerHTML = blocos;
    else document.getElementById('flowList').insertAdjacentHTML('beforeend', blocos);
  })
  .catch(err => console.error('Erro ao carregar ações internas:', err));

    const md = document.getElementById('detailsModal');
    md.classList.remove('hidden');
    md.classList.add('flex');
  }
  window.openDetails = openDetails;

  function closeDetails(){
    const md = document.getElementById('detailsModal');
    md.classList.add('hidden');
    md.classList.remove('flex');
    if (TEMPO_TIMER) { clearInterval(TEMPO_TIMER); TEMPO_TIMER = null; }
  }
  document.getElementById('closeDetails')?.addEventListener('click', closeDetails);
  document.getElementById('okDetails')?.addEventListener('click', closeDetails);
  document.getElementById('detailsModal')?.addEventListener('click', (e)=>{ if (e.target.id==='detailsModal') closeDetails(); });

  // botões da lista
  document.getElementById('btnReload').addEventListener('click', loadAll);
  document.getElementById('btnClear').addEventListener('click', () => { el('filterNum').value=''; render(DATA); });
  document.getElementById('filterNum').addEventListener('input', e => {
    const q = e.target.value.trim().toLowerCase();
    if (!q) return render(DATA);
    const f = DATA.filter(p => {
      const num  = (p.registro.numero_processo || '').toLowerCase();
      const nome = (p.registro.nome_processo  || '').toLowerCase();
      return num.includes(q) || nome.includes(q);
    });
    render(f);
  });

  // carrega automaticamente
  loadAll();
  </script>
</body>
</html>
