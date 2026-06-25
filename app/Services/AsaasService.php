<?php

namespace Services;

class AsaasService
{
    private $baseUrl;

    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) ASAAS_API_BASE_URL, '/');
        $this->apiKey = (string) ASAAS_API_KEY;
    }

    public function request($method, $endpoint, $payload = null)
    {
        $method = strtoupper((string) $method);
        $endpoint = '/' . ltrim((string) $endpoint, '/');

        if(!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)){
            return $this->respostaPadronizada(
                false,
                0,
                $endpoint,
                $method,
                null,
                'Método HTTP não suportado.'
            );
        }

        if(trim($this->apiKey) === ''){
            return $this->respostaPadronizada(
                false,
                0,
                $endpoint,
                $method,
                null,
                'API Key do Asaas não configurada.'
            );
        }

        $curl = curl_init();

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Disparador.net/1.0 (contato@disparador.net)',
            'access_token: ' . $this->apiKey
        ];

        $options = [
            CURLOPT_URL => $this->baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ];

        if($payload !== null && in_array($method, ['POST', 'PUT'], true)){
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $erroCurl = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

echo '<pre>';

echo "URL:\n";
echo $url . "\n\n";

echo "HTTP CODE:\n";
echo $httpCode . "\n\n";

echo "CURL ERROR:\n";
echo curl_error($curl) . "\n\n"; // use o nome correto da variável

echo "RESPONSE:\n";
echo $response . "\n\n";

echo "PAYLOAD:\n";
print_r($payload);

die();

        curl_close($curl);

        $responseDecodificado = null;

        if($response !== false && $response !== ''){
            $responseDecodificado = json_decode($response, true);

            if(json_last_error() !== JSON_ERROR_NONE){
                $responseDecodificado = $response;
            }
        }

        return $this->respostaPadronizada(
            $erroCurl === '' && $httpCode >= 200 && $httpCode < 300,
            $httpCode,
            $endpoint,
            $method,
            $responseDecodificado,
            $this->erroResumido($erroCurl, $responseDecodificado, $httpCode)
        );
    }

    private function respostaPadronizada($sucesso, $httpCode, $endpoint, $method, $response, $erro)
    {
        return [
            'sucesso' => $sucesso,
            'http_code' => $httpCode,
            'endpoint' => $endpoint,
            'method' => $method,
            'response' => $response,
            'erro' => $erro
        ];
    }

    private function erroResumido($erroCurl, $response, $httpCode)
    {
        if($erroCurl !== ''){
            return 'Falha de comunicação com o Asaas.';
        }

        if($httpCode >= 200 && $httpCode < 300){
            return null;
        }

        if(is_array($response) && !empty($response['errors'][0]['description'])){
            return $response['errors'][0]['description'];
        }

        if(is_array($response) && !empty($response['message'])){
            return $response['message'];
        }

        return 'Falha ao processar requisição no Asaas.';
    }


    public function criarOuAtualizarCliente($cliente)
    {
        $customerId = trim((string) ($cliente['CLI_ProviderCustomerId'] ?? ''));
        $payload = $this->montarPayloadCliente($cliente);

        if($customerId !== ''){
            return $this->request('POST', '/customers/' . rawurlencode($customerId), $payload);
        }

        return $this->request('POST', '/customers', $payload);
    }

    public function criarCobranca($cliente, $cobranca)
    {
        $payload = [
            'customer' => $cliente['CLI_ProviderCustomerId'],
            'billingType' => 'UNDEFINED',
            'dueDate' => $cobranca['COB_DataVencimento'],
            'value' => (float) $cobranca['COB_Valor'],
            'description' => $cobranca['descricao'] ?? 'Mensalidade Disparador.net',
            'externalReference' => 'cobranca_' . $cobranca['COB_ID']
        ];

        return $this->request('POST', '/payments', $payload);
    }

    public function consultarCobranca($providerPaymentId)
    {
        return $this->request('GET', '/payments/' . rawurlencode($providerPaymentId));
    }

    public function buscarPixQrCode($providerPaymentId)
    {
        return $this->request('GET', '/payments/' . rawurlencode($providerPaymentId) . '/pixQrCode');
    }

    private function montarPayloadCliente($cliente)
    {
        $documento = preg_replace('/\D/', '', (string) ($cliente['CLI_CPF_CNPJ'] ?? ''));
        $telefone = preg_replace('/\D/', '', (string) ($cliente['CLI_Telefone'] ?? ''));
        $nome = trim((string) ($cliente['CLI_RazaoSocial'] ?? ''));

        if($nome === ''){
            $nome = trim((string) ($cliente['CLI_Nome'] ?? ''));
        }

        $payload = [
            'name' => $nome,
            'email' => trim((string) ($cliente['CLI_Email'] ?? '')),
            'externalReference' => 'cliente_' . ($cliente['CLI_ID'] ?? '')
        ];

        if($documento !== ''){
            $payload['cpfCnpj'] = $documento;
        }

        if($telefone !== ''){
            if(strlen($telefone) >= 10){
                $payload['mobilePhone'] = $telefone;
            }else{
                $payload['phone'] = $telefone;
            }
        }

        return array_filter($payload, function($valor){
            return $valor !== null && $valor !== '';
        });
    }

    public function testarConexao()
    {
        return $this->request('GET', '/myAccount');
    }
}
