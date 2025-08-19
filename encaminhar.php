<?php
$token = $_POST['access_dinamic'] ?? $_GET['access_dinamic'] ?? '';
if (!$token) {
  die("Token de acesso ausente.");
}
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;
date_default_timezone_set('America/Recife');

// 1. Recebe os dados
$id_demanda = (int)($_POST['id_demanda'] ?? 0);
$setor_destino = trim($_POST['setor_destino'] ?? '');
$setor_origem = trim($_POST['setor_origem'] ?? 'INDEFINIDO');
$status = 'Em andamento';

// 2. Verifica se a demanda existe
$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id_demanda);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orig) {
  die('Solicitação não encontrada.');
}

// 3. Grava o histórico de encaminhamento
$stmt = $conn->prepare("INSERT INTO encaminhamentos 
  (id_demanda, setor_origem, setor_destino, status, data_encaminhamento) 
  VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("isss", $id_demanda, $setor_origem, $setor_destino, $status);
$stmt->execute();
$stmt->close();

// 4. Atualiza o setor atual na tabela solicitacoes
$stmt = $conn->prepare("UPDATE solicitacoes SET setor_responsavel = ?, data_liberacao = CURDATE() WHERE id = ?");
$stmt->bind_param("si", $setor_destino, $id_demanda);
$stmt->execute();
$stmt->close();

// 5. Redireciona
header("Location: painel.php?access_dinamic=" . urlencode($token));

exit;
