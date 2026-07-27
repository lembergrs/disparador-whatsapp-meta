<?php $tagsSelecionadas = array_map(function($tag){ return (int)$tag['ATG_ID']; }, $artigo['tags'] ?? []); ?>
<form method="post" action="<?= BASE_URL; ?>/index.php?url=artigoAdmin/salvar" enctype="multipart/form-data">
<?= \Core\Csrf::input(); ?><input type="hidden" name="id" value="<?= (int)($artigo['ART_ID']??0); ?>">
<div class="row"><div class="col-lg-8">
<div class="card"><div class="card-header"><h3 class="card-title">Conteúdo</h3></div><div class="card-body">
<div class="form-group"><label for="artTitulo">Título</label><input id="artTitulo" name="titulo" maxlength="220" class="form-control" required value="<?= htmlspecialchars($artigo['ART_Titulo']??'',ENT_QUOTES,'UTF-8'); ?>"></div>
<div class="form-group"><label for="artSlug">Slug</label><input id="artSlug" name="slug" maxlength="240" class="form-control" value="<?= htmlspecialchars($artigo['ART_Slug']??'',ENT_QUOTES,'UTF-8'); ?>"><small class="text-muted">Deixe vazio para gerar automaticamente; duplicidades recebem sufixo numérico.</small></div>
<div class="form-group"><label for="artResumo">Resumo</label><textarea id="artResumo" name="resumo" maxlength="500" rows="3" class="form-control" required><?= htmlspecialchars($artigo['ART_Resumo']??''); ?></textarea></div>
<div class="form-group"><label for="artConteudo">Conteúdo completo</label><textarea id="artConteudo" name="conteudo" class="form-control editor-artigo" rows="18" required><?= htmlspecialchars($artigo['ART_Conteudo']??'',ENT_QUOTES,'UTF-8'); ?></textarea><small class="text-muted">HTML é sanitizado no servidor. Scripts, iframes e atributos perigosos são removidos.</small></div>
</div></div>
<div class="card"><div class="card-header"><h3 class="card-title">SEO</h3></div><div class="card-body"><div class="form-group"><label for="metaTitle">Meta Title</label><input id="metaTitle" name="meta_title" maxlength="220" class="form-control" value="<?= htmlspecialchars($artigo['ART_MetaTitle']??'',ENT_QUOTES,'UTF-8'); ?>"></div><div class="form-group"><label for="metaDesc">Meta Description</label><textarea id="metaDesc" name="meta_description" maxlength="320" rows="2" class="form-control"><?= htmlspecialchars($artigo['ART_MetaDescription']??''); ?></textarea></div><div class="form-group"><label for="canonica">URL Canônica</label><input id="canonica" name="url_canonica" type="url" maxlength="500" class="form-control" placeholder="https://disparador.net/blog/..." value="<?= htmlspecialchars($artigo['ART_UrlCanonica']??'',ENT_QUOTES,'UTF-8'); ?>"></div></div></div>
</div><div class="col-lg-4">
<div class="card"><div class="card-header"><h3 class="card-title">Organização e publicação</h3></div><div class="card-body">
<div class="form-group"><label for="categoria">Categoria</label><select id="categoria" name="categoria_id" class="form-control" required><option value="">Selecione</option><?php foreach($categorias as $cat){ ?><option value="<?= (int)$cat['ACG_ID']; ?>" <?= (int)($artigo['ACG_ID']??0)===(int)$cat['ACG_ID']?'selected':''; ?>><?= htmlspecialchars($cat['ACG_Nome']); ?></option><?php } ?></select></div>
<div class="form-group"><label for="tags">Tags</label><select id="tags" name="tags[]" class="form-control" multiple size="8"><?php foreach($tags as $tag){ ?><option value="<?= (int)$tag['ATG_ID']; ?>" <?= in_array((int)$tag['ATG_ID'],$tagsSelecionadas,true)?'selected':''; ?>><?= htmlspecialchars($tag['ATG_Nome']); ?></option><?php } ?></select></div>
<div class="form-group"><label for="status">Status</label><select id="status" name="status" class="form-control"><option value="rascunho" <?= ($artigo['ART_Status']??'')!=='publicado'?'selected':''; ?>>Rascunho</option><option value="publicado" <?= ($artigo['ART_Status']??'')==='publicado'?'selected':''; ?>>Publicado</option></select></div>
<div class="form-group"><label for="dataPublicacao">Data de publicação</label><input id="dataPublicacao" type="datetime-local" name="data_publicacao" class="form-control" max="<?= date('Y-m-d\TH:i'); ?>" value="<?= !empty($artigo['ART_DataPublicacao'])?date('Y-m-d\TH:i',strtotime($artigo['ART_DataPublicacao'])):''; ?>"></div>
<div class="custom-control custom-checkbox mb-3"><input id="destaque" type="checkbox" name="destaque" value="1" class="custom-control-input" <?= ($artigo['ART_Destaque']??'N')==='S'?'checked':''; ?>><label for="destaque" class="custom-control-label">Artigo em destaque</label></div>
<div class="form-group"><label for="imagem">Imagem de destaque</label><input id="imagem" type="file" name="imagem_destaque" class="form-control-file" accept="image/jpeg,image/png,image/webp"><small class="text-muted">JPG, PNG ou WebP, até 5 MB.</small><?php if(!empty($artigo['ART_ImagemDestaque'])){ ?><img src="<?= htmlspecialchars($artigo['ART_ImagemDestaque']); ?>" class="img-fluid mt-2" alt="Imagem atual"><?php } ?></div>
</div><div class="card-footer"><button class="btn btn-success btn-block"><i class="fas fa-save"></i> Salvar artigo</button><?php if(!empty($artigo['ART_ID'])){ ?><a target="_blank" rel="noopener" class="btn btn-outline-secondary btn-block" href="<?= BASE_URL; ?>/index.php?url=artigoAdmin/preview&id=<?= (int)$artigo['ART_ID']; ?>">Visualizar prévia</a><?php } ?></div></div>
</div></div></form>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script>
<script>
$(function(){
    $('.editor-artigo').summernote({
        height:420,
        toolbar:[['style',['style']],['font',['bold','italic','underline']],['para',['ul','ol','paragraph']],['insert',['link','picture','table']],['view',['codeview']]],
        callbacks:{
            onImageUpload:function(files){
                const editor = $(this);
                Array.from(files).forEach(function(file){
                    const dados = new FormData();
                    dados.append('imagem', file);
                    dados.append('csrf_token', CSRF_TOKEN);
                    $.ajax({url:BASE_URL + '/index.php?url=artigoAdmin/uploadImagemConteudo', method:'POST', data:dados, processData:false, contentType:false})
                        .done(function(resposta){ if(resposta.ok){ editor.summernote('insertImage', resposta.url); } })
                        .fail(function(xhr){ alert((xhr.responseJSON && xhr.responseJSON.message) || 'Não foi possível enviar a imagem.'); });
                });
            }
        }
    });
});
</script>
