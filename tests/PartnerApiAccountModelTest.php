<?php

function partnerAccountAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/20260930_add_partner_account_roles.sql');
$model = file_get_contents($root . '/app/Models/ParceiroApi.php');
$docs = file_get_contents($root . '/docs/partner-api-v1.md');
$policy = file_get_contents($root . '/docs/partner-api-documentation-policy.md');

partnerAccountAssert(strpos($migration, "CLI_TipoConta ENUM('cliente','cliente_partner','cliente_partner_vinculado')") !== false, 'clientes devem ter classificação explícita');
partnerAccountAssert(strpos($migration, 'ADD COLUMN CLI_ID INT NOT NULL') !== false, 'parceiro deve pertencer a um cliente partner');
partnerAccountAssert(strpos($migration, 'UK_parceiros_api_cliente') !== false, 'cliente partner deve possuir vínculo único com parceiro');

partnerAccountAssert(strpos($model, "cp.CLI_TipoConta = 'cliente_partner'") !== false, 'autenticação deve exigir conta partner');
partnerAccountAssert(strpos($model, "c.CLI_TipoConta = 'cliente_partner_vinculado'") !== false, 'canais devem pertencer a cliente partner vinculado');
partnerAccountAssert(substr_count($model, 'm.CLI_ID = pc.CLI_ID') >= 2, 'autorização deve manter isolamento CLI_ID + MTA_ID');

partnerAccountAssert(strpos($docs, 'Exemplo de requisição') !== false, 'guia deve conter exemplo de request');
partnerAccountAssert(strpos($docs, 'Exemplo de resposta') !== false, 'guia deve conter exemplo de response');
partnerAccountAssert(strpos($docs, '### Homologação') !== false, 'guia deve documentar o processo de homologação');
partnerAccountAssert(strpos($docs, 'Não existe, neste momento, um sandbox público separado') !== false, 'guia não deve prometer sandbox público inexistente');
partnerAccountAssert(strpos($policy, 'mesmo Pull Request') !== false, 'documentação deve acompanhar alterações públicas');

echo "Partner account/documentation static tests passed\n";
