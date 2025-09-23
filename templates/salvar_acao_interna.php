<?php
// templates/salvar_acao_interna.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); ini_set('log_errors','1');
while (ob_get_level() > 0) { ob_end_clean(); }

require __DIR__.'/config.php'; // $connLocal (mysqli)

$reply = function(int $http, array $obj){ http_response_code($http); echo json_encode($obj,JSON_UNESCAPED_UNICODE); exit; };
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) { $reply(401,['ok'=>false,'error'=>'Não autenticado.']); }

$raw  = file_get_contents('php://input') ?: '';
$in   = json_decode($raw, true);
if (!is_array($in)) { $in = $_POST ?: []; }

$pid   = isset($in['processo_id']) ? (int)$in['processo_id'] : 0;
$texto = trim((string)($in['texto'] ?? ''));
$setorSessao = trim((string)($_SESSION['setor'] ?? ''));
$usuario     = (string)(($_SESSION['nome'] ?? '') ?: ($_SESSION['u_rede'] ?? ''));

if ($pid<=0 || $texto==='') { $reply(400, ['ok'=>false,'error'=>'Dados incompletos.']); }

// Regra: só o setor que está com o processo pode adicionar ação
$st = $connLocal->prepare("SELECT enviar_para FROM novo_processo WHERE id=?");
$st->bind_param('i',$pid); $st->execute();
$enviar = $st->get_result()->fetch_assoc()['enviar_para'] ?? '';
$st->close();
if (mb_strtolower($enviar) !== mb_strtolower($setorSessao)) {
  $reply(403, ['ok'=>false,'error'=>'Seu setor não possui este processo.']);
}

try {
  $st = $connLocal->prepare(
    "INSERT INTO processo_acao_interna (processo_id,setor,texto,usuario) VALUES (?,?,?,?)"
  );
  $st->bind_param('isss',$pid,$setorSessao,$texto,$usuario);
  $st->execute();
  $idNew = $st->insert_id;
  $st->close();

  $reply(200, [
    'ok'=>true,
    'data'=>[
      'id'=>$idNew,
      'processo_id'=>$pid,
      'setor'=>$setorSessao,
      'texto'=>$texto,
      'usuario'=>$usuario,
      'data_registro'=>date('Y-m-d H:i:s')
    ]
  ]);
} catch (Throwable $e) {
  $reply(500, ['ok'=>false,'error'=>$e->getMessage()]);
}
