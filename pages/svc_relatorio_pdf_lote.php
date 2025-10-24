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
foreach ($fpdfPaths as $p) { if (is_file($p)) { require_once $p; $found = true; break; } }
if (!$found) { http_response_code(500); echo "FPDF não encontrado."; exit; }

/* ===== CONFIG / DB ===== */
$cfgCandidates = [ __DIR__.'/config.php', __DIR__.'/../templates/config.php', __DIR__.'/../config.php' ];
foreach ($cfgCandidates as $p) { if (file_exists($p)) { require_once $p; break; } }

$driver = (isset($pdo) && $pdo instanceof PDO) ? 'pdo'
        : ((isset($conexao) && $conexao instanceof mysqli) ? 'mysqli' : null);
if (!$driver) { http_response_code(500); echo "Sem conexão DB."; exit; }

/* ===== INPUT ===== */
// Aceita JSON em ?nums=[...], ou CSV em ?nums=AAA,BBB
$raw = $_GET['nums'] ?? '';
if ($raw === '') { http_response_code(400); echo 'Seleção vazia.'; exit; }

$nums = json_decode($raw, true);
if (!is_array($nums)) {
  // tenta CSV
  $nums = array_filter(array_map('trim', explode(',', $raw)));
}
$nums = array_values(array_unique(array_filter($nums)));
if (!$nums) { http_response_code(400); echo 'Nenhum processo válido.'; exit; }

/* ===== HELPERS ===== */
function enc($s){ if ($s===null) return ''; $c=@iconv('UTF-8','ISO-8859-1//TRANSLIT',(string)$s); return $c===false?utf8_decode((string)$s):$c; }
function dtbr($s){ if(!$s) return '—'; $ts=strtotime($s); return $ts===false?enc($s):date('d/m/Y H:i',$ts); }
function nomePrimeiroSobrenome($nome){
  if(!$nome) return '';
  $nome = trim(preg_replace('/\s+/', ' ', (string)$nome));
  if ($nome === '') return '';
  $tokens = explode(' ', $nome);
  $first  = $tokens[0];
  $stop = ['de','da','do','das','dos','e','d\'','di','du'];
  for ($i=1; $i<count($tokens); $i++){
    $t = mb_strtolower($tokens[$i], 'UTF-8');
    if (!in_array($t, $stop, true)) return $first.' '.$tokens[$i];
  }
  return $first;
}

/* ===== CONSULTAS ===== */
function buscarProcesso($numero, $driver, $pdo, $conexao) {
  if ($driver === 'pdo') {
    $st = $pdo->prepare(
      "SELECT id, numero_processo, setor_demandante, enviar_para,
              tipos_processo_json, tipo_outros, descricao, data_registro
         FROM novo_processo
        WHERE numero_processo = :num
        LIMIT 1"
    );
    $st->execute([':num'=>$numero]);
    return $st->fetch();
  } else {
    $num = $conexao->real_escape_string($numero);
    $sql = "SELECT id, numero_processo, setor_demandante, enviar_para,
                   tipos_processo_json, tipo_outros, descricao, data_registro
              FROM novo_processo
             WHERE numero_processo = '$num'
             LIMIT 1";
    $res = $conexao->query($sql);
    if (!$res) throw new RuntimeException('MySQLi: '.$conexao->error);
    return $res->fetch_assoc();
  }
}

function buscarFluxo($pid, $driver, $pdo, $conexao) {
  if ($driver === 'pdo') {
    $st2 = $pdo->prepare(
      "SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
         FROM processo_fluxo
        WHERE processo_id = :pid
        ORDER BY ordem ASC"
    );
    $st2->execute([':pid'=>$pid]);
    return $st2->fetchAll();
  } else {
    $sql2 = "SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
               FROM processo_fluxo
              WHERE processo_id = ".(int)$pid."
              ORDER BY ordem ASC";
    $res2 = $conexao->query($sql2);
    if (!$res2) throw new RuntimeException('MySQLi: '.$conexao->error);
    return $res2->fetch_all(MYSQLI_ASSOC);
  }
}

/* ===== PDF ===== */
class PDF extends FPDF {
  function Header(){
    $this->SetFont('Arial','B',12);
    $this->Cell(0,8,enc('CEHAB - Acompanhamento de Processos'),0,1,'L');
    $this->SetFont('Arial','',9);
    $this->Cell(0,6,enc('Relatório de Processo (lote)'),0,1,'L');
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

$widths = [12, 22, 22, 50, 33, 25, 26];
$labels = ['Ord.', 'Setor', 'Status', 'Ação Finalizadora', 'Usuário', 'Data Registro', 'Data Fim'];

foreach ($nums as $idx => $numero) {
  $pdf->AddPage();
  $pdf->SetFont('Arial','B',11);
  $pdf->Cell(0,8,enc('Dados do Processo'),0,1,'L');
  $pdf->SetFont('Arial','',9);

  try {
    $proc = buscarProcesso($numero, $driver, $pdo ?? null, $conexao ?? null);
    if (!$proc) {
      $pdf->SetFont('Arial','I',9);
      $pdf->MultiCell(0,7,enc("Processo $numero não encontrado."),1,'L');
      continue;
    }

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

    // bloco dados
    $pdf->RowKV('Número',           $proc['numero_processo']);
    $pdf->RowKV('Setor Demandante', $proc['setor_demandante'] ?: '—');
    $pdf->RowKV('Enviar para',      $proc['enviar_para'] ?: '—');
    $pdf->RowKV('Tipos',            $tiposStr);
    $pdf->RowKV('Descrição',        $proc['descricao'] ?: '—');
    $pdf->RowKV('Criado em',        dtbr($proc['data_registro']));
    $pdf->Ln(3);

    // bloco fluxo
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0,8,enc('Fluxo do Processo'),0,1,'L');
    $pdf->SetFont('Arial','',9);
    $pdf->TableHeader2($widths, $labels);

    $fluxo = buscarFluxo((int)$proc['id'], $driver, $pdo ?? null, $conexao ?? null);

    if (empty($fluxo)) {
      $pdf->SetFont('Arial','I',9);
      $pdf->Cell(0,8,enc('Sem eventos de fluxo registrados.'),1,1,'C');
    } else {
      $primeiraLinha = true;
      foreach ($fluxo as $row) {
        $setorNome = $row['setor'];
        if (strpos($setorNome, ' - ') !== false) $setorNome = trim(explode(' - ', $setorNome)[0]);

        $dataRegistro = $row['data_registro'] ?: null;
        $dataFimRaw = $row['data_fim'] ?? null;
        $isEmptyFim = ($dataFimRaw === null || $dataFimRaw === '' || $dataFimRaw === '0000-00-00 00:00:00');
        if ($primeiraLinha && $isEmptyFim) $dataFimRaw = $dataRegistro;
        $primeiraLinha = false;

        $usuarioBruto = $row['usuario'] ?? '';
        $usuarioCurto = $usuarioBruto ? nomePrimeiroSobrenome($usuarioBruto) : '—';

        $pdf->TableRow([
          $row['ordem'],
          $setorNome,
          $row['status'],
          $row['acao_finalizadora'] ?: '—',
          $usuarioCurto,
          $dataRegistro ? dtbr($dataRegistro) : '—',
          $dataFimRaw  ? dtbr($dataFimRaw)   : '—'
        ], $widths, ['small_cols' => [5,6], 'small_size' => 7]);
      }
    }
  } catch (Throwable $e) {
    $pdf->SetFont('Arial','I',9);
    $pdf->MultiCell(0,7,enc("Erro ao gerar para $numero."),1,'L');
  }
}

/* ===== SAÍDA ===== */
while (ob_get_level()) { ob_end_clean(); }
$fname = 'relatorios_lote.pdf';
$pdf->Output('I', $fname);
