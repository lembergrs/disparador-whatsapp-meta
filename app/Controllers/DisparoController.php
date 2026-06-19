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
use Services\ControlePlanoService;

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

}