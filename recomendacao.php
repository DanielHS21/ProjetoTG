<?php
include 'header.php';

echo '<a href="index.php" class="btn-voltar" title="Voltar"><img src="Imagens/voltar.png" class="icone-voltar" alt="Voltar"></a>';

echo "<h1 class='titulo'>Recomendações para você</h1>";

$lista     = $_SESSION['lista']     ?? [];
$favoritos = $_SESSION['favoritos'] ?? [];

if (empty($lista) && empty($favoritos)) {
    echo "<p class='reco-vazio'>Sua lista está vazia. <a href='index.php'>Adicione filmes</a> para receber recomendações.</p>";
} else {
    if (count(array_unique(array_merge($lista, $favoritos))) < 3) {
        echo "<p class='reco-dica'>Dica: com 3 ou mais filmes na lista as recomendações ficam bem mais precisas.</p>";
    }
    echo recomendarFilme($lista, $apiKey, $favoritos);
}
?>

<style>
.reco-vazio, .reco-dica {
    text-align: center;
    font-family: "Josefin Sans", sans-serif;
    color: #aaa;
    margin-top: 3%;
}
.reco-dica { color: #777; font-size: 0.85vw; margin-top: 1%; }
.reco-vazio a { color: red; text-decoration: none; }

.reco-perfil {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.6em;
    margin: 1.5% auto 0;
    width: 90%;
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.85vw;
    color: #888;
}
.reco-perfil strong { color: white; }
.reco-perfil-sep { color: #444; }
.reco-chip {
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 20px;
    padding: 0.2em 0.8em;
    color: #ddd;
}
.reco-atualizar {
    margin-left: 1em;
    color: #777;
    text-decoration: none;
    border: 1px solid #2a2a2a;
    border-radius: 6px;
    padding: 0.2em 0.8em;
    transition: 0.2s;
}
.reco-atualizar:hover { color: white; border-color: red; }

.reco-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 2.2vw 1.6vw;
    width: 88%;
    margin: 3% auto 0;
}
.reco-card { display: flex; flex-direction: column; min-width: 0; }
.reco-oculto { display: none; }

.reco-poster { position: relative; }
.reco-poster > img {
    width: 100%;
    aspect-ratio: 2 / 3;
    object-fit: cover;
    border-radius: 12px;
    display: block;
    cursor: pointer;
    transition: transform 0.3s;
}
.reco-poster > img:hover { transform: scale(1.04); }

.reco-match {
    position: absolute;
    left: 8px;
    bottom: 8px;
    background: rgba(0, 0, 0, 0.8);
    color: #46d369;
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.75vw;
    font-weight: 700;
    padding: 0.2em 0.55em;
    border-radius: 6px;
    pointer-events: none;
}
.reco-add {
    position: absolute;
    top: 8px;
    right: 8px;
    opacity: 0;
    transition: opacity 0.2s;
    line-height: 0;
}
.reco-poster:hover .reco-add { opacity: 1; }
.reco-add img {
    height: 1.6vw;
    width: auto;
    filter: drop-shadow(0 0 5px rgba(0,0,0,0.9));
    transition: transform 0.2s;
}
.reco-add img:hover { transform: scale(1.2); }

.reco-titulo {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.9vw;
    color: white;
    margin: 0.6em 0 0.15em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.reco-meta {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.72vw;
    color: #777;
    margin: 0;
}
.reco-motivo {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.72vw;
    color: #aaa;
    margin: 0.35em 0 0;
    line-height: 1.3;
    border-left: 2px solid red;
    padding-left: 0.5em;
}

@media (max-width: 900px) {
    .reco-grid { grid-template-columns: repeat(3, 1fr); width: 92%; gap: 18px 12px; }
    .reco-titulo { font-size: 14px; }
    .reco-meta, .reco-motivo, .reco-perfil, .reco-dica { font-size: 12px; }
    .reco-match { font-size: 11px; }
    .reco-add { opacity: 1; }
    .reco-add img { height: 24px; }
}
</style>

<script>
function alterarListaReco(filmeId, el) {
    const icone   = document.getElementById('icone-' + filmeId);
    const naLista = icone.src.includes('del.png');
    const acao    = naLista ? 'removeLista' : 'addLista';
    fetch('filme.php?id=' + filmeId + '&' + acao + '=1').then(() => {
        icone.src = naLista ? 'Imagens/add.png' : 'Imagens/del.png';
        el.title  = naLista ? 'Adicionar à lista' : 'Remover da lista';
    });
}

function mostrarMaisReco() {
    document.querySelectorAll('.reco-oculto').forEach(el => el.classList.remove('reco-oculto'));
    document.getElementById('reco-mais')?.remove();
}

window.addEventListener('load', function () {
    document.getElementById('loading-overlay')?.classList.remove('visivel');
});
</script>

<?php include 'footer.php'; ?>