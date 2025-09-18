<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');
header('Content-Type: application/json; charset=utf-8');

$cfgPath = __DIR__ . '/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/../config.php'; }
if (!file_exists($cfgPath)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Config não encontrada']);
  exit;
}
require_once $cfgPath;

if (empty($_SESSION['auth_ok']) || empty($_SESSION['setor'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Não autenticado']);
  exit;
}

$setor = $_SESSION['setor'];

try {
  $sql = "SELECT id, id_usuario_cehab_online, numero_processo, setor_demandante,
                 enviar_para, tipos_processo_json, tipo_outros, descricao, data_registro
          FROM novo_processo
          WHERE enviar_para = ?
          ORDER BY id DESC";
  $st = $connLocal->prepare($sql);
  $st->bind_param('s', $setor);
  $st->execute();
  $res = $st->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) { $rows[] = $r; }

  echo json_encode(['ok'=>true,'data'=>$rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
