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
            return [
                'sucesso' => false,
                'http_code' => 0,
                'response' => null,
                'erro' => 'Método HTTP não suportado.'
            ];
        }

        if(trim($this->apiKey) === ''){
            return [
                'sucesso' => false,
                'http_code' => 0,
                'response' => null,
                'erro' => 'API Key do Asaas não configurada.'
            ];
        }

        $curl = curl_init();

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
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

        curl_close($curl);

        $responseDecodificado = null;

        if($response !== false && $response !== ''){
            $responseDecodificado = json_decode($response, true);

            if(json_last_error() !== JSON_ERROR_NONE){
                $responseDecodificado = $response;
            }
        }

        return [
            'sucesso' => $erroCurl === '' && $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $responseDecodificado,
            'erro' => $erroCurl !== '' ? 'Falha de comunicação com o Asaas.' : null
        ];
    }

    public function testarConexao()
    {
        return $this->request('GET', '/myAccount');
    }
}
