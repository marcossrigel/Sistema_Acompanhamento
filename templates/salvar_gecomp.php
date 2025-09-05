<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal ?? $conn ?? $conexao ?? null;
$conn->set_charset('utf8mb4');
header('Content-Type: application/json; charset=utf-8');

$token = $_GET['access_dinamic'] ?? $_POST['access_dinamic'] ?? '';
if ($token === '') { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'token ausente']); exit; }

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: $_POST;

$id       = (int)($body['id'] ?? 0);
$tr       = isset($body['tr'])       ? (int)!!$body['tr']       : null;
$etp      = isset($body['etp'])      ? (int)!!$body['etp']      : null;
$cotacao  = isset($body['cotacao'])  ? (int)!!$body['cotacao']  : null;
$obs      = isset($body['obs'])      ? trim((string)$body['obs']) : null;

if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

$fields = [];
$params = [];
$types  = '';

if ($tr !== null)      { $fields[] = 'gecomp_tr = ?';      $params[] = $tr;      $types .= 'i'; }
if ($etp !== null)     { $fields[] = 'gecomp_etp = ?';     $params[] = $etp;     $types .= 'i'; }
if ($cotacao !== null) { $fields[] = 'gecomp_cotacao = ?'; $params[] = $cotacao; $types .= 'i'; }
if ($obs !== null)     { $fields[] = 'gecomp_obs = ?';     $params[] = $obs;     $types .= 's'; }

if (!$fields) { echo json_encode(['ok'=>true,'msg'=>'Nada para atualizar']); exit; }

$sql = "UPDATE solicitacoes SET ".implode(', ', $fields)." WHERE id = ?";
$params[] = $id;
$types .= 'i';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
  echo json_encode(['ok'=>true]);
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$stmt->error]);
}


