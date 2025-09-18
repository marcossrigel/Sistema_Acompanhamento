<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');
header('Content-Type: application/json; charset=utf-8');

/* carrega config */
$cfgPath = __DIR__ . '/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/../config.php'; }
if (!file_exists($cfgPath)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Arquivo de configuração não encontrado.']);
  exit;
}
require_once $cfgPath;

/* exige login */
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Não autenticado.']);
  exit;
}

$gId   = (int)($_SESSION['g_id'] ?? 0);
$setor = trim($_SESSION['setor'] ?? '');

/* whitelist de setores */
$SETORES_DEST = [
  'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS',
  'GECOMP','DDO','CPL','DAF - HOMOLOGACAO','PARECER JUR',
  'GEFIN NE INICIAL','REMESSA','GOP PF (SEFAZ)','GEFIN NE DEFINITIVO',
  'LIQ','PD (SEFAZ)','OB'
];

try {
  $raw  = file_get_contents('php://input');
  $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  $numero    = trim($data['numero_processo'] ?? '');
  $enviar    = trim($data['enviar_para'] ?? '');
  $tipos     = $data['tipos_processo'] ?? [];
  $outrosTxt = trim($data['tipo_outros'] ?? '');
  $desc      = trim($data['descricao'] ?? '');

  if ($numero === '' || $desc === '' || $setor === '' || !$gId) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Campos obrigatórios ausentes (número/descrição/sessão).']);
    exit;
  }
  if (!in_array($enviar, $SETORES_DEST, true)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Setor de destino inválido.']);
    exit;
  }

  $tipos = array_values(array_unique(array_map('strval', (array)$tipos)));
  if (empty($tipos)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Selecione ao menos um tipo de processo.']);
    exit;
  }
  if (in_array('outros', $tipos, true) && $outrosTxt === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Descreva o tipo em "outros" ou desmarque a opção.']);
    exit;
  }
  $tiposJson = json_encode($tipos, JSON_UNESCAPED_UNICODE);

  /* INSERT conforme sua tabela novo_processo */
  $sql = "INSERT INTO novo_processo
            (id_usuario_cehab_online, numero_processo, setor_demandante,
             enviar_para, tipos_processo_json, tipo_outros, descricao)
          VALUES (?,?,?,?,?,?,?)";

  $stmt = $connLocal->prepare($sql);
  if (!$stmt) { throw new RuntimeException('Falha ao preparar statement.'); }

  $tipoOutrosOrNull = ($outrosTxt !== '') ? $outrosTxt : null;
  $stmt->bind_param(
    "issssss",
    $gId,
    $numero,
    $setor,          // vem da sessão, não do cliente
    $enviar,
    $tiposJson,
    $tipoOutrosOrNull,
    $desc
  );

  $ok = $stmt->execute();
  if (!$ok) { throw new RuntimeException('Falha ao inserir no banco.'); }

  echo json_encode(['ok'=>true, 'id'=>$connLocal->insert_id]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Falha ao salvar: '.$e->getMessage()]);
}
