-- ============================================================
--  ProjetoTG — Script de criação do banco de dados
--  Execute no phpMyAdmin ou via terminal MySQL do XAMPP
-- ============================================================

CREATE DATABASE IF NOT EXISTS projetotg
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE projetotg;

-- ------------------------------------------------------------
--  Tabela: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(100)    NOT NULL,
    email         VARCHAR(150)    NOT NULL,
    senha_hash    VARCHAR(255)    NOT NULL,          -- armazenada com password_hash()
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Tabela: lista_filmes
--  Equivale à $_SESSION['lista'] atual, mas persistida por usuário
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lista_filmes (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED    NOT NULL,
    filme_id      INT             NOT NULL,           -- ID do filme na API TMDB
    adicionado_em DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_filme (usuario_id, filme_id),   -- evita duplicatas
    KEY idx_usuario (usuario_id),
    CONSTRAINT fk_lista_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Tabela: favoritos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favoritos (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED    NOT NULL,
    filme_id      INT             NOT NULL,
    adicionado_em DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_fav_usuario_filme (usuario_id, filme_id),
    KEY idx_fav_usuario (usuario_id),
    CONSTRAINT fk_fav_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
