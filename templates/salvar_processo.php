<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

$cfgPath = __DIR__ . '/templates/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/config.php'; }
if (!file_exists($cfgPath)) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Arquivo de configuração não encontrado.']);
  exit;
}
require_once $cfgPath;

// precisa estar logado
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Não autenticado.']);
  exit;
}

$g_id  = (int)($_SESSION['g_id'] ?? 0);              // (2) id_usuario_cehab_online
$setor = trim($_SESSION['setor'] ?? '');             // (3) setor_demandante

// lê JSON do fetch
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$numero   = trim($body['numero_processo'] ?? '');
$desc     = trim($body['descricao'] ?? '');
$dataCli  = trim($body['data_registro_client'] ?? ''); // (4) data/hora da máquina do usuário, formato "YYYY-MM-DD HH:MM:SS"

if ($numero === '' || $desc === '' || $setor === '' || !$g_id) {
  http_response_code(422);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Campos obrigatórios ausentes.']);
  exit;
}

// valida data (formato "YYYY-MM-DD HH:MM:SS"); se vier vazio/errado, usa agora do servidor
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dataCli)) {
  $dataCli = date('Y-m-d H:i:s');
}

$sql = "INSERT INTO novo_processo
        (id_usuario_cehab_online, numero_processo, setor_demandante, descricao, data_registro)
        VALUES (?,?,?,?,?)";

$stmt = $connLocal->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'Falha ao preparar statement.']);
  exit;
}

$stmt->bind_param('issss', $g_id, $numero, $setor, $desc, $dataCli);
$ok = $stmt->execute();

header('Content-Type: application/json');
if ($ok) {
  echo json_encode(['ok'=>true, 'id'=>$stmt->insert_id]);
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Falha ao inserir no banco.']);
}
