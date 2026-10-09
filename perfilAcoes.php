<?php
// ============================================================
//  Salva alterações do perfil (formulários de editarPerfil.php)
//    acao=dados → nome, e-mail, bio e foto
//    acao=senha → troca de senha
// ============================================================

session_start();
ob_start();                       // funcionalidade.php imprime um <link>; descartamos
require_once 'db.php';
require_once 'funcionalidade.php';
ob_end_clean();

function voltar($params) {
    header('Location: editarPerfil.php?' . http_build_query($params));
    exit;
}

if (empty($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') voltar([]);

// Proteção simples contra envio de formulário por outro site (CSRF)
if (!hash_equals($_SESSION['csrf_perfil'] ?? '', $_POST['csrf'] ?? '')) voltar(['erro' => 'sessao']);

$pdo     = getDB();
$uid     = (int)$_SESSION['usuario_id'];
$usuario = buscarUsuario($pdo, $uid);
if (!$usuario) { header('Location: auth.php?acao=logout'); exit; }

$stmtSenha = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
$stmtSenha->execute([$uid]);
$hashAtual = $stmtSenha->fetchColumn();

$acao = $_POST['acao'] ?? '';

// ─────────────────────────────────────────────────────────────
//  Dados do perfil
// ─────────────────────────────────────────────────────────────
if ($acao === 'dados') {
    $nome  = trim(preg_replace('/\s+/', ' ', $_POST['nome'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $bio   = trim(preg_replace('/\s+/', ' ', $_POST['bio'] ?? ''));

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) voltar(['erro' => 'nome']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) voltar(['erro' => 'email']);
    if (mb_strlen($bio) > BIO_MAX) voltar(['erro' => 'bio']);

    // Trocar o e-mail exige a senha atual (é o login da conta)
    if (strcasecmp($email, $usuario['email']) !== 0) {
        if (!password_verify($_POST['senha_atual'] ?? '', $hashAtual)) voltar(['erro' => 'senha_email']);
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
        $stmt->execute([$email, $uid]);
        if ($stmt->fetch()) voltar(['erro' => 'email_existe']);
    }

    // Foto: nova, removida ou mantida
    $foto = $usuario['foto'];
    $fotoAntiga = null;
    if (!empty($_POST['foto_data'])) {
        [$nova, $erro] = salvarFotoPerfil($uid, $_POST['foto_data']);
        if ($erro) voltar(['erro' => 'foto', 'msg' => $erro]);
        $fotoAntiga = $foto;
        $foto = $nova;
    } elseif (!empty($_POST['remover_foto'])) {
        $fotoAntiga = $foto;
        $foto = null;
    }

    $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, bio = ?, foto = ? WHERE id = ?');
    $stmt->execute([$nome, $email, $bio !== '' ? $bio : null, $foto, $uid]);
    if ($fotoAntiga && $fotoAntiga !== $foto) apagarFotoArquivo($fotoAntiga);

    $_SESSION['usuario_nome'] = $nome;
    $_SESSION['usuario_foto'] = $foto;
    voltar(['ok' => 'dados']);
}

// ─────────────────────────────────────────────────────────────
//  Troca de senha
// ─────────────────────────────────────────────────────────────
if ($acao === 'senha') {
    $atual = $_POST['senha_atual'] ?? '';
    $nova  = $_POST['senha_nova'] ?? '';
    $conf  = $_POST['senha_confirm'] ?? '';

    if (!password_verify($atual, $hashAtual)) voltar(['erro' => 'senha_atual', 'aba' => 'senha']);
    if (strlen($nova) < 6)                    voltar(['erro' => 'senha_curta', 'aba' => 'senha']);
    if ($nova !== $conf)                      voltar(['erro' => 'senha_diff', 'aba' => 'senha']);

    $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($nova, PASSWORD_BCRYPT), $uid]);
    session_regenerate_id(true);
    voltar(['ok' => 'senha', 'aba' => 'senha']);
}

voltar([]);
