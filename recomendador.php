<?php
// ============================================================
//  Motor de recomendação — ProjetoTG
//
//  Visão geral do algoritmo
//  ------------------------
//  1. SEMENTES: filmes da lista (+ favoritos, com peso MENOR). Filmes
//     adicionados mais recentemente pesam mais (decaimento suave).
//  2. PERFIL: a partir das sementes monta-se um "gosto" ponderado:
//     gêneros, palavras-chave (temas), diretores, roteiristas, elenco,
//     idioma original e época.
//  3. CANDIDATOS vêm de várias fontes:
//       - /recommendations do TMDB (sinal colaborativo: "quem viu X também viu Y")
//       - /similar do TMDB
//       - /discover por palavras-chave e combinação de gêneros do perfil
//       - /discover por diretores/atores que se repetem nas sementes
//  4. ENRIQUECIMENTO: os melhores candidatos têm detalhes, palavras-chave
//     e créditos buscados (em paralelo, com cache em disco).
//  5. PONTUAÇÃO final combinando: sinal colaborativo, similaridade de
//     gêneros, similaridade de temas, pessoas em comum, qualidade
//     (média bayesiana), proximidade de época e idioma — com penalidades
//     para gêneros que o usuário nunca escolhe.
//  6. DIVERSIDADE (MMR): evita 5 filmes da mesma franquia/diretor.
//  7. EXPLICAÇÃO: cada recomendação diz por que foi sugerida.
// ============================================================

const RECO_TTL_DETALHES = 604800;   // 7 dias
const RECO_TTL_LISTAS   = 86400;    // 1 dia
const RECO_MAX_SEMENTES = 30;
const RECO_MAX_ENRIQUECER = 60;
const RECO_TOTAL = 24;              // 12 visíveis + 12 em "Mostrar mais"

// Palavras-chave genéricas que não dizem nada sobre o gosto do usuário
const RECO_KW_IGNORAR = [
    'duringcreditsstinger', 'aftercreditsstinger', 'woman director', 'based on novel or book',
    'sequel', 'prequel', 'remake', '3d', 'imax', 'live action remake', 'based on young adult novel',
    'new york city', 'los angeles, california', 'london, england', 'paris, france', 'usa',
    'duplicate', 'female protagonist', 'male protagonist', 'murder', 'friendship', 'love',
];

// Gêneros "de nicho": se o usuário nunca escolhe, recomendá-los costuma errar feio
const RECO_GENEROS_SENSIVEIS = [27, 16, 10751, 10749, 10402, 37, 99, 10770];

// ─────────────────────────────────────────────────────────────
//  Camada HTTP: requisições paralelas + cache em disco
// ─────────────────────────────────────────────────────────────

function tmdbUrl($path, array $params, $apiKey) {
    $params = array_merge(['api_key' => $apiKey, 'language' => 'pt-BR'], $params);
    return 'https://api.themoviedb.org/3' . $path . '?' . http_build_query($params);
}

function recoCacheDir() {
    static $dir = false;
    if ($dir !== false) return $dir;
    $d = __DIR__ . '/cache/tmdb';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    $dir = is_writable($d) ? $d : null;
    if ($dir) limparCacheTmdb($dir);
    return $dir;
}

// ─────────────────────────────────────────────────────────────
//  Limpeza automática do cache
//  Roda no máximo a cada 6 horas (controlado por um arquivo-marcador),
//  durante uma visita qualquer ao site — não precisa de agendador.
//    1. apaga arquivos mais velhos que CACHE_IDADE_MAX
//    2. se ainda passar de CACHE_TAMANHO_MAX, apaga os mais antigos
// ─────────────────────────────────────────────────────────────
const CACHE_IDADE_MAX     = 7 * 86400;           // 7 dias (= maior validade usada)
const CACHE_TAMANHO_MAX   = 100 * 1024 * 1024;   // 100 MB
const CACHE_INTERVALO     = 6 * 3600;            // verifica a cada 6 horas

function limparCacheTmdb($dir, $forcar = false) {
    $marcador = $dir . '/.ultima_limpeza';
    clearstatcache();
    if (!$forcar && is_file($marcador) && time() - filemtime($marcador) < CACHE_INTERVALO) return null;
    @touch($marcador);   // marca antes, para duas visitas simultâneas não limparem juntas

    $agora = time();
    $restantes = [];
    $total = 0;
    $apagados = 0;
    foreach (glob($dir . '/*.json') ?: [] as $arq) {
        $mtime = @filemtime($arq);
        if ($mtime === false) continue;
        if ($agora - $mtime > CACHE_IDADE_MAX) {
            if (@unlink($arq)) $apagados++;
            continue;
        }
        $tam = @filesize($arq) ?: 0;
        $restantes[] = [$mtime, $arq, $tam];
        $total += $tam;
    }

    if ($total > CACHE_TAMANHO_MAX) {
        sort($restantes);                       // mais antigos primeiro
        foreach ($restantes as [$mtime, $arq, $tam]) {
            if ($total <= CACHE_TAMANHO_MAX * 0.8) break;   // folga de 20%
            if (@unlink($arq)) { $total -= $tam; $apagados++; }
        }
    }
    return ['apagados' => $apagados, 'bytes' => $total];
}

/**
 * Busca várias URLs em paralelo. Retorna [chave => array decodificado | null].
 */
function tmdbGetMulti(array $urls, $ttl) {
    $resultado = [];
    $pendentes = [];
    $dir = recoCacheDir();

    foreach ($urls as $chave => $url) {
        $arquivo = $dir ? $dir . '/' . md5($url) . '.json' : null;
        if ($arquivo && is_file($arquivo) && (time() - filemtime($arquivo)) < $ttl) {
            $dados = json_decode(file_get_contents($arquivo), true);
            if (is_array($dados)) { $resultado[$chave] = $dados; continue; }
        }
        $pendentes[$chave] = $url;
    }

    if ($pendentes) {
        // Gancho para testes automatizados (sem acesso à internet)
        if (isset($GLOBALS['TMDB_MOCK']) && is_callable($GLOBALS['TMDB_MOCK'])) {
            $brutos = array_map($GLOBALS['TMDB_MOCK'], $pendentes);
        } elseif (function_exists('curl_multi_init')) {
            $brutos = tmdbCurlMulti($pendentes);
            // No XAMPP/Windows o cURL às vezes falha por falta de certificado SSL
            // configurado (curl.cainfo). Se TUDO falhou, tenta o método antigo.
            if (!array_filter($brutos)) {
                foreach ($pendentes as $chave => $url) $brutos[$chave] = @file_get_contents($url);
            }
        } else {
            $brutos = [];
            foreach ($pendentes as $chave => $url) $brutos[$chave] = @file_get_contents($url);
        }

        foreach ($pendentes as $chave => $url) {
            $corpo = $brutos[$chave] ?? false;
            $dados = $corpo ? json_decode($corpo, true) : null;
            if (!is_array($dados) || isset($dados['status_code']) && empty($dados['id']) && empty($dados['results'])) {
                $resultado[$chave] = null;
                continue;
            }
            $resultado[$chave] = $dados;
            if ($dir) @file_put_contents($dir . '/' . md5($url) . '.json', $corpo, LOCK_EX);
        }
    }
    return $resultado;
}

function tmdbCurlMulti(array $urls, $simultaneas = 20) {
    $saida = [];
    $fila  = $urls;
    $mh    = curl_multi_init();
    $ativos = [];

    $adicionar = function () use (&$fila, &$ativos, $mh) {
        if (!$fila) return;
        $chave = array_key_first($fila);
        $url   = $fila[$chave];
        unset($fila[$chave]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_ENCODING       => '',
        ]);
        curl_multi_add_handle($mh, $ch);
        $ativos[(int)$ch] = [$chave, $ch];
    };

    for ($i = 0; $i < $simultaneas; $i++) $adicionar();

    do {
        curl_multi_exec($mh, $rodando);
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            [$chave] = $ativos[(int)$ch];
            $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $saida[$chave] = ($codigo === 200) ? curl_multi_getcontent($ch) : false;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($ativos[(int)$ch]);
            $adicionar();
        }
        if ($rodando || $ativos) curl_multi_select($mh, 0.5);
    } while ($rodando || $ativos);

    curl_multi_close($mh);
    return $saida;
}

/** Uma URL só, com o mesmo cache em disco. */
function tmdbGetCache($url, $ttl) {
    return tmdbGetMulti(['u' => $url], $ttl)['u'] ?? null;
}

/**
 * Detalhes completos de filmes (com créditos), buscados em paralelo e em cache.
 * Usa a MESMA URL do recomendador/perfil, então um cache serve para todos.
 * Guarda também em memória durante a requisição: chamar precarregarFilmes()
 * antes de um loop faz cada detalhesFilme() seguinte ser instantâneo.
 */
function precarregarFilmes(array $ids, $apiKey) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $faltam = array_values(array_diff($ids, array_keys($GLOBALS['FILMES_MEMORIA'] ?? [])));
    if ($faltam) {
        $urls = [];
        foreach ($faltam as $id) {
            $urls[$id] = tmdbUrl("/movie/$id", ['append_to_response' => 'credits,keywords,recommendations,similar'], $apiKey);
        }
        foreach (tmdbGetMulti($urls, RECO_TTL_DETALHES) as $id => $dados) {
            $GLOBALS['FILMES_MEMORIA'][$id] = $dados;
        }
    }
    $saida = [];
    foreach ($ids as $id) $saida[$id] = $GLOBALS['FILMES_MEMORIA'][$id] ?? null;
    return $saida;
}

function detalhesFilme($id, $apiKey) {
    return precarregarFilmes([$id], $apiKey)[(int)$id] ?? null;
}

// ─────────────────────────────────────────────────────────────
//  Utilidades
// ─────────────────────────────────────────────────────────────

function recoAno($data) {
    return $data ? (int)substr($data, 0, 4) : 0;
}

function recoQualidade($media, $votos) {
    // Média bayesiana: filme com nota 9 e 12 votos não passa na frente de um 8.2 com 20 mil votos
    $m = 300; $C = 6.6;
    $bayes = ($votos * $media + $m * $C) / ($votos + $m);
    return max(0, min(1, ($bayes - 5.5) / 2.5));
}

function recoJaccard(array $a, array $b) {
    if (!$a && !$b) return 0;
    $inter = count(array_intersect($a, $b));
    $uniao = count(array_unique(array_merge($a, $b)));
    return $uniao ? $inter / $uniao : 0;
}

function recoTopChaves(array $mapa, $n) {
    arsort($mapa);
    return array_slice(array_keys($mapa), 0, $n, true);
}

// ─────────────────────────────────────────────────────────────
//  1–2. Sementes e perfil
// ─────────────────────────────────────────────────────────────

function recoSementes(array $lista, array $favoritos) {
    $lista     = array_values(array_unique(array_map('intval', $lista)));
    $favoritos = array_values(array_unique(array_map('intval', $favoritos)));
    $lista     = array_slice($lista, -RECO_MAX_SEMENTES);

    $pesos = [];
    $n = count($lista);
    foreach ($lista as $i => $id) {
        $idade = $n - 1 - $i;                       // 0 = adicionado por último
        $pesos[$id] = max(0.45, pow(0.94, $idade));  // decaimento suave
    }
    // Favoritos são um atalho do usuário, não um sinal mais forte que a lista:
    // - se o filme já está na lista, vale o peso da lista (sem bônus);
    // - se está só nos favoritos, entra com peso menor que qualquer item da lista.
    foreach ($favoritos as $id) {
        if (!isset($pesos[$id])) $pesos[$id] = 0.35;
    }
    return $pesos;
}

function recoMontarPerfil(array $sementes) {
    $p = [
        'generos' => [], 'kw' => [], 'kwSeeds' => [], 'kwNomes' => [],
        'diretores' => [], 'roteiristas' => [], 'elenco' => [], 'elencoSeeds' => [], 'diretoresSeeds' => [],
        'idiomas' => [], 'anos' => [], 'colecoes' => [], 'nomesGeneros' => [], 'nomesPessoas' => [],
        'pesoTotal' => 0,
    ];

    foreach ($sementes as $id => $s) {
        $w = $s['peso'];
        $d = $s['dados'];
        $p['pesoTotal'] += $w;

        foreach ($d['genres'] ?? [] as $g) {
            $p['generos'][$g['id']] = ($p['generos'][$g['id']] ?? 0) + $w;
            $p['nomesGeneros'][$g['id']] = $g['name'];
        }

        foreach ($d['keywords']['keywords'] ?? [] as $k) {
            if (in_array(strtolower($k['name']), RECO_KW_IGNORAR, true)) continue;
            $p['kw'][$k['id']] = ($p['kw'][$k['id']] ?? 0) + $w;
            $p['kwSeeds'][$k['id']][] = $id;
            $p['kwNomes'][$k['id']] = $k['name'];
        }

        foreach ($d['credits']['crew'] ?? [] as $c) {
            if ($c['job'] === 'Director') {
                $p['diretores'][$c['id']] = ($p['diretores'][$c['id']] ?? 0) + $w;
                $p['diretoresSeeds'][$c['id']][] = $id;
                $p['nomesPessoas'][$c['id']] = $c['name'];
            } elseif (in_array($c['job'], ['Screenplay', 'Writer', 'Novel', 'Story'], true)) {
                $p['roteiristas'][$c['id']] = ($p['roteiristas'][$c['id']] ?? 0) + $w;
            }
        }

        foreach ($d['credits']['cast'] ?? [] as $a) {
            if (($a['order'] ?? 99) > 5) continue;   // só elenco principal
            $p['elenco'][$a['id']] = ($p['elenco'][$a['id']] ?? 0) + $w;
            $p['elencoSeeds'][$a['id']][] = $id;
            $p['nomesPessoas'][$a['id']] = $a['name'];
        }

        $lang = $d['original_language'] ?? '';
        if ($lang) $p['idiomas'][$lang] = ($p['idiomas'][$lang] ?? 0) + $w;

        $ano = recoAno($d['release_date'] ?? '');
        if ($ano) $p['anos'][] = [$ano, $w];

        if (!empty($d['belongs_to_collection']['id'])) {
            $p['colecoes'][$d['belongs_to_collection']['id']] = true;
        }
    }

    // Palavra-chave que aparece em só uma semente é um sinal mais fraco
    foreach ($p['kw'] as $k => $v) {
        if (count(array_unique($p['kwSeeds'][$k])) < 2) $p['kw'][$k] = $v * 0.5;
    }

    // Distribuições normalizadas
    $soma = array_sum($p['generos']) ?: 1;
    $p['generosDist'] = array_map(fn($v) => $v / $soma, $p['generos']);
    $p['generosNorma'] = sqrt(array_sum(array_map(fn($v) => $v * $v, $p['generosDist']))) ?: 1;
    $p['kwNorma'] = sqrt(array_sum(array_map(fn($v) => $v * $v, $p['kw']))) ?: 1;

    $somaIdiomas = array_sum($p['idiomas']) ?: 1;
    $p['idiomasDist'] = array_map(fn($v) => $v / $somaIdiomas, $p['idiomas']);

    // Época: média e desvio ponderados
    $sw = 0; $sa = 0;
    foreach ($p['anos'] as [$a, $w]) { $sa += $a * $w; $sw += $w; }
    $media = $sw ? $sa / $sw : 2010;
    $var = 0;
    foreach ($p['anos'] as [$a, $w]) $var += $w * ($a - $media) ** 2;
    $p['anoMedia']  = $media;
    $p['anoDesvio'] = $sw ? sqrt($var / $sw) : 15;

    // Peso máximo de uma semente (para normalizar pessoas)
    $p['pesoMax'] = max(array_column($sementes, 'peso') ?: [1]);

    return $p;
}

// ─────────────────────────────────────────────────────────────
//  3. Geração de candidatos
// ─────────────────────────────────────────────────────────────

function recoAdicionarCandidato(array &$cands, array $f, $fonte, $valor, $semente = null) {
    $id = $f['id'] ?? 0;
    if (!$id) return;
    if (!isset($cands[$id])) {
        $cands[$id] = ['base' => $f, 'colab' => 0, 'similar' => 0, 'kwFonte' => 0, 'pessoaFonte' => 0,
                       'generoFonte' => 0, 'colabPorSemente' => []];
    }
    $cands[$id][$fonte] += $valor;
    if ($semente !== null && $fonte === 'colab') {
        $cands[$id]['colabPorSemente'][$semente] = ($cands[$id]['colabPorSemente'][$semente] ?? 0) + $valor;
    }
}

function recoGerarCandidatos(array $sementes, array $perfil, $apiKey) {
    $cands = [];

    // (a) Recomendações e similares do próprio TMDB (já vieram no append_to_response)
    foreach ($sementes as $id => $s) {
        foreach ($s['dados']['recommendations']['results'] ?? [] as $pos => $f) {
            recoAdicionarCandidato($cands, $f, 'colab', $s['peso'] * (1 - $pos / 25), $id);
        }
        foreach ($s['dados']['similar']['results'] ?? [] as $pos => $f) {
            recoAdicionarCandidato($cands, $f, 'similar', $s['peso'] * (1 - $pos / 25));
        }
    }

    // (b) Consultas /discover guiadas pelo perfil
    $consultas = [];
    $nSementes = count($sementes);
    $semGeneros = implode('|', array_filter(RECO_GENEROS_SENSIVEIS, fn($g) => ($perfil['generosDist'][$g] ?? 0) < 0.05));
    $idiomaDominante = recoTopChaves($perfil['idiomasDist'], 1)[0] ?? null;
    $filtroIdioma = ($idiomaDominante && $perfil['idiomasDist'][$idiomaDominante] >= 0.85)
        ? ['with_original_language' => $idiomaDominante] : [];

    // Palavras-chave mais fortes do perfil (temas recorrentes)
    $kwFortes = recoTopChaves($perfil['kw'], 6);
    if ($kwFortes) {
        $consultas[] = ['kwFonte', 1.0, tmdbUrl('/discover/movie', [
            'with_keywords' => implode('|', $kwFortes), 'sort_by' => 'vote_count.desc',
            'vote_count.gte' => 150, 'without_genres' => $semGeneros, 'include_adult' => 'false',
        ], $apiKey)];
        foreach (array_slice($kwFortes, 0, 3) as $k) {
            $consultas[] = ['kwFonte', 0.8, tmdbUrl('/discover/movie', [
                'with_keywords' => $k, 'sort_by' => 'vote_count.desc', 'vote_count.gte' => 100,
                'without_genres' => $semGeneros, 'include_adult' => 'false',
            ], $apiKey)];
        }
    }

    // Combinação dos dois gêneros principais (E lógico, bem mais preciso que um gênero só)
    $topGeneros = recoTopChaves($perfil['generos'], 3);
    if (count($topGeneros) >= 2) {
        foreach ([[0, 1], [0, 2]] as [$a, $b]) {
            if (!isset($topGeneros[$b])) continue;
            $consultas[] = ['generoFonte', 1.0, tmdbUrl('/discover/movie', array_merge([
                'with_genres' => $topGeneros[$a] . ',' . $topGeneros[$b], 'sort_by' => 'vote_average.desc',
                'vote_count.gte' => 800, 'without_genres' => $semGeneros, 'include_adult' => 'false',
            ], $filtroIdioma), $apiKey)];
        }
    } elseif ($topGeneros) {
        $consultas[] = ['generoFonte', 1.0, tmdbUrl('/discover/movie', array_merge([
            'with_genres' => $topGeneros[0], 'sort_by' => 'vote_average.desc', 'vote_count.gte' => 1000,
            'without_genres' => $semGeneros, 'include_adult' => 'false',
        ], $filtroIdioma), $apiKey)];
    }

    // Pessoas recorrentes: contam SEMENTES DISTINTAS (antes o peso de um único filme já bastava)
    $minRepeticoes = $nSementes <= 2 ? 1 : 2;
    $diretores = array_filter($perfil['diretoresSeeds'], fn($s) => count(array_unique($s)) >= $minRepeticoes);
    uksort($diretores, fn($a, $b) => $perfil['diretores'][$b] <=> $perfil['diretores'][$a]);
    foreach (array_slice(array_keys($diretores), 0, 3) as $pid) {
        $consultas[] = ['pessoaFonte', 1.0, tmdbUrl('/discover/movie', [
            'with_crew' => $pid, 'sort_by' => 'vote_count.desc', 'vote_count.gte' => 50,
        ], $apiKey)];
    }
    $atores = array_filter($perfil['elencoSeeds'], fn($s) => count(array_unique($s)) >= 2);
    uksort($atores, fn($a, $b) => $perfil['elenco'][$b] <=> $perfil['elenco'][$a]);
    foreach (array_slice(array_keys($atores), 0, 3) as $pid) {
        $consultas[] = ['pessoaFonte', 0.7, tmdbUrl('/discover/movie', [
            'with_cast' => $pid, 'sort_by' => 'vote_count.desc', 'vote_count.gte' => 200,
            'without_genres' => $semGeneros,
        ], $apiKey)];
    }

    $urls = [];
    foreach ($consultas as $i => $c) $urls[$i] = $c[2];
    $respostas = tmdbGetMulti($urls, RECO_TTL_LISTAS);

    foreach ($consultas as $i => [$fonte, $peso]) {
        foreach ($respostas[$i]['results'] ?? [] as $pos => $f) {
            recoAdicionarCandidato($cands, $f, $fonte, $peso * (1 - $pos / 30));
        }
    }

    return $cands;
}

// ─────────────────────────────────────────────────────────────
//  5. Pontuação
// ─────────────────────────────────────────────────────────────

function recoSimGeneros(array $ids, array $perfil) {
    if (!$ids) return 0;
    $dot = 0;
    foreach ($ids as $g) $dot += $perfil['generosDist'][$g] ?? 0;
    return $dot / ($perfil['generosNorma'] * sqrt(count($ids)));
}

function recoPenalidadeGeneros(array $ids, array $perfil) {
    $pen = 0;
    foreach ($ids as $g) {
        if (in_array($g, RECO_GENEROS_SENSIVEIS, true) && ($perfil['generosDist'][$g] ?? 0) < 0.05) {
            $pen += 0.12;
        }
    }
    return $pen;
}

function recoFiltrar(array $f, array $perfil, array $excluir) {
    $id = $f['id'];
    if (isset($excluir[$id])) return false;
    if (empty($f['poster_path'])) return false;
    if (!empty($f['adult'])) return false;
    if (($f['vote_count'] ?? 0) < 40) return false;
    $data = $f['release_date'] ?? '';
    if (!$data || $data > date('Y-m-d')) return false;   // ainda não lançado
    $generos = $f['genre_ids'] ?? array_column($f['genres'] ?? [], 'id');
    // Documentário e filme de TV só se o usuário já mostrou interesse
    foreach ([99, 10770] as $g) {
        if (in_array($g, $generos) && ($perfil['generosDist'][$g] ?? 0) < 0.05) return false;
    }
    return true;
}

/** Pontuação rápida, só com os dados da listagem, para decidir quem enriquecer. */
function recoPrePontuar(array &$cands, array $perfil) {
    $maxColab = max(array_column($cands, 'colab') ?: [0]) ?: 1;
    $maxSim   = max(array_column($cands, 'similar') ?: [0]) ?: 1;
    foreach ($cands as $id => &$c) {
        $f = $c['base'];
        $g = $f['genre_ids'] ?? [];
        $c['pre'] = 0.35 * sqrt($c['colab'] / $maxColab)
                  + 0.10 * ($c['similar'] / $maxSim)
                  + 0.12 * min(1, $c['kwFonte'])
                  + 0.10 * min(1, $c['pessoaFonte'])
                  + 0.05 * min(1, $c['generoFonte'])
                  + 0.20 * recoSimGeneros($g, $perfil)
                  + 0.10 * recoQualidade($f['vote_average'] ?? 0, $f['vote_count'] ?? 0)
                  + 0.05 * ($perfil['idiomasDist'][$f['original_language'] ?? ''] ?? 0)
                  - recoPenalidadeGeneros($g, $perfil);
    }
    unset($c);
}

function recoPontuarFinal(array &$cands, array $perfil) {
    $maxColab = max(array_column($cands, 'colab') ?: [0]) ?: 1;
    $maxSim   = max(array_column($cands, 'similar') ?: [0]) ?: 1;
    $sigmaAno = max($perfil['anoDesvio'], 7) + 3;

    // Similaridade de temas (cosseno entre palavras-chave)
    $kwBrutos = [];
    foreach ($cands as $id => $c) {
        $kws = $c['kwIds'];
        if (!$kws) { $kwBrutos[$id] = 0; continue; }
        $dot = 0;
        foreach ($kws as $k) $dot += $perfil['kw'][$k] ?? 0;
        $kwBrutos[$id] = $dot / ($perfil['kwNorma'] * sqrt(count($kws)));
    }
    $maxKw = max($kwBrutos ?: [0]) ?: 1;

    foreach ($cands as $id => &$c) {
        $d = $c['dados'];
        $generos = array_column($d['genres'] ?? [], 'id');

        // Pessoas em comum
        $pm = $perfil['pesoMax'];
        $dirScore = 0; $castScore = 0; $rotScore = 0;
        foreach ($c['diretores'] as $pid) $dirScore = max($dirScore, ($perfil['diretores'][$pid] ?? 0) / $pm);
        foreach ($c['roteiristas'] as $pid) $rotScore = max($rotScore, ($perfil['roteiristas'][$pid] ?? 0) / $pm);
        foreach ($c['elenco'] as $pid) $castScore += ($perfil['elenco'][$pid] ?? 0) / $pm;
        $pessoas = min(1, 0.6 * min(1, $dirScore) + 0.25 * min(1, $rotScore) + 0.3 * min(1, $castScore));

        $ano  = recoAno($d['release_date'] ?? '');
        $epoca = $ano ? exp(-(($ano - $perfil['anoMedia']) ** 2) / (2 * $sigmaAno ** 2)) : 0.5;
        $lang = $d['original_language'] ?? '';
        $idioma = $perfil['idiomasDist'][$lang] ?? 0;

        $comp = [
            'colab'    => sqrt($c['colab'] / $maxColab) * 0.85 + ($c['similar'] / $maxSim) * 0.15,
            'generos'  => recoSimGeneros($generos, $perfil),
            'temas'    => $kwBrutos[$id] / $maxKw,
            'pessoas'  => $pessoas,
            'qualidade'=> recoQualidade($d['vote_average'] ?? 0, $d['vote_count'] ?? 0),
            'epoca'    => $epoca,
            'idioma'   => $idioma,
        ];

        $score = 0.27 * $comp['colab']
               + 0.20 * $comp['generos']
               + 0.20 * $comp['temas']
               + 0.10 * $comp['pessoas']
               + 0.11 * $comp['qualidade']
               + 0.06 * $comp['epoca']
               + 0.06 * $comp['idioma'];

        $score -= recoPenalidadeGeneros($generos, $perfil);
        if ($idioma == 0 && $lang !== 'en') $score -= 0.04;          // idioma que o usuário nunca vê
        if (($d['vote_count'] ?? 0) < 150)  $score -= 0.04;          // muito obscuro
        if (($d['runtime'] ?? 90) > 0 && ($d['runtime'] ?? 90) < 60) $score -= 0.10; // curta-metragem

        $c['comp']  = $comp;
        $c['score'] = $score;
        $c['dirScore'] = $dirScore;
    }
    unset($c);
}

// ─────────────────────────────────────────────────────────────
//  6. Diversidade (Maximal Marginal Relevance)
// ─────────────────────────────────────────────────────────────

function recoDiversificar(array $cands, $total) {
    uasort($cands, fn($a, $b) => $b['score'] <=> $a['score']);
    // Só entra no sorteio de diversidade quem tem relevância razoável
    $topo = reset($cands)['score'] ?? 0;
    $pool = array_filter(array_slice($cands, 0, $total * 3, true), fn($c) => $c['score'] >= $topo * 0.42);
    $escolhidos = [];
    $porColecao = [];
    $porDiretor = [];
    $lambda = 0.88;

    while (count($escolhidos) < $total && $pool) {
        $melhorId = null; $melhorValor = -INF;
        foreach ($pool as $id => $c) {
            $col = $c['colecao'];
            if ($col && ($porColecao[$col] ?? 0) >= 1) continue;
            $dirBloqueado = false;
            foreach ($c['diretores'] as $d) if (($porDiretor[$d] ?? 0) >= 3) $dirBloqueado = true;
            if ($dirBloqueado) continue;

            $maxSim = 0;
            foreach ($escolhidos as $e) {
                $sim = 0.45 * recoJaccard($c['generoIds'], $e['generoIds'])
                     + 0.55 * recoJaccard($c['kwIds'], $e['kwIds']);
                if (array_intersect($c['diretores'], $e['diretores'])) $sim += 0.25;
                $maxSim = max($maxSim, $sim);
            }
            $valor = $lambda * $c['score'] - (1 - $lambda) * $maxSim;
            if ($valor > $melhorValor) { $melhorValor = $valor; $melhorId = $id; }
        }
        if ($melhorId === null) break;
        $c = $pool[$melhorId];
        unset($pool[$melhorId]);
        $escolhidos[$melhorId] = $c;
        if ($c['colecao']) $porColecao[$c['colecao']] = ($porColecao[$c['colecao']] ?? 0) + 1;
        foreach ($c['diretores'] as $d) $porDiretor[$d] = ($porDiretor[$d] ?? 0) + 1;
    }
    // Completa até um mínimo de 12 com os melhores restantes (listas pequenas
    // podem esbarrar nos limites de diretor/relevância e ficar com poucos itens)
    $minimo = min(12, $total);
    if (count($escolhidos) < $minimo) {
        foreach ($cands as $id => $c) {
            if (count($escolhidos) >= $minimo) break;
            if (isset($escolhidos[$id])) continue;
            if ($c['colecao'] && ($porColecao[$c['colecao']] ?? 0) >= 1) continue;
            $escolhidos[$id] = $c;
            if ($c['colecao']) $porColecao[$c['colecao']] = 1;
        }
    }

    // MMR decide QUEM entra; a exibição segue a ordem de relevância
    uasort($escolhidos, fn($a, $b) => $b['score'] <=> $a['score']);
    return $escolhidos;
}

// ─────────────────────────────────────────────────────────────
//  7. Explicação
// ─────────────────────────────────────────────────────────────

function recoExplicar(array $c, array $perfil, array $sementes) {
    $titulo = fn($id) => $sementes[$id]['dados']['title'] ?? '';

    // Mesmo diretor
    foreach ($c['diretores'] as $pid) {
        if (!empty($perfil['diretoresSeeds'][$pid])) {
            $nome = $perfil['nomesPessoas'][$pid] ?? '';
            $seeds = array_unique($perfil['diretoresSeeds'][$pid]);
            return count($seeds) >= 2
                ? "De $nome, diretor que você acompanha"
                : "Do mesmo diretor de " . $titulo(end($seeds));
        }
    }

    // Colaborativo: semente que mais "puxou" esse filme
    if ($c['comp']['colab'] >= 0.25 && $c['colabPorSemente']) {
        arsort($c['colabPorSemente']);
        $seeds = array_keys($c['colabPorSemente']);
        if (count($seeds) >= 2) {
            return "Porque você curtiu " . $titulo($seeds[0]) . " e " . $titulo($seeds[1]);
        }
        return "Porque você curtiu " . $titulo($seeds[0]);
    }

    // Ator recorrente
    foreach ($c['elenco'] as $pid) {
        if (!empty($perfil['elencoSeeds'][$pid]) && count(array_unique($perfil['elencoSeeds'][$pid])) >= 2) {
            return "Com " . ($perfil['nomesPessoas'][$pid] ?? '') . ", presente em vários da sua lista";
        }
    }

    // Semente com mais temas em comum
    $melhor = null; $melhorQtd = 0;
    foreach ($sementes as $sid => $s) {
        $kwS = array_column($s['dados']['keywords']['keywords'] ?? [], 'id');
        $qtd = count(array_intersect($kwS, $c['kwIds']));
        if ($qtd > $melhorQtd) { $melhorQtd = $qtd; $melhor = $sid; }
    }
    if ($melhor && $melhorQtd >= 2) return "Temas parecidos com " . $titulo($melhor);

    // Gêneros
    $nomes = [];
    foreach ($c['dados']['genres'] ?? [] as $g) {
        if (($perfil['generosDist'][$g['id']] ?? 0) >= 0.1) $nomes[] = $g['name'];
    }
    if ($nomes) return "Combina com seu gosto por " . implode(' e ', array_slice($nomes, 0, 2));

    return "Bem avaliado e alinhado ao seu perfil";
}

// ─────────────────────────────────────────────────────────────
//  Função principal
// ─────────────────────────────────────────────────────────────

/**
 * Calcula as recomendações e devolve dados estruturados (sem HTML).
 * @return array ['itens' => [...], 'perfil' => [...]] ou ['erro' => '...']
 */
function calcularRecomendacoes(array $lista, $apiKey, array $favoritos = [], $total = RECO_TOTAL) {
    $pesos = recoSementes($lista, $favoritos);
    if (!$pesos) return ['erro' => 'vazio'];

    // 1. Detalhes completos das sementes em UMA requisição cada (em paralelo)
    $urls = [];
    foreach ($pesos as $id => $w) {
        $urls[$id] = tmdbUrl("/movie/$id", ['append_to_response' => 'credits,keywords,recommendations,similar'], $apiKey);
    }
    $dados = tmdbGetMulti($urls, RECO_TTL_DETALHES);

    $sementes = [];
    foreach ($pesos as $id => $w) {
        if (!empty($dados[$id]['id'])) $sementes[$id] = ['peso' => $w, 'dados' => $dados[$id]];
    }
    if (!$sementes) return ['erro' => 'api'];

    // 2. Perfil
    $perfil = recoMontarPerfil($sementes);

    // 3. Candidatos
    $cands = recoGerarCandidatos($sementes, $perfil, $apiKey);
    $excluir = array_fill_keys(array_map('intval', array_merge($lista, $favoritos)), true);
    $cands = array_filter($cands, fn($c) => recoFiltrar($c['base'], $perfil, $excluir));
    if (!$cands) return ['erro' => 'nada'];

    // 4. Pré-seleção + enriquecimento
    recoPrePontuar($cands, $perfil);
    uasort($cands, fn($a, $b) => $b['pre'] <=> $a['pre']);
    $cands = array_slice($cands, 0, RECO_MAX_ENRIQUECER, true);

    $urls = [];
    foreach ($cands as $id => $c) {
        $urls[$id] = tmdbUrl("/movie/$id", ['append_to_response' => 'credits,keywords'], $apiKey);
    }
    $det = tmdbGetMulti($urls, RECO_TTL_DETALHES);

    foreach ($cands as $id => &$c) {
        $d = $det[$id] ?? null;
        if (!$d || empty($d['id'])) {
            // Sem detalhes: usa o que veio na listagem
            $d = $c['base'];
            $d['genres'] = array_map(fn($g) => ['id' => $g, 'name' => $perfil['nomesGeneros'][$g] ?? ''], $d['genre_ids'] ?? []);
        }
        $c['dados'] = $d;
        $c['generoIds'] = array_column($d['genres'] ?? [], 'id');
        $c['kwIds'] = array_values(array_filter(
            array_map(fn($k) => in_array(strtolower($k['name']), RECO_KW_IGNORAR, true) ? null : $k['id'],
                      $d['keywords']['keywords'] ?? [])
        ));
        $c['diretores'] = []; $c['roteiristas'] = []; $c['elenco'] = [];
        foreach ($d['credits']['crew'] ?? [] as $m) {
            if ($m['job'] === 'Director') $c['diretores'][] = $m['id'];
            elseif (in_array($m['job'], ['Screenplay', 'Writer', 'Novel', 'Story'], true)) $c['roteiristas'][] = $m['id'];
        }
        foreach ($d['credits']['cast'] ?? [] as $a) {
            if (($a['order'] ?? 99) <= 7) $c['elenco'][] = $a['id'];
        }
        $c['colecao'] = $d['belongs_to_collection']['id'] ?? null;
    }
    unset($c);

    // Revalida com dados completos (ex.: duração, gêneros reais)
    $cands = array_filter($cands, fn($c) => recoFiltrar($c['dados'], $perfil, $excluir));
    if (!$cands) return ['erro' => 'nada'];

    // 5–6. Pontuação final + diversidade
    recoPontuarFinal($cands, $perfil);
    $escolhidos = recoDiversificar($cands, $total);

    // 7. Saída
    $topScore = max(array_column($escolhidos, 'score') ?: [1]) ?: 1;
    $itens = [];
    foreach ($escolhidos as $id => $c) {
        $d = $c['dados'];
        $itens[] = [
            'id'      => $id,
            'titulo'  => $d['title'] ?? '',
            'poster'  => $d['poster_path'] ?? '',
            'ano'     => recoAno($d['release_date'] ?? ''),
            'nota'    => round($d['vote_average'] ?? 0, 1),
            'match'   => max(50, min(99, (int)round(97 * $c['score'] / $topScore))),
            'motivo'  => recoExplicar($c, $perfil, $sementes),
            'score'   => round($c['score'], 4),
            'comp'    => array_map(fn($v) => round($v, 3), $c['comp']),
        ];
    }

    $topGen = array_map(fn($g) => $perfil['nomesGeneros'][$g] ?? '', recoTopChaves($perfil['generos'], 3));
    return [
        'itens'  => $itens,
        'perfil' => ['generos' => array_values(array_filter($topGen)), 'sementes' => count($sementes)],
    ];
}

/**
 * Mantém a assinatura antiga usada em recomendacao.php e devolve o HTML pronto.
 */
function recomendarFilme($lista, $apiKey, $favoritos = []) {
    $lista     = array_values(array_unique(array_map('intval', $lista)));
    $favoritos = array_values(array_unique(array_map('intval', $favoritos)));
    $forcar = isset($_GET['atualizar']);
    $cache  = $_SESSION['reco_cache'] ?? null;
    $r = null;

    // Ignora cache em formato antigo/incompleto (ex.: salvo por versão anterior)
    $cacheValido = is_array($cache) && isset($cache['quando'], $cache['resultado'])
        && is_array($cache['lista'] ?? null) && is_array($cache['favoritos'] ?? null);

    if (!$forcar && $cacheValido && time() - $cache['quando'] < 6 * 3600) {
        // Reaproveita o resultado se a lista mudou APENAS por filmes que vieram
        // das próprias recomendações (ex.: usuário abriu um, adicionou e voltou).
        // Assim a tela não é recalculada e as demais sugestões continuam lá.
        $idsReco    = array_column($cache['resultado']['itens'] ?? [], 'id');
        $semReco    = fn($ids) => array_values(array_diff($ids, $idsReco));
        $mesmaLista = $semReco($lista) == $semReco($cache['lista']);
        $mesmosFavs = $semReco($favoritos) == $semReco($cache['favoritos']);
        if ($mesmaLista && $mesmosFavs) $r = $cache['resultado'];
    }

    if ($r === null) {
        $r = calcularRecomendacoes($lista, $apiKey, $favoritos);
        if (empty($r['erro'])) {
            $_SESSION['reco_cache'] = ['lista' => $lista, 'favoritos' => $favoritos,
                                       'quando' => time(), 'resultado' => $r];
        }
    }

    if (!empty($r['erro'])) {
        $msg = $r['erro'] === 'api'
            ? 'Não foi possível falar com o TMDB agora. Tente novamente em instantes.'
            : 'Nenhuma recomendação encontrada. Adicione mais alguns filmes à sua lista.';
        return "<p class='reco-vazio'>$msg</p>";
    }

    return renderizarRecomendacoes($r, $lista);
}

function renderizarRecomendacoes(array $r, array $lista = []) {
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);

    $html = "<div class='reco-perfil'>";
    $html .= "<span>Baseado em <strong>{$r['perfil']['sementes']}</strong> filme" . ($r['perfil']['sementes'] > 1 ? 's' : '') . "</span>";
    if ($r['perfil']['generos']) {
        $html .= "<span class='reco-perfil-sep'>·</span><span>Seu perfil:</span>";
        foreach ($r['perfil']['generos'] as $g) $html .= "<span class='reco-chip'>" . $h($g) . "</span>";
    }
    $html .= "<a href='recomendacao.php?atualizar=1' class='reco-atualizar' title='Recalcular'>↻ Atualizar</a>";
    $html .= "</div>";

    $html .= "<div class='reco-grid' id='reco-grid'>";
    foreach ($r['itens'] as $i => $f) {
        $id = (int)$f['id'];
        $oculto = $i >= 12 ? ' reco-oculto' : '';
        $poster = 'https://image.tmdb.org/t/p/w342' . $h($f['poster']);
        $html .= "<div class='reco-card$oculto' id='card-$id'>";
        $html .= "  <div class='reco-poster'>";
        $html .= "    <img src='$poster' alt='" . $h($f['titulo']) . "' loading='lazy' onclick=\"window.location.href='filme.php?id=$id'\">";
        $html .= "    <span class='reco-match'>{$f['match']}%</span>";
        $naLista = in_array($id, $lista);
        $icone   = $naLista ? 'Imagens/del.png' : 'Imagens/add.png';
        $dica    = $naLista ? 'Remover da lista' : 'Adicionar à lista';
        $html .= "    <a href='javascript:void(0)' class='reco-add' title='$dica' onclick=\"alterarListaReco($id, this)\">";
        $html .= "      <img src='$icone' id='icone-$id' alt='$dica'>";
        $html .= "    </a>";
        $html .= "  </div>";
        $html .= "  <p class='reco-titulo' title='" . $h($f['titulo']) . "'>" . $h($f['titulo']) . "</p>";
        $html .= "  <p class='reco-meta'>" . ($f['ano'] ?: '—') . " · ⭐ " . number_format($f['nota'], 1) . "</p>";
        $html .= "  <p class='reco-motivo'>" . $h($f['motivo']) . "</p>";
        $html .= "</div>";
    }
    $html .= "</div>";

    if (count($r['itens']) > 12) {
        $html .= "<div style='text-align:center'><a href='javascript:void(0)' class='btn-descubra' id='reco-mais' onclick='mostrarMaisReco()'>Mostrar mais</a></div>";
    }
    return $html;
}