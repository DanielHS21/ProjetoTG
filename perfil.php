<?php
include 'header.php';

// Redireciona se não estiver logado (o header já enviou HTML, então usamos JS)
if (empty($_SESSION['usuario_id'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$nome      = $_SESSION['usuario_nome'] ?? '';
$lista     = array_values(array_unique(array_map('intval', $_SESSION['lista'] ?? [])));
$favoritos = array_values(array_unique(array_map('intval', $_SESSION['favoritos'] ?? [])));

// ── Data de cadastro ──
$membroDesde = '';
$foto = $_SESSION['usuario_foto'] ?? null;
$bio  = '';
try {
    require_once 'db.php';
    $usuarioDb = buscarUsuario(getDB(), $_SESSION['usuario_id']);
    if ($usuarioDb) {
        $nome = $usuarioDb['nome'];
        $foto = $usuarioDb['foto'];
        $bio  = $usuarioDb['bio'] ?? '';
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_foto'] = $foto;
    }
    if ($criado = $usuarioDb['created_at'] ?? null) {
        $meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
        $ts = strtotime($criado);
        $membroDesde = $meses[(int)date('n', $ts) - 1] . ' de ' . date('Y', $ts);
    }
} catch (Throwable $e) { /* sem banco: apenas não mostra a data */ }

// ── Detalhes de todos os filmes (em paralelo, reaproveitando o cache do recomendador) ──
$todos = array_values(array_unique(array_merge($lista, $favoritos)));
$urls  = [];
foreach ($todos as $id) {
    $urls[$id] = tmdbUrl("/movie/$id", ['append_to_response' => 'credits,keywords,recommendations,similar'], $apiKey);
}
$filmes = $urls ? array_filter(tmdbGetMulti($urls, RECO_TTL_DETALHES)) : [];

// ── Estatísticas e "DNA" (baseado na lista; favoritos só entram se não estiverem nela) ──
$minutos = 0; $somaNotas = 0; $qtdNotas = 0;
$generos = []; $diretores = []; $atores = []; $decadas = []; $nomes = [];
foreach ($todos as $id) {
    if (empty($filmes[$id])) continue;
    $f = $filmes[$id];
    $minutos += (int)($f['runtime'] ?? 0);
    if (!empty($f['vote_average'])) { $somaNotas += $f['vote_average']; $qtdNotas++; }
    foreach ($f['genres'] ?? [] as $g) $generos[$g['name']] = ($generos[$g['name']] ?? 0) + 1;
    foreach ($f['credits']['crew'] ?? [] as $c) {
        if ($c['job'] === 'Director') { $diretores[$c['id']] = ($diretores[$c['id']] ?? 0) + 1; $nomes[$c['id']] = $c['name']; }
    }
    foreach (array_slice($f['credits']['cast'] ?? [], 0, 5) as $a) {
        $atores[$a['id']] = ($atores[$a['id']] ?? 0) + 1; $nomes[$a['id']] = $a['name'];
    }
    $ano = (int)substr($f['release_date'] ?? '', 0, 4);
    if ($ano) { $d = intdiv($ano, 10) * 10; $decadas[$d] = ($decadas[$d] ?? 0) + 1; }
}
arsort($generos); arsort($diretores); arsort($atores); arsort($decadas);
$totalGeneros = array_sum($generos) ?: 1;
$topGeneros   = array_slice($generos, 0, 5, true);
$topDiretor   = $diretores ? [$nomes[array_key_first($diretores)], reset($diretores)] : null;
$topAtor      = $atores    ? [$nomes[array_key_first($atores)], reset($atores)] : null;
$topDecada    = $decadas   ? array_key_first($decadas) : null;
$horas        = intdiv($minutos, 60);
$notaMedia    = $qtdNotas ? number_format($somaNotas / $qtdNotas, 1, ',', '') : '—';

// ── Capa: backdrop do último filme adicionado à lista ──
$backdrop = '';
foreach (array_reverse($lista ?: $favoritos) as $id) {
    if (!empty($filmes[$id]['backdrop_path'])) { $backdrop = 'https://image.tmdb.org/t/p/w1280' . $filmes[$id]['backdrop_path']; break; }
}

// ── Prévia das recomendações (só se já calculadas; não pesa no carregamento) ──
$recos = [];
$cache = $_SESSION['reco_cache'] ?? null;
if (is_array($cache) && !empty($cache['resultado']['itens'])) {
    foreach ($cache['resultado']['itens'] as $it) {
        if (in_array((int)$it['id'], $lista)) continue;
        $recos[] = $it;
        if (count($recos) >= 6) break;
    }
}

$recentes = array_slice(array_reverse($lista), 0, 8);
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);

function cardPerfil($id, $f, $favoritos, $h) {
    if (empty($f['poster_path'])) return '';
    $ano = substr($f['release_date'] ?? '', 0, 4);
    $fav = in_array($id, $favoritos) ? "<span class='pf-card-fav' title='Nos favoritos'>★</span>" : '';
    return "<a class='pf-card' href='filme.php?id=$id'>
              <div class='pf-card-poster'><img src='https://image.tmdb.org/t/p/w342{$h($f['poster_path'])}' alt='{$h($f['title'] ?? '')}' loading='lazy'>$fav</div>
              <p class='pf-card-titulo'>{$h($f['title'] ?? '')}</p>
              <p class='pf-card-ano'>$ano</p>
            </a>";
}
?>

<style>
.pf { padding-bottom: 3%; }

/* ── Capa ── */
.pf-capa {
    position: relative;
    height: 300px;
    background: #111 center 25% / cover no-repeat;
    margin-top: -1px;
}
.pf-capa::after {
    content: '';
    position: absolute;
    inset: 0;
    background:
        linear-gradient(to top, #000 0%, rgba(0,0,0,0.55) 55%, rgba(0,0,0,0.35) 100%),
        linear-gradient(to right, rgba(0,0,0,0.6), transparent 60%);
}
.pf-capa-sem { background: radial-gradient(ellipse at 30% 0%, #3a0000 0%, #0a0a0a 60%, #000 100%); }

.pf-topo {
    position: relative;
    z-index: 2;
    width: 88%;
    margin: -130px auto 0;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 2em;
}
.pf-identidade { display: flex; align-items: flex-end; gap: 1.5em; }
.pf-avatar-wrap { position: relative; flex-shrink: 0; }
.pf-avatar-wrap .avatar {
    width: 120px; height: 120px;
    font-size: 44px;
    border: 4px solid #000;
    box-shadow: 0 0 0 2px rgba(255,0,0,0.6), 0 10px 30px rgba(0,0,0,0.6);
}
.pf-avatar-editar {
    position: absolute; right: 2px; bottom: 6px;
    width: 34px; height: 34px; border-radius: 50%;
    background: #1a1a1a; border: 2px solid #000;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; text-decoration: none;
    opacity: 0; transition: opacity 0.2s, background 0.2s;
}
.pf-avatar-wrap:hover .pf-avatar-editar { opacity: 1; }
.pf-avatar-editar:hover { background: red; }
.pf-bio {
    font-family: "Josefin Sans", sans-serif;
    color: #ccc; font-size: 15px; font-style: italic;
    margin: 0.2em 0 0; max-width: 560px; line-height: 1.4;
}
.pf-nome {
    font-family: "Gabarito", sans-serif;
    font-size: 40px;
    color: white;
    margin: 0;
    line-height: 1.1;
}
.pf-sub {
    font-family: "Josefin Sans", sans-serif;
    color: #aaa;
    font-size: 15px;
    margin: 0.4em 0 0.3em;
}
.pf-acoes { display: flex; gap: 0.7em; padding-bottom: 0.6em; }
.pf-btn {
    font-family: "Josefin Sans", sans-serif;
    font-size: 14px;
    text-decoration: none;
    border-radius: 8px;
    padding: 0.6em 1.2em;
    transition: 0.2s;
    white-space: nowrap;
}
.pf-btn-primario { background: red; color: white; }
.pf-btn-primario:hover { background: #cc0000; }
.pf-btn-sec { color: #aaa; border: 1px solid #333; }
.pf-btn-sec:hover { color: white; border-color: red; background: #1a0000; }

/* ── Estatísticas ── */
.pf-stats {
    width: 88%;
    margin: 2.2em auto 0;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1em;
}
.pf-stat {
    background: linear-gradient(160deg, #141414, #0b0b0b);
    border: 1px solid #1f1f1f;
    border-radius: 14px;
    padding: 1.2em 1.4em;
    transition: border-color 0.2s, transform 0.2s;
    text-decoration: none;
    display: block;
}
a.pf-stat:hover { border-color: #400; transform: translateY(-2px); }
.pf-stat-valor {
    font-family: "Gabarito", sans-serif;
    font-size: 34px;
    color: white;
    line-height: 1;
}
.pf-stat-valor small { font-size: 16px; color: #888; margin-left: 0.15em; }
.pf-stat-label {
    font-family: "Josefin Sans", sans-serif;
    font-size: 13px;
    color: #777;
    margin-top: 0.5em;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.pf-stat-icone { float: right; font-size: 20px; opacity: 0.8; }

/* ── Seções ── */
.pf-secao { width: 88%; margin: 3em auto 0; }
.pf-secao-topo {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 1.1em;
}
.pf-secao-titulo {
    font-family: "Gabarito", sans-serif;
    font-size: 22px;
    color: white;
    margin: 0;
}
.pf-secao-titulo span { color: red; }
.pf-ver-tudo {
    font-family: "Josefin Sans", sans-serif;
    font-size: 14px;
    color: #888;
    text-decoration: none;
}
.pf-ver-tudo:hover { color: red; }

/* ── DNA ── */
.pf-dna { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1em; }
.pf-painel {
    background: #0d0d0d;
    border: 1px solid #1f1f1f;
    border-radius: 14px;
    padding: 1.4em 1.6em;
}
.pf-genero { margin-bottom: 0.9em; }
.pf-genero:last-child { margin-bottom: 0; }
.pf-genero-topo {
    display: flex; justify-content: space-between;
    font-family: "Josefin Sans", sans-serif;
    font-size: 14px; color: #ddd; margin-bottom: 0.35em;
}
.pf-genero-topo span:last-child { color: #777; }
.pf-barra { height: 8px; background: #1c1c1c; border-radius: 99px; overflow: hidden; }
.pf-barra div {
    height: 100%;
    background: linear-gradient(90deg, #8b0000, red);
    border-radius: 99px;
    width: 0;
    transition: width 1s cubic-bezier(.2,.8,.2,1);
}
.pf-destaques { display: flex; flex-direction: column; gap: 1em; justify-content: center; }
.pf-destaque { display: flex; align-items: center; gap: 0.9em; }
.pf-destaque-icone {
    width: 42px; height: 42px; border-radius: 10px;
    background: #1a0000; border: 1px solid #330000;
    display: flex; align-items: center; justify-content: center;
    font-size: 19px; flex-shrink: 0;
}
.pf-destaque-label {
    font-family: "Josefin Sans", sans-serif;
    font-size: 12px; color: #666;
    text-transform: uppercase; letter-spacing: 0.08em;
}
.pf-destaque-valor { font-family: "Josefin Sans", sans-serif; font-size: 16px; color: white; margin-top: 0.15em; }
.pf-destaque-valor small { color: #777; font-size: 13px; }

/* ── Fileiras de cards ── */
.pf-fileira {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: calc((100% - 7 * 1em) / 8);
    gap: 1em;
    overflow-x: auto;
    padding-bottom: 0.6em;
    scrollbar-width: thin;
    scrollbar-color: #333 transparent;
}
.pf-fileira.seis { grid-auto-columns: calc((100% - 5 * 1em) / 6); }
.pf-card { text-decoration: none; min-width: 0; }
.pf-card-poster { position: relative; }
.pf-card-poster img {
    width: 100%;
    aspect-ratio: 2 / 3;
    object-fit: cover;
    border-radius: 10px;
    display: block;
    transition: transform 0.3s;
    background: #151515;
}
.pf-card:hover .pf-card-poster img { transform: scale(1.04); }
.pf-card-fav {
    position: absolute; top: 6px; left: 8px;
    color: gold; font-size: 16px;
    filter: drop-shadow(0 0 4px rgba(0,0,0,0.9));
}
.pf-card-match {
    position: absolute; left: 6px; bottom: 6px;
    background: rgba(0,0,0,0.8); color: #46d369;
    font-family: "Josefin Sans", sans-serif;
    font-size: 12px; font-weight: 700;
    padding: 0.15em 0.5em; border-radius: 5px;
}
.pf-card-titulo {
    font-family: "Josefin Sans", sans-serif;
    font-size: 14px; color: #ddd;
    margin: 0.5em 0 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pf-card-ano { font-family: "Josefin Sans", sans-serif; font-size: 12px; color: #666; margin: 0.15em 0 0; }
.pf-card-motivo {
    font-family: "Josefin Sans", sans-serif;
    font-size: 12px; color: #888; margin: 0.25em 0 0;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

.pf-vazio {
    font-family: "Josefin Sans", sans-serif;
    color: #777; font-size: 15px;
    background: #0d0d0d; border: 1px dashed #2a2a2a; border-radius: 14px;
    padding: 1.6em; text-align: center;
}
.pf-vazio a { color: red; text-decoration: none; }

.pf-cta {
    display: flex; align-items: center; justify-content: space-between; gap: 1em;
    background: linear-gradient(110deg, #1a0000, #0d0d0d 70%);
    border: 1px solid #330000; border-radius: 14px;
    padding: 1.4em 1.6em;
    font-family: "Josefin Sans", sans-serif; color: #ccc; font-size: 15px;
}
.pf-cta strong { color: white; font-family: "Gabarito", sans-serif; font-size: 18px; display: block; margin-bottom: 0.2em; }

@media (max-width: 900px) {
    .pf-capa { height: 200px; }
    .pf-topo { flex-direction: column; align-items: flex-start; margin-top: -90px; width: 92%; }
    .pf-identidade { flex-direction: column; align-items: flex-start; gap: 0.8em; }
    .pf-avatar-wrap .avatar { width: 88px; height: 88px; font-size: 32px; }
    .pf-avatar-editar { opacity: 1; }
    .pf-acoes { flex-wrap: wrap; }
    .pf-nome { font-size: 28px; }
    .pf-stats, .pf-secao { width: 92%; }
    .pf-stats { grid-template-columns: repeat(2, 1fr); }
    .pf-dna { grid-template-columns: 1fr; }
    .pf-fileira, .pf-fileira.seis { grid-auto-columns: 38%; }
    .pf-cta { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="pf">

    <!-- Capa -->
    <div class="pf-capa <?php echo $backdrop ? '' : 'pf-capa-sem'; ?>"
         <?php if ($backdrop): ?>style="background-image:url('<?php echo $h($backdrop); ?>')"<?php endif; ?>></div>

    <div class="pf-topo">
        <div class="pf-identidade">
            <div class="pf-avatar-wrap">
                <?php echo avatarHtml($nome, $foto); ?>
                <a href="editarPerfil.php" class="pf-avatar-editar" title="Alterar foto">📷</a>
            </div>
            <div>
                <h1 class="pf-nome"><?php echo $h($nome); ?></h1>
                <p class="pf-sub">
                    <?php echo $membroDesde ? 'Membro desde ' . $h($membroDesde) : 'Cinéfilo da RecFilmes'; ?>
                    <?php if ($generos): ?> · Fã de <?php echo $h(array_key_first($generos)); ?><?php endif; ?>
                </p>
                <?php if ($bio): ?><p class="pf-bio">“<?php echo $h($bio); ?>”</p><?php endif; ?>
            </div>
        </div>
        <div class="pf-acoes">
            <a href="recomendacao.php" class="pf-btn pf-btn-primario">✨ Minhas recomendações</a>
            <a href="editarPerfil.php" class="pf-btn pf-btn-sec">✏️ Editar perfil</a>
            <a href="auth.php?acao=logout" class="pf-btn pf-btn-sec">Sair</a>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="pf-stats">
        <a class="pf-stat" href="listaFilmes.php">
            <span class="pf-stat-icone">📋</span>
            <div class="pf-stat-valor"><?php echo count($lista); ?></div>
            <div class="pf-stat-label">Filmes na lista</div>
        </a>
        <a class="pf-stat" href="listaFilmes.php">
            <span class="pf-stat-icone">⭐</span>
            <div class="pf-stat-valor"><?php echo count($favoritos); ?></div>
            <div class="pf-stat-label">Favoritos</div>
        </a>
        <div class="pf-stat">
            <span class="pf-stat-icone">⏱️</span>
            <div class="pf-stat-valor"><?php echo $horas; ?><small>h</small></div>
            <div class="pf-stat-label">De filmes salvos</div>
        </div>
        <div class="pf-stat">
            <span class="pf-stat-icone">🎯</span>
            <div class="pf-stat-valor"><?php echo $notaMedia; ?></div>
            <div class="pf-stat-label">Nota média (TMDB)</div>
        </div>
    </div>

    <!-- DNA cinematográfico -->
    <div class="pf-secao">
        <div class="pf-secao-topo">
            <h2 class="pf-secao-titulo">Seu DNA <span>cinematográfico</span></h2>
        </div>
        <?php if (!$topGeneros): ?>
            <div class="pf-vazio">Adicione alguns filmes à sua lista para descobrir seu perfil. <a href="index.php">Explorar filmes</a></div>
        <?php else: ?>
            <div class="pf-dna">
                <div class="pf-painel">
                    <?php foreach ($topGeneros as $g => $qtd): $pct = round(100 * $qtd / $totalGeneros); ?>
                        <div class="pf-genero">
                            <div class="pf-genero-topo"><span><?php echo $h($g); ?></span><span><?php echo $pct; ?>%</span></div>
                            <div class="pf-barra"><div data-largura="<?php echo min(100, round(100 * $qtd / reset($topGeneros))); ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="pf-painel pf-destaques">
                    <?php if ($topDiretor): ?>
                    <div class="pf-destaque">
                        <div class="pf-destaque-icone">🎬</div>
                        <div>
                            <div class="pf-destaque-label">Diretor mais presente</div>
                            <div class="pf-destaque-valor"><?php echo $h($topDiretor[0]); ?> <small>· <?php echo $topDiretor[1]; ?> filme<?php echo $topDiretor[1] > 1 ? 's' : ''; ?></small></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($topAtor): ?>
                    <div class="pf-destaque">
                        <div class="pf-destaque-icone">🎭</div>
                        <div>
                            <div class="pf-destaque-label">Ator / atriz mais presente</div>
                            <div class="pf-destaque-valor"><?php echo $h($topAtor[0]); ?> <small>· <?php echo $topAtor[1]; ?> filme<?php echo $topAtor[1] > 1 ? 's' : ''; ?></small></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($topDecada): ?>
                    <div class="pf-destaque">
                        <div class="pf-destaque-icone">📅</div>
                        <div>
                            <div class="pf-destaque-label">Década preferida</div>
                            <div class="pf-destaque-valor">Anos <?php echo $topDecada >= 2000 ? $topDecada : substr($topDecada, 2); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recomendados para você -->
    <div class="pf-secao">
        <div class="pf-secao-topo">
            <h2 class="pf-secao-titulo">Recomendados <span>para você</span></h2>
            <?php if ($recos): ?><a class="pf-ver-tudo" href="recomendacao.php">Ver todas →</a><?php endif; ?>
        </div>
        <?php if ($recos): ?>
            <div class="pf-fileira seis">
                <?php foreach ($recos as $r): ?>
                    <a class="pf-card" href="filme.php?id=<?php echo (int)$r['id']; ?>">
                        <div class="pf-card-poster">
                            <img src="https://image.tmdb.org/t/p/w342<?php echo $h($r['poster']); ?>" alt="<?php echo $h($r['titulo']); ?>" loading="lazy">
                            <span class="pf-card-match"><?php echo (int)$r['match']; ?>%</span>
                        </div>
                        <p class="pf-card-titulo"><?php echo $h($r['titulo']); ?></p>
                        <p class="pf-card-motivo"><?php echo $h($r['motivo']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php elseif ($lista): ?>
            <div class="pf-cta">
                <div><strong>Descubra seu próximo filme favorito</strong>Geramos sugestões a partir dos <?php echo count($lista); ?> filmes da sua lista.</div>
                <a href="recomendacao.php" class="pf-btn pf-btn-primario">Gerar recomendações</a>
            </div>
        <?php else: ?>
            <div class="pf-vazio">Monte sua lista para receber recomendações personalizadas. <a href="index.php">Começar agora</a></div>
        <?php endif; ?>
    </div>

    <!-- Adicionados recentemente -->
    <div class="pf-secao">
        <div class="pf-secao-topo">
            <h2 class="pf-secao-titulo">Adicionados <span>recentemente</span></h2>
            <?php if (count($lista) > count($recentes)): ?><a class="pf-ver-tudo" href="listaFilmes.php">Ver lista completa (<?php echo count($lista); ?>) →</a><?php endif; ?>
        </div>
        <?php if ($recentes): ?>
            <div class="pf-fileira">
                <?php foreach ($recentes as $id) echo isset($filmes[$id]) ? cardPerfil($id, $filmes[$id], $favoritos, $h) : ''; ?>
            </div>
        <?php else: ?>
            <div class="pf-vazio">Sua lista está vazia. <a href="index.php">Adicione filmes</a> para vê-los aqui.</div>
        <?php endif; ?>
    </div>

    <!-- Favoritos -->
    <div class="pf-secao">
        <div class="pf-secao-topo">
            <h2 class="pf-secao-titulo">Seus <span>favoritos</span></h2>
            <?php if (count($favoritos) > 8): ?><a class="pf-ver-tudo" href="listaFilmes.php">Ver todos (<?php echo count($favoritos); ?>) →</a><?php endif; ?>
        </div>
        <?php if ($favoritos): ?>
            <div class="pf-fileira">
                <?php foreach (array_slice(array_reverse($favoritos), 0, 8) as $id) echo isset($filmes[$id]) ? cardPerfil($id, $filmes[$id], $favoritos, $h) : ''; ?>
            </div>
        <?php else: ?>
            <div class="pf-vazio">Nenhum favorito ainda. Abra um filme e clique em ☆ para favoritar.</div>
        <?php endif; ?>
    </div>

</div>

<script>
// Anima as barras de gênero ao carregar
window.addEventListener('load', () => {
    document.getElementById('loading-overlay')?.classList.remove('visivel');
    requestAnimationFrame(() => {
        document.querySelectorAll('.pf-barra div').forEach(b => b.style.width = b.dataset.largura);
    });
});
</script>

<?php include 'footer.php'; ?>