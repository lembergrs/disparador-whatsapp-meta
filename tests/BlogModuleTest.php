<?php

require_once __DIR__ . '/../app/Services/ArtigoConteudoService.php';
require_once __DIR__ . '/../app/Services/ArtigoImagemService.php';

use Services\ArtigoConteudoService;
use Services\ArtigoImagemService;

$assert = function($condition, $message){
    if(!$condition){ fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};

$assert(ArtigoConteudoService::slug('API Oficial: Guia Rápido!') === 'api-oficial-guia-rapido', 'slug deve ser amigável');
$sanitizado = ArtigoConteudoService::sanitizarHtml('<h2 onclick="x()">Título</h2><script>alert(1)</script><iframe src="x"></iframe><a href="javascript:alert(1)">link</a><p><strong>Seguro</strong></p>');
$assert(strpos($sanitizado, '<script') === false && strpos($sanitizado, '<iframe') === false && strpos($sanitizado, 'onclick') === false && strpos($sanitizado, 'javascript:') === false, 'HTML perigoso deve ser removido');
$assert(strpos($sanitizado, '<h2>Título</h2>') !== false && strpos($sanitizado, '<strong>Seguro</strong>') !== false, 'HTML editorial permitido deve ser mantido');

$sumario = ArtigoConteudoService::prepararSumario('<h2>Primeiro tema</h2><p>A</p><h3>Detalhe</h3><h2>Conclusão</h2>');
$assert(count($sumario['sumario']) === 3, 'sumário deve aparecer com muitos subtítulos');
$assert(strpos($sumario['conteudo'], 'id="primeiro-tema"') !== false, 'subtítulos devem receber âncoras');
$assert(ArtigoConteudoService::prepararSumario('<h2>Único</h2>')['sumario'] === [], 'sumário curto não deve poluir o artigo');
$assert(ArtigoConteudoService::tempoLeitura('') === 1, 'tempo de leitura deve ter mínimo de um minuto');
$assert(ArtigoConteudoService::tempoLeitura('<p>' . implode(' ', array_fill(0, 221, 'palavra')) . '</p>') === 2, 'tempo de leitura deve remover HTML e arredondar para cima');
$assert(ArtigoConteudoService::rotuloTempoLeitura(1) === 'Leitura estimada: 1 minuto', 'tempo de leitura deve usar singular');
$assert(ArtigoConteudoService::rotuloTempoLeitura(2) === 'Leitura estimada: 2 minutos', 'tempo de leitura deve usar plural');
$assert(ArtigoConteudoService::formatarDataPtBr('2026-07-27 10:00:00') === '27 de julho de 2026', 'data deve ser formatada em português sem locale');
$assert(!ArtigoConteudoService::foiAtualizadoDepoisDaPublicacao('2026-07-27 10:00:00', '2026-07-27 10:00:00'), 'datas equivalentes não devem indicar atualização');
$assert(ArtigoConteudoService::foiAtualizadoDepoisDaPublicacao('2026-07-27 10:00:00', '2026-07-30 10:00:00'), 'alteração posterior deve ser indicada');
$assert(ArtigoConteudoService::urlCanonica(['ART_Slug'=>'seguro', 'ART_UrlCanonica'=>'javascript:alert(1)'], 'https://disparador.net') === 'https://disparador.net/blog/seguro', 'canonical inválida deve ser rejeitada');

$tmp = tempnam(sys_get_temp_dir(), 'blog-upload-');
file_put_contents($tmp, 'conteudo-invalido');
try{
    ArtigoImagemService::salvar(['error'=>UPLOAD_ERR_OK,'size'=>strlen('conteudo-invalido'),'tmp_name'=>$tmp,'name'=>'ataque.jpg']);
    $assert(false, 'upload com MIME inválido deveria falhar');
}catch(InvalidArgumentException $e){ $assert(true, 'upload inválido recusado'); }
@unlink($tmp);

$root = dirname(__DIR__);
$router = file_get_contents($root . '/app/Core/Router.php');
$admin = file_get_contents($root . '/app/Controllers/ArtigoAdminController.php');
$publico = file_get_contents($root . '/app/Controllers/BlogController.php');
$model = file_get_contents($root . '/app/Models/Artigo.php');
$layout = file_get_contents($root . '/app/Views/blog/layout.php');
$viewArtigo = file_get_contents($root . '/app/Views/blog/artigo.php');
$migration = file_get_contents($root . '/database/migrations/20260727_create_blog_artigos.sql');
$sitemap = file_get_contents($root . '/app/Controllers/SitemapController.php');
$htaccess = file_get_contents($root . '/.htaccess');

$assert(strpos($router, "controllerClass === 'Controllers\\\\BlogController'") !== false && strpos($router, 'artigo($method)') !== false, 'roteador deve aceitar /blog/slug');
$assert(substr_count($admin, 'validarCsrfPost()') >= 8 && strpos($admin, 'Auth::admin()') !== false, 'CRUD administrativo deve exigir administrador e CSRF');
$assert(strpos($admin, 'ArtigoConteudoService::sanitizarHtml') !== false, 'conteúdo deve ser sanitizado no backend');
$assert(strpos($model, 'GROUP_CONCAT') !== false && strpos($model, 'LIMIT {$porPagina} OFFSET {$offset}') !== false, 'consultas devem evitar N+1 e paginar');
$assert(strpos($model, "ART_Status = 'publicado'") !== false && strpos($model, 'ART_DataPublicacao <= NOW()') !== false, 'área pública deve retornar somente publicados');
$assert(strpos($model, 'public function navegacaoPublica(array $artigo)') !== false && substr_count($model, "c.ACG_Ativo = 'S'") >= 3, 'navegação pública deve existir e excluir rascunhos, futuros e categorias inativas');
$assert(strpos($model, 'ART_ID NOT IN') !== false && strpos($model, 'ORDER BY (a.ACG_ID = ?) DESC') !== false, 'relacionados devem excluir repetidos e priorizar categoria');
$assert(strpos($publico, 'listarPublicados') !== false && strpos($publico, 'buscarPublicadoPorSlug') !== false, 'blog deve implementar listagem, busca e artigo');
$assert(strpos($layout, "'@type'=>'BlogPosting'") !== false && strpos($layout, "'@type'=>'BreadcrumbList'") !== false, 'artigo deve gerar schemas SEO');
$assert(strpos($layout, "'timeRequired'=>'PT'") !== false, 'BlogPosting deve incluir tempo de leitura ISO 8601');
$assert(strpos($viewArtigo, 'breadcrumb-item') !== false && strpos($viewArtigo, 'aria-current="page"') !== false, 'breadcrumb visual e semântico deve ser exibido');
$assert(strpos($viewArtigo, 'wa.me/?text=') !== false && strpos($viewArtigo, 'linkedin.com/sharing/share-offsite') !== false && strpos($viewArtigo, 'facebook.com/sharer/sharer.php') !== false, 'URLs oficiais de compartilhamento devem existir');
$assert(strpos($viewArtigo, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT') !== false && strpos($viewArtigo, 'navigator.clipboard') !== false && strpos($viewArtigo, 'execCommand') !== false, 'cópia deve serializar URL com segurança e oferecer fallback');
$assert(strpos($viewArtigo, "?: 'Equipe Disparador.net'") === false && strpos($publico, "?: 'Equipe Disparador.net'") !== false, 'fallback de autor deve ser preparado fora da view');
$assert(strpos($viewArtigo, 'Atualizado em') !== false && strpos($viewArtigo, "dataAtualizacaoExibicao'] !== ''") !== false, 'atualização deve ser condicional');
$assert(strpos($layout, 'noindex, nofollow') !== false && strpos($admin, "'preview'=>true") !== false, 'preview deve ser noindex');
$assert(strpos($sitemap, 'slugsPublicados') !== false && strpos($htaccess, 'sitemap\\.xml') !== false, 'sitemap deve incluir artigos dinamicamente');
foreach(['artigos','artigos_categorias','artigos_tags','artigos_tags_relacao'] as $tabela){ $assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS ' . $tabela) !== false, 'tabela ausente: ' . $tabela); }

define('BASE_URL', 'https://disparador.net');
define('ASSET_URL', 'https://disparador.net/public/assets');
$artigo = [
    'ART_Titulo'=>'Guia da API Oficial','ART_MetaTitle'=>'Guia da API Oficial | Disparador.net','ART_Resumo'=>'Resumo seguro',
    'ART_MetaDescription'=>'Descrição para mecanismos de busca','ART_UrlCanonica'=>null,'ART_Slug'=>'guia-api-oficial',
    'ART_ImagemDestaque'=>'/public/uploads/blog/imagem.webp','ART_DataPublicacao'=>'2026-07-20 10:00:00','ART_AtualizadoEm'=>'2026-07-21 11:00:00',
    'ACG_Nome'=>'WhatsApp Business', 'ACG_Slug'=>'whatsapp-business', 'autorExibicao'=>'Equipe Disparador.net'
];
$preview = false; $categoria = null; $whatsappSite = null; $conteudo = '<article>Conteúdo</article>';
ob_start(); require $root . '/app/Views/blog/layout.php'; $html = ob_get_clean();
$assert(strpos($html, '<link rel="canonical" href="https://disparador.net/blog/guia-api-oficial">') !== false, 'canonical automático ausente');
$assert(strpos($html, 'application/ld+json') !== false && strpos($html, 'BlogPosting') !== false, 'JSON-LD renderizado ausente');
$assert(strpos($html, '"timeRequired":"PT1M"') !== false, 'timeRequired renderizado ausente');
$assert(strpos($html, 'og:image') !== false && strpos($html, 'twitter:image') !== false, 'imagem social do artigo ausente');

echo "Blog module checks passed\n";
