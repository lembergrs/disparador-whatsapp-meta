<?php

/**
 * Homologação manual - Sprint 4A
 *
 * Executar:
 * php tests/manual/asaas_desconto_sandbox.php
 *
 * NÃO versionar este arquivo se não quiser mantê-lo no projeto.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__, 2);
$envFile = $root . DIRECTORY_SEPARATOR . '.env';

if (!file_exists($envFile)) {
    exit("ERRO: .env não encontrado em: {$envFile}\n");
}

/**
 * Loader simples de .env.
 */
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
    $linha = trim($linha);

    if ($linha === '' || strpos($linha, '#') === 0) {
        continue;
    }

    $pos = strpos($linha, '=');

    if ($pos === false) {
        continue;
    }

    $nome  = trim(substr($linha, 0, $pos));
    $valor = trim(substr($linha, $pos + 1));

    if (
        (substr($valor, 0, 1) === '"' && substr($valor, -1) === '"') ||
        (substr($valor, 0, 1) === "'" && substr($valor, -1) === "'")
    ) {
        $valor = substr($valor, 1, -1);
    }

    if (getenv($nome) === false) {
        putenv($nome . '=' . $valor);
        $_ENV[$nome] = $valor;
    }
}

$ambiente = strtolower(trim((string) getenv('ASAAS_ENV')));
$apiKey   = trim((string) getenv('ASAAS_API_KEY'));

/**
 * TRAVAS DE SEGURANÇA
 */
if ($ambiente !== 'sandbox') {
    exit("ERRO DE SEGURANÇA: ASAAS_ENV precisa ser exatamente 'sandbox'.\n");
}

if ($apiKey === '') {
    exit("ERRO: ASAAS_API_KEY não encontrada.\n");
}

/**
 * A documentação atual do Asaas usa este endpoint para sandbox.
 */
$baseUrl = 'https://api-sandbox.asaas.com/v3';

if (strpos($baseUrl, 'api-sandbox.asaas.com') === false) {
    exit("ERRO DE SEGURANÇA: URL não é sandbox.\n");
}

echo "=============================================\n";
echo "HOMOLOGAÇÃO ASAAS - SPRINT 4A\n";
echo "=============================================\n";
echo "Ambiente: sandbox\n";
echo "Endpoint: {$baseUrl}\n";
echo "API key: configurada (não exibida)\n\n";


/**
 * Requisição HTTP.
 */
function asaasRequest($method, $endpoint, $body = null)
{
    global $baseUrl, $apiKey;

    $url = $baseUrl . $endpoint;

    if (strpos($url, 'api-sandbox.asaas.com') === false) {
        throw new RuntimeException(
            'Bloqueio de segurança: tentativa de chamada fora do sandbox.'
        );
    }

    $ch = curl_init($url);

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Disparador.net-Sprint4A-Sandbox',
        'access_token: ' . $apiKey
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30
    ]);

    /**
     * GET obrigatoriamente sem body.
     */
    if ($body !== null && strtoupper($method) !== 'GET') {
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($body, JSON_UNESCAPED_UNICODE)
        );
    }

    $resposta = curl_exec($ch);

    if ($resposta === false) {
        $erro = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException('Erro cURL: ' . $erro);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($resposta, true);

    if (!is_array($json)) {
        $json = [
            '_raw' => $resposta
        ];
    }

    return [
        'http_code' => $httpCode,
        'body'      => $json
    ];
}


/**
 * Mostra somente campos úteis.
 */
function mostrar($titulo, array $dados)
{
    echo "\n=============================================\n";
    echo $titulo . "\n";
    echo "=============================================\n";

    echo json_encode(
        $dados,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );

    echo "\n";
}


/**
 * Gera CPF matematicamente válido apenas para sandbox.
 */
function gerarCpfTeste()
{
    $numeros = [];

    for ($i = 0; $i < 9; $i++) {
        $numeros[] = random_int(0, 9);
    }

    // Evitar todos os dígitos iguais.
    if (count(array_unique($numeros)) === 1) {
        $numeros[8] = ($numeros[8] + 1) % 10;
    }

    $soma = 0;

    for ($i = 0; $i < 9; $i++) {
        $soma += $numeros[$i] * (10 - $i);
    }

    $resto = $soma % 11;
    $d1 = ($resto < 2) ? 0 : 11 - $resto;

    $numeros[] = $d1;

    $soma = 0;

    for ($i = 0; $i < 10; $i++) {
        $soma += $numeros[$i] * (11 - $i);
    }

    $resto = $soma % 11;
    $d2 = ($resto < 2) ? 0 : 11 - $resto;

    $numeros[] = $d2;

    return implode('', $numeros);
}


/**
 * Sanitiza resposta antes de exibir.
 */
function resumoPagamento(array $p)
{
    $campos = [
        'id',
        'customer',
        'billingType',
        'status',
        'value',
        'netValue',
        'originalValue',
        'dueDate',
        'paymentDate',
        'clientPaymentDate',
        'confirmedDate',
        'invoiceUrl',
        'bankSlipUrl',
        'identificationField',
        'externalReference',
        'discount',
        'interest',
        'fine'
    ];

    $resultado = [];

    foreach ($campos as $campo) {
        if (array_key_exists($campo, $p)) {
            $resultado[$campo] = $p[$campo];
        }
    }

    return $resultado;
}


try {

    /**
     * ---------------------------------------------------------
     * 1. CLIENTE SANDBOX
     * ---------------------------------------------------------
     */

    echo "Criando cliente fictício no sandbox...\n";

    $cpfTeste = gerarCpfTeste();

    $cliente = asaasRequest(
        'POST',
        '/customers',
        [
            'name'                 => 'Homologacao Disparador Sprint 4A',
            'cpfCnpj'              => $cpfTeste,
            'externalReference'    => 'homologacao_sprint4a_' . time(),
            'notificationDisabled' => true
        ]
    );

    mostrar(
        '1. CRIAÇÃO DO CLIENTE',
        [
            'http_code' => $cliente['http_code'],
            'id'        => $cliente['body']['id'] ?? null,
            'errors'    => $cliente['body']['errors'] ?? null
        ]
    );

    if ($cliente['http_code'] < 200 || $cliente['http_code'] >= 300) {
        throw new RuntimeException('Falha ao criar cliente sandbox.');
    }

    $customerId = $cliente['body']['id'] ?? null;

    if (!$customerId) {
        throw new RuntimeException('Asaas não retornou customer ID.');
    }


    /**
     * ---------------------------------------------------------
     * 2. COBRANÇA R$ 100 COM R$ 15 FIXO
     * ---------------------------------------------------------
     */

    $dueDate = date('Y-m-d');

    echo "\nCriando cobrança R$ 100,00 com desconto FIXED R$ 15,00...\n";

    $cobranca = asaasRequest(
        'POST',
        '/payments',
        [
            'customer'          => $customerId,
            'billingType'       => 'UNDEFINED',
            'dueDate'           => $dueDate,
            'value'             => 100.00,
            'description'       => 'Homologacao Sprint 4A - desconto fixo',
            'externalReference' => 'homologacao_4a_' . time(),

            'discount' => [
                'value'            => 15.00,
                'type'             => 'FIXED',
                'dueDateLimitDays' => 0
            ]
        ]
    );

    mostrar(
        '2. CRIAÇÃO DA COBRANÇA',
        [
            'http_code' => $cobranca['http_code'],
            'payment'   => resumoPagamento($cobranca['body']),
            'errors'    => $cobranca['body']['errors'] ?? null
        ]
    );

    if ($cobranca['http_code'] < 200 || $cobranca['http_code'] >= 300) {
        throw new RuntimeException('Falha ao criar cobrança sandbox.');
    }

    $paymentId = $cobranca['body']['id'] ?? null;

    if (!$paymentId) {
        throw new RuntimeException('Asaas não retornou payment ID.');
    }


    /**
     * ---------------------------------------------------------
     * 3. CONSULTA DA COBRANÇA
     * ---------------------------------------------------------
     */

    $consulta = asaasRequest(
        'GET',
        '/payments/' . rawurlencode($paymentId)
    );

    mostrar(
        '3. CONSULTA DA COBRANÇA',
        [
            'http_code' => $consulta['http_code'],
            'payment'   => resumoPagamento($consulta['body']),
            'errors'    => $consulta['body']['errors'] ?? null
        ]
    );


    /**
     * ---------------------------------------------------------
     * 4. PIX QR CODE
     * ---------------------------------------------------------
     */

    $pix = asaasRequest(
        'GET',
        '/payments/' . rawurlencode($paymentId) . '/pixQrCode'
    );

    mostrar(
        '4. PIX QR CODE',
        [
            'http_code'      => $pix['http_code'],
            'payload_presente' => !empty($pix['body']['payload']),
            'expirationDate' => $pix['body']['expirationDate'] ?? null,
            'errors'         => $pix['body']['errors'] ?? null
        ]
    );


    /**
     * ---------------------------------------------------------
     * 5. DECODIFICAR PIX NA DATA DO VENCIMENTO
     *
     * Esse teste é muito importante porque o próprio Asaas
     * calcula o valor financeiro aplicável na data informada.
     * ---------------------------------------------------------
     */

    if (!empty($pix['body']['payload'])) {

        $decodeHoje = asaasRequest(
            'POST',
            '/pix/qrCodes/decode',
            [
                'payload'             => $pix['body']['payload'],
                'expectedPaymentDate' => $dueDate
            ]
        );

        mostrar(
            '5. PIX - VALOR NA DATA DO VENCIMENTO',
            [
                'http_code' => $decodeHoje['http_code'],
                'response'  => $decodeHoje['body']
            ]
        );


        /**
         * -----------------------------------------------------
         * 6. SIMULAR DATA APÓS VENCIMENTO
         * -----------------------------------------------------
         */

        $dataAtrasada = date(
            'Y-m-d',
            strtotime($dueDate . ' +1 day')
        );

        $decodeAtrasado = asaasRequest(
            'POST',
            '/pix/qrCodes/decode',
            [
                'payload'             => $pix['body']['payload'],
                'expectedPaymentDate' => $dataAtrasada
            ]
        );

        mostrar(
            '6. PIX - VALOR SIMULADO 1 DIA APÓS VENCIMENTO',
            [
                'expectedPaymentDate' => $dataAtrasada,
                'http_code'           => $decodeAtrasado['http_code'],
                'response'            => $decodeAtrasado['body']
            ]
        );
    }


    /**
     * ---------------------------------------------------------
     * 7. TESTE DOS 50% DA PRIMEIRA MENSALIDADE
     * ---------------------------------------------------------
     */

    $cobranca50 = asaasRequest(
        'POST',
        '/payments',
        [
            'customer'          => $customerId,
            'billingType'       => 'UNDEFINED',
            'dueDate'           => $dueDate,
            'value'             => 100.00,
            'description'       => 'Homologacao Sprint 4A - desconto inicial 50',
            'externalReference' => 'homologacao_4a_50_' . time(),

            'discount' => [
                'value'            => 50.00,
                'type'             => 'FIXED',
                'dueDateLimitDays' => 0
            ]
        ]
    );

    mostrar(
        '7. TESTE DESCONTO INICIAL R$ 50,00',
        [
            'http_code' => $cobranca50['http_code'],
            'payment'   => resumoPagamento($cobranca50['body']),
            'errors'    => $cobranca50['body']['errors'] ?? null
        ]
    );


    echo "\n=============================================\n";
    echo "TESTE FINALIZADO\n";
    echo "=============================================\n";
    echo "Envie para o ChatGPT a saída deste script.\n";
    echo "A API key nunca é exibida pelo script.\n";

} catch (Throwable $e) {

    echo "\n=============================================\n";
    echo "ERRO\n";
    echo "=============================================\n";
    echo $e->getMessage() . "\n";

    exit(1);
}