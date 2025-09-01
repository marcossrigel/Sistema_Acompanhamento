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

// depois de buscar $orig:
$setorOriginal = $orig['setor_original'] ?: $orig['setor'];
$rootId = (int)($orig['id_original'] ?: $id_demanda); // <-- id da demanda raiz

$conn->begin_transaction();

try {
  // Finaliza o encaminhamento aberto da RAIZ
  $fin = $conn->prepare("
    UPDATE encaminhamentos
       SET status = 'Finalizado'
     WHERE id_demanda = ?
       AND status = 'Em andamento'
  ");
  $fin->bind_param("i", $rootId);
  $fin->execute();
  $fin->close();

  // Fecha a etapa atual
  $upd = $conn->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = ?");
  $upd->bind_param("i", $id_demanda);
  $upd->execute();
  $upd->close();

  // Replica tempos
  $tempoMedio = (string)($orig['tempo_medio'] ?? '00:00:00');
  $tempoReal  = isset($orig['tempo_real']) ? (int)$orig['tempo_real'] : null;

  // NOVA ETAPA (repare em id_original na lista de colunas)
  $ins = $conn->prepare("
    INSERT INTO solicitacoes
      (id_usuario, demanda, sei, codigo, setor, setor_original, responsavel,
       data_solicitacao, data_liberacao, data_liberacao_original,
       tempo_medio, tempo_real, setor_responsavel, id_original)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, CURDATE(), NULL, ?, ?, ?, ?, ?)
  ");

  // 12 variáveis  →  tipos: i s s s s s s s s i s i  => "issssssssisi"
  $ins->bind_param(
    "issssssssisi",
    $orig['id_usuario'],
    $orig['demanda'],
    $orig['sei'],
    $orig['codigo'],
    $setor_destino,
    $setorOriginal,
    $orig['responsavel'],
    $orig['data_liberacao_original'],
    $tempoMedio,
    $tempoReal,
    $setor_destino,
    $rootId
  );
  $ins->execute();
  $ins->close();

  // Novo encaminhamento ABERTO apontando para a RAIZ
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

header("Location: painel.php?access_dinamic=".urlencode($token));
exit;
