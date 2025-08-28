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

// 1) Busca a linha atual
$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id_demanda);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orig) die('Solicitação não encontrada.');

// AGORA sim podemos definir o setor_original
$setorOriginal = $orig['setor_original'] ?: $orig['setor'];

$conn->begin_transaction();

try {
  // (opcional, mas recomendado) garante que a linha atual também tenha setor_original
  if (empty($orig['setor_original'])) {
    $fix = $conn->prepare("UPDATE solicitacoes SET setor_original = ? WHERE id = ?");
    $fix->bind_param("si", $setorOriginal, $id_demanda);
    $fix->execute();
    $fix->close();
  }

  // 2) Libera a linha atual
  $upd = $conn->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = ?");
  $upd->bind_param("i", $id_demanda);
  $upd->execute();
  $upd->close();

  // 3) Cria a nova linha no setor de destino (propagando setor_original)
  // 3) Cria a nova linha no setor de destino (propagando setor_original e data_liberacao_original)
  $tempoMedio = (string)($orig['tempo_medio'] ?? '00:00:00');
  $tempoReal  = isset($orig['tempo_real']) ? (int)$orig['tempo_real'] : null;

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
    $orig['id_usuario'],                 // i
    $orig['demanda'],                    // s
    $orig['sei'],                        // s
    $orig['codigo'],                     // s
    $setor_destino,                      // s
    $setorOriginal,                      // s
    $orig['responsavel'],                // s
    $orig['data_liberacao_original'],    // s  <-- replica a original
    $tempoMedio,                         // s
    $tempoReal,                          // i
    $setor_destino                       // s
  );

  $ins->execute();
  $novoId = $conn->insert_id;
  $ins->close();

  // 4) Encaminhamento
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
