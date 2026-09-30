<?php

namespace Services;

use Models\ParceiroApi;

class PartnerApiAuthService
{
    private $model;

    public function __construct($model = null)
    {
        $this->model = $model ?: new ParceiroApi();
    }

    public function autenticar()
    {
        $authorization = $this->authorizationHeader();

        if(!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)){
            return null;
        }

        $token = trim($matches[1]);
        if($token === '' || strlen($token) < 32){
            return null;
        }

        return $this->model->autenticarPorToken($token);
    }

    private function authorizationHeader()
    {
        if(!empty($_SERVER['HTTP_AUTHORIZATION'])){
            return trim((string) $_SERVER['HTTP_AUTHORIZATION']);
        }

        if(function_exists('getallheaders')){
            $headers = getallheaders();
            foreach($headers as $nome => $valor){
                if(strtolower((string)$nome) === 'authorization'){
                    return trim((string)$valor);
                }
            }
        }

        return '';
    }
}
