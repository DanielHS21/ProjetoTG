<?php

echo '<link rel="stylesheet" href="estyle.css">';
echo '<header class="header">';

session_start();
include 'funcionalidade.php';
$busca = $_GET['busca'] ?? '';

?>
<link rel='stylesheet' href='estyle.css'>

<a href='index.php' class='logo-link'>
    <img src='Imagens/logo.png' class='logo' alt='Logo'>
</a>

<div class='header-nav'>
    <a href='listaFilmes.php'>Lista</a>
    <a href='recomendacao.php' id='link-recomendacoes'>Recomendações</a>

    <form method="GET" action="index.php" id="form-busca">
        <input type="text" name="busca" placeholder="Buscar filme"
               value="<?php echo htmlspecialchars($busca); ?>">
        <button type="submit">Buscar</button>
    </form>

    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="perfil.php" class="header-usuario">
            <img src="Imagens/user.png" alt="Perfil" class="icone-usuario">
            <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
        </a>
        <a href="auth.php?acao=logout" class="header-auth-link">Sair</a>
    <?php else: ?>
        <a href="login.php" class="header-auth-link">Entrar</a>
        <a href="cadastro.php" class="header-auth-btn">Cadastrar</a>
    <?php endif; ?>
</div>

<?php echo '</header>'; ?>

<!-- Loading overlay global -->
<div id="loading-overlay">
    <div class="loading-spinner"></div>
    <p class="loading-texto">Buscando filmes...</p>
</div>

<style>
#loading-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    z-index: 999;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1.2em;
}
#loading-overlay.visivel {
    display: flex;
}
.loading-spinner {
    width: 52px;
    height: 52px;
    border: 5px solid #333;
    border-top-color: red;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.loading-texto {
    font-family: "Josefin Sans", sans-serif;
    font-size: 1.1vw;
    color: #ccc;
    margin: 0;
    letter-spacing: 0.05em;
}

    .header-usuario {
        font-family: "Josefin Sans", sans-serif;
        font-size: 0.85vw;
        color: #aaa;
        white-space: nowrap;
        text-decoration: none;
        transition: color 0.2s;
        flex-shrink: 0;
    }
    .header-usuario:hover { color: white; }
    .icone-usuario {
        height: 1.4vw;
        width: auto;
        vertical-align: middle;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    .header-usuario:hover .icone-usuario { opacity: 1; }
    .header-auth-link {
        font-family: "Josefin Sans", sans-serif;
        font-size: 0.85vw;
        color: #ccc;
        text-decoration: none;
        transition: color 0.2s;
        white-space: nowrap;
        flex-shrink: 0;
        margin-left: 0;
    }
    .header-auth-link:hover { color: red; }
    .header-auth-btn {
        font-family: "Josefin Sans", sans-serif;
        font-size: 0.8vw;
        color: white;
        background: red;
        text-decoration: none;
        padding: 0.35em 0.9em;
        border-radius: 6px;
        transition: background 0.2s;
        white-space: nowrap;
        flex-shrink: 0;
        margin-left: 0;
    }
    .header-auth-btn:hover { background: #cc0000; color: white; }
</style>

<script>
document.getElementById('form-busca').addEventListener('submit', function() {
    const overlay = document.getElementById('loading-overlay');
    overlay.querySelector('.loading-texto').textContent = 'Buscando filmes...';
    overlay.classList.add('visivel');
});

document.getElementById('link-recomendacoes').addEventListener('click', function(e) {
    e.preventDefault();
    const overlay = document.getElementById('loading-overlay');
    overlay.querySelector('.loading-texto').textContent = 'Gerando recomendações...';
    overlay.classList.add('visivel');
    setTimeout(() => { window.location.href = this.href; }, 50);
});
</script>