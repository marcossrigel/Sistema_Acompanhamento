const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m =>
  ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#39;'}[m])
);

// ====== SETORES DESTINO ======
const SECTORS_DEST = [
  'DAF - Diretoria de Administração e Finanças',
  'DOHDU - Diretoria de Obras',
  'CELOE I - Comissão de Licitação I',
  'CELOE II - Comissão de Licitação II',
  'CELOSE - Comissão de Licitação',
  'GCOMP - Gerência de Compras',
  'GOP - Gerência de Orçamento e Planejamento',
  'GFIN - Gerência Financeira',
  'GCONT - Gerência de Contabilidade',
  'DP - Diretoria da Presidência',
  'GAD - Gerência Administrativa',
  'GAC - Gerência de Acompanhamento de Contratos',
  'CGAB - Chefia de Gabinete',
  'DOE - Diretoria de Obras Estratégicas',
  'DSU - Diretoria de Obras de Saúde',
  'DSG - Diretoria de Obras de Segurança',
  'DED - Diretoria de Obras de Educação',
  'SPO - Superintendência de Projetos de Obras',
  'SUAJ - Superintendência de Apoio Jurídico',
  'SUFIN - Superintendência Financeira',
  'GAJ - Gerência de Apoio Jurídico',
  'SUPLAN - Superintendência de Planejamento',
  'DPH - Diretoria de Projetos Habitacionais'
];

// ====== ELEMENTOS DO MODAL "NOVO PROCESSO" ======
const openBtn        = document.getElementById('newProcessBtn');
const modal          = document.getElementById('processModal');
const closeBtn       = document.getElementById('closeModalBtn');
const closeBtnGhost  = document.getElementById('closeModalBtn_ghost');
const form           = document.getElementById('processForm');

const destSelect      = document.getElementById('destSector');
const tipoOutrosCheck = document.getElementById('tipoOutrosCheck');
const tipoOutrosInput = document.getElementById('tipoOutrosInput');

function populateDest() {
  if (!destSelect) return;
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
  document.getElementById('processNumber')?.focus();
}
function closeModal() {
  form?.reset();
  tipoOutrosInput?.classList.add('hidden');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

openBtn?.addEventListener('click', openModal);
closeBtn?.addEventListener('click', closeModal);
closeBtnGhost?.addEventListener('click', closeModal);
modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

tipoOutrosCheck?.addEventListener('change', () => {
  if (tipoOutrosCheck.checked) {
    tipoOutrosInput.classList.remove('hidden');
    tipoOutrosInput.focus();
  } else {
    tipoOutrosInput.classList.add('hidden');
    tipoOutrosInput.value = '';
  }
});

form?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const numero     = document.getElementById('processNumber').value.trim();
  const descricao  = document.getElementById('description').value.trim();
  const enviarPara = destSelect.value;

  const tipos = Array.from(document.querySelectorAll('input[name="tipo_proc"]:checked'))
                .map(i => i.value);
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
        descricao
      })
    });
    const json = await resp.json();
    if (!resp.ok || !json.ok) throw new Error(json.error || 'Erro ao salvar.');

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
const brDay = iso => {
  if (!iso) return '—';
  const d = new Date(String(iso).replace(' ', 'T'));
  return isNaN(d) ? '—' : d.toLocaleDateString('pt-BR');
};
const parseTipos = j => {
  try { const a = JSON.parse(j||'[]'); return Array.isArray(a) ? a.join(', ') : ''; }
  catch { return ''; }
};

// ====== LISTA DA HOME ======
async function loadMyProcesses(){
  const wrap = document.getElementById('processList');
  wrap.innerHTML = `
    <div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">
      Carregando…
    </div>`;
  try {
    const r = await fetch('listar_processos.php', { credentials:'same-origin' });
    const j = await r.json();
    if (!r.ok || !j.ok) throw new Error(j.error || 'erro');
    const data = j.data || [];

    if (!data.length){
      wrap.innerHTML = `
        <div class="col-span-full text-gray-400 border border-dashed rounded-lg p-8 text-center">
          Nenhum processo encontrado.
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
            <div class="font-semibold text-gray-800">${esc(p.numero_processo || '—')}</div>
          </div>
          <span class="text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">
            ${esc(p.enviar_para || '—')}
          </span>
        </div>
        <div class="mt-3 text-sm text-gray-600 line-clamp-2">${esc(p.descricao || '')}</div>
        <div class="mt-3 text-right text-xs text-gray-400">${brDate(p.data_registro)}</div>
      `;
      card.addEventListener('click', () => openDetails(p));
      wrap.appendChild(card);
    });
  } catch(e){
    console.error(e);
    wrap.innerHTML = `
      <div class="col-span-full text-red-500 border border-red-200 rounded-lg p-8 text-center">
        Erro ao carregar.
      </div>`;
  }
}

// ====== DETALHES + FLUXO ======
let currentProcess = null;

function flowItem({ordem, setor, status, acao_finalizadora, acoes = [], entrada, saida, tempo}) {
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

  const entrou = brDay(entrada);
  const saiu   = isDone ? brDay(saida) : null;
  const icone  = `<i class="fa-solid fa-arrow-right-long mx-1"></i><i class="fa-solid fa-door-open"></i>`;

  const datasHtml = isDone
    ? `<div class="mt-1 text-xs text-gray-600">
         <span class="text-gray-500">Entrada:</span> ${entrou}
         <span class="mx-2 text-gray-400">${icone}</span>
         <span class="text-gray-500">Saída:</span> ${saiu}
         <span class="mx-2 text-gray-300">•</span>
         <span class="text-gray-500">Tempo:</span> ${esc(tempo || '—')}
       </div>`
    : `<div class="mt-1 text-xs text-gray-600">
         <span class="text-gray-500">Entrada:</span> ${entrou}
         <span class="mx-2 text-gray-300">•</span>
         <span class="text-gray-500">Tempo no setor:</span> ${esc(tempo || '—')}
       </div>`;

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

    wrap.innerHTML = fluxo.map(f => {
      const key = norm(f.setor);
      return flowItem({
        ordem: f.ordem,
        setor: f.setor,
        status: f.status,
        acao_finalizadora: f.acao_finalizadora,
        acoes: mapAcoes[key] || [],
        entrada: f.data_registro,
        saida:  f.data_fim,
        tempo:  f.tempo_legivel
      });
    }).join('');

  }catch(e){
    console.error(e);
    wrap.innerHTML = '<div class="text-red-500">Erro ao carregar fluxo.</div>';
  }
}

function openDetails(p){
  currentProcess = p;

  document.getElementById('d_num').textContent   = esc(p.numero_processo || '—');
  document.getElementById('d_setor').textContent = esc(p.setor_demandante || '—');
  document.getElementById('d_dest').textContent  = esc(p.enviar_para || '—');

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
