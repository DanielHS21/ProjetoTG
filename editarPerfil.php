<?php
include 'header.php';

if (empty($_SESSION['usuario_id'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

require_once 'db.php';
$usuario = buscarUsuario(getDB(), $_SESSION['usuario_id']);
if (!$usuario) { echo "<script>window.location.href='auth.php?acao=logout';</script>"; exit; }

if (empty($_SESSION['csrf_perfil'])) $_SESSION['csrf_perfil'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_perfil'];

$h   = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$aba = ($_GET['aba'] ?? '') === 'senha' ? 'senha' : 'dados';

$mensagensErro = [
    'nome'         => 'O nome deve ter entre 2 e 100 caracteres.',
    'email'        => 'Informe um e-mail válido.',
    'email_existe' => 'Esse e-mail já está em uso por outra conta.',
    'senha_email'  => 'Para trocar o e-mail, confirme sua senha atual.',
    'bio'          => 'A bio pode ter no máximo ' . BIO_MAX . ' caracteres.',
    'foto'         => $_GET['msg'] ?? 'Não foi possível salvar a foto.',
    'senha_atual'  => 'Senha atual incorreta.',
    'senha_curta'  => 'A nova senha deve ter pelo menos 6 caracteres.',
    'senha_diff'   => 'A confirmação não confere com a nova senha.',
    'sessao'       => 'Sua sessão expirou. Tente novamente.',
];
$mensagensOk = ['dados' => 'Perfil atualizado com sucesso!', 'senha' => 'Senha alterada com sucesso!'];
$erro = $mensagensErro[$_GET['erro'] ?? ''] ?? null;
$ok   = $mensagensOk[$_GET['ok'] ?? ''] ?? null;
?>

<style>
.ep { width: min(860px, 92%); margin: 1.5% auto 4%; }
.ep-titulo { font-family: "Gabarito", sans-serif; color: white; font-size: 30px; margin: 0 0 0.2em; }
.ep-sub { font-family: "Josefin Sans", sans-serif; color: #777; margin: 0 0 1.5em; font-size: 15px; }

.ep-abas { display: flex; gap: 0; border-bottom: 2px solid #1f1f1f; margin-bottom: 1.6em; }
.ep-aba {
    font-family: "Josefin Sans", sans-serif; font-size: 14px; letter-spacing: 0.06em; text-transform: uppercase;
    color: #666; background: none; border: none; padding: 0.7em 1.4em; cursor: pointer;
    border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color 0.2s;
}
.ep-aba:hover { color: #bbb; }
.ep-aba.ativa { color: white; border-bottom-color: red; }
.ep-painel { display: none; }
.ep-painel.ativo { display: block; }

.ep-alerta {
    font-family: "Josefin Sans", sans-serif; font-size: 14px;
    padding: 0.8em 1.1em; border-radius: 10px; margin-bottom: 1.4em;
}
.ep-alerta.erro { background: #1f0505; border: 1px solid #5a1010; color: #ff8a8a; }
.ep-alerta.ok   { background: #051f0c; border: 1px solid #10501f; color: #7ee29a; }

.ep-cartao {
    background: #0d0d0d; border: 1px solid #1f1f1f; border-radius: 16px;
    padding: 1.8em 2em;
}
.ep-grid { display: grid; grid-template-columns: 200px 1fr; gap: 2.2em; }

/* Foto */
.ep-foto { display: flex; flex-direction: column; align-items: center; gap: 0.9em; }
.ep-foto .avatar { width: 150px; height: 150px; font-size: 52px; }
.ep-foto-botoes { display: flex; flex-direction: column; gap: 0.5em; width: 100%; }
.ep-foto-dica { font-family: "Josefin Sans", sans-serif; font-size: 12px; color: #555; text-align: center; margin: 0; }


/* Campos */
.ep-campo { margin-bottom: 1.15em; }
.ep-campo label {
    display: block; font-family: "Josefin Sans", sans-serif; font-size: 12px; color: #888;
    text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.45em;
}
.ep-campo input, .ep-campo textarea {
    width: 100%; box-sizing: border-box;
    background: #141414; border: 1px solid #2a2a2a; border-radius: 9px;
    color: white; font-family: "Josefin Sans", sans-serif; font-size: 15px;
    padding: 0.75em 0.9em; transition: border-color 0.2s; resize: vertical;
}
.ep-campo input:focus, .ep-campo textarea:focus { outline: none; border-color: red; }
.ep-campo-rodape { display: flex; justify-content: space-between; font-family: "Josefin Sans", sans-serif; font-size: 12px; color: #555; margin-top: 0.35em; }
.ep-contador.limite { color: #ff6b6b; }
.ep-campo-senha-email { display: none; }
.ep-campo-senha-email.visivel { display: block; }

.ep-rodape { display: flex; justify-content: flex-end; gap: 0.7em; margin-top: 0.6em; }
.ep-btn {
    font-family: "Josefin Sans", sans-serif; font-size: 14px; border-radius: 8px;
    padding: 0.65em 1.4em; cursor: pointer; transition: 0.2s; text-decoration: none;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4em;
}
.ep-btn-primario { background: red; color: white; border: 1px solid red; }
.ep-btn-primario:hover { background: #cc0000; }
.ep-btn-sec { background: none; color: #bbb; border: 1px solid #333; }
.ep-btn-sec:hover { color: white; border-color: #666; }
.ep-btn-perigo { background: none; color: #888; border: 1px solid #2a2a2a; }
.ep-btn-perigo:hover { color: #ff6b6b; border-color: #5a1010; }
.ep-btn:disabled { opacity: 0.5; cursor: default; }

/* Modal de recorte */
.ep-modal {
    display: none; position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.85); align-items: center; justify-content: center;
}
.ep-modal.visivel { display: flex; }
.ep-modal-caixa {
    background: #111; border: 1px solid #2a2a2a; border-radius: 16px;
    padding: 1.5em; width: min(380px, 92vw); text-align: center;
}
.ep-modal-caixa h3 { font-family: "Gabarito", sans-serif; color: white; margin: 0 0 0.3em; font-size: 20px; }
.ep-modal-caixa p { font-family: "Josefin Sans", sans-serif; color: #777; font-size: 13px; margin: 0 0 1em; }
.ep-recorte {
    position: relative; width: 300px; height: 300px; max-width: 100%; margin: 0 auto;
    border-radius: 12px; overflow: hidden; background: #000; cursor: grab; touch-action: none;
}
.ep-recorte:active { cursor: grabbing; }
.ep-recorte canvas { display: block; width: 100%; height: 100%; }
.ep-recorte::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(circle at center, transparent 0, transparent 148px, rgba(0,0,0,0.6) 149px);
}
.ep-zoom { display: flex; align-items: center; gap: 0.7em; margin: 1.1em 0; color: #777; font-size: 18px; }
.ep-zoom input { flex: 1; accent-color: red; }

@media (max-width: 700px) {
    .ep-grid { grid-template-columns: 1fr; }
    .ep-cartao { padding: 1.4em 1.2em; }
    .ep-rodape { flex-direction: column-reverse; }
}
</style>

<a href="perfil.php" class="btn-voltar" title="Voltar ao perfil">
    <img src="Imagens/voltar.png" class="icone-voltar" alt="Voltar">
</a>

<div class="ep">
    <h1 class="ep-titulo">Editar perfil</h1>
    <p class="ep-sub">Atualize suas informações e sua foto.</p>

    <?php if ($erro): ?><div class="ep-alerta erro"><?php echo $h($erro); ?></div><?php endif; ?>
    <?php if ($ok):   ?><div class="ep-alerta ok">✓ <?php echo $h($ok); ?> <a href="perfil.php" style="color:inherit">Ver perfil →</a></div><?php endif; ?>

    <div class="ep-abas">
        <button type="button" class="ep-aba <?php echo $aba === 'dados' ? 'ativa' : ''; ?>" data-aba="dados">Perfil</button>
        <button type="button" class="ep-aba <?php echo $aba === 'senha' ? 'ativa' : ''; ?>" data-aba="senha">Senha</button>
    </div>

    <!-- ── Aba: dados ── -->
    <div class="ep-painel <?php echo $aba === 'dados' ? 'ativo' : ''; ?>" id="painel-dados">
        <form class="ep-cartao" method="POST" action="perfilAcoes.php" id="form-dados">
            <input type="hidden" name="acao" value="dados">
            <input type="hidden" name="csrf" value="<?php echo $h($csrf); ?>">
            <input type="hidden" name="foto_data" id="foto_data">
            <input type="hidden" name="remover_foto" id="remover_foto">

            <div class="ep-grid">
                <div class="ep-foto">
                    <div id="avatar-preview"><?php echo avatarHtml($usuario['nome'], $usuario['foto']); ?></div>
                    <div class="ep-foto-botoes">
                        <button type="button" class="ep-btn ep-btn-sec" onclick="document.getElementById('arquivo-foto').click()">📷 Alterar foto</button>
                        <button type="button" class="ep-btn ep-btn-perigo" id="btn-remover-foto" onclick="removerFoto()"
                                <?php echo $usuario['foto'] ? '' : 'style="display:none"'; ?>>Remover foto</button>
                    </div>
                    <p class="ep-foto-dica">JPG, PNG ou WEBP · opcional</p>
                    <input type="file" id="arquivo-foto" accept="image/jpeg,image/png,image/webp" hidden>
                </div>

                <div>
                    <div class="ep-campo">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" maxlength="100" required value="<?php echo $h($usuario['nome']); ?>">
                    </div>
                    <div class="ep-campo">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" maxlength="150" required
                               value="<?php echo $h($usuario['email']); ?>" data-original="<?php echo $h($usuario['email']); ?>">
                    </div>
                    <div class="ep-campo ep-campo-senha-email" id="campo-senha-email">
                        <label for="senha_atual_email">Senha atual (necessária para trocar o e-mail)</label>
                        <input type="password" id="senha_atual_email" name="senha_atual" autocomplete="current-password">
                    </div>
                    <div class="ep-campo">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="3" maxlength="<?php echo BIO_MAX; ?>"
                                  placeholder="Ex.: Fã de suspense psicológico e de tudo que o Nolan faz."><?php echo $h($usuario['bio'] ?? ''); ?></textarea>
                        <div class="ep-campo-rodape"><span>Aparece no seu perfil</span><span class="ep-contador" id="contador-bio"></span></div>
                    </div>
                </div>
            </div>

            <div class="ep-rodape">
                <a href="perfil.php" class="ep-btn ep-btn-sec">Cancelar</a>
                <button type="submit" class="ep-btn ep-btn-primario">Salvar alterações</button>
            </div>
        </form>
    </div>

    <!-- ── Aba: senha ── -->
    <div class="ep-painel <?php echo $aba === 'senha' ? 'ativo' : ''; ?>" id="painel-senha">
        <form class="ep-cartao" method="POST" action="perfilAcoes.php" style="max-width:480px">
            <input type="hidden" name="acao" value="senha">
            <input type="hidden" name="csrf" value="<?php echo $h($csrf); ?>">
            <div class="ep-campo">
                <label for="senha_atual">Senha atual</label>
                <input type="password" id="senha_atual" name="senha_atual" required autocomplete="current-password">
            </div>
            <div class="ep-campo">
                <label for="senha_nova">Nova senha</label>
                <input type="password" id="senha_nova" name="senha_nova" required minlength="6" autocomplete="new-password">
                <div class="ep-campo-rodape"><span>Mínimo de 6 caracteres</span></div>
            </div>
            <div class="ep-campo">
                <label for="senha_confirm">Confirmar nova senha</label>
                <input type="password" id="senha_confirm" name="senha_confirm" required minlength="6" autocomplete="new-password">
            </div>
            <div class="ep-rodape">
                <button type="submit" class="ep-btn ep-btn-primario">Alterar senha</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de recorte da foto -->
<div class="ep-modal" id="modal-recorte">
    <div class="ep-modal-caixa">
        <h3>Ajustar foto</h3>
        <p>Arraste para posicionar e use o controle para dar zoom.</p>
        <div class="ep-recorte" id="area-recorte"><canvas id="canvas-recorte" width="600" height="600"></canvas></div>
        <div class="ep-zoom">−<input type="range" id="zoom" min="1" max="4" step="0.01" value="1">+</div>
        <div class="ep-rodape" style="justify-content:center">
            <button type="button" class="ep-btn ep-btn-sec" onclick="fecharRecorte()">Cancelar</button>
            <button type="button" class="ep-btn ep-btn-primario" onclick="aplicarRecorte()">Aplicar</button>
        </div>
    </div>
</div>

<script>
// ── Abas ──
document.querySelectorAll('.ep-aba').forEach(btn => btn.addEventListener('click', () => {
    document.querySelectorAll('.ep-aba').forEach(b => b.classList.toggle('ativa', b === btn));
    document.querySelectorAll('.ep-painel').forEach(p => p.classList.toggle('ativo', p.id === 'painel-' + btn.dataset.aba));
}));

// ── Contador da bio ──
const bio = document.getElementById('bio'), contador = document.getElementById('contador-bio');
const atualizarContador = () => {
    contador.textContent = bio.value.length + '/' + bio.maxLength;
    contador.classList.toggle('limite', bio.value.length >= bio.maxLength);
};
bio.addEventListener('input', atualizarContador); atualizarContador();

// ── Senha exigida só ao trocar e-mail ──
const email = document.getElementById('email'), campoSenhaEmail = document.getElementById('campo-senha-email');
const senhaEmail = document.getElementById('senha_atual_email');
email.addEventListener('input', () => {
    const mudou = email.value.trim().toLowerCase() !== email.dataset.original.toLowerCase();
    campoSenhaEmail.classList.toggle('visivel', mudou);
    senhaEmail.required = mudou;
});

// ── Foto: seleção e recorte ──
const canvas = document.getElementById('canvas-recorte'), ctx = canvas.getContext('2d');
const zoom = document.getElementById('zoom'), area = document.getElementById('area-recorte');
let img = null, escalaBase = 1, pos = { x: 0, y: 0 }, arrasto = null;

document.getElementById('arquivo-foto').addEventListener('change', e => {
    const arquivo = e.target.files[0];
    e.target.value = '';
    if (!arquivo) return;
    if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) { alert('Use uma imagem JPG, PNG ou WEBP.'); return; }
    if (arquivo.size > 15 * 1024 * 1024) { alert('Imagem muito grande (máx. 15 MB).'); return; }
    const leitor = new FileReader();
    leitor.onload = () => {
        const im = new Image();
        im.onload = () => {
            img = im;
            escalaBase = Math.max(canvas.width / im.width, canvas.height / im.height);
            zoom.value = 1; pos = { x: 0, y: 0 };
            desenhar();
            document.getElementById('modal-recorte').classList.add('visivel');
        };
        im.onerror = () => alert('Não foi possível abrir essa imagem.');
        im.src = leitor.result;
    };
    leitor.readAsDataURL(arquivo);
});

function limitarPosicao() {
    const esc = escalaBase * zoom.value;
    const maxX = Math.max(0, (img.width * esc - canvas.width) / 2);
    const maxY = Math.max(0, (img.height * esc - canvas.height) / 2);
    pos.x = Math.min(maxX, Math.max(-maxX, pos.x));
    pos.y = Math.min(maxY, Math.max(-maxY, pos.y));
}

function desenhar(alvo = ctx, lado = canvas.width) {
    if (!img) return;
    limitarPosicao();
    const f = lado / canvas.width;
    const esc = escalaBase * zoom.value * f;
    const w = img.width * esc, h = img.height * esc;
    alvo.fillStyle = '#111'; alvo.fillRect(0, 0, lado, lado);
    alvo.drawImage(img, (lado - w) / 2 + pos.x * f, (lado - h) / 2 + pos.y * f, w, h);
}

zoom.addEventListener('input', () => desenhar());
area.addEventListener('pointerdown', e => {
    arrasto = { x: e.clientX, y: e.clientY, px: pos.x, py: pos.y };
    area.setPointerCapture(e.pointerId);
});
area.addEventListener('pointermove', e => {
    if (!arrasto) return;
    const r = canvas.width / area.clientWidth;   // canvas interno é maior que o exibido
    pos.x = arrasto.px + (e.clientX - arrasto.x) * r;
    pos.y = arrasto.py + (e.clientY - arrasto.y) * r;
    desenhar();
});
area.addEventListener('pointerup', () => arrasto = null);
area.addEventListener('wheel', e => {
    e.preventDefault();
    zoom.value = Math.min(4, Math.max(1, parseFloat(zoom.value) - e.deltaY * 0.002));
    desenhar();
}, { passive: false });

function fecharRecorte() { document.getElementById('modal-recorte').classList.remove('visivel'); }

function aplicarRecorte() {
    const saida = document.createElement('canvas');
    saida.width = saida.height = 400;
    desenhar(saida.getContext('2d'), 400);
    const dataUrl = saida.toDataURL('image/jpeg', 0.9);
    document.getElementById('foto_data').value = dataUrl;
    document.getElementById('remover_foto').value = '';
    document.getElementById('avatar-preview').innerHTML = "<span class='avatar'><img src='" + dataUrl + "' alt=''></span>";
    document.getElementById('btn-remover-foto').style.display = '';
    fecharRecorte();
}

function removerFoto() {
    document.getElementById('foto_data').value = '';
    document.getElementById('remover_foto').value = '1';
    const nome = document.getElementById('nome').value.trim().split(/\s+/);
    const ini = ((nome[0] || '?')[0] + (nome.length > 1 ? nome[nome.length - 1][0] : '')).toUpperCase();
    const span = document.createElement('span');
    span.className = 'avatar avatar-iniciais';
    span.style.background = '#333';
    span.textContent = ini;
    document.getElementById('avatar-preview').replaceChildren(span);
    document.getElementById('btn-remover-foto').style.display = 'none';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharRecorte(); });
window.addEventListener('load', () => document.getElementById('loading-overlay')?.classList.remove('visivel'));
</script>

<?php include 'footer.php'; ?>
