<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

require 'db.php'; // conexão PDO

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Não autenticado.']);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id_processo       = $data['id_processo'] ?? null;
$setor_origem      = $data['setor_origem'] ?? '';
$setor_destino     = $data['setor_destino'] ?? '';
$acao_finalizadora = $data['acao_finalizadora'] ?? '';

if (!$id_processo || !$setor_destino || !$acao_finalizadora) {
    echo json_encode(['ok'=>false,'error'=>'Dados incompletos.']); 
    exit;
}

// 1. Marca setor atual como concluído e registra ação finalizadora
$stmt = $pdo->prepare("UPDATE processo_fluxo 
                          SET status='concluido', acao_finalizadora=? 
                        WHERE id_processo=? AND setor=? AND status='ativo'");
$stmt->execute([$acao_finalizadora, $id_processo, $setor_origem]);

// 2. Cria nova etapa para o próximo setor
$stmt = $pdo->prepare("INSERT INTO processo_fluxo (id_processo, setor, status, data_registro) 
                       VALUES (?,?, 'ativo', NOW())");
$stmt->execute([$id_processo, $setor_destino]);

echo json_encode(['ok'=>true]);
