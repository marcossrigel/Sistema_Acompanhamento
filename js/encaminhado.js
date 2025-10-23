let __sectorsCache = null;
async function getSectors() {
  if (__sectorsCache) return __sectorsCache;

  const r = await fetch('../templates/listar_setores.php', { credentials:'same-origin' });
  const j = await r.json();
  if (!r.ok || !j.ok) throw new Error(j.error || 'Falha ao listar setores');

  const data  = j.data || [];
  const arr   = data.map(x => x.nome);
  const byName = Object.fromEntries(data.map(x => [x.nome, (x.sigla || '').toUpperCase()]));
  const norm = s => String(s||'')
    .normalize('NFD').replace(/\p{Diacritic}/gu,'')
    .replace(/\s+/g,' ').trim().toLowerCase();
  const byNorm = Object.fromEntries(data.map(x => [norm(x.nome), (x.sigla || '').toUpperCase()]));

  __sectorsCache = { arr, final: j.finalizador, byName, byNorm };
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

// helper: pega a SIGLA antes do " - "
const sigla = (s) => String(s || '').split('-')[0].trim().toUpperCase();
const norm  = (s) => String(s||'')
  .normalize('NFD').replace(/\p{Diacritic}/gu,'')
  .replace(/\s+/g,' ').trim().toLowerCase();


async function populateNextSectors() {
  const sel = document.getElementById('nextSector');
  if (!sel) return;

  sel.innerHTML = '<option value="" selected disabled>Selecione o próximo setor.</option>';

  try {
    const { arr, final } = await getSectors();
    // opcional: sincroniza setor finalizador vindo do servidor
    window.FINAL_SECTOR = final || window.FINAL_SECTOR;

    arr.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s;
      opt.textContent = s;
      sel.appendChild(opt);
    });
  } catch (e) {
    console.error(e);
  }
}

const acoesModal   = document.getElementById('acoesModal');
const btnAcoes     = document.getElementById('btnAcoes');
const fecharAcoes  = document.getElementById('fecharAcoes');
const cancelarAcoes= document.getElementById('cancelarAcoes');
const salvarAcao   = document.getElementById('salvarAcao');
const acoesList    = document.getElementById('acoesList');
const acaoTexto    = document.getElementById('acaoTexto');

function renderBadgeConcluido(){
  return `<span class="badge-done ml-2 inline-flex items-center px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
    <i class="fa-solid fa-check mr-1"></i> Concluído
  </span>`;
}

function renderAcoesItem(a){
  const li = document.createElement('li');
  li.className = "p-3 border rounded-lg bg-gray-50";
  li.innerHTML = `
    <div class="text-sm text-gray-800 whitespace-pre-wrap break-words">${esc(a.texto)}</div>
    <div class="mt-1 text-xs text-gray-500">
      <span class="font-medium">${esc(a.setor)}</span>
      ${a.usuario ? ` • ${esc(a.usuario)}` : ''} • ${brDate(a.data_registro)}
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

function openAcoes(){ acaoTexto.value = ''; acoesModal.classList.remove('hidden'); acoesModal.classList.add('flex'); loadAcoes(); }
function closeAcoes(){ acoesModal.classList.add('hidden'); acoesModal.classList.remove('flex'); }

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
    await loadAcoes();
    await renderFlow(currentProcess.id);
  }catch(e){
    alert('Erro: ' + (e.message||e));
    console.error(e);
  }
});

const MY_SETOR     = window.MY_SETOR || '';
const FINAL_SECTOR = window.FINAL_SECTOR || 'GFIN - Gerência Financeira';
let IS_FINALIZE_MODE = false;

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
const brDay = iso => {
  if (!iso) return '—';
  const d = new Date(String(iso).replace(' ', 'T'));
  return isNaN(d) ? '—' : d.toLocaleDateString('pt-BR');
};
const parseTipos = j => { try { const a = JSON.parse(j||'[]'); return Array.isArray(a) ? a.join(', ') : ''; } catch { return ''; } };

async function loadIncoming(){
  const wrap = document.getElementById('encList');
  const termo = (document.getElementById('searchNumero')?.value || '').trim();

  wrap.innerHTML = `
    <div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">
      Carregando…
    </div>`;

  try {
    const url = new URL('listar_encaminhados.php', window.location.href);
    if (termo) url.searchParams.set('busca', termo);

    const r = await fetch(url.toString(), { credentials: 'same-origin' });
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.error || 'Falha ao listar');

    const data = (j.data || []);
    if (!data.length) {
      wrap.innerHTML = `
        <div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">
          Nenhum processo encontrado${termo ? ' para "'+esc(termo)+'"' : ''}.
        </div>`;
      return;
    }

    wrap.innerHTML = '';
    // (opcional) carrega cache de setores p/ sigla
    try { await getSectors(); } catch {}

    data.forEach(p => {
      const sig = getSigla(p.setor_demandante) || sigla(p.setor_demandante) || '—';

      const card = document.createElement('div'); // <<< FALTAVA ISTO

      card.className = 'card-processo bg-white border rounded-lg p-4 hover:shadow-md transition cursor-pointer';
      card.setAttribute('data-id', String(p.id));

      card.innerHTML = `
        <div class="flex justify-between items-start">
          <div class="pr-2 min-w-0">
            <div class="text-[11px] uppercase tracking-wide text-gray-500 mb-0.5">Nº do processo</div>
            <div class="text-[13px] font-medium text-gray-800 break-all leading-tight">
              ${esc(p.numero_processo || '—')}
              ${Number(p.finalizado) === 1 ? renderBadgeConcluido() : ''}
            </div>
            <div class="mt-1 text-[15px] font-semibold text-gray-900 leading-snug break-words whitespace-normal max-h-[3.5rem] overflow-hidden">
              ${esc(p.nome_processo || '')}
            </div>
          </div>
          <span
            class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 whitespace-nowrap self-start"
            title="${esc(p.setor_demandante || '')}"
          >
            ${esc(sig)}
          </span>
        </div>
        <div class="mt-2 text-right text-[11px] text-gray-400">${brDate(p.data_registro)}</div>
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


let currentProcess = null;

function openDetails(p){
  const encBlock       = document.getElementById('encBlock');
  const finalizarBlock = document.getElementById('finalizarBlock');
  const btnAcoesEl     = document.getElementById('btnAcoes');

  const canAct             = sigla(p.enviar_para) === sigla(MY_SETOR);
  const processoFinalizado = Number(p.finalizado) === 1;
  const processoNoGFIN     = sigla(p.enviar_para) === sigla(FINAL_SECTOR);
  const usuarioEhGFIN      = sigla(MY_SETOR)      === sigla(FINAL_SECTOR);
  const podeFinalizar      = !processoFinalizado && processoNoGFIN && usuarioEhGFIN;

  finalizarBlock?.classList.toggle('hidden', !podeFinalizar);
  encBlock?.classList.toggle('hidden',  podeFinalizar ? true : !canAct);
  btnAcoesEl?.classList.toggle('hidden', !canAct);

  const btnFinalizar = document.getElementById('btnFinalizarProcesso');
  if (btnFinalizar) {
    btnFinalizar.onclick = () => {
      IS_FINALIZE_MODE = true;
      document.getElementById('acaoFinalizadora').value = '';
      const fm = document.getElementById('finalizarModal');
      fm.classList.remove('hidden'); fm.classList.add('flex');
    };
  }

  currentProcess = p;
  document.getElementById('d_num').textContent   = p.numero_processo || '—';
  document.getElementById('d_nome').textContent  = p.nome_processo || '—';
  document.getElementById('d_setor').textContent = p.setor_demandante || '—';
  document.getElementById('d_dest').textContent  = p.enviar_para || '—';
  const tipos = parseTipos(p.tipos_processo_json);
  document.getElementById('d_tipos').textContent = tipos || '—';
  const hasOutros = (p.tipo_outros || '').trim() !== '';
  document.getElementById('d_outros_row').classList.toggle('hidden', !hasOutros);
  document.getElementById('d_outros').textContent = p.tipo_outros || '';
  document.getElementById('d_desc').textContent = p.descricao || '';
  document.getElementById('d_dt').textContent   = brDate(p.data_registro);


  renderFlow(p.id);

  const md = document.getElementById('detailsModal');
  md.classList.remove('hidden');
  md.classList.add('flex');

  // << aqui popula o select
  populateNextSectors();

  const nextSel = document.getElementById('nextSector');
  if (nextSel) nextSel.value = '';
}


function closeDetails(){
  const md = document.getElementById('detailsModal');
  md.classList.add('hidden');
  md.classList.remove('flex');
}
document.getElementById('closeDetails')?.addEventListener('click', closeDetails);
document.getElementById('okDetails')?.addEventListener('click', closeDetails);
const detailsModal = document.getElementById('detailsModal');
detailsModal?.addEventListener('click', (e) => { if (e.target === detailsModal) closeDetails(); });

function flowItem({
  ordem, setor, status, acao_finalizadora,
  acoes = [], entrada, saida, tempo,
  isFirstCreator = false
}) {
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

  // Sempre com data + hora
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

  }catch(e){
    console.error(e);
    wrap.innerHTML = '<div class="text-red-500">Erro ao carregar fluxo.</div>';
  }
}


const frmBusca   = document.getElementById('frmBusca');
const btnLimpar  = document.getElementById('btnLimpar');

frmBusca?.addEventListener('submit', (e) => {
  e.preventDefault();
  const termo = (document.getElementById('searchNumero')?.value || '').trim();
  const url = new URL(window.location.href);
  if (termo) url.searchParams.set('busca', termo);
  else url.searchParams.delete('busca');
  history.replaceState({}, '', url.toString());

  loadIncoming();
});

btnLimpar?.addEventListener('click', () => {
  document.getElementById('searchNumero').value = '';
  const url = new URL(window.location.href);
  url.searchParams.delete('busca');
  history.replaceState({}, '', url.toString());
  loadIncoming();
});

(function initBuscaFromURL(){
  const url = new URL(window.location.href);
  const termo = url.searchParams.get('busca') || '';
  const input = document.getElementById('searchNumero');
  if (input) input.value = termo;
})();


const btnEncaminhar     = document.getElementById('btnEncaminhar');
const finalizarModal    = document.getElementById('finalizarModal');
const cancelarFinalizar = document.getElementById('cancelarFinalizar');
const confirmarFinalizar= document.getElementById('confirmarFinalizar');

btnEncaminhar?.addEventListener('click', () => {
  // este botão só aparece quando NÃO é modo finalizar
  IS_FINALIZE_MODE = false;
  document.getElementById('acaoFinalizadora').value = '';
  finalizarModal.classList.remove('hidden');
  finalizarModal.classList.add('flex');
});

cancelarFinalizar?.addEventListener('click', () => {
  finalizarModal.classList.add('hidden');
  finalizarModal.classList.remove('flex');
});

confirmarFinalizar?.addEventListener('click', async () => {
  if (!currentProcess) { alert('Nenhum processo selecionado.'); return; }
  const acao = document.getElementById('acaoFinalizadora').value.trim();
  const proxSetor = document.getElementById('nextSector')?.value || '';

  const btn = document.getElementById('confirmarFinalizar');
  const cancelar = document.getElementById('cancelarFinalizar');

  try {
    btn.disabled = true; cancelar.disabled = true; btn.textContent = 'Enviando...';

    if (IS_FINALIZE_MODE) {
      if (!acao) { alert('Descreva a ação finalizadora.'); return; }

      const resp = await fetch('finalizar_processo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ id_processo: currentProcess.id, acao })
      });
      const raw = await resp.text(); let j; try { j = JSON.parse(raw); } catch { throw new Error('Resposta não-JSON: '+raw); }
      if (!resp.ok || !j.ok) throw new Error(j.error || 'Falha ao finalizar');

      // marca o card como concluído imediatamente
      currentProcess.finalizado = 1;
      const card = document.querySelector(`.card-processo[data-id="${currentProcess.id}"]`);
      if (card) {
        const titleDiv = card.querySelector('.font-semibold');
        if (titleDiv && !titleDiv.querySelector('.badge-done')) {
          titleDiv.insertAdjacentHTML('beforeend', renderBadgeConcluido());
        }
      }

      alert('Processo finalizado com sucesso!');
    } else {
      if (!acao || !proxSetor) { alert("Preencha a ação e selecione o próximo setor."); return; }

      const resp = await fetch('encaminhar_processo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          id_processo: currentProcess.id,
          setor_origem: MY_SETOR,
          setor_destino: proxSetor,
          acao_finalizadora: acao
        })
      });
      const raw = await resp.text(); let j; try { j = JSON.parse(raw); } catch { throw new Error('Resposta não-JSON: '+raw); }
      if (!resp.ok || !j.ok) throw new Error(j.error || 'Falha ao encaminhar');
    }

    await renderFlow(currentProcess.id);
    await loadIncoming();

    document.getElementById('acaoFinalizadora').value = '';
    finalizarModal.classList.add('hidden');
    finalizarModal.classList.remove('flex');

  } catch (e) {
    alert('Erro: ' + (e.message || e));
    console.error(e);
  } finally {
    btn.disabled = false; cancelar.disabled = false; btn.textContent = 'Confirmar e Avançar';
  }
});

loadIncoming();
