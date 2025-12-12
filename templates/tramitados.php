<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php');
  exit;
}

require_once __DIR__ . '/config.php';

$SETOR_FINAL = 'GFIN - Gerência Financeira';
try {
  $st = $connLocal->prepare("SELECT nome FROM setores WHERE ativo=1 AND is_finalizador=1 LIMIT 1");
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  if (!empty($r['nome'])) $SETOR_FINAL = $r['nome'];
} catch (Throwable $e) { /* mantém fallback */ }

$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
$nome  = htmlspecialchars($_SESSION['nome']  ?? '',  ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CEHAB - Tramitados</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/encaminhado.css"> <!-- reaproveitando o mesmo CSS -->
</head>
<body>

  <header class="site-header">
    <div class="container site-header__row">
      <a href="home.php" class="brand">
        <i class="fas fa-sitemap" style="font-size:28px; color:#2563eb;"></i>
        <h1 class="brand__title">CEHAB - Acompanhamento de Processos</h1>
      </a>
      <div class="header-actions">
        <a href="encaminhado.php" class="btn">
          <i class="fa-solid fa-share-from-square"></i> Encaminhados
        </a>
        <span class="badge-primary">
          <i class="fa-solid fa-right-left"></i> Tramitados
        </span>
        <a href="home.php" class="btn">
          <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>
      </div>
    </div>
  </header>

  <main class="section">
    <div class="container">
      <div class="card">
        <div class="user-sector">
          <i class="fas fa-building" style="color:#6b7280;"></i>
          <span>Setor do usuário:</span>
          <span class="chip"><?= $setor ?></span>
        </div>

        <h2 class="title">Processos Tramitados</h2>

        <form id="frmBusca" class="flex items-center gap-2 mb-4" action="" method="GET">
          <div class="flex items-center w-full max-w-3xl border rounded-full pl-4 pr-2 py-2 bg-white">
            <i class="fa-solid fa-magnifying-glass mr-2 opacity-70"></i>
            <input
              id="searchNumero"
              name="numero"
              type="text"
              inputmode="numeric"
              pattern="[0-9./-]*"
              placeholder="Digite o nº do contrato/processo (ex.: 4561184878/4664-68)"
              class="w-full outline-none"
              value="<?= htmlspecialchars($_GET['numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            />
            <button id="btnBuscar" class="ml-2 rounded-full px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 transition" type="submit">
              Pesquisar
            </button>
          </div>
          <button id="btnLimpar" class="btn" type="button" title="Limpar">Limpar</button>
        </form>

        <div id="encList" class="grid"></div>
      </div>
    </div>
  </main>

  <!-- você pode reaproveitar os mesmos modais de detalhes / ações se quiser,
       ou, se em TRAMITADOS for só consulta, pode remover os blocos de encaminhar/finalizar -->

  <div id="detailsModal" class="modal-backdrop hidden">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Detalhes do Processo</h3>
        <button id="closeDetails" class="modal__close" aria-label="Fechar">
          <i class="fa-solid fa-xmark" style="font-size:20px;"></i>
        </button>
      </div>

      <div class="modal__body">
        <div class="modal-grid">
          <div class="flow-col">
            <h4 class="flow-title">Histórico e Fluxo do Processo</h4>
            <div id="flowList" class="flow-list"></div>
          </div>

          <aside>
            <div class="sidebar-box">
              <h5 class="sidebar-title">Informações Gerais</h5>
              <div class="info-list">
                <p><span class="info-label">Número:</span> <span id="d_num" class="font-medium">—</span></p>
                <p><span class="info-label">Nome do processo:</span> <span id="d_nome" class="font-medium">—</span></p>
                <p><span class="info-label">Setor Demandante:</span> <span id="d_setor" class="font-medium">—</span></p>
                <p><span class="info-label">Tipos:</span> <span id="d_tipos" class="font-medium">—</span></p>
                <p id="d_outros_row" class="hidden">
                  <span class="info-label">Outros:</span> <span id="d_outros" class="font-medium">—</span>
                </p>
                <p><span class="info-label">Descrição:</span> <span id="d_desc" class="font-medium break-words">—</span></p>
                <p><span class="info-label">Criado em:</span> <span id="d_dt" class="font-medium">—</span></p>
              </div>
            </div>

            <div style="margin-top:12px; font-size:14px;">
              <p>
                <span class="info-label">Enviar para:</span>
                <span id="d_dest" class="font-medium">—</span>
              </p>
            </div>

            <!-- BLOCO FINALIZAR (aparece para GFIN quando está no GFIN) -->
            <div id="finalizarBlock" class="encaminhar-block hidden">
              <p class="label" style="margin-bottom:10px;">Finalizar processo</p>
              <button id="btnFinalizarProcesso" class="btn--primary" type="button">
                <i class="fa-solid fa-flag-checkered"></i> Finalizar processo
              </button>
            </div>

            <!-- BLOCO ENCAMINHAR (padrão) -->
            <div id="encBlock" class="encaminhar-block">
              <label class="label">Encaminhar para</label>
              <select id="nextSector" class="select">
                <option value="" selected disabled>Selecione o próximo setor.</option>
              </select>
              <button id="btnEncaminhar" class="btn--primary" type="button">Encaminhar</button>
            </div>

            <div style="margin-top:8px;">
              <button id="btnAcoes" class="btn--ghost" type="button">Ações internas</button>
            </div>
          </aside>
        </div>
      </div>

      <div class="modal__footer">
        <button id="okDetails" class="btn--ok" type="button">OK</button>
      </div>
    </div>
  </div>

  <script>
    window.PAGE_MODE   = 'tramitados';
    window.MY_SETOR    = <?= json_encode($_SESSION['setor'] ?? '') ?>;
    window.FINAL_SECTOR= <?= json_encode($SETOR_FINAL) ?>;
</script>
    <script src="../js/encaminhado.js?v=20251212_1"></script>
</body>
</html>
