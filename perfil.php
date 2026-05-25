<?php
include 'header.php';

// Redireciona se não estiver logado
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$nome      = $_SESSION['usuario_nome'] ?? '';
$lista     = $_SESSION['lista']        ?? [];
$favoritos = $_SESSION['favoritos']    ?? [];
?>

<style>
.perfil-container {
    width: 88%;
    margin: 0 auto;
    padding-bottom: 4%;
}

/* ── Cabeçalho do perfil ── */
.perfil-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2.5% 0 2%;
    border-bottom: 1px solid #1f1f1f;
    margin-bottom: 3%;
}

.perfil-info {
    display: flex;
    align-items: center;
    gap: 1.2em;
}

.perfil-avatar {
    width: 4vw;
    height: 4vw;
    border-radius: 50%;
    background: #1a1a1a;
    border: 2px solid #333;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
.perfil-avatar-img {
    width: 55%;
    height: auto;
    opacity: 0.6;
}

.perfil-nome {
    font-family: "Gabarito", sans-serif;
    font-size: 1.8vw;
    color: white;
    margin: 0 0 0.15em 0;
}

.perfil-stats {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.75vw;
    color: #555;
    letter-spacing: 0.05em;
}

.perfil-stats span {
    color: #aaa;
    margin-right: 1.5em;
}

.perfil-stats strong {
    color: red;
}

.btn-logout {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.8vw;
    color: #666;
    text-decoration: none;
    border: 1px solid #2a2a2a;
    border-radius: 6px;
    padding: 0.45em 1.2em;
    transition: all 0.2s;
    letter-spacing: 0.05em;
}
.btn-logout:hover {
    color: white;
    border-color: red;
    background: #1a0000;
}

/* ── Seções ── */
.perfil-secao {
    margin-bottom: 3.5%;
}

.perfil-secao-titulo {
    font-family: "Gabarito", sans-serif;
    font-size: 1.1vw;
    color: white;
    margin: 0 0 1.2em 0;
    display: flex;
    align-items: center;
    gap: 0.5em;
}

.perfil-secao-titulo::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #1f1f1f;
    margin-left: 0.8em;
}

.perfil-secao-badge {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.65vw;
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    color: #666;
    padding: 0.2em 0.6em;
    border-radius: 20px;
}

/* ── Grid de filmes ── */
.perfil-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.2em;
}

.perfil-filme-card {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.perfil-poster-wrapper {
    position: relative;
    width: 9vw;
}

.perfil-poster-wrapper img {
    width: 100%;
    border-radius: 10px;
    display: block;
    transition: 0.3s;
    cursor: pointer;
}

.perfil-poster-wrapper img:hover {
    transform: scale(1.05);
}

.perfil-filme-titulo {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.65vw;
    color: #aaa;
    text-align: center;
    margin-top: 0.4em;
    max-width: 9vw;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Estrela de favorito no card */
.perfil-fav-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    font-size: 0.9vw;
    color: gold;
    filter: drop-shadow(0 0 3px rgba(0,0,0,0.9));
    line-height: 1;
}

/* ── Mensagem vazia ── */
.perfil-vazio {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.85vw;
    color: #444;
    padding: 1.5em 0;
}

.perfil-vazio a {
    color: red;
    text-decoration: none;
}
.perfil-vazio a:hover { text-decoration: underline; }
</style>

<div class="perfil-container">

    <!-- Cabeçalho do perfil -->
    <div class="perfil-header">
        <div class="perfil-info">
            <div class="perfil-avatar"><img src="Imagens/user.png" alt="Perfil" class="perfil-avatar-img"></div>
            <div>
                <h1 class="perfil-nome"><?php echo htmlspecialchars($nome); ?></h1>
                <p class="perfil-stats">
                    <span><strong><?php echo count($lista); ?></strong> na lista</span>
                    <span><strong><?php echo count($favoritos); ?></strong> favoritos</span>
                </p>
            </div>
        </div>
        <a href="auth.php?acao=logout" class="btn-logout">Sair da conta</a>
    </div>

    <!-- Seção: Minha Lista -->
    <div class="perfil-secao">
        <h2 class="perfil-secao-titulo">
            📋 Minha Lista
            <span class="perfil-secao-badge"><?php echo count($lista); ?> filmes</span>
        </h2>

        <?php if (empty($lista)): ?>
            <p class="perfil-vazio">Sua lista está vazia. <a href="index.php">Adicione filmes</a> para vê-los aqui.</p>
        <?php else: ?>
            <div class="perfil-grid">
                <?php foreach ($lista as $filmeId):
                    $url      = "https://api.themoviedb.org/3/movie/$filmeId?api_key=$apiKey&language=pt-BR";
                    $response = @file_get_contents($url);
                    if (!$response) continue;
                    $filme  = json_decode($response, true);
                    $poster = "https://image.tmdb.org/t/p/w300" . $filme['poster_path'];
                    $isFav  = in_array($filmeId, $favoritos);
                ?>
                    <div class="perfil-filme-card">
                        <div class="perfil-poster-wrapper">
                            <img src="<?php echo $poster; ?>"
                                 onclick="window.location.href='filme.php?id=<?php echo $filmeId; ?>'"
                                 title="<?php echo htmlspecialchars($filme['title']); ?>">
                            <?php if ($isFav): ?>
                                <span class="perfil-fav-badge" title="Nos favoritos">★</span>
                            <?php endif; ?>
                        </div>
                        <p class="perfil-filme-titulo"><?php echo htmlspecialchars($filme['title']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Seção: Favoritos -->
    <div class="perfil-secao">
        <h2 class="perfil-secao-titulo">
            ⭐ Favoritos
            <span class="perfil-secao-badge"><?php echo count($favoritos); ?> filmes</span>
        </h2>

        <?php if (empty($favoritos)): ?>
            <p class="perfil-vazio">Nenhum favorito ainda. Acesse a página de um filme e clique em ☆ para favoritar.</p>
        <?php else: ?>
            <div class="perfil-grid">
                <?php foreach ($favoritos as $filmeId):
                    $url      = "https://api.themoviedb.org/3/movie/$filmeId?api_key=$apiKey&language=pt-BR";
                    $response = @file_get_contents($url);
                    if (!$response) continue;
                    $filme  = json_decode($response, true);
                    $poster = "https://image.tmdb.org/t/p/w300" . $filme['poster_path'];
                ?>
                    <div class="perfil-filme-card">
                        <div class="perfil-poster-wrapper">
                            <img src="<?php echo $poster; ?>"
                                 onclick="window.location.href='filme.php?id=<?php echo $filmeId; ?>'"
                                 title="<?php echo htmlspecialchars($filme['title']); ?>">
                            <span class="perfil-fav-badge">★</span>
                        </div>
                        <p class="perfil-filme-titulo"><?php echo htmlspecialchars($filme['title']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'footer.php'; ?>