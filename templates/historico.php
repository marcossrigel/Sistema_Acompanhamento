<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$token = trim($_GET['access_dinamic'] ?? '');
if ($token === '') { http_response_code(401); exit('Token inválido.'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Histórico</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/painel.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <h1>Histórico</h1>
    <div class="vazio">Em breve exibiremos as demandas finalizadas/arquivadas.</div>
  </div>
</body>
</html>
