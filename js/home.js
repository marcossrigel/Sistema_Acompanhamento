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
  const MY_SETOR  = (window.APP && window.APP.MY_SETOR) || '';
  const USER_NAME = (window.APP && window.APP.USER_NAME) || '';
  const GID       = (window.APP && window.APP.GID) || '';

// desenha um “passo” do fluxo
function makeStep(idx, title, subtitle, status='pending') {
  let bullet = '';
  let classes = '';

  if(status==='done'){
    bullet = `<span class="flex h-7 w-7 items-center justify-center rounded-full bg-green-600 text-white font-bold mr-3"><i class="fa-solid fa-check"></i></span>`;
    classes = 'border-green-200 bg-green-50/60 text-green-800';
  } else if(status==='active'){
    bullet = `<span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white font-bold mr-3">${idx}</span>`;
    classes = 'border-blue-200 bg-blue-50/60 text-blue-800';
  } else {
    bullet = `<span class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-500 font-bold mr-3">${idx}</span>`;
    classes = 'border-gray-200 bg-white text-gray-800';
  }

  const wrap = document.createElement('li');
  wrap.className = `flex items-start p-3 rounded-lg border ${classes}`;
  wrap.innerHTML = `
    ${bullet}
    <div class="min-w-0">
      <div class="font-semibold">${title}</div>
      ${subtitle ? `<div class="text-xs">${subtitle}</div>` : ''}
    </div>
  `;
  return wrap;
}

// monta o fluxo simples: 1) Demandante → 2) Destino
function buildFlow(p) {
  const flow = document.getElementById('flowList');
  flow.innerHTML = '';

  // passo 1 concluído
  flow.appendChild(makeStep(1, p.setor_demandante || '—', 'Setor demandante', 'done'));

  // passo 2 ativo
  flow.appendChild(makeStep(2, p.enviar_para || '—', 'Destino atual', 'active'));
}