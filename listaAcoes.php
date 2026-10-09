<?php
// ============================================================
//  Ações em massa da lista (chamadas via fetch/POST)
//    acao=limpar    → remove todos os filmes da lista
//    acao=restaurar → desfaz a última limpeza (até 10 min)
// ============================================================

session_start();
ob_start();                      // funcionalidade.php imprime um <link>; descartamos
require_once 'funcionalidade.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido']);
    exit;
}

$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'limpar') {
        $removidos = limparLista();
        echo json_encode(['ok' => true, 'removidos' => $removidos]);
    } elseif ($acao === 'restaurar') {
        $ok = restaurarLista();
        echo json_encode(['ok' => $ok, 'erro' => $ok ? null : 'Não há nada para desfazer']);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Ação inválida']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'erro' => 'Erro ao atualizar a lista']);
}
