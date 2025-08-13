<?php

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
date_default_timezone_set('America/Recife');

$dbLocal  = $connLocal  ?? ($conn ?? ($conexao ?? null));
$dbRemoto = $connRemoto ?? ($conexao2 ?? null);

if (!$dbLocal || !$dbRemoto) {
  http_response_code(500);
  echo 'Erro de configuração das conexões.';
  exit;
}

$token = trim($_GET['access_dinamic'] ?? '');
if ($token === '') { http_response_code(401); echo 'Token inválido ou expirado.'; exit; }
$idPortal = 0;
try {
  $sql = "SELECT g_id AS usuario_id FROM token_sessao WHERE token = ? ORDER BY id DESC LIMIT 1";
  if ($st = $dbRemoto->prepare($sql)) {
    $st->bind_param('s', $token);
    $st->execute();
    $r = $st->get_result();
    if ($r && ($row = $r->fetch_assoc()) && !empty($row['usuario_id'])) {
      $idPortal = (int)$row['usuario_id'];
    }
    $st->close();
  }
} catch (Throwable $e) {

}

if ($idPortal <= 0) { http_response_code(401); echo 'Token inválido ou expirado.'; exit; }

$sqlLocal = "SELECT id, id_usuario_cehab_online, nome, setor
             FROM usuarios
             WHERE id_usuario_cehab_online = ?
             LIMIT 1";
$st2 = $dbLocal->prepare($sqlLocal);
if (!$st2) { http_response_code(500); echo 'Falha ao consultar usuário local.'; exit; }
$st2->bind_param('i', $idPortal);
$st2->execute();
$user = $st2->get_result()->fetch_assoc();
$st2->close();

if (!$user) {
  http_response_code(403);
  echo "Usuário (portal id {$idPortal}) não encontrado no sistema local.";
  exit;
}

$_SESSION['id_usuario']              = (int)$user['id'];
$_SESSION['id_usuario_cehab_online'] = (int)$user['id_usuario_cehab_online'];
$_SESSION['nome']                    = $user['nome'];
$_SESSION['setor']                   = $user['setor'];
$_SESSION['tipo_usuario']            = (strcasecmp(trim($user['setor']), 'Solicitante') === 0) ? 'solicitante' : 'colaborador';

header('Location: ' . ($_SESSION['tipo_usuario'] === 'solicitante' ? 'home.php' : 'painel.php'));
exit;
