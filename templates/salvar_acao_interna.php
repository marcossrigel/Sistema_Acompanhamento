<?php
require_once 'config.php'; // ou ajuste o caminho conforme necessário
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $texto = $_POST['texto'] ?? '';
    $usuario = $_SESSION['usuario_cehab_online'] ?? null;

    if ($usuario && !empty($texto)) {
        $stmt = $connRemoto->prepare("INSERT INTO acao_interna (usuario_cehab_online, texto) VALUES (?, ?)");
        $stmt->bind_param("is", $usuario, $texto);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos.']);
    }
}
?>
