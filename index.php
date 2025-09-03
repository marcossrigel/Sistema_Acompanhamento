<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/templates/config.php';
date_default_timezone_set('America/Recife');

$dbLocal  = $connLocal;
$dbRemoto = $connRemoto;

$token = trim($_GET['access_dinamic'] ?? '');
if ($token === '') { http_response_code(401); exit('Token inválido ou expirado.'); }

$gId = 0; $nomePortal = null;
$sql = "
  SELECT ts.g_id, u.u_nome_completo
  FROM token_sessao ts
  LEFT JOIN users u ON u.g_id = ts.g_id
  WHERE ts.token = ?
  ORDER BY ts.id DESC
  LIMIT 1";
if ($st = $dbRemoto->prepare($sql)) {
  $st->bind_param('s', $token);
  $st->execute();
  if ($res = $st->get_result()) {
    if ($row = $res->fetch_assoc()) {
      $gId = (int)$row['g_id'];
      $nomePortal = $row['u_nome_completo'] ?? null;
    }
  }
  $st->close();
}
if ($gId <= 0) { http_response_code(401); exit('Token inválido ou expirado.'); }

$sqlLocal = "SELECT id, id_usuario_cehab_online, nome, setor
             FROM usuarios
             WHERE id_usuario_cehab_online = ?
             LIMIT 1";
$st2 = $dbLocal->prepare($sqlLocal);
$st2->bind_param('i', $gId);
$st2->execute();
$local = $st2->get_result()->fetch_assoc();
$st2->close();

$_SESSION['id_portal'] = $gId;
if ($local) {
  $_SESSION['tipo_usuario']            = 'colaborador';
  $_SESSION['id_usuario_local']        = (int)$local['id'];        // opcional
  $_SESSION['id_usuario_cehab_online'] = (int)$local['id_usuario_cehab_online'];
  $_SESSION['nome']                    = $local['nome'] ?: ($nomePortal ?? '');
  $_SESSION['setor']                   = $local['setor'] ?: '';
  $redirect = 'templates/home_setor.php?access_dinamic=' . urlencode($token);
} else {
  $_SESSION['tipo_usuario']            = 'solicitante';
  $_SESSION['id_usuario_local']        = null;
  $_SESSION['id_usuario_cehab_online'] = $gId;
  $_SESSION['nome']                    = $nomePortal ?? '';
  $_SESSION['setor']                   = 'Solicitante';
  $redirect = 'templates/home.php?access_dinamic=' . urlencode($token);
}

header('Location: ' . $redirect);
exit;
