<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

$cfgPath = __DIR__ . '/templates/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/config.php'; }
if (!file_exists($cfgPath)) {
  http_response_code(500);
  exit('Arquivo de configuração não encontrado (templates/config.php ou config.php).');
}
require_once $cfgPath;

function getTokenRow(mysqli $dbRemote, string $token): ?array {
  $sql = "SELECT g_id, u_rede, data_hora FROM token_sessao WHERE token = ? LIMIT 1";
  $st  = $dbRemote->prepare($sql);
  $st->bind_param("s", $token);
  $st->execute();
  $r = $st->get_result();
  return $r->fetch_assoc() ?: null;
}
function getRemoteUser(mysqli $dbRemote, int $g_id): ?array {
  $sql = "SELECT g_id, u_rede, u_nome_completo AS nome, u_email FROM users WHERE g_id = ? LIMIT 1";
  $st  = $dbRemote->prepare($sql);
  $st->bind_param("i", $g_id);
  $st->execute();
  $r = $st->get_result();
  return $r->fetch_assoc() ?: null;
}
function getLocalUser(mysqli $dbLocal, int $g_id): ?array {
  $sql = "SELECT * FROM usuarios WHERE id_usuario_cehab_online = ? LIMIT 1";
  $st  = $dbLocal->prepare($sql);
  $st->bind_param("i", $g_id);
  $st->execute();
  $r = $st->get_result();
  return $r->fetch_assoc() ?: null;
}

/* ---------- logout ---------- */
if (isset($_GET['logout'])) {
  // limpa a sessão
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000,
      $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();

  // decide para onde ir
  if (isset($_GET['go']) && $_GET['go'] === 'getic') {
    header('Location: https://www.getic.pe.gov.br/');
  } else {
    header('Location: ./');
  }
  exit;
}

/* ---------- já autenticado ---------- */
if (!empty($_SESSION['auth_ok']) && !empty($_SESSION['g_id'])) {
  header('Location: templates/home.php');
  exit;
}

/* ---------- token obrigatório ---------- */
$token = trim($_GET['access_dinamic'] ?? $_GET['token'] ?? $_GET['t'] ?? '');
if ($token === '' || strlen($token) < 32) {
  http_response_code(401);
  echo '<!doctype html><meta charset="utf-8"><style>body{font:16px/1.5 system-ui;padding:40px}</style>
  <h2>Acesso negado</h2><p>Token ausente ou inválido. Use <code>?token=SEU_TOKEN</code>.</p>';
  exit;
}

/* ---------- valida token no REMOTO ---------- */
$tk = getTokenRow($connRemoto, $token);
if (!$tk) {
  http_response_code(401);
  echo '<!doctype html><meta charset="utf-8"><style>body{font:16px/1.5 system-ui;padding:40px}</style>
  <h2>Token inválido</h2><p>Não foi possível validar o token informado.</p>';
  exit;
}

$g_id   = (int)$tk['g_id'];
$u_rede = $tk['u_rede'] ?? '';

/* ---------- pega usuário remoto ---------- */
$uRemote = getRemoteUser($connRemoto, $g_id);
if (!$uRemote) {
  http_response_code(403);
  echo '<!doctype html><meta charset="utf-8"><style>body{font:16px/1.5 system-ui;padding:40px}</style>
  <h2>Usuário não encontrado</h2><p>O <code>g_id</code> do token não foi localizado na base remota.</p>';
  exit;
}

/* ---------- confere usuário local ---------- */
$uLocal = getLocalUser($connLocal, $g_id);
if (!$uLocal) {
  // não cadastrado → manda para templates/solicitacoes.php
  $query = http_build_query([
    'g_id'   => $uRemote['g_id'],
    'u_rede' => $uRemote['u_rede'] ?? $u_rede,
    'nome'   => $uRemote['nome'] ?? '',
    'email'  => $uRemote['u_email'] ?? '',
    'origem' => 'token'
  ]);
  header('Location: templates/solicitacoes.php?'.$query);
  exit;
}

/* ---------- cadastrado → cria sessão e vai pra home ---------- */
$_SESSION['auth_ok']          = true;
$_SESSION['g_id']             = $uRemote['g_id'];
$_SESSION['u_rede']           = $uRemote['u_rede'] ?? $u_rede;
$_SESSION['nome']             = $uRemote['nome'] ?? '';
$_SESSION['email']            = $uRemote['u_email'] ?? '';
$_SESSION['id_usuario_local'] = $uLocal['id'] ?? $uLocal['id_usuario'] ?? null;
$_SESSION['setor']            = $uLocal['setor'] ?? '—';

header('Location: templates/home.php');
exit;
?>
