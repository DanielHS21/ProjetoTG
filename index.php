<?php
include 'header.php';

$busca   = $_GET['busca'] ?? '';
$genero  = $_GET['genero'] ?? '';
$ordem   = $_GET['ordem'] ?? '';
$page    = (int)($_GET['page'] ?? 1);

$generos = [
    28    => 'Ação',
    12    => 'Aventura',
    16    => 'Animação',
    35    => 'Comédia',
    80    => 'Crime',
    99    => 'Documentário',
    18    => 'Drama',
    10751 => 'Família',
    14    => 'Fantasia',
    36    => 'História',
    27    => 'Terror',
    10402 => 'Música',
    9648  => 'Mistério',
    10749 => 'Romance',
    878   => 'Ficção Científica',
    53    => 'Thriller',
    10752 => 'Guerra',
    37    => 'Faroeste',
];

$ordens = [
    'popularity.desc'     => '🔥 Mais Populares',
    'vote_average.desc'   => '⭐ Mais Bem Avaliados',
    'release_date.desc'   => '🆕 Mais Novos',
    'release_date.asc'    => '🕰️ Mais Antigos',
    'revenue.desc'        => '💰 Maior Bilheteria',
];

// Monta a base da query string preservando os filtros ativos
function buildQuery($extra = [], $excluir = []) {
    $params = [];
    foreach (['busca', 'genero', 'ordem'] as $key) {
        if (in_array($key, $excluir)) continue;
        if (!empty($_GET[$key])) $params[$key] = $_GET[$key];
    }
    foreach ($extra as $k => $v) {
        if ($v === null) unset($params[$k]);
        else $params[$k] = $v;
    }
    return http_build_query($params);
}

// Label do título principal
function tituloAtual($busca, $genero, $ordem, $generos, $ordens) {
    $partes = [];
    if (!empty($busca))  $partes[] = htmlspecialchars($busca);
    if (!empty($genero)) $partes[] = $generos[(int)$genero] ?? '';
    if (!empty($ordem))  $partes[] = preg_replace('/^[^\s]+ /', '', $ordens[$ordem] ?? ''); // remove emoji
    return !empty($partes) ? implode(' — ', $partes) : 'Filmes Recentes';
}
?>

<style>
    /* ── Sidebar ── */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 98;
    }
    .sidebar-overlay.visivel { display: block; }

    .btn-toggle-sidebar {
        position: fixed;
        top: 12%;
        left: 0;
        z-index: 100;
        background-color: #111;
        color: #ccc;
        border: none;
        border-radius: 0 8px 8px 0;
        padding: 0.7em 0.9em;
        font-family: "Josefin Sans", sans-serif;
        font-size: 0.85vw;
        cursor: pointer;
        transition: 0.2s;
        writing-mode: vertical-rl;
        text-orientation: mixed;
    }
    .btn-toggle-sidebar:hover,
    .btn-toggle-sidebar.ativo { background-color: red; color: white; }

    .sidebar-generos {
        position: fixed;
        top: 0;
        left: -220px;
        width: 200px;
        height: 100vh;
        padding: 80px 12px 20px;
        background-color: #111;
        z-index: 99;
        overflow-y: auto;
        transition: left 0.3s ease;
        box-sizing: border-box;
    }
    .sidebar-generos.aberta { left: 0; }

    .sidebar-titulo {
        font-family: "Gabarito", sans-serif;
        font-size: 15px;
        color: red;
        margin: 0 0 0.6em 0;
        text-align: center;
        border-bottom: 1px solid #333;
        padding-bottom: 0.5em;
    }

    .sidebar-secao {
        margin-bottom: 1.2em;
    }

    .sidebar-secao-label {
        font-family: "Josefin Sans", sans-serif;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #555;
        margin: 0 0 0.4em 4px;
    }

    .genero-lista {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .genero-item {
        display: block;
        color: #ccc;
        text-decoration: none;
        font-family: "Josefin Sans", sans-serif;
        font-size: 12.5px;
        padding: 5px 10px;
        border-radius: 6px;
        transition: 0.2s;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .genero-item:hover { color: white; background-color: #222; }
    .genero-item.ativo { color: white; background-color: red; }

    /* ── Tags de filtros ativos ── */
    .filtros-ativos {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5em;
        margin: 0.8em auto 0;
        width: 90%;
    }
    .tag-filtro {
        display: inline-flex;
        align-items: center;
        gap: 0.4em;
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 20px;
        padding: 0.25em 0.75em;
        font-family: "Josefin Sans", sans-serif;
        font-size: 0.75vw;
        color: #ccc;
        text-decoration: none;
    }
    .tag-filtro .remover {
        color: #666;
        font-size: 0.9em;
        transition: color 0.2s;
    }
    .tag-filtro:hover .remover { color: red; }

    /* Poster wrapper */
    .filme-card {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .poster-wrapper {
        position: relative;
        width: 15vw;
        display: block;
    }
    .poster-wrapper img {
        width: 100%;
        border-radius: 15px;
        display: block;
        transition: 0.3s;
        cursor: pointer;
    }
    .poster-wrapper img:hover { transform: scale(1.05); }
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
        height: 1.6vw;
        width: auto;
        filter: drop-shadow(0 0 5px rgba(0,0,0,0.9));
        transition: transform 0.2s;
        cursor: pointer;
    }
    .icone-hover-lista:hover { transform: scale(1.2); }
</style>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<button class="btn-toggle-sidebar" id="btn-toggle" onclick="toggleSidebar()" title="Filtros">
    ☰ Filtros
</button>

<aside class="sidebar-generos" id="sidebar-generos">
    <h3 class="sidebar-titulo">Filtros</h3>

    <!-- Seção: Ordenar -->
    <div class="sidebar-secao">
        <p class="sidebar-secao-label">Ordenar por</p>
        <ul class="genero-lista">
            <li>
                <a href="index.php?<?php echo buildQuery([], ['ordem']); ?>"
                   class="genero-item <?php echo empty($ordem) ? 'ativo' : ''; ?>">
                    Padrão
                </a>
            </li>
            <?php foreach ($ordens as $val => $label): ?>
            <li>
                <a href="index.php?<?php echo buildQuery(['ordem' => $val]); ?>"
                   class="genero-item <?php echo $ordem === $val ? 'ativo' : ''; ?>">
                    <?php echo $label; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Seção: Gêneros -->
    <div class="sidebar-secao">
        <p class="sidebar-secao-label">Gênero</p>
        <ul class="genero-lista">
            <li>
                <a href="index.php?<?php echo buildQuery([], ['genero']); ?>"
                   class="genero-item <?php echo empty($genero) ? 'ativo' : ''; ?>">
                    Todos
                </a>
            </li>
            <?php foreach ($generos as $id => $nome): ?>
            <li>
                <a href="index.php?<?php echo buildQuery(['genero' => $id]); ?>"
                   class="genero-item <?php echo $genero == $id ? 'ativo' : ''; ?>">
                    <?php echo $nome; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</aside>

<div style="width:100%; text-align:center;">
    <h1 class='titulo'>
        <?php echo tituloAtual($busca, $genero, $ordem, $generos, $ordens); ?>
    </h1>

    <!-- Tags de filtros ativos -->
    <?php
        $temFiltros = !empty($genero) || !empty($ordem);
        if ($temFiltros):
    ?>
    <div class="filtros-ativos">
        <?php if (!empty($genero)): ?>
            <a href="index.php?<?php echo buildQuery([], ['genero']); ?>" class="tag-filtro">
                <?php echo $generos[(int)$genero] ?? ''; ?>
                <span class="remover">✕</span>
            </a>
        <?php endif; ?>
        <?php if (!empty($ordem)): ?>
            <a href="index.php?<?php echo buildQuery([], ['ordem']); ?>" class="tag-filtro">
                <?php echo $ordens[$ordem] ?? ''; ?>
                <span class="remover">✕</span>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $page; $i++): ?>
        <?php echo listarFilmes($busca, $apiKey, $i, $genero, $ordem); ?>
    <?php endfor; ?>

    <?php
        $proximaPagina = $page + 1;
        $urlProxima = "index.php?" . buildQuery(['page' => $proximaPagina]);
    ?>
    <a class='btn-descubra' href='<?php echo $urlProxima; ?>'>Descubra mais</a>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar-generos');
    const overlay = document.getElementById('sidebar-overlay');
    const btn     = document.getElementById('btn-toggle');
    sidebar.classList.toggle('aberta');
    overlay.classList.toggle('visivel');
    btn.classList.toggle('ativo');

    if (sidebar.classList.contains('aberta')) {
        btn.style.top = '2%';
    } else {
        btn.style.top = '12%';
    }
}
</script>

<script>
function alterarLista(filmeId, el) {
    const icone   = document.getElementById('icone-' + filmeId);
    const naLista = icone.src.includes('del.png');
    const acao    = naLista ? 'removeLista' : 'addLista';
    const url     = 'filme.php?id=' + filmeId + '&' + acao + '=1';

    fetch(url).then(() => {
        if (naLista) {
            icone.src = 'Imagens/add.png';
            el.title  = 'Adicionar à lista';
        } else {
            icone.src = 'Imagens/del.png';
            el.title  = 'Remover da lista';
        }
    });
}
</script>

<script>
window.addEventListener('load', function() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.classList.remove('visivel');
});
</script>
<?php include 'footer.php'; ?>