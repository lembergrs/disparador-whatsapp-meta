<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\Artigo;
use Models\ArtigoCategoria;
use Models\ArtigoTag;
use Services\ArtigoConteudoService;
use Services\ArtigoImagemService;

class ArtigoAdminController extends Controller
{
    private $artigos;
    private $categorias;
    private $tags;

    public function __construct()
    {
        Auth::admin();
        $this->artigos = new Artigo();
        $this->categorias = new ArtigoCategoria();
        $this->tags = new ArtigoTag();
    }

    public function index()
    {
        $filtros = [
            'status'=>trim((string) ($_GET['status'] ?? '')),
            'categoria'=>(int) ($_GET['categoria'] ?? 0),
            'titulo'=>trim((string) ($_GET['titulo'] ?? ''))
        ];
        $this->view('artigos_admin/index', [
            'titulo'=>'Conteúdo / Artigos',
            'artigos'=>$this->artigos->listarAdmin($filtros),
            'categorias'=>$this->categorias->listar(false),
            'tags'=>$this->tags->listar(false),
            'filtros'=>$filtros
        ]);
    }

    public function formulario()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $artigo = $id ? $this->artigos->buscarAdmin($id) : null;
        if($id && !$artigo){ Session::flash('error', 'Artigo não encontrado.'); $this->redirect('artigoAdmin'); }
        $this->view('artigos_admin/form', [
            'titulo'=>$id ? 'Editar artigo' : 'Novo artigo',
            'artigo'=>$artigo,
            'categorias'=>$this->categorias->listar(),
            'tags'=>$this->tags->listar()
        ]);
    }

    public function salvar()
    {
        $this->validarCsrfPost();
        $id = (int) ($_POST['id'] ?? 0);
        $atual = $id ? $this->artigos->buscarAdmin($id) : null;
        if($id && !$atual){ Session::flash('error', 'Artigo não encontrado.'); $this->redirect('artigoAdmin'); }

        try{
            $titulo = trim(strip_tags((string) ($_POST['titulo'] ?? '')));
            $resumo = trim(strip_tags((string) ($_POST['resumo'] ?? '')));
            $conteudo = ArtigoConteudoService::sanitizarHtml($_POST['conteudo'] ?? '');
            $status = ($_POST['status'] ?? 'rascunho') === 'publicado' ? 'publicado' : 'rascunho';
            $categoriaId = (int) ($_POST['categoria_id'] ?? 0);
            if($titulo === '' || mb_strlen($titulo) > 220 || $resumo === '' || mb_strlen($resumo) > 500 || trim(strip_tags($conteudo)) === '' || !$this->categorias->buscar($categoriaId)){
                throw new \InvalidArgumentException('Preencha título, resumo, conteúdo e categoria dentro dos limites informados.');
            }
            $dataPublicacao = trim((string) ($_POST['data_publicacao'] ?? ''));
            if($status === 'publicado'){
                $timestamp = $dataPublicacao !== '' ? strtotime($dataPublicacao) : time();
                if(!$timestamp || $timestamp > time()){ throw new \InvalidArgumentException('A data de publicação não pode estar no futuro.'); }
                $dataPublicacao = date('Y-m-d H:i:s', $timestamp);
            }else{
                $dataPublicacao = $atual['ART_DataPublicacao'] ?? null;
            }
            $metaTitle = trim(strip_tags((string) ($_POST['meta_title'] ?? '')));
            $metaDescription = trim(strip_tags((string) ($_POST['meta_description'] ?? '')));
            $canonica = trim((string) ($_POST['url_canonica'] ?? ''));
            if(mb_strlen($metaTitle) > 220 || mb_strlen($metaDescription) > 320){ throw new \InvalidArgumentException('Revise os limites dos campos de SEO.'); }
            if($canonica !== '' && (!filter_var($canonica, FILTER_VALIDATE_URL) || stripos($canonica, 'https://') !== 0)){ throw new \InvalidArgumentException('Informe uma URL canônica HTTPS válida.'); }

            $imagem = ArtigoImagemService::salvar($_FILES['imagem_destaque'] ?? []);
            if(!$imagem) $imagem = $atual['ART_ImagemDestaque'] ?? null;

            $artigoId = $this->artigos->salvar([
                'id'=>$id, 'categoria_id'=>$categoriaId, 'autor_id'=>(int) ($atual['USU_Autor_ID'] ?? Auth::usuario()['id']),
                'titulo'=>$titulo, 'slug'=>trim((string) ($_POST['slug'] ?? '')), 'resumo'=>$resumo,
                'conteudo'=>$conteudo, 'imagem'=>$imagem, 'status'=>$status,
                'destaque'=>!empty($_POST['destaque']) ? 'S' : 'N', 'data_publicacao'=>$dataPublicacao,
                'meta_title'=>$metaTitle ?: null, 'meta_description'=>$metaDescription ?: null,
                'url_canonica'=>$canonica ?: null
            ], $_POST['tags'] ?? []);
            Session::flash('success', 'Artigo salvo com sucesso.');
            $this->redirect('artigoAdmin/formulario&id=' . $artigoId);
        }catch(\Throwable $e){
            error_log('Erro ao salvar artigo: ' . $e->getMessage());
            Session::flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Não foi possível salvar o artigo.');
            $this->redirect('artigoAdmin/formulario' . ($id ? '&id=' . $id : ''));
        }
    }

    public function publicar(){ $this->mudarStatus('publicado'); }
    public function despublicar(){ $this->mudarStatus('rascunho'); }
    private function mudarStatus($status){ $this->validarCsrfPost(); $this->artigos->alterarStatus((int) ($_GET['id'] ?? 0), $status); Session::flash('success', $status === 'publicado' ? 'Artigo publicado.' : 'Artigo despublicado.'); $this->redirect('artigoAdmin'); }
    public function excluir(){ $this->validarCsrfPost(); $this->artigos->inativar((int) ($_GET['id'] ?? 0)); Session::flash('success', 'Artigo excluído.'); $this->redirect('artigoAdmin'); }

    public function preview()
    {
        $artigo = $this->artigos->buscarAdmin((int) ($_GET['id'] ?? 0));
        if(!$artigo){ http_response_code(404); exit('Artigo não encontrado'); }
        $preparado = ArtigoConteudoService::prepararSumario($artigo['ART_Conteudo']);
        $artigo['ART_Conteudo'] = $preparado['conteudo'];
        $this->viewComLayout('blog/artigo', 'blog/layout', ['artigo'=>$artigo, 'sumario'=>$preparado['sumario'], 'relacionados'=>[], 'preview'=>true, 'whatsappSite'=>null]);
    }

    public function uploadImagemConteudo()
    {
        $this->validarCsrfPost();
        header('Content-Type: application/json; charset=UTF-8');
        try{
            $caminho = ArtigoImagemService::salvar($_FILES['imagem'] ?? []);
            if(!$caminho){ throw new \InvalidArgumentException('Selecione uma imagem.'); }
            echo json_encode(['ok'=>true, 'url'=>$caminho], JSON_UNESCAPED_SLASHES);
        }catch(\Throwable $e){
            http_response_code(422);
            echo json_encode(['ok'=>false, 'message'=>$e instanceof \InvalidArgumentException ? $e->getMessage() : 'Não foi possível enviar a imagem.']);
        }
        exit;
    }

    public function salvarCategoria(){ $this->validarCsrfPost(); try{ $this->categorias->salvar($_POST['nome'] ?? '', (int) ($_POST['id'] ?? 0)); Session::flash('success','Categoria salva.'); }catch(\Throwable $e){ Session::flash('error',$e->getMessage()); } $this->redirect('artigoAdmin'); }
    public function excluirCategoria(){ $this->validarCsrfPost(); $this->categorias->inativar((int) ($_GET['id'] ?? 0)); Session::flash('success','Categoria desativada.'); $this->redirect('artigoAdmin'); }
    public function salvarTag(){ $this->validarCsrfPost(); try{ $this->tags->salvar($_POST['nome'] ?? '', (int) ($_POST['id'] ?? 0)); Session::flash('success','Tag salva.'); }catch(\Throwable $e){ Session::flash('error',$e->getMessage()); } $this->redirect('artigoAdmin'); }
    public function excluirTag(){ $this->validarCsrfPost(); $this->tags->inativar((int) ($_GET['id'] ?? 0)); Session::flash('success','Tag desativada.'); $this->redirect('artigoAdmin'); }
}
