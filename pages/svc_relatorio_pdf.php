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
function dtbr($s){
  if (!$s) return '—';
  // aceita "YYYY-mm-dd HH:ii:ss"
  $ts = strtotime($s);
  if ($ts === false) return enc($s);
  return date('d/m/Y H:i', $ts);
}

// Retorna "Primeiro Último" ignorando partículas comuns

function nomePrimeiroSobrenome($nome){
  if (!$nome) return '';
  $nome = trim(preg_replace('/\s+/', ' ', (string)$nome));
  if ($nome === '') return '';

  $tokens = explode(' ', $nome);
  $first  = $tokens[0];

  // partículas comuns que NÃO devem ser usadas como 2º token
  $stop = ['de','da','do','das','dos','e','d\'','di','du'];

  // procura o primeiro token após o primeiro que NÃO seja partícula
  for ($i = 1; $i < count($tokens); $i++) {
    $t = mb_strtolower($tokens[$i], 'UTF-8');
    if (!in_array($t, $stop, true)) {
      return $first.' '.$tokens[$i];
    }
  }
  // fallback (não encontrou 2º token válido)
  return $first;
}


// Se $val for ID, busca no mapa; se for nome, usa direto
function resolveUsuarioNome($val, $usuariosMap) {
  if ($val === null || $val === '') return '';
  // numérico?
  if (ctype_digit((string)$val) && isset($usuariosMap[(int)$val])) {
    return $usuariosMap[(int)$val];
  }
  return (string)$val;
}


/* ===== BUSCA DADOS ===== */
try {
  // PROESSO
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

  // TIPOS
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

} catch (Throwable $e) {
  http_response_code(500);
  echo 'Erro ao buscar dados.';
  exit;
}

/* ===== PDF ===== */
class PDF extends FPDF {
  function Header(){
    // título
    $this->SetFont('Arial','B',12);
    $this->Cell(0,8,enc('CEHAB - Acompanhamento de Processos'),0,1,'L');
    $this->SetFont('Arial','',9);
    $this->Cell(0,6,enc('Relatório de Processo'),0,1,'L');
    $this->Ln(2);
    // linha
    $this->SetDrawColor(200,200,200);
    $this->Line(10, $this->GetY(), 200, $this->GetY());
    $this->Ln(4);
  }
  function Footer(){
    $this->SetY(-15);
    $this->SetFont('Arial','I',8);
    $this->Cell(0,10,enc('Página ').$this->PageNo().enc(' de {nb}'),0,0,'C');
  }

  // célula de título + valor
  function RowKV($k, $v){
    $this->SetFont('Arial','B',9);
    $this->Cell(45,6,enc($k.':'),0,0,'L');
    $this->SetFont('Arial','',9);
    $this->MultiCell(0,6,enc($v),0,'L');
  }

  // cabeçalho da tabela
  function TableHeader($cols){
    $this->SetFont('Arial','B',8);
    $this->SetFillColor(240,240,240);
    foreach ($cols as $w => $txt) {
      $this->Cell($w,7,enc($txt),1,0,'C',true);
    }
    $this->Ln();
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

  // linha da tabela
  // linha da tabela (com fonte menor em colunas indicadas)
  function TableRow($data, $widths, $opts = []) {
    // colunas que usam fonte menor (índices zero-based)
    $smallCols = $opts['small_cols'] ?? [];
    $smallSize = $opts['small_size'] ?? 7; // tamanho menor
    $normSize  = 8;                        // tamanho normal

    $maxY = $this->GetY();
    foreach ($data as $i => $txt) {
      $w = $widths[$i];
      $x = $this->GetX();
      $y = $this->GetY();

      // Fonte por coluna
      if (in_array($i, $smallCols, true)) {
        $this->SetFont('Arial','', $smallSize);
        $lineH = 4.8; // altura de linha menor
      } else {
        $this->SetFont('Arial','', $normSize);
        $lineH = 6;
      }

      $this->MultiCell($w, $lineH, enc($txt), 1, 'L');
      $maxY = max($maxY, $this->GetY());
      $this->SetXY($x + $w, $y); // avança para a próxima célula na mesma linha
    }
    $this->SetY($maxY);
  }

}

$pdf = new PDF('P','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',10);

/* === BLOCO: DADOS DO PROCESSO === */
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

/* === BLOCO: FLUXO DO PROCESSO === */
$pdf->SetFont('Arial','B',11);
$pdf->Cell(0,8,enc('Fluxo do Processo'),0,1,'L');
$pdf->SetFont('Arial','',9);

// Larguras (somam 190 mm) e rótulos
$widths = [12, 22, 22, 50, 33, 25, 26]; 
$labels = ['Ord.', 'Setor', 'Status', 'Ação Finalizadora', 'Usuário', 'Data Registro', 'Data Fim'];

$pdf->TableHeader2($widths, $labels);

if (empty($fluxo)) {
  $pdf->SetFont('Arial','I',9);
  $pdf->Cell(0,8,enc('Sem eventos de fluxo registrados.'),1,1,'C');
} else {
    $primeiraLinha = true;

  foreach ($fluxo as $row) {
    // Abrevia setor
    $setorNome = $row['setor'];
    if (strpos($setorNome, ' - ') !== false) {
      $setorNome = trim(explode(' - ', $setorNome)[0]);
    }

    // Datas
    $dataRegistro = $row['data_registro'] ?: null;

    // Se for a primeira linha e não houver data_fim, copie data_registro
    $dataFimRaw = $row['data_fim'] ?? null;
    $isEmptyFim = ($dataFimRaw === null || $dataFimRaw === '' || $dataFimRaw === '0000-00-00 00:00:00');
    if ($primeiraLinha && $isEmptyFim) {
      $dataFimRaw = $dataRegistro;
    }
    $primeiraLinha = false; // a partir da próxima linha, regra não se aplica


    $usuarioBruto = $row['usuario'] ?? '';
    $usuarioCurto = $usuarioBruto ? nomePrimeiroSobrenome($usuarioBruto) : '—';

    $pdf->TableRow([
      $row['ordem'],
      $setorNome,
      $row['status'],
      $row['acao_finalizadora'] ?: '—',
      $usuarioCurto,                                // << aqui vai só Nome + Sobrenome
      $dataRegistro ? dtbr($dataRegistro) : '—',
      $dataFimRaw  ? dtbr($dataFimRaw)   : '—'
    ], $widths, [
      'small_cols' => [5, 6],
      'small_size' => 7
    ]);

  }
}

/* === SAÍDA === */
// evita que algum BOM/eco quebre o PDF
while (ob_get_level()) { ob_end_clean(); }
$fname = 'relatorio_'.$proc['numero_processo'].'.pdf';
$pdf->Output('I', $fname);
