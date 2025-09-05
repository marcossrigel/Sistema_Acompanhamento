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

// ✅ CAPTURA os campos da GECOMP (se vierem do form)
$tr      = isset($_POST['gecomp_tr'])      ? (int)$_POST['gecomp_tr']      : null;
$etp     = isset($_POST['gecomp_etp'])     ? (int)$_POST['gecomp_etp']     : null;
$cotacao = isset($_POST['gecomp_cotacao']) ? (int)$_POST['gecomp_cotacao'] : null;
$obs     = $_POST['gecomp_obs'] ?? null;

$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id_demanda);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$orig) die('Solicitação não encontrada.');

$setorOriginal = $orig['setor_original'] ?: $orig['setor'];
$rootId        = (int)($orig['id_original'] ?: $id_demanda);

$conn->begin_transaction();

try {
  // 1) Finaliza encaminhamento em aberto da RAIZ (se houver)
  $fin = $conn->prepare("
    UPDATE encaminhamentos
       SET status = 'Finalizado'
     WHERE id_demanda = ?
       AND status = 'Em andamento'
  ");
  $fin->bind_param("i", $rootId);
  $fin->execute();
  $fin->close();

  // 2) Fecha a etapa ATUAL (se ainda não fechada)
  $upd = $conn->prepare("
    UPDATE solicitacoes
       SET data_liberacao = NOW()
     WHERE id = ?
       AND data_liberacao IS NULL
  ");
  $upd->bind_param("i", $id_demanda);
  $upd->execute();
  $upd->close();

  // ✅ 2.1) (FALLBACK) Se a origem for GECOMP, persiste gecomp_* na ETAPA ENCERRADA
  if (strcasecmp(trim($setor_origem), 'GECOMP') === 0) {
  $updG = $conn->prepare("
    UPDATE solicitacoes
       SET gecomp_tr      = ?,
           gecomp_etp     = ?,
           gecomp_cotacao = ?,
           gecomp_obs     = ?
     WHERE id = ?
  ");
  $tr      = (int)($_POST['gecomp_tr'] ?? 0);
  $etp     = (int)($_POST['gecomp_etp'] ?? 0);
  $cotacao = (int)($_POST['gecomp_cotacao'] ?? 0);
  $obs     = (string)($_POST['gecomp_obs'] ?? '');
  $updG->bind_param('iiisi', $tr, $etp, $cotacao, $obs, $id_demanda);
  $updG->execute();
  $updG->close();
}

  // 3) Replica tempos (garantindo tipos)
  $tempoMedio = (string)($orig['tempo_medio'] ?? '00:00:00'); // HH:MM:SS
  $tempoReal  = isset($orig['tempo_real']) ? (int)$orig['tempo_real'] : null;

  // 4) Cria a NOVA ETAPA (em andamento)
  $ins = $conn->prepare("
    INSERT INTO solicitacoes
      (id_usuario, demanda, sei, codigo, setor, setor_original, responsavel,
       data_solicitacao, data_liberacao, data_liberacao_original,
       tempo_medio, tempo_real, setor_responsavel, id_original)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, NOW(), NULL, ?, ?, ?, ?, ?)
  ");
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

  // 5) Novo encaminhamento na RAIZ
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
