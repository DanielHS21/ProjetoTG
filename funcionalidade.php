<?php
echo "<link rel='stylesheet' href='estyle.css'>";

$apiKey = "5ced0e51cd24880895288b3d6c5571c9";

function listarFilmes($busca, $apiKey, $page = 1, $generoId = '', $ordem = '') {

    $pessoaId     = null;
    $usandoPessoa = false;

    if (!empty($busca)) {
        // ── Passo 1: verifica se o termo buscado corresponde a uma pessoa conhecida ──
        $urlPessoa = "https://api.themoviedb.org/3/search/person?api_key=$apiKey&query=" . urlencode($busca) . "&language=pt-BR";
        $rPessoa   = file_get_contents($urlPessoa);
        if ($rPessoa !== FALSE) {
            $dataPessoa = json_decode($rPessoa, true);
            if (!empty($dataPessoa['results'])) {
                $primeira   = $dataPessoa['results'][0];
                $nomePessoa = strtolower(trim($primeira['name'] ?? ''));
                $termoBusca = strtolower(trim($busca));
                $popularity = $primeira['popularity'] ?? 0;

                // Usa busca por pessoa se o nome da API for igual OU contiver o termo completo E a pessoa for popular
                if (
                    isset($primeira['known_for_department']) &&
                    ($nomePessoa === $termoBusca || (stripos($nomePessoa, $termoBusca) !== false && $popularity >= 5))
                ) {
                    $pessoaId     = $primeira['id'];
                    $usandoPessoa = true;
                }
            }
        }

        // ── Passo 2: se não identificou uma pessoa, usa busca por título ──
        // (o fluxo abaixo já trata isso via $usandoPessoa = false)
    }

    // Monta a URL conforme o caso
    if ($usandoPessoa && $pessoaId) {
        // Busca filmes do ator/diretor via discover
        $url = "https://api.themoviedb.org/3/discover/movie?api_key=$apiKey&language=pt-BR&sort_by=popularity.desc&with_cast=$pessoaId&page=$page";
        if (!empty($generoId)) {
            $url .= "&with_genres=$generoId";
        }
    } elseif (!empty($busca)) {
        $url = "https://api.themoviedb.org/3/search/movie?api_key=$apiKey&query=" . urlencode($busca) . "&language=pt-BR&page=$page";
    } elseif (!empty($generoId)) {
        $sortBy = !empty($ordem) ? $ordem : 'popularity.desc';
        // Filmes muito antigos com vote_average podem ter poucos votos; adiciona filtro mínimo
        $voteFilter = ($ordem === 'vote_average.desc') ? '&vote_count.gte=300' : '';
        $url = "https://api.themoviedb.org/3/discover/movie?api_key=$apiKey&with_genres=$generoId&language=pt-BR&sort_by=$sortBy$voteFilter&page=$page";
    } else {
        if (!empty($ordem)) {
            $sortBy = $ordem;
            $voteFilter = ($ordem === 'vote_average.desc') ? '&vote_count.gte=300' : '';
            $url = "https://api.themoviedb.org/3/discover/movie?api_key=$apiKey&language=pt-BR&sort_by=$sortBy$voteFilter&page=$page";
        } else {
            $url = "https://api.themoviedb.org/3/movie/popular?api_key=$apiKey&language=pt-BR&page=$page";
        }
    }

    $response = file_get_contents($url);
    if ($response === FALSE) return "<p>Erro ao buscar filmes</p>";

    $data = json_decode($response, true);
    $html = "<div class='carousel'>";

    // Quando busca por título + gênero, filtra manualmente por gênero
    if (!$usandoPessoa && !empty($busca) && !empty($generoId)) {
        $filmes = array_filter($data['results'], function($filme) use ($generoId) {
            return in_array((int)$generoId, $filme['genre_ids'] ?? []);
        });
        $filmes = array_slice(array_values($filmes), 0, 5);
    } else {
        $offset = (($page - 1) % 4) * 5;
        $filmes = array_slice($data['results'], $offset, 5);
    }

    if (empty($filmes)) {
        $html .= "<p class='msg-nao-encontrado'>Nenhum filme encontrado para <strong>" . htmlspecialchars($busca) . "</strong>. Tente outro título ou nome de ator.</p>";
    } else {
        foreach ($filmes as $filme) {
            $id      = $filme['id'];
            $poster  = "https://image.tmdb.org/t/p/w300" . $filme['poster_path'];
            $naLista = isset($_SESSION['lista']) && in_array($id, $_SESSION['lista']);
            $icone   = $naLista ? 'Imagens/del.png' : 'Imagens/add.png';
            $title   = $naLista ? 'Remover da lista' : 'Adicionar à lista';

            $html .= "
                <div class='filme-card' id='card-$id'>
                    <div class='poster-wrapper'>
                        <img src='$poster' onclick=\"window.location.href='filme.php?id=$id'\">
                        <a href='javascript:void(0)'
                           class='btn-hover-lista'
                           title='$title'
                           onclick=\"alterarLista($id, this)\">
                            <img src='$icone' class='icone-hover-lista' id='icone-$id'>
                        </a>
                    </div>
                </div>
            ";
        }
    }

    $html .= "</div>";
    return $html;
}

function exibirFilme($id, $apiKey) {
    // Dados do filme
    $url      = "https://api.themoviedb.org/3/movie/$id?api_key=$apiKey&language=pt-BR";
    $response = file_get_contents($url);
    if ($response === FALSE) return "<p>Erro ao buscar detalhes do filme</p>";
    $filme = json_decode($response, true);

    // Créditos (elenco e direção)
    $urlCredits  = "https://api.themoviedb.org/3/movie/$id/credits?api_key=$apiKey&language=pt-BR";
    $rCredits    = file_get_contents($urlCredits);
    $credits     = $rCredits ? json_decode($rCredits, true) : [];

    // Diretor(es)
    $diretores = [];
    foreach ($credits['crew'] ?? [] as $membro) {
        if ($membro['job'] === 'Director') $diretores[] = $membro['name'];
    }

    // Top 3 atores
    $elenco = array_slice(array_column($credits['cast'] ?? [], 'name'), 0, 3);

    // Gêneros
    $generos = array_column($filme['genres'] ?? [], 'name');

    // Ano e duração
    $ano     = $filme['release_date'] ? substr($filme['release_date'], 0, 4) : '—';
    $duracao = $filme['runtime'] ? intdiv($filme['runtime'], 60) . 'h' . str_pad($filme['runtime'] % 60, 2, '0') : '—';
    $nota    = $filme['vote_average'] ? number_format($filme['vote_average'], 1) : '—';

    $poster  = "https://image.tmdb.org/t/p/w342" . $filme['poster_path'];
    $titulo  = htmlspecialchars($filme['title']);
    $sinopse = htmlspecialchars($filme['overview'] ?: 'Sinopse não disponível.');

    // Botão lista
    $naLista     = isset($_SESSION['lista']) && in_array($id, $_SESSION['lista']);
    $btnListaUrl = $naLista ? "filme.php?id=$id&removeLista=1" : "filme.php?id=$id&addLista=1";
    $btnListaImg = $naLista ? 'Imagens/del.png' : 'Imagens/add.png';
    $btnListaTip = $naLista ? 'Remover da Lista' : 'Adicionar à Lista';

    $html  = "<div class='filme-layout'>";

    // ── Coluna esquerda: poster ──
    $html .= "<div class='filme-poster-col'>";
    $html .= "  <img src='$poster' class='filme-poster-img' alt='$titulo'>";
    $html .= "</div>";

    // ── Coluna direita: informações ──
    $html .= "<div class='filme-info-col'>";
    $html .= "  <h1 class='filme-titulo'>$titulo</h1>";
    $html .= "  <div class='filme-meta'>";
    $html .= "    <span>$ano</span>";
    $html .= "    <span class='filme-meta-sep'>·</span>";
    $html .= "    <span>⭐ $nota</span>";
    $html .= "    <span class='filme-meta-sep'>·</span>";
    $html .= "    <span>$duracao</span>";
    $html .= "  </div>";

    if (!empty($diretores)) {
        $html .= "  <div class='filme-info-linha'>";
        $html .= "    <span class='filme-info-label'>Direção</span>";
        $html .= "    <span class='filme-info-valor'>" . htmlspecialchars(implode(', ', $diretores)) . "</span>";
        $html .= "  </div>";
    }

    if (!empty($elenco)) {
        $html .= "  <div class='filme-info-linha'>";
        $html .= "    <span class='filme-info-label'>Elenco</span>";
        $html .= "    <span class='filme-info-valor'>" . htmlspecialchars(implode(', ', $elenco)) . "</span>";
        $html .= "  </div>";
    }

    if (!empty($generos)) {
        $html .= "  <div class='filme-info-linha'>";
        $html .= "    <span class='filme-info-label'>Gênero</span>";
        $html .= "    <span class='filme-info-valor'>" . htmlspecialchars(implode(', ', $generos)) . "</span>";
        $html .= "  </div>";
    }

    $html .= "  <div class='filme-info-linha filme-sinopse-linha'>";
    $html .= "    <span class='filme-info-label'>Sinopse</span>";
    $html .= "    <span class='filme-info-valor filme-sinopse-texto'>$sinopse</span>";
    $html .= "  </div>";

    // Botões de ação
    $html .= "  <div class='filme-acoes'>";
    $html .= "    <a href='$btnListaUrl' class='btn-lista' title='$btnListaTip'><img src='$btnListaImg' class='icone-lista' alt='$btnListaTip'></a>";

    if (!empty($_SESSION['usuario_id'])) {
        if (isFavorito($id)) {
            $html .= "    <a href='filme.php?id=$id&removeFavorito=1' class='btn-favorito ativo' title='Remover dos Favoritos'>★</a>";
        } else {
            $html .= "    <a href='filme.php?id=$id&addFavorito=1' class='btn-favorito' title='Adicionar aos Favoritos'>☆</a>";
        }
    }

    $html .= "  </div>";
    $html .= "</div>"; // .filme-info-col
    $html .= "</div>"; // .filme-layout

    return $html;
}


function exibirFilmeCard($id, $apiKey) {
    $url = "https://api.themoviedb.org/3/movie/$id?api_key=$apiKey&language=pt-BR";
    $response = file_get_contents($url);
    if ($response === FALSE) return "<p>Erro ao buscar filme</p>";

    $filme   = json_decode($response, true);
    $poster  = "https://image.tmdb.org/t/p/w300" . $filme['poster_path'];
    $naLista = isset($_SESSION['lista']) && in_array($id, $_SESSION['lista']);
    $icone   = $naLista ? 'Imagens/del.png' : 'Imagens/add.png';
    $title   = $naLista ? 'Remover da lista' : 'Adicionar à lista';

    $html  = "<div class='filme-card' id='card-$id'>";
    $html .= "  <div class='poster-wrapper'>";
    $html .= "      <img src='$poster' onclick=\"window.location.href='filme.php?id=$id'\" title='" . htmlspecialchars($filme['title']) . "'>";
    $html .= "      <a href='javascript:void(0)' class='btn-hover-lista' title='$title' onclick=\"alterarLista($id, this)\">";
    $html .= "          <img src='$icone' class='icone-hover-lista' id='icone-$id'>";
    $html .= "      </a>";
    $html .= "  </div>";
    $html .= "  <p class='filme-card-titulo'>" . htmlspecialchars($filme['title']) . "</p>";
    $html .= "</div>";

    return $html;
}

function addLista($filmeId) {
    if (!isset($_SESSION['lista'])) {
        $_SESSION['lista'] = [];
    }
    $filmeId = (int)$filmeId;
    if (!in_array($filmeId, $_SESSION['lista'])) {
        $_SESSION['lista'][] = $filmeId;
    }

    // Persiste no banco se o usuário estiver logado
    if (!empty($_SESSION['usuario_id'])) {
        require_once 'db.php';
        $pdo  = getDB();
        $stmt = $pdo->prepare('INSERT IGNORE INTO lista_filmes (usuario_id, filme_id) VALUES (?, ?)');
        $stmt->execute([$_SESSION['usuario_id'], $filmeId]);
    }
}

function removeLista($filmeId) {
    $filmeId = (int)$filmeId;
    if (isset($_SESSION['lista'])) {
        $_SESSION['lista'] = array_values(array_diff($_SESSION['lista'], [$filmeId]));
    }

    // Remove do banco se o usuário estiver logado
    if (!empty($_SESSION['usuario_id'])) {
        require_once 'db.php';
        $pdo  = getDB();
        $stmt = $pdo->prepare('DELETE FROM lista_filmes WHERE usuario_id = ? AND filme_id = ?');
        $stmt->execute([$_SESSION['usuario_id'], $filmeId]);
    }
}


function addFavorito($filmeId) {
    $filmeId = (int)$filmeId;
    if (empty($_SESSION['usuario_id'])) return; // só para logados

    if (!isset($_SESSION['favoritos'])) $_SESSION['favoritos'] = [];
    if (!in_array($filmeId, $_SESSION['favoritos'])) {
        $_SESSION['favoritos'][] = $filmeId;
    }

    require_once 'db.php';
    $pdo  = getDB();
    $stmt = $pdo->prepare('INSERT IGNORE INTO favoritos (usuario_id, filme_id) VALUES (?, ?)');
    $stmt->execute([$_SESSION['usuario_id'], $filmeId]);
}

function removeFavorito($filmeId) {
    $filmeId = (int)$filmeId;
    if (empty($_SESSION['usuario_id'])) return;

    if (isset($_SESSION['favoritos'])) {
        $_SESSION['favoritos'] = array_values(array_diff($_SESSION['favoritos'], [$filmeId]));
    }

    require_once 'db.php';
    $pdo  = getDB();
    $stmt = $pdo->prepare('DELETE FROM favoritos WHERE usuario_id = ? AND filme_id = ?');
    $stmt->execute([$_SESSION['usuario_id'], $filmeId]);
}

function carregarFavoritosDoBanco($pdo, $usuarioId) {
    $stmt = $pdo->prepare('SELECT filme_id FROM favoritos WHERE usuario_id = ? ORDER BY adicionado_em ASC');
    $stmt->execute([$usuarioId]);
    $_SESSION['favoritos'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function isFavorito($filmeId) {
    return isset($_SESSION['favoritos']) && in_array((int)$filmeId, $_SESSION['favoritos']);
}

function exibirFilmeCardFavorito($id, $apiKey) {
    $url = "https://api.themoviedb.org/3/movie/$id?api_key=$apiKey&language=pt-BR";
    $response = file_get_contents($url);
    if ($response === FALSE) return '';

    $filme  = json_decode($response, true);
    $poster = "https://image.tmdb.org/t/p/w300" . $filme['poster_path'];

    $html  = "<div class='filme-card' id='fav-card-$id'>";
    $html .= "  <div class='poster-wrapper'>";
    $html .= "      <img src='$poster' onclick=\"window.location.href='filme.php?id=$id'\" title='" . htmlspecialchars($filme['title']) . "'>";
    $html .= "      <a href='javascript:void(0)' class='btn-hover-fav' title='Remover dos favoritos' onclick=\"removerFavorito($id)\">★</a>";
    $html .= "  </div>";
    $html .= "  <p class='filme-card-titulo'>" . htmlspecialchars($filme['title']) . "</p>";
    $html .= "</div>";

    return $html;
}

function analiseLista($apiKey) {
    // Usa cache na sessão para não refazer requisições
    if (isset($_SESSION['generos_cache'])) {
        return $_SESSION['generos_cache'];
    }

    $generos = [];
    foreach ($_SESSION['lista'] as $filmeId) {
        $url = "https://api.themoviedb.org/3/movie/$filmeId?api_key=$apiKey&language=pt-BR";
        $response = file_get_contents($url);
        if ($response === FALSE) continue;

        $filme = json_decode($response, true);
        foreach ($filme['genres'] as $genero) {
            $generos[$genero['name']] = ($generos[$genero['name']] ?? 0) + 1;
        }
    }

    $_SESSION['generos_cache'] = $generos; // salva cache
    return $generos;
}

function recomendarFilme($lista, $apiKey) {

    $lista = array_slice($lista, -5);
    $contagemGeneros   = [];
    $contagemAtores    = [];
    $contagemDiretores = [];
    $pontuacao         = [];
    $todosRecomendados = [];

    // ── Passo 1: coletar gêneros, atores e diretores da lista ──
    foreach ($lista as $index => $filmeId) {
        $peso = $index + 1;

        // Cache do filme
        if (!isset($_SESSION['filme_cache'][$filmeId])) {
            $url = "https://api.themoviedb.org/3/movie/$filmeId?api_key=$apiKey&language=pt-BR";
            $r   = file_get_contents($url);
            if ($r === FALSE) continue;
            $_SESSION['filme_cache'][$filmeId] = json_decode($r, true);
        }
        $data = $_SESSION['filme_cache'][$filmeId];

        foreach ($data['genres'] as $g) {
            $contagemGeneros[$g['id']] = ($contagemGeneros[$g['id']] ?? 0) + $peso;
        }

        // Cache dos créditos
        if (!isset($_SESSION['credits_cache'][$filmeId])) {
            $url = "https://api.themoviedb.org/3/movie/$filmeId/credits?api_key=$apiKey";
            $r   = file_get_contents($url);
            if ($r !== FALSE) {
                $_SESSION['credits_cache'][$filmeId] = json_decode($r, true);
            }
        }

        if (isset($_SESSION['credits_cache'][$filmeId])) {
            $credits = $_SESSION['credits_cache'][$filmeId];

            // Top 3 atores do elenco principal
            foreach (array_slice($credits['cast'] ?? [], 0, 3) as $ator) {
                $contagemAtores[$ator['id']] = ($contagemAtores[$ator['id']] ?? 0) + $peso;
            }

            // Diretor
            foreach ($credits['crew'] ?? [] as $membro) {
                if ($membro['job'] === 'Director') {
                    $contagemDiretores[$membro['id']] = ($contagemDiretores[$membro['id']] ?? 0) + $peso;
                }
            }
        }
    }

    // ── Passo 2: buscar candidatos via /discover filtrando por ator ou diretor recorrente ──
    // Gêneros predominantes para usar como filtro adicional
    arsort($contagemGeneros);
    $generosIds = implode(',', array_slice(array_keys($contagemGeneros), 0, 3));

    // Atores que aparecem em 2+ filmes
    $atoresFrequentes = array_keys(array_filter($contagemAtores, function($v) {
    return $v >= 2;
}));

    // Diretores que aparecem em 2+ filmes
    $diretoresFrequentes = array_keys(array_filter($contagemDiretores, function($v) {
    return $v >= 2;
}));

    $consultas = [];

    foreach ($diretoresFrequentes as $dirId) {
        $consultas[] = [
            'url'   => "https://api.themoviedb.org/3/discover/movie?api_key=$apiKey&language=pt-BR&sort_by=vote_average.desc&vote_count.gte=500&with_crew=$dirId",
            'bonus' => 15, // diretor recorrente tem peso alto
        ];
    }

    foreach ($atoresFrequentes as $atorId) {
        $consultas[] = [
            'url'   => "https://api.themoviedb.org/3/discover/movie?api_key=$apiKey&language=pt-BR&sort_by=vote_average.desc&vote_count.gte=500&with_cast=$atorId",
            'bonus' => 10,
        ];
    }

    // Fallback: busca por gêneros + filmes similares se não há atores/diretores frequentes
    if (empty($consultas)) {
        foreach ($lista as $filmeId) {
            $url = "https://api.themoviedb.org/3/movie/$filmeId/similar?api_key=$apiKey&language=pt-BR";
            $r   = file_get_contents($url);
            if ($r === FALSE) continue;
            $data = json_decode($r, true);
            foreach ($data['results'] as $filme) {
                if (in_array($filme['id'], $lista)) continue;
                if (empty($filme['poster_path'])) continue;
                $pontos = ($filme['vote_average'] ?? 0);
                foreach ($filme['genre_ids'] as $gId) {
                    $pontos += $contagemGeneros[$gId] ?? 0;
                }
                if (!isset($pontuacao[$filme['id']]) || $pontos > $pontuacao[$filme['id']]) {
                    $pontuacao[$filme['id']] = $pontos;
                    $todosRecomendados[$filme['id']] = $filme;
                }
            }
        }
    }

    // ── Passo 3: pontuar candidatos das consultas ──
    foreach ($consultas as $consulta) {
        $r = file_get_contents($consulta['url']);
        if ($r === FALSE) continue;
        $data = json_decode($r, true);

        foreach ($data['results'] ?? [] as $filme) {
            if (in_array($filme['id'], $lista)) continue;
            if (empty($filme['poster_path'])) continue;

            // Ignorar documentários (genre_id 99)
            if (in_array(99, $filme['genre_ids'] ?? [])) continue;

            $pontos = $consulta['bonus'];

            // Bônus por gêneros em comum com a lista
            foreach ($filme['genre_ids'] as $gId) {
                $pontos += $contagemGeneros[$gId] ?? 0;
            }

            // Bônus por nota (filmes bem avaliados sobem)
            $pontos += $filme['vote_average'] ?? 0;

            // Bônus/penalidade por ano
            $ano = isset($filme['release_date']) ? (int)substr($filme['release_date'], 0, 4) : 0;
            if ($ano < 2000) $pontos -= 3;
            if ($ano >= 2015) $pontos += 2;

            if (!isset($pontuacao[$filme['id']]) || $pontos > $pontuacao[$filme['id']]) {
                $pontuacao[$filme['id']] = $pontos;
                $todosRecomendados[$filme['id']] = $filme;
            }
        }
    }

    if (empty($todosRecomendados)) {
        return "<p>Nenhuma recomendação encontrada.</p>";
    }

    arsort($pontuacao);
    $topIds = array_slice(array_keys($pontuacao), 0, 3);

    $html  = "<h2>Filmes Recomendados</h2>";
    $html .= "<div class='carousel'>";
    foreach ($topIds as $id) {
        if (!isset($todosRecomendados[$id])) continue;
        $filme  = $todosRecomendados[$id];
        $poster = "https://image.tmdb.org/t/p/w300" . $filme['poster_path'];
        $html  .= "<div class='filme-card'>";
        $html  .= "<img src='$poster' onclick=\"window.location.href='filme.php?id=$id'\">";
        $html  .= "</div>";
    }
    $html .= "</div>";

    return $html;
}