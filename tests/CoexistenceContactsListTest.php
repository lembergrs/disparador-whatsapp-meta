<?php

require_once dirname(__DIR__) . '/app/Services/TelefoneService.php';
require_once dirname(__DIR__) . '/app/Services/MetaWebhookStateSyncService.php';

use Services\MetaWebhookStateSyncService;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

class FakeContatoCoexistence
{
    public $salvos = [];

    public function buscarPorTelefone($clienteId, $telefone)
    {
        if($telefone === '5541999991111'){
            return ['CON_ID'=>10, 'CON_Telefone'=>$telefone];
        }
        if($telefone === '5541977773333'){
            return ['CON_ID'=>30, 'CON_Telefone'=>$telefone];
        }
        return false;
    }

    public function salvar($dados)
    {
        $this->salvos[] = $dados;
        return 20;
    }
}

class FakeListaCoexistence
{
    public $clientes = [];

    public function obterOuCriarListaWhatsapp($clienteId)
    {
        $this->clientes[] = $clienteId;
        return 99;
    }
}

class FakeListaItemCoexistence
{
    public $vinculos = [];

    public function contatoExisteNaLista($listaId, $contatoId)
    {
        return false;
    }

    public function contatoExisteEmOutraListaDoCliente($clienteId, $contatoId, $listaIgnoradaId = 0)
    {
        return $clienteId === 7 && $contatoId === 30 && $listaIgnoradaId === 99;
    }

    public function adicionar($listaId, $contatoId)
    {
        $this->vinculos[] = [$listaId, $contatoId];
        return true;
    }
}

$contato = new FakeContatoCoexistence();
$lista = new FakeListaCoexistence();
$item = new FakeListaItemCoexistence();

$service = new MetaWebhookStateSyncService($contato, null, $lista, $item);
$resultado = $service->processar([
    'metadata'=>['phone_number_id'=>'123'],
    'state_sync'=>[
        [
            'type'=>'contact',
            'action'=>'update',
            'contact'=>[
                'phone_number'=>'5541999991111',
                'full_name'=>'Contato Existente Sem Lista'
            ]
        ],
        [
            'type'=>'contact',
            'action'=>'add',
            'contact'=>[
                'phone_number'=>'5541988882222',
                'full_name'=>'Contato Novo'
            ]
        ],
        [
            'type'=>'contact',
            'action'=>'update',
            'contact'=>[
                'phone_number'=>'5541977773333',
                'full_name'=>'Contato Já Organizado'
            ]
        ]
    ]
], [
    'CLI_ID'=>7,
    'MTA_ID'=>3
]);

$assert($resultado['existentes'] === 2, 'Contatos já existentes devem ser reconhecidos.');
$assert($resultado['criadas'] === 1, 'Contato novo do state sync deve ser criado.');
$assert($resultado['vinculadas'] === 2, 'Somente contato novo e existente sem outra lista devem entrar na lista automática.');
$assert(count($lista->clientes) === 1, 'Lista automática deve ser resolvida apenas uma vez por payload.');
$assert($lista->clientes[0] === 7, 'Lista automática deve pertencer ao CLI_ID correto.');
$assert($item->vinculos === [[99,10],[99,20]], 'Contato já organizado em outra lista não deve ser revinculado à lista automática.');
$assert(($contato->salvos[0]['dados_json'] ?? '') === '{"origem":"whatsapp_business_app"}', 'Contato criado pelo Coexistence deve preservar a origem.');

$root = dirname(__DIR__);
$listaModel = file_get_contents($root . '/app/Models/ListaContato.php');
$listaItemModel = file_get_contents($root . '/app/Models/ListaContatoItem.php');
$migration = file_get_contents($root . '/database/migrations/20260911_backfill_coexistence_contacts_list.sql');

$assert(strpos($listaModel, "'Contatos do WhatsApp'") !== false, 'Lista automática deve ter nome estável.');
$assert(strpos($listaModel, 'obterOuCriarListaWhatsapp') !== false, 'Model deve obter ou criar a lista automática.');
$assert(strpos($listaItemModel, 'contatoExisteEmOutraListaDoCliente') !== false, 'Model deve consultar se o contato já foi organizado em outra lista.');
$assert(strpos($migration, "'whatsapp_business_app'") !== false, 'Backfill deve limitar-se aos contatos sincronizados pelo WhatsApp Business.');
$assert(strpos($migration, 'INSERT IGNORE INTO lista_contatos_itens') !== false, 'Backfill deve ser idempotente ao criar vínculos.');

echo "Coexistence contacts list checks passed\n";
