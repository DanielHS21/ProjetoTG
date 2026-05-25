<?php
// ============================================================
//  Conexão com o banco de dados
//  Altere as credenciais abaixo conforme seu XAMPP
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'projetotg');
define('DB_USER', 'root');
define('DB_PASS', 'admin123');          // XAMPP padrão: senha vazia

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die('Erro de conexão com o banco: ' . $e->getMessage());
        }
    }
    return $pdo;
}