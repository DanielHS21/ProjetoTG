<?php include 'header.php'; ?>

<style>
    .filme-card {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .poster-wrapper {
        position: relative;
        width: 13vw;
        display: block;
    }
    .poster-wrapper > img {
        width: 100%;
        border-radius: 15px;
        display: block;
        transition: 0.3s;
        cursor: pointer;
    }
    .poster-wrapper > img:hover { transform: scale(1.05); }
    .btn-hover-lista {
        position: absolute;
        top: 8px;
        right: 8px;
        opacity: 0;
        transition: opacity 0.2s ease;
        line-height: 0;
        z-index: 10;
    }
    .poster-wrapper:hover .btn-hover-lista { opacity: 1; }
    .icone-hover-lista {
        height: 1.6vw !important;
        width: auto !important;
        filter: drop-shadow(0 0 5px rgba(0,0,0,0.9));
        transition: transform 0.2s;
        cursor: pointer;
    }
    .icone-hover-lista:hover { transform: scale(1.2); }

    /* ── Abas ── */
    .abas {
        display: flex;
        justify-content: center;
        gap: 0;
        margin: 0 auto 2em;
        width: fit-content;
        border-bottom: 2px solid #222;
    }
    .aba-btn {
        font-family: "Josefin Sans", sans-serif;
        font-size: 0.9vw;
        color: #555;
        background: none;
        border: none;
        padding: 0.6em 2em;
        cursor: pointer;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        transition: color 0.2s;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
    }
    .aba-btn:hover { color: #aaa; }
    .aba-btn.ativa { color: white; border-bottom-color: red; }

    /* ── Conteúdo das abas ── */
    .aba-conteudo { display: none; }
    .aba-conteudo.ativa { display: block; }

    /* ── Favoritos: estrela no hover do card ── */
    .btn-hover-fav {
        position: absolute;
        top: 8px;
        left: 8px;
        opacity: 0;
        transition: opacity 0.2s ease;
        font-size: 1.3vw;
        color: gold;
        text-decoration: none;
        line-height: 1;
        filter: drop-shadow(0 0 4px rgba(0,0,0,0.9));
        z-index: 10;
    }
    .poster-wrapper:hover .btn-hover-fav { opacity: 1; }

    .msg-vazia {
        text-align: center;
        font-family: "Josefin Sans", sans-serif;
        font-size: 1vw;
        color: #aaa;
        margin-top: 3%;
        width: 100%;
    }
    .msg-vazia a {
        color: red;
        text-decoration: none;
    }
    .msg-vazia a:hover { text-decoration: underline; }
</style>

<a href='index.php' class='btn-voltar' title='Voltar'>
    <img src='Imagens/voltar.png' class='icone-voltar' alt='Voltar'>
</a>

<h1 class="titulo">Minhas Listas</h1>

<div class="abas">
    <button class="aba-btn ativa" onclick="trocarAba('lista', this)">📋 Lista</button>
    <?php if (!empty($_SESSION['usuario_id'])): ?>
        <button class="aba-btn" onclick="trocarAba('favoritos', this)">⭐ Favoritos</button>
    <?php endif; ?>
</div>

<!-- Aba: Lista -->
<div id="aba-lista" class="aba-conteudo ativa">
    <div id="lista-filmes">
        <?php if (empty($_SESSION['lista'])): ?>
            <p class="msg-vazia">Sua lista está vazia. <a href="index.php">Adicione filmes</a> para vê-los aqui!</p>
        <?php else: ?>
            <?php foreach ($_SESSION['lista'] as $filmeId): ?>
                <?php echo exibirFilmeCard($filmeId, $apiKey); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Aba: Favoritos (só logados) -->
<?php if (!empty($_SESSION['usuario_id'])): ?>
<div id="aba-favoritos" class="aba-conteudo">
    <div id="lista-favoritos">
        <?php if (empty($_SESSION['favoritos'])): ?>
            <p class="msg-vazia">Você ainda não tem favoritos. Acesse a página de um filme e clique na ★ para favoritar!</p>
        <?php else: ?>
            <?php foreach ($_SESSION['favoritos'] as $filmeId): ?>
                <?php echo exibirFilmeCardFavorito($filmeId, $apiKey); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
function trocarAba(nome, btn) {
    document.querySelectorAll('.aba-conteudo').forEach(el => el.classList.remove('ativa'));
    document.querySelectorAll('.aba-btn').forEach(el => el.classList.remove('ativa'));
    document.getElementById('aba-' + nome).classList.add('ativa');
    btn.classList.add('ativa');
}

function alterarLista(filmeId, el) {
    const icone   = document.getElementById('icone-' + filmeId);
    const naLista = icone.src.includes('del.png');
    const acao    = naLista ? 'removeLista' : 'addLista';
    fetch('filme.php?id=' + filmeId + '&' + acao + '=1').then(() => {
        if (naLista) {
            icone.src = 'Imagens/add.png';
            el.title  = 'Adicionar à lista';
            document.getElementById('card-' + filmeId)?.remove();
        } else {
            icone.src = 'Imagens/del.png';
            el.title  = 'Remover da lista';
        }
    });
}

function removerFavorito(filmeId) {
    fetch('filme.php?id=' + filmeId + '&removeFavorito=1').then(() => {
        document.getElementById('fav-card-' + filmeId)?.remove();
    });
}
</script>

<?php include 'footer.php'; ?>