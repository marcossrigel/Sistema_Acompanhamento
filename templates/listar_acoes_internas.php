<?php
// templates/listar_acoes_internas.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); ini_set('log_errors','1');
while (ob_get_level() > 0) { ob_end_clean(); }

require __DIR__.'/config.php'; // $connLocal (mysqli)

$reply = function(int $http, array $obj){ http_response_code($http); echo json_encode($obj,JSON_UNESCAPED_UNICODE); exit; };
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) { $reply(401,['ok'=>false,'error'=>'Não autenticado.']); }

$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid<=0) { $reply(400, ['ok'=>false,'error'=>'id inválido']); }

try {
  $sql = "SELECT setor, texto, usuario, data_registro
            FROM processo_acao_interna
           WHERE processo_id=?
        ORDER BY data_registro ASC, id ASC";
  $st = $connLocal->prepare($sql);
  $st->bind_param('i',$pid);
  $st->execute();
  $res = $st->get_result();
  $data = [];
  while ($r = $res->fetch_assoc()) { $data[] = $r; }
  $st->close();
  $reply(200, ['ok'=>true, 'data'=>$data]);
} catch (Throwable $e) {
  $reply(500, ['ok'=>false,'error'=>$e->getMessage()]);
}
