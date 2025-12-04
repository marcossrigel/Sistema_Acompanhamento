<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../templates/config.php';

if (!$pdo) {
  http_response_code(500);
  exit('PDO local indisponível (ver config.php).');
}

// precisa estar autenticado
if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header("Location: ../index.php");
  exit;
}

// somente Bruno (id_usuario_cehab_online = 600)
$userId = (int)($_SESSION['g_id'] ?? 0);
if ($userId !== 600) {
  header("Location: ../templates/home.php");
  exit;
}

$nome  = $_SESSION['nome']  ?? '';
$setor = $_SESSION['setor'] ?? '';
$tipo  = $_SESSION['tipo']  ?? 'admin';

$flashMsg = "";

// --- TRATAMENTO DE EXCLUSÃO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $procNumero = trim($_POST['proc_numero'] ?? '');
    $solicId    = (int)($_POST['solic_id'] ?? 0);

    if ($procNumero !== '' && $solicId > 0) {
        try {
            $pdo->beginTransaction();

            // apaga da tabela de processos
            $del1 = $pdo->prepare("DELETE FROM novo_processo WHERE numero_processo = ?");
            $del1->execute([$procNumero]);

            // apaga da tabela de solicitações
            $del2 = $pdo->prepare("DELETE FROM solicitacao_exclusao WHERE id = ?");
            $del2->execute([$solicId]);

            $pdo->commit();
            $flashMsg = "Processo {$procNumero} e respectiva solicitação foram excluídos com sucesso.";
        } catch (Throwable $e) {
            $pdo->rollBack();
            $flashMsg = "Erro ao excluir processo: " . $e->getMessage();
            error_log('Erro exclusão dupla: ' . $e->getMessage());
        }
    } else {
        $flashMsg = "Dados insuficientes para exclusão.";
    }
}

// buscar todas as solicitações de exclusão (após possível exclusão)
$solicitacoes = [];
try {
  $stmt = $pdo->query("
    SELECT
      id,
      id_usuario,
      processo,
      motivo,
      criado_em,
      DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') AS criado_em_fmt
    FROM solicitacao_exclusao
    ORDER BY criado_em DESC
  ");
  $solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('Erro ao buscar solicitacoes_exclusao: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Solicitações de Exclusão</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/home.css">
</head>
<body class="bg-gray-100">

  <!-- TOP BAR -->
  <header class="site-header">
    <div class="container site-header__row">
      <a href="../templates/home.php" class="brand">
        <i class="fas fa-sitemap" style="font-size:28px; color:#2563eb;"></i>
        <h1 class="brand__title">CEHAB - Acompanhamento de Processos</h1>
      </a>

      <div class="flex items-center gap-2">
        <a href="../templates/home.php" class="btn btn--outline-blue">
          <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>
      </div>
    </div>
  </header>

  <main class="section">
    <div class="container">

      <div class="card">
        <h2 class="title mb-4">Solicitações de Exclusão de Processos</h2>

        <?php if ($flashMsg): ?>
          <div class="mb-4 p-3 rounded bg-green-100 text-green-800 text-sm">
            <?= htmlspecialchars($flashMsg) ?>
          </div>
        <?php endif; ?>

        <?php if (empty($solicitacoes)): ?>
          <p class="text-gray-500">Nenhuma solicitação de exclusão registrada.</p>
        <?php else: ?>
          <div class="grid gap-3 md:grid-cols-2">
            <?php foreach ($solicitacoes as $s): ?>
              
              <div class="bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition">

              <!-- Linha superior ID + Lixeira -->
              <div class="flex justify-between items-center mb-2">
                <p class="text-xs text-gray-400">ID #<?= htmlspecialchars($s['id']) ?></p>

                <button
                  type="button"
                  class="text-red-500 hover:text-red-700"
                  onclick="openDeleteModal(
                    '<?= htmlspecialchars($s['processo'], ENT_QUOTES, 'UTF-8') ?>',
                    '<?= (int)$s['id'] ?>'
                  )"
                  title="Excluir processo e solicitação"
                >
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>

              <!-- Conteúdo clicável -->
              <button
                type="button"
                class="w-full text-left"
                data-id="<?= htmlspecialchars($s['id']) ?>"
                data-processo="<?= htmlspecialchars($s['processo']) ?>"
                data-motivo="<?= htmlspecialchars($s['motivo']) ?>"
                data-criado="<?= htmlspecialchars($s['criado_em_fmt']) ?>"
                data-usuario="<?= htmlspecialchars($s['id_usuario']) ?>"
                onclick="openSolicModal(this)"
              >
                <p class="font-semibold text-gray-800 mb-1">
                  Processo: <?= htmlspecialchars($s['processo']) ?>
                </p>

                <p class="text-sm text-gray-600 line-clamp-2 mb-2">
                  <?= nl2br(htmlspecialchars($s['motivo'])) ?>
                </p>

                <p class="text-xs text-gray-400">
                  Criado em: <?= htmlspecialchars($s['criado_em_fmt']) ?>
                </p>
              </button>
            </div>


            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <!-- MODAL DETALHES -->
  <div id="solicModal" class="modal-backdrop hidden">
    <div class="modal modal--sm">
      <div class="modal__header">
        <h3 class="modal__title">Detalhes da Solicitação</h3>
        <button id="closeSolicModal" class="modal__close" aria-label="Fechar">
          <i class="fa-solid fa-xmark" style="font-size:20px;"></i>
        </button>
      </div>

      <div class="modal__body">
        <div class="space-y-2 text-sm">
          <p><span class="info-label">Processo:</span> <span id="m_processo" class="font-medium"></span></p>
          <p><span class="info-label">Motivo:</span></p>
          <p id="m_motivo" class="whitespace-pre-line border rounded p-2 bg-gray-50 text-gray-700"></p>
          <p><span class="info-label">Data de criação:</span> <span id="m_criado" class="font-medium"></span></p>
          <p><span class="info-label">ID do usuário:</span> <span id="m_usuario" class="font-medium"></span></p>
        </div>

        <div class="modal__footer" style="text-align:right; margin-top:16px;">
          <button id="okSolicModal" class="btn--ok" type="button">OK</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL CONFIRMAÇÃO EXCLUSÃO -->
  <div id="confirmModal" class="modal-backdrop hidden">
    <div class="modal modal--sm">
      <div class="modal__header">
        <h3 class="modal__title">Confirmar Exclusão</h3>
        <button id="closeConfirmModal" class="modal__close" aria-label="Fechar">
          <i class="fa-solid fa-xmark" style="font-size:20px;"></i>
        </button>
      </div>

      <div class="modal__body">
        <p class="mb-4 text-sm text-gray-700">
          Deseja realmente excluir do banco o processo abaixo e a respectiva solicitação?
        </p>
        <p class="mb-4 text-sm">
          <span class="info-label">Processo:</span>
          <span id="c_processo" class="font-medium"></span>
        </p>

        <div class="modal__footer" style="text-align:right; margin-top:16px;">
          <button
            type="button"
            id="cancelDelete"
            class="text-gray-600 hover:text-gray-800 font-medium mr-4"
            style="padding:4px 10px;"
          >
            Cancelar
          </button>
          <button type="button" id="confirmDelete" class="btn--danger-outline">Confirmar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- FORMULÁRIO INVISÍVEL PARA EXCLUSÃO -->
  <form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="acao" value="excluir">
    <input type="hidden" name="proc_numero" id="del_proc_numero">
    <input type="hidden" name="solic_id" id="del_solic_id">
  </form>

  <script>
    // Modal de detalhes
    function openSolicModal(el) {
      document.getElementById('m_processo').textContent = el.dataset.processo || '';
      document.getElementById('m_motivo').textContent   = el.dataset.motivo   || '';
      document.getElementById('m_criado').textContent   = el.dataset.criado   || '';
      document.getElementById('m_usuario').textContent  = el.dataset.usuario  || '';

      document.getElementById('solicModal').classList.remove('hidden');
    }

    function closeSolic() {
      document.getElementById('solicModal').classList.add('hidden');
    }

    document.getElementById('closeSolicModal')?.addEventListener('click', closeSolic);
    document.getElementById('okSolicModal')?.addEventListener('click', closeSolic);

    function openDeleteModal(processo, solicId) {
      document.getElementById('c_processo').textContent = processo;
      document.getElementById('del_proc_numero').value  = processo;
      document.getElementById('del_solic_id').value     = solicId;

      const modal = document.getElementById('confirmModal');
      modal.classList.remove('hidden');
      modal.style.display = 'flex';   // garante que apareça
    }

    function closeDeleteModal() {
      const modal = document.getElementById('confirmModal');
      modal.classList.add('hidden');
      modal.style.display = 'none';   // garante que suma
    }

    document.getElementById('closeConfirmModal')?.addEventListener('click', closeDeleteModal);
    document.getElementById('cancelDelete')?.addEventListener('click', closeDeleteModal);

    document.getElementById('confirmDelete')?.addEventListener('click', function () {
      document.getElementById('deleteForm').submit();
    });


    // ESC fecha os modais
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeSolic();
        closeDeleteModal();
      }
    });
  </script>

</body>
</html>
