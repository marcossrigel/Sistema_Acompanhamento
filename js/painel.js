document.querySelectorAll('.accordion').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const panel = document.getElementById('panel-' + id);
    const isOpen = btn.classList.contains('active');
    document.querySelectorAll('.accordion').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.style.display = 'none');
    if (!isOpen) {
      btn.classList.add('active');
      panel.style.display = 'block';
      localStorage.setItem('solicitacaoAbertaPainel', id);
    } else {
      localStorage.removeItem('solicitacaoAbertaPainel');
    }
  });
});
const abertaId = localStorage.getItem('solicitacaoAbertaPainel');
if (abertaId) {
  const btn = document.querySelector(`.accordion[data-id='${abertaId}']`);
  const panel = document.getElementById(`panel-${abertaId}`);
  if (btn && panel) {
    btn.classList.add('active');
    panel.style.display = 'block';
  }
}

function andamentoSetor(id){
  window.location.href = 'andamento.php?id=' + id;
}

function encaminhar(id) {
  if (!confirm('Encaminhar para o próximo setor?')) return;
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('access_dinamic');
  window.location.href = 'liberar.php?id=' + id + '&access_dinamic=' + encodeURIComponent(token);
}

(function () {
  function key(id){ return 'gecomp_chk_' + id; }
  function load(id){
    try { return JSON.parse(localStorage.getItem(key(id))) || {}; }
    catch { return {}; }
  }
  function save(id, obj){ localStorage.setItem(key(id), JSON.stringify(obj)); }

  // CHECKBOXES
  document.querySelectorAll('.gecomp-chk').forEach(el => {
    const id      = el.dataset.id;
    const field   = el.dataset.field;    // 'tr' | 'etp' | 'cotacao'
    const initial = (el.dataset.initial === '1');
    const ver     = el.dataset.ver || '';

    let obj = load(id);

    // se o versor mudou, reseta o rascunho para os valores vindos do banco
    if (obj.__ver !== ver) {
      obj = { __ver: ver, tr: 0, etp: 0, cotacao: 0, obs: '' };
    }

    // se o campo ainda não existe (primeiro acesso), semeia com o valor do banco
    if (!(field in obj)) obj[field] = initial;

    el.checked = !!obj[field];

    el.addEventListener('change', () => {
      const o = load(id);
      if (o.__ver !== ver) { o.__ver = ver; o.tr = 0; o.etp = 0; o.cotacao = 0; } // defesa extra
      o[field] = el.checked;
      save(id, o);
    });
  });

  // TEXTAREA
  document.querySelectorAll('.gecomp-obs').forEach(el => {
    const id  = el.dataset.id;
    const ver = el.dataset.ver || '';

    let obj = load(id);

    if (obj.__ver !== ver) {
      obj = { __ver: ver, tr: obj.tr ?? 0, etp: obj.etp ?? 0, cotacao: obj.cotacao ?? 0, obs: el.value || '' };
      save(id, obj);
    }

    if (typeof obj.obs !== 'string') { obj.obs = el.value || ''; save(id, obj); }
    el.value = obj.obs;

    let t;
    el.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        const o = load(id);
        if (o.__ver !== ver) o.__ver = ver;
        o.obs = el.value;
        save(id, o);
      }, 300);
    });
  });
})();

document.querySelectorAll('form[action="encaminhar.php"]').forEach(form => {
  form.addEventListener('submit', () => {
    const id = form.querySelector('input[name="id_demanda"]').value;

    const pick = (f) => document.querySelector(`.gecomp-chk[data-id="${id}"][data-field="${f}"]`);
    const tr      = pick('tr')?.checked ? 1 : 0;
    const etp     = pick('etp')?.checked ? 1 : 0;
    const cotacao = pick('cotacao')?.checked ? 1 : 0;
    const obs     = document.querySelector(`#obs-${id}`)?.value || '';

    (document.getElementById(`hid-tr-${id}`)  || {}).value = tr;
    (document.getElementById(`hid-etp-${id}`) || {}).value = etp;
    (document.getElementById(`hid-cot-${id}`) || {}).value = cotacao;
    (document.getElementById(`hid-obs-${id}`) || {}).value = obs;
  });
});


const tokenQS = new URLSearchParams(location.search).get('access_dinamic');

function autosaveGecomp(id) {
  const get = (attr) =>
    document.querySelector(`.gecomp-chk[data-id="${id}"][data-field="${attr}"]`);

  const body = {
    id,
    tr:      get('tr')?.checked ? 1 : 0,
    etp:     get('etp')?.checked ? 1 : 0,
    cotacao: get('cotacao')?.checked ? 1 : 0,
    obs:     document.querySelector(`#obs-${id}`)?.value || ''
  };

  fetch('salvar_gecomp.php?access_dinamic=' + encodeURIComponent(tokenQS), {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(body)
  }).catch(() => {});
}

document.querySelectorAll('.gecomp-chk').forEach(chk => {
  chk.addEventListener('change', () => autosaveGecomp(chk.dataset.id));
});

document.querySelectorAll('.gecomp-obs').forEach(txt => {
  let t;
  txt.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => autosaveGecomp(txt.dataset.id), 500);
  });
  txt.addEventListener('blur', () => autosaveGecomp(txt.dataset.id));
});

document.addEventListener('submit', function (e) {
  const form = e.target;
  if (!form.id || !form.id.startsWith('f-')) return; // só nos forms de encaminhar

  const id = form.id.split('-')[1];

  const tr  = document.querySelector(`input.gecomp-chk[data-id="${id}"][data-field="tr"]`);
  const etp = document.querySelector(`input.gecomp-chk[data-id="${id}"][data-field="etp"]`);
  const cot = document.querySelector(`input.gecomp-chk[data-id="${id}"][data-field="cotacao"]`);
  const obs = document.querySelector(`#obs-${id}`);

  const hidTr  = document.querySelector(`#hid-tr-${id}`);
  const hidEtp = document.querySelector(`#hid-etp-${id}`);
  const hidCot = document.querySelector(`#hid-cot-${id}`);
  const hidObs = document.querySelector(`#hid-obs-${id}`);

  if (hidTr)  hidTr.value  = tr  ? (tr.checked ? 1 : 0) : '';
  if (hidEtp) hidEtp.value = etp ? (etp.checked ? 1 : 0) : '';
  if (hidCot) hidCot.value = cot ? (cot.checked ? 1 : 0) : '';
  if (hidObs) hidObs.value = obs ? obs.value.trim() : '';
});