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

        $artigo['ART_TempoLeitura'] = ArtigoConteudoService::tempoLeitura($artigo['ART_Conteudo']);

        $artigo['tempoLeitura'] = ArtigoConteudoService::tempoLeitura($artigo['ART_Conteudo']);
        $artigo['autorExibicao'] = trim((string)($artigo['AutorNome'] ?? '')) ?: 'Equipe Disparador.net';
        $artigo['dataPublicacaoExibicao'] = ArtigoConteudoService::formatarDataPtBr($artigo['ART_DataPublicacao']);
        $artigo['dataAtualizacaoExibicao'] = ArtigoConteudoService::foiAtualizadoDepoisDaPublicacao(
            $artigo['ART_DataPublicacao'],
            $artigo['ART_AtualizadoEm']
        )
            ? ArtigoConteudoService::formatarDataPtBr($artigo['ART_AtualizadoEm'])
            : null;

        $artigo['urlCanonicaExibicao'] = ArtigoConteudoService::urlCanonica($artigo, BASE_URL);

        $navegacao = $this->artigos->navegacaoPublica($artigo);

        $excluirRelacionados = array_filter([
            $navegacao['anterior']['ART_ID'] ?? null,
            $navegacao['proximo']['ART_ID'] ?? null,
        ]);
        $this->viewComLayout('blog/artigo', 'blog/layout', [
            'artigo'=>$artigo, 'sumario'=>$preparado['sumario'], 'navegacao'=>$navegacao,
            'relacionados'=>$this->artigos->relacionados($artigo, 3, $excluirRelacionados),
            'preview'=>false, 'whatsappSite'=>(new ConfiguracaoSite())->obterConfiguracaoWhatsappSite()
        ]);
    }
}
