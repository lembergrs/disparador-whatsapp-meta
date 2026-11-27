<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Campanha;
use Models\TemplateMeta;
use Models\Contato;
use Models\FilaEnvio;
use Models\CampanhaVariavel;
use Services\MetaService;
use Models\ListaContato;
use Models\ListaContatoItem;
use Models\MetaConta;
use Models\Cliente;
use Models\ConsumoMensal;
use Services\AnalyticsService;
use Services\MetaHealthService;

use Services\MetaErrorMessageService;
class CampanhaController extends Controller
{
    private $campanhaModel;

    private $templateModel;

    public function __construct()
    {
        Auth::check();

        $this->campanhaModel =
            new Campanha();

        $this->templateModel =
            new TemplateMeta();
    }

    public function index()
    {
        $usuario =
            Auth::usuario();

        $campanhas =
            $this->campanhaModel
            ->listarPorCliente(
                $usuario['cliente_id']
            );

        $templates =
            $this->templateModel
            ->listarAprovadosParaEnvioPorCliente(
                $usuario['cliente_id']
            );

        $listaModel =
            new ListaContato();

        $listas =
            $listaModel->listarPorCliente(
                $usuario['CLI_ID']
            );

        $contatoModel = new Contato();

        $camposContato =
            $contatoModel->camposJsonPorCliente(
                $usuario['cliente_id']
            );

        $clientePlano = (new Cliente())->buscarComPlano($usuario['CLI_ID']);
        $consumoMes = (new ConsumoMensal())->buscarMesAtual($usuario['CLI_ID']);
        $contasMeta = (new MetaConta())->listarPorCliente($usuario['CLI_ID']);
        $metaContaLimite = $contasMeta[0] ?? null;

        $this->view(
            'campanhas/index',
            [
                'titulo' => 'Campanhas',
                'campanhas' => $campanhas,
                'templates' => $templates,
                'camposContato' => $camposContato,
                'listas' => $listas,
                'etiquetas' => (new \Models\Conversa())->listarEtiquetas($usuario['CLI_ID']),
                'clientePlano' => $clientePlano,
                'consumoMes' => $consumoMes,
                'contasMeta' => $contasMeta,
                'metaContaLimite' => $metaContaLimite
            ]
        );
    }

    private function resolverPublicoCampanha(array $usuario, array $template): array
    {
        $listaId = (int) ($_POST['lista'] ?? 0);
        $etiquetas = $_POST['etiquetas'] ?? [];
        $operador = $_POST['operador_etiquetas'] ?? 'OR';
        if (!is_array($etiquetas) || $listaId < 0) {
            throw new \InvalidArgumentException('Segmentação inválida.');
        }
        $publico = (new \Services\SegmentacaoDestinatariosService())->resolver(
            $usuario, (int) $template['MTA_ID'], $listaId ?: null, $etiquetas, $operador
        );
        return [$publico, $listaId ?: null, array_values(array_unique(array_map('intval', $etiquetas))), $operador];
    }

    public function contarDestinatariosAjax()
    {
        $this->validarCsrfPost();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $usuario = Auth::usuario();
            $template = $this->templateModel->buscarAprovadoParaEnvioPorCliente((int) ($_POST['template'] ?? 0), $usuario['CLI_ID']);
            if (!$template) throw new \InvalidArgumentException('Template inválido.');
            [$publico] = $this->resolverPublicoCampanha($usuario, $template);
            echo json_encode(['sucesso'=>true, 'total'=>count($publico), 'assinatura'=>\Services\SegmentacaoDestinatariosService::assinatura(array_column($publico, 'numero'))]);
        } catch (\Throwable $e) {
            echo json_encode(['sucesso'=>false, 'erro'=>'Confira o template e os filtros selecionados.']);
        }
    }

    public function criar()
    {
        $this->validarCsrfPost();
        $usuario = Auth::usuario();
        $template = $this->templateModel->buscarAprovadoParaEnvioPorCliente((int) ($_POST['template'] ?? 0), $usuario['CLI_ID']);
        if (!$template) {
            Session::flash('error', 'Template inválido.');
            $this->redirect('campanha');
            return;
        }
        $this->validarContaMetaParaEnvio((int) $template['MTA_ID'], (int) $usuario['CLI_ID']);
        $db = \Core\Database::getInstance();
        try {
            $db->beginTransaction();
            [$publico, $listaId, $etiquetas, $operador] = $this->resolverPublicoCampanha($usuario, $template);
            if (!$publico) throw new \InvalidArgumentException('Nenhum destinatário encontrado.');
            if (!isset($_POST['total_confirmado'], $_POST['assinatura_publico']) || (int) $_POST['total_confirmado'] !== count($publico) || !is_string($_POST['assinatura_publico']) || !hash_equals(\Services\SegmentacaoDestinatariosService::assinatura(array_column($publico, 'numero')), $_POST['assinatura_publico'])) {
                throw new \InvalidArgumentException('O público mudou. Confira a quantidade e tente novamente.');
            }

            $mapeamento = $_POST['variaveis'] ?? [];
            $componentes = json_decode($template['TMP_Componentes'], true);
            if (!is_array($componentes) || !is_array($mapeamento)) throw new \InvalidArgumentException('Variáveis inválidas.');
            $necessarias = [];
            foreach ($componentes as $comp) {
                $textos = [$comp['text'] ?? ''];
                foreach ($comp['buttons'] ?? [] as $btn) $textos[] = $btn['url'] ?? '';
                foreach ($textos as $texto) {
                    preg_match_all('/{{\s*([^{}]+?)\s*}}/', $texto, $matches);
                    foreach ($matches[1] as $v) $necessarias[trim($v)] = true;
                }
            }
            if (array_diff_key($necessarias, $mapeamento) || array_diff_key($mapeamento, $necessarias)) {
                throw new \InvalidArgumentException('Mapeie todas as variáveis do template.');
            }
            foreach ($publico as $item) {
                $dados = json_decode($item['dados_json'] ?? '{}', true);
                foreach ($mapeamento as $campo) {
                    if (!is_string($campo) || $campo === '' || !isset($dados[$campo]) || !is_scalar($dados[$campo]) || trim((string) $dados[$campo]) === '') {
                        throw new \InvalidArgumentException('Existem destinatários sem os dados necessários para as variáveis. Revise o público ou escolha outro template.');
                    }
                }
            }

            $contatoModel = new Contato();
            $ids = [];
            foreach ($publico as $item) {
                $id = $item['contato_id'] ?? $contatoModel->salvar([
                    'cliente_id'=>$usuario['CLI_ID'], 'nome'=>mb_substr($item['nome'] ?? '', 0, 150),
                    'telefone'=>$item['numero'], 'dados_json'=>'{}'
                ]);
                if (!$contatoModel->buscarPorClienteId($usuario['CLI_ID'], $id)) {
                    throw new \InvalidArgumentException('Existe um contato indisponível no público.');
                }
                $ids[(int) $id] = (int) $id;
            }
            if (count($ids) !== count($publico)) throw new \InvalidArgumentException('O público mudou. Confira os contatos.');
            $lista = $listaId ? (new ListaContato())->buscar($listaId, $usuario['CLI_ID']) : null;
            $nomes = [];
            foreach ((new \Models\Conversa())->listarEtiquetas($usuario['CLI_ID']) as $tag) {
                if (in_array((int) $tag['ETQ_ID'], $etiquetas, true)) $nomes[] = $tag['ETQ_Nome'];
            }
            $campanhaId = $this->campanhaModel->salvar([
                'cliente_id'=>$usuario['CLI_ID'], 'template_id'=>$template['TMP_ID'], 'lista_id'=>$listaId,
                'segmentacao_json'=>json_encode(['lista_id'=>$listaId, 'lista_nome'=>$lista['LST_Nome'] ?? null, 'etiquetas_ids'=>$etiquetas, 'etiquetas_nomes'=>$nomes, 'operador'=>$operador], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'nome'=>trim($_POST['nome'] ?? ''), 'descricao'=>trim($_POST['descricao'] ?? ''),
                'data_agendamento'=>!empty($_POST['data_agendamento']) ? $_POST['data_agendamento'] : date('Y-m-d H:i:s')
            ]);
            $variavelModel = new CampanhaVariavel();
            foreach ($mapeamento as $variavel => $campo) $variavelModel->salvar($campanhaId, $variavel, $campo);
            $fila = new FilaEnvio();
            foreach ($ids as $id) $fila->adicionar($campanhaId, $id);
            $this->campanhaModel->atualizarTotalContatos($campanhaId, count($ids));
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Campanha criação: ' . get_class($e));
            Session::flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Não foi possível criar a campanha.');
            $this->redirect('campanha');
            return;
        }
        if ($this->campanhaModel->contarPorCliente($usuario['CLI_ID']) === 1) {
            AnalyticsService::registrar('first_campaign_created', ['campaign_type'=>'scheduled', 'first_campaign'=>true]);
        }
        Session::flash('success', 'Campanha criada com ' . count($ids) . ' contatos.');
        $this->redirect('campanha');
    }

    public function detalhes()
    {
        $id = $_GET['id'] ?? null;

        if(!$id){
            \Core\Session::flash(
                'error',
                'Campanha não informada.'
            );

            $this->redirect('campanha');
        }

        $usuario = Auth::usuario();

        $campanha =
            $this->campanhaModel->buscarPorCliente($id, $usuario['CLI_ID']);

        if(!$campanha){
            \Core\Session::flash('error', 'Campanha não encontrada.');
            $this->redirect('campanha');
        }

        $fila =
            $this->campanhaModel->listarFilaPorCliente($id, $usuario['CLI_ID']);

        foreach($fila as &$itemFila){
            if(($itemFila['FIL_Status'] ?? '') === 'failed' || ($itemFila['FIL_Status'] ?? '') === 'erro'){
                $itemFila['erro_detalhes'] = MetaErrorMessageService::detalhes($itemFila['FIL_Retorno'] ?? null, $itemFila['FIL_Erro'] ?? null);
                $codigo=MetaErrorMessageService::codigoDoRetorno($itemFila['FIL_Retorno'] ?? null);
                if($codigo !== ''){
                    $itemFila['FIL_Erro']=MetaErrorMessageService::amigavel($codigo, $itemFila['FIL_Erro'] ?? null);
                }
            }
        }
        unset($itemFila);

        $this->view(
            'campanhas/detalhes',
            [
                'titulo' => 'Detalhes da Campanha',
                'campanha' => $campanha,
                'fila' => $fila
            ]
        );
    }

    public function cancelar()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $id = $_POST['id'] ?? null;

        if(!$id){

            \Core\Session::flash(
                'error',
                'Campanha não informada.'
            );

            $this->redirect('campanha');
        }

        $this->campanhaModel->cancelar(
            $id,
            $usuario['CLI_ID']
        );

        \Core\Session::flash(
            'success',
            'Campanha cancelada com sucesso.'
        );

        $this->redirect('campanha/detalhes&id=' . $id);
    }

    public function preview()
    {
        $usuario = Auth::usuario();

        $id = $_GET['id'];

        $campanha =
            $this->campanhaModel
            ->buscarPorCliente($id, $usuario['CLI_ID']);

        if(!$campanha){
            \Core\Session::flash('error', 'Campanha não encontrada.');
            $this->redirect('campanha');
        }





        $contato =
            $this->campanhaModel
            ->buscarContatoExemploPorCliente($id, $usuario['CLI_ID']);





        $variavelModel =
            new CampanhaVariavel();





        $variaveis =
            $variavelModel
            ->listarPorCampanha($id);





        $dadosContato =
            json_decode(
                $contato['CON_DadosJson'],
                true
            );





        $componentes =
            json_decode(
                $campanha['TMP_Componentes'],
                true
            );





        $body = '';

        foreach($componentes as $comp){

            if(
                strtoupper($comp['type'])
                == 'BODY'
            ){

                $body =
                    $comp['text'];

            }

        }






        foreach($variaveis as $var){

            $valor =
                $dadosContato[
                    $var['CPV_Campo']
                ] ?? '';

            $body =
                str_replace(

                    '{{'
                    . $var['CPV_Variavel']
                    . '}}',

                    $valor,

                    $body

                );

        }






        $this->view(

            'campanhas/preview',

            [

                'titulo' =>
                    'Pré-visualização',

                'campanha' =>
                    $campanha,

                'contato' =>
                    $contato,

                'variaveis' =>
                    $variaveis,

                'mensagem' =>
                    $body

            ]

        );
    }

    public function reagendar()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $id =
            $_POST['id'] ?? null;

        $dataAgendamento =
            $_POST['data_agendamento'] ?? null;

        if(!$id || !$dataAgendamento){

            \Core\Session::flash(
                'error',
                'Informe a campanha e a nova data de envio.'
            );

            $this->redirect('campanha');

            return;
        }

        $campanha = $this->campanhaModel->buscarPorCliente($id, $usuario['CLI_ID']);
        if(!$campanha){
            Session::flash('error', 'Campanha não encontrada.');
            $this->redirect('campanha');
        }

        $this->validarContaMetaParaEnvio((int) $campanha['MTA_ID'], (int) $usuario['CLI_ID']);

        $this->campanhaModel->reagendar(
            $id,
            $usuario['CLI_ID'],
            $dataAgendamento
        );

        $this->campanhaModel->resetarFilaPorCliente(
            $id,
            $usuario['CLI_ID']
        );

        \Core\Session::flash(
            'success',
            'Campanha reagendada com sucesso.'
        );

        $this->redirect(
            'campanha/detalhes&id=' . $id
        );
    }

    public function enviarTeste()
    {
        $this->validarCsrfPost();

        $usuario = Auth::usuario();

        $campanhaId =
            $_POST['campanha_id'];

        $telefone =
            $_POST['telefone'];

        $campanha =
            $this->campanhaModel->buscarPorCliente($campanhaId, $usuario['CLI_ID']);

        if(!$campanha){
            \Core\Session::flash('error', 'Campanha não encontrada.');
            $this->redirect('campanha');
        }


        $this->validarContaMetaParaEnvio((int) $campanha['MTA_ID'], (int) $usuario['CLI_ID']);

        $contato =
            $this->campanhaModel->buscarContatoExemploPorCliente($campanhaId, $usuario['CLI_ID']);

        $variavelModel =
            new \Models\CampanhaVariavel();

        $variaveis =
            $variavelModel->listarPorCampanha($campanhaId);

        $dadosContato =
            json_decode(
                $contato['CON_DadosJson'],
                true
            );

        if(!is_array($dadosContato)){
            $dadosContato = [];
        }

        $parametros = [];

        foreach($variaveis as $var){

            $campo =
                $var['CPV_Campo'];

            $parametros[$var['CPV_Variavel']] =
                $dadosContato[$campo] ?? '';

        }

        try{
            $telefone =
                preg_replace(
                    '/\D/',
                    '',
                    $_POST['telefone']
                );
                
            $telefone = '55' . $telefone;

            $meta =
                new MetaService(
                    $campanha['MTA_ID'],
                    $usuario['CLI_ID']
                );

            $retorno =
                $meta->enviarTemplate(
                    $telefone,
                    $campanha,
                    $parametros
                );

            if(isset($retorno['messages'][0]['id'])){

                \Core\Session::flash(
                    'success',
                    'Mensagem de teste enviada com sucesso.'
                );

            }else{

                \Core\Session::flash(
                    'error',
                    $retorno['error']['message']
                    ?? 'Erro ao enviar teste.'
                );

            }

        }catch(\Exception $e){

            \Core\Session::flash(
                'error',
                $e->getMessage()
            );

        }

        $this->redirect(
            'campanha/preview&id=' . $campanhaId
        );
    }

    private function validarContaMetaParaEnvio(int $metaId, int $clienteId): void
    {
        $conta = (new MetaConta())->buscarPorCliente($metaId, $clienteId);
        if(!$conta){
            Session::flash('error', 'Conta Meta não encontrada para este cliente.');
            $this->redirect('campanha');
        }

        $aptidao = MetaHealthService::avaliarAptidaoEnvio($conta);
        if(!$aptidao['permitido']){
            Session::flash('error', $aptidao['mensagem']);
            $this->redirect('campanha');
        }
    }

}
