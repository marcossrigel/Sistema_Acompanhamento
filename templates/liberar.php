<?php
require_once __DIR__ . '/config.php';
date_default_timezone_set('America/Recife');

$id = (int)($_GET['id'] ?? 0);        // id da ETAPA atual
if ($id <= 0) die("ID inválido.");
$access = $_GET['access_dinamic'] ?? '';

$st = $connLocal->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$st->bind_param("i", $id);
$st->execute();
$origem = $st->get_result()->fetch_assoc();
$st->close();
if (!$origem) die("Registro não encontrado.");

$proximo = $_GET['setor_destino'] ?? null;
if (!$proximo) {
  function proximoSetor($setorAtual){ $fluxo=['DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS','GECOMP','DDO','CPL','DAF - HOMOLOGACAO','PARECER JUR','GEFIN NE INICIAL','GOP PF (SEFAZ)','GEFIN NE DEFINITIVO','LIQ','PD (SEFAZ)','OB','REMESSA']; $i=array_search($setorAtual,$fluxo,true); return ($i!==false && isset($fluxo[$i+1]))? $fluxo[$i+1]:null; }
  $proximo = proximoSetor($origem['setor']);
}

$connLocal->begin_transaction();
try {
  $rootId = (int)($origem['id_original'] ?: $id);

  // finaliza encaminhamento aberto da RAIZ
  $fin = $connLocal->prepare("
    UPDATE encaminhamentos
       SET status = 'Finalizado'
     WHERE id_demanda = ?
       AND status = 'Em andamento'
  ");
  $fin->bind_param("i", $rootId);
  $fin->execute();
  $fin->close();

  // fecha etapa atual
  $upd = $connLocal->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = ?");
  $upd->bind_param("i", $id);
  $upd->execute();
  $upd->close();

  // recarrega etapa atualizada (agora com data_liberacao)
  $st2 = $connLocal->prepare("SELECT * FROM solicitacoes WHERE id = ?");
  $st2->bind_param("i", $id);
  $st2->execute();
  $origemAtualizada = $st2->get_result()->fetch_assoc();
  $st2->close();

  if ($proximo) {
    // ... depois de recarregar $origemAtualizada:

$rootId = (int)($origemAtualizada['id_original'] ?: $id);
$setorOriginal   = $origemAtualizada['setor_original'] ?: $origemAtualizada['setor'];
$dataSolicitacao = $origemAtualizada['data_liberacao'];
$dataLibOriginal = $origemAtualizada['data_liberacao_original'];
$tempoMedio = (string)($origemAtualizada['tempo_medio'] ?? '00:00:00');
$tempoReal  = isset($origemAtualizada['tempo_real']) ? (int)$origemAtualizada['tempo_real'] : null;

$ins = $connLocal->prepare("
  INSERT INTO solicitacoes
    (id_usuario, demanda, sei, codigo,
     setor, setor_original, responsavel,
     data_solicitacao, data_liberacao, data_liberacao_original,
     tempo_medio, tempo_real, setor_responsavel, id_original)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)
");

# 13 variáveis → tipos: i s s s s s s s s s i s i  => "isssssssssisi"
$ins->bind_param(
  "isssssssssisi",
  $origemAtualizada['id_usuario'],
  $origemAtualizada['demanda'],
  $origemAtualizada['sei'],
  $origemAtualizada['codigo'],
  $proximo,
  $setorOriginal,
  $origemAtualizada['responsavel'],
  $dataSolicitacao,
  $dataLibOriginal,
  $tempoMedio,
  $tempoReal,
  $proximo,
  $rootId
);
$ins->execute();
$ins->close();

  $connLocal->commit();
} catch (Throwable $e) {
  $connLocal->rollback();
  die("Falha ao encaminhar: ".$e->getMessage());
}

header("Location: painel.php?access_dinamic=".urlencode($access));
exit;
