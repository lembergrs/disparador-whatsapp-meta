<section class="blog-hero py-5 bg-light border-bottom"><div class="container text-center">
    <h1><?= !empty($categoria) ? 'Artigos sobre ' . htmlspecialchars($categoria['ACG_Nome']) : 'Blog Disparador.net'; ?></h1>
    <p class="lead text-muted">Conteúdo prático sobre a API Oficial do WhatsApp Business.</p>
    <form method="get" action="<?= BASE_URL; ?>/blog" class="form-inline justify-content-center mt-4">
        <label class="sr-only" for="buscaBlog">Buscar artigos</label>
        <input type="search" id="buscaBlog" name="q" value="<?= htmlspecialchars($busca ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="form-control mr-2" placeholder="Buscar no blog">
        <button class="btn btn-success" type="submit"><i class="fas fa-search" aria-hidden="true"></i> Buscar</button>
    </form>
</div></section>
<section class="py-5"><div class="container">
    <?php if(empty($artigos)){ ?><div class="alert alert-light border text-center">Nenhum artigo publicado encontrado.</div><?php } ?>
    <?php
    $artigoDestaque = null;
    foreach($artigos as $candidatoDestaque){
        if(($candidatoDestaque['ART_Destaque'] ?? 'N') === 'S'){ $artigoDestaque = $candidatoDestaque; break; }
    }
    ?>
    <?php if($artigoDestaque && empty($busca) && ($pagina ?? 1) === 1){ ?>
    <article class="card site-card-feature mb-5 overflow-hidden"><div class="row no-gutters align-items-stretch">
        <?php if(!empty($artigoDestaque['ART_ImagemDestaque'])){ ?><div class="col-lg-6"><img src="<?= htmlspecialchars($artigoDestaque['ART_ImagemDestaque'], ENT_QUOTES, 'UTF-8'); ?>" class="w-100 h-100 blog-destaque-imagem" alt="" loading="eager"></div><?php } ?>
        <div class="<?= !empty($artigoDestaque['ART_ImagemDestaque'])?'col-lg-6':'col-12'; ?>"><div class="card-body p-4 p-lg-5"><span class="badge badge-success mb-3">Artigo em destaque</span><div class="small text-muted mb-2"><?= htmlspecialchars($artigoDestaque['ACG_Nome']); ?> · <?= date('d/m/Y', strtotime($artigoDestaque['ART_DataPublicacao'])); ?></div><h2><a class="text-dark" href="<?= BASE_URL; ?>/blog/<?= rawurlencode($artigoDestaque['ART_Slug']); ?>"><?= htmlspecialchars($artigoDestaque['ART_Titulo']); ?></a></h2><p class="text-muted"><?= htmlspecialchars($artigoDestaque['ART_Resumo']); ?></p><a class="btn btn-outline-success" href="<?= BASE_URL; ?>/blog/<?= rawurlencode($artigoDestaque['ART_Slug']); ?>">Ler artigo em destaque</a></div></div>
    </div></article>
    <?php } ?>
    <div class="row">
    <?php foreach($artigos as $item){ ?>
        <?php if($artigoDestaque && empty($busca) && ($pagina ?? 1) === 1 && (int)$item['ART_ID'] === (int)$artigoDestaque['ART_ID']) continue; ?>
        <div class="col-md-6 col-lg-4 mb-4"><article class="card h-100 site-card-feature blog-card">
            <?php if(!empty($item['ART_ImagemDestaque'])){ ?><img src="<?= htmlspecialchars($item['ART_ImagemDestaque'], ENT_QUOTES, 'UTF-8'); ?>" class="card-img-top" alt="" loading="lazy"><?php } ?>
            <div class="card-body d-flex flex-column"><div class="small text-success font-weight-bold mb-2"><?= htmlspecialchars($item['ACG_Nome']); ?> · <?= date('d/m/Y', strtotime($item['ART_DataPublicacao'])); ?></div>
            <h2 class="h4"><a class="text-dark" href="<?= BASE_URL; ?>/blog/<?= rawurlencode($item['ART_Slug']); ?>"><?= htmlspecialchars($item['ART_Titulo']); ?></a></h2>
            <p class="text-muted"><?= htmlspecialchars($item['ART_Resumo']); ?></p><a class="mt-auto" href="<?= BASE_URL; ?>/blog/<?= rawurlencode($item['ART_Slug']); ?>">Ler artigo <span aria-hidden="true">→</span></a></div>
        </article></div>
    <?php } ?>
    </div>
    <?php $paginas = max(1, (int) ceil(($total ?? 0) / ($porPagina ?? 9))); if($paginas > 1){ ?><nav aria-label="Paginação do blog"><ul class="pagination justify-content-center"><?php for($p=1;$p<=$paginas;$p++){ ?><li class="page-item <?= $p===$pagina?'active':''; ?>"><a class="page-link" href="?pagina=<?= $p; ?>&q=<?= rawurlencode($busca ?? ''); ?>"><?= $p; ?></a></li><?php } ?></ul></nav><?php } ?>
</div></section>
