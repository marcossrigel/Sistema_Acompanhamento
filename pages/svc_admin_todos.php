<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'err'=>'unauthorized']);
  exit;
}
if (($_SESSION['tipo'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'err'=>'forbidden']);
  exit;
}

try {
  $pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=sistema_acompanhamento;charset=utf8mb4',
    'root','',
    [ PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC ]
  );

  // Busca TODOS os processos (ordem: mais recentes primeiro)
  $procs = $pdo->query("
    SELECT id, numero_processo, setor_demandante, enviar_para,
           tipos_processo_json, tipo_outros, descricao, data_registro
    FROM novo_processo
    ORDER BY data_registro DESC
  ")->fetchAll();

  if (!$procs) {
    echo json_encode(['ok'=>true,'processos'=>[]]);
    exit;
  }

  // Coleta IDs e puxa todos os fluxos de uma vez
  $ids = array_column($procs, 'id');
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st  = $pdo->prepare("
    SELECT processo_id, ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
    FROM processo_fluxo
    WHERE processo_id IN ($in)
    ORDER BY processo_id ASC, ordem ASC
  ");
  $st->execute($ids);
  $rowsFluxo = $st->fetchAll();

  // Agrupa fluxos por processo_id
  $byProc = [];
  foreach ($rowsFluxo as $r) {
    // mantém apenas sigla do setor (antes do " - ")
    if (!empty($r['setor']) && strpos($r['setor'], ' - ') !== false) {
      $r['setor'] = substr($r['setor'], 0, strpos($r['setor'], ' - '));
    }
    $byProc[$r['processo_id']][] = $r;
  }

  // Monta resposta com "tipos", "criado_em" etc. (padroniza com o front)
  $out = [];
  foreach ($procs as $p) {
    $tipos = [];
    if (!empty($p['tipos_processo_json'])) {
      $tmp = json_decode($p['tipos_processo_json'], true);
      if (is_array($tmp)) $tipos = $tmp;
    }
    $tiposStr = $tipos ? implode(', ', $tipos) : '—';
    if (!empty($p['tipo_outros'])) {
      $tiposStr = ($tiposStr === '—' ? '' : $tiposStr.' | ').'Outros: '.$p['tipo_outros'];
    }
    if ($tiposStr === '') $tiposStr = '—';

    $p['setor_destino'] = $p['enviar_para'];
    $p['tipos']         = $tiposStr;
    $p['criado_em']     = $p['data_registro'];

    $out[] = [
      'registro' => $p,
      'fluxo'    => $byProc[$p['id']] ?? []
    ];
  }

  echo json_encode(['ok'=>true,'processos'=>$out]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'err'=>'db_error','msg'=>$e->getMessage()]);
}
