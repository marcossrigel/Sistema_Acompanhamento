<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');
header('Content-Type: application/json; charset=utf-8');

// carrega config (compatível com seu padrão antigo)
$cfgPath = __DIR__ . '/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/../config.php'; }
if (!file_exists($cfgPath)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Config não encontrada'], JSON_UNESCAPED_UNICODE);
  exit;
}
require_once $cfgPath;

// exige login
if (empty($_SESSION['auth_ok']) || empty($_SESSION['setor'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Não autenticado'], JSON_UNESCAPED_UNICODE);
  exit;
}

$meuSetor = trim((string)$_SESSION['setor']);

// filtro opcional (?numero=...)
// ...
$busca = trim((string)($_GET['busca'] ?? ''));
$buscaLike = $busca !== '' ? '%'.$busca.'%' : '';
$digitsRaw = preg_replace('/\D+/', '', $busca);   // <-- só os dígitos puros

try {
  $sql = "
    SELECT
      np.id,
      np.id_usuario_cehab_online,
      np.numero_processo,
      np.nome_processo,
      np.setor_demandante,
      np.enviar_para,
      np.tipos_processo_json,
      np.tipo_outros,
      np.descricao,
      np.data_registro
    FROM novo_processo np
    WHERE np.setor_demandante = ?
  ";

  $types  = 's';
  $params = [$meuSetor];

  if ($busca !== '') {
    $or = [];

    // só adiciona filtros por número se existe ao menos 1 dígito
    if ($digitsRaw !== '') {
      $or[] = "REPLACE(REPLACE(REPLACE(REPLACE(np.numero_processo, '.', ''), '/', ''), '-', ''), ' ', '') LIKE ?";
      $types .= 's';
      $params[] = '%'.$digitsRaw.'%';

      $or[] = "np.numero_processo LIKE ?";
      $types .= 's';
      $params[] = '%'.$digitsRaw.'%'; // pode ser $buscaLike, mas com dígitos já resolve
    }

    // sempre filtra por nome e descrição
    $or[] = "np.nome_processo LIKE ?";
    $types .= 's';
    $params[] = $buscaLike;

    $or[] = "np.descricao LIKE ?";
    $types .= 's';
    $params[] = $buscaLike;

    $sql .= " AND (".implode(' OR ', $or).") ";
  }

  $sql .= " ORDER BY np.id DESC LIMIT 300 ";

  $st = $connLocal->prepare($sql) or throw new RuntimeException('Falha ao preparar SELECT');
  $bind = [$types]; foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
  call_user_func_array([$st, 'bind_param'], $bind);
  $st->execute();
  $res  = $st->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) { $rows[] = $r; }
  $st->close();

  echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
