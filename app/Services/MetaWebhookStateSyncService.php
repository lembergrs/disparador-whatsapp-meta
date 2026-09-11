<?php

namespace Services;

use Models\ListaContato;
use Models\ListaContatoItem;

class MetaWebhookStateSyncService
{
    private $contatoModel;
    private $listaModel;
    private $listaItemModel;
    private $logger;

    public function __construct($contatoModel, callable $logger = null, $listaModel = null, $listaItemModel = null)
    {
        $this->contatoModel = $contatoModel;
        $this->logger = $logger;
        $this->listaModel = $listaModel ?: new ListaContato();
        $this->listaItemModel = $listaItemModel ?: new ListaContatoItem();
    }

    public function processar(array $value, array $metaConta)
    {
        $resultado = ['criadas'=>0, 'existentes'=>0, 'vinculadas'=>0, 'removidas'=>0, 'ignoradas'=>0, 'invalidas'=>0];
        $clienteId = (int) ($metaConta['CLI_ID'] ?? 0);
        $metaId = (int) ($metaConta['MTA_ID'] ?? 0);
        $listaWhatsappId = null;

        if($clienteId <= 0 || $metaId <= 0){
            return ['criadas'=>0, 'existentes'=>0, 'vinculadas'=>0, 'removidas'=>0, 'ignoradas'=>0, 'invalidas'=>1];
        }

        foreach(($value['state_sync'] ?? []) as $state){
            try{
                $type = strtolower(trim((string) ($state['type'] ?? '')));
                $action = strtolower(trim((string) ($state['action'] ?? '')));
                if($type !== 'contact' || !in_array($action, ['add','added','create','created','update','updated','remove'], true)){
                    $resultado['ignoradas']++;
                    $this->log('state_sync_tipo_adiado', [
                        'phone_number_id'=>$value['metadata']['phone_number_id'] ?? null,
                        'type'=>$type ?: null,
                        'action'=>$action ?: null
                    ]);
                    continue;
                }

                $telefone = trim((string) ($state['contact']['phone_number'] ?? ''));
                $normalizado = TelefoneService::normalizar($telefone);
                if(!$normalizado || !preg_match('/^[0-9]{8,20}$/', $normalizado)){
                    $resultado['invalidas']++;
                    continue;
                }

                $existente = $this->contatoModel->buscarPorTelefone($clienteId, $normalizado);

                if($action === 'remove'){
                    if(!$existente){
                        continue;
                    }

                    if($listaWhatsappId === null){
                        $listaWhatsapp = $this->listaModel->buscarPorNome($clienteId, 'Contatos do WhatsApp');
                        $listaWhatsappId = $listaWhatsapp ? (int) $listaWhatsapp['LST_ID'] : 0;
                    }

                    $contatoId = (int) $existente['CON_ID'];
                    if($listaWhatsappId > 0 && $contatoId > 0 && $this->listaItemModel->contatoExisteNaLista($listaWhatsappId, $contatoId)){
                        if($this->listaItemModel->removerContato($listaWhatsappId, $contatoId)){
                            $resultado['removidas']++;
                        }
                    }
                    continue;
                }

                if($existente){
                    $contatoId = (int) $existente['CON_ID'];
                    $resultado['existentes']++;
                }else{
                    $nome = trim((string) ($state['contact']['full_name'] ?? ($state['contact']['first_name'] ?? '')));
                    if($nome === '') $nome = $normalizado;

                    $contatoId = (int) $this->contatoModel->salvar([
                        'cliente_id'=>$clienteId,
                        'nome'=>$nome,
                        'telefone'=>$normalizado,
                        'dados_json'=>json_encode(['origem'=>'whatsapp_business_app'], JSON_UNESCAPED_UNICODE)
                    ]);
                    $resultado['criadas']++;
                }

                if($listaWhatsappId === null || $listaWhatsappId === 0){
                    $listaWhatsappId = $this->listaModel->obterOuCriarListaWhatsapp($clienteId);
                }

                if($listaWhatsappId > 0 && $contatoId > 0){
                    $jaVinculado = $this->listaItemModel->contatoExisteNaLista($listaWhatsappId, $contatoId);
                    $this->listaItemModel->adicionar($listaWhatsappId, $contatoId);
                    if(!$jaVinculado){
                        $resultado['vinculadas']++;
                    }
                }
            }catch(\Throwable $e){
                $resultado['invalidas']++;
                $this->log('state_sync_item_erro', [
                    'phone_number_id'=>$value['metadata']['phone_number_id'] ?? null,
                    'type'=>$state['type'] ?? null,
                    'action'=>$state['action'] ?? null,
                    'error_class'=>get_class($e)
                ]);
            }
        }

        return $resultado;
    }

    private function log($acao, array $dados)
    {
        if($this->logger) call_user_func($this->logger, $acao, $dados);
    }
}
