<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

/* ===== FPDF ===== */
$fpdfPaths = [
  __DIR__.'/libs/fpdf182/fpdf.php',
  __DIR__.'/../libs/fpdf182/fpdf.php',
  __DIR__.'/../../libs/fpdf182/fpdf.php'
];
$found = false;
foreach ($fpdfPaths as $p) {
  if (is_file($p)) { require_once $p; $found = true; break; }
}
if (!$found) { http_response_code(500); echo "FPDF não encontrado."; exit; }

/* ===== CONFIG / DB ===== */
$cfgCandidates = [ __DIR__.'/config.php', __DIR__.'/../templates/config.php', __DIR__.'/../config.php' ];
foreach ($cfgCandidates as $p) { if (file_exists($p)) { require_once $p; break; } }

$driver = (isset($pdo) && $pdo instanceof PDO) ? 'pdo'
        : ((isset($conexao) && $conexao instanceof mysqli) ? 'mysqli' : null);
if (!$driver) { http_response_code(500); echo "Sem conexão DB."; exit; }

/* ===== INPUT ===== */
$numero = trim($_GET['numero'] ?? '');
if ($numero === '') { http_response_code(400); echo 'Número do processo não informado.'; exit; }

/* ===== HELPERS ===== */
function enc($s){ if ($s===null) return ''; $c = @iconv('UTF-8','ISO-8859-1//TRANSLIT',(string)$s); return $c===false?utf8_decode((string)$s):$c; }
function curto($s,$m){ $s=(string)$s; return (strlen($s)<= $m)?$s:substr($s,0,max(0,$m-1)).'…'; }
function dtbr($s){ if (!$s) return '—'; $ts = strtotime($s); return $ts===false?enc($s):date('d/m/Y H:i', $ts); }
// Mantido caso você queira reaproveitar depois
function nomePrimeiroSobrenome($nome){
  if (!$nome) return ''; $nome = trim(preg_replace('/\s+/', ' ', (string)$nome)); if ($nome === '') return '';
  $tokens = explode(' ', $nome); $first  = $tokens[0];
  $stop = ['de','da','do','das','dos','e','d\'','di','du'];
  for ($i = 1; $i < count($tokens); $i++) { $t = mb_strtolower($tokens[$i], 'UTF-8'); if (!in_array($t, $stop, true)) return $first.' '.$tokens[$i]; }
  return $first;
}

/* ===== BUSCAS ===== */
try {
  // PROCESSO
  if ($driver === 'pdo') {
    $st = $pdo->prepare(
      "SELECT id, numero_processo, setor_demandante, enviar_para,
              tipos_processo_json, tipo_outros, descricao, data_registro
         FROM novo_processo
        WHERE numero_processo = :num
        LIMIT 1"
    );
    $st->execute([':num'=>$numero]);
    $proc = $st->fetch();
  } else {
    $num = $conexao->real_escape_string($numero);
    $sql = "SELECT id, numero_processo, setor_demandante, enviar_para,
                   tipos_processo_json, tipo_outros, descricao, data_registro
              FROM novo_processo
             WHERE numero_processo = '$num'
             LIMIT 1";
    $res = $conexao->query($sql);
    if (!$res) throw new RuntimeException('MySQLi: '.$conexao->error);
    $proc = $res->fetch_assoc();
  }
  if (!$proc) { http_response_code(404); echo 'Processo não encontrado.'; exit; }

  // TIPOS (string formatada)
  $tiposArr = [];
  if (!empty($proc['tipos_processo_json'])) {
    $tmp = json_decode($proc['tipos_processo_json'], true);
    if (is_array($tmp)) $tiposArr = $tmp;
  }
  $tiposStr = $tiposArr ? implode(', ', $tiposArr) : '—';
  if (!empty($proc['tipo_outros'])) {
    $tiposStr = ($tiposStr==='—' ? '' : $tiposStr.' | ').'Outros: '.$proc['tipo_outros'];
    if ($tiposStr==='') $tiposStr='—';
  }

  // FLUXO
  $pid = (int)$proc['id'];
  if ($driver === 'pdo') {
    $st2 = $pdo->prepare(
      "SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
         FROM processo_fluxo
        WHERE processo_id = :pid
        ORDER BY ordem ASC"
    );
    $st2->execute([':pid'=>$pid]);
    $fluxo = $st2->fetchAll();
  } else {
    $sql2 = "SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
               FROM processo_fluxo
              WHERE processo_id = $pid
              ORDER BY ordem ASC";
    $res2 = $conexao->query($sql2);
    if (!$res2) throw new RuntimeException('MySQLi: '.$conexao->error);
    $fluxo = $res2->fetch_all(MYSQLI_ASSOC);
  }

  // AÇÕES INTERNAS
  if ($driver === 'pdo') {
    $st3 = $pdo->prepare(
      "SELECT texto, data_registro
         FROM processo_acao_interna
        WHERE processo_id = :pid
        ORDER BY data_registro ASC"
    );
    $st3->execute([':pid'=>$pid]);
    $acoesInternas = $st3->fetchAll();
  } else {
    $sql3 = "SELECT texto, data_registro
               FROM processo_acao_interna
              WHERE processo_id = $pid
              ORDER BY data_registro ASC";
    $res3 = $conexao->query($sql3);
    if (!$res3) throw new RuntimeException('MySQLi: '.$conexao->error);
    $acoesInternas = $res3->fetch_all(MYSQLI_ASSOC);
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo 'Erro ao buscar dados.';
  exit;
}

/* ===== PDF ===== */
class PDF extends FPDF {
  function Header(){
    $this->SetFont('Arial','B',12);
    $this->Cell(0,8,enc('CEHAB - Acompanhamento de Processos'),0,1,'L');
    $this->SetFont('Arial','',9);
    $this->Cell(0,6,enc('Relatório de Processo'),0,1,'L');
    $this->Ln(2);
    $this->SetDrawColor(200,200,200);
    $this->Line(10, $this->GetY(), 200, $this->GetY());
    $this->Ln(4);
  }
  function Footer(){
    $this->SetY(-15);
    $this->SetFont('Arial','I',8);
    $this->Cell(0,10,enc('Página ').$this->PageNo().enc(' de {nb}'),0,0,'C');
  }
  function RowKV($k, $v){
    $this->SetFont('Arial','B',9);
    $this->Cell(45,6,enc($k.':'),0,0,'L');
    $this->SetFont('Arial','',9);
    $this->MultiCell(0,6,enc($v),0,'L');
  }
  function TableHeader2($widths, $labels){
    $this->SetFont('Arial','B',8);
    $this->SetFillColor(240,240,240);
    foreach ($labels as $i => $txt) {
      $w = $widths[$i] ?? 20;
      $this->Cell($w, 7, enc($txt), 1, 0, 'C', true);
    }
    $this->Ln();
  }
  function TableRow($data, $widths, $opts = []) {
    $smallCols = $opts['small_cols'] ?? [];
    $smallSize = $opts['small_size'] ?? 7;
    $normSize  = 8;
    $maxY = $this->GetY();
    foreach ($data as $i => $txt) {
      $w = $widths[$i];
      $x = $this->GetX();
      $y = $this->GetY();
      if (in_array($i, $smallCols, true)) { $this->SetFont('Arial','', $smallSize); $lineH = 4.8; }
      else { $this->SetFont('Arial','', $normSize); $lineH = 6; }
      $this->MultiCell($w, $lineH, enc($txt), 1, 'L');
      $maxY = max($maxY, $this->GetY());
      $this->SetXY($x + $w, $y);
    }
    $this->SetY($maxY);
  }
}

$pdf = new PDF('P','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',10);

/* === DADOS DO PROCESSO === */
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,enc('Dados do Processo'),0,1,'L');
$pdf->SetFont('Arial','',9);

$pdf->RowKV('Número',          $proc['numero_processo']);
$pdf->RowKV('Setor Demandante',$proc['setor_demandante'] ?: '—');
$pdf->RowKV('Enviar para',     $proc['enviar_para'] ?: '—');
$pdf->RowKV('Tipos',           $tiposStr);
$pdf->RowKV('Descrição',       $proc['descricao'] ?: '—');
$pdf->RowKV('Criado em',       dtbr($proc['data_registro']));
$pdf->Ln(3);

/* === Fluxo do Processo === */
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,enc('Fluxo do Processo'),0,1,'L');
$pdf->SetFont('Arial','',9);

/* NOVAS larguras/labels: removido "Usuário", adicionado "Ações Internas" */
$widths = [12, 25, 25, 40, 55, 25];  // soma ≈ 182 (A4 útil ~190mm; sobra p/ margens)
$labels = ['Ord.', 'Setor', 'Status', 'Ação Finalizadora', 'Ações Internas', 'Data Registro'];

$pdf->TableHeader2($widths, $labels);

/* Monta texto de ações internas (uma por linha) */
$acoesText = '—';
if (!empty($acoesInternas)) {
  $buf = [];
  foreach ($acoesInternas as $a) {
    $linha = '- '.$a['texto'];
    // Se quiser a data junto: $linha .= ' ('.dtbr($a['data_registro']).')';
    $buf[] = $linha;
  }
  $acoesText = implode("\n", $buf);
}

if (empty($fluxo)) {
  $pdf->SetFont('Arial','I',9);
  $pdf->Cell(0,8,enc('Sem eventos de fluxo registrados.'),1,1,'C');
} else {
  $primeiraLinha = true;
  foreach ($fluxo as $row) {
    // Setor “curto”
    $setorNome = $row['setor'];
    if (strpos($setorNome, ' - ') !== false) $setorNome = trim(explode(' - ', $setorNome)[0]);

    // Datas: primeira linha sem fim recebe registro
    $dataRegistro = $row['data_registro'] ?: null;
    $dataFimRaw = $row['data_fim'] ?? null;
    $isEmptyFim = ($dataFimRaw === null || $dataFimRaw === '' || $dataFimRaw === '0000-00-00 00:00:00');
    if ($primeiraLinha && $isEmptyFim) $dataFimRaw = $dataRegistro;
    $primeiraLinha = false;

    // Linha (sem usuário; com ações internas)
    $pdf->TableRow([
      $row['ordem'],
      $setorNome,
      $row['status'],
      $row['acao_finalizadora'] ?: '—',
      $acoesText,
      $dataRegistro ? dtbr($dataRegistro) : '—'
    ], $widths, [
      'small_cols' => [4,5],   // fonte menor em Ações Internas e Data Registro
      'small_size' => 7
    ]);
  }
}

/* (Opcional) Bloco extra listando ações internas em separado
$pdf->Ln(4);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,7,enc('Ações Internas Registradas'),0,1,'L');
$pdf->SetFont('Arial','',9);
if ($acoesText === '—') {
  $pdf->Cell(0,6,enc('— Nenhuma ação interna registrada.'),0,1,'L');
} else {
  $pdf->MultiCell(0,6,enc($acoesText),0,'L');
}
*/

/* === SAÍDA === */
while (ob_get_level()) { ob_end_clean(); }
$fname = 'relatorio_'.$proc['numero_processo'].'.pdf';
$pdf->Output('I', $fname);
