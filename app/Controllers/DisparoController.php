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
        $usuario =
            Auth::usuario();

        $template =
            $this->templateModel
            ->buscar(
                $_POST['template']
            );

        $meta =
            new MetaService(
                $_POST['meta']
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

                $status = 'enviado';

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
                . ' mensagem(ns) enviada(s).'
            );

        }else{

            \Core\Session::flash(
                'error',
                'Envio concluído com erros. Enviadas: '
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

    public function enviarAjax()
    {
        header('Content-Type: application/json; charset=utf-8');

        try{

            $usuario =
                Auth::usuario();

            $template =
                $this->templateModel
                ->buscar(
                    $_POST['template']
                );

            $meta =
                new \Services\MetaService(
                    $_POST['meta']
                );

            $numero =
                preg_replace(
                    '/\D/',
                    '',
                    $_POST['numero']
                );

            if(substr($numero, 0, 2) != '55'){
                $numero = '55' . $numero;
            }

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

                $status = 'enviado';

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

            if($status == 'enviado'){

                echo json_encode([
                    'sucesso' => true,
                    'numero' => $numero,
                    'message_id' => $messageId
                ]);

                return;
            }

            echo json_encode([
                'sucesso' => false,
                'numero' => $numero,
                'erro' =>
                    $response['error']['message']
                    ?? 'Erro ao enviar mensagem'
            ]);

        }catch(\Exception $e){

            echo json_encode([
                'sucesso' => false,
                'erro' => $e->getMessage()
            ]);

        }
    }

}