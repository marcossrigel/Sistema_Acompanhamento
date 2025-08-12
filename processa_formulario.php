<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: formulario.php');
    exit;
}

require_once __DIR__ . '/config.php';
$conn->set_charset('utf8mb4');
date_default_timezone_set('America/Recife');

function null_if_empty($v) {
    $v = isset($v) ? trim($v) : null;
    return ($v === '' ? null : $v);
}

$sei              = $_POST['sei'] ?? '';
$codigo           = $_POST['codigo'] ?? '';
$setor            = $_POST['setor'] ?? '';
$responsavel      = $_POST['responsavel'] ?? '';
$data_solicitacao = $_POST['data_solicitacao'] ?? '';
$data_liberacao   = $_POST['data_liberacao'] ?? '';
$tempo_medio      = $_POST['tempo_medio'] ?? '';
$tempo_real       = $_POST['tempo_real'] ?? '';

$erros = [];
if ($sei === '')              { $erros[] = 'SEI é obrigatório.'; }
if ($codigo === '')           { $erros[] = 'Código é obrigatório.'; }
if ($setor === '')            { $erros[] = 'Setor é obrigatório.'; }
if ($responsavel === '')      { $erros[] = 'Responsável é obrigatório.'; }
if ($data_solicitacao === '') { $erros[] = 'Data de Solicitação é obrigatória.'; }

if ($erros) {
    $msg = urlencode(implode(' ', $erros));
    header("Location: formulario.php?mensagem=erro&detalhe={$msg}");
    exit;
}

$data_liberacao = null_if_empty($data_liberacao);
$tempo_medio    = null_if_empty($tempo_medio);
$tempo_real     = null_if_empty($tempo_real);
$demanda = $_POST['demanda'] ?? null;

$sql = "INSERT INTO solicitacoes (sei, codigo, setor, responsavel, data_solicitacao, data_liberacao, tempo_medio, tempo_real, demanda, data_registro)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssss", $sei, $codigo, $setor, $responsavel, $data_solicitacao, $data_liberacao, $tempo_medio, $tempo_real, $demanda);

if ($stmt->execute()) {
    header("Location: home.php?mensagem=sucesso");
} else {
    $msg = urlencode('Erro ao salvar: ' . $stmt->error);
    header("Location: formulario.php?mensagem=erro&detalhe={$msg}");
}

$stmt->close();
$conn->close();
