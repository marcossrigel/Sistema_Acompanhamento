<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');
header('Content-Type: application/json; charset=utf-8');

// config
$cfgPath = __DIR__ . '/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/../config.php'; }
if (!file_exists($cfgPath)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Config não encontrada'], JSON_UNESCAPED_UNICODE);
  exit;
}
require_once $cfgPath;

if (empty($_SESSION['auth_ok']) || empty($_SESSION['setor'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Não autenticado'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!$pdo) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'PDO indisponível (config.php)'], JSON_UNESCAPED_UNICODE);
  exit;
}

$meuSetor = trim((string)$_SESSION['setor']);

$norm = function (?string $s): string {
  return strtolower(trim((string)$s));
};

$busca     = trim((string)($_GET['busca'] ?? ''));
$buscaLike = $busca !== '' ? '%'.$busca.'%' : '';
$digitsRaw = preg_replace('/\D+/', '', $busca);
$normBusca = $norm($busca);

$filterFinalizado = null;
if (in_array($normBusca, ['concluido','concluído','concluido.','concluído.','finalizado','finalizado.'], true)) {
  $filterFinalizado = 1;
} elseif (in_array($normBusca, ['andamento','ativo','pendente'], true)) {
  $filterFinalizado = 0;
}

$scope = strtolower(trim((string)($_GET['scope'] ?? 'atual')));

// SQL base
$params = [];
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
    np.data_registro,
    np.finalizado,

    /* --- setor atual do fluxo --- */
    COALESCE(
      (SELECT pf1.setor
         FROM processo_fluxo pf1
        WHERE pf1.processo_id = np.id
          AND pf1.status = 'atual'
        ORDER BY pf1.ordem DESC
        LIMIT 1),
      (SELECT pf2.setor
         FROM processo_fluxo pf2
        WHERE pf2.processo_id = np.id
        ORDER BY pf2.ordem DESC
        LIMIT 1),
      np.setor_demandante
    ) AS setor_atual,

    /* --- sigla atual (antes de ' - ') --- */
    UPPER(SUBSTRING_INDEX(
      COALESCE(
        (SELECT pf3.setor
           FROM processo_fluxo pf3
          WHERE pf3.processo_id = np.id
            AND pf3.status = 'atual'
          ORDER BY pf3.ordem DESC
          LIMIT 1),
        (SELECT pf4.setor
           FROM processo_fluxo pf4
          WHERE pf4.processo_id = np.id
          ORDER BY pf4.ordem DESC
          LIMIT 1),
        np.setor_demandante
      ), ' - ', 1
    )) AS sigla_atual

  FROM novo_processo np
";

if ($scope === 'demandante') {
  $sql    .= " WHERE np.setor_demandante = :setor ";
  $params[':setor'] = $meuSetor;
} else {
  /* PADRÃO: processos cuja ETAPA ATIVA está no meu setor */
  $sql .= "
    WHERE EXISTS (
      SELECT 1
      FROM processo_fluxo pf
      WHERE pf.processo_id = np.id
        AND pf.status = 'atual'
        AND TRIM(pf.setor) = TRIM(:setor)
    )
  ";
  $params[':setor'] = $meuSetor;
}

/* filtro finalizado */
if ($filterFinalizado !== null) {
  $sql .= " AND np.finalizado = :fin ";
  $params[':fin'] = $filterFinalizado;
}

/* busca livre */
if ($busca !== '' && $filterFinalizado === null) {
  $ors = [];

  if ($digitsRaw !== '') {
    $ors[] = "REPLACE(REPLACE(REPLACE(REPLACE(np.numero_processo, '.', ''), '/', ''), '-', ''), ' ', '') LIKE :numdigits";
    $params[':numdigits'] = '%'.$digitsRaw.'%';

    $ors[] = "np.numero_processo LIKE :numlike";
    $params[':numlike'] = '%'.$digitsRaw.'%';
  }

  $ors[] = "np.nome_processo LIKE :nome";
  $params[':nome'] = $buscaLike;

  $ors[] = "np.descricao LIKE :desc";
  $params[':desc'] = $buscaLike;

  $ors[] = "np.enviar_para LIKE :dest";
  $params[':dest'] = $buscaLike;

  /* também vale buscar pela sigla/descrição do setor atual */
  $ors[] = "COALESCE(
              (SELECT pf.setor FROM processo_fluxo pf WHERE pf.processo_id=np.id AND pf.status='atual' ORDER BY pf.ordem DESC LIMIT 1),
              (SELECT pf.setor FROM processo_fluxo pf WHERE pf.processo_id=np.id ORDER BY pf.ordem DESC LIMIT 1),
              np.setor_demandante
            ) LIKE :setor_like";
  $params[':setor_like'] = $buscaLike;

  $sql .= " AND ( ".implode(' OR ', $ors)." ) ";
}

$sql .= " ORDER BY np.id DESC LIMIT 300 ";

try {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
