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
$migration = file_get_contents($root . '/database/migrations/20260727_create_blog_artigos.sql');
$sitemap = file_get_contents($root . '/app/Controllers/SitemapController.php');
$htaccess = file_get_contents($root . '/.htaccess');

$assert(strpos($router, "controllerClass === 'Controllers\\\\BlogController'") !== false && strpos($router, 'artigo($method)') !== false, 'roteador deve aceitar /blog/slug');
$assert(substr_count($admin, 'validarCsrfPost()') >= 8 && strpos($admin, 'Auth::admin()') !== false, 'CRUD administrativo deve exigir administrador e CSRF');
$assert(strpos($admin, 'ArtigoConteudoService::sanitizarHtml') !== false, 'conteúdo deve ser sanitizado no backend');
$assert(strpos($model, 'GROUP_CONCAT') !== false && strpos($model, 'LIMIT {$porPagina} OFFSET {$offset}') !== false, 'consultas devem evitar N+1 e paginar');
$assert(strpos($model, "ART_Status = 'publicado'") !== false && strpos($model, 'ART_DataPublicacao <= NOW()') !== false, 'área pública deve retornar somente publicados');
$assert(strpos($publico, 'listarPublicados') !== false && strpos($publico, 'buscarPublicadoPorSlug') !== false, 'blog deve implementar listagem, busca e artigo');
$assert(strpos($layout, "'@type'=>'BlogPosting'") !== false && strpos($layout, "'@type'=>'BreadcrumbList'") !== false, 'artigo deve gerar schemas SEO');
$assert(strpos($layout, 'noindex, nofollow') !== false && strpos($admin, "'preview'=>true") !== false, 'preview deve ser noindex');
$assert(strpos($sitemap, 'slugsPublicados') !== false && strpos($htaccess, 'sitemap\\.xml') !== false, 'sitemap deve incluir artigos dinamicamente');
foreach(['artigos','artigos_categorias','artigos_tags','artigos_tags_relacao'] as $tabela){ $assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS ' . $tabela) !== false, 'tabela ausente: ' . $tabela); }

define('BASE_URL', 'https://disparador.net');
define('ASSET_URL', 'https://disparador.net/public/assets');
$artigo = [
    'ART_Titulo'=>'Guia da API Oficial','ART_MetaTitle'=>'Guia da API Oficial | Disparador.net','ART_Resumo'=>'Resumo seguro',
    'ART_MetaDescription'=>'Descrição para mecanismos de busca','ART_UrlCanonica'=>null,'ART_Slug'=>'guia-api-oficial',
    'ART_ImagemDestaque'=>'/public/uploads/blog/imagem.webp','ART_DataPublicacao'=>'2026-07-20 10:00:00','ART_AtualizadoEm'=>'2026-07-21 11:00:00'
];
$preview = false; $categoria = null; $whatsappSite = null; $conteudo = '<article>Conteúdo</article>';
ob_start(); require $root . '/app/Views/blog/layout.php'; $html = ob_get_clean();
$assert(strpos($html, '<link rel="canonical" href="https://disparador.net/blog/guia-api-oficial">') !== false, 'canonical automático ausente');
$assert(strpos($html, 'application/ld+json') !== false && strpos($html, 'BlogPosting') !== false, 'JSON-LD renderizado ausente');
$assert(strpos($html, 'og:image') !== false && strpos($html, 'twitter:image') !== false, 'imagem social do artigo ausente');

echo "Blog module checks passed\n";
