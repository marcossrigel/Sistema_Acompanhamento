<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Não autenticado.']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id         = (int)($input['id'] ?? 0);
$novo_setor = trim($input['novo_setor'] ?? '');

if ($id <= 0 || $novo_setor === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Dados inválidos.']);
  exit;
}

// Busca o processo
$st = $connLocal->prepare("SELECT id, setor_demandante, enviar_para FROM novo_processo WHERE id = ? LIMIT 1");
$st->bind_param("i", $id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'Processo não encontrado.']);
  exit;
}

// Só pode encaminhar se o processo estiver no seu setor
$meuSetor = $_SESSION['setor'] ?? '';
if (strcasecmp($row['enviar_para'], $meuSetor) !== 0) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Você não pode encaminhar este processo.']);
  exit;
}

$connLocal->begin_transaction();
try {
  // Atualiza destino no processo
  $up = $connLocal->prepare("UPDATE novo_processo SET enviar_para = ? WHERE id = ?");
  $up->bind_param("si", $novo_setor, $id);
  if (!$up->execute()) { throw new Exception('Falha ao atualizar processo.'); }

  // Fecha etapa atual
  $q1 = $connLocal->prepare("UPDATE processo_fluxo SET status='concluido', data_registro=NOW() WHERE processo_id=? AND status='atual'");
  $q1->bind_param("i", $id);
  $q1->execute();

  // Nova ordem
  $res = $connLocal->query("SELECT COALESCE(MAX(ordem),0) AS max_ordem FROM processo_fluxo WHERE processo_id={$id}");
  $max = (int)($res->fetch_assoc()['max_ordem'] ?? 0);
  $nextOrdem = $max + 1;

  // Abre nova etapa
  $st3 = $connLocal->prepare("INSERT INTO processo_fluxo (processo_id, ordem, setor, status) VALUES (?, ?, ?, 'atual')");
  $st3->bind_param("iis", $id, $nextOrdem, $novo_setor);
  $st3->execute();

  $connLocal->commit();
  echo json_encode(['ok'=>true,'novo_setor'=>$novo_setor]);

} catch (Throwable $e) {
  $connLocal->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Falha ao encaminhar: '.$e->getMessage()]);
}
