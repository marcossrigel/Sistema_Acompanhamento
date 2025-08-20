<?php
$token = $_POST['access_dinamic'] ?? $_GET['access_dinamic'] ?? '';
if (!$token) {
  die("Token de acesso ausente.");
}
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;
date_default_timezone_set('America/Recife');

$id_demanda = (int)($_POST['id_demanda'] ?? 0);
$setor_destino = trim($_POST['setor_destino'] ?? '');
$setor_origem = trim($_POST['setor_origem'] ?? 'INDEFINIDO');
$status = 'Em andamento';

$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id_demanda);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orig) {
  die('Solicitação não encontrada.');
}

$stmt = $conn->prepare("INSERT INTO encaminhamentos 
  (id_demanda, setor_origem, setor_destino, status, data_encaminhamento) 
  VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("isss", $id_demanda, $setor_origem, $setor_destino, $status);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("UPDATE solicitacoes SET setor_responsavel = ? WHERE id = ?");
$stmt->bind_param("si", $setor_destino, $id_demanda);
$stmt->execute();
$stmt->close();

header("Location: painel.php?access_dinamic=" . urlencode($token));

exit;
