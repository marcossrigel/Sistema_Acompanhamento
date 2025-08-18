<?php
require_once __DIR__ . '/config.php';
date_default_timezone_set('America/Recife');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die("ID inválido."); }

$access = $_GET['access_dinamic'] ?? '';

// Buscar a linha atual
$st = $connLocal->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$st->bind_param("i", $id);
$st->execute();
$linha = $st->get_result()->fetch_assoc();
$st->close();

if (!$linha) { die("Solicitação não encontrada."); }

// Atualiza data de liberação do setor atual
$connLocal->query("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = $id");

// Define a sequência dos setores
$fluxo = [
  'DEMANDANTE',
  'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS',
  'GECOMP',
  'DDO',
  'CPL',
  'HOMOLOGACAO',
  'PARECER JUR',
  'NE',
  'PF',
  'NE',
  'LIQ',
  'PD',
  'OB',
  'REMESSA'
];

// Encontra próximo setor
$atual = $linha['setor_responsavel'];
$idx = array_search($atual, $fluxo);
$proximo = $fluxo[$idx + 1] ?? null;

if (!$proximo) {
  echo "Não há próximo setor."; exit;
}

// Pega data de liberação da linha atual
$dataSolicitacao = date('Y-m-d');

// Inserir nova linha para o próximo setor
$sql = "INSERT INTO solicitacoes (
    id_usuario, demanda, sei, codigo, setor, responsavel,
    data_solicitacao, data_liberacao, tempo_medio, tempo_real,
    data_registro, setor_responsavel
) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, NOW(), ?)";

$stmt = $connLocal->prepare($sql);
$stmt->bind_param(
  "isssssssis",
  $linha['id_usuario'],
  $linha['demanda'],
  $linha['sei'],
  $linha['codigo'],
  $linha['setor'],
  $linha['responsavel'],
  $dataSolicitacao,
  $linha['tempo_medio'],
  $linha['tempo_real'],
  $proximo
);
$stmt->execute();
$stmt->close();

// Redireciona de volta ao painel com o token
header("Location: painel.php?access_dinamic=" . urlencode($access));
exit;
