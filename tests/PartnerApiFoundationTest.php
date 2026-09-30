<?php

function partnerApiAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/20260930_create_partner_api_foundation.sql');
$model = file_get_contents($root . '/app/Models/ParceiroApi.php');
$auth = file_get_contents($root . '/app/Services/PartnerApiAuthService.php');
$controller = file_get_contents($root . '/app/Controllers/ApiV1Controller.php');
$router = file_get_contents($root . '/app/Core/Router.php');

partnerApiAssert(strpos($migration, 'CREATE TABLE parceiros_api') !== false, 'migration deve criar parceiros');
partnerApiAssert(strpos($migration, 'CREATE TABLE parceiro_api_keys') !== false, 'migration deve criar API keys');
partnerApiAssert(strpos($migration, 'CREATE TABLE parceiro_clientes') !== false, 'migration deve criar vínculos');
partnerApiAssert(strpos($migration, 'PAK_Hash CHAR(64)') !== false, 'API key deve ser persistida por hash');

partnerApiAssert(strpos($auth, "preg_match('/^Bearer") !== false, 'autenticação deve exigir Bearer');
partnerApiAssert(strpos($model, "hash('sha256'") !== false, 'token recebido deve ser convertido para hash');
partnerApiAssert(strpos($model, "m.MTA_ID = pc.MTA_ID") !== false, 'canal deve respeitar MTA_ID autorizado');
partnerApiAssert(strpos($model, "m.CLI_ID = pc.CLI_ID") !== false, 'canal deve respeitar CLI_ID autorizado');

partnerApiAssert(strpos($router, "^api/v1") !== false, 'router deve reconhecer API v1');
partnerApiAssert(strpos($controller, 'public function status()') !== false, 'API deve expor status autenticado');
partnerApiAssert(strpos($controller, "'unauthorized'") !== false, 'API deve responder 401 para chave inválida');
partnerApiAssert(strpos($controller, 'MTA_Token') === false, 'endpoint público não pode expor token Meta');

echo "Partner API foundation static tests passed\n";
