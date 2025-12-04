<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../templates/config.php';

if (!$pdo) {
  http_response_code(500);
  exit('PDO local indisponível (ver config.php).');
}

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header("Location: ../index.php");
  exit;
}

$nomeUsuario  = $_SESSION['nome']  ?? '';
$setorUsuario = $_SESSION['setor'] ?? '';
$userId       = $_SESSION['g_id']  ?? 0;

// BUSCAR PROCESSOS DO SETOR DO USUÁRIO
$processos = [];
try {
  $sql = $pdo->prepare("
    SELECT id, numero_processo
    FROM novo_processo
    WHERE numero_processo IS NOT NULL AND numero_processo <> ''
    ORDER BY id DESC
");
  $sql->execute();
  $processos = $sql->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('Erro ao buscar processos: ' . $e->getMessage());
}

$mensagem = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $processo = trim($_POST['processo'] ?? "");
    $motivo   = trim($_POST['motivo']   ?? "");

    if ($processo === "" || $motivo === "") {
        $mensagem = "Preencha todos os campos.";
    } else {
        try {
          $stmt = $pdo->prepare("
              INSERT INTO solicitacao_exclusao
              (id_usuario, processo, motivo, criado_em)
              VALUES (?, ?, ?, NOW())
          ");
          $stmt->execute([$userId, $processo, $motivo]);

          $mensagem = "Sua solicitação foi enviada com sucesso!";
        } catch (Throwable $e) {
          $mensagem = "Erro ao salvar a solicitação: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Solicitar Exclusão de Processo</title>
</head>
<body class="bg-gray-100">

  <div class="max-w-2xl mx-auto mt-10 bg-white shadow-lg p-8 rounded-xl">

    <h1 class="text-2xl font-bold mb-6 text-blue-700">Solicitar Exclusão de Processo</h1>

    <?php if (!empty($mensagem)): ?>
      <div class="mb-4 p-3 rounded bg-green-100 text-green-800">
        <?= htmlspecialchars($mensagem) ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">

      <div>
        <label class="block mb-1 font-medium">Qual processo deseja excluir?</label>
        <select name="processo" required class="w-full border rounded px-3 py-2">
        <option value="">Selecione…</option>

        <?php foreach ($processos as $p): ?>
            <option value="<?= $p['numero_processo'] ?>">
            <?= $p['numero_processo'] ?>
            </option>
        <?php endforeach; ?>
        </select>

      </div>

      <div>
        <label class="block mb-1 font-medium">Explique o motivo da exclusão</label>
        <textarea 
            name="motivo" 
            rows="4" 
            class="w-full border rounded px-3 py-2"
            placeholder="Descreva o porquê deseja excluir este processo..."
            required
        ></textarea>
      </div>

      <div class="flex justify-end gap-3">
        <a href="../templates/home.php" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">
          Cancelar
        </a>

        <button 
          class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700"
          type="submit"
        >
          Enviar Solicitação
        </button>
      </div>

    </form>
  </div>

</body>
</html>
