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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CEHAB - Acompanhamento de Processos</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; background:#f0f2f5; }
  </style>
</head>
<body class="antialiased">
  <!-- HEADER / TOP BAR -->
  <header class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
      <div class="flex items-center">
        <i class="fas fa-sitemap text-3xl text-blue-600 mr-3"></i>
        <h1 class="text-2xl font-bold text-gray-800">CEHAB - Acompanhamento de Processos</h1>
      </div>
      <div class="flex items-center gap-2">
        <a href="encaminhado.php"
          class="bg-white border border-blue-600 text-blue-600 hover:bg-blue-50 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center">
          <i class="fa-regular fa-share-from-square mr-2"></i> Encaminhados
        </a>

        <!-- ABRIR MODAL -->
        <button id="newProcessBtn"
          class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
          <i class="fas fa-plus mr-2"></i> Novo Processo
        </button>

        <!-- Sair: faz logout e redireciona para GETIC -->
        <a href="../index.php?logout=1&go=getic"
           class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-300 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center">
          <i class="fa-solid fa-right-from-bracket mr-2"></i> Sair
        </a>
      </div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white p-6 rounded-lg shadow-lg">
      <!-- Linha do setor do usuário -->
      <div class="mb-3 flex items-center gap-2 text-sm text-gray-700">
        <i class="fas fa-building text-gray-500"></i>
        <span>Setor do usuário:</span>
        <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-50 text-indigo-700 font-semibold">
          <?= $setor ?>
        </span>
      </div>

      <h2 class="text-xl font-semibold text-gray-700 mb-4">Processos em Andamento</h2>

      <div id="processList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
    </div>
  </main>

  <!-- MODAL: NOVO PROCESSO -->
  <div id="processModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-30 items-center justify-center">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg m-4 overflow-hidden">
      <div class="p-6 border-b">
        <h3 class="text-2xl font-semibold text-gray-800">Novo Processo</h3>
      </div>
      <form id="processForm" class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Número do Processo</label>
          <input id="processNumber" type="text" required
                 class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Setor Demandante</label>
          <input id="requestingSectorModal" type="text" value="<?= $setor ?>" readonly
                 class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-700 cursor-not-allowed">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Enviar para</label>
          <select id="destSector" required
                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            <option value="" selected disabled>Selecione o setor...</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de processo</label>

          <label class="flex items-center gap-2 cursor-pointer mb-2">
            <input type="checkbox" name="tipo_proc" value="nova licitação/aquisição" class="h-4 w-4">
            <span>nova licitação/aquisição</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer mb-2">
            <input type="checkbox" name="tipo_proc" value="solicitação de pagamento" class="h-4 w-4">
            <span>solicitação de pagamento</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer mb-2">
            <input id="tipoOutrosCheck" type="checkbox" name="tipo_proc" value="outros" class="h-4 w-4">
            <span>outros</span>
          </label>

          <input id="tipoOutrosInput" type="text" placeholder="Descreva o tipo…"
                 class="hidden w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Descrição</label>
          <textarea id="description" rows="3" required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Descreva o processo..."></textarea>
        </div>

        <div class="flex justify-end pt-4 gap-2">
          <button type="button" id="closeModalBtn"
                  class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg">
            Cancelar
          </button>
          <button type="submit"
                  class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
            Salvar Processo
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL DE SUCESSO -->
<div id="successModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-30 items-center justify-center">
  <div class="bg-white rounded-lg shadow-2xl w-full max-w-sm m-4 overflow-hidden">
    <div class="p-6 text-center">
      <h3 class="text-xl font-semibold text-gray-800 mb-4">Processo salvo com sucesso!</h3>
      <button id="successOkBtn"
              class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
        OK
      </button>
    </div>
  </div>
</div>

<!-- MODAL DETALHES (com fluxo) -->
<div id="detailsModal" class="fixed inset-0 z-50 hidden bg-black/40 items-center justify-center">
  <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl m-4">
    <div class="p-5 border-b flex justify-between items-center">
      <h3 class="text-xl font-semibold">Detalhes do Processo</h3>
      <button id="closeDetails" class="text-gray-500 hover:text-gray-700">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>
    </div>

    <div class="p-5">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Coluna esquerda: Fluxo -->
        <div class="lg:col-span-2">
          <h4 class="text-base font-semibold text-gray-800 mb-3">Histórico e Fluxo do Processo</h4>
          <div id="flowList" class="space-y-3"></div>
        </div>

        <!-- Coluna direita: Informações Gerais -->
        <aside class="lg:col-span-1">
          <div class="bg-gray-50 border rounded-lg p-4">
            <h5 class="font-semibold text-gray-800 mb-3">Informações Gerais</h5>
            <div class="space-y-2 text-sm">
              <p><span class="text-gray-500">Número:</span> <span id="d_num" class="font-medium"></span></p>
              <p><span class="text-gray-500">Setor Demandante:</span> <span id="d_setor" class="font-medium"></span></p>
              <p><span class="text-gray-500">Enviar para:</span> <span id="d_dest" class="font-medium"></span></p>
              <p><span class="text-gray-500">Tipos:</span> <span id="d_tipos" class="font-medium"></span></p>
              <p id="d_outros_row" class="hidden">
                <span class="text-gray-500">Outros:</span> <span id="d_outros" class="font-medium"></span>
              </p>
              <p><span class="text-gray-500">Descrição:</span> <span id="d_desc" class="font-medium"></span></p>
              <p><span class="text-gray-500">Criado em:</span> <span id="d_dt" class="font-medium"></span></p>
            </div>
          </div>

          <div class="mt-4 text-right">
            <button id="okDetails"
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
              OK
            </button>
          </div>
        </aside>
      </div>
    </div>
  </div>
</div>

<script>
  window.APP = {
    MY_SETOR: <?= json_encode($_SESSION['setor'] ?? '') ?>,
    USER_NAME: <?= json_encode($_SESSION['nome']  ?? '') ?>,
    GID:       <?= json_encode($_SESSION['g_id']  ?? '') ?>
  };
</script>
<script src="../js/home.js?v=3"></script>

</body>
</html>
