<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');
header('Content-Type: application/json; charset=utf-8');

/** Carrega config.php (tanto se este arquivo estiver em /templates quanto fora) */
$cfgPath = __DIR__ . '/config.php';
if (!file_exists($cfgPath)) { $cfgPath = __DIR__ . '/../config.php'; }
if (!file_exists($cfgPath)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Arquivo de configuração não encontrado.']);
  exit;
}
require_once $cfgPath;

/** Autenticação obrigatória */
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Não autenticado.']);
  exit;
}

$gId        = (int)($_SESSION['g_id'] ?? 0);
$setorUser  = trim($_SESSION['setor'] ?? '');

/** Função utilitária para ler JSON com fallback */
function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

try {
  $data = read_json_body();

  $numero       = trim($data['numero_processo'] ?? '');
  $nomeProc     = trim($data['nome_processo']    ?? ''); // <-- NOVO
  $enviar       = trim($data['enviar_para']      ?? '');
  $tipos        = (array)($data['tipos_processo'] ?? []);
  $outrosTxt    = trim($data['tipo_outros']      ?? '');
  $desc         = trim($data['descricao']        ?? '');

  // --------- validações básicas ----------
  if ($gId <= 0 || $setorUser === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Sessão inválida.']);
    exit;
  }

  if ($numero === '' || $nomeProc === '' || $desc === '' || $enviar === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Preencha número, nome do processo, descrição e setor de destino.']);
    exit;
  }

  // valida formato do número: 1234567890.123456/1234-12
  if (!preg_match('/^\d{10}\.\d{6}\/\d{4}-\d{2}$/', $numero)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Número de processo em formato inválido. Use NNNNNNNNNN.NNNNNN/NNNN-NN']);
    exit;
  }

  // nome_processo (até 150 chars)
  if (mb_strlen($nomeProc) > 150) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'O nome do processo deve ter no máximo 150 caracteres.']);
    exit;
  }

  // normaliza tipos e exige ao menos um
  $tipos = array_values(array_unique(array_filter(array_map(
    fn($x) => trim((string)$x),
    $tipos
  ), fn($x) => $x !== '')));

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

  // --------- valida setor de destino contra a TABELA ----------
  $chk = $connLocal->prepare("SELECT 1 FROM setores WHERE ativo = 1 AND nome = ? LIMIT 1");
  if (!$chk) throw new RuntimeException('Falha ao preparar verificação do setor.');
  $chk->bind_param('s', $enviar);
  $chk->execute();
  $exists = (bool)$chk->get_result()->fetch_row();
  $chk->close();

  if (!$exists) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Setor de destino inválido ou inativo.']);
    exit;
  }

  $tiposJson = json_encode($tipos, JSON_UNESCAPED_UNICODE);

  // --------- transação: cria processo + fluxo inicial ----------
  $connLocal->begin_transaction();

  // INSERT em novo_processo (com nome_processo)
  $sql = "INSERT INTO novo_processo
            (id_usuario_cehab_online, numero_processo, nome_processo, setor_demandante,
             enviar_para, tipos_processo_json, tipo_outros, descricao)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
  $stmt = $connLocal->prepare($sql);
  if (!$stmt) throw new RuntimeException('Falha ao preparar inserção do processo.');

  // tipo_outros pode ser NULL
  $tipoOutrosOrNull = ($outrosTxt !== '') ? $outrosTxt : null;

  // i + 7s
  $stmt->bind_param(
    "isssssss",
    $gId,            // i
    $numero,         // s
    $nomeProc,       // s  <-- NOVO
    $setorUser,      // s
    $enviar,         // s
    $tiposJson,      // s
    $tipoOutrosOrNull, // s (pode ser NULL)
    $desc            // s
  );

  if (!$stmt->execute()) throw new RuntimeException('Falha ao inserir o processo: '.$stmt->error);
  $proc_id = (int)$connLocal->insert_id;
  $stmt->close();

  // Fluxo: ordem 1 concluído (setor demandante)
  $fx1 = $connLocal->prepare(
    "INSERT INTO processo_fluxo (processo_id, ordem, setor, status) VALUES (?, 1, ?, 'concluido')"
  );
  if (!$fx1) throw new RuntimeException('Falha ao preparar fluxo (etapa 1).');
  $fx1->bind_param("is", $proc_id, $setorUser);
  if (!$fx1->execute()) throw new RuntimeException('Falha ao inserir fluxo (etapa 1).');
  $fx1->close();

  // Fluxo: ordem 2 atual (setor destino)
  $fx2 = $connLocal->prepare(
    "INSERT INTO processo_fluxo (processo_id, ordem, setor, status) VALUES (?, 2, ?, 'atual')"
  );
  if (!$fx2) throw new RuntimeException('Falha ao preparar fluxo (etapa 2).');
  $fx2->bind_param("is", $proc_id, $enviar);
  if (!$fx2->execute()) throw new RuntimeException('Falha ao inserir fluxo (etapa 2).');
  $fx2->close();

  $connLocal->commit();

  echo json_encode(['ok'=>true, 'id'=>$proc_id], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  // tenta reverter se estivermos em transação
  if ($connLocal instanceof mysqli) {
    @mysqli_rollback($connLocal);
  }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Falha ao salvar: '.$e->getMessage()]);
}
