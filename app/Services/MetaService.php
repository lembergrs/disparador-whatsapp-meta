<?php

namespace Services;

use Helpers\UrlHelper;

use Core\Database;
use Exception;
use PDO;

class MetaService
{
    private $db;

    private $conta;





    public function __construct($metaId, $clienteId = null)
    {
        $this->db =
            Database::getInstance();





        $whereCliente = '';
        $params = [$metaId];

        if($clienteId !== null){
            $whereCliente = ' AND CLI_ID = ? ';
            $params[] = $clienteId;
        }

        $sql = $this->db->prepare("

            SELECT *

            FROM meta_contas

            WHERE MTA_ID = ?
            AND MTA_Ativo = 'S'
            {$whereCliente}

        ");

        $sql->execute($params);





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
        $componentesOriginais = [];
        $mapaVariaveis = [];
        $contadorVariaveis = 1;

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        if(!empty($dados['header_tipo'])){

            $headerTipo = strtoupper((string) $dados['header_tipo']);

            if($headerTipo == 'TEXT'
                && !empty($dados['header'])){

                $headerOriginal = $dados['header'];
                $headerTexto = $this->normalizarTextoVariaveisMeta(
                    $headerOriginal,
                    $mapaVariaveis,
                    $contadorVariaveis
                );

                $headerComponent = [

                    'type'   => 'HEADER',
                    'format' => 'TEXT',
                    'text'   => $headerTexto

                ];

                $variaveisHeader = $this->extrairVariaveisTextoMeta($headerOriginal);

                if(!empty($variaveisHeader)){
                    $headerComponent['example'] = [
                        'header_text' => $this->montarExemplosVariaveisMeta(
                            $variaveisHeader,
                            $dados['exemplos'] ?? []
                        )
                    ];
                }

                $components[] = $headerComponent;

                $componentesOriginais[] = [
                    'type' => 'HEADER',
                    'format' => 'TEXT',
                    'text' => $headerOriginal
                ];

            }elseif(in_array($headerTipo, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)){

                $handle = $dados['header_media_handle'] ?? null;

                if(!$handle && !empty($_FILES['header_media'])){
                    $mediaService = new MetaMediaService(
                        $this->conta['MTA_ID'],
                        $this->conta['CLI_ID'] ?? null
                    );

                    $upload = $mediaService->uploadTemplateHandle(
                        $_FILES['header_media'],
                        $headerTipo
                    );

                    $handle = $upload['handle'] ?? null;
                    $dados['header_media_nome'] = $upload['nome_original'] ?? null;
                }

                if(!$handle){
                    return [
                        'error' => [
                            'message' => 'Envie um arquivo de exemplo para o cabeçalho de mídia.'
                        ]
                    ];
                }

                $components[] = [
                    'type' => 'HEADER',
                    'format' => $headerTipo,
                    'example' => [
                        'header_handle' => [$handle]
                    ]
                ];

                $componentesOriginais[] = [
                    'type' => 'HEADER',
                    'format' => $headerTipo,
                    'example' => [
                        'header_handle' => [$handle]
                    ],
                    'media_name' => $dados['header_media_nome'] ?? ($_FILES['header_media']['name'] ?? null)
                ];

            }else{

                $components[] = [

                    'type'   => 'HEADER',
                    'format' => $headerTipo

                ];

                $componentesOriginais[] = [
                    'type'   => 'HEADER',
                    'format' => $headerTipo
                ];

            }

        }

        /*
        |--------------------------------------------------------------------------
        | BODY
        |--------------------------------------------------------------------------
        */

        $bodyOriginal = $dados['body'];
        $bodyTexto = $this->normalizarTextoVariaveisMeta(
            $bodyOriginal,
            $mapaVariaveis,
            $contadorVariaveis
        );

        $bodyComponent = [

            'type' => 'BODY',

            'text' => $bodyTexto

        ];

        $variaveisBody = $this->extrairVariaveisTextoMeta($bodyOriginal);

        if(!empty($variaveisBody)){

            $bodyComponent['example'] = [

                'body_text' => [
                    $this->montarExemplosVariaveisMeta(
                        $variaveisBody,
                        $dados['exemplos'] ?? []
                    )
                ]

            ];

        }

        $components[] = $bodyComponent;

        $componentesOriginais[] = [
            'type' => 'BODY',
            'text' => $bodyOriginal
        ];

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

            $componentesOriginais[] = [
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
            $buttonsOriginais = [];
            $totalUrl = 0;
            $totalTelefone = 0;

            foreach($dados['botoes'] as $botao){

                $tipo = $botao['tipo'] ?? '';
                $texto = trim($botao['texto'] ?? '');
                $valor = trim($botao['valor'] ?? '');

                if($texto == ''){
                    continue;
                }

                if(mb_strlen($texto) > 25){
                    return [
                        'error' => [
                            'message' => 'O texto dos botões deve ter no máximo 25 caracteres.'
                        ]
                    ];
                }

                switch($tipo){

                    case 'QUICK_REPLY':

                        $buttons[] = [

                            'type' => 'QUICK_REPLY',
                            'text' => $texto

                        ];

                        $buttonsOriginais[] = [
                            'type' => 'QUICK_REPLY',
                            'text' => $texto
                        ];

                    break;

                    case 'URL':

                        $totalUrl++;

                        if($valor == '' || !preg_match('/^https?:\/\//i', $valor)){
                            return [
                                'error' => [
                                    'message' => 'Informe uma URL válida para todos os botões de URL.'
                                ]
                            ];
                        }

                        $urlOriginal = $valor;
                        $urlMeta = $this->normalizarTextoVariaveisMeta(
                            $urlOriginal,
                            $mapaVariaveis,
                            $contadorVariaveis
                        );

                        $botaoUrl = [

                            'type' => 'URL',
                            'text' => $texto,
                            'url'  => $urlMeta

                        ];

                        $variaveisUrl = $this->extrairVariaveisTextoMeta($urlOriginal);

                        if(!empty($variaveisUrl)){
                            $botaoUrl['example'] = [
                                $this->montarExemploUrlMeta(
                                    $urlOriginal,
                                    $dados['exemplos'] ?? []
                                )
                            ];
                        }

                        $buttons[] = $botaoUrl;

                        $buttonsOriginais[] = [
                            'type' => 'URL',
                            'text' => $texto,
                            'url' => $urlOriginal
                        ];

                    break;

                    case 'PHONE_NUMBER':

                        $totalTelefone++;

                        if($valor == ''){
                            return [
                                'error' => [
                                    'message' => 'Informe o telefone para todos os botões de telefone.'
                                ]
                            ];
                        }

                        $buttons[] = [

                            'type' => 'PHONE_NUMBER',
                            'text' => $texto,
                            'phone_number' => preg_replace('/[^0-9+]/', '', $valor)

                        ];

                        $buttonsOriginais[] = [
                            'type' => 'PHONE_NUMBER',
                            'text' => $texto,
                            'phone_number' => preg_replace('/[^0-9+]/', '', $valor)
                        ];

                    break;

                }

                if(count($buttons) > 10){
                    return [
                        'error' => [
                            'message' => 'A Meta permite no máximo 10 botões por template.'
                        ]
                    ];
                }

                if($totalUrl > 2){
                    return [
                        'error' => [
                            'message' => 'A Meta permite no máximo 2 botões de URL.'
                        ]
                    ];
                }

                if($totalTelefone > 1){
                    return [
                        'error' => [
                            'message' => 'A Meta permite no máximo 1 botão de telefone.'
                        ]
                    ];
                }

            }

            if(count($buttons) > 0){

                $components[] = [

                    'type' => 'BUTTONS',

                    'buttons' => $buttons

                ];

                $componentesOriginais[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $buttonsOriginais
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




        if(!empty($mapaVariaveis)){
            $componentesOriginais[] = [
                'type' => 'VARIABLE_MAPPING',
                'mapping' => $mapaVariaveis
            ];
        }



        $payload = [

            'name' => $nomeTemplate,

            'category' => $dados['categoria'],

            'language' => $dados['idioma'],

            'components' => $components

        ];





        $this->registrarLogTemplateMeta('request', [
            'cli_id' => $this->conta['CLI_ID'] ?? null,
            'waba_id' => $this->conta['MTA_WabaId'] ?? null,
            'template' => $nomeTemplate,
            'categoria' => $dados['categoria'] ?? null,
            'idioma' => $dados['idioma'] ?? null,
            'payload' => $payload
        ]);



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

        $curlError = curl_error($curl);

        $httpCode = curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );

        curl_close($curl);



        $retorno = json_decode(
            $response,
            true
        );

        if(!is_array($retorno)){
            $retorno = [
                'error' => [
                    'message' => $curlError ?: 'Resposta inválida da Meta.'
                ],
                'raw_response' => $response
            ];
        }

        $retorno['http_code'] = $httpCode;
        $retorno['raw_response'] = $response;

        if($curlError){
            $retorno['curl_error'] = $curlError;
        }

        $retorno['template_local'] = [
            'name' => $nomeTemplate,
            'category' => $dados['categoria'],
            'language' => $dados['idioma'],
            'status' => $retorno['status'] ?? 'PENDING',
            'components' => !empty($componentesOriginais) ? $componentesOriginais : $components
        ];

        $this->registrarLogTemplateMeta('response', [
            'cli_id' => $this->conta['CLI_ID'] ?? null,
            'waba_id' => $this->conta['MTA_WabaId'] ?? null,
            'template' => $nomeTemplate,
            'categoria' => $dados['categoria'] ?? null,
            'idioma' => $dados['idioma'] ?? null,
            'http_code' => $httpCode,
            'response' => $retorno,
            'error' => $this->extrairErroLogTemplateMeta($retorno)
        ]);

        if($httpCode >= 400 || !empty($retorno['error'])){
            $this->registrarErroCriacaoTemplateMeta($url, $httpCode, $payload, $retorno);
        }

        return $retorno;
    }


    public function enviarTemplate(
        $numero,
        $template,
        $variaveis = [],
        $midiaHeader = null
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





        $variaveis = $this->ordenarVariaveisEnvioTemplate(
            $variaveis,
            $template
        );

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






        $components = [];

        $headerMidiaComponent = $this->montarHeaderMidiaEnvio(
            $template,
            $midiaHeader
        );

        if($headerMidiaComponent){
            $components[] = $headerMidiaComponent;
        }

        if(!empty($parameters)){
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters
            ];
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

                ]

            ]

        ];

        if(!empty($components)){
            $payload['template']['components'] = $components;
        }



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




    private function montarHeaderMidiaEnvio($template, $midiaHeader)
    {
        $tipo = strtoupper((string) ($template['TMP_HeaderTipo'] ?? ''));
        $urlTemplate = (string) ($template['TMP_HeaderMidiaUrlExemplo'] ?? '');
        $documentoNomeTemplate = (string) ($template['TMP_HeaderDocumentoNome'] ?? '');

        if($tipo === '' || $urlTemplate === '' || $documentoNomeTemplate === ''){
            $componentes = json_decode($template['TMP_Componentes'] ?? '[]', true);

            if(is_array($componentes)){
                foreach($componentes as $componente){
                    if(($componente['type'] ?? '') == 'HEADER'){
                        if($tipo === ''){
                            $tipo = strtoupper((string) ($componente['format'] ?? ''));
                        }

                        if($urlTemplate === ''){
                            $urlTemplate = (string) ($componente['media_url'] ?? '');
                        }

                        if($documentoNomeTemplate === ''){
                            $documentoNomeTemplate = (string) ($componente['media_name'] ?? '');
                        }

                        break;
                    }
                }
            }
        }

        $tipo = strtolower($tipo);

        if(!in_array($tipo, ['image', 'video', 'document'], true)){
            return null;
        }

        $mediaId = trim((string) ($midiaHeader['media_id'] ?? ''));
        $mediaLink = trim((string) (
            $midiaHeader['link']
            ?? $midiaHeader['url']
            ?? $urlTemplate
        ));

        if($mediaLink !== '' && strpos($mediaLink, 'http') !== 0){
            $mediaLink = rtrim(BASE_URL, '/') . '/' . ltrim($mediaLink, '/');
        }

        if($mediaId !== ''){
            $media = [
                'id' => $mediaId
            ];
        }elseif($mediaLink !== ''){
            $media = [
                'link' => $mediaLink
            ];
        }else{
            return null;
        }

        if($tipo === 'document'){
            $filename = trim((string) (
                $midiaHeader['filename']
                ?? $midiaHeader['nome']
                ?? $documentoNomeTemplate
            ));

            if($filename !== ''){
                $media['filename'] = $filename;
            }
        }

        return [
            'type' => 'header',
            'parameters' => [
                [
                    'type' => $tipo,
                    $tipo => $media
                ]
            ]
        ];
    }

        if(!in_array($tipo, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)){
            return null;
        }

        $midiaHeader = is_array($midiaHeader) ? $midiaHeader : [];
        $mediaId = (string) ($midiaHeader['media_id'] ?? '');
        $mediaLink = (string) ($midiaHeader['link'] ?? ($midiaHeader['url'] ?? $urlTemplate));
        //$mediaLink = str_replace('/public/uploads/templates/', '/uploads/templates/', $mediaLink);

        if($mediaId === '' && $mediaLink === ''){
            return null;
        }

        $chave = strtolower($tipo);
        $media = $mediaId !== ''
            ? ['id' => $mediaId]
            : ['link' => $mediaLink];

        if($tipo == 'DOCUMENT'){
            $filename = (string) ($midiaHeader['filename'] ?? $documentoNomeTemplate);

            if($filename !== ''){
                $media['filename'] = $filename;
            }
        }

        return [
            'type' => 'header',
            'parameters' => [
                [
                    'type' => $chave,
                    $chave => $media
                ]
            ]
        ];
    }




    public static function validarSintaxeVariaveisTemplate($texto)
    {
        $texto = (string) $texto;

        preg_match_all('/{{(.*?)}}/s', $texto, $matches);

        foreach(($matches[1] ?? []) as $conteudo){
            if(
                $conteudo !== trim($conteudo)
                ||
                !preg_match('/^[A-Za-z0-9_]+$/', $conteudo)
            ){
                return false;
            }
        }

        $textoSemVariaveisValidas = preg_replace(
            '/{{[A-Za-z0-9_]+}}/',
            '',
            $texto
        );

        return !preg_match('/[{}]/', $textoSemVariaveisValidas);
    }



    private function validarTextoVariaveisMeta($texto)
    {
        if(!self::validarSintaxeVariaveisTemplate($texto)){
            throw new Exception('Existe uma variável inválida no template. Use o formato {{nome}} ou {{1}}.');
        }
    }



    private function extrairVariaveisTextoMeta($texto)
    {
        $this->validarTextoVariaveisMeta($texto);

        preg_match_all('/{{\s*([A-Za-z0-9_]+)\s*}}/', $texto, $matches);

        $variaveis = [];

        foreach(($matches[1] ?? []) as $variavel){
            if(!in_array($variavel, $variaveis, true)){
                $variaveis[] = $variavel;
            }
        }

        return $variaveis;
    }



    private function normalizarTextoVariaveisMeta($texto, &$mapa, &$contador)
    {
        $this->validarTextoVariaveisMeta($texto);

        return preg_replace_callback(
            '/{{\s*([A-Za-z0-9_]+)\s*}}/',
            function($match) use (&$mapa, &$contador){
                $variavel = $match[1];

                if(!isset($mapa[$variavel])){
                    $mapa[$variavel] = ctype_digit($variavel)
                        ? (int) $variavel
                        : $contador++;

                    if(ctype_digit($variavel) && (int) $variavel >= $contador){
                        $contador = ((int) $variavel) + 1;
                    }
                }

                return '{{' . $mapa[$variavel] . '}}';
            },
            $texto
        );
    }



    private function montarExemplosVariaveisMeta($variaveis, $exemplosRecebidos)
    {
        $exemplos = [];

        foreach($variaveis as $variavel){
            $exemplos[] = $exemplosRecebidos[$variavel]
                ?? $exemplosRecebidos[(string) $variavel]
                ?? 'Exemplo';
        }

        return $exemplos;
    }



    private function montarExemploUrlMeta($url, $exemplosRecebidos)
    {
        $variaveis = $this->extrairVariaveisTextoMeta($url);

        if(empty($variaveis)){
            return 'exemplo';
        }

        $primeiraVariavel = $variaveis[0];

        return $exemplosRecebidos[$primeiraVariavel]
            ?? $exemplosRecebidos[(string) $primeiraVariavel]
            ?? 'exemplo';
    }



    private function ordenarVariaveisEnvioTemplate($variaveis, $template)
    {
        $componentes = json_decode($template['TMP_Componentes'] ?? '[]', true);

        if(!is_array($componentes)){
            return $variaveis;
        }

        $mapping = [];

        foreach($componentes as $componente){
            if(($componente['type'] ?? '') == 'VARIABLE_MAPPING'){
                $mapping = $componente['mapping'] ?? [];
                break;
            }
        }

        if(empty($mapping)){
            return $variaveis;
        }

        asort($mapping, SORT_NUMERIC);

        $ordenadas = [];

        foreach($mapping as $nome => $numero){
            if(array_key_exists($nome, $variaveis)){
                $ordenadas[(int) $numero] = $variaveis[$nome];
                continue;
            }

            if(array_key_exists((string) $numero, $variaveis)){
                $ordenadas[(int) $numero] = $variaveis[(string) $numero];
            }
        }

        ksort($ordenadas, SORT_NUMERIC);

        return !empty($ordenadas) ? array_values($ordenadas) : $variaveis;
    }




    private function registrarLogTemplateMeta($fase, $dados)
    {
        $diretorio = dirname(__DIR__, 2) . '/storage/logs';

        if(!is_dir($diretorio)){
            mkdir($diretorio, 0755, true);
        }

        $registro = [
            'data_hora' => date('Y-m-d H:i:s'),
            'fase' => $fase,
            'dados' => $this->mascararDadosSensiveisTemplateMeta($dados)
        ];

        file_put_contents(
            $diretorio . '/meta_templates.log',
            json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . PHP_EOL,
            FILE_APPEND
        );
    }



    private function registrarErroCriacaoTemplateMeta($endpoint, $httpCode, $payload, $retorno)
    {
        $diretorio = dirname(__DIR__, 2) . '/storage/logs';

        if(!is_dir($diretorio)){
            mkdir($diretorio, 0755, true);
        }

        $erro = $this->extrairErroLogTemplateMeta($retorno);

        $registro = [
            'data_hora' => date('Y-m-d H:i:s'),
            'cli_id' => $this->conta['CLI_ID'] ?? null,
            'mta_id' => $this->conta['MTA_ID'] ?? null,
            'waba_id' => $this->conta['MTA_WabaId'] ?? null,
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'payload' => $this->mascararDadosSensiveisTemplateMeta($payload),
            'response' => $this->mascararDadosSensiveisTemplateMeta($retorno),
            'message' => $erro['message'] ?? ($retorno['error']['message'] ?? null),
            'code' => $erro['code'] ?? ($retorno['error']['code'] ?? null),
            'error_subcode' => $erro['error_subcode'] ?? ($retorno['error']['error_subcode'] ?? null),
            'error_data' => $erro['error_data'] ?? ($retorno['error']['error_data'] ?? null)
        ];

        file_put_contents(
            $diretorio . '/meta-template-create.log',
            json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }



    private function mascararDadosSensiveisTemplateMeta($dados)
    {
        if(!is_array($dados)){
            return $dados;
        }

        foreach($dados as $chave => $valor){
            $chaveNormalizada = strtolower((string) $chave);

            if(
                strpos($chaveNormalizada, 'token') !== false
                ||
                strpos($chaveNormalizada, 'authorization') !== false
            ){
                $dados[$chave] = '[mascarado]';
                continue;
            }

            if(is_array($valor)){
                $dados[$chave] = $this->mascararDadosSensiveisTemplateMeta($valor);
            }
        }

        return $dados;
    }



    private function extrairErroLogTemplateMeta($retorno)
    {
        if(empty($retorno['error']) || !is_array($retorno['error'])){
            return null;
        }

        $erro = $retorno['error'];

        return [
            'message' => $erro['message'] ?? null,
            'code' => $erro['code'] ?? null,
            'error_subcode' => $erro['error_subcode'] ?? null,
            'error_data' => $erro['error_data'] ?? null,
            'fbtrace_id' => $erro['fbtrace_id'] ?? null
        ];
    }

}
