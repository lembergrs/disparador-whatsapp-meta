<?php
$ehArtigo = !empty($artigo);
$preview = !empty($preview);
$tituloPagina = $ehArtigo ? (($artigo['ART_MetaTitle'] ?? '') ?: $artigo['ART_Titulo']) : (!empty($categoria) ? 'Artigos sobre ' . $categoria['ACG_Nome'] : 'Blog Disparador.net');
$descricaoPagina = mb_substr($ehArtigo ? (($artigo['ART_MetaDescription'] ?? '') ?: $artigo['ART_Resumo']) : 'Conteúdo sobre API Oficial do WhatsApp Business, campanhas, templates, atendimento e boas práticas.', 0, 320);
$canonical = $ehArtigo ? ($artigo['urlCanonicaExibicao'] ?? (($artigo['ART_UrlCanonica'] ?? '') ?: 'https://disparador.net/blog/' . rawurlencode($artigo['ART_Slug']))) : (!empty($categoria) ? 'https://disparador.net/blog/categoria/' . rawurlencode($categoria['ACG_Slug']) : 'https://disparador.net/blog');
$imagem = $ehArtigo && !empty($artigo['ART_ImagemDestaque']) ? 'https://disparador.net' . $artigo['ART_ImagemDestaque'] : null;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php $googleTagManagerSection = 'head'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?= htmlspecialchars($descricaoPagina, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="<?= $preview ? 'noindex, nofollow' : 'index, follow, max-image-preview:large'; ?>">
    <?php if(!$preview){ ?><link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>"><?php } ?>
    <meta name="theme-color" content="#08a63f">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="<?= $ehArtigo ? 'article' : 'website'; ?>">
    <meta property="og:site_name" content="Disparador.net">
    <meta property="og:title" content="<?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($descricaoPagina, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if($imagem){ ?><meta property="og:image" content="<?= htmlspecialchars($imagem, ENT_QUOTES, 'UTF-8'); ?>"><?php } ?>
    <meta name="twitter:card" content="<?= $imagem ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($descricaoPagina, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if($imagem){ ?><meta name="twitter:image" content="<?= htmlspecialchars($imagem, ENT_QUOTES, 'UTF-8'); ?>"><?php } ?>
    <?php if($ehArtigo && !$preview){ ?>
    <script type="application/ld+json"><?= json_encode([
        '@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$artigo['ART_Titulo'],
        'description'=>$descricaoPagina,'image'=>$imagem,'author'=>['@type'=>'Person','name'=>$artigo['autorExibicao'] ?? (($artigo['AutorNome'] ?? '') ?: 'Equipe Disparador.net')],
        'publisher'=>['@type'=>'Organization','name'=>'Disparador.net','logo'=>['@type'=>'ImageObject','url'=>'https://disparador.net/public/assets/img/logo-disparador.png']],
        'datePublished'=>date('c', strtotime($artigo['ART_DataPublicacao'])),'dateModified'=>date('c', strtotime($artigo['ART_AtualizadoEm'])),
        'timeRequired'=>'PT' . max(1, (int) ($artigo['tempoLeitura'] ?? 1)) . 'M',
        'mainEntityOfPage'=>$canonical
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <script type="application/ld+json"><?= json_encode([
        '@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
            ['@type'=>'ListItem','position'=>1,'name'=>'Início','item'=>'https://disparador.net/'],
            ['@type'=>'ListItem','position'=>2,'name'=>'Blog','item'=>'https://disparador.net/blog'],
            ['@type'=>'ListItem','position'=>3,'name'=>$artigo['ACG_Nome'],'item'=>'https://disparador.net/blog/categoria/' . rawurlencode($artigo['ACG_Slug'])],
            ['@type'=>'ListItem','position'=>4,'name'=>$artigo['ART_Titulo'],'item'=>$canonical]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php } ?>
    <link rel="icon" href="<?= ASSET_URL; ?>/img/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= ASSET_URL; ?>/css/style.css?v=13">
</head>
<body class="blog-publico">
<?php $googleTagManagerSection = 'body'; require __DIR__ . '/../partials/google_tag_manager.php'; ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm site-navbar">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_URL; ?>/"><img src="<?= ASSET_URL; ?>/img/logo-disparador.png" alt="Disparador.net" class="site-logo" width="1136" height="247"></a>
        <div class="ml-auto"><a href="<?= BASE_URL; ?>/blog" class="btn btn-link text-dark">Blog</a><a href="<?= BASE_URL; ?>/index.php?url=site/cadastro" class="btn btn-success site-btn-main" data-analytics-event="select_trial" data-analytics-location="blog_header" data-analytics-destination="registration">Começar teste grátis</a></div>
    </div>
</nav>
<?php if($preview){ ?><div class="alert alert-warning text-center rounded-0 mb-0"><strong>PRÉVIA:</strong> este artigo não está indexado nem disponível publicamente.</div><?php } ?>
<main><?= $conteudo; ?></main>
<footer class="py-4 bg-dark text-white mt-5"><div class="container d-flex flex-column flex-md-row justify-content-between"><span>© 2026 Disparador.net</span><span><a class="text-white" href="<?= BASE_URL; ?>/index.php?url=site/termosUso">Termos de Uso</a> · <a class="text-white" href="<?= BASE_URL; ?>/index.php?url=site/politicaPrivacidade">Privacidade</a></span></div></footer>
<?php $analyticsWhatsappLocation = 'blog'; require __DIR__ . '/../site/partials/whatsapp_button.php'; ?>
</body>
</html>
