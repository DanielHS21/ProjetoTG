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

    /* ── Barra de ações da lista ── */
    .lista-acoes {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 90%;
        margin: 0 auto;
        font-family: "Josefin Sans", sans-serif;
        font-size: 0.85vw;
        color: #777;
    }
    .lista-acoes strong { color: white; }
    .btn-limpar-lista {
        font-family: "Josefin Sans", sans-serif;
        font-size: 0.85vw;
        color: #aaa;
        background: none;
        border: 1px solid #2a2a2a;
        border-radius: 6px;
        padding: 0.45em 1.1em;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-limpar-lista:hover { color: white; border-color: red; background: #1a0000; }

    /* ── Modal de confirmação ── */
    .modal-fundo {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.75);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .modal-fundo.visivel { display: flex; }
    .modal-caixa {
        background: #111;
        border: 1px solid #2a2a2a;
        border-radius: 12px;
        padding: 1.8em 2em;
        width: min(420px, 90vw);
        text-align: center;
        font-family: "Josefin Sans", sans-serif;
    }
    .modal-caixa h3 {
        font-family: "Gabarito", sans-serif;
        color: white;
        margin: 0 0 0.5em;
        font-size: 20px;
    }
    .modal-caixa p { color: #aaa; font-size: 14px; margin: 0 0 1.5em; line-height: 1.4; }
    .modal-botoes { display: flex; gap: 0.8em; justify-content: center; }
    .modal-botoes button {
        font-family: "Josefin Sans", sans-serif;
        font-size: 14px;
        border-radius: 6px;
        padding: 0.6em 1.4em;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-cancelar { background: none; color: #ccc; border: 1px solid #333; }
    .btn-cancelar:hover { border-color: #666; color: white; }
    .btn-confirmar { background: red; color: white; border: 1px solid red; }
    .btn-confirmar:hover { background: #cc0000; }
    .btn-confirmar:disabled { opacity: 0.6; cursor: wait; }

    /* ── Aviso com "Desfazer" ── */
    .toast {
        position: fixed;
        left: 50%;
        bottom: 30px;
        transform: translateX(-50%) translateY(20px);
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 10px;
        padding: 0.8em 1.2em;
        display: flex;
        align-items: center;
        gap: 1.2em;
        font-family: "Josefin Sans", sans-serif;
        font-size: 14px;
        color: #ddd;
        opacity: 0;
        pointer-events: none;
        transition: all 0.25s;
        z-index: 1001;
    }
    .toast.visivel { opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
    .toast button {
        background: none;
        border: none;
        color: red;
        font-family: "Josefin Sans", sans-serif;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        padding: 0;
    }
    .toast button:hover { text-decoration: underline; }
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
    <?php $qtdLista = count($_SESSION['lista'] ?? []); ?>
    <div class="lista-acoes" id="lista-acoes" <?php echo $qtdLista ? '' : 'style="display:none"'; ?>>
        <span><strong id="lista-qtd"><?php echo $qtdLista; ?></strong> <span id="lista-qtd-label"><?php echo $qtdLista === 1 ? 'filme' : 'filmes'; ?></span> na lista</span>
        <button type="button" class="btn-limpar-lista" onclick="abrirModalLimpar()">🗑 Limpar lista</button>
    </div>
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

<!-- Modal de confirmação -->
<div class="modal-fundo" id="modal-limpar" onclick="if (event.target === this) fecharModalLimpar()">
    <div class="modal-caixa">
        <h3>Limpar toda a lista?</h3>
        <p><span id="modal-texto"></span> Seus favoritos não serão afetados.</p>
        <div class="modal-botoes">
            <button type="button" class="btn-cancelar" onclick="fecharModalLimpar()">Cancelar</button>
            <button type="button" class="btn-confirmar" id="btn-confirmar-limpar" onclick="confirmarLimpar()">Limpar lista</button>
        </div>
    </div>
</div>

<!-- Aviso com opção de desfazer -->
<div class="toast" id="toast-limpar">
    <span id="toast-texto">Lista limpa.</span>
    <button type="button" onclick="desfazerLimpar()">Desfazer</button>
</div>

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
            atualizarContadorLista();
        } else {
            icone.src = 'Imagens/del.png';
            el.title  = 'Remover da lista';
        }
    });
}

// ── Limpar lista ──
let toastTimer = null;

function atualizarContadorLista() {
    const qtd = document.querySelectorAll('#lista-filmes .filme-card').length;
    document.getElementById('lista-qtd').textContent = qtd;
    document.getElementById('lista-qtd-label').textContent = qtd === 1 ? 'filme' : 'filmes';
    document.getElementById('lista-acoes').style.display = qtd ? '' : 'none';
    if (!qtd && !document.querySelector('#lista-filmes .msg-vazia')) {
        document.getElementById('lista-filmes').innerHTML =
            '<p class="msg-vazia">Sua lista está vazia. <a href="index.php">Adicione filmes</a> para vê-los aqui!</p>';
    }
}

function abrirModalLimpar() {
    const qtd = document.querySelectorAll('#lista-filmes .filme-card').length;
    document.getElementById('modal-texto').innerHTML = qtd === 1
        ? 'O filme da sua lista será removido.'
        : 'Todos os <strong>' + qtd + '</strong> filmes serão removidos da sua lista.';
    document.getElementById('modal-limpar').classList.add('visivel');
}

function fecharModalLimpar() {
    document.getElementById('modal-limpar').classList.remove('visivel');
}

function acaoLista(acao) {
    const corpo = new URLSearchParams({ acao });
    return fetch('listaAcoes.php', { method: 'POST', body: corpo })
        .then(r => r.json())
        .catch(() => ({ ok: false, erro: 'Falha de conexão' }));
}

function confirmarLimpar() {
    const btn = document.getElementById('btn-confirmar-limpar');
    btn.disabled = true;
    btn.textContent = 'Limpando...';

    acaoLista('limpar').then(res => {
        btn.disabled = false;
        btn.textContent = 'Limpar lista';
        fecharModalLimpar();
        if (!res.ok) { alert(res.erro || 'Não foi possível limpar a lista.'); return; }

        // Guarda o HTML atual para restaurar sem recarregar
        window._listaAnterior = document.getElementById('lista-filmes').innerHTML;
        document.getElementById('lista-filmes').innerHTML = '';
        atualizarContadorLista();

        const n = res.removidos;
        document.getElementById('toast-texto').textContent =
            n + (n === 1 ? ' filme removido da lista.' : ' filmes removidos da lista.');
        const toast = document.getElementById('toast-limpar');
        toast.classList.add('visivel');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('visivel'), 8000);
    });
}

function desfazerLimpar() {
    clearTimeout(toastTimer);
    document.getElementById('toast-limpar').classList.remove('visivel');
    acaoLista('restaurar').then(res => {
        if (!res.ok) { alert(res.erro || 'Não foi possível desfazer.'); return; }
        if (window._listaAnterior) {
            document.getElementById('lista-filmes').innerHTML = window._listaAnterior;
            atualizarContadorLista();
        } else {
            location.reload();
        }
    });
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModalLimpar(); });

function removerFavorito(filmeId) {
    fetch('filme.php?id=' + filmeId + '&removeFavorito=1').then(() => {
        document.getElementById('fav-card-' + filmeId)?.remove();
    });
}
</script>

<?php include 'footer.php'; ?>