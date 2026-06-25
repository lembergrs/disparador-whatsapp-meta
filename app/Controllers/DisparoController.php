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
            ->listarPorCliente(
                $usuario['CLI_ID']
            );





        $this->view(
            'disparos/index',
            [

                'titulo' => 'Disparador',

                'contas' => $contas,

                'templates' => $templates

            ]
        );
    }

    public function enviar()
    {
        $this->validarCsrfPost();

        $usuario =
            Auth::usuario();

        $template =
            $this->templateModel
            ->buscarPorCliente(
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
                ->buscarPorCliente(
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
                ->buscarPorCliente(
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

            $template = $this->templateModel->buscarPorCliente(
                $templateId,
                $usuario['CLI_ID']
            );

            if(!$template || (int) $template['MTA_ID'] !== $metaId){
                throw new \Exception('Template não encontrado.');
            }

            $destinos = json_decode($_POST['destinos_json'] ?? '[]', true);

            if(!is_array($destinos) || empty($destinos)){
                throw new \Exception('Informe pelo menos um destino válido.');
            }

            $variaveisTemplate = $this->extrairVariaveisTemplate($template);
            $model = new DisparoManual();
            $itens = [];
            $numerosUnicos = [];

            foreach($destinos as $destino){
                $numero = preg_replace('/\D/', '', $destino['numero'] ?? '');

                if($numero == ''){
                    throw new \Exception('Número de destino não informado.');
                }

                if(substr($numero, 0, 2) != '55'){
                    $numero = '55' . $numero;
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
                throw new \Exception('Nenhum destino válido para enfileirar.');
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
