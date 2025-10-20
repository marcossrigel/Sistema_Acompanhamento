<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'err'=>'unauthorized']); exit; }
if (($_SESSION['tipo'] ?? '') !== 'admin')                   { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'forbidden']);    exit; }

/* carrega config e pega $pdo/$conexao */
$cfgCandidates = [ __DIR__.'/config.php', __DIR__.'/../templates/config.php', __DIR__.'/../config.php' ];
foreach ($cfgCandidates as $p) { if (file_exists($p)) { require_once $p; break; } }

$driver = (isset($pdo) && $pdo instanceof PDO) ? 'pdo' : ((isset($conexao) && $conexao instanceof mysqli) ? 'mysqli' : null);
if (!$driver) { http_response_code(500); echo json_encode(['ok'=>false,'err'=>'db_handle_missing']); exit; }

try {
  // (1) Processos
  if ($driver === 'pdo') {
    $procs = $pdo->query("
      SELECT id, numero_processo, nome_processo, setor_demandante, enviar_para,
             tipos_processo_json, tipo_outros, descricao, data_registro
      FROM novo_processo
      ORDER BY id DESC
      LIMIT 500
    ")->fetchAll();
  } else {
    $res = $conexao->query("
      SELECT id, numero_processo, nome_processo, setor_demandante, enviar_para,
             tipos_processo_json, tipo_outros, descricao, data_registro
      FROM novo_processo
      ORDER BY id DESC
      LIMIT 500
    ");
    if (!$res) throw new RuntimeException('MySQLi: '.$conexao->error);
    $procs = $res->fetch_all(MYSQLI_ASSOC);
  }

  if (!$procs) { echo json_encode(['ok'=>true,'processos'=>[]]); exit; }

  // (2) Fluxo
  $ids = array_values(array_filter(array_map('intval', array_column($procs,'id')), fn($v)=>$v>0));
  $rowsFluxo = [];
  if ($ids) {
    if ($driver === 'pdo') {
      $in = implode(',', array_fill(0,count($ids),'?'));
      $st = $pdo->prepare("
        SELECT processo_id, ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
        FROM processo_fluxo
        WHERE processo_id IN ($in)
        ORDER BY processo_id ASC, ordem ASC
      ");
      $st->execute($ids);
      $rowsFluxo = $st->fetchAll();
    } else {
      $in = implode(',', $ids);
      $sql = "
        SELECT processo_id, ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
        FROM processo_fluxo
        WHERE processo_id IN ($in)
        ORDER BY processo_id ASC, ordem ASC
      ";
      $resF = $conexao->query($sql);
      if (!$resF) throw new RuntimeException('MySQLi: '.$conexao->error);
      $rowsFluxo = $resF->fetch_all(MYSQLI_ASSOC);
    }
  }

  $byProc = [];
  foreach ($rowsFluxo as $r) {
    if (!empty($r['setor']) && ($pos = strpos($r['setor'],' - ')) !== false) { $r['setor'] = substr($r['setor'],0,$pos); }
    $byProc[(int)$r['processo_id']][] = $r;
  }

  // (3) Saída
  $out = [];
  foreach ($procs as $p) {
    $tipos = [];
    if (!empty($p['tipos_processo_json'])) { $tmp = json_decode($p['tipos_processo_json'], true); if (is_array($tmp)) $tipos=$tmp; }
    $tiposStr = $tipos ? implode(', ',$tipos) : '—';
    if (!empty($p['tipo_outros'])) { $tiposStr = ($tiposStr==='—'?'':$tiposStr.' | ').'Outros: '.$p['tipo_outros']; if ($tiposStr==='') $tiposStr='—'; }

    $p['setor_destino'] = $p['enviar_para'];
    $p['tipos']         = $tiposStr;
    $p['criado_em']     = $p['data_registro'];

    $out[] = ['registro'=>$p, 'fluxo'=>$byProc[(int)$p['id']] ?? []];
  }

  echo json_encode(['ok'=>true,'processos'=>$out], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'err'=>'db_error','msg'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
