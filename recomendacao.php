<?php
include 'header.php';

echo '<a href="index.php" class="btn-voltar" title="Voltar"><img src="Imagens/voltar.png" class="icone-voltar" alt="Voltar"></a>';

echo "<h1 class='titulo'>Recomendação</h1>";

if (!isset($_SESSION['lista']) || empty($_SESSION['lista'])) {
    echo "<p style='text-align:center; font-family: \"Josefin Sans\", sans-serif; color:#aaa; margin-top:3%;'>Sua lista está vazia. Adicione filmes para receber recomendações.</p>";
} else {
    echo recomendarFilme($_SESSION['lista'], $apiKey);
}
?>
<?php include 'footer.php'; ?>