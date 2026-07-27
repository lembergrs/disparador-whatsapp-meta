<?php

namespace Controllers;

use Core\Controller;
use Models\Artigo;
use Models\ArtigoCategoria;
use Models\ConfiguracaoSite;
use Services\ArtigoConteudoService;

class BlogController extends Controller
{
    private $artigos;
    public function __construct(){ $this->artigos = new Artigo(); }

    public function index()
    {
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $busca = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $resultado = $this->artigos->listarPublicados($pagina, 9, $busca);
        $this->viewComLayout('blog/index', 'blog/layout', [
            'artigos'=>$resultado['itens'], 'total'=>$resultado['total'], 'pagina'=>$pagina,
            'porPagina'=>9, 'busca'=>$busca, 'categoria'=>null,
            'whatsappSite'=>(new ConfiguracaoSite())->obterConfiguracaoWhatsappSite()
        ]);
    }

    public function categoria()
    {
        $partes = explode('/', trim((string) ($_GET['url'] ?? ''), '/'));
        $slug = $partes[2] ?? '';
        $categoria = (new ArtigoCategoria())->buscarPorSlug($slug);
        if(!$categoria){ http_response_code(404); $this->viewComLayout('blog/404', 'blog/layout', ['whatsappSite'=>null]); return; }
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $resultado = $this->artigos->listarPublicados($pagina, 9, '', (int) $categoria['ACG_ID']);
        $this->viewComLayout('blog/index', 'blog/layout', [
            'artigos'=>$resultado['itens'], 'total'=>$resultado['total'], 'pagina'=>$pagina,
            'porPagina'=>9, 'busca'=>'', 'categoria'=>$categoria,
            'whatsappSite'=>(new ConfiguracaoSite())->obterConfiguracaoWhatsappSite()
        ]);
    }

    public function artigo($slug)
    {
        $artigo = $this->artigos->buscarPublicadoPorSlug($slug);
        if(!$artigo){ http_response_code(404); $this->viewComLayout('blog/404', 'blog/layout', ['whatsappSite'=>null]); return; }
        $preparado = ArtigoConteudoService::prepararSumario($artigo['ART_Conteudo']);
        $artigo['ART_Conteudo'] = $preparado['conteudo'];
        $this->viewComLayout('blog/artigo', 'blog/layout', [
            'artigo'=>$artigo, 'sumario'=>$preparado['sumario'], 'relacionados'=>$this->artigos->relacionados($artigo),
            'preview'=>false, 'whatsappSite'=>(new ConfiguracaoSite())->obterConfiguracaoWhatsappSite()
        ]);
    }
}
