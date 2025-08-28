<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/config.php';
$conn->set_charset('utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: visualizar.php');
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  header('Location: visualizar.php?mensagem=erro&detalhe=' . urlencode('ID inválido'));
  exit;
}

$stmt = $conn->prepare("DELETE FROM solicitacoes WHERE id=?");
if (!$stmt) {
  header('Location: visualizar.php?mensagem=erro&detalhe=' . urlencode($conn->error));
  exit;
}
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
  header('Location: visualizar.php?mensagem=excluido');
  exit;
} else {
  header('Location: visualizar.php?mensagem=erro&detalhe=' . urlencode($stmt->error));
  exit;
}
