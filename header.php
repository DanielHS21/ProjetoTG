<?php

// viewport: sem ele o celular renderiza o site como se tivesse ~980px e "encolhe" tudo
echo '<meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<link rel="stylesheet" href="estyle.css">';
echo '<header class="header" id="header">';

session_start();
include 'funcionalidade.php';
$busca = $_GET['busca'] ?? '';

?>
<link rel='stylesheet' href='estyle.css'>

<a href='index.php' class='logo-link'>
    <img src='Imagens/logo.png' class='logo' alt='Logo'>
</a>

<div class='header-nav'>
    <a href='listaFilmes.php' class='so-desktop' data-loading='Carregando sua lista...'>Lista</a>
    <a href='recomendacao.php' id='link-recomendacoes' class='so-desktop'>Recomendações</a>

    <form method="GET" action="index.php" id="form-busca">
        <input type="text" name="busca" placeholder="Buscar filme"
               value="<?php echo htmlspecialchars($busca); ?>">
        <button type="submit">Buscar</button>
    </form>

    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="perfil.php" class="header-usuario" data-loading="Carregando seu perfil...">
            <?php
                // Sessões abertas antes desta versão ainda não têm a foto carregada
                if (!array_key_exists('usuario_foto', $_SESSION)) {
                    try {
                        require_once 'db.php';
                        $uTmp = buscarUsuario(getDB(), $_SESSION['usuario_id']);
                        $_SESSION['usuario_foto'] = $uTmp['foto'] ?? null;
                    } catch (Throwable $e) { $_SESSION['usuario_foto'] = null; }
                }
                echo avatarHtml($_SESSION['usuario_nome'] ?? '', $_SESSION['usuario_foto'], 'avatar-header');
            ?>
            <span class="header-usuario-nome"><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></span>
        </a>
        <a href="auth.php?acao=logout" class="header-auth-link so-desktop">Sair</a>
    <?php else: ?>
        <a href="login.php" class="header-auth-link so-desktop">Entrar</a>
        <a href="cadastro.php" class="header-auth-btn">Cadastrar</a>
    <?php endif; ?>

    <button type="button" class="header-menu-btn" id="header-menu-btn" aria-label="Abrir menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</div>

<!-- Menu do celular (escondido no computador) -->
<nav class="menu-mobile" id="menu-mobile">
    <a href="index.php">🏠 Início</a>
    <a href="listaFilmes.php" data-loading="Carregando sua lista...">📋 Minha lista</a>
    <a href="recomendacao.php" class="link-reco">✨ Recomendações</a>
    <?php if (isset($_SESSION['usuario_id'])): ?>
        <a href="perfil.php" data-loading="Carregando seu perfil...">👤 Meu perfil</a>
        <a href="editarPerfil.php">✏️ Editar perfil</a>
        <a href="auth.php?acao=logout" class="menu-mobile-sair">Sair da conta</a>
    <?php else: ?>
        <a href="login.php">Entrar</a>
        <a href="cadastro.php">Criar conta</a>
    <?php endif; ?>
</nav>

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
        display: inline-flex;
        align-items: center;
        gap: 0.5em;
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

    /* ── Menu do celular ── */
    .header-menu-btn, .menu-mobile { display: none; }

    @media (max-width: 900px) {
        .header {
            position: sticky;
            top: 0;
            z-index: 500;
            background: rgba(0, 0, 0, 0.92);
            backdrop-filter: blur(8px);
            flex-wrap: wrap;
            height: auto;
            padding: 10px 16px;
            gap: 10px 12px;
            font-size: 15px;
        }
        .logo { height: 42px; }
        .header-nav { display: contents; }
        .so-desktop { display: none !important; }

        .header-usuario { order: 2; margin-left: auto; font-size: 14px; }
        .header-usuario-nome { display: none; }
        .avatar-header { width: 34px; height: 34px; font-size: 14px; }
        .header-auth-btn { order: 2; margin-left: auto; font-size: 13px; padding: 0.5em 1em; }

        .header-menu-btn {
            order: 3;
            display: inline-flex;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            width: 40px;
            height: 40px;
            padding: 0 9px;
            background: none;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            cursor: pointer;
        }
        .header-menu-btn span {
            display: block;
            height: 2px;
            background: #ddd;
            border-radius: 2px;
            transition: transform 0.25s, opacity 0.2s;
        }
        .header-menu-btn.aberto span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .header-menu-btn.aberto span:nth-child(2) { opacity: 0; }
        .header-menu-btn.aberto span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        #form-busca { order: 4; flex-basis: 100%; }
        #form-busca input {
            flex: 1;
            min-width: 0;
            font-size: 16px;            /* 16px evita o zoom automático do iPhone */
            padding: 9px 12px;
            border-radius: 8px;
            border: 1px solid #2a2a2a;
            background: #141414;
            color: white;
        }
        #form-busca button {
            font-size: 15px;
            padding: 9px 14px;
            border-radius: 8px;
            border: none;
            background: red;
            color: white;
        }

        .menu-mobile {
            position: fixed;
            left: 0;
            right: 0;
            top: var(--altura-header, 110px);
            z-index: 499;
            background: #0b0b0b;
            border-bottom: 1px solid #222;
            flex-direction: column;
            padding: 6px 0 10px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.7);
        }
        .menu-mobile.aberto { display: flex; }
        .menu-mobile a {
            font-family: "Josefin Sans", sans-serif;
            font-size: 16px;
            color: #ddd;
            text-decoration: none;
            padding: 14px 22px;
        }
        .menu-mobile a:active, .menu-mobile a:hover { background: #1a1a1a; color: white; }
        .menu-mobile-sair { color: #ff6b6b !important; border-top: 1px solid #1f1f1f; margin-top: 4px; }

        .loading-texto { font-size: 15px; }
    }
</style>

<script>
document.getElementById('form-busca').addEventListener('submit', function() {
    const overlay = document.getElementById('loading-overlay');
    overlay.querySelector('.loading-texto').textContent = 'Buscando filmes...';
    overlay.classList.add('visivel');
});

// Tela de carregamento para páginas que buscam dados no TMDB.
// Qualquer link com data-loading="Mensagem..." ganha esse comportamento.
function mostrarLoading(texto) {
    const overlay = document.getElementById('loading-overlay');
    overlay.querySelector('.loading-texto').textContent = texto;
    overlay.classList.add('visivel');
}
const mensagensLoading = {
    'recomendacao.php': 'Gerando recomendações...',
    'listaFilmes.php':  'Carregando sua lista...',
    'perfil.php':       'Carregando seu perfil...',
};
document.addEventListener('click', function (e) {
    const link = e.target.closest('a[href]');
    if (!link || link.target || e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    const pagina = link.getAttribute('href').split('?')[0];
    const texto = link.dataset.loading || mensagensLoading[pagina];
    if (!texto) return;
    e.preventDefault();
    mostrarLoading(texto);
    setTimeout(() => { window.location.href = link.href; }, 50);
});
// Ao voltar pelo botão "voltar" do navegador, a página pode vir do cache com o overlay aberto
window.addEventListener('pageshow', () => document.getElementById('loading-overlay').classList.remove('visivel'));

// ── Menu do celular ──
(function () {
    const btn = document.getElementById('header-menu-btn');
    const menu = document.getElementById('menu-mobile');
    const header = document.getElementById('header');
    const fechar = () => {
        menu.classList.remove('aberto');
        btn.classList.remove('aberto');
        btn.setAttribute('aria-expanded', 'false');
    };
    btn.addEventListener('click', e => {
        e.stopPropagation();
        document.documentElement.style.setProperty('--altura-header', header.getBoundingClientRect().bottom + 'px');
        const abrir = !menu.classList.contains('aberto');
        menu.classList.toggle('aberto', abrir);
        btn.classList.toggle('aberto', abrir);
        btn.setAttribute('aria-expanded', abrir);
    });
    document.addEventListener('click', e => { if (!menu.contains(e.target)) fechar(); });
    window.addEventListener('resize', fechar);
    window.addEventListener('pageshow', fechar);
})();
</script>