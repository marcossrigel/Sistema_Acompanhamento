const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m =>
  ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#39;'}[m])
);

// ====== ELEMENTOS DO MODAL "NOVO PROCESSO" ======
const openBtn        = document.getElementById('newProcessBtn');
const modal          = document.getElementById('processModal');
const closeBtn       = document.getElementById('closeModalBtn');
const closeBtnGhost  = document.getElementById('closeModalBtn_ghost');
const form           = document.getElementById('processForm');

const destSelect      = document.getElementById('destSector');
const tipoOutrosRadio = document.getElementById('tipoOutrosRadio');
const tipoOutrosInput = document.getElementById('tipoOutrosInput');

const procInput = document.getElementById('processNumber');

// radios de tipo de processo
const tipoRadios = document.querySelectorAll('input[name="tipo_proc"]');

function toggleOutros() {
  const sel = document.querySelector('input[name="tipo_proc"]:checked');
  const isOutros = !!sel && sel.value === 'outros';
  if (isOutros) {
    tipoOutrosInput.classList.remove('hidden');
    tipoOutrosInput.required = true;
  } else {
    tipoOutrosInput.classList.add('hidden');
    tipoOutrosInput.required = false;
    tipoOutrosInput.value = '';
  }
}

tipoRadios.forEach(r => r.addEventListener('change', toggleOutros));

// ====== BUSCA NA HOME ======
const frmBuscaHome   = document.getElementById('frmBuscaHome');
const inputHome      = document.getElementById('searchNumeroHome');
const btnLimparHome  = document.getElementById('btnLimparHome');
const wrap = document.getElementById('processList');

// foco ao abrir a página
window.addEventListener('DOMContentLoaded', () => {
  inputHome?.focus();
});

// Esc limpa o campo
inputHome?.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    e.preventDefault();
    inputHome.value = '';
  }
});

// submit atualiza a URL e recarrega a lista
frmBuscaHome?.addEventListener('submit', (e) => {
  e.preventDefault();
  const termo = (inputHome?.value || '').trim();
  const url = new URL(window.location.href);
  if (termo) url.searchParams.set('busca', termo);
  else url.searchParams.delete('busca');
  history.replaceState({}, '', url.toString());
  loadMyProcesses();
});

// botão limpar
btnLimparHome?.addEventListener('click', () => {
  inputHome.value = '';
  inputHome.focus(); inputHome.select?.();
  const url = new URL(window.location.href);
  url.searchParams.delete('busca');
  url.searchParams.delete('numero'); // compat antigas
  history.replaceState({}, '', url.toString());
  loadMyProcesses();
});

// pré-preenche pelo querystring se houver
(function initBuscaHomeFromURL(){
  const url = new URL(window.location.href);
  const termo = url.searchParams.get('busca') || url.searchParams.get('numero') || '';
  if (inputHome) inputHome.value = termo;
})();

let __sectorsCache = null;
async function getSectors() {
  if (__sectorsCache) return __sectorsCache;
  const r = await fetch('../templates/listar_setores.php', { credentials: 'same-origin' });

  const j = await r.json();
  console.log('[listar_processos] status', r.status, 'payload', j);
  if (!r.ok || !j.ok) throw new Error(j.error || 'Falha ao listar setores');
  const data = j.data || [];
  const arr = data.map(x => x.nome);
  const byName = Object.fromEntries(data.map(x => [x.nome, (x.sigla || '').toUpperCase()]));
  const norm = s => String(s||'')
    .normalize('NFD').replace(/\p{Diacritic}/gu,'')
    .replace(/\s+/g,' ').trim().toLowerCase();
  const byNorm = Object.fromEntries(data.map(x => [norm(x.nome), (x.sigla || '').toUpperCase()]));
  __sectorsCache = { arr, final: j.finalizador, byName, byNorm };
  console.log('[listar_processos] data.length =', data.length);
  return __sectorsCache;
}

const __norm = s => String(s||'')
  .normalize('NFD').replace(/\p{Diacritic}/gu,'')
  .replace(/\s+/g,' ').trim().toLowerCase();

function getSigla(setorNome){
  if (!__sectorsCache) return '';
  const k = __norm(setorNome);
  return __sectorsCache.byNorm[k] || '';
}

async function populateDest() {
  if (!destSelect) return;
  destSelect.innerHTML = '<option value="" selected disabled>Selecione o setor...</option>';
  try {
    const { arr } = await getSectors();
    arr.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s; opt.textContent = s;
      destSelect.appendChild(opt);
    });
  } catch (e) { console.error(e); }
}


function openModal() {
  populateDest();
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  toggleOutros();
  document.getElementById('processNumber')?.focus();
}
function closeModal() {
  form?.reset();
  tipoOutrosInput?.classList.add('hidden');
  tipoOutrosInput.required = false;
  // garante que nenhum radio fica marcado ao reabrir (form.reset normalmente já faz isso)
  document.querySelectorAll('input[name="tipo_proc"]').forEach(r => r.checked = false);
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

openBtn?.addEventListener('click', openModal);
closeBtn?.addEventListener('click', closeModal);
closeBtnGhost?.addEventListener('click', closeModal);
modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

form?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const numero      = document.getElementById('processNumber').value.trim();
  const nomeProc    = document.getElementById('processName').value.trim(); // <-- NOVO
  const descricao   = document.getElementById('description').value.trim();
  const enviarPara  = destSelect.value;

  const selTipo = document.querySelector('input[name="tipo_proc"]:checked');
  const tipoSelecionado = selTipo ? selTipo.value : '';

  let outrosTxt = '';
  if (tipoSelecionado === 'outros') {
    outrosTxt = (tipoOutrosInput.value || '').trim();
    if (!outrosTxt) { alert('Descreva o tipo em "outros" ou escolha outro tipo.'); return; }
  }

  // validações
  if (!numero || !nomeProc || !descricao || !enviarPara || !tipoSelecionado) {
    alert('Preencha número, NOME DO PROCESSO, descrição, “enviar para” e selecione o tipo.');
    return;
  }
  if (nomeProc.length > 150) {
    alert('O nome do processo deve ter no máximo 150 caracteres.');
    return;
  }

  try {
    const resp = await fetch('salvar_processo.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        numero_processo: numero,
        nome_processo:   nomeProc,
        enviar_para:     enviarPara,
        tipos_processo:  [tipoSelecionado],
        tipo_outros:     outrosTxt,
        descricao
      })
    });

    let json = null;
    try { json = await resp.json(); } catch (_) {}

    if (!resp.ok || !json?.ok) {
      console.error('Falha ao salvar:', { status: resp.status, json });
      alert(json?.error || `Erro ao salvar. HTTP ${resp.status}`);
      return;
    }
    document.getElementById('successModal').classList.remove('hidden');
    document.getElementById('successModal').classList.add('flex');
    closeModal();
    loadMyProcesses();
  } catch (err) {
    console.error(err);
    alert('Falha ao salvar o processo. Tente novamente.');
  }
});


document.getElementById('successOkBtn')?.addEventListener('click', () => {
  const m = document.getElementById('successModal');
  m.classList.add('hidden');
  m.classList.remove('flex');
});

// ====== HELPERS ======
const brDate = iso => {
  if (!iso) return '—';
  const d = new Date(String(iso).replace(' ', 'T'));
  return isNaN(d)
    ? '—'
    : d.toLocaleDateString('pt-BR') + ' ' +
      d.toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit' });
};
const parseTipos = j => {
  try { const a = JSON.parse(j||'[]'); return Array.isArray(a) ? a.join(', ') : ''; }
  catch { return ''; }
};

// Adicione (se ainda não tiver neste arquivo)
function renderBadgeConcluido(){
  return `<span class="badge-done ml-2 inline-flex items-center px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
    <i class="fa-solid fa-check mr-1"></i> Concluído
  </span>`;
}

// ====== LISTA DA HOME ======
async function loadMyProcesses(){
  const termo = (document.getElementById('searchNumeroHome')?.value || '').trim();
  const url = new URL('listar_processos.php', window.location.href);
  url.searchParams.set('scope', 'demandante');
  if (termo) url.searchParams.set('busca', termo);

  wrap.innerHTML = `
    <div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">
      Carregando…
    </div>`;

  try {
    // <<< garante cache de setores carregado p/ podermos usar getSigla
    await getSectors();

    const r = await fetch(url.toString(), { credentials:'same-origin' });
    const raw = await r.text();
    let j = null;
    try { j = JSON.parse(raw); } catch { throw new Error('Resposta inválida do servidor.'); }
    if (!r.ok || !j?.ok) throw new Error(j?.error || `HTTP ${r.status}`);

    const data = j.data || [];
    if (!data.length){
      wrap.innerHTML = `
        <div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">
          Nenhum processo${termo ? ` encontrado para "${esc(termo)}"` : ''}.
        </div>`;
      return;
    }

    wrap.innerHTML = '';
    data.forEach(p => {
      // sigla a partir do cache; se não vier, cai no “prefixo antes do hífen”
      const currentSetor = p.setor_atual || p.setor_demandante || '';
      const sig =
        (p.sigla_atual && String(p.sigla_atual).toUpperCase()) ||
        getSigla(currentSetor) ||
        (currentSetor.split(' - ', 1)[0] || '').toUpperCase() ||
        '—';

      const card = document.createElement('div');
      card.className = 'card-processo bg-white border rounded-lg p-4 hover:shadow-md transition cursor-pointer';
      card.setAttribute('data-id', String(p.id));

      card.innerHTML = `
        <div class="flex justify-between items-start">
          <div class="pr-2 min-w-0 flex-1">  <!-- <— dá espaço pra esquerda -->
            <div class="text-[11px] uppercase tracking-wide text-gray-500 mb-0.5">Nº do processo</div>
            <div class="text-[13px] font-medium text-gray-800 leading-tight truncate"> <!-- <— sem quebrar -->
              ${esc(p.numero_processo || '—')}
              ${Number(p.finalizado) === 1 ? renderBadgeConcluido() : ''}
            </div>
            <div class="mt-1 text-[15px] font-semibold text-gray-900 leading-snug break-words line-clamp-2"> <!-- <— 2 linhas -->
              ${esc(p.nome_processo || '')}
            </div>
          </div>
          <span
            class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 whitespace-nowrap self-start"
            title="${esc(currentSetor)}"
          >${esc(sig)}</span>
        </div>
        <div class="mt-2 text-right text-[11px] text-gray-400">${brDate(p.data_registro)}</div>
      `;
      card.addEventListener('click', () => openDetails(p));
      wrap.appendChild(card);
    });


  } catch(e){
    console.error(e);
    wrap.innerHTML = `
      <div class="col-span-full text-red-500 border border-red-200 rounded-lg p-8 text-center">
        Erro ao carregar${e?.message ? `: ${esc(e.message)}` : ''}.
      </div>`;
  }
}


// ====== DETALHES + FLUXO ======
let currentProcess = null;

function flowItem({ordem, setor, status, acao_finalizadora, acoes = [], entrada, saida, tempo, isFirstCreator = false}) {
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

  // Sempre com data + hora (HH:MM)
  const entrou = brDate(entrada);
  // Regra cirúrgica: primeiro setor (criador) => Saída = Entrada e sem tempo
  const saidaEfetiva = isFirstCreator ? entrada : saida;
  const saiu = (isDone || isFirstCreator) ? brDate(saidaEfetiva) : null;

  const icone  = `<i class="fa-solid fa-arrow-right-long mx-1"></i><i class="fa-solid fa-door-open"></i>`;

  let datasHtml = '';
  if (isFirstCreator) {
    // Criador: Entrada e Saída iguais, sem tempo
    datasHtml = `
      <div class="mt-1 text-xs text-gray-600">
        <span class="text-gray-500">Entrada:</span> ${entrou}
        <span class="mx-2 text-gray-400">${icone}</span>
        <span class="text-gray-500">Saída:</span> ${saiu}
      </div>`;
  } else if (isDone) {
    // Concluído: Entrada, Saída e Tempo
    datasHtml = `
      <div class="mt-1 text-xs text-gray-600">
        <span class="text-gray-500">Entrada:</span> ${entrou}
        <span class="mx-2 text-gray-400">${icone}</span>
        <span class="text-gray-500">Saída:</span> ${saiu}
        <span class="mx-2 text-gray-300">•</span>
        <span class="text-gray-500">Tempo:</span> ${esc(tempo || '—')}
      </div>`;
  } else {
    // Ativo/Atual: Entrada + Tempo no setor
    datasHtml = `
      <div class="mt-1 text-xs text-gray-600">
        <span class="text-gray-500">Entrada:</span> ${entrou}
        <span class="mx-2 text-gray-300">•</span>
        <span class="text-gray-500">Tempo no setor:</span> ${esc(tempo || '—')}
      </div>`;
  }

  const acoesHtml = (acoes.slice(-3)).map(a => {
    const when = a.data_registro ? ` <span class="text-gray-500">• ${brDate(a.data_registro)}</span>` : '';
    return `<div class="text-xs text-gray-700 leading-tight">• ${esc(a.texto)}${when}</div>`;
  }).join('');

  return `
    <div class="flex items-start gap-3 p-4 rounded-lg border ${boxCls}">
      ${badge}
      <div class="flex-1">
        <div class="font-semibold">${esc(setor || '—')}</div>
        ${sub ? `<div class="text-xs text-gray-500">${sub}</div>` : ''}
        ${datasHtml}
        ${isDone && acao_finalizadora ? `<div class="text-xs text-gray-600">Ação: ${esc(acao_finalizadora)}</div>` : ''}
        ${acoesHtml ? `<div class="mt-2 space-y-1">${acoesHtml}</div>` : ''}
      </div>
    </div>`;
}

async function renderFlow(processoId){
  const wrap = document.getElementById('flowList');
  wrap.innerHTML = '<div class="text-gray-400">Carregando fluxo…</div>';

  try{
    const [rf, ra] = await Promise.all([
      fetch(`listar_fluxo.php?id=${encodeURIComponent(processoId)}`, { credentials:'same-origin' }),
      fetch(`listar_acoes_internas.php?id=${encodeURIComponent(processoId)}`, { credentials:'same-origin' })
    ]);

    const jf = await rf.json();
    const ja = await ra.json();
    if (!rf.ok || !jf.ok) throw new Error(jf.error || 'Falha ao listar fluxo');
    if (!ra.ok || !ja.ok) throw new Error(ja.error || 'Falha ao listar ações internas');

    const fluxo = jf.data || [];
    const todasAcoes = ja.data || [];

    const norm = s => String(s||'')
      .normalize('NFD').replace(/\p{Diacritic}/gu,'')
      .replace(/\s+/g,' ').trim().toLowerCase();

    const mapAcoes = todasAcoes.reduce((acc, a) => {
      const k = norm(a.setor);
      (acc[k] ||= []).push(a);
      return acc;
    }, {});

    // setor demandante do processo atual (quem criou)
    const setorCriador = currentProcess ? norm(currentProcess.setor_demandante) : '';

    wrap.innerHTML = fluxo.map((f, idx) => {
      const key = norm(f.setor);
      const isFirstCreator = (idx === 0) && (norm(f.setor) === setorCriador);
      return flowItem({
        ordem: f.ordem,
        setor: f.setor,
        status: f.status,
        acao_finalizadora: f.acao_finalizadora,
        acoes: mapAcoes[key] || [],
        entrada: f.data_registro,
        saida:  f.data_fim,
        tempo:  f.tempo_legivel,
        isFirstCreator
      });
    }).join('');

  } catch (e) {
    console.error(e);
    wrap.innerHTML = '<div class="text-red-500">Erro ao carregar fluxo.</div>';
  }
}

function openDetails(p){
  currentProcess = p;

  document.getElementById('d_num').textContent   = esc(p.numero_processo || '—');
  document.getElementById('d_setor').textContent = esc(p.setor_demandante || '—');
  document.getElementById('d_dest').textContent  = esc(p.enviar_para || '—');
  document.getElementById('d_nome').textContent  = esc(p.nome_processo || '—'); // <-- AQUI

  const tipos = parseTipos(p.tipos_processo_json);
  document.getElementById('d_tipos').textContent = esc(tipos || '—');

  const hasOutros = (p.tipo_outros || '').trim() !== '';
  document.getElementById('d_outros_row').classList.toggle('hidden', !hasOutros);
  document.getElementById('d_outros').textContent = esc(p.tipo_outros || '');

  document.getElementById('d_desc').textContent = esc(p.descricao || '');
  document.getElementById('d_dt').textContent   = brDate(p.data_registro);

  renderFlow(p.id);

  const md = document.getElementById('detailsModal');
  md.classList.remove('hidden');
  md.classList.add('flex');
}

function closeDetails(){
  const md = document.getElementById('detailsModal');
  md.classList.add('hidden');
  md.classList.remove('flex');
}
document.getElementById('closeDetails')?.addEventListener('click', closeDetails);
document.getElementById('okDetails')?.addEventListener('click', closeDetails);
document.getElementById('detailsModal')?.addEventListener('click', (e)=>{ if (e.target.id==='detailsModal') closeDetails(); });

// ====== start ======
loadMyProcesses();
