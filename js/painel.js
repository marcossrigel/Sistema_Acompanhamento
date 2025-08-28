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

(function(){
  function key(id){ return 'gecomp_chk_' + id; }
  function load(id){
    try { return JSON.parse(localStorage.getItem(key(id))) || {}; }
    catch(e){ return {}; }
  }
  function save(id, obj){
    localStorage.setItem(key(id), JSON.stringify(obj));
  }

  // inicia checkboxes a partir do localStorage
  document.querySelectorAll('.gecomp-chk').forEach(el => {
    const id = el.dataset.id;
    const field = el.dataset.field;
    const data = load(id);
    el.checked = !!data[field];

    el.addEventListener('change', () => {
      const obj = load(id);
      obj[field] = el.checked;
      save(id, obj);
    });
  });
})();

(function(){
  function key(id){ return 'gecomp_chk_' + id; }   // já existe
  function load(id){
    try { return JSON.parse(localStorage.getItem(key(id))) || {}; }
    catch(e){ return {}; }
  }
  function save(id, obj){
    localStorage.setItem(key(id), JSON.stringify(obj));
  }

  // ✅ iniciar checkboxes a partir do localStorage (já existia)
  document.querySelectorAll('.gecomp-chk').forEach(el => {
    const id = el.dataset.id;
    const field = el.dataset.field;
    const data = load(id);
    el.checked = !!data[field];

    el.addEventListener('change', () => {
      const obj = load(id);
      obj[field] = el.checked;
      save(id, obj);
    });
  });

  // ✅ iniciar textarea de observações a partir do localStorage
  document.querySelectorAll('.gecomp-obs').forEach(el => {
    const id = el.dataset.id;
    const data = load(id);
    if (typeof data.obs === 'string') el.value = data.obs;

    // salva a cada digitação
    el.addEventListener('input', () => {
      const obj = load(id);
      obj.obs = el.value;
      save(id, obj);
    });
  });
})();