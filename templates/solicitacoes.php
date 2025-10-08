<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('America/Recife');
require_once __DIR__.'/config.php';

$SETORES = [
  'DAF - Diretoria de Administração e Finanças',
  'DOHDU - Diretoria de Obras',
  'CELOE I - Comissão de Licitação I',
  'CELOE II - Comissão de Licitação II',
  'CELOSE - Comissão de Licitação',
  'GCOMP - Gerência de Compras',
  'GOP - Gerência de Orçamento e Planejamento',
  'GFIN - Gerência Financeira',
  'GCONT - Gerência de Contabilidade',
  'DP - Diretoria da Presidência',
  'GAD - Gerência Administrativa',
  'GAC - Gerência de Acompanhamento de Contratos',
  'CGAB - Chefia de Gabinete',
  'DOE - Diretoria de Obras Estratégicas',
  'DSU - Diretoria de Obras de Saúde',
  'DSG - Diretoria de Obras de Segurança',
  'DED - Diretoria de Obras de Educação',
  'SPO - Superintendência de Projetos de Obras',
  'SUAJ - Superintendência de Apoio Jurídico',
  'SUFIN - Superintendência Financeira',
  'GAJ - Gerência de Apoio Jurídico',
  'SUPLAN - Superintendência de Planejamento',
  'DPH - Diretoria de Projetos Habitacionais'
];

$GETIC_URL = 'https://www.getic.pe.gov.br/';

$g_id   = (int)($_GET['g_id']   ?? $_POST['g_id']   ?? 0);
$u_rede = trim($_GET['u_rede']  ?? $_POST['u_rede'] ?? '');
$nome   = trim($_GET['nome']    ?? $_POST['nome']   ?? '');
$email  = trim($_GET['email']   ?? $_POST['email']  ?? '');

// valores vindos do POST (para repovoar o form em caso de erro)
$setorPost   = trim($_POST['setor'] ?? '');
$setorCustom = trim($_POST['setor_custom'] ?? '');

$erro = '';
$ok   = false;

// se POST: salva uma SOLICITAÇÃO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nomePost = trim($_POST['nome'] ?? '');

  // decide qual setor usar (lista ou digitado)
  $setorFinal = '';
  if ($setorPost === '__custom__') {
    $setorFinal = $setorCustom;
  } else {
    $setorFinal = $setorPost;
  }

  // validações
  if ($g_id <= 0 || $nomePost === '') {
    http_response_code(422);
    $erro = 'Preencha corretamente o nome.';
  } elseif ($setorPost !== '__custom__' && !in_array($setorFinal, $SETORES, true)) {
    http_response_code(422);
    $erro = 'Selecione um setor válido.';
  } elseif ($setorPost === '__custom__') {
    // precisa ter algo digitado
    if ($setorFinal === '') {
      http_response_code(422);
      $erro = 'Digite o setor quando escolher "Setor não encontrado".';
    } elseif (mb_strlen($setorFinal) < 5) {
      http_response_code(422);
      $erro = 'O setor digitado é muito curto. Use a sigla e o nome completo.';
    }
  }

  // se está tudo ok, grava
  if ($erro === '') {
    $sql = "INSERT INTO solicitacoes
              (id_usuario_cehab_online, nome, setor, status)
            VALUES (?, ?, ?, 'ABERTA')";
    $st  = $connLocal->prepare($sql);
    if ($st === false) {
      $erro = 'Falha ao preparar inserção.';
    } else {
      $st->bind_param('iss', $g_id, $nomePost, $setorFinal);
      if ($st->execute()) {
        $ok = true; // exibe modal de sucesso
        // limpa campos do formulário após sucesso
        $setorPost = '';
        $setorCustom = '';
      } else {
        $erro = 'Falha ao salvar a solicitação.';
      }
      $st->close();
    }
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Solicitações - Primeiro acesso</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-[#f0f2f5]" style="font-family:Inter,system-ui">
  <div class="max-w-xl mx-auto mt-16 bg-white rounded-lg shadow p-6">
    <h1 class="text-2xl font-bold text-gray-800 mb-1">Bem-vindo(a)!</h1>
    <p class="text-gray-600 mb-6">Complete os dados abaixo para enviar sua solicitação de acesso.</p>

    <?php if (!empty($erro)): ?>
      <div class="mb-4 rounded bg-red-50 text-red-700 px-4 py-2 border border-red-200">
        <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <input type="hidden" name="g_id" value="<?= htmlspecialchars($g_id) ?>">
      <input type="hidden" name="u_rede" value="<?= htmlspecialchars($u_rede) ?>">
      <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

      <div>
        <label class="block text-sm font-medium text-gray-700">Nome completo</label>
        <input name="nome" type="text" value="<?= htmlspecialchars($nome) ?>"
               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Setor</label>
        <select name="setor" id="setorSelect"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                required>
          <option value="" <?= $setorPost==='' ? 'selected' : '' ?> disabled>Selecione…</option>

          <?php foreach ($SETORES as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"
              <?= ($setorPost === $s) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s) ?>
            </option>
          <?php endforeach; ?>

          <!-- opção para setor não encontrado -->
          <option value="__custom__" <?= ($setorPost==='__custom__') ? 'selected' : '' ?>>
            Setor não encontrado? Digite abaixo
          </option>
        </select>

        <!-- campo digitável -->
        <div id="setorCustomWrapper"
             class="mt-3 <?= ($setorPost==='__custom__') ? '' : 'hidden' ?>">
          <label class="block text-sm font-medium text-gray-700">
            Setor não encontrado?
          </label>
          <input name="setor_custom" id="setorCustomInput" type="text"
                 placeholder="digite a sigla do setor e o nome completo"
                 value="<?= htmlspecialchars($setorCustom) ?>"
                 class="mt-1 block w-full px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
          <p class="text-xs text-gray-500 mt-1">
            Ex.: <em>GOP - Gerência de Orçamento e Planejamento</em>
          </p>
        </div>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <a href="<?= $GETIC_URL ?>" class="px-4 py-2 rounded-lg border text-gray-700 hover:bg-gray-50">Cancelar</a>
        <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700">
          Salvar e continuar
        </button>
      </div>
    </form>
  </div>

  <!-- Modal de Sucesso -->
  <div id="successModal" class="fixed inset-0 z-50 <?= $ok ? 'flex' : 'hidden' ?> bg-black/40 items-center justify-center">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-sm m-4 overflow-hidden">
      <div class="p-6 text-center">
        <h3 class="text-xl font-semibold text-gray-800 mb-2">Solicitação enviada com sucesso!</h3>
        <p class="text-gray-600 mb-5">Aguarde liberação.</p>
        <button id="successOkBtn"
          class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
          OK
        </button>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const select = document.getElementById('setorSelect');
      const customWrap = document.getElementById('setorCustomWrapper');
      const okBtn = document.getElementById('successOkBtn');

      function toggleCustom() {
        if (select.value === '__custom__') {
          customWrap.classList.remove('hidden');
        } else {
          customWrap.classList.add('hidden');
        }
      }
      if (select) select.addEventListener('change', toggleCustom);
      toggleCustom(); // estado inicial

      if (okBtn) {
        okBtn.addEventListener('click', function () {
          window.location.href = <?= json_encode($GETIC_URL) ?>;
        });
      }
    })();
  </script>
</body>
</html>
