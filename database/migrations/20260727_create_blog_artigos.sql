-- Aplicação manual. Não executar automaticamente em produção.
CREATE TABLE IF NOT EXISTS artigos_categorias (
    ACG_ID INT NOT NULL AUTO_INCREMENT,
    ACG_Nome VARCHAR(120) NOT NULL,
    ACG_Slug VARCHAR(140) NOT NULL,
    ACG_Ativo ENUM('S','N') NOT NULL DEFAULT 'S',
    ACG_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ACG_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ACG_ID),
    UNIQUE KEY uk_artigos_categorias_slug (ACG_Slug),
    KEY idx_artigos_categorias_ativo_nome (ACG_Ativo, ACG_Nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artigos_tags (
    ATG_ID INT NOT NULL AUTO_INCREMENT,
    ATG_Nome VARCHAR(80) NOT NULL,
    ATG_Slug VARCHAR(100) NOT NULL,
    ATG_Ativo ENUM('S','N') NOT NULL DEFAULT 'S',
    ATG_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ATG_ID),
    UNIQUE KEY uk_artigos_tags_slug (ATG_Slug),
    KEY idx_artigos_tags_ativo_nome (ATG_Ativo, ATG_Nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artigos (
    ART_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ACG_ID INT NOT NULL,
    USU_Autor_ID INT NOT NULL,
    ART_Titulo VARCHAR(220) NOT NULL,
    ART_Slug VARCHAR(240) NOT NULL,
    ART_Resumo VARCHAR(500) NOT NULL,
    ART_Conteudo LONGTEXT NOT NULL,
    ART_ImagemDestaque VARCHAR(500) NULL,
    ART_Status ENUM('rascunho','publicado') NOT NULL DEFAULT 'rascunho',
    ART_Destaque ENUM('S','N') NOT NULL DEFAULT 'N',
    ART_DataPublicacao DATETIME NULL,
    ART_MetaTitle VARCHAR(220) NULL,
    ART_MetaDescription VARCHAR(320) NULL,
    ART_UrlCanonica VARCHAR(500) NULL,
    ART_Ativo ENUM('S','N') NOT NULL DEFAULT 'S',
    ART_CriadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ART_AtualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ART_ID),
    UNIQUE KEY uk_artigos_slug (ART_Slug),
    KEY idx_artigos_publicacao (ART_Ativo, ART_Status, ART_DataPublicacao),
    KEY idx_artigos_categoria_publicacao (ACG_ID, ART_Status, ART_DataPublicacao),
    KEY idx_artigos_destaque (ART_Destaque, ART_Status, ART_DataPublicacao),
    FULLTEXT KEY ft_artigos_busca (ART_Titulo, ART_Resumo, ART_Conteudo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS artigos_tags_relacao (
    ART_ID BIGINT UNSIGNED NOT NULL,
    ATG_ID INT NOT NULL,
    PRIMARY KEY (ART_ID, ATG_ID),
    KEY idx_artigos_tags_relacao_tag (ATG_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estrutura intencionalmente separada em conteúdo, taxonomias e relação N:N.
-- Futuras versões, revisão editorial e metadados de geração assistida podem ser
-- adicionados em tabelas próprias de versões sem alterar a publicação atual.
