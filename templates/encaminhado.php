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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CEHAB - Encaminhados</title>

  <!-- Mantido para os elementos gerados via JS que usam utilitárias -->
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
      --emerald-50:#ecfdf5;
      --emerald-700:#047857;
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

    /* Utilizadas pelo seu JS para abrir/fechar modais */
    .hidden{ display:none !important; }
    .flex{ display:flex !important; }

    /* Containers responsivos */
    .container{
      width:100%; max-width:1120px; margin:0 auto;
      padding-left:16px; padding-right:16px;
    }

    /* Header */
    .site-header{ background:var(--white); box-shadow:0 1px 2px rgba(0,0,0,.06); }
    .site-header__row{
      display:flex; align-items:center; justify-content:space-between; padding:16px 0;
    }
    .brand{ display:flex; align-items:center; gap:12px; }
    .brand__title{ font-size:22px; font-weight:700; color:var(--text); }
    .brand:hover .brand__title{ color:#1e40af; } /* hover azul */

    .header-actions{ display:flex; align-items:center; gap:8px; }
    .badge-primary{
      display:inline-flex; align-items:center; gap:8px;
      background:var(--blue); color:#fff; font-weight:700;
      padding:10px 16px; border-radius:10px; box-shadow:0 1px 2px rgba(0,0,0,.05);
      cursor:default;
    }
    .btn{
      display:inline-flex; align-items:center; gap:8px;
      background:var(--gray-100); color:#111827; font-weight:700;
      padding:10px 16px; border-radius:10px; box-shadow:0 1px 2px rgba(0,0,0,.05);
      transition:.2s ease;
      border:1px solid var(--gray-200);
    }
    .btn:hover{ background:#e5e7eb; }

    /* Main card */
    .section{ padding:32px 0; }
    .card{
      background:var(--white); border-radius:var(--radius); box-shadow:var(--shadow);
      padding:24px;
    }
    .user-sector{
      display:flex; align-items:center; gap:8px; color:var(--muted-700); font-size:14px; margin-bottom:12px;
    }
    .chip{
      display:inline-flex; align-items:center;
      padding:2px 8px; border-radius:9999px; background:var(--indigo-50); color:var(--indigo-700);
      font-weight:600;
    }
    .title{ font-size:20px; font-weight:600; color:#374151; margin:0 0 16px; }

    /* Grid de cards dos processos */
    .grid{
      display:grid; grid-template-columns:1fr; gap:16px;
    }
    @media(min-width:768px){ .grid{ grid-template-columns:repeat(2,1fr);} }
    @media(min-width:1024px){ .grid{ grid-template-columns:repeat(3,1fr);} }

    /* Modal (backdrop + card) */
    .modal-backdrop{
      position:fixed; inset:0; background:rgba(0,0,0,.4);
      z-index:50; display:none; align-items:center; justify-content:center;
    }
    .modal{
      background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
      width:100%; max-width:960px; margin:16px; max-height:90vh; display:flex; flex-direction:column;
    }
    .modal__header{
      padding:20px; border-bottom:1px solid var(--gray-200);
      display:flex; align-items:center; justify-content:space-between;
    }
    .modal__title{ font-size:20px; font-weight:600; }
    .modal__close{ color:#6b7280; background:transparent; border:0; cursor:pointer; }
    .modal__close:hover{ color:#374151; }

    .modal__body{
      padding:24px; flex:1; overflow-y:auto;
    }
    /* Grid interno do modal */
    .modal-grid{
      display:grid; grid-template-columns:1fr; gap:24px; min-width:0;
    }
    @media(min-width:1024px){ .modal-grid{ grid-template-columns:2fr 1fr; } }

    .flow-col{ overflow-y:auto; padding-right:8px; }
    .flow-title{ font-weight:600; margin:0 0 12px; color:#1f2937; font-size:16px; }
    .flow-list{ display:flex; flex-direction:column; gap:12px; }

    /* Sidebar de informações */
    .sidebar-box{
      background:#f9fafb; border:1px solid var(--gray-200);
      border-radius:12px; padding:16px;
    }
    .sidebar-title{ font-weight:600; margin:0 0 12px; color:#1f2937; }
    .info-list{ display:flex; flex-direction:column; gap:6px; font-size:14px; }
    .info-label{ color:var(--muted); }
    .break-words{ word-wrap:break-word; overflow-wrap:anywhere; }

    .encaminhar-block{ border-top:1px solid var(--gray-200); padding-top:16px; margin-top:16px; }
    .label{ display:block; font-size:14px; color:#374151; font-weight:500; margin-bottom:6px; }
    .select{
      width:100%; border:1px solid var(--gray-300); border-radius:8px; padding:10px 12px; background:#fff;
    }
    .btn--primary{
      width:100%; background:#059669; color:#fff; font-weight:600; padding:10px 16px; border-radius:8px;
      border:0; cursor:pointer; transition:.2s ease;
    }
    .btn--primary:hover{ background:#047857; }

    .btn--ghost{
      width:100%; background:#fff; color:#374151; font-weight:600; padding:10px 16px; border-radius:8px;
      border:1px solid var(--gray-300); cursor:pointer; transition:.2s ease;
    }
    .btn--ghost:hover{ background:#f9fafb; }

    .modal__footer{
      padding:20px; border-top:1px solid var(--gray-200); text-align:right;
    }
    .btn--ok{
      background:var(--blue); color:#fff; padding:10px 16px; border-radius:10px; border:0;
      font-weight:600; cursor:pointer;
    }
    .btn--ok:hover{ background:var(--blue-700); }

    /* Modal "Ações Internas" */
    .inner-modal-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.4); display:none; align-items:center; justify-content:center; z-index:60; }
    .inner-modal{
      background:#fff; border-radius:12px; padding:24px; width:100%; max-width:640px; box-shadow:var(--shadow);
    }
    .inner-modal__header{ display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
    .inner-modal__list{ max-height:260px; overflow:auto; display:flex; flex-direction:column; gap:12px; margin-bottom:16px; }
    .textarea{ width:100%; border:1px solid var(--gray-300); border-radius:8px; padding:10px 12px; font-family:inherit; }
    .btn-row{ display:flex; justify-content:flex-end; gap:8px; margin-top:12px; }
    .btn--muted{ background:var(--gray-200); border:0; border-radius:8px; padding:8px 12px; cursor:pointer; }
    .btn--blue{ background:var(--blue); color:#fff; border:0; border-radius:8px; padding:8px 12px; cursor:pointer; }
    .btn--blue:hover{ background:var(--blue-700); }

    /* Modal "Finalizar Etapa" */
    .finish-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.4); display:none; align-items:center; justify-content:center; z-index:50; }
    .finish-card{
      background:#fff; border-radius:12px; padding:24px; width:100%; max-width:480px; box-shadow:var(--shadow);
    }
    .finish-title{ font-size:18px; font-weight:600; margin:0 0 12px; }
    .finish-actions{ display:flex; justify-content:flex-end; gap:8px; }

    /* Estilos específicos existentes */
    #detailsModal #flowList ul { padding-left:0; }
    #detailsModal #flowList li { text-indent:0; }

    /* Scrollbar do modal (suave) */
    #detailsModal .modal__body::-webkit-scrollbar,
    #detailsModal .flow-col::-webkit-scrollbar,
    #acoesModal .inner-modal__list::-webkit-scrollbar { width:8px; }
    #detailsModal .modal__body::-webkit-scrollbar-thumb,
    #detailsModal .flow-col::-webkit-scrollbar-thumb,
    #acoesModal .inner-modal__list::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:9999px; }
    #detailsModal .modal__body:hover::-webkit-scrollbar-thumb,
    #detailsModal .flow-col:hover::-webkit-scrollbar-thumb,
    #acoesModal .inner-modal__list:hover::-webkit-scrollbar-thumb { background:#94a3b8; }
  </style>
</head>
<body>

  <!-- HEADER -->
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

  <!-- MAIN -->
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

  <!-- MODAL DETALHES -->
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

  <!-- MODAL AÇÕES INTERNAS -->
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

  <!-- MODAL FINALIZAR -->
  <div id="finalizarModal" class="finish-backdrop hidden">
    <div class="finish-card">
      <h2 class="finish-title">Finalizar Etapa</h2>

      <label class="label" style="margin-bottom:6px;">Descreva a ação finalizadora:</label>
      <textarea id="acaoFinalizadora" class="textarea" rows="3" placeholder="Ex: GECOMP analisou e encaminhou para GEFIN NE INICIAL"></textarea>

      <div class="finish-actions">
        <button id="cancelarFinalizar" class="btn--muted" type="button">Cancelar</button>
        <button id="confirmarFinalizar" class="btn--blue" type="button">Confirmar e Avançar</button>
      </div>
    </div>
  </div>

  <script>
    window.MY_SETOR = <?= json_encode($_SESSION['setor'] ?? '') ?>;
  </script>
  <script src="../js/encaminhado.js"></script>
</body>
</html>
