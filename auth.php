<?php
session_start();
require_once 'db.php';
require_once 'funcionalidade.php';

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

// ============================================================
//  LOGOUT
// ============================================================
if ($acao === 'logout') {
    // Limpa a sessão inteira (lista temporária inclusa)
    $_SESSION = [];
    session_destroy();
    header('Location: login.php?msg=saiu');
    exit;
}

// ============================================================
//  CADASTRO
// ============================================================
if ($acao === 'cadastro') {
    $nome          = trim($_POST['nome']          ?? '');
    $email         = trim($_POST['email']         ?? '');
    $senha         = $_POST['senha']              ?? '';
    $senhaConfirm  = $_POST['senha_confirm']      ?? '';

    // Validações
    if (empty($nome) || empty($email) || empty($senha) || empty($senhaConfirm)) {
        redirect('cadastro.php', ['erro' => 'campos', 'nome' => $nome, 'email' => $email]);
    }

    if (strlen($senha) < 6) {
        redirect('cadastro.php', ['erro' => 'senha_curta', 'nome' => $nome, 'email' => $email]);
    }

    if ($senha !== $senhaConfirm) {
        redirect('cadastro.php', ['erro' => 'senha_diff', 'nome' => $nome, 'email' => $email]);
    }

    $pdo = getDB();

    // Verifica se e-mail já existe
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        redirect('cadastro.php', ['erro' => 'email_existe', 'nome' => $nome, 'email' => $email]);
    }

    // Cria o usuário
    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash) VALUES (?, ?, ?)');
    $stmt->execute([$nome, $email, $hash]);

    redirect('login.php', ['msg' => 'cadastrado']);
}

// ============================================================
//  LOGIN
// ============================================================
if ($acao === 'login') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha']      ?? '';

    if (empty($email) || empty($senha)) {
        redirect('login.php', ['erro' => 'campos']);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
        redirect('login.php', ['erro' => 'credenciais']);
    }

    // Login bem-sucedido — salva na sessão
    $_SESSION['usuario_id']   = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];

    // ── Migra a lista temporária da sessão para o banco ──
    if (!empty($_SESSION['lista'])) {
        migrarListaParaBanco($pdo, $usuario['id'], $_SESSION['lista']);
        unset($_SESSION['lista']); // limpa a versão temporária
    }

    // ── Carrega a lista e favoritos do banco para a sessão ──
    carregarListaDoBanco($pdo, $usuario['id']);
    carregarFavoritosDoBanco($pdo, $usuario['id']);

    redirect('index.php', []);
}

// ============================================================
//  Funções auxiliares
// ============================================================

function redirect($pagina, $params) {
    $query = !empty($params) ? '?' . http_build_query($params) : '';
    header('Location: ' . $pagina . $query);
    exit;
}

function migrarListaParaBanco($pdo, $usuarioId, $lista) {
    $stmt = $pdo->prepare('
        INSERT IGNORE INTO lista_filmes (usuario_id, filme_id)
        VALUES (?, ?)
    ');
    foreach ($lista as $filmeId) {
        $stmt->execute([$usuarioId, (int)$filmeId]);
    }
}

function carregarListaDoBanco($pdo, $usuarioId) {
    $stmt = $pdo->prepare('
        SELECT filme_id FROM lista_filmes
        WHERE usuario_id = ?
        ORDER BY adicionado_em ASC
    ');
    $stmt->execute([$usuarioId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['lista'] = $ids;
}