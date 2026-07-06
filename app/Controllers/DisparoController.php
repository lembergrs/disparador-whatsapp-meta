<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Models\MetaConta;
use Models\TemplateMeta;
use Models\Disparo;
use Services\MetaService;
use Models\Conversa;
use Models\ConsumoMensal;
use Models\DisparoManual;
use Models\ListaContato;
use Models\ListaContatoItem;
use Services\ControlePlanoService;
use Services\DisparoManualQueueService;

class DisparoController extends Controller
{
    private $metaModel;

    private $templateModel;





    public function __construct()
    {
        Auth::cliente();

        $this->metaModel =
            new MetaConta();

        $this->templateModel =
            new TemplateMeta();
    }






    public function index()
    {
        $usuario =
            Auth::usuario();





        $contas =
            $this->metaModel
            ->listarPorCliente(
                $usuario['CLI_ID']
            );





        $templates =
            $this->templateModel
            ->listarAprovadosParaEnvioPorCliente(
                $usuario['CLI_ID']
            );





        $listaModel = new ListaContato();
        $listaItemModel = new ListaContatoItem();

        $listas = $listaModel->listarPorCliente(
            $usuario['CLI_ID']
        );

        foreach($listas as &$lista){
            $contatosLista = $listaItemModel->listarContatos(
                $lista['LST_ID']
            );

            $lista['contatos'] = array_map(function($contato){
                return [
                    'nome' => $contato['CON_Nome'] ?? '',
                    'telefone' => $contato['CON_Telefone'] ?? ''
                ];
            }, $contatosLista);
        }
        unset($lista);





        $this->view(
            'disparos/index',
            [

                'titulo' => 'Disparador',

                'contas' => $contas,

                'templates' => $templates,

                'listas' => $listas

            ]
        );
    }


    public function historico()
    {
        $usuario = Auth::usuario();
        $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
        $model = new DisparoManual();

        $perPage = (int) ($_GET['per_page'] ?? 10);
        $permitidos = [10, 20, 50];

        if(!in_array($perPage, $permitidos, true)){
            $perPage = 10;
        }

        $pagina = max(1, (int) ($_GET['page'] ?? 1));
        $filtros = $this->filtrosHistoricoDisparo();
        $total = $model->contarLotesCliente($clienteId, $filtros);
        $totalPaginas = max(1, (int) ceil($total / $perPage));
        $pagina = min($pagina, $totalPaginas);
        $offset = ($pagina - 1) * $perPage;

        $lotes = $model->listarLotesClientePaginado(
            $clienteId,
            $filtros,
            $perPage,
            $offset
        );

        $templates = $this->templateModel->listarPorCliente($clienteId);

        $this->view(
            'disparos/historico',
            [
                'titulo' => 'Histórico de Disparos',
                'lotes' => $lotes,
                'templates' => $templates,
                'filtros' => $filtros,
                'pagina' => $pagina,
                'perPage' => $perPage,
                'total' => $total,
                'totalPaginas' => $totalPaginas,
                'statusLoteOpcoes' => $this->statusLoteOpcoes()
            ]
        );
    }

    public function detalhesLoteAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        try{
            $usuario = Auth::usuario();
            $clienteId = (int) ($usuario['CLI_ID'] ?? 0);
            $loteId = (int) ($_GET['lote_id'] ?? 0);

            if($loteId <= 0){
                http_response_code(400);
                echo json_encode(['sucesso' => false, 'erro' => 'Lote não informado.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $model = new DisparoManual();
            $lote = $model->buscarLoteDetalhadoCliente($loteId, $clienteId);

            if(!$lote){
                http_response_code(404);
                echo json_encode(['sucesso' => false, 'erro' => 'Lote não encontrado.'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $itens = $model->listarItensCliente($loteId, $clienteId);
            $itensSeguros = array_map(function($item){
                return $this->itemSeguroHistorico($item);
            }, $itens);

            echo json_encode([
                'sucesso' => true,
                'lote' => $this->loteSeguroHistorico($lote),
                'resumo' => $this->resumoItensHistorico($itensSeguros),
                'itens' => $itensSeguros
            ], JSON_UNESCAPED_UNICODE);
        }catch(\Throwable $e){
            http_response_code(500);
            echo json_encode(['sucesso' => false, 'erro' => 'Não foi possível carregar os detalhes do lote.'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function filtrosHistoricoDisparo()
    {
        $statusPermitidos = array_keys($this->statusLoteOpcoes());
        $status = (string) ($_GET['status'] ?? '');

        if(!in_array($status, $statusPermitidos, true)){
            $status = '';
        }

        $dataInicial = $this->dataFiltroHistorico($_GET['data_inicial'] ?? '');
        $dataFinal = $this->dataFiltroHistorico($_GET['data_final'] ?? '');

        return [
            'data_inicial' => $dataInicial,
            'data_final' => $dataFinal,
            'status' => $status,
            'template' => max(0, (int) ($_GET['template'] ?? 0)),
            'numero' => preg_replace('/\D/', '', (string) ($_GET['numero'] ?? ''))
        ];
    }

    private function dataFiltroHistorico($data)
    {
        $data = trim((string) $data);

        if($data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)){
            return '';
        }

        return $data;
    }

    private function statusLoteOpcoes()
    {
        return [
            'pendente' => 'Na fila',
            'processando' => 'Processando',
            'concluido' => 'Concluído',
            'concluido_com_erros' => 'Concluído com erros',
            'erro' => 'Erro'
        ];
    }

    private function loteSeguroHistorico(array $lote)
    {
        return [
            'id' => (int) ($lote['DML_ID'] ?? 0),
            'status' => (string) ($lote['DML_Status'] ?? ''),
            'status_label' => $this->statusLoteOpcoes()[$lote['DML_Status'] ?? ''] ?? ucfirst((string) ($lote['DML_Status'] ?? '-')),
            'conta' => (string) ($lote['MTA_Nome'] ?? '-'),
            'template' => (string) ($lote['TMP_Nome'] ?? '-'),
            'data_cadastro' => (string) ($lote['DML_DataCadastro'] ?? ''),
            'data_atualizacao' => (string) ($lote['DML_DataAtualizacao'] ?? ''),
            'data_conclusao' => (string) ($lote['DML_DataConclusao'] ?? '')
        ];
    }

    private function itemSeguroHistorico(array $item)
    {
        $status = (string) ($item['DMI_Status'] ?? '');
        $erro = (string) ($item['DMI_Erro'] ?? '');

        return [
            'numero' => $this->formatarTelefoneDisparo($item['DMI_Numero'] ?? ''),
            'status' => $status,
            'status_label' => $this->statusItemDisparo($status),
            'status_badge' => $this->badgeItemDisparo($status),
            'mensagem' => $this->mensagemItemDisparo($status, $erro),
            'data_cadastro' => $this->formatarDataHoraDisparo($item['DMI_DataCadastro'] ?? ''),
            'data_envio' => $this->formatarDataHoraDisparo($item['DMI_DataEnvio'] ?? ''),
            'data_atualizacao' => $this->formatarDataHoraDisparo($item['DMI_DataAtualizacao'] ?? ''),
            'erro' => $this->erroAmigavelDisparo($erro)
        ];
    }


    private function resumoItensHistorico(array $itens)
    {
        $resumo = [
            'total' => count($itens),
            'enviadas' => 0,
            'entregues' => 0,
            'lidas' => 0,
            'erros' => 0,
            'progresso' => 0
        ];

        foreach($itens as $item){
            $status = $item['status'] ?? '';

            if(in_array($status, ['aguardando_confirmacao', 'enviado', 'sent', 'delivered', 'entregue', 'read', 'lido'], true)){
                $resumo['enviadas']++;
            }

            if(in_array($status, ['delivered', 'entregue', 'read', 'lido'], true)){
                $resumo['entregues']++;
            }

            if(in_array($status, ['read', 'lido'], true)){
                $resumo['lidas']++;
            }

            if(in_array($status, ['failed', 'erro'], true)){
                $resumo['erros']++;
            }
        }

        if($resumo['total'] > 0){
            $concluidas = $resumo['enviadas'] + $resumo['erros'];
            $resumo['progresso'] = min(100, (int) round(($concluidas / $resumo['total']) * 100));
        }

        return $resumo;
    }

    private function badgeItemDisparo($status)
    {
        $mapa = [
            'pendente' => 'warning',
            'processando' => 'info',
            'aguardando_confirmacao' => 'primary',
            'enviado' => 'success',
            'sent' => 'success',
            'delivered' => 'success',
            'entregue' => 'success',
            'read' => 'purple',
            'lido' => 'purple',
            'failed' => 'danger',
            'erro' => 'danger',
            'cancelado' => 'secondary'
        ];

        return $mapa[$status] ?? 'secondary';
    }

    private function formatarTelefoneDisparo($numero)
    {
        $numero = preg_replace('/\D/', '', (string) $numero);

        if(strlen($numero) > 11 && substr($numero, 0, 2) === '55'){
            $numero = substr($numero, 2);
        }

        if(strlen($numero) === 11){
            return sprintf('(%s) %s-%s', substr($numero, 0, 2), substr($numero, 2, 5), substr($numero, 7));
        }

        if(strlen($numero) === 10){
            return sprintf('(%s) %s-%s', substr($numero, 0, 2), substr($numero, 2, 4), substr($numero, 6));
        }

        return $numero !== '' ? $numero : '-';
    }

    private function formatarDataHoraDisparo($data)
    {
        if(empty($data)){
            return '-';
        }

        $timestamp = strtotime((string) $data);

        if(!$timestamp){
            return '-';
        }

        return date('d/m/Y H:i:s', $timestamp);
    }

    private function erroAmigavelDisparo($erro)
    {
        $erro = strtolower((string) $erro);

        if(trim($erro) === ''){
            return '-';
        }

        if(str_contains($erro, 'invalid') || str_contains($erro, 'inválido') || str_contains($erro, 'invalido')){
            return 'Telefone inválido.';
        }

        if(str_contains($erro, 'whatsapp') && (str_contains($erro, 'not') || str_contains($erro, 'sem'))){
            return 'Número sem WhatsApp.';
        }

        if(str_contains($erro, 'block') || str_contains($erro, 'bloque')){
            return 'Telefone bloqueado.';
        }

        if(str_contains($erro, 'tempor') || str_contains($erro, 'timeout') || str_contains($erro, 'rate')){
            return 'Erro temporário da Meta.';
        }

        return 'Não foi possível enviar para este número.';
    }

    private function statusItemDisparo($status)
    {
        $mapa = [
            'pendente' => 'Na fila',
            'processando' => 'Enviando',
            'aguardando_confirmacao' => 'Aguardando confirmação',
            'enviado' => 'Enviado',
            'sent' => 'Enviado',
            'delivered' => 'Entregue',
            'entregue' => 'Entregue',
            'read' => 'Lido',
            'lido' => 'Lido',
            'failed' => 'Erro',
            'erro' => 'Erro'
        ];

        return $mapa[$status] ?? ucfirst($status ?: '-');
    }

    private function mensagemItemDisparo($status, $erro = '')
    {
        if(in_array($status, ['failed', 'erro'], true)){
            return $erro !== '' ? $this->resumirTextoSeguro($erro, 160) : 'Falha no envio.';
        }

        $mensagens = [
            'pendente' => 'Aguardando processamento.',
            'processando' => 'Envio em processamento.',
            'aguardando_confirmacao' => 'Mensagem enviada para a Meta; aguardando confirmação.',
            'enviado' => 'Mensagem enviada.',
            'sent' => 'Mensagem enviada.',
            'delivered' => 'Mensagem entregue.',
            'entregue' => 'Mensagem entregue.',
            'read' => 'Mensagem lida.',
            'lido' => 'Mensagem lida.'
        ];

        return $mensagens[$status] ?? 'Status atualizado.';
    }

    private function resumirTextoSeguro($valor, $limite)
    {
        $valor = preg_replace('/(access_token|token|authorization|bearer|senha|password|secret)[^,;\s]*/i', '$1=***', (string) $valor);
        $valor = preg_replace('/[\r\n\t]+/', ' ', $valor);
        $valor = trim($valor);

        if(function_exists('mb_substr')){
            return mb_substr($valor, 0, $limite);
        }

        return substr($valor, 0, $limite);
    }

    private function resumirRetornoMeta($retorno)
    {
        $retorno = $this->resumirTextoSeguro($retorno, 600);

        if($retorno === ''){
            return '';
        }

        $decodificado = json_decode($retorno, true);

        if(is_array($decodificado)){
            $resumo = [];

            foreach(['messaging_product', 'message_status', 'status', 'error'] as $chave){
                if(array_key_exists($chave, $decodificado)){
                    $resumo[$chave] = $decodificado[$chave];
                }
            }

            if(!empty($resumo)){
                return $this->resumirTextoSeguro(json_encode($resumo, JSON_UNESCAPED_UNICODE), 600);
            }
        }

        return $retorno;
    }

    public function enviar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $template =
            $this->templateModel
            ->buscarAprovadoParaEnvioPorCliente(
                (int) ($_POST['template'] ?? 0),
                $usuario['CLI_ID']
            );

        if(!$template || (int) $template['MTA_ID'] !== (int) ($_POST['meta'] ?? 0)){
            \Core\Session::flash('error', 'Template inválido.');
            $this->redirect('disparo');
        }

        $meta =
            new MetaService(
                (int) ($_POST['meta'] ?? 0),
                $usuario['CLI_ID']
            );

        $entradaNumeros =
            $_POST['numeros']
            ?? '';

        $numeros =
            preg_split(
                '/[\r\n,;]+/',
                $entradaNumeros
            );

        $numerosLimpos = [];

        foreach($numeros as $numero){

            $numero =
                preg_replace(
                    '/\D/',
                    '',
                    $numero
                );

            if(empty($numero)){
                continue;
            }

            if(substr($numero, 0, 2) != '55'){
                $numero = '55' . $numero;
            }

            $numerosLimpos[] = $numero;
        }

        $numerosLimpos =
            array_unique($numerosLimpos);

        if(empty($numerosLimpos)){

            \Core\Session::flash(
                'error',
                'Informe pelo menos um número válido.'
            );

            $this->redirect(
                'disparo'
            );

            return;
        }

        $totalEnviados = 0;
        $totalErros = 0;
        $erros = [];

        foreach($numerosLimpos as $numero){

            $response =
                $meta->enviarTemplate(

                    $numero,

                    $template,

                    $_POST['variaveis']
                    ?? []

                );

            $messageId = null;
            $status = 'erro';

            if(isset($response['messages'][0]['id'])){

                $messageId =
                    $response['messages'][0]['id'];

                $status = 'aguardando_confirmacao';

                $consumo =
                    new ConsumoMensal();

                $consumo->registrarMensagem(
                    $usuario['CLI_ID']
                );

                $controlePlano =
                    new ControlePlanoService();

                $controlePlano->registrarUso(
                    $usuario['CLI_ID']
                );

                $totalEnviados++;

            }else{

                $totalErros++;

                $erros[] =
                    $numero
                    . ': '
                    . (
                        $response['error']['message']
                        ??
                        'Erro ao enviar mensagem'
                    );
            }

            $disparo =
                new Disparo();

            $disparo->salvar([

                'cliente' =>
                    $usuario['CLI_ID'],

                'meta' =>
                    $_POST['meta'],

                'template_id' =>
                    $_POST['template'],

                'numero' =>
                    $numero,

                'template' =>
                    $template['TMP_Nome'],

                'variaveis' =>
                    $_POST['variaveis']
                    ?? [],

                'message_id' =>
                    $messageId,

                'status' =>
                    $status,

                'retorno' =>
                    $response

            ]);

            $conversaModel =
                new Conversa();

            $conversaId =
                $conversaModel->buscarOuCriar(
                    $usuario['CLI_ID'],
                    $_POST['meta'],
                    $numero,
                    null
                );

            $conversaModel->salvarMensagem([

                'conversa_id' =>
                    $conversaId,

                'direcao' =>
                    'enviada',

                'tipo' =>
                    'template',

                'texto' =>
                    $template['TMP_Nome'],

                'message_id' =>
                    $messageId,

                'status' =>
                    $status,

                'retorno' =>
                    $response,

                'data_mensagem' =>
                    date('Y-m-d H:i:s')

            ]);

        }

        if($totalErros == 0){

            \Core\Session::flash(
                'success',
                'Envio concluído. '
                . $totalEnviados
                . ' mensagem(ns) aceita(s) para processamento.'
            );

        }else{

            \Core\Session::flash(
                'error',
                'Envio concluído com erros. Aceitas para processamento: '
                . $totalEnviados
                . ' | Erros: '
                . $totalErros
                . '. '
                . implode(' | ', $erros)
            );
        }

        $this->redirect(
            'disparo'
        );
    }

    private function validarCsrfAjaxSilencioso()
    {
        return \Core\Csrf::validarPost();
    }

    public function statusAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        $token =
            $_POST['csrf_token']
            ?? $_GET['csrf_token']
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if(!\Core\Csrf::validar($token)){
            http_response_code(403);
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Token de segurança inválido.'
            ]);
            return;
        }

        $usuario = Auth::usuario();

        $messageIds =
            $_POST['message_ids']
            ?? $_GET['message_ids']
            ?? [];

        if(is_string($messageIds)){
            $messageIds = explode(',', $messageIds);
        }

        if(!is_array($messageIds)){
            $messageIds = [];
        }

        $disparo = new Disparo();

        echo json_encode([
            'sucesso' => true,
            'statuses' => $disparo->buscarPorMessageIds(
                $usuario['CLI_ID'],
                $messageIds
            )
        ], JSON_UNESCAPED_UNICODE);
    }

    private function extrairVariaveisTemplate($template)
    {
        $componentes = json_decode(
            $template['TMP_Componentes'] ?? '[]',
            true
        );

        if(!is_array($componentes)){
            return [];
        }

        $variaveis = [];

        $coletarVariaveis = function($texto) use (&$variaveis){
            preg_match_all(
                '/{{(.*?)}}/',
                $texto,
                $matches
            );

            foreach(($matches[1] ?? []) as $variavel){

                $variavel = trim($variavel);

                if(
                    $variavel !== ''
                    &&
                    !in_array($variavel, $variaveis, true)
                ){
                    $variaveis[] = $variavel;
                }
            }
        };

        foreach($componentes as $componente){

            if(!empty($componente['text'])){
                $coletarVariaveis($componente['text']);
            }

            if(
                ($componente['type'] ?? '') == 'BUTTONS'
                &&
                !empty($componente['buttons'])
                &&
                is_array($componente['buttons'])
            ){
                foreach($componente['buttons'] as $botao){
                    if(!empty($botao['url'])){
                        $coletarVariaveis($botao['url']);
                    }
                }
            }
        }

        $todasNumericas = !empty($variaveis);

        foreach($variaveis as $variavel){
            if(!is_numeric($variavel)){
                $todasNumericas = false;
                break;
            }
        }

        if($todasNumericas){
            usort($variaveis, function($a, $b){
                return (int) $a <=> (int) $b;
            });
        }

        return $variaveis;
    }

    private function normalizarVariaveisDisparo($variaveisRecebidas, $variaveisTemplate)
    {
        if(!is_array($variaveisRecebidas)){
            $variaveisRecebidas = [];
        }

        $normalizadas = [];

        foreach($variaveisTemplate as $indice => $variavel){

            $valor = $variaveisRecebidas[$variavel]
                ?? $variaveisRecebidas[(string) ($indice + 1)]
                ?? $variaveisRecebidas[$indice]
                ?? null;

            if($valor === null || trim((string) $valor) === ''){
                throw new \Exception(
                    'Informe o valor da variável {{' . $variavel . '}}.'
                );
            }

            $normalizadas[$variavel] = trim((string) $valor);
        }

        return $normalizadas;
    }

    private function formatarNumero($numero)
    {
        $numero = preg_replace('/\D/', '', $numero);

        if(substr($numero, 0, 2) == '55'){
            $numero = substr($numero, 2);
        }

        if(strlen($numero) == 11){
            return '(' . substr($numero, 0, 2) . ') '
                . substr($numero, 2, 5)
                . '-'
                . substr($numero, 7);
        }

        if(strlen($numero) == 10){
            return '(' . substr($numero, 0, 2) . ') '
                . substr($numero, 2, 4)
                . '-'
                . substr($numero, 6);
        }

        return $numero;
    }

    private function extrairErroMeta($response)
    {
        if(!is_array($response)){
            return 'Erro ao enviar mensagem';
        }

        if(!empty($response['error']['message'])){
            return $response['error']['message'];
        }

        if(!empty($response['error']['error_user_msg'])){
            return $response['error']['error_user_msg'];
        }

        if(!empty($response['message'])){
            return $response['message'];
        }

        return 'Erro ao enviar mensagem';
    }

    private function processarEnvioManualDestino(
        $usuario,
        $template,
        $metaId,
        $numeroEntrada,
        $variaveisRecebidas,
        $variaveisTemplate,
        $meta = null,
        $disparo = null,
        $conversaModel = null,
        $consumo = null,
        $controlePlano = null
    ){
        $numero = preg_replace('/\D/', '', $numeroEntrada ?? '');

        if($numero == ''){
            throw new \Exception('Número de destino não informado.');
        }

        if(substr($numero, 0, 2) != '55'){
            $numero = '55' . $numero;
        }

        $variaveisEnvio =
            $this->normalizarVariaveisDisparo(
                $variaveisRecebidas,
                $variaveisTemplate
            );

        $meta = $meta ?: new \Services\MetaService(
            (int) $metaId,
            $usuario['CLI_ID']
        );

        $response =
            $meta->enviarTemplate(
                $numero,
                $template,
                $variaveisEnvio
            );

        $messageId = null;
        $status = 'erro';

        if(isset($response['messages'][0]['id'])){

            $messageId =
                $response['messages'][0]['id'];

            $status = 'aguardando_confirmacao';

            $consumo = $consumo ?: new ConsumoMensal();
            $consumo->registrarMensagem(
                $usuario['CLI_ID']
            );

            $controlePlano = $controlePlano ?: new ControlePlanoService();
            $controlePlano->registrarUso(
                $usuario['CLI_ID']
            );
        }

        $disparo = $disparo ?: new \Models\Disparo();

        $disparo->salvar([
            'cliente' => $usuario['CLI_ID'],
            'meta' => $metaId,
            'template_id' => $template['TMP_ID'],
            'numero' => $numero,
            'template' => $template['TMP_Nome'],
            'variaveis' => $variaveisEnvio,
            'message_id' => $messageId,
            'status' => $status,
            'retorno' => $response
        ]);

        $conversaModel = $conversaModel ?: new Conversa();

        $conversaId =
            $conversaModel->buscarOuCriar(
                $usuario['CLI_ID'],
                $metaId,
                $numero,
                null
            );

        $conversaModel->salvarMensagem([
            'conversa_id' => $conversaId,
            'direcao' => 'enviada',
            'tipo' => 'template',
            'texto' => $template['TMP_Nome'],
            'message_id' => $messageId,
            'status' => $status,
            'retorno' => $response,
            'data_mensagem' => date('Y-m-d H:i:s')
        ]);

        if($status == 'aguardando_confirmacao'){
            return [
                'sucesso' => true,
                'status' => 'aguardando_confirmacao',
                'numero' => $numero,
                'numero_formatado' => $this->formatarNumero($numero),
                'mensagem' => 'Aguardando confirmação da Meta',
                'message_id' => $messageId,
                'retorno' => $response
            ];
        }

        return [
            'sucesso' => false,
            'status' => 'erro',
            'numero' => $numero,
            'numero_formatado' => $this->formatarNumero($numero),
            'erro' => $this->extrairErroMeta($response),
            'retorno' => $response
        ];
    }

    private function pausaEntreEnviosManual()
    {
        $enviosPorSegundo = defined('WHATSAPP_ENVIOS_POR_SEGUNDO')
            ? (int) WHATSAPP_ENVIOS_POR_SEGUNDO
            : 5;

        if($enviosPorSegundo <= 0){
            $enviosPorSegundo = 1;
        }

        usleep((int) ceil(1000000 / $enviosPorSegundo));
    }

    public function enviarAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        try{

            $usuario =
                Auth::usuario();

            if(!$this->validarCsrfAjaxSilencioso()){
                http_response_code(403);
                echo json_encode(['sucesso' => false, 'erro' => 'Token de segurança inválido.']);
                return;
            }

            $template =
                $this->templateModel
                ->buscarAprovadoParaEnvioPorCliente(
                    (int) ($_POST['template'] ?? 0),
                    $usuario['CLI_ID']
                );

            if(!$template || (int) $template['MTA_ID'] !== (int) ($_POST['meta'] ?? 0)){
                throw new \Exception('Template não encontrado.');
            }

            $numero =
                preg_replace(
                    '/\D/',
                    '',
                    $_POST['numero']
                    ?? ''
                );

            if($numero == ''){
                throw new \Exception('Número de destino não informado.');
            }

            if(substr($numero, 0, 2) != '55'){
                $numero = '55' . $numero;
            }

            $variaveisTemplate =
                $this->extrairVariaveisTemplate(
                    $template
                );

            $variaveisEnvio =
                $this->normalizarVariaveisDisparo(
                    $_POST['variaveis'] ?? [],
                    $variaveisTemplate
                );

            $meta =
                new \Services\MetaService(
                    (int) ($_POST['meta'] ?? 0),
                    $usuario['CLI_ID']
                );

            $response =
                $meta->enviarTemplate(
                    $numero,
                    $template,
                    $variaveisEnvio
                );

            $messageId = null;
            $status = 'erro';

            if(isset($response['messages'][0]['id'])){

                $messageId =
                    $response['messages'][0]['id'];

                $status = 'aguardando_confirmacao';

                $consumo =
                    new ConsumoMensal();

                $consumo->registrarMensagem(
                    $usuario['CLI_ID']
                );

                $controlePlano =
                    new ControlePlanoService();

                $controlePlano->registrarUso(
                    $usuario['CLI_ID']
                );
            }

            $disparo =
                new \Models\Disparo();

            $disparo->salvar([

                'cliente' =>
                    $usuario['CLI_ID'],

                'meta' =>
                    $_POST['meta'],

                'template_id' =>
                    $_POST['template'],

                'numero' =>
                    $numero,

                'template' =>
                    $template['TMP_Nome'],

                'variaveis' =>
                    $variaveisEnvio,

                'message_id' =>
                    $messageId,

                'status' =>
                    $status,

                'retorno' =>
                    $response

            ]);

            $conversaModel =
                new Conversa();

            $conversaId =
                $conversaModel->buscarOuCriar(
                    $usuario['CLI_ID'],
                    $_POST['meta'],
                    $numero,
                    null
                );

            $conversaModel->salvarMensagem([

                'conversa_id' =>
                    $conversaId,

                'direcao' =>
                    'enviada',

                'tipo' =>
                    'template',

                'texto' =>
                    $template['TMP_Nome'],

                'message_id' =>
                    $messageId,

                'status' =>
                    $status,

                'retorno' =>
                    $response,

                'data_mensagem' =>
                    date('Y-m-d H:i:s')

            ]);

            if($status == 'aguardando_confirmacao'){

                echo json_encode([
                    'sucesso' => true,
                    'status' => 'aguardando_confirmacao',
                    'numero' => $numero,
                    'numero_formatado' => $this->formatarNumero($numero),
                    'mensagem' => 'Aguardando confirmação da Meta',
                    'message_id' => $messageId,
                    'retorno' => $response
                ]);

                return;
            }

            echo json_encode([
                'sucesso' => false,
                'status' => 'erro',
                'numero' => $numero,
                'numero_formatado' => $this->formatarNumero($numero),
                'erro' => $this->extrairErroMeta($response),
                'retorno' => $response
            ]);

        }catch(\Exception $e){

            echo json_encode([
                'sucesso' => false,
                'status' => 'erro',
                'numero' => $_POST['numero'] ?? null,
                'numero_formatado' => $this->formatarNumero($_POST['numero'] ?? ''),
                'erro' => $e->getMessage(),
                'retorno' => [
                    'exception' => $e->getMessage()
                ]
            ]);

        }
    }

    public function enviarLoteAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        try{

            $usuario = Auth::usuario();

            if(!$this->validarCsrfAjaxSilencioso()){
                http_response_code(403);
                echo json_encode(['sucesso' => false, 'erro' => 'Token de segurança inválido.']);
                return;
            }

            $metaId = (int) ($_POST['meta'] ?? 0);

            $template =
                $this->templateModel
                ->buscarAprovadoParaEnvioPorCliente(
                    (int) ($_POST['template'] ?? 0),
                    $usuario['CLI_ID']
                );

            if(!$template || (int) $template['MTA_ID'] !== $metaId){
                throw new \Exception('Template não encontrado.');
            }

            $destinosJson = $_POST['destinos_json'] ?? '[]';
            $destinos = json_decode($destinosJson, true);

            if(!is_array($destinos)){
                throw new \Exception('Lote de destinos inválido.');
            }

            $destinos = array_slice($destinos, 0, 10);

            if(empty($destinos)){
                throw new \Exception('Nenhum destino informado para o lote.');
            }

            $variaveisTemplate =
                $this->extrairVariaveisTemplate(
                    $template
                );

            $meta = new \Services\MetaService(
                $metaId,
                $usuario['CLI_ID']
            );

            $disparo = new \Models\Disparo();
            $conversaModel = new Conversa();
            $consumo = new ConsumoMensal();
            $controlePlano = new ControlePlanoService();

            $resultados = [];
            $totalDestinos = count($destinos);

            foreach($destinos as $indice => $destino){

                try{

                    $variaveisRecebidas = [];

                    if(isset($destino['variaveis']) && is_array($destino['variaveis'])){
                        $variaveisRecebidas = $destino['variaveis'];
                    }

                    $resultados[] = $this->processarEnvioManualDestino(
                        $usuario,
                        $template,
                        $metaId,
                        $destino['numero'] ?? '',
                        $variaveisRecebidas,
                        $variaveisTemplate,
                        $meta,
                        $disparo,
                        $conversaModel,
                        $consumo,
                        $controlePlano
                    );

                }catch(\Exception $e){

                    $numero = $destino['numero'] ?? null;

                    $resultados[] = [
                        'sucesso' => false,
                        'status' => 'erro',
                        'numero' => $numero,
                        'numero_formatado' => $this->formatarNumero($numero ?? ''),
                        'erro' => $e->getMessage(),
                        'retorno' => [
                            'exception' => $e->getMessage()
                        ]
                    ];
                }

                if($indice < ($totalDestinos - 1)){
                    $this->pausaEntreEnviosManual();
                }
            }

            echo json_encode([
                'sucesso' => true,
                'resultados' => $resultados
            ], JSON_UNESCAPED_UNICODE);

        }catch(\Exception $e){

            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function normalizarTelefoneDisparoManual($telefone)
    {
        $numero = preg_replace('/\D/', '', (string) $telefone);

        if($numero === ''){
            return '';
        }

        if(substr($numero, 0, 2) !== '55'){
            $numero = '55' . $numero;
        }

        return preg_match('/^55\d{10,11}$/', $numero) ? $numero : '';
    }


    public function criarLoteAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        try{
            $usuario = Auth::usuario();

            if(!$this->validarCsrfAjaxSilencioso()){
                http_response_code(403);
                echo json_encode(['sucesso' => false, 'erro' => 'Token de segurança inválido.']);
                return;
            }

            $metaId = (int) ($_POST['meta'] ?? 0);
            $templateId = (int) ($_POST['template'] ?? 0);

            $template = $this->templateModel->buscarAprovadoParaEnvioPorCliente(
                $templateId,
                $usuario['CLI_ID']
            );

            if(!$template || (int) $template['MTA_ID'] !== $metaId){
                throw new \Exception('Template não encontrado.');
            }

            $destinos = json_decode($_POST['destinos_json'] ?? '[]', true);

            if(!is_array($destinos)){
                throw new \Exception('Destinos manuais inválidos.');
            }

            $listaId = (int) ($_POST['lista_id'] ?? 0);
            $variaveisTemplate = $this->extrairVariaveisTemplate($template);
            $model = new DisparoManual();
            $itens = [];
            $numerosUnicos = [];

            if($listaId > 0){
                $listaModel = new ListaContato();
                $lista = $listaModel->buscar($listaId, $usuario['CLI_ID']);

                if(!$lista || ($lista['LST_Ativo'] ?? 'S') !== 'S'){
                    throw new \Exception('Lista de contatos não encontrada para este cliente.');
                }

                if(!empty($variaveisTemplate)){
                    throw new \Exception('Este template possui variáveis. Nesta etapa, selecione uma lista apenas com templates sem variáveis ou informe os números manualmente com as variáveis necessárias.');
                }

                $contatosLista = (new ListaContatoItem())->listarContatos($listaId);

                foreach($contatosLista as $contato){
                    $numero = $this->normalizarTelefoneDisparoManual($contato['CON_Telefone'] ?? '');

                    if($numero === '' || isset($numerosUnicos[$numero])){
                        continue;
                    }

                    $numerosUnicos[$numero] = true;
                    $itens[] = [
                        'numero' => $numero,
                        'variaveis' => []
                    ];
                }
            }

            foreach($destinos as $destino){
                $numero = $this->normalizarTelefoneDisparoManual($destino['numero'] ?? '');

                if($numero === ''){
                    throw new \Exception('Número de destino manual inválido.');
                }

                if(isset($numerosUnicos[$numero])){
                    continue;
                }

                $variaveisRecebidas = [];

                if(isset($destino['variaveis']) && is_array($destino['variaveis'])){
                    $variaveisRecebidas = $destino['variaveis'];
                }

                $variaveisEnvio = $this->normalizarVariaveisDisparo(
                    $variaveisRecebidas,
                    $variaveisTemplate
                );

                $numerosUnicos[$numero] = true;
                $itens[] = [
                    'numero' => $numero,
                    'variaveis' => $variaveisEnvio
                ];
            }

            if(empty($itens)){
                if($listaId > 0){
                    throw new \Exception('A lista selecionada não possui contatos com telefone válido. Informe números manualmente ou escolha outra lista.');
                }

                throw new \Exception('Informe pelo menos um destino válido.');
            }

            $loteId = $model->criarLote(
                $usuario['CLI_ID'],
                $metaId,
                $templateId,
                count($itens)
            );

            foreach($itens as $item){
                $model->adicionarItem(
                    $loteId,
                    $usuario['CLI_ID'],
                    $item['numero'],
                    $item['variaveis']
                );
            }

            echo json_encode([
                'sucesso' => true,
                'lote_id' => $loteId,
                'total' => count($itens)
            ], JSON_UNESCAPED_UNICODE);

        }catch(\Exception $e){
            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }



    private function liberarSessaoParaPollingAjax()
    {
        if(session_status() === PHP_SESSION_ACTIVE){
            session_write_close();
        }
    }

    public function processarLoteAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        try{
            if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
                http_response_code(405);
                echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
                return;
            }

            $usuario = Auth::usuario();

            if(!$this->validarCsrfAjaxSilencioso()){
                http_response_code(403);
                echo json_encode(['sucesso' => false, 'erro' => 'Token de segurança inválido.']);
                return;
            }

            $loteId = (int) ($_POST['lote_id'] ?? 0);

            if($loteId <= 0){
                throw new \Exception('Lote não informado.');
            }

            $model = new DisparoManual();
            $lote = $model->buscarLoteCliente($loteId, $usuario['CLI_ID']);

            if(!$lote){
                throw new \Exception('Lote não encontrado.');
            }

            if(!in_array($lote['DML_Status'], ['pendente', 'processando'], true)){
                $itens = $model->listarItensCliente($loteId, $usuario['CLI_ID']);

                echo json_encode([
                    'sucesso' => true,
                    'processou' => false,
                    'mensagem' => 'Lote sem pendências para processamento.',
                    'lote' => $lote,
                    'itens' => $itens
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if(session_status() === PHP_SESSION_ACTIVE){
                session_write_close();
            }

            $service = new DisparoManualQueueService(false);
            $resumo = $service->processarLote(
                (int) $usuario['CLI_ID'],
                $loteId,
                5,
                'ajax'
            );

            $itens = $model->listarItensCliente($loteId, $usuario['CLI_ID']);

            echo json_encode([
                'sucesso' => true,
                'processou' => true,
                'resumo' => $resumo,
                'lote' => $resumo['lote'] ?? $lote,
                'itens' => $itens
            ], JSON_UNESCAPED_UNICODE);

        }catch(\Exception $e){
            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function statusLoteAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        try{
            $usuario = Auth::usuario();

            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

            if(!\Core\Csrf::validar($token)){
                http_response_code(403);
                echo json_encode(['sucesso' => false, 'erro' => 'Token de segurança inválido.']);
                return;
            }

            $loteId = (int) ($_POST['lote_id'] ?? $_GET['lote_id'] ?? 0);

            if($loteId <= 0){
                throw new \Exception('Lote não informado.');
            }

            $model = new DisparoManual();
            $lote = $model->buscarLoteCliente($loteId, $usuario['CLI_ID']);

            if(!$lote){
                throw new \Exception('Lote não encontrado.');
            }

            $itens = $model->listarItensCliente($loteId, $usuario['CLI_ID']);

            echo json_encode([
                'sucesso' => true,
                'lote' => $lote,
                'itens' => $itens
            ], JSON_UNESCAPED_UNICODE);

        }catch(\Exception $e){
            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

}
