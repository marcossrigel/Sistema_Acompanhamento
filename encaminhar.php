<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;
date_default_timezone_set('America/Recife');

$id    = (int)($_GET['id'] ?? 0);
$token = $_GET['access_dinamic'] ?? '';
if ($id <= 0) { header('Location: painel.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orig) { die('Solicitação não encontrada.'); }

$mapaProximo = [
  'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS' => 'GECOMP',
  'GECOMP'      => 'DDO',
  'DDO'         => 'LICITACAO',
  'LICITACAO'   => 'HOMOLOGACAO',
  'HOMOLOGACAO' => 'PARECER JUR',
  'PARECER JUR' => 'NE',
  'NE'          => 'PF',
  'NE'          => 'LIQ',
  'LIQ'         => 'PD',
  'PD'          => 'OB',
  'OB'          => 'REMESSA'
];

$atual   = $orig['setor_responsavel'];
$proximo = $mapaProximo[$atual] ?? null;
if (!$proximo) { die('Não há próximo setor configurado para: ' . $atual); }

$conn->begin_transaction();

try {
  $stmt = $conn->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = ?");
  $stmt->bind_param("i", $id);
  if (!$stmt->execute()) throw new Exception($stmt->error);

  $sql = "INSERT INTO solicitacoes (
            id_usuario, demanda, sei, codigo, setor, responsavel,
            data_solicitacao, data_liberacao, tempo_medio, tempo_real,
            data_registro, setor_responsavel
          ) VALUES (
            ?, ?, ?, ?, ?, ?, CURDATE(), NULL, ?, ?, NOW(), ?
          )";

  $stmt = $conn->prepare($sql);

  $idUsuario         = (int)$orig['id_usuario'];
  $demanda           = $orig['demanda'];
  $sei               = $orig['sei'];
  $codigo            = $orig['codigo'];
  $setor             = $orig['setor'];
  $responsavel       = $orig['responsavel'];
  $tempo_medio       = $orig['tempo_medio'];
  $tempo_real        = 0;
  $setor_responsavel = $proximo;

  $stmt->bind_param(
    "issssssis",
    $idUsuario,
    $demanda,
    $sei,
    $codigo,
    $setor,
    $responsavel,
    $tempo_medio,
    $tempo_real,
    $setor_responsavel
  );

  if (!$stmt->execute()) throw new Exception($stmt->error);

  $conn->commit();
  header("Location: painel.php?access_dinamic=" . urlencode($token));
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  die('Erro ao encaminhar: ' . $e->getMessage());
}
