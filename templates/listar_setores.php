<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require __DIR__ . '/config.php'; // <-- caminho correto
header('Content-Type: application/json; charset=utf-8');

try {
  $q = $connLocal->prepare("SELECT nome, is_finalizador FROM setores WHERE ativo=1 ORDER BY nome");
  $q->execute();
  $res = $q->get_result()->fetch_all(MYSQLI_ASSOC);

  $final = null;
  foreach ($res as $r) { if ((int)$r['is_finalizador'] === 1) { $final = $r['nome']; break; } }
  if (!$final) { $final = 'GFIN - Gerência Financeira'; }

  echo json_encode(['ok'=>true, 'data'=>$res, 'finalizador'=>$final]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Falha ao listar setores']);
}
