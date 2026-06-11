<?php

namespace Services;

use Core\Database;
use Exception;
use PDO;

class MetaService
{
    private $db;

    private $conta;





    public function __construct($metaId)
    {
        $this->db =
            Database::getInstance();





        $sql = $this->db->prepare("

            SELECT *

            FROM meta_contas

            WHERE MTA_ID = ?
            AND MTA_Ativo = 'S'

        ");

        $sql->execute([$metaId]);





        $this->conta =
            $sql->fetch(PDO::FETCH_ASSOC);





        if(!$this->conta){

            throw new Exception(
                'Conta Meta não encontrada.'
            );

        }
    }





    public function testarConexao()
    {
        $url =

            rtrim(
                $this->conta['MTA_UrlBase'],
                '/'
            )

            . '/'

            . $this->conta['MTA_PhoneNumberId'];





        $curl = curl_init();





        curl_setopt_array($curl, [

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [

                'Authorization: Bearer '
                . $this->conta['MTA_Token']

            ]

        ]);





        $response =
            curl_exec($curl);

        $httpCode =
            curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

        curl_close($curl);





        if($httpCode == 200){

            $this->atualizarStatus(
                'conectado'
            );

            return [

                'sucesso' => true,

                'retorno' =>
                    json_decode(
                        $response,
                        true
                    )

            ];

        }






        $this->atualizarStatus(
            'erro'
        );





        return [

            'sucesso' => false,

            'retorno' =>
                json_decode(
                    $response,
                    true
                )

        ];
    }





    public function enviarTexto(
        $numero,
        $mensagem
    )
    {
        $url =

            rtrim(
                $this->conta['MTA_UrlBase'],
                '/'
            )

            . '/'

            . $this->conta['MTA_PhoneNumberId']

            . '/messages';





        $payload = [

            'messaging_product' =>
                'whatsapp',

            'to' => $numero,

            'type' => 'text',

            'text' => [

                'body' => $mensagem

            ]

        ];





        $curl = curl_init();





        curl_setopt_array($curl, [

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS =>
                json_encode($payload),

            CURLOPT_HTTPHEADER => [

                'Content-Type: application/json',

                'Authorization: Bearer '
                . $this->conta['MTA_Token']

            ]

        ]);





        $response =
            curl_exec($curl);

        $httpCode =
            curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

        curl_close($curl);





        return [

            'http_code' => $httpCode,

            'response' =>
                json_decode(
                    $response,
                    true
                )

        ];
    }





    private function atualizarStatus($status)
    {
        $sql = $this->db->prepare("

            UPDATE meta_contas

            SET

                MTA_Status = ?,

                MTA_UltimaVerificacao = NOW()

            WHERE MTA_ID = ?

        ");





        $sql->execute([

            $status,

            $this->conta['MTA_ID']

        ]);
    }

    public function buscarTemplates()
    {
        $url =

            rtrim(
                $this->conta['MTA_UrlBase'],
                '/'
            )

            . '/'

            . $this->conta['MTA_WabaId']

            . '/message_templates';





        $curl = curl_init();





        curl_setopt_array($curl, [

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [

                'Authorization: Bearer '
                . $this->conta['MTA_Token']

            ]

        ]);





        $response =
            curl_exec($curl);

        curl_close($curl);





        return json_decode(
            $response,
            true
        );
    }

    public function criarTemplate($dados)
    {
        $url =

            rtrim(
                $this->conta['MTA_UrlBase'],
                '/'
            )

            . '/'

            . $this->conta['MTA_WabaId']

            . '/message_templates';


        $components = [];

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        if(!empty($dados['header_tipo'])){

            if($dados['header_tipo'] == 'TEXT'
                && !empty($dados['header'])){

                $components[] = [

                    'type'   => 'HEADER',
                    'format' => 'TEXT',
                    'text'   => $dados['header']

                ];

            }else{

                $components[] = [

                    'type'   => 'HEADER',
                    'format' => $dados['header_tipo']

                ];

            }

        }

        /*
        |--------------------------------------------------------------------------
        | BODY
        |--------------------------------------------------------------------------
        */

        $bodyComponent = [

            'type' => 'BODY',

            'text' => $dados['body']

        ];

        preg_match_all(
            '/{{(.*?)}}/',
            $dados['body'],
            $matches
        );

        if(!empty($matches[1])){

            $exemplos = [];

            foreach($matches[1] as $var){

                $var = trim($var);

                $exemplos[] =
                    $dados['exemplos'][$var]
                    ?? 'Exemplo';

            }

            $bodyComponent['example'] = [

                'body_text' => [
                    $exemplos
                ]

            ];

        }

        $components[] = $bodyComponent;

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        if(!empty($dados['footer'])){

            $components[] = [

                'type' => 'FOOTER',
                'text' => $dados['footer']

            ];

        }

        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        if(
            isset($dados['botoes'])
            &&
            is_array($dados['botoes'])
        ){

            $buttons = [];

            foreach($dados['botoes'] as $botao){

                if(empty($botao['texto'])){
                    continue;
                }

                switch($botao['tipo']){

                    case 'QUICK_REPLY':

                        $buttons[] = [

                            'type' => 'QUICK_REPLY',
                            'text' => $botao['texto']

                        ];

                    break;

                    case 'URL':

                        $buttons[] = [

                            'type' => 'URL',
                            'text' => $botao['texto'],

                            'url'  =>
                                $botao['valor']
                                ?? ''

                        ];

                    break;

                    case 'PHONE_NUMBER':

                        $buttons[] = [

                            'type' => 'PHONE_NUMBER',
                            'text' => $botao['texto'],

                            'phone_number' =>
                                $botao['valor']
                                ?? ''

                        ];

                    break;

                }

            }

            if(count($buttons) > 0){

                $components[] = [

                    'type' => 'BUTTONS',

                    'buttons' => $buttons

                ];

            }

        }

        $nomeTemplate = strtolower($dados['nome']);

        $nomeTemplate = iconv(
            'UTF-8',
            'ASCII//TRANSLIT',
            $nomeTemplate
        );

        $nomeTemplate = preg_replace(
            '/[^a-z0-9]+/',
            '_',
            $nomeTemplate
        );

        $nomeTemplate = trim(
            $nomeTemplate,
            '_'
        );

        $nomeTemplate = preg_replace(
            '/_+/',
            '_',
            $nomeTemplate
        );




        $payload = [

            'name' => $nomeTemplate,

            'category' => $dados['categoria'],

            'language' => $dados['idioma'],

            'components' => $components

        ];





        $curl = curl_init();





        curl_setopt_array($curl, [

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS =>
                json_encode($payload),

            CURLOPT_HTTPHEADER => [

                'Authorization: Bearer '
                . $this->conta['MTA_Token'],

                'Content-Type: application/json'

            ]

        ]);





        $response =
            curl_exec($curl);

        curl_close($curl);





        return json_decode(
            $response,
            true
        );
    }


    public function enviarTemplate(
        $numero,
        $template,
        $variaveis = []
    )
    {
        $url =

            rtrim(
                $this->conta['MTA_UrlBase'],
                '/'
            )

            . '/'

            . $this->conta['MTA_PhoneNumberId']

            . '/messages';





        $parameters = [];





        foreach($variaveis as $nome => $valor){

            $parametro = [

                'type' => 'text',

                'text' => $valor

            ];

            if(
                !is_int($nome)
                &&
                !ctype_digit((string) $nome)
            ){

                $parametro['parameter_name'] =
                    (string) $nome;

            }

            $parameters[] = $parametro;

        }






        $payload = [

            'messaging_product' => 'whatsapp',

            'to' => preg_replace(
                '/\D/',
                '',
                $numero
            ),

            'type' => 'template',

            'template' => [

                'name' => $template['TMP_Nome'],

                'language' => [

                    'code' =>
                    $template['TMP_Idioma']

                ],

                'components' => [

                    [

                        'type' => 'body',

                        'parameters' => $parameters

                    ]

                ]

            ]

        ];





        $curl = curl_init();





        curl_setopt_array($curl, [

            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            //CURLOPT_SSL_VERIFYPEER => false,    //Para teste local, descomentar essa linha
            //CURLOPT_SSL_VERIFYHOST => false,    //Para teste local, descomentar essa linha
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '
                . $this->conta['MTA_Token'],
                'Content-Type: application/json'
            ]
        ]);


        $response = curl_exec($curl);

        $curlError = curl_error($curl);

        $httpCode = curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);

        if($curlError){

            return [
                'error' => [
                    'message' => $curlError
                ],
                'http_code' => $httpCode,
                'payload' => $payload
            ];

        }

        $retorno = json_decode(
            $response,
            true
        );

        if(!is_array($retorno)){
            $retorno = [
                'error' => [
                    'message' => 'Resposta inválida da Meta.'
                ]
            ];
        }

        $retorno['http_code'] = $httpCode;
        $retorno['raw_response'] = $response;
        $retorno['payload'] = $payload;

        return $retorno;
    }

}