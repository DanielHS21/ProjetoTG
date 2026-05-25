<?php
include 'header.php';

$filmeId = (int)($_GET['id'] ?? 0);
$apiKey  = "5ced0e51cd24880895288b3d6c5571c9";

if (isset($_GET['addLista']))       addLista($filmeId);
if (isset($_GET['removeLista']))    removeLista($filmeId);
if (isset($_GET['addFavorito']))    addFavorito($filmeId);
if (isset($_GET['removeFavorito'])) removeFavorito($filmeId);

$filmeDetalhes = exibirFilme($filmeId, $apiKey);
?>

<style>
/* ── Botão voltar ── */
.filme-voltar {
    display: inline-block;
    margin: 1.5% 0 0 3%;
}

/* ── Layout principal ── */
.filme-layout {
    display: flex;
    gap: 3.5%;
    align-items: flex-start;
    width: 72%;
    margin: 2.5% auto 0;
    padding-bottom: 4%;
}

/* ── Coluna poster ── */
.filme-poster-col {
    flex-shrink: 0;
    width: 22%;
}

.filme-poster-img {
    width: 100%;
    border-radius: 12px;
    display: block;
    box-shadow: 0 8px 32px rgba(0,0,0,0.6);
}

/* ── Coluna informações ── */
.filme-info-col {
    flex: 1;
    padding-top: 0.3em;
}

.filme-titulo {
    font-family: "Gabarito", sans-serif;
    font-size: 2vw;
    color: white;
    margin: 0 0 0.3em 0;
    line-height: 1.2;
}

.filme-meta {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.85vw;
    color: #777;
    margin-bottom: 1.8em;
    display: flex;
    align-items: center;
    gap: 0.5em;
}

.filme-meta-sep {
    color: #333;
}

/* ── Linhas de informação ── */
.filme-info-linha {
    display: flex;
    gap: 1.2em;
    margin-bottom: 1em;
    align-items: baseline;
}

.filme-info-label {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.75vw;
    color: #555;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    white-space: nowrap;
    width: 4.5em;
    flex-shrink: 0;
}

.filme-info-valor {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.9vw;
    color: #ccc;
    line-height: 1.5;
}

.filme-sinopse-linha {
    margin-top: 0.5em;
    align-items: flex-start;
}

.filme-sinopse-texto {
    color: #999;
    font-size: 0.85vw;
    line-height: 1.7;
}

/* ── Botões de ação ── */
.filme-acoes {
    display: flex;
    align-items: center;
    gap: 1.2em;
    margin-top: 1.8em;
}

.btn-favorito {
    font-size: 2vw;
    color: #444;
    text-decoration: none;
    line-height: 1;
    transition: color 0.2s, transform 0.2s;
    display: inline-block;
}
.btn-favorito:hover  { color: gold; transform: scale(1.15); }
.btn-favorito.ativo  { color: gold; }
</style>

<a href='index.php' class='btn-voltar filme-voltar' title='Voltar'>
    <img src='Imagens/voltar.png' class='icone-voltar' alt='Voltar'>
</a>

<?php echo $filmeDetalhes; ?>

<?php include 'footer.php'; ?>