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

if ($id_demanda <= 0 || $setor_destino === '') die('Parâmetros inválidos.');

$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id_demanda);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$orig) die('Solicitação não encontrada.');

// id raiz do processo e setor "original" de referência
$setorOriginal = $orig['setor_original'] ?: $orig['setor'];
$rootId        = (int)($orig['id_original'] ?: $id_demanda);

$conn->begin_transaction();

try {
  // 1) Finaliza o encaminhamento em aberto da RAIZ (se houver)
  $fin = $conn->prepare("
    UPDATE encaminhamentos
       SET status = 'Finalizado'
     WHERE id_demanda = ?
       AND status = 'Em andamento'
  ");
  $fin->bind_param("i", $rootId);
  $fin->execute();
  $fin->close();

  // 2) Fecha a etapa ATUAL (somente se ainda não fechada)
  $upd = $conn->prepare("
    UPDATE solicitacoes
       SET data_liberacao = NOW()
     WHERE id = ?
       AND data_liberacao IS NULL
  ");
  $upd->bind_param("i", $id_demanda);
  $upd->execute();
  $upd->close();

  // 3) Replica tempos (garantindo tipos)
  $tempoMedio = (string)($orig['tempo_medio'] ?? '00:00:00'); // HH:MM:SS
  $tempoReal  = isset($orig['tempo_real']) ? (int)$orig['tempo_real'] : null;

  // 4) Cria a NOVA ETAPA (data_liberacao = NULL => EM ANDAMENTO)
  $ins = $conn->prepare("
    INSERT INTO solicitacoes
      (id_usuario, demanda, sei, codigo, setor, setor_original, responsavel,
       data_solicitacao, data_liberacao, data_liberacao_original,
       tempo_medio, tempo_real, setor_responsavel, id_original)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, NOW(), NULL, ?, ?, ?, ?, ?)
  ");
  // Tipos: i + 8s + i + s + i  => "issssssssisi"
  $ins->bind_param(
    "issssssssisi",
    $orig['id_usuario'],          // i
    $orig['demanda'],             // s
    $orig['sei'],                 // s
    $orig['codigo'],              // s
    $setor_destino,               // s
    $setorOriginal,               // s
    $orig['responsavel'],         // s
    $orig['data_liberacao_original'], // s (pode ser NULL)
    $tempoMedio,                  // s
    $tempoReal,                   // i (pode ser NULL)
    $setor_destino,               // s (setor_responsavel)
    $rootId                       // i (id_original)
  );
  $ins->execute();
  $ins->close();

  // 5) Abre um novo encaminhamento na RAIZ
  $enc = $conn->prepare("
    INSERT INTO encaminhamentos
      (id_demanda, setor_origem, setor_destino, status, data_encaminhamento)
    VALUES (?, ?, ?, 'Em andamento', NOW())
  ");
  $enc->bind_param("iss", $rootId, $setor_origem, $setor_destino);
  $enc->execute();
  $enc->close();

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  die('Falha ao encaminhar: ' . $e->getMessage());
}

header("Location: painel.php?access_dinamic=" . urlencode($token));
exit;
