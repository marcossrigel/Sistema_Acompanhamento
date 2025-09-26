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
  <title>CEHAB - Encaminhados</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body{font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial,'Noto Sans';background:#f0f2f5}
    #detailsModal #flowList ul { padding-left: 0; }
    #detailsModal #flowList li { text-indent: 0; }
  </style>
</head>
<body class="antialiased">
  <!-- HEADER -->
  <header class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
      <a href="home.php" class="flex items-center group">
        <i class="fas fa-sitemap text-3xl text-blue-600 mr-3"></i>
        <h1 class="text-2xl font-bold text-gray-800 group-hover:text-blue-700 transition">
          CEHAB - Acompanhamento de Processos
        </h1>
      </a>
      <div class="flex items-center gap-2">
        <span class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg shadow-sm flex items-center cursor-default">
          <i class="fa-regular fa-share-from-square mr-2"></i> Encaminhados
        </span>
        <a href="home.php"
           class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center">
          <i class="fa-solid fa-arrow-left mr-2"></i> Voltar
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white p-6 rounded-lg shadow-lg">
      <div class="mb-3 flex items-center gap-2 text-sm text-gray-700">
        <i class="fas fa-building text-gray-500"></i>
        <span>Setor do usuário:</span>
        <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 font-semibold">
          <?= $setor ?>
        </span>
      </div>

  <h2 class="text-xl font-semibold text-gray-700 mb-4">Processos Encaminhados</h2>
  <div id="encList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
  </div>
  </main>

<div id="detailsModal" class="fixed inset-0 z-50 hidden bg-black/40 items-center justify-center">
  <div class="bg-white rounded-lg shadow-2xl w-full max-w-5xl m-4">
    <div class="p-5 border-b flex justify-between items-center">
      <h3 class="text-xl font-semibold">Detalhes do Processo</h3>
      <button id="closeDetails" class="text-gray-500 hover:text-gray-700">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2">
        <h4 class="text-md font-semibold text-gray-800 mb-3">Histórico e Fluxo do Processo</h4>
        <div id="flowList" class="space-y-3"></div>
      </div>

<aside class="lg:col-span-1">
  <div class="bg-gray-50 border rounded-lg p-4">
    <h4 class="font-semibold text-gray-800 mb-3">Informações Gerais</h4>

    <div class="space-y-2 text-sm">
      <p><span class="text-gray-500">Número:</span> <span id="d_num" class="font-medium">—</span></p>
      <p><span class="text-gray-500">Setor Demandante:</span> <span id="d_setor" class="font-medium">—</span></p>
      <p><span class="text-gray-500">Tipos:</span> <span id="d_tipos" class="font-medium">—</span></p>
      <p id="d_outros_row" class="hidden">
        <span class="text-gray-500">Outros:</span> <span id="d_outros" class="font-medium">—</span>
      </p>
      <p><span class="text-gray-500">Descrição:</span> <span id="d_desc" class="font-medium break-words">—</span></p>
      <p><span class="text-gray-500">Criado em:</span> <span id="d_dt" class="font-medium">—</span></p>
    </div>
  </div>

  <div class="mt-3 text-sm">
    <p>
      <span class="text-gray-500">Enviar para:</span>
      <span id="d_dest" class="font-medium">—</span>
    </p>
  </div>

    <div id="encBlock" class="mt-4 border-t pt-4">
      <label class="block text-sm font-medium text-gray-700 mb-1">Encaminhar para</label>
      
      <select id="nextSector" class="w-full border rounded-md px-3 py-2">
        <option value="" selected disabled>Selecione o próximo setor.</option>
        <option>DAF - Diretoria de Administração e Finanças</option>
        <option>DOHDU - Diretoria de Obras</option>
        <option>CELOE I - Comissão de Licitação I</option>
        <option>CELOE II - Comissão de Licitação II</option>
        <option>CELOSE - Comissão de Licitação</option>
        <option>GCOMP - Gerência de Compras</option>
        <option>GOP - Gerência de Orçamento e Planejamento</option>
        <option>GFIN - Gerência Financeira</option>
        <option>GCONT - Gerência de Contabilidade</option>
        <option>DP - Diretoria da Presidência</option>
        <option>GAD - Gerência Administrativa</option>
        <option>GAC - Gerência de Acompanhamento de Contratos</option>
        <option>CGAB - Chefia de Gabinete</option>
        <option>DOE - Diretoria de Obras Estratégicas</option>
        <option>DSU - Diretoria de Obras de Saúde</option>
        <option>DSG - Diretoria de Obras de Segurança</option>
        <option>DED - Diretoria de Obras de Educação</option>
        <option>SPO - Superintendência de Projetos de Obras</option>
        <option>SUAJ - Superintendência de Apoio Jurídico</option>
        <option>SUFIN - Superintendência Financeira</option>
        <option>GAJ - Gerência de Apoio Jurídico</option>
        <option>SUPLAN - Superintendência de Planejamento</option>
        <option>DPH - Diretoria de Projetos Habitacionais</option>
      </select>

      <button id="btnEncaminhar" class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-md">
        Encaminhar
      </button>
    </div>

    <div id="acoesModal" class="fixed inset-0 hidden bg-black/40 items-center justify-center z-[60]">
      <div class="bg-white rounded-lg p-6 w-full max-w-xl shadow-2xl">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Ações Internas do Setor</h2>
          <button id="fecharAcoes" class="text-gray-500 hover:text-gray-700"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <ul id="acoesList" class="space-y-3 max-h-64 overflow-auto mb-4"></ul>
        <label class="block text-sm text-gray-600 mb-1">Nova ação (visível a todos):</label>
        <textarea id="acaoTexto" class="w-full border rounded p-2" rows="3" placeholder="Ex.: Tive um problema com tal emenda"></textarea>
        <div class="flex justify-end gap-2 mt-3">
          <button id="cancelarAcoes" class="px-4 py-2 rounded bg-gray-200">Cancelar</button>
          <button id="salvarAcao" class="px-4 py-2 rounded bg-blue-600 text-white">Salvar ação</button>
        </div>
      </div>
    </div>
    <button id="btnAcoes" class="mt-2 w-full border bg-white hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2 rounded-md">
      Ações internas
    </button>
  </aside>

    </div>

    <div class="p-5 border-t text-right">
      <button id="okDetails" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">OK</button>
    </div>
  </div>
</div>

<div id="finalizarModal" class="fixed inset-0 hidden bg-black bg-opacity-40 items-center justify-center z-50">
  <div class="bg-white rounded-lg p-6 w-full max-w-md shadow-lg">
    <h2 class="text-lg font-semibold mb-4">Finalizar Etapa</h2>
    <label class="block text-sm text-gray-600 mb-1">Descreva a ação finalizadora:</label>
    <textarea id="acaoFinalizadora" class="w-full border rounded p-2 mb-4" rows="3"
      placeholder="Ex: GECOMP analisou e encaminhou para GEFIN NE INICIAL"></textarea>
    
    <div class="flex justify-end gap-2">
      <button id="cancelarFinalizar" class="px-4 py-2 rounded bg-gray-200">Cancelar</button>
      <button id="confirmarFinalizar" class="px-4 py-2 rounded bg-blue-600 text-white">Confirmar e Avançar</button>
    </div>
  </div>
</div>

<script>
  window.MY_SETOR = <?= json_encode($_SESSION['setor'] ?? '') ?>;
</script>
<script src="../js/encaminhado.js"></script>

</body>
</html>