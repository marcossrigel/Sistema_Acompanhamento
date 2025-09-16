<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

$cfgPath = __DIR__ . '/templates/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/config.php'; }
if (!file_exists($cfgPath)) { http_response_code(500); exit('Arquivo de configuração não encontrado.'); }
require_once $cfgPath;

function getTokenRow(mysqli $dbRemote, string $token): ?array {
  $stmt = $dbRemote->prepare("SELECT g_id, u_rede, data_hora FROM token_sessao WHERE token = ? LIMIT 1");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_assoc() ?: null;
}
function getCehabUser(mysqli $dbRemote, int $g_id): ?array {
  $stmt = $dbRemote->prepare("SELECT g_id, u_rede, u_nome_completo AS nome, u_email FROM users WHERE g_id = ? LIMIT 1");
  $stmt->bind_param("i", $g_id);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_assoc() ?: null;
}
function getLocalUser(mysqli $dbLocal, int $g_id): ?array {
  $stmt = $dbLocal->prepare("SELECT * FROM usuarios WHERE id_usuario_cehab_online = ? LIMIT 1");
  $stmt->bind_param("i", $g_id);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res->fetch_assoc() ?: null;
}

function requireAuth(mysqli $dbLocal, mysqli $dbRemote) {
  if (!empty($_SESSION['auth_ok']) && !empty($_SESSION['g_id'])) { return; }
  $token = $_GET['token'] ?? $_GET['t'] ?? '';
  $token = trim($token);
  if ($token === '' || strlen($token) < 32) { http_response_code(401); exit('Acesso negado.'); }
  $tk = getTokenRow($dbRemote, $token);
  if (!$tk) { http_response_code(401); exit('Token inválido.'); }
  $g_id  = (int)$tk['g_id'];
  $uRede = $tk['u_rede'] ?? null;
  $uRemote = getCehabUser($dbRemote, $g_id);
  $uLocal  = getLocalUser($dbLocal, $g_id);
  if (!$uRemote) { http_response_code(403); exit('Usuário não encontrado.'); }
  if (!$uLocal) {
    $query = http_build_query([
      'g_id'   => $uRemote['g_id'],
      'u_rede' => $uRemote['u_rede'] ?? $uRede,
      'nome'   => $uRemote['nome'] ?? '',
      'email'  => $uRemote['u_email'] ?? '',
      'origem' => 'token'
    ]);
    header("Location: cadastro.php?".$query);
    exit;
  }
  $_SESSION['auth_ok'] = true;
  $_SESSION['g_id']    = $uRemote['g_id'];
  $_SESSION['u_rede']  = $uRemote['u_rede'] ?? $uRede;
  $_SESSION['nome']    = $uRemote['nome'] ?? '';
  $_SESSION['email']   = $uRemote['u_email'] ?? '';
  $_SESSION['id_usuario_local'] = $uLocal['id_usuario'] ?? ($uLocal['id'] ?? null);
  $_SESSION['setor']   = $uLocal['setor'] ?? ($uLocal['setor_nome'] ?? '—');
}

requireAuth($connLocal, $connRemoto);

$nome  = htmlspecialchars($_SESSION['nome'] ?: ($_SESSION['u_rede'] ?? 'Usuário'), ENT_QUOTES, 'UTF-8');
$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CEHAB - Encaminhados</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="antialiased bg-[#f0f2f5]">
  <?php $isEnc = basename($_SERVER['PHP_SELF']) === 'encaminhado.php'; ?>
  <!-- HEADER (mesmo da home) -->
  <header class="bg-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
      <a href="home.php" class="flex items-center group">
        <i class="fas fa-sitemap text-3xl text-blue-600 mr-3"></i>
        <h1 class="text-2xl font-bold text-gray-800 group-hover:text-blue-700 transition">
          CEHAB - Acompanhamento de Processos
        </h1>
      </a>
      <div class="flex items-center gap-2">
        <a
          href="encaminhado.php"
          class="<?= $isEnc
            ? 'bg-blue-600 hover:bg-blue-700 text-white'
            : 'bg-white border border-blue-600 text-blue-600 hover:bg-blue-50'
          ?> font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300 flex items-center"
        >
          <i class="fa-regular fa-share-from-square mr-2"></i> Encaminhados
        </a>
        <!-- na página encaminhado.php você pode ocultar o botão de novo processo, se quiser -->
        <a href="home.php"
           class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-lg shadow-sm transition duration-300">
          Voltar
        </a>
      </div>
    </div>
  </header>

  <!-- CONTEÚDO -->
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

      <!-- TODO: liste aqui os encaminhados -->
      <p class="text-gray-500">Em breve: listagem de processos encaminhados…</p>
    </div>
  </main>
</body>
</html>
