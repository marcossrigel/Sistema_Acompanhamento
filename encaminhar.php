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

// Busca a linha atual da solicitação (na GECOMP)
$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id_demanda);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orig) die('Solicitação não encontrada.');

$conn->begin_transaction();

try {
  // 1) Liberar a linha atual (preenche a data_liberacao da GECOMP)
  $upd = $conn->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = ?");
  $upd->bind_param("i", $id_demanda);
  $upd->execute();
  $upd->close();

  // Preparar tipos corretos
  $tempoMedio = (string)($orig['tempo_medio'] ?? '00:00:00'); // TIME como string
  $tempoReal  = (int)($orig['tempo_real']  ?? 0);

  // 2) Criar nova linha para o setor de destino
  $ins = $conn->prepare("
    INSERT INTO solicitacoes
      (id_usuario, demanda, sei, codigo, setor, responsavel,
       data_solicitacao, data_liberacao, tempo_medio, tempo_real, setor_responsavel)
    VALUES
      (?, ?, ?, ?, ?, ?, CURDATE(), NULL, ?, ?, ?)
  ");
  // 9 placeholders -> tipos: i s s s s s s i s
  $ins->bind_param(
    "issssssis",
    $orig['id_usuario'],   // i
    $orig['demanda'],      // s
    $orig['sei'],          // s
    $orig['codigo'],       // s
    $setor_destino,        // s
    $orig['responsavel'],  // s
    $tempoMedio,           // s (TIME)
    $tempoReal,            // i
    $setor_destino         // s (setor_responsavel)
  );
  $ins->execute();
  $novoId = $conn->insert_id;
  $ins->close();

  // 3) Registrar o encaminhamento apontando para a NOVA linha
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
