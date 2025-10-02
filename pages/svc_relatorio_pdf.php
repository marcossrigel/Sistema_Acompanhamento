<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$fpdfPaths = [
  __DIR__ . '/libs/fpdf182/fpdf.php',
  __DIR__ . '/../libs/fpdf182/fpdf.php',
  __DIR__ . '/../../libs/fpdf182/fpdf.php',
];
$found = false;
foreach ($fpdfPaths as $p) {
  if (is_file($p)) { require_once $p; $found = true; break; }
}
if (!$found) {
  http_response_code(500);
  echo "FPDF não encontrado. Coloque o arquivo 'fpdf.php' em:\n".
       " - pages/libs/fpdf/fpdf.php\n".
       " - libs/fpdf/fpdf.php\n".
       " (ou ajuste o caminho em svc_relatorio_pdf.php)";
  exit;
}

$numero = trim($_GET['numero'] ?? '');
if ($numero === '') {
  http_response_code(400);
  echo 'Número do processo não informado.';
  exit;
}

try {
  $pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=sistema_acompanhamento;charset=utf8mb4',
    'root',
    '',
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );

  $stmt = $pdo->prepare("
    SELECT id,
           numero_processo,
           setor_demandante,
           enviar_para,
           tipos_processo_json,
           tipo_outros,
           descricao,
           data_registro
    FROM novo_processo
    WHERE numero_processo = :num
    LIMIT 1
  ");
  $stmt->execute([':num' => $numero]);
  $proc = $stmt->fetch();

  if (!$proc) {
    http_response_code(404);
    echo 'Processo não encontrado.';
    exit;
  }

  $stmt2 = $pdo->prepare("
    SELECT ordem, setor, status, acao_finalizadora, observacao, usuario, data_registro, data_fim
    FROM processo_fluxo
    WHERE processo_id = :pid
    ORDER BY ordem ASC
  ");
  $stmt2->execute([':pid' => $proc['id']]);
  $fluxo = $stmt2->fetchAll();

  $tiposArr = [];
  if (!empty($proc['tipos_processo_json'])) {
    $decoded = json_decode($proc['tipos_processo_json'], true);
    if (is_array($decoded)) $tiposArr = $decoded;
  }
  $tiposStr = $tiposArr ? implode(', ', $tiposArr) : '—';
  if (!empty($proc['tipo_outros'])) {
    $tiposStr = ($tiposStr === '—' ? '' : $tiposStr . ' | ') . 'Outros: ' . $proc['tipo_outros'];
  }
  if ($tiposStr === '') $tiposStr = '—';

  class PDF extends FPDF {
    function Header(){
      $this->SetFont('Arial','B',12);
      $this->Cell(0,7,'CEHAB - Relatório de Processo',0,1,'C');
      $this->Ln(2);
    }
    function Footer(){
      $this->SetY(-15);
      $this->SetFont('Arial','I',8);
      $this->Cell(0,10,'Gerado em '.date('d/m/Y H:i').' - Página '.$this->PageNo().'/{nb}',0,0,'C');
    }
    function H2($txt){
      $this->SetFont('Arial','B',11);
      $this->Cell(0,7,$txt,0,1);
    }
    function KV($k,$v){
      $this->SetFont('Arial','',10);
      $this->MultiCell(0,6,$k.': '.$v,0,1);
    }
    function CellFit($w, $h, $txt, $border=0, $ln=0, $align='', $fill=false){
      $this->Cell($w,$h,$txt,$border,$ln,$align,$fill);
    }
  }

  $pdf = new PDF('P','mm','A4');
  $pdf->AliasNbPages();
  $pdf->AddPage();
  $pdf->SetFont('Arial','',10);

  $pdf->H2('Dados do Processo');
  $pdf->KV('Número', $proc['numero_processo']);
  $pdf->KV('Setor Demandante', $proc['setor_demandante'] ?: '—');
  $pdf->KV('Enviar para', $proc['enviar_para'] ?: '—');
  $pdf->KV('Tipos', $tiposStr);
  $pdf->KV('Descrição', $proc['descricao'] ?: '—');
  $pdf->KV('Criado em', $proc['data_registro'] ? date('d/m/Y H:i', strtotime($proc['data_registro'])) : '—');
  $pdf->Ln(2);

  $pdf->H2('Histórico / Fluxo');
  if (!$fluxo) {
    $pdf->KV('Eventos', 'Nenhum evento registrado.');
  } else {
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(12,8,'#',1,0,'C');
    $pdf->Cell(40,8,'Setor',1,0,'C');
    $pdf->Cell(22,8,'Status',1,0,'C');
    $pdf->Cell(35,8,'Usuário',1,0,'C');
    $pdf->Cell(35,8,'Início',1,0,'C');
    $pdf->Cell(35,8,'Fim',1,1,'C');

    $pdf->SetFont('Arial','',10);
    foreach ($fluxo as $f) {
      $ordem = $f['ordem'] ?? '';
      $setor = $f['setor'] ?? '—';
      $status = $f['status'] ?? '—';
      $usuario = $f['usuario'] ?? '—';
      $dtIni = $f['data_registro'] ? date('d/m/Y H:i', strtotime($f['data_registro'])) : '—';
      $dtFim = $f['data_fim'] ? date('d/m/Y H:i', strtotime($f['data_fim'])) : '—';

      $pdf->CellFit(12,8,(string)$ordem,1,0,'C');
      $pdf->CellFit(40,8,$setor,1,0,'L');
      $pdf->CellFit(22,8,$status,1,0,'C');
      $pdf->CellFit(35,8,$usuario,1,0,'L');
      $pdf->CellFit(35,8,$dtIni,1,0,'C');
      $pdf->CellFit(35,8,$dtFim,1,1,'C');

      if (!empty($f['observacao'])) {
        $pdf->SetFont('Arial','I',9);
        $pdf->MultiCell(0,7,'Observação: '.$f['observacao'],1,'L');
        $pdf->SetFont('Arial','',10);
      }
      if (!empty($f['acao_finalizadora'])) {
        $pdf->SetFont('Arial','I',9);
        $pdf->MultiCell(0,7,'Ação: '.$f['acao_finalizadora'],1,'L');
        $pdf->SetFont('Arial','',10);
      }
    }
  }

  $fname = 'relatorio_'.$proc['numero_processo'].'.pdf';
  $pdf->Output('I', $fname);

} catch (Throwable $e) {
  http_response_code(500);
  echo 'Erro ao gerar relatório.';
}
