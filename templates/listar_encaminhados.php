<?php
// templates/listar_encaminhados.php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','0'); ini_set('log_errors','1');
while (ob_get_level() > 0) { ob_end_clean(); }

require __DIR__.'/config.php';

$reply = function(int $http, array $obj){ http_response_code($http); echo json_encode($obj,JSON_UNESCAPED_UNICODE); exit; };
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) { $reply(401, ['ok'=>false,'error'=>'Não autenticado.']); }

$meuSetor = trim((string)($_SESSION['setor'] ?? ''));
if ($meuSetor === '') { $reply(400, ['ok'=>false,'error'=>'Setor não encontrado na sessão.']); }

try {
  // traz processos cujo destino atual é meu setor OU que já tiveram etapa concluída pelo meu setor
  $sql = "
    SELECT np.id, np.numero_processo, np.setor_demandante, np.enviar_para,
           np.tipos_processo_json, np.tipo_outros, np.descricao, np.data_registro
    FROM novo_processo np
    WHERE LOWER(np.enviar_para) = LOWER(?)
       OR EXISTS (
            SELECT 1
              FROM processo_fluxo pf
             WHERE pf.processo_id = np.id
               AND LOWER(pf.setor) = LOWER(?)
               AND pf.status = 'concluido'
          )
    ORDER BY np.data_registro DESC
    LIMIT 300
  ";
  $st = $connLocal->prepare($sql);
  $st->bind_param('ss', $meuSetor, $meuSetor);
  $st->execute();
  $res  = $st->get_result();
  $data = [];
  while ($r = $res->fetch_assoc()) { $data[] = $r; }
  $st->close();
  $reply(200, ['ok'=>true, 'data'=>$data]);
} catch (Throwable $e) {
  $reply(500, ['ok'=>false,'error'=>$e->getMessage()]);
}
