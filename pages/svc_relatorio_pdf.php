<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/libs/fpdf/fpdf.php';

$numero = trim($_GET['numero'] ?? '');
if ($numero === '') {
  http_response_code(400);
  echo 'Número do processo não informado.';
  exit;
}

try {
  $pdo = new PDO('mysql:host=127.0.0.1;dbname=seu_banco;charset=utf8mb4','root','',[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $stmt = $pdo->prepare("SELECT * FROM novo_processo WHERE numero_processo = :num LIMIT 1");
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

  class PDF extends FPDF {
    function Header(){
      $this->SetFont('Arial','B',12);
      $this->Cell(0,7,utf8_decode('CEHAB - Relatório de Processo'),0,1,'C');
      $this->Ln(2);
    }
    function Footer(){
      $this->SetY(-15);
      $this->SetFont('Arial','I',8);
      $this->Cell(0,10,utf8_decode('Gerado em ').date('d/m/Y H:i').' - Página '.$this->PageNo().'/{nb}',0,0,'C');
    }
    function H2($txt){
      $this->SetFont('Arial','B',11);
      $this->Cell(0,7,utf8_decode($txt),0,1);
    }
    function KV($k,$v){
      $this->SetFont('Arial','',10);
      $this->MultiCell(0,6,utf8_decode($k.': '.$v),0,1);
    }
  }

  $pdf = new PDF('P','mm','A4');
  $pdf->AliasNbPages();
  $pdf->AddPage();
  $pdf->SetFont('Arial','',10);

  $pdf->H2('Dados do Processo');
  $pdf->KV('Número', $proc['numero_processo']);
  $pdf->KV('Setor Demandante', $proc['setor_demandante'] ?: '—');
  $pdf->KV('Enviar para', $proc['setor_destino'] ?: '—');
  $tipos = trim(($proc['tipos'] ?: '').($proc['outros'] ? ' | Outros: '.$proc['outros'] : ''));
  $pdf->KV('Tipos', $tipos !== '' ? $tipos : '—');
  $pdf->KV('Descrição', $proc['descricao'] ?: '—');
  $pdf->KV('Criado em', $proc['criado_em'] ? date('d/m/Y H:i', strtotime($proc['criado_em'])) : '—');
  $pdf->Ln(2);

  $pdf->H2('Histórico / Fluxo');
  if (!$fluxo) {
    $pdf->KV('Eventos', 'Nenhum evento registrado.');
  } else {
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(35,8,utf8_decode('Data'),1,0,'C');
    $pdf->Cell(40,8,utf8_decode('Etapa'),1,0,'C');
    $pdf->Cell(45,8,utf8_decode('Usuário'),1,0,'C');
    $pdf->Cell(70,8,utf8_decode('Observação'),1,1,'C');
    $pdf->SetFont('Arial','',10);

    foreach ($fluxo as $f) {
      $pdf->Cell(35,8, $f['data_evento'] ? date('d/m/Y H:i', strtotime($f['data_evento'])) : '—',1,0,'C');
      $pdf->Cell(40,8, utf8_decode($f['etapa'] ?: '—'),1,0);
      $pdf->Cell(45,8, utf8_decode($f['usuario'] ?: '—'),1,0);
      $obs = mb_strimwidth($f['observacao'] ?? '—', 0, 120, '…', 'UTF-8');
      $pdf->Cell(70,8, utf8_decode($obs),1,1);
    }
  }

  $fname = 'relatorio_'.$proc['numero_processo'].'.pdf';
  $pdf->Output('I', $fname);
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Erro ao gerar relatório.';
}
