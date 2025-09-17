<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php'; // mesmo include do seu projeto

header('Content-Type: application/json; charset=utf-8');

try {
  // você pode restringir por usuário, se quiser:
  // $gId = (int)($_SESSION['g_id'] ?? 0);
  // $stmt = $connLocal->prepare("SELECT id, id_usuario_cehab_online, numero_processo, setor_demandante, descricao, data_registro FROM novo_processo WHERE id_usuario_cehab_online = ? ORDER BY id DESC");
  // $stmt->bind_param("i", $gId);

  $sql = "SELECT id, id_usuario_cehab_online, numero_processo, setor_demandante, descricao, data_registro
          FROM novo_processo
          ORDER BY id DESC";
  $stmt = $connLocal->prepare($sql);
  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($r = $res->fetch_assoc()) {
    // normaliza datas em ISO para o JS
    $r['data_registro'] = $r['data_registro'] ? date('Y-m-d H:i:s', strtotime($r['data_registro'])) : null;
    $rows[] = $r;
  }
  echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Falha ao listar processos.'], JSON_UNESCAPED_UNICODE);
}
