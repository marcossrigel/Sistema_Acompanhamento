<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';

const FINAL_SECTOR_CANON = 'GFIN - Gerência Financeira';

$reply = function (int $http, array $obj) {
  http_response_code($http);
  echo json_encode($obj, JSON_UNESCAPED_UNICODE);
  exit;
};

try {
  if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
    $reply(401, ['ok'=>false,'error'=>'not_authenticated']);
  }

  $in          = json_decode(file_get_contents('php://input'), true) ?: [];
  $idProcesso  = isset($in['id_processo']) ? (int)$in['id_processo'] : 0;
  $acaoFinal   = trim((string)($in['acao'] ?? ''));

  if ($idProcesso <= 0 || $acaoFinal === '') {
    $reply(400, ['ok'=>false,'error'=>'invalid_payload']);
  }

  $meuSetor = (string)($_SESSION['setor'] ?? '');
  $norm = function($s){
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s ?? '');
    $s = strtolower($s);
    return preg_replace('/\s+/',' ', trim($s));
  };
  if ($norm($meuSetor) !== $norm(FINAL_SECTOR_CANON)) {
    $reply(403, ['ok'=>false,'error'=>'forbidden_not_gfin']);
  }

  if (!$connLocal->begin_transaction()) {
    throw new RuntimeException('Falha ao iniciar transação.');
  }

  $st = $connLocal->prepare("SELECT id, finalizado, enviar_para FROM novo_processo WHERE id=? FOR UPDATE");
  $st->bind_param('i', $idProcesso);
  $st->execute();
  $res  = $st->get_result();
  $proc = $res->fetch_assoc();
  $st->close();

  if (!$proc) { throw new RuntimeException('process_not_found'); }
  if ((int)$proc['finalizado'] === 1) { throw new RuntimeException('already_finalized'); }
  if ($norm($proc['enviar_para']) !== $norm(FINAL_SECTOR_CANON)) {
    throw new RuntimeException('not_at_final_sector');
  }

  $st = $connLocal->prepare("
    UPDATE processo_fluxo
       SET status='concluido',
           data_fim = COALESCE(data_fim, NOW())
     WHERE processo_id=? AND status IN ('atual','ativo')
  ");
  $st->bind_param('i', $idProcesso);
  $st->execute();
  $st->close();

  $st = $connLocal->prepare("
    SELECT id, setor
      FROM processo_fluxo
     WHERE processo_id=?
  ORDER BY id DESC
     LIMIT 1
  ");
  $st->bind_param('i', $idProcesso);
  $st->execute();
  $res  = $st->get_result();
  $last = $res->fetch_assoc();
  $st->close();

  $usuarioResp = (string)(($_SESSION['nome'] ?? '') ?: ($_SESSION['u_rede'] ?? $_SESSION['g_id'] ?? 'sistema'));

  if ($last) {
    $st = $connLocal->prepare("
      UPDATE processo_fluxo
         SET status='concluido',
             acao_finalizadora=?,
             usuario=?,
             data_fim=NOW()
       WHERE id=?
    ");
    $st->bind_param('ssi', $acaoFinal, $usuarioResp, $last['id']);
    $st->execute();
    $st->close();
  } else {
    $st = $connLocal->prepare("SELECT COALESCE(MAX(ordem),0) FROM processo_fluxo WHERE processo_id=?");
    $st->bind_param('i', $idProcesso);
    $st->execute();
    $st->bind_result($maxOrdem);
    $st->fetch();
    $st->close();
    $nextOrdem = ((int)$maxOrdem) + 1;

    $status = 'concluido';
    $setor  = FINAL_SECTOR_CANON;
    $st = $connLocal->prepare("
      INSERT INTO processo_fluxo
        (processo_id, ordem, setor, status, acao_finalizadora, usuario, data_registro, data_fim)
      VALUES (?,?,?,?,?, ?, NOW(), NOW())
    ");
    $st->bind_param('iissss', $idProcesso, $nextOrdem, $setor, $status, $acaoFinal, $usuarioResp);
    $st->execute();
    $st->close();
  }

  $st = $connLocal->prepare("
    UPDATE novo_processo
       SET finalizado=1,
           finalizado_em=NOW(),
           enviar_para='CONCLUÍDO'
     WHERE id=?
  ");
  $st->bind_param('i', $idProcesso);
  $st->execute();
  $st->close();

  $connLocal->commit();
  $reply(200, ['ok'=>true]);

} catch (Throwable $e) {
  if ($connLocal && $connLocal->errno === 0) { @$connLocal->rollback(); }
  $reply(500, ['ok'=>false,'error'=>'db_error','msg'=>$e->getMessage()]);
}
