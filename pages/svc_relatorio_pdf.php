<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

/* carrega FPDF (mesmos caminhos) */
$fpdfPaths = [ __DIR__.'/libs/fpdf182/fpdf.php', __DIR__.'/../libs/fpdf182/fpdf.php', __DIR__.'/../../libs/fpdf182/fpdf.php' ];
$found=false; foreach($fpdfPaths as $p){ if(is_file($p)){ require_once $p; $found=true; break; } }
if(!$found){ http_response_code(500); echo "FPDF não encontrado."; exit; }

/* carrega config e usa $pdo/$conexao */
$cfgCandidates = [ __DIR__.'/config.php', __DIR__.'/../templates/config.php', __DIR__.'/../config.php' ];
foreach ($cfgCandidates as $p) { if (file_exists($p)) { require_once $p; break; } }
$driver = (isset($pdo) && $pdo instanceof PDO) ? 'pdo' : ((isset($conexao) && $conexao instanceof mysqli) ? 'mysqli' : null);
if (!$driver) { http_response_code(500); echo "Sem conexão DB."; exit; }

$numero = trim($_GET['numero'] ?? '');
if($numero===''){ http_response_code(400); echo 'Número do processo não informado.'; exit; }

/* helpers de texto */
function t($s){ if($s===null) return ''; $c=@iconv('UTF-8','ISO-8859-1//TRANSLIT',(string)$s); return $c===false?utf8_decode((string)$s):$c; }
function tz($s,$m){ $s=(string)$s; return strlen($s)<=$m?$s:substr($s,0,max(0,$m-1))."…"; }

try{
  // processo
  if ($driver==='pdo'){
    $st=$pdo->prepare("SELECT id, numero_processo, setor_demandante, enviar_para, tipos_processo_json, tipo_outros, descricao, data_registro
                       FROM novo_processo WHERE numero_processo = :num LIMIT 1");
    $st->execute([':num'=>$numero]); $proc=$st->fetch();
  } else {
    $num=$conexao->real_escape_string($numero);
    $res=$conexao->query("SELECT id, numero_processo, setor_demandante, enviar_para, tipos_processo_json, tipo_outros, descricao, data_registro
                          FROM novo_processo WHERE numero_processo = '$num' LIMIT 1");
    if(!$res) throw new RuntimeException('MySQLi: '.$conexao->error);
    $proc=$res->fetch_assoc();
  }
  if(!$proc){ http_response_code(404); echo 'Processo não encontrado.'; exit; }

  // fluxo
  $pid=(int)$proc['id'];
  if ($driver==='pdo'){
    $st2=$pdo->prepare("SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
                        FROM processo_fluxo WHERE processo_id=:pid ORDER BY ordem ASC");
    $st2->execute([':pid'=>$pid]); $fluxo=$st2->fetchAll();
  } else {
    $res2=$conexao->query("SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
                           FROM processo_fluxo WHERE processo_id=$pid ORDER BY ordem ASC");
    if(!$res2) throw new RuntimeException('MySQLi: '.$conexao->error);
    $fluxo=$res2->fetch_all(MYSQLI_ASSOC);
  }

  // string de tipos
  $tiposArr=[]; if(!empty($proc['tipos_processo_json'])){ $tmp=json_decode($proc['tipos_processo_json'],true); if(is_array($tmp)) $tiposArr=$tmp; }
  $tiposStr=$tiposArr?implode(', ',$tiposArr):'—';
  if(!empty($proc['tipo_outros'])){ $tiposStr=($tiposStr==='—'?'':$tiposStr.' | ').'Outros: '.$proc['tipo_outros']; if($tiposStr==='') $tiposStr='—'; }

  // PDF (igual ao seu, omitido aqui por brevidade)...
  class PDF extends FPDF { /* ... mesmo código da sua versão ... */ }

  $pdf=new PDF('P','mm','A4'); $pdf->AliasNbPages(); $pdf->AddPage(); $pdf->SetFont('Arial','',10);
  // … (mesmo preenchimento que você já fez) …
  // (copie aqui o mesmo bloco que você já tem de renderização; não precisa mudar)

  $fname='relatorio_'.$proc['numero_processo'].'.pdf';
  $pdf->Output('I',$fname);

}catch(Throwable $e){
  http_response_code(500);
  echo 'Erro ao gerar relatório.';
}
