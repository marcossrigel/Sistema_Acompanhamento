<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

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
  <title>CEHAB - Gerar Relatório</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <style>
    body{font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;}
    .container{max-width:1100px;margin:0 auto;padding:24px;}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;}
    .label{font-weight:600;font-size:0.875rem;color:#374151;margin-bottom:6px;display:block;}
    .input,.select{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px}
    .btn{display:inline-flex;align-items:center;gap:8px;border-radius:10px;padding:10px 14px;font-weight:600}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .btn--blue{background:#2563eb;color:#fff}
    .btn--muted{background:#f3f4f6}
    .btn--green{background:#059669;color:#fff}
    .notice{padding:10px 12px;border-radius:10px;background:#f3f4f6}
    .title{font-size:1.25rem;font-weight:700;margin:0 0 12px}
  </style>
</head>
<body class="bg-gray-50">

<header class="bg-white border-b border-gray-200">
  <div class="container flex items-center justify-between">
    <a href="../templates/home.php" class="flex items-center gap-3">
      <i class="fas fa-sitemap text-blue-600 text-2xl"></i>
      <h1 class="text-lg font-bold">CEHAB - Acompanhamento de Processos</h1>
    </a>
    <div class="flex items-center gap-2">
      <a href="../templates/home.php" class="btn btn--muted"><i class="fa-solid fa-chevron-left"></i> Voltar</a>
    </div>
  </div>
</header>

<main class="container">
  <h2 class="title">Gerar Relatório de Processo</h2>

  <div class="card">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="label">Número do Processo</label>
        <input id="processNumber" type="text" class="input"
               placeholder="Ex: 0060900018.001341/2025-49"
               maxlength="25"
               pattern="\d{10}\.\d{6}/\d{4}-\d{2}"
               title="Formato: NNNNNNNNNN.NNNNNN/NNNN-NN"
               autocomplete="off">
        <p class="text-gray-500 text-sm mt-2">Digite o número completo. O sistema validará a existência.</p>
      </div>

      <div>
        <label class="label">Setor Demandante (opcional)</label>
        <input id="requestingSector" type="text" class="input" placeholder="Filtrar por setor" />
      </div>
    </div>

    <div class="flex items-center gap-3 mt-4">
      <button id="btnCheck" class="btn btn--blue">
        <i class="fa-solid fa-magnifying-glass"></i> Verificar Processo
      </button>

      <button id="btnClear" class="btn btn--muted">
        <i class="fa-solid fa-xmark"></i> Limpar
      </button>

      <button id="btnPdf" class="btn btn--green" disabled>
        <i class="fa-regular fa-file-pdf"></i> Gerar Relatório (PDF)
      </button>
    </div>

    <div id="resultBox" class="notice mt-4 hidden"></div>
  </div>
</main>

<script>
  const el = (id) => document.getElementById(id);
  const resultBox = el('resultBox');
  const btnPdf = el('btnPdf');

  const showResult = (html, ok=false) => {
    resultBox.classList.remove('hidden');
    resultBox.style.background = ok ? '#ecfdf5' : '#f3f4f6';
    resultBox.style.border = ok ? '1px solid #10b981' : '1px solid #e5e7eb';
    resultBox.innerHTML = html;
  };

  el('btnClear').addEventListener('click', () => {
    el('processNumber').value = '';
    el('requestingSector').value = '';
    btnPdf.disabled = true;
    resultBox.classList.add('hidden');
    resultBox.innerHTML = '';
  });

  el('btnCheck').addEventListener('click', async () => {
    const num = el('processNumber').value.trim();
    const setor = el('requestingSector').value.trim();

    if (!num) {
      showResult('<b>Atenção:</b> informe o número do processo.', false);
      btnPdf.disabled = true;
      return;
    }

    try {
      const res = await fetch('svc_buscar_processo.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ numero: num, setor })
      });

      const data = await res.json();
      if (data.ok && data.registro) {
        btnPdf.disabled = false;
        btnPdf.dataset.num = num;
        showResult(`
          <div>
            <div class="font-bold mb-1">Processo localizado ✅</div>
            <div><b>Número:</b> ${data.registro.numero_processo}</div>
            <div><b>Setor Demandante:</b> ${data.registro.setor_demandante || '—'}</div>
            <div><b>Enviar para:</b> ${data.registro.setor_destino || '—'}</div>
            <div><b>Tipos:</b> ${data.registro.tipos || '—'}</div>
            <div><b>Descrição:</b> ${data.registro.descricao || '—'}</div>
            <div><b>Criado em:</b> ${data.registro.criado_em || '—'}</div>
            <div class="mt-2 text-sm text-gray-600">
              <b>Eventos no fluxo:</b> ${Array.isArray(data.fluxo) && data.fluxo.length ? data.fluxo.length : 0}
            </div>
          </div>
        `, true);
      } else {
        btnPdf.disabled = true;
        showResult('Nenhum processo encontrado com esse número (e setor, se informado). ❌', false);
      }
    } catch (e) {
      btnPdf.disabled = true;
      showResult('Erro ao consultar. Verifique a rede/servidor.', false);
    }
  });

  btnPdf.addEventListener('click', () => {
    const num = btnPdf.dataset.num;
    if (!num) return;
    window.open('svc_relatorio_pdf.php?numero=' + encodeURIComponent(num), '_blank');
  });
</script>

</body>
</html>
