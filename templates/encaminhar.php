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

// pegue o data_registro da RAIZ (não do registro atual)
$rootDataRegistro = $orig['data_registro'];
if ($rootId !== (int)$orig['id']) {
  $qdr = $conn->prepare("SELECT data_registro FROM solicitacoes WHERE id = ? LIMIT 1");
  $qdr->bind_param("i", $rootId);
  $qdr->execute();
  if ($tmp = $qdr->get_result()->fetch_assoc()) {
    $rootDataRegistro = $tmp['data_registro'];
  }
  $qdr->close();
}

$conn->begin_transaction();

try {
  $fin = $conn->prepare("
    UPDATE encaminhamentos
       SET status = 'Finalizado'
     WHERE id_demanda = ?
       AND status = 'Em andamento'
  ");
  $fin->bind_param("i", $rootId);
  $fin->execute();
  $fin->close();

  $upd = $conn->prepare("
  UPDATE solicitacoes
     SET data_liberacao = CURDATE(),
         hora_liberacao = CURTIME(),
         tempo_real     = GREATEST(DATEDIFF(CURDATE(), data_solicitacao), 0)
   WHERE id = ?
     AND data_liberacao IS NULL
");
$upd->bind_param("i", $id_demanda);
$upd->execute();
$upd->close();

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

  $tempoMedio = (string)($orig['tempo_medio'] ?? '00:00:00');
  $tempoReal  = isset($orig['tempo_real']) ? (int)$orig['tempo_real'] : null;

  $ins = $conn->prepare("
  INSERT INTO solicitacoes
    (id_usuario, demanda, sei, codigo, setor, setor_original, responsavel,
     data_solicitacao, hora_solicitacao,
     data_liberacao, hora_liberacao, data_liberacao_original,
     tempo_medio, tempo_real, setor_responsavel, id_original, data_registro)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(),
     NULL, NULL, ?, ?, ?, ?, ?, ?)
");

  // Tipos (13 params): i + s*8 + i + s + i + s  => "issssssssisis"
  $ins->bind_param(
    "issssssssisis",
    $orig['id_usuario'],            // i
    $orig['demanda'],               // s
    $orig['sei'],                   // s
    $orig['codigo'],                // s
    $setor_destino,                 // s
    $setorOriginal,                 // s
    $orig['responsavel'],           // s
    $orig['data_liberacao_original'], // s (pode ser NULL)
    $tempoMedio,                    // s
    $tempoReal,                     // i (pode ser NULL)
    $setor_destino,                 // s
    $rootId,                        // i
    $rootDataRegistro               // s  <<< mantém a data/hora original
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
