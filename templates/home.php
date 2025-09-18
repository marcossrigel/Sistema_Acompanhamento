<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

/* se quiser impedir acesso direto sem login/token */
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php'); // volta para o index para validar token
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
        <button class="bg-white border border-blue-600 text-blue-600 hover:bg-blue-50 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center">
          <i class="fa-regular fa-share-from-square mr-2"></i> Encaminhados
        </button>
        <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 flex items-center">
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

      <!-- Área de conteúdo (vazia por enquanto) -->
      <div class="border border-dashed border-gray-200 rounded-lg p-8 text-center text-gray-400">
        Nenhum processo encontrado.
      </div>
    </div>
  </main>
</body>
</html>
