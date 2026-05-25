<?php include 'header.php'; ?>

<style>
.auth-container {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 4% 0 2%;
}

.auth-box {
    background: #0d0d0d;
    border: 1px solid #1f1f1f;
    border-radius: 16px;
    padding: 2.5em 3em;
    width: 28vw;
    min-width: 320px;
}

.auth-titulo {
    font-family: "Gabarito", sans-serif;
    font-size: 1.8vw;
    color: red;
    text-align: center;
    margin: 0 0 0.2em 0;
}

.auth-subtitulo {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.75vw;
    color: #555;
    text-align: center;
    margin: 0 0 1.8em 0;
    letter-spacing: 0.05em;
}

.auth-box label {
    display: block;
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.75vw;
    color: #888;
    margin-bottom: 0.4em;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.auth-box input[type="email"],
.auth-box input[type="password"] {
    width: 100%;
    background: #161616;
    border: 1px solid #2a2a2a;
    border-radius: 8px;
    color: white;
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.85vw;
    padding: 0.65em 0.9em;
    box-sizing: border-box;
    margin-bottom: 1.2em;
    outline: none;
    transition: border-color 0.2s;
}

.auth-box input:focus {
    border-color: red;
}

.auth-btn {
    width: 100%;
    background: red;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.7em;
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.85vw;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 0.4em;
}

.auth-btn:hover {
    background: #cc0000;
}

.auth-link {
    text-align: center;
    margin-top: 1.2em;
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.75vw;
    color: #555;
}

.auth-link a {
    color: red;
    text-decoration: none;
    transition: color 0.2s;
}

.auth-link a:hover {
    color: #ff4444;
}

.auth-mensagem {
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.75vw;
    text-align: center;
    padding: 0.6em 1em;
    border-radius: 6px;
    margin-bottom: 1em;
}

.auth-mensagem.erro {
    background: #1a0000;
    border: 1px solid #440000;
    color: #ff6666;
}

.auth-mensagem.sucesso {
    background: #001a00;
    border: 1px solid #004400;
    color: #66ff66;
}

.auth-divider {
    border: none;
    border-top: 1px solid #1f1f1f;
    margin: 1.5em 0;
}
</style>

<div class="auth-container">
    <div class="auth-box">
        <h1 class="auth-titulo">Entrar</h1>
        <p class="auth-subtitulo">Acesse sua conta para ver sua lista</p>

        <?php if (!empty($_GET['erro'])): ?>
            <div class="auth-mensagem erro">
                <?php
                    $erros = [
                        'credenciais' => 'E-mail ou senha incorretos.',
                        'campos'      => 'Preencha todos os campos.',
                    ];
                    echo $erros[$_GET['erro']] ?? 'Erro ao fazer login.';
                ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['msg'])): ?>
            <div class="auth-mensagem sucesso">
                <?php
                    $msgs = [
                        'cadastrado' => 'Conta criada com sucesso! Faça login.',
                        'saiu'       => 'Você saiu da sua conta.',
                    ];
                    echo $msgs[$_GET['msg']] ?? '';
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="auth.php">
            <input type="hidden" name="acao" value="login">

            <label>E-mail</label>
            <input type="email" name="email" placeholder="seu@email.com" required autofocus>

            <label>Senha</label>
            <input type="password" name="senha" placeholder="••••••••" required>

            <button type="submit" class="auth-btn">Entrar</button>
        </form>

        <hr class="auth-divider">

        <p class="auth-link">Não tem conta? <a href="cadastro.php">Cadastre-se</a></p>
    </div>
</div>

<?php include 'footer.php'; ?>