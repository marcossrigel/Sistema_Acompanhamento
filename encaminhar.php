<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
$conn = $connLocal;
date_default_timezone_set('America/Recife');

$id = (int)($_GET['id'] ?? 0);
$token = $_GET['access_dinamic'] ?? '';
if ($id <= 0) { header('Location: painel.php'); exit; }

// 1) Carrega linha original
$stmt = $conn->prepare("SELECT * FROM solicitacoes WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$orig = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$orig){ die('Solicitação não encontrada.'); }

// 2) Descobre próximo setor (ajuste a regra conforme seu fluxo)
$mapaProximo = [
  'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS' => 'GECOMP',
  'GECOMP' => 'DDO',
  // ...
];
$atual = $orig['setor_responsavel'];
$proximo = $mapaProximo[$atual] ?? null;
if(!$proximo){
  die('Não há próximo setor configurado para: '.$atual);
}

$conn->begin_transaction();

try {
  // 3) Libera a linha atual (data_liberacao = hoje)
  $stmt = $conn->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id=?");
  $stmt->bind_param("i",$id);
  if(!$stmt->execute()) throw new Exception($stmt->error);

  // 4) Cria nova linha para o próximo setor (recebendo hoje)
  $sql = "INSERT INTO solicitacoes
          (demanda, sei, codigo, setor, responsavel,
           data_solicitacao, data_liberacao, tempo_medio, tempo_real,
           setor_responsavel, data_registro)
          VALUES (?,?,?,?,?, CURDATE(), NULL, ?, ?, ?, NOW())";
  $stmt = $conn->prepare($sql);
  $tempoMedio = $orig['tempo_medio'];      // mantém
  $tempoReal  = null;                       // zera p/ novo ciclo
  $setorResp  = $proximo;                   // agora quem trabalha é o próximo
  $stmt->bind_param("ssssssss",
    $orig['demanda'],
    $orig['sei'],
    $orig['codigo'],
    $orig['setor'],         // setor de origem permanece
    $orig['responsavel'],
    $tempoMedio,
    $tempoReal,
    $setorResp
  );
  if(!$stmt->execute()) throw new Exception($stmt->error);

  $conn->commit();

  header("Location: painel.php?access_dinamic=".urlencode($token));
  exit;

} catch(Throwable $e){
  $conn->rollback();
  die('Erro ao encaminhar: '.$e->getMessage());
}
