<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['ok'=>false, 'error'=>'id inválido']); exit; }

// tenta histórico completo
$st = $connLocal->prepare("
  SELECT id, processo_id, ordem, setor, status, data_registro
  FROM processo_fluxo
  WHERE processo_id = ?
  ORDER BY ordem ASC
");
$st->bind_param("i", $id);
$st->execute();
$res = $st->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);

if ($rows && count($rows) > 0) {
  echo json_encode(['ok'=>true,'data'=>$rows]);
  exit;
}

// plano B (não tem histórico: monta 2 passos a partir de novo_processo)
$st2 = $connLocal->prepare("
  SELECT setor_demandante, enviar_para, data_registro
  FROM novo_processo WHERE id = ? LIMIT 1
");
$st2->bind_param("i", $id);
$st2->execute();
$base = $st2->get_result()->fetch_assoc();

if (!$base) { echo json_encode(['ok'=>false, 'error'=>'processo não encontrado']); exit; }

$fake = [
  ['ordem'=>1,'setor'=>$base['setor_demandante'],'status'=>'concluido','data_registro'=>$base['data_registro']],
  ['ordem'=>2,'setor'=>$base['enviar_para'],     'status'=>'atual',     'data_registro'=>$base['data_registro']],
];
echo json_encode(['ok'=>true,'data'=>$fake]);
