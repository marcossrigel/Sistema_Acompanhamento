document.addEventListener('DOMContentLoaded', function () {
  const perfil = document.querySelector('form').dataset.perfil;
  const campoLiberacao = document.getElementById('data_liberacao');
  const campoTempoMedio = document.getElementById('grupo-tempo-medio');
  const campoTempoReal  = document.getElementById('grupo-tempo-real');

  if (campoTempoMedio) campoTempoMedio.style.display = 'none';
  if (campoTempoReal)  campoTempoReal.style.display  = 'none';

  if (perfil.toLowerCase() === 'solicitante') {
    const hoje = new Date();
    const yyyy = hoje.getFullYear();
    const mm = String(hoje.getMonth() + 1).padStart(2, '0');
    const dd = String(hoje.getDate()).padStart(2, '0');
    const isoHoje = `${yyyy}-${mm}-${dd}`;

    campoLiberacao.value = isoHoje;

    const campoSolic = document.getElementById('data_solicitacao');
    if (campoSolic) campoSolic.value = isoHoje;
    campoLiberacao.readOnly = true;
  }
});

(function () {
  const form = document.querySelector('form.formulario');
  const perfil = (form.dataset.perfil || '').toLowerCase();
  const isSolicitante = perfil === 'solicitante';

  const dataSolic       = document.getElementById('data_solicitacao');
  const dataLib         = document.getElementById('data_liberacao');
  const tempoMed        = document.getElementById('tempo_medio');
  const tempoRealDias   = document.getElementById('tempo_real_dias');
  const tempoMedioPadrao= form.dataset.tempoMedio || '00:30';

  const grupoTempoMedio = document.getElementById('grupo-tempo-medio');
  const grupoTempoReal  = document.getElementById('grupo-tempo-real');

  function hojeLocalISO() {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  if (isSolicitante) {
    const hojeISO = hojeLocalISO();
    if (dataLib)   dataLib.value   = hojeISO;
    if (dataSolic) dataSolic.value = hojeISO;
    if (tempoMed)  tempoMed.value  = tempoMedioPadrao;
    if (tempoRealDias) tempoRealDias.value = '0';

    if (dataLib) dataLib.readOnly = true;

    grupoTempoMedio?.classList.add('hidden');
    grupoTempoReal?.classList.add('hidden');
  } else {
    grupoTempoMedio?.classList.remove('hidden');
    grupoTempoReal?.classList.remove('hidden');
  }
})();

function fecharModal() {
  const sucesso = document.getElementById('modal-sucesso');
  const erro = document.getElementById('modal-erro');
  if (sucesso) sucesso.style.display = 'none';
  if (erro) erro.style.display = 'none';

  window.history.replaceState({}, document.title, window.location.pathname);
}
