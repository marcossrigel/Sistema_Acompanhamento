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
$busca = trim((string)($_GET['busca'] ?? ''));
$buscaLike   = $busca !== '' ? '%'.$busca.'%' : '';
$buscaDigits = $busca !== '' ? '%'.preg_replace('/\D+/', '', $busca).'%' : '';

try {
  $sql = "
    SELECT
      np.id,
      np.id_usuario_cehab_online,
      np.numero_processo,
      np.nome_processo,           -- << NOVO
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
    // compara versão “só dígitos” OU LIKE direto no número OU trecho no nome/descrição
    $sql .= "
      AND (
        REPLACE(REPLACE(REPLACE(REPLACE(np.numero_processo, '.', ''), '/', ''), '-', ''), ' ', '') LIKE ?
        OR np.numero_processo LIKE ?
        OR np.nome_processo LIKE ?     -- novo campo
        OR np.descricao LIKE ?
      )
    ";
    $types .= 'ssss';
    $params[] = $buscaDigits;
    $params[] = $buscaLike;
    $params[] = $buscaLike;  // nome_processo
    $params[] = $buscaLike;  // descricao
  }

  $sql .= " ORDER BY np.id DESC LIMIT 300 ";

  $st = $connLocal->prepare($sql);
  if (!$st) {
    throw new RuntimeException('Falha ao preparar SELECT');
  }

  // bind dinâmico
  $bind = [$types];
  foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
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
