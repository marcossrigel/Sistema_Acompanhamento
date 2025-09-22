<?php
// templates/encaminhar_processo.php (mysqli)
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0');
ini_set('log_errors','1');
while (ob_get_level() > 0) { ob_end_clean(); }

require __DIR__ . '/config.php'; // deve criar $connLocal (mysqli) SEM imprimir nada

$reply = function (int $http, array $obj) {
  http_response_code($http);
  echo json_encode($obj, JSON_UNESCAPED_UNICODE);
  exit;
};

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  $reply(401, ['ok'=>false, 'error'=>'Não autenticado.']);
}

// Lê JSON do fetch; fallback para form-encoded
$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST ?: []; }

$processoId       = isset($data['id_processo']) ? (int)$data['id_processo'] : 0;
$setorDestino     = trim((string)($data['setor_destino'] ?? ''));
$acaoFinalizadora = trim((string)($data['acao_finalizadora'] ?? ''));
$meuSetor         = trim((string)($_SESSION['setor'] ?? ''));

if ($processoId <= 0 || $setorDestino === '' || $acaoFinalizadora === '' || $meuSetor === '') {
  $reply(400, ['ok'=>false, 'error'=>'Dados incompletos.']);
}

try {
  // Transação
  if (!$connLocal->begin_transaction()) {
    throw new RuntimeException('Falha ao iniciar transação.');
  }

  // 1) Confirma posse do processo (trava linha)
  $sql = "SELECT enviar_para FROM novo_processo WHERE id = ? FOR UPDATE";
  $st  = $connLocal->prepare($sql);
  $st->bind_param('i', $processoId);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  $st->close();

  if (!$row)       { throw new RuntimeException('Processo não encontrado.'); }
  $enviarParaAtual = trim((string)$row['enviar_para']);
  if (mb_strtolower($enviarParaAtual) !== mb_strtolower($meuSetor)) {
    throw new RuntimeException('Seu setor não possui este processo no momento.');
  }

  // 2) Busca etapa atual no fluxo
  $sql = "SELECT id, setor, ordem
            FROM processo_fluxo
           WHERE processo_id = ? AND status IN ('atual','ativo')
        ORDER BY id DESC LIMIT 1";
  $st = $connLocal->prepare($sql);
  $st->bind_param('i', $processoId);
  $st->execute();
  $res   = $st->get_result();
  $etapa = $res->fetch_assoc();
  $st->close();

  // Se não existir, cria uma etapa "atual" para o setor em posse
  if (!$etapa) {
    $sql = "INSERT INTO processo_fluxo (processo_id, ordem, setor, status, data_registro)
            VALUES (?, 1, ?, 'atual', NOW())";
    $st  = $connLocal->prepare($sql);
    $st->bind_param('is', $processoId, $meuSetor);
    $st->execute();
    $etapa = ['id' => $connLocal->insert_id, 'setor' => $meuSetor, 'ordem' => 1];
    $st->close();
  }

  // 3) Concluir a etapa atual
  $usuarioResp = (string)(($_SESSION['nome'] ?? '') ?: ($_SESSION['u_rede'] ?? ''));
  $sql = "UPDATE processo_fluxo
             SET status='concluido', acao_finalizadora=?, usuario=?, data_fim=NOW()
           WHERE id=?";
  $st  = $connLocal->prepare($sql);
  $st->bind_param('ssi', $acaoFinalizadora, $usuarioResp, $etapa['id']);
  $st->execute();
  $st->close();

  // 4) Próxima ordem
  $sql = "SELECT COALESCE(MAX(ordem),0) AS max_ordem FROM processo_fluxo WHERE processo_id=?";
  $st  = $connLocal->prepare($sql);
  $st->bind_param('i', $processoId);
  $st->execute();
  $res       = $st->get_result();
  $maxOrdem  = (int)($res->fetch_assoc()['max_ordem'] ?? 0);
  $nextOrdem = $maxOrdem + 1;
  $st->close();

  // 5) Inserir nova etapa (status 'atual') para o próximo setor
  $sql = "INSERT INTO processo_fluxo (processo_id, ordem, setor, status, data_registro)
          VALUES (?, ?, ?, 'atual', NOW())";
  $st  = $connLocal->prepare($sql);
  $st->bind_param('iis', $processoId, $nextOrdem, $setorDestino);
  $st->execute();
  $st->close();

  // 6) Atualizar destino do processo
  $sql = "UPDATE novo_processo SET enviar_para=? WHERE id=?";
  $st  = $connLocal->prepare($sql);
  $st->bind_param('si', $setorDestino, $processoId);
  $st->execute();
  $st->close();

  // Commit
  $connLocal->commit();

  $reply(200, [
    'ok'    => true,
    'from'  => $etapa['setor'],
    'to'    => $setorDestino,
    'ordem' => $nextOrdem
  ]);

} catch (Throwable $e) {
  if ($connLocal && $connLocal->errno === 0) {
    // Se a transação ainda existir, tenta rollback
    @$connLocal->rollback();
  }
  $reply(500, ['ok'=>false, 'error'=>$e->getMessage()]);
}
