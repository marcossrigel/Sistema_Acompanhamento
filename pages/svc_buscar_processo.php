<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

$numero = trim($payload['numero'] ?? '');
$setor  = trim($payload['setor']  ?? '');

if ($numero === '') {
  echo json_encode(['ok' => false, 'err' => 'numero_vazio']);
  exit;
}

try {
  $pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=sistema_acompanhamento;charset=utf8mb4',
    'root',
    '',
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );

  $sql = "SELECT id,
                 numero_processo,
                 setor_demandante,
                 enviar_para,
                 tipos_processo_json,
                 tipo_outros,
                 descricao,
                 data_registro
          FROM novo_processo
          WHERE numero_processo = :num";
  if ($setor !== '') {
    $sql .= " AND setor_demandante LIKE :setor";
  }
  $sql .= " LIMIT 1";

  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':num', $numero);
  if ($setor !== '') $stmt->bindValue(':setor', "%{$setor}%");
  $stmt->execute();
  $row = $stmt->fetch();

  if (!$row) {
    echo json_encode(['ok'=>true, 'registro'=>null, 'fluxo'=>[]]);
    exit;
  }

  $processoId = $row['id'];

  $stmt2 = $pdo->prepare("
      SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
      FROM processo_fluxo
      WHERE processo_id = :pid
      ORDER BY ordem ASC
  ");
  $stmt2->execute([':pid' => $processoId]);
  $fluxo = $stmt2->fetchAll();

  /* === Ajustes de campos === */
  $tipos = [];
  if (!empty($row['tipos_processo_json'])) {
    $tipos = json_decode($row['tipos_processo_json'], true);
  }
  $tiposStr = $tipos ? implode(', ', $tipos) : '—';
  if (!empty($row['tipo_outros'])) {
    $tiposStr = ($tiposStr === '—' ? '' : $tiposStr.' | ').'Outros: '.$row['tipo_outros'];
  }
  if ($tiposStr === '') $tiposStr = '—';

  // Renomeia para bater com o front-end
  $row['setor_destino'] = $row['enviar_para'];
  $row['tipos'] = $tiposStr;
  $row['criado_em'] = $row['data_registro'];

  echo json_encode(['ok'=>true,'registro'=>$row,'fluxo'=>$fluxo]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false,'err'=>'db_error','msg'=>$e->getMessage()]);
}
