<article class="py-5"><div class="container blog-artigo-container">
    <nav aria-label="Breadcrumb" class="small mb-4"><a href="<?= BASE_URL; ?>/blog">Blog</a> / <a href="<?= BASE_URL; ?>/blog/categoria/<?= rawurlencode($artigo['ACG_Slug']); ?>"><?= htmlspecialchars($artigo['ACG_Nome']); ?></a></nav>
    <header class="mb-4"><h1><?= htmlspecialchars($artigo['ART_Titulo']); ?></h1><p class="lead text-muted"><?= htmlspecialchars($artigo['ART_Resumo']); ?></p><div class="small text-muted">Publicado em <?= $artigo['ART_DataPublicacao'] ? date('d/m/Y', strtotime($artigo['ART_DataPublicacao'])) : 'não publicado'; ?> · <?= htmlspecialchars($artigo['ACG_Nome']); ?></div></header>
    <?php if(!empty($artigo['ART_ImagemDestaque'])){ ?><img src="<?= htmlspecialchars($artigo['ART_ImagemDestaque'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($artigo['ART_Titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid rounded blog-artigo-imagem mb-4"><?php } ?>
    <?php if(!empty($sumario)){ ?><aside class="card bg-light mb-4"><div class="card-body"><h2 class="h5">Neste artigo</h2><ul class="mb-0"><?php foreach($sumario as $item){ ?><li class="<?= $item['nivel']==='h3'?'ml-3':''; ?>"><a href="#<?= htmlspecialchars($item['id']); ?>"><?= htmlspecialchars($item['texto']); ?></a></li><?php } ?></ul></div></aside><?php } ?>
    <div class="blog-artigo-conteudo"><?= $artigo['ART_Conteudo']; ?></div>
    <?php if(!empty($artigo['tags'])){ ?><div class="mt-4" aria-label="Tags"><?php foreach($artigo['tags'] as $tag){ ?><span class="badge badge-light border mr-1"><?= htmlspecialchars($tag['ATG_Nome']); ?></span><?php } ?></div><?php } ?>
    <div class="site-final-cta rounded text-center p-5 mt-5"><h2 class="h3">Leve sua comunicação no WhatsApp para o próximo nível</h2><p>Conheça campanhas, templates e atendimento pela API Oficial do WhatsApp Business.</p><a class="btn btn-light" href="<?= BASE_URL; ?>/index.php?url=site/cadastro" data-analytics-event="click_start_trial" data-analytics-location="blog_cta">Começar teste grátis</a></div>
    <?php if(!empty($relacionados)){ ?><section class="mt-5"><h2>Artigos relacionados</h2><div class="row"><?php foreach($relacionados as $rel){ ?><div class="col-md-4 mb-3"><div class="card h-100"><div class="card-body"><h3 class="h5"><a href="<?= BASE_URL; ?>/blog/<?= rawurlencode($rel['ART_Slug']); ?>"><?= htmlspecialchars($rel['ART_Titulo']); ?></a></h3><p class="small text-muted mb-0"><?= htmlspecialchars($rel['ART_Resumo']); ?></p></div></div></div><?php } ?></div></section><?php } ?>
</div></article>
<?php if(empty($preview)){ ?>
<script>
window.Disparador.analytics.push('view_blog_post', <?= json_encode([
    'article_slug'=>(string)$artigo['ART_Slug'],
    'article_title'=>(string)$artigo['ART_Titulo'],
    'article_category'=>(string)$artigo['ACG_Nome'],
    'article_author'=>(string)($artigo['AutorNome'] ?: 'Equipe Disparador.net'),
    'article_reading_time'=>(int)$artigo['ART_TempoLeitura'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
</script>
<?php } ?>
