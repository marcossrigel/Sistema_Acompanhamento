<?php
require_once __DIR__ . '/config.php';
date_default_timezone_set('America/Recife');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die("ID inválido."); }

$access = $_GET['access_dinamic'] ?? '';

// Consulta a solicitação atual
$st = $connLocal->prepare("SELECT * FROM solicitacoes WHERE id = ?");
$st->bind_param("i", $id);
$st->execute();
$origem = $st->get_result()->fetch_assoc();
$st->close();

if (!$origem) { die("Registro não encontrado."); }

// Usa setor_destino enviado pelo painel.php, se existir
$proximo = $_GET['setor_destino'] ?? null;

// Caso não tenha vindo por GET, usa fluxo automático
if (!$proximo) {
    function proximoSetor($setorAtual) {
        $fluxo = [
            'DAF - DIRETORIA DE ADMINISTRAÇÃO E FINANÇAS',
            'GECOMP',
            'DDO',
            'CPL',
            'DAF - HOMOLOGACAO',
            'PARECER JUR',
            'GEFIN NE INICIAL',
            'GOP PF (SEFAZ)',
            'GEFIN NE DEFINITIVO',
            'LIQ',
            'PD (SEFAZ)',
            'OB',
            'REMESSA'
        ];
        $idx = array_search($setorAtual, $fluxo, true);
        return ($idx !== false && isset($fluxo[$idx+1])) ? $fluxo[$idx+1] : null;
    }
    $proximo = proximoSetor($origem['setor']);
}

$connLocal->begin_transaction();

try {
    // 1) Atualiza a data de liberação do setor atual
    $upd = $connLocal->prepare("UPDATE solicitacoes SET data_liberacao = CURDATE() WHERE id = ?");
    $upd->bind_param("i", $id);
    $upd->execute();
    $upd->close();

    // 2) Recarrega os dados atualizados
    $st2 = $connLocal->prepare("SELECT * FROM solicitacoes WHERE id = ?");
    $st2->bind_param("i", $id);
    $st2->execute();
    $origemAtualizada = $st2->get_result()->fetch_assoc();
    $st2->close();

    if ($proximo) {
        $dataSolicitacao = $origemAtualizada['data_liberacao'];

        // setor_original propagado (fallback para dados antigos)
        $setorOriginal = !empty($origemAtualizada['setor_original'])
            ? $origemAtualizada['setor_original']
            : $origemAtualizada['setor'];

        // Tipos seguros
        $dataSolicitacao    = $origemAtualizada['data_liberacao'];            // segue igual
        $dataLibOriginal    = $origemAtualizada['data_liberacao_original'];   // <- replica

        // 3) Insere a nova linha NO PRÓXIMO SETOR
        
        $ins = $connLocal->prepare("
        INSERT INTO solicitacoes
            (id_usuario, demanda, sei, codigo,
            setor, setor_original, responsavel,
            data_solicitacao, data_liberacao, data_liberacao_original,
            tempo_medio, tempo_real, setor_responsavel)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?)
        ");
        // Tipagem: i + 8s + i + s  -> "issssssssis"
        $ins->bind_param(
            "isssssssssis",
            $origemAtualizada['id_usuario'],  // i
            $origemAtualizada['demanda'],     // s
            $origemAtualizada['sei'],         // s
            $origemAtualizada['codigo'],      // s
            $proximo,                         // s
            $setorOriginal,                   // s
            $origemAtualizada['responsavel'], // s
            $dataSolicitacao,                 // s
            $dataLibOriginal,                 // s  <-- replica original
            $tempoMedio,                      // s
            $tempoReal,                       // i
            $proximo                          // s
        );
        $ins->execute();
        $novoId = $connLocal->insert_id;
        $ins->close();

        // 4) Registra o encaminhamento apontando PARA A NOVA LINHA
        $insEnc = $connLocal->prepare("
            INSERT INTO encaminhamentos
              (id_demanda, setor_origem, setor_destino, status, data_encaminhamento)
            VALUES (?, ?, ?, 'Em andamento', NOW())
        ");
        $insEnc->bind_param(
            "iss",
            $novoId,
            $origemAtualizada['setor'],
            $proximo
        );
        $insEnc->execute();
        $insEnc->close();
    }

    $connLocal->commit();
} catch (Throwable $e) {
    $connLocal->rollback();
    die("Falha ao encaminhar: " . $e->getMessage());
}

// Redireciona de volta para o painel
header("Location: painel.php?access_dinamic=" . urlencode($access));
exit;
