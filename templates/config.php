<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

/* ==================== CONEXÕES EXISTENTES ==================== */
// ... seu código atual de $connLocal, $connRemoto, etc.
# ao final, você já define:
# $conn     = $connLocal;
# $conexao  = $connLocal;
# $conexao2 = $connRemoto;

/* ==================== FIREBASE (CLIENT) ==================== */
/**
 * Config do Firebase que o FRONTEND precisa. Isso NÃO é secreto.
 * Preencha com os dados do seu projeto Firebase.
 * Ideal: usar getenv() com variáveis de ambiente no servidor.
 */
const FIREBASE_CLIENT_CONFIG = [
  'apiKey'        => '', // getenv('FIREBASE_API_KEY') ?: ''
  'authDomain'    => '', // ex: "meuapp.firebaseapp.com"
  'projectId'     => '', // ex: "meuapp"
  'appId'         => '', // opcional
  'storageBucket' => '', // opcional
];

/* ==================== FIREBASE (ADMIN OPCIONAL) ==================== */
/**
 * Para emitir Custom Token (autenticar usuário do PHP no Firebase),
 * instale via Composer:
 *   composer require kreait/firebase-php
 * Guarde o JSON da service account em local seguro e aponte a constante abaixo.
 */
define('FIREBASE_SA_JSON', getenv('FIREBASE_SA_JSON') ?: __DIR__ . '/firebase-service-account.json');

$__firebaseAuth = null;
try {
  if (is_file(FIREBASE_SA_JSON)) {
    require_once __DIR__ . '/vendor/autoload.php';
    $factory = (new \Kreait\Firebase\Factory())->withServiceAccount(FIREBASE_SA_JSON);
    $__firebaseAuth = $factory->createAuth();
  }
} catch (Throwable $e) {
  // não derruba a app se não tiver token; o front usará anonymous auth
}

/**
 * Gera um Custom Token para o usuário logado no PHP (se possível).
 * @return string|null
 */
function firebase_custom_token_for_current_user(): ?string {
  global $__firebaseAuth, $conn;

  if (!$__firebaseAuth) return null;

  // seu padrão atual de sessão/portal
  $idPortal = (int)($_SESSION['id_portal'] ?? $_SESSION['id_usuario_cehab_online'] ?? 0);
  if ($idPortal <= 0) return null;

  // opcional: buscar setor para claims
  $setor = null;
  if ($conn && $st = $conn->prepare("SELECT setor FROM usuarios WHERE id_usuario_cehab_online = ? LIMIT 1")) {
    $st->bind_param('i', $idPortal);
    if ($st->execute() && ($row = $st->get_result()->fetch_assoc())) {
      $setor = $row['setor'] ?? null;
    }
    $st->close();
  }

  $uid = 'cehab:' . $idPortal; // UID único no Firebase
  $claims = array_filter([
    'perfil' => $_SESSION['tipo_usuario'] ?? 'solicitante',
    'setor'  => $setor,
  ]);

  try {
    return $__firebaseAuth->createCustomToken($uid, $claims)->toString();
  } catch (Throwable $e) {
    return null; // front cai em anonymous
  }
}

/**
 * Emite as variáveis JS esperadas pelo seu frontend:
 *   - __firebase_config
 *   - __app_id
 *   - __initial_auth_token (se disponível)
 *
 * $appId: agrupe por ambiente/cliente/órgão, ex: "cehab-portal"
 *         isso define o caminho no Firestore: artifacts/{appId}/public/data/processes
 */
function emit_frontend_bootstrap_vars(string $appId = 'cehab-portal'): void {
  $cfg   = FIREBASE_CLIENT_CONFIG;
  $token = firebase_custom_token_for_current_user();

  // segurança: encode como JSON literal
  echo "<script>";
  echo "const __firebase_config = " . json_encode($cfg, JSON_UNESCAPED_SLASHES) . ";";
  echo "const __app_id = " . json_encode($appId, JSON_UNESCAPED_SLASHES) . ";";
  if ($token) {
    echo "const __initial_auth_token = " . json_encode($token, JSON_UNESCAPED_SLASHES) . ";";
  } else {
    echo "const __initial_auth_token = null;";
  }
  echo "</script>";
}
