<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../_db/connect.php'; // ajuste para o seu arquivo de conexão

try {
  if (empty($_SESSION['auth_ok']) || empty($_SESSION['g_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_authenticated']);
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true) ?: [];
  $idProcesso = (int)($input['id_processo'] ?? 0);
  $acao       = trim($input['acao'] ?? '');
  $setorUser  = $_SESSION['setor'] ?? '';
  $FINAL_SECTOR = 'GFIN - Gerência Financeira';

  if (!$idProcesso || $acao === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
  }

  if ($setorUser !== $FINAL_SECTOR) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
  }

  $pdo->beginTransaction();
  $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

  // Confere se o processo existe e não está finalizado
  $st = $pdo->prepare("SELECT id, finalizado FROM novo_processo WHERE id = :id FOR UPDATE");
  $st->execute([':id' => $idProcesso]);
  $proc = $st->fetch(PDO::FETCH_ASSOC);
  if (!$proc) throw new Exception('process_not_found');
  if ((int)$proc['finalizado'] === 1) throw new Exception('already_finalized');

  // Confere último destino do fluxo = GFIN
  $st = $pdo->prepare("
    SELECT pf.destino_setor
    FROM processo_fluxo pf
    WHERE pf.id_processo = :id
    ORDER BY pf.id DESC
    LIMIT 1
  ");
  $st->execute([':id' => $idProcesso]);
  $ultimo = $st->fetch(PDO::FETCH_ASSOC);
  $destinoAtual = $ultimo['destino_setor'] ?? null;

  if ($destinoAtual !== $FINAL_SECTOR) {
    throw new Exception('not_at_final_sector');
  }

  // 1) Insere um registro final no fluxo, marcando conclusão
  $st = $pdo->prepare("
    INSERT INTO processo_fluxo (id_processo, origem_setor, destino_setor, acao, concluido, criado_em)
    VALUES (:id, :origem, :destino, :acao, 1, NOW())
  ");
  $st->execute([
    ':id'      => $idProcesso,
    ':origem'  => $FINAL_SECTOR,
    ':destino' => $FINAL_SECTOR,
    ':acao'    => $acao
  ]);

  // 2) Marca processo como finalizado
  $st = $pdo->prepare("UPDATE novo_processo SET finalizado = 1, finalizado_em = NOW() WHERE id = :id");
  $st->execute([':id' => $idProcesso]);

  $pdo->commit();
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
