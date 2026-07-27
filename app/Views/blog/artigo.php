<?php
$esc = function($valor){ return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); };
$canonical = $artigo['urlCanonicaExibicao'];
$tituloCompartilhamento = $artigo['ART_Titulo'] . ' — ' . $canonical;
$urlsCompartilhamento = [
    'whatsapp'=>'https://wa.me/?text=' . rawurlencode($tituloCompartilhamento),
    'linkedin'=>'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($canonical),
    'facebook'=>'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($canonical)
];
$renderizarSumario = function() use ($sumario, $esc){ ?>
    <ol class="blog-sumario-lista mb-0 pl-3">
        <?php foreach($sumario as $item){ ?><li class="<?= $item['nivel'] === 'h3' ? 'ml-3' : ''; ?>"><a href="#<?= $esc($item['id']); ?>"><?= $esc($item['texto']); ?></a></li><?php } ?>
    </ol>
<?php };
?>
<article class="py-4 py-md-5"><div class="container blog-artigo-container">
    <nav aria-label="Navegação estrutural">
        <ol class="breadcrumb blog-breadcrumb flex-wrap px-0 bg-transparent small mb-4">
            <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>/">Início</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>/blog">Blog</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>/blog/categoria/<?= rawurlencode($artigo['ACG_Slug']); ?>"><?= $esc($artigo['ACG_Nome']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $esc($artigo['ART_Titulo']); ?></li>
        </ol>
    </nav>
    <header class="blog-artigo-cabecalho mb-4">
        <h1><?= $esc($artigo['ART_Titulo']); ?></h1>
        <p class="lead text-muted"><?= $esc($artigo['ART_Resumo']); ?></p>
        <div class="blog-artigo-meta small text-muted" aria-label="Informações do artigo">
            <span><?= $esc($artigo['autorExibicao']); ?></span><span>Publicado em <?= $esc($artigo['dataPublicacaoExibicao']); ?></span>
            <?php if($artigo['dataAtualizacaoExibicao'] !== ''){ ?><span>Atualizado em <?= $esc($artigo['dataAtualizacaoExibicao']); ?></span><?php } ?>
            <span><?= $esc(\Services\ArtigoConteudoService::rotuloTempoLeitura($artigo['tempoLeitura'])); ?></span>
        </div>
    </header>
    <?php if(!empty($artigo['ART_ImagemDestaque'])){ ?><img src="<?= $esc($artigo['ART_ImagemDestaque']); ?>" alt="<?= $esc($artigo['ART_Titulo']); ?>" class="img-fluid rounded blog-artigo-imagem mb-4"><?php } ?>

    <div class="blog-artigo-grade <?= empty($sumario) ? 'sem-sumario' : ''; ?>">
        <?php if(!empty($sumario)){ ?><aside class="blog-sumario card bg-light" aria-labelledby="sumario-titulo">
            <div class="card-header p-0 d-lg-none"><button class="btn btn-link btn-block text-left p-3 collapsed" type="button" data-toggle="collapse" data-target="#blog-sumario-conteudo" aria-expanded="false" aria-controls="blog-sumario-conteudo"><i class="fas fa-list-ul mr-2" aria-hidden="true"></i>Neste artigo</button></div>
            <div id="blog-sumario-conteudo" class="collapse blog-sumario-conteudo"><div class="card-body"><h2 id="sumario-titulo" class="h5 d-none d-lg-block">Neste artigo</h2><?php $renderizarSumario(); ?></div></div>
        </aside><?php } ?>
        <div class="blog-artigo-corpo">
            <div class="blog-artigo-conteudo"><?= $artigo['ART_Conteudo']; ?></div>
            <?php if(!empty($artigo['tags'])){ ?><div class="mt-4" aria-label="Tags"><?php foreach($artigo['tags'] as $tag){ ?><span class="badge badge-light border mr-1"><?= $esc($tag['ATG_Nome']); ?></span><?php } ?></div><?php } ?>

            <aside class="blog-sobre rounded p-4 mt-5" aria-labelledby="sobre-disparador"><h2 id="sobre-disparador" class="h4">Sobre o Disparador.net</h2><p>O Disparador.net é uma plataforma integrada à API Oficial do WhatsApp Business, desenvolvida para ajudar empresas a organizar contatos, enviar campanhas, acompanhar conversas e profissionalizar sua comunicação com clientes de forma segura.</p><a class="btn btn-success" href="<?= BASE_URL; ?>/index.php?url=site/cadastro">Conheça o Disparador.net</a></aside>

            <section class="blog-compartilhar mt-5" aria-labelledby="titulo-compartilhar"><h2 id="titulo-compartilhar" class="h4">Compartilhe este artigo</h2><div class="d-flex flex-wrap" role="group" aria-label="Opções de compartilhamento">
                <a class="btn btn-success mr-2 mb-2" href="<?= $esc($urlsCompartilhamento['whatsapp']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Compartilhar no WhatsApp"><i class="fab fa-whatsapp mr-1" aria-hidden="true"></i> WhatsApp</a>
                <a class="btn btn-outline-primary mr-2 mb-2" href="<?= $esc($urlsCompartilhamento['linkedin']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Compartilhar no LinkedIn"><i class="fab fa-linkedin mr-1" aria-hidden="true"></i> LinkedIn</a>
                <a class="btn btn-outline-primary mr-2 mb-2" href="<?= $esc($urlsCompartilhamento['facebook']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Compartilhar no Facebook"><i class="fab fa-facebook mr-1" aria-hidden="true"></i> Facebook</a>
                <button class="btn btn-outline-secondary mb-2" type="button" id="copiar-link-artigo" data-url="<?= $esc($canonical); ?>" aria-label="Copiar link do artigo"><i class="fas fa-link mr-1" aria-hidden="true"></i> <span>Copiar link</span></button>
                <span id="feedback-copia" class="align-self-center ml-2 mb-2 text-success" role="status" aria-live="polite"></span>
            </div></section>

            <?php if(!empty($navegacao['anterior']) || !empty($navegacao['proximo'])){ ?><nav class="blog-navegacao-artigos row mt-5" aria-label="Navegação entre artigos">
                <div class="col-md-6 mb-3"><?php if(!empty($navegacao['anterior'])){ ?><a class="card h-100 p-3" rel="prev" href="<?= BASE_URL; ?>/blog/<?= rawurlencode($navegacao['anterior']['ART_Slug']); ?>"><small>← Artigo anterior</small><strong><?= $esc($navegacao['anterior']['ART_Titulo']); ?></strong></a><?php } ?></div>
                <div class="col-md-6 mb-3 text-md-right"><?php if(!empty($navegacao['proximo'])){ ?><a class="card h-100 p-3" rel="next" href="<?= BASE_URL; ?>/blog/<?= rawurlencode($navegacao['proximo']['ART_Slug']); ?>"><small>Próximo artigo →</small><strong><?= $esc($navegacao['proximo']['ART_Titulo']); ?></strong></a><?php } ?></div>
            </nav><?php } ?>

            <?php if(!empty($relacionados)){ ?><section class="mt-5" aria-labelledby="leia-tambem"><h2 id="leia-tambem">Leia também</h2><div class="row"><?php foreach($relacionados as $rel){ ?><div class="col-md-4 mb-3"><article class="card blog-relacionado h-100"><?php if(!empty($rel['ART_ImagemDestaque'])){ ?><img class="card-img-top" loading="lazy" src="<?= $esc($rel['ART_ImagemDestaque']); ?>" alt="<?= $esc($rel['ART_Titulo']); ?>"><?php } ?><div class="card-body d-flex flex-column"><h3 class="h5"><a href="<?= BASE_URL; ?>/blog/<?= rawurlencode($rel['ART_Slug']); ?>"><?= $esc($rel['ART_Titulo']); ?></a></h3><p class="small text-muted"><?= $esc(mb_substr($rel['ART_Resumo'], 0, 140)); ?><?= mb_strlen($rel['ART_Resumo']) > 140 ? '…' : ''; ?></p><a class="mt-auto" href="<?= BASE_URL; ?>/blog/<?= rawurlencode($rel['ART_Slug']); ?>" aria-label="Ler <?= $esc($rel['ART_Titulo']); ?>">Ler artigo →</a></div></article></div><?php } ?></div></section><?php } ?>

            <div class="site-final-cta rounded text-center p-4 p-md-5 mt-5"><h2 class="h3">Leve sua comunicação no WhatsApp para o próximo nível</h2><p>Conheça campanhas, templates e atendimento pela API Oficial do WhatsApp Business.</p><a class="btn btn-light" href="<?= BASE_URL; ?>/index.php?url=site/cadastro">Começar teste grátis</a></div>
        </div>
    </div>
</div></article>
<script>
(function(){
    'use strict';
    var botao = document.getElementById('copiar-link-artigo');
    if(!botao){ return; }
    var url = <?= json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var feedback = document.getElementById('feedback-copia');
    function sucesso(){ botao.querySelector('span').textContent = 'Copiado'; feedback.textContent = 'Link copiado!'; window.setTimeout(function(){ botao.querySelector('span').textContent = 'Copiar link'; feedback.textContent = ''; }, 3000); }
    function fallback(){ var campo = document.createElement('textarea'); campo.value = url; campo.setAttribute('readonly', ''); campo.style.position = 'fixed'; campo.style.opacity = '0'; document.body.appendChild(campo); campo.select(); var copiado = document.execCommand('copy'); document.body.removeChild(campo); if(copiado){ sucesso(); } else { feedback.textContent = 'Selecione e copie o endereço da página.'; } }
    botao.addEventListener('click', function(){ if(navigator.clipboard && window.isSecureContext){ navigator.clipboard.writeText(url).then(sucesso).catch(fallback); } else { fallback(); } });
}());
</script>
