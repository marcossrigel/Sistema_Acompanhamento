<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$numero = trim($payload['numero'] ?? '');
$setor  = trim($payload['setor']  ?? '');

if ($numero === '') { echo json_encode(['ok'=>false,'err'=>'numero_vazio']); exit; }

/* config */
$cfgCandidates = [ __DIR__.'/config.php', __DIR__.'/../templates/config.php', __DIR__.'/../config.php' ];
foreach ($cfgCandidates as $p) { if (file_exists($p)) { require_once $p; break; } }

$driver = (isset($pdo) && $pdo instanceof PDO) ? 'pdo' : ((isset($conexao) && $conexao instanceof mysqli) ? 'mysqli' : null);
if (!$driver) { echo json_encode(['ok'=>false,'err'=>'db_handle_missing']); exit; }

try {
  // processo
  if ($driver === 'pdo') {
    $sql = "SELECT id, numero_processo, setor_demandante, enviar_para, tipos_processo_json, tipo_outros, descricao, data_registro
            FROM novo_processo
            WHERE numero_processo = :num".($setor!==''?" AND setor_demandante LIKE :setor":'')." LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->bindValue(':num', $numero);
    if ($setor!=='') $st->bindValue(':setor', '%'.$setor.'%');
    $st->execute();
    $row = $st->fetch();
  } else {
    $num = $conexao->real_escape_string($numero);
    $sql = "SELECT id, numero_processo, setor_demandante, enviar_para, tipos_processo_json, tipo_outros, descricao, data_registro
            FROM novo_processo
            WHERE numero_processo = '$num'".
            ($setor!=='' ? " AND setor_demandante LIKE '%".$conexao->real_escape_string($setor)."%'" : '').
            " LIMIT 1";
    $res = $conexao->query($sql);
    if (!$res) throw new RuntimeException('MySQLi: '.$conexao->error);
    $row = $res->fetch_assoc();
  }

  if (!$row) { echo json_encode(['ok'=>true,'registro'=>null,'fluxo'=>[]]); exit; }

  // fluxo
  $pid = (int)$row['id'];
  if ($driver === 'pdo') {
    $st2 = $pdo->prepare("SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
                          FROM processo_fluxo WHERE processo_id = :pid ORDER BY ordem ASC");
    $st2->execute([':pid'=>$pid]);
    $fluxo = $st2->fetchAll();
  } else {
    $res2 = $conexao->query("SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
                             FROM processo_fluxo WHERE processo_id = $pid ORDER BY ordem ASC");
    if (!$res2) throw new RuntimeException('MySQLi: '.$conexao->error);
    $fluxo = $res2->fetch_all(MYSQLI_ASSOC);
  }

  // tipos + campos do front
  $tipos = [];
  if (!empty($row['tipos_processo_json'])) { $tmp = json_decode($row['tipos_processo_json'], true); if (is_array($tmp)) $tipos=$tmp; }
  $tiposStr = $tipos ? implode(', ',$tipos) : '—';
  if (!empty($row['tipo_outros'])) { $tiposStr = ($tiposStr==='—'?'':$tiposStr.' | ').'Outros: '.$row['tipo_outros']; if ($tiposStr==='') $tiposStr='—'; }

  $row['setor_destino'] = $row['enviar_para'];
  $row['tipos']         = $tiposStr;
  $row['criado_em']     = $row['data_registro'];

  echo json_encode(['ok'=>true,'registro'=>$row,'fluxo'=>$fluxo], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'err'=>'db_error','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
