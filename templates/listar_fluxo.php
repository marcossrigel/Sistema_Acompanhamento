<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

$param = (int)($_GET['proc_id'] ?? $_GET['id'] ?? 0);
if ($param <= 0) { echo json_encode(['ok'=>false,'error'=>'id inválido']); exit; }

$processoId = $param;

/** tenta histórico direto (assumindo que $param é processo_id) */
$stmt = $connLocal->prepare("
  SELECT id, processo_id, ordem, setor, status, data_registro, acao_finalizadora
  FROM processo_fluxo
  WHERE processo_id = ?
  ORDER BY ordem ASC, id ASC
");
$stmt->bind_param("i", $processoId);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

if (!$rows || count($rows) === 0) {
  // talvez $param seja um fluxo_id
  $q = $connLocal->prepare("SELECT processo_id FROM processo_fluxo WHERE id = ? LIMIT 1");
  $q->bind_param("i", $param);
  $q->execute();
  $pf = $q->get_result()->fetch_assoc();

  if ($pf && !empty($pf['processo_id'])) {
    $processoId = (int)$pf['processo_id'];

    $stmt2 = $connLocal->prepare("
      SELECT id, processo_id, ordem, setor, status, data_registro, acao_finalizadora
      FROM processo_fluxo
      WHERE processo_id = ?
      ORDER BY ordem ASC, id ASC
    ");
    $stmt2->bind_param("i", $processoId);
    $stmt2->execute();
    $res2  = $stmt2->get_result();
    $rows  = $res2->fetch_all(MYSQLI_ASSOC);
  }
}

/* 2) Se já tem histórico, devolve */
if ($rows && count($rows) > 0) {
  echo json_encode(['ok'=>true, 'data'=>$rows, 'processo_id'=>$processoId]);
  exit;
}

/* 3) Plano B: não há histórico ainda → montar 2 passos a partir de novo_processo */
$baseStmt = $connLocal->prepare("
  SELECT setor_demandante, enviar_para, data_registro
  FROM novo_processo
  WHERE id = ? LIMIT 1
");
$baseStmt->bind_param("i", $processoId);
$baseStmt->execute();
$base = $baseStmt->get_result()->fetch_assoc();

if (!$base) {
  echo json_encode(['ok'=>false, 'error'=>'processo não encontrado']);
  exit;
}

$fake = [
  ['ordem'=>1,'setor'=>$base['setor_demandante'],'status'=>'concluido','data_registro'=>$base['data_registro']],
  ['ordem'=>2,'setor'=>$base['enviar_para'],     'status'=>'atual','data_registro'=>$base['data_registro']],
];

echo json_encode(['ok'=>true,'data'=>$fake,'processo_id'=>$processoId]);
