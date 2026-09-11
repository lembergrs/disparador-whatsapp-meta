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

class FakeContatoRemoveCoexistence
{
    public $rows = [
        '7:5541999991111'=>['CON_ID'=>10, 'CON_Telefone'=>'5541999991111']
    ];

    public function buscarPorTelefone($clienteId, $telefone)
    {
        return $this->rows[$clienteId . ':' . $telefone] ?? false;
    }

    public function salvar($dados)
    {
        throw new RuntimeException('remove não deve criar contato');
    }
}

class FakeListaRemoveCoexistence
{
    public $buscarChamadas = [];
    public $criarChamadas = [];
    public $lista = ['LST_ID'=>99];

    public function buscarPorNome($clienteId, $nome)
    {
        $this->buscarChamadas[] = [$clienteId, $nome];
        return $this->lista;
    }

    public function obterOuCriarListaWhatsapp($clienteId)
    {
        $this->criarChamadas[] = $clienteId;
        return 99;
    }
}

class FakeListaItemRemoveCoexistence
{
    public $vinculos = [
        '99:10'=>true,
        '123:10'=>true
    ];
    public $remocoes = [];

    public function contatoExisteNaLista($listaId, $contatoId)
    {
        return !empty($this->vinculos[$listaId . ':' . $contatoId]);
    }

    public function removerContato($listaId, $contatoId)
    {
        $this->remocoes[] = [$listaId, $contatoId];
        unset($this->vinculos[$listaId . ':' . $contatoId]);
        return true;
    }

    public function adicionar($listaId, $contatoId)
    {
        $this->vinculos[$listaId . ':' . $contatoId] = true;
        return true;
    }
}

$contato = new FakeContatoRemoveCoexistence();
$lista = new FakeListaRemoveCoexistence();
$item = new FakeListaItemRemoveCoexistence();
$service = new MetaWebhookStateSyncService($contato, null, $lista, $item);

$resultado = $service->processar([
    'metadata'=>['phone_number_id'=>'123'],
    'state_sync'=>[[
        'type'=>'contact',
        'action'=>'remove',
        'contact'=>['phone_number'=>'5541999991111']
    ]]
], [
    'CLI_ID'=>7,
    'MTA_ID'=>3
]);

$assert($resultado['removidas'] === 1, 'Ação remove deve contabilizar o desvínculo realizado.');
$assert($item->remocoes === [[99,10]], 'Contato deve ser removido somente da lista automática do WhatsApp.');
$assert(empty($item->vinculos['99:10']), 'Vínculo da lista automática deve ser removido.');
$assert(!empty($item->vinculos['123:10']), 'Vínculos com outras listas devem ser preservados.');
$assert($lista->criarChamadas === [], 'Ação remove não deve criar a lista automática se ela já existe.');
$assert(isset($contato->rows['7:5541999991111']), 'Contato deve permanecer na base após remoção no WhatsApp Business.');

$listaSemAutomatica = new FakeListaRemoveCoexistence();
$listaSemAutomatica->lista = false;
$itemSemAutomatica = new FakeListaItemRemoveCoexistence();
$serviceSemAutomatica = new MetaWebhookStateSyncService($contato, null, $listaSemAutomatica, $itemSemAutomatica);
$resultadoSemAutomatica = $serviceSemAutomatica->processar([
    'state_sync'=>[[
        'type'=>'contact',
        'action'=>'remove',
        'contact'=>['phone_number'=>'5541999991111']
    ]]
], [
    'CLI_ID'=>7,
    'MTA_ID'=>3
]);

$assert($resultadoSemAutomatica['removidas'] === 0, 'Sem lista automática não há vínculo para remover.');
$assert($listaSemAutomatica->criarChamadas === [], 'Remove não deve criar uma lista automática inexistente.');
$assert($itemSemAutomatica->remocoes === [], 'Sem lista automática nenhuma remoção deve ser executada.');

$resultadoInexistente = $service->processar([
    'state_sync'=>[[
        'type'=>'contact',
        'action'=>'remove',
        'contact'=>['phone_number'=>'5541888882222']
    ]]
], [
    'CLI_ID'=>7,
    'MTA_ID'=>3
]);

$assert($resultadoInexistente['removidas'] === 0, 'Contato inexistente deve tornar o remove idempotente.');
$assert(count($item->remocoes) === 1, 'Retry ou contato inexistente não deve produzir remoções adicionais.');

echo "Coexistence contact remove checks passed\n";
