<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;
date_default_timezone_set('America/Recife');

$id    = (int)($_GET['id'] ?? 0);
$token = $_GET['access_dinamic'] ?? '';
if ($id <= 0) { header('Location: painel.php'); exit; }

// 1) Carrega a linha original
$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$orig) { die('Solicitação não encontrada.'); }

// 2) Define o próximo setor
$mapaProximo = [
  'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS' => 'GECOMP',
  'GECOMP'      => 'DDO',
  'DDO'         => 'LICITACAO',
  'LICITACAO'   => 'HOMOLOGACAO',
  'HOMOLOGACAO' => 'PARECER JUR',
  'PARECER JUR' => 'NE',
  'NE'          => 'PF',
  'PF'          => 'NE',   // atenção: chave repetida “NE” pode causar ambiguidade
  'NE'          => 'LIQ',  // esta entrada sobrescreve a anterior em arrays PHP
  'LIQ'         => 'PD',
  'PD'          => 'OB',
  'OB'          => 'REMESSA'
];

$atual   = $orig['setor_responsavel'];
$proximo = $mapaProximo[$atual] ?? null;
if (!$proximo) { die('Não há próximo setor configurado para: ' . $atual); }

$conn->begin_transaction();

try {
  // 3) Concluir a linha atual (marca liberação hoje)
  $stmt = $conn->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = ?");
  $stmt->bind_param("i", $id);
  if (!$stmt->execute()) throw new Exception($stmt->error);

  // 4) Criar a nova linha para o próximo setor (recebe hoje, liberação NULL)
  $sql = "INSERT INTO solicitacoes (
            id_usuario, demanda, sei, codigo, setor, responsavel,
            data_solicitacao, data_liberacao, tempo_medio, tempo_real,
            data_registro, setor_responsavel
          ) VALUES (
            ?, ?, ?, ?, ?, ?, CURDATE(), NULL, ?, ?, NOW(), ?
          )";

  $stmt = $conn->prepare($sql);

  $idUsuario        = (int)$orig['id_usuario'];
  $demanda          = $orig['demanda'];
  $sei              = $orig['sei'];
  $codigo           = $orig['codigo'];
  $setor            = $orig['setor'];          // setor de origem permanece
  $responsavel      = $orig['responsavel'];
  $tempo_medio      = $orig['tempo_medio'];    // mantém
  $tempo_real       = null;                    // zera para o novo ciclo (NULL)
  $setor_responsavel= $proximo;                // quem trabalha agora

  // 9 placeholders => 1 int + 8 strings (tempo_real é int/NULL)
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
