<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php');
  exit;
}

$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
$nome  = htmlspecialchars($_SESSION['nome']  ?? '',  ENT_QUOTES, 'UTF-8');
$SETOR_FINAL = 'GFIN - Gerência Financeira';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CEHAB - Encaminhados</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/encaminhado.css">
</head>
<body>

  <header class="site-header">
    <div class="container site-header__row">
      <a href="home.php" class="brand">
        <i class="fas fa-sitemap" style="font-size:28px; color:#2563eb;"></i>
        <h1 class="brand__title">CEHAB - Acompanhamento de Processos</h1>
      </a>
      <div class="header-actions">
        <span class="badge-primary">
          <i class="fa-regular fa-share-from-square"></i> Encaminhados
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

        <h2 class="title">Processos Encaminhados</h2>
        <div id="encList" class="grid"></div>
      </div>
    </div>
  </main>

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

            <!-- BLOCO FINALIZAR (aparece só para GFIN quando o processo está no GFIN) -->
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
                <option>DAF - Diretoria de Administração e Finanças</option>
                <option>DOHDU - Diretoria de Obras</option>
                <option>CELOE I - Comissão de Licitação I</option>
                <option>CELOE II - Comissão de Licitação II</option>
                <option>CELOSE - Comissão de Licitação</option>
                <option>GCOMP - Gerência de Compras</option>
                <option>GOP - Gerência de Orçamento e Planejamento</option>
                <option>GFIN - Gerência Financeira</option>
                <option>GCONT - Gerência de Contabilidade</option>
                <option>DP - Diretoria da Presidência</option>
                <option>GAD - Gerência Administrativa</option>
                <option>GAC - Gerência de Acompanhamento de Contratos</option>
                <option>CGAB - Chefia de Gabinete</option>
                <option>DOE - Diretoria de Obras Estratégicas</option>
                <option>DSU - Diretoria de Obras de Saúde</option>
                <option>DSG - Diretoria de Obras de Segurança</option>
                <option>DED - Diretoria de Obras de Educação</option>
                <option>SPO - Superintendência de Projetos de Obras</option>
                <option>SUAJ - Superintendência de Apoio Jurídico</option>
                <option>SUFIN - Superintendência Financeira</option>
                <option>GAJ - Gerência de Apoio Jurídico</option>
                <option>SUPLAN - Superintendência de Planejamento</option>
                <option>DPH - Diretoria de Projetos Habitacionais</option>
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

  <div id="acoesModal" class="inner-modal-backdrop hidden">
    <div class="inner-modal">
      <div class="inner-modal__header">
        <h2 class="modal__title" style="font-size:18px;">Ações Internas do Setor</h2>
        <button id="fecharAcoes" class="modal__close" aria-label="Fechar">
          <i class="fa-solid fa-xmark" style="font-size:20px;"></i>
        </button>
      </div>

      <ul id="acoesList" class="inner-modal__list"></ul>

      <label class="label" style="margin-bottom:6px;">Nova ação (visível a todos):</label>
      <textarea id="acaoTexto" class="textarea" rows="3" placeholder="Ex.: Tive um problema com tal emenda"></textarea>

      <div class="btn-row">
        <button id="cancelarAcoes" class="btn--muted" type="button">Cancelar</button>
        <button id="salvarAcao" class="btn--blue" type="button">Salvar ação</button>
      </div>
    </div>
  </div>

  <div id="finalizarModal" class="finish-backdrop hidden">
    <div class="finish-card">
      <h2 class="finish-title">Finalizar Etapa</h2>
      <label class="label" style="margin-bottom:6px;">Descreva a ação finalizadora:</label>
      <textarea id="acaoFinalizadora" class="textarea" rows="3" placeholder="Ex: Finalizando o fluxo desse processo"></textarea>
      <div class="finish-actions">
        <button id="cancelarFinalizar" class="btn--muted" type="button">Cancelar</button>
        <button id="confirmarFinalizar" class="btn--blue" type="button">Confirmar e Avançar</button>
      </div>
    </div>
  </div>

  <script>
    window.MY_SETOR      = <?= json_encode($_SESSION['setor'] ?? '') ?>;
    window.FINAL_SECTOR  = <?= json_encode($SETOR_FINAL) ?>;
  </script>
  <script src="../js/encaminhado.js"></script>
</body>
</html>
