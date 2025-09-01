<?php
$token = $_POST['access_dinamic'] ?? $_GET['access_dinamic'] ?? '';
if (!$token) die("Token de acesso ausente.");

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;
date_default_timezone_set('America/Recife');

$id_demanda    = (int)($_POST['id_demanda'] ?? 0);
$setor_destino = trim($_POST['setor_destino'] ?? '');
$setor_origem  = trim($_POST['setor_origem']  ?? 'INDEFINIDO');

if ($id_demanda <= 0 || $setor_destino === '') {
  die('Parâmetros inválidos.');
}

$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id_demanda);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orig) die('Solicitação não encontrada.');

$setorOriginal = $orig['setor_original'] ?: $orig['setor'];

$conn->begin_transaction();

try {
  // 1) Finaliza o(s) encaminhamento(s) em aberto do registro atual
  $fin = $conn->prepare("
    UPDATE encaminhamentos
       SET status = 'Finalizado'
     WHERE id_demanda = ?
       AND status = 'Em andamento'
  ");
  $fin->bind_param("i", $id_demanda);
  $fin->execute();
  $fin->close();

  // 2) Garante setor_original preenchido
  if (empty($orig['setor_original'])) {
    $fix = $conn->prepare("UPDATE solicitacoes SET setor_original = ? WHERE id = ?");
    $fix->bind_param("si", $setorOriginal, $id_demanda);
    $fix->execute();
    $fix->close();
  }

  // 3) Fecha a etapa atual com data de liberação
  $upd = $conn->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = ?");
  $upd->bind_param("i", $id_demanda);
  $upd->execute();
  $upd->close();

  // 4) Replica tempos
  $tempoMedio = (string)($orig['tempo_medio'] ?? '00:00:00');
  $tempoReal  = isset($orig['tempo_real']) ? (int)$orig['tempo_real'] : null;

  // 5) Cria a nova linha da solicitação no setor de destino
  $ins = $conn->prepare("
    INSERT INTO solicitacoes
      (id_usuario, demanda, sei, codigo, setor, setor_original, responsavel,
       data_solicitacao, data_liberacao, data_liberacao_original,
       tempo_medio, tempo_real, setor_responsavel)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, CURDATE(), NULL, ?, ?, ?, ?)
  ");
  $ins->bind_param(
    "issssssssis",
    $orig['id_usuario'],              // i
    $orig['demanda'],                 // s
    $orig['sei'],                     // s
    $orig['codigo'],                  // s
    $setor_destino,                   // s
    $setorOriginal,                   // s
    $orig['responsavel'],             // s
    $orig['data_liberacao_original'], // s
    $tempoMedio,                      // s
    $tempoReal,                       // i
    $setor_destino                    // s
  );
  $ins->execute();
  $novoId = $conn->insert_id;
  $ins->close();

  // 6) Abre o novo encaminhamento (Em andamento) para o novo id
  $enc = $conn->prepare("
    INSERT INTO encaminhamentos
      (id_demanda, setor_origem, setor_destino, status, data_encaminhamento)
    VALUES
      (?, ?, ?, 'Em andamento', NOW())
  ");
  $enc->bind_param("iss", $novoId, $setor_origem, $setor_destino);
  $enc->execute();
  $enc->close();

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  die('Falha ao encaminhar: ' . $e->getMessage());
}

header("Location: painel.php?access_dinamic=" . urlencode($token));
exit;
