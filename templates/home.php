<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');

if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
  header('Location: ../index.php');
  exit;
}
$setor = htmlspecialchars($_SESSION['setor'] ?? '—', ENT_QUOTES, 'UTF-8');
$nome  = htmlspecialchars($_SESSION['nome']  ?? '',  ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CEHAB - Acompanhamento de Processos</title>

  <!-- Mantido para não quebrar o HTML gerado dinamicamente por home.js (usa utilitárias) -->
  <script src="https://cdn.tailwindcss.com"></script>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <style>
    :root{
      --bg:#f0f2f5;
      --white:#fff;
      --text:#1f2937;   /* gray-800 */
      --muted:#6b7280;  /* gray-500 */
      --muted-700:#374151; /* gray-700 */
      --blue:#2563eb;   /* blue-600 */
      --blue-700:#1d4ed8;
      --indigo-50:#eef2ff;
      --indigo-700:#4338ca;
      --gray-50:#f9fafb;
      --gray-100:#f3f4f6;
      --gray-200:#e5e7eb;
      --gray-300:#d1d5db;
      --gray-400:#9ca3af;
      --red-50:#fef2f2;
      --red-100:#fee2e2;
      --red-300:#fca5a5;
      --red-700:#b91c1c;
      --radius:12px;
      --shadow:0 1px 2px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.08);
    }

    /* Base */
    html,body{height:100%;}
    body{
      font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial,'Noto Sans';
      background:var(--bg); color:var(--text); margin:0;
      -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
    }
    a{ color:inherit; text-decoration:none; }

    /* Utilizadas pelo JS para abrir/fechar modais */
    .hidden{ display:none !important; }
    .flex{ display:flex !important; }

    /* Container */
    .container{
      width:100%; max-width:1120px; margin:0 auto;
      padding-left:16px; padding-right:16px;
    }

    /* Header / Top bar */
    .site-header{ background:var(--white); box-shadow:0 1px 2px rgba(0,0,0,.06); }
    .site-header__row{
      display:flex; align-items:center; justify-content:space-between; padding:16px 0;
    }
    .brand{ display:flex; align-items:center; gap:12px; }
    .brand__title{ font-size:22px; font-weight:700; color:var(--text); }
    .brand:hover .brand__title{ color:#1e40af; }

    .header-actions{ display:flex; align-items:center; gap:8px; }
    .btn{
      display:inline-flex; align-items:center; gap:8px;
      font-weight:700; padding:10px 16px; border-radius:10px;
      border:1px solid var(--gray-200); background:var(--white); color:#111827;
      transition:.2s ease; box-shadow:0 1px 2px rgba(0,0,0,.05);
    }
    .btn:hover{ background:#f3f4f6; }
    .btn--primary{
      border:0; background:var(--blue); color:#fff;
      box-shadow:0 1px 2px rgba(0,0,0,.05);
    }
    .btn--primary:hover{ background:var(--blue-700); }
    .btn--outline-blue{
      border:1px solid var(--blue); color:var(--blue); background:var(--white);
    }
    .btn--outline-blue:hover{ background:#eff6ff; }
    .btn--danger-outline{
      border:1px solid var(--red-300); color:var(--red-700); background:var(--red-50);
    }
    .btn--danger-outline:hover{ background:var(--red-100); }

    /* Main */
    .section{ padding:32px 0; }
    .card{
      background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow);
      padding:24px;
    }
    .user-sector{
      display:flex; align-items:center; gap:8px; color:var(--muted-700); font-size:14px; margin-bottom:12px;
    }
    .chip{
      display:inline-flex; align-items:center; padding:2px 8px; border-radius:9999px;
      background:var(--indigo-50); color:var(--indigo-700); font-weight:600;
    }
    .title{ font-size:20px; font-weight:600; color:#374151; margin:0 0 16px; }

    /* Grid de processos (o conteúdo é montado via JS) */
    .grid{ display:grid; grid-template-columns:1fr; gap:16px; }
    @media(min-width:768px){ .grid{ grid-template-columns:repeat(2,1fr);} }
    @media(min-width:1024px){ .grid{ grid-template-columns:repeat(3,1fr);} }

    /* Modal backdrop padrão */
    .modal-backdrop{
      position:fixed; inset:0; background:rgba(0,0,0,.3);
      z-index:50; display:none; align-items:center; justify-content:center;
    }

    /* Modal genérico (cartão) */
    .modal{
      background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
      width:100%; max-width:960px; margin:16px; overflow:hidden;
    }
    .modal--sm{ max-width:640px; }
    .modal__header{
      padding:20px; border-bottom:1px solid var(--gray-200);
      display:flex; align-items:center; justify-content:space-between;
    }
    .modal__title{ font-size:20px; font-weight:600; }
    .modal__close{ color:#6b7280; background:transparent; border:0; cursor:pointer; }
    .modal__close:hover{ color:#374151; }
    .modal__body{ padding:24px; }
    .modal__footer{
      padding:20px; border-top:1px solid var(--gray-200); text-align:right;
    }
    .btn--ok{
      background:var(--blue); color:#fff; padding:10px 16px; border-radius:10px; border:0;
      font-weight:600; cursor:pointer;
    }
    .btn--ok:hover{ background:var(--blue-700); }

    /* Modal "Novo Processo" (form) */
    .form-grid{ display:flex; flex-direction:column; gap:16px; }
    .label{ display:block; font-size:14px; color:#374151; font-weight:500; margin-bottom:6px; }
    .input, .select, .textarea{
      width:100%; border:1px solid var(--gray-300); border-radius:8px; padding:10px 12px; background:#fff;
      font-family:inherit;
    }
    .input[readonly]{ border-color:var(--gray-200); background:var(--gray-50); color:#6b7280; cursor:not-allowed; }

    .actions-right{ display:flex; justify-content:flex-end; gap:8px; padding-top:8px; }
    .btn--muted{ background:var(--gray-200); border:0; border-radius:10px; padding:10px 16px; font-weight:700; cursor:pointer; }
    .btn--blue{ background:var(--blue); color:#fff; border:0; border-radius:10px; padding:10px 16px; font-weight:700; cursor:pointer; }
    .btn--blue:hover{ background:var(--blue-700); }

    /* Modal de sucesso */
    .modal-center{ text-align:center; }
    .success-title{ font-size:18px; font-weight:600; margin:0 0 16px; }

    /* Modal Detalhes com rolagem vertical */
    .modal--details{
      max-height:90vh; display:flex; flex-direction:column;
    }
    .modal__body--scroll{ flex:1; overflow-y:auto; }

    /* Grid interno do modal de detalhes */
    .modal-grid{
      display:grid; grid-template-columns:1fr; gap:24px; min-width:0;
    }
    @media(min-width:1024px){ .modal-grid{ grid-template-columns:2fr 1fr; } }

    .flow-col{ overflow-y:auto; padding-right:8px; }
    .flow-title{ font-weight:600; margin:0 0 12px; color:#1f2937; font-size:16px; }
    .flow-list{ display:flex; flex-direction:column; gap:12px; }

    .sidebar-box{
      background:#f9fafb; border:1px solid var(--gray-200);
      border-radius:12px; padding:16px;
    }
    .sidebar-title{ font-weight:600; margin:0 0 12px; color:#1f2937; }
    .info-list{ display:flex; flex-direction:column; gap:6px; font-size:14px; }
    .info-label{ color:var(--muted); }
    .break-words{ word-wrap:break-word; overflow-wrap:anywhere; }

    /* Scrollbars suaves no corpo do modal */
    #detailsModal .modal__body--scroll::-webkit-scrollbar,
    #detailsModal .flow-col::-webkit-scrollbar { width:8px; }
    #detailsModal .modal__body--scroll::-webkit-scrollbar-thumb,
    #detailsModal .flow-col::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:9999px; }
    #detailsModal .modal__body--scroll:hover::-webkit-scrollbar-thumb,
    #detailsModal .flow-col:hover::-webkit-scrollbar-thumb { background:#94a3b8; }

  </style>
</head>
<body>

  <!-- HEADER / TOP BAR -->
  <header class="site-header">
    <div class="container site-header__row">
      <div class="brand">
        <i class="fas fa-sitemap" style="font-size:28px; color:#2563eb;"></i>
        <h1 class="brand__title">CEHAB - Acompanhamento de Processos</h1>
      </div>
      <div class="header-actions">
        <a href="encaminhado.php" class="btn btn--outline-blue">
          <i class="fa-regular fa-share-from-square"></i> Encaminhados
        </a>

        <!-- ABRIR MODAL -->
        <button id="newProcessBtn" class="btn btn--primary">
          <i class="fas fa-plus"></i> Novo Processo
        </button>

        <!-- Sair -->
        <a href="../index.php?logout=1&go=getic" class="btn btn--danger-outline">
          <i class="fa-solid fa-right-from-bracket"></i> Sair
        </a>
      </div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="section">
    <div class="container">
      <div class="card">
        <!-- Linha do setor do usuário -->
        <div class="user-sector">
          <i class="fas fa-building" style="color:#6b7280;"></i>
          <span>Setor do usuário:</span>
          <span class="chip"><?= $setor ?></span>
        </div>

        <h2 class="title">Processos em Andamento</h2>

        <div id="processList" class="grid"></div>
      </div>
    </div>
  </main>

  <!-- MODAL: NOVO PROCESSO -->
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
            <input id="processNumber" type="text" class="input" required>
          </div>

          <div>
            <label class="label">Setor Demandante</label>
            <input id="requestingSectorModal" type="text" value="<?= $setor ?>" readonly class="input">
          </div>

          <div>
            <label class="label">Enviar para</label>
            <select id="destSector" required class="select">
              <option value="" selected disabled>Selecione o setor.</option>
              <!-- A lista é populada pelo JS -->
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

  <!-- MODAL DE SUCESSO -->
  <div id="successModal" class="modal-backdrop hidden">
    <div class="modal modal--sm">
      <div class="modal__body modal-center">
        <h3 class="success-title">Processo salvo com sucesso!</h3>
        <button id="successOkBtn" class="btn btn--primary">OK</button>
      </div>
    </div>
  </div>

  <!-- MODAL DETALHES (com fluxo) -->
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
          <!-- Coluna esquerda: Fluxo -->
          <div class="flow-col">
            <h4 class="flow-title">Histórico e Fluxo do Processo</h4>
            <div id="flowList" class="flow-list"></div>
          </div>

          <!-- Coluna direita: Informações Gerais -->
          <aside>
            <div class="sidebar-box">
              <h5 class="sidebar-title">Informações Gerais</h5>
              <div class="info-list">
                <p><span class="info-label">Número:</span> <span id="d_num" class="font-medium"></span></p>
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
    // Pequeno helper: alguns botões de fechar do modal "Novo Processo" convivem
    // com o mesmo id em versões anteriores. Garantimos fechamento pelos dois.
    document.getElementById('closeModalBtn_ghost')?.addEventListener('click', () => {
      document.getElementById('processModal')?.classList.add('hidden');
    });

    window.APP = {
      MY_SETOR: <?= json_encode($_SESSION['setor'] ?? '') ?>,
      USER_NAME: <?= json_encode($_SESSION['nome']  ?? '') ?>,
      GID:       <?= json_encode($_SESSION['g_id']  ?? '') ?>
    };
  </script>
  <script src="../js/home.js?v=3"></script>
</body>
</html>
