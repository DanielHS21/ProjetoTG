<?php
// Autores exibidos no rodapé — para adicionar/remover, edite esta lista
$autores = [
    'Daniel Henrique dos Santos',
    'Matheus Prado e Silva',
];
$autoresHtml = array_map(fn($a) => '<span>' . htmlspecialchars($a) . '</span>', $autores);
$ultimo = array_pop($autoresHtml);
$autoresTexto = $autoresHtml ? implode(', ', $autoresHtml) . ' e ' . $ultimo : $ultimo;
?>
<footer class="site-footer">
    <p class="footer-dev">Desenvolvido por <?php echo $autoresTexto; ?></p>
    <p class="footer-contato">Contato: <a href="mailto:danielcosmos123@gmail.com">danielcosmos123@gmail.com</a></p>
</footer>

<style>
.site-footer {
    margin-top: 4%;
    padding: 1.5em 0;
    border-top: 1px solid #1f1f1f;
    text-align: center;
    font-family: "Josefin Sans", sans-serif;
    font-size: 0.8vw;
    color: #444;
}
.site-footer p {
    margin: 0.3em 0;
}
.site-footer span {
    color: #777;
}
.site-footer a {
    color: #777;
    text-decoration: none;
    transition: color 0.2s;
}
.site-footer a:hover {
    color: red;
}
@media (max-width: 900px) {
    .site-footer { font-size: 12px; padding: 1.4em 16px; margin-top: 40px; }
}
</style>