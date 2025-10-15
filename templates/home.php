<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php'); exit;
}

if (empty($_SESSION['tipo'])) {
  try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=sistema_acompanhamento;charset=utf8mb4','root','',[
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
    $st = $pdo->prepare('SELECT tipo FROM usuarios WHERE id_usuario_cehab_online = :gid LIMIT 1');
    $st->execute([':gid' => $_SESSION['g_id']]);
    $t = $st->fetchColumn();
    $_SESSION['tipo'] = $t ?: 'comum';
  } catch (Throwable $e) {
    $_SESSION['tipo'] = 'comum';
  }
}

$setor   = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
$nome    = htmlspecialchars($_SESSION['nome']  ?? '',  ENT_QUOTES, 'UTF-8');
$isAdmin = (($_SESSION['tipo'] ?? '') === 'admin'); // <-- controla o botão

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CEHAB - Acompanhamento de Processos</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/home.css">
</head>
<body>

  <header class="site-header">
    <div class="container site-header__row">
      <a href="home.php" class="brand">
        <i class="fas fa-sitemap" style="font-size:28px; color:#2563eb;"></i>
        <h1 class="brand__title">CEHAB - Acompanhamento de Processos</h1>
      </a>

      <div class="flex items-center gap-2">
        <a href="../pages/gerar_relatorio.php" class="btn btn--outline-green">
          <i class="fa-solid fa-file-lines"></i> Gerar Relatório
        </a>

        <?php if ($isAdmin): ?>
        <!-- Botão visível apenas para admin -->
        <a href="../pages/todos.php" class="btn btn--outline-blue">
          <i class="fa-solid fa-layer-group"></i> TODOS
        </a>
        <?php endif; ?>
      </div>

      <div class="header-actions">
        <a href="encaminhado.php" class="btn btn--outline-blue">
          <i class="fa-regular fa-share-from-square"></i> Encaminhados
        </a>

        <button id="newProcessBtn" class="btn btn--primary">
          <i class="fas fa-plus"></i> Novo Processo
        </button>

        <a href="../index.php?logout=1&go=getic" class="btn btn--danger-outline">
          <i class="fa-solid fa-right-from-bracket"></i> Sair
        </a>
      </div>
    </div>
  </header>

  <main class="section">
    <div class="container">
      <div class="card">
        <div class="user-sector">
          <i class="fas fa-building icon-muted"></i>
          <span>Setor do usuário:</span>
          <span class="chip"><?= $setor ?></span>
        </div>

        <h2 class="title">Processos em Andamento</h2>

        <!-- Barra de busca -->
        <form id="frmBuscaHome" class="flex items-center gap-2 mb-4" action="" method="GET">
          <div class="flex items-center w-full max-w-3xl border rounded-full pl-4 pr-2 py-2 bg-white">
            <i class="fa-solid fa-magnifying-glass mr-2 opacity-70"></i>
            <input
              id="searchNumeroHome"
              name="numero"
              type="text"
              inputmode="numeric"
              pattern="[0-9./-]*"
              autocomplete="off"
              placeholder="Digite o nº do contrato/processo (ex.: 4561184878/4664-68)"
              class="w-full outline-none"
              value="<?= htmlspecialchars($_GET['numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              aria-label="Pesquisar por número do contrato/processo"
            />
            <button id="btnBuscarHome" class="ml-2 rounded-full px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 transition" type="submit">
              Pesquisar
            </button>
          </div>
          <button id="btnLimparHome" class="btn" type="button" title="Limpar">Limpar</button>
        </form>

        <div id="processList" class="grid"></div>
      </div>
    </div>
  </main>

  <!-- Modais (inalterados) -->
  <div id="processModal" class="modal-backdrop hidden">
    <div class="modal modal--sm">
      <div class="modal__header">
        <h3 class="modal__title">Novo Processo</h3>
        <button id="closeModalBtn" class="modal__close" aria-label="Fechar">
          <i class="fa-solid fa-xmark" style="font-size:20px;"></i>
        </button>
      </div>

      <form id="processForm" class="modal__body">
        <div class="form-grid">
          <div>
            <label class="label">Número do Processo</label>
            <input id="processNumber"
              type="text"
              class="input"
              required
              pattern="\d{10}\.\d{6}/\d{4}-\d{2}"
              maxlength="25"
              inputmode="numeric"
              autocomplete="off"
              placeholder="Ex: 0060900018.001341/2025-49"
              title="Formato: NNNNNNNNNN.NNNNNN/NNNN-NN">
          </div>

          <div>
            <label class="label">Nome do Processo</label>
            <input id="processName"
                  type="text"
                  class="input"
                  maxlength="150"
                  autocomplete="off"
                  placeholder="Digite o nome do processo…"
                  required>
          </div>

          <div>
            <label class="label">Setor Demandante</label>
            <input id="requestingSectorModal" type="text" value="<?= $setor ?>" readonly class="input">
          </div>

          <div>
            <label class="label">Enviar para</label>
            <select id="destSector" required class="select">
              <option value="" selected disabled>Selecione o setor.</option>
            </select>
          </div>

          <div>
            <label class="label">Tipo de processo</label>

            <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer;">
              <input type="checkbox" name="tipo_proc" value="nova licitação/aquisição">
              <span>nova licitação/aquisição</span>
            </label>

            <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer;">
              <input type="checkbox" name="tipo_proc" value="solicitação de pagamento">
              <span>solicitação de pagamento</span>
            </label>

            <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; cursor:pointer;">
              <input id="tipoOutrosCheck" type="checkbox" name="tipo_proc" value="outros">
              <span>outros</span>
            </label>

            <input id="tipoOutrosInput" type="text" placeholder="Descreva o tipo…" class="input hidden">
          </div>

          <div>
            <label class="label">Descrição</label>
            <textarea id="description" rows="3" class="textarea" placeholder="Descreva o processo..." required></textarea>
          </div>

          <div class="actions-right">
            <button type="button" id="closeModalBtn_ghost" class="btn--muted">Cancelar</button>
            <button type="submit" class="btn--blue">Salvar Processo</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div id="successModal" class="modal-backdrop hidden">
    <div class="modal modal--sm">
      <div class="modal__body modal-center">
        <h3 class="success-title">Processo salvo com sucesso!</h3>
        <button id="successOkBtn" class="btn btn--primary">OK</button>
      </div>
    </div>
  </div>

  <div id="detailsModal" class="modal-backdrop hidden">
    <div class="modal modal--details">
      <div class="modal__header">
        <h3 class="modal__title">Detalhes do Processo</h3>
        <button id="closeDetails" class="modal__close" aria-label="Fechar">
          <i class="fa-solid fa-xmark" style="font-size:20px;"></i>
        </button>
      </div>

      <div class="modal__body modal__body--scroll">
        <div class="modal-grid">
          <div class="flow-col">
            <h4 class="flow-title">Histórico e Fluxo do Processo</h4>
            <div id="flowList" class="flow-list"></div>
          </div>

          <aside>
            <div class="sidebar-box">
              <h5 class="sidebar-title">Informações Gerais</h5>
              <div class="info-list">
                <p><span class="info-label">Número:</span> <span id="d_num" class="font-medium"></span></p>
                <p><span class="info-label">Nome:</span> <span id="d_nome" class="font-medium"></span></p>
                <p><span class="info-label">Setor Demandante:</span> <span id="d_setor" class="font-medium"></span></p>
                <p><span class="info-label">Enviar para:</span> <span id="d_dest" class="font-medium"></span></p>
                <p><span class="info-label">Tipos:</span> <span id="d_tipos" class="font-medium"></span></p>
                <p id="d_outros_row" class="hidden">
                  <span class="info-label">Outros:</span> <span id="d_outros" class="font-medium"></span>
                </p>
                <p><span class="info-label">Descrição:</span> <span id="d_desc" class="font-medium"></span></p>
                <p><span class="info-label">Criado em:</span> <span id="d_dt" class="font-medium"></span></p>
              </div>
            </div>

            <div class="modal__footer" style="padding:0; border:0; text-align:right; margin-top:16px;">
              <button id="okDetails" class="btn--ok" type="button">OK</button>
            </div>
          </aside>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.APP = {
      MY_SETOR: <?= json_encode($_SESSION['setor'] ?? '') ?>,
      USER_NAME: <?= json_encode($_SESSION['nome']  ?? '') ?>,
      GID:       <?= json_encode($_SESSION['g_id']  ?? '') ?>,
      USER_TYPE: <?= json_encode($_SESSION['tipo'] ?? '') ?>
    };
  </script>
  <script src="../js/home.js?v=3"></script>
</body>
</html>
