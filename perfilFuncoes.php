<?php
// ============================================================
//  Funções de perfil do usuário (foto, bio, dados da conta)
// ============================================================

const AVATAR_DIR      = 'uploads/avatars';
const AVATAR_MAX_BYTES = 2 * 1024 * 1024;   // 2 MB (a imagem já chega recortada e comprimida)
const BIO_MAX         = 160;

/**
 * Garante que as colunas novas existam na tabela usuarios.
 * Assim quem já tem o banco criado não precisa rodar nenhum script manualmente.
 */
function garantirColunasPerfil(PDO $pdo) {
    static $feito = false;
    if ($feito) return;
    $feito = true;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('foto', $cols)) $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) NULL AFTER senha_hash");
        if (!in_array('bio', $cols))  $pdo->exec("ALTER TABLE usuarios ADD COLUMN bio VARCHAR(160) NULL AFTER foto");
    } catch (Throwable $e) { /* sem permissão de ALTER: rode banco.sql manualmente */ }
}

function buscarUsuario(PDO $pdo, $id) {
    garantirColunasPerfil($pdo);
    $stmt = $pdo->prepare('SELECT id, nome, email, foto, bio, created_at FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Iniciais do nome: "Daniel Henrique Santos" → "DS" */
function iniciaisNome($nome) {
    $partes = preg_split('/\s+/', trim($nome)) ?: ['?'];
    $ini = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) $ini .= mb_substr(end($partes), 0, 1);
    return mb_strtoupper($ini ?: '?');
}

/**
 * HTML do avatar: foto se houver, senão iniciais com cor própria do usuário.
 * $classe permite estilizar tamanhos diferentes (header, perfil, edição).
 */
function avatarHtml($nome, $foto, $classe = '') {
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    if ($foto && is_file(__DIR__ . '/' . $foto)) {
        $v = filemtime(__DIR__ . '/' . $foto);
        return "<span class='avatar $classe'><img src='{$h($foto)}?v=$v' alt='{$h($nome)}'></span>";
    }
    $matiz = crc32((string)$nome) % 360;
    $m2 = ($matiz + 40) % 360;
    return "<span class='avatar avatar-iniciais $classe' style='background:linear-gradient(135deg,hsl($matiz,65%,38%),hsl($m2,70%,22%))'>"
         . $h(iniciaisNome($nome)) . "</span>";
}

/** Remove o arquivo de foto do disco (só dentro da pasta de avatares). */
function apagarFotoArquivo($foto) {
    if (!$foto) return;
    $caminho = realpath(__DIR__ . '/' . $foto);
    $base    = realpath(__DIR__ . '/' . AVATAR_DIR);
    if ($caminho && $base && strpos($caminho, $base) === 0 && is_file($caminho)) @unlink($caminho);
}

/**
 * Salva a foto recebida como data URL (o navegador já recorta em 400x400).
 * Retorna [caminhoRelativo, null] ou [null, mensagemDeErro].
 */
function salvarFotoPerfil($usuarioId, $dataUrl) {
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $dataUrl, $m)) {
        return [null, 'Formato de imagem inválido. Use JPG, PNG ou WEBP.'];
    }
    $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($bin === false || strlen($bin) === 0) return [null, 'Não foi possível ler a imagem.'];
    if (strlen($bin) > AVATAR_MAX_BYTES)     return [null, 'Imagem muito grande (máx. 2 MB).'];

    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return [null, 'O arquivo enviado não é uma imagem válida.'];
    }
    if ($info[0] > 2000 || $info[1] > 2000) return [null, 'Imagem com dimensões muito grandes.'];

    $dir = __DIR__ . '/' . AVATAR_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return [null, 'Não foi possível criar a pasta de fotos.'];

    $nomeArq = 'u' . (int)$usuarioId . '_' . bin2hex(random_bytes(6)) . '.jpg';
    $destino = $dir . '/' . $nomeArq;

    // Com GD: reprocessa a imagem (remove metadados e qualquer conteúdo estranho)
    if (function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring($bin))) {
        $lado = 400;
        $out = imagecreatetruecolor($lado, $lado);
        imagefill($out, 0, 0, imagecolorallocate($out, 17, 17, 17));
        $w = imagesx($img); $hgt = imagesy($img); $s = min($w, $hgt);
        imagecopyresampled($out, $img, 0, 0, intdiv($w - $s, 2), intdiv($hgt - $s, 2), $lado, $lado, $s, $s);
        $ok = imagejpeg($out, $destino, 88);
        imagedestroy($img); imagedestroy($out);
    } else {
        $ok = file_put_contents($destino, $bin) !== false;
    }
    if (!$ok) return [null, 'Falha ao salvar a imagem.'];

    return [AVATAR_DIR . '/' . $nomeArq, null];
}
