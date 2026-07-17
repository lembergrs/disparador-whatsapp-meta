<?php

namespace Services;

use Core\Database;
use Models\Conversa;
use Models\Contato;
use Models\MetaConta;
use Models\TemplateMeta;
use Exception;
use PDO;

class ConversaTemplateService
{
    private $db;
    private $conversas;
    private $contatos;
    private $metas;
    private $templates;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conversas = new Conversa();
        $this->contatos = new Contato();
        $this->metas = new MetaConta();
        $this->templates = new TemplateMeta();
    }

    public function normalizarTelefone($telefone)
    {
        return preg_replace('/\D/', '', (string) $telefone);
    }

    public function enviar(array $usuario, array $dados)
    {
        $metaId = (int) ($dados['meta_id'] ?? 0);
        $templateId = (int) ($dados['template_id'] ?? 0);
        $telefone = $this->normalizarTelefone($dados['telefone'] ?? '');
        $nome = trim((string) ($dados['nome'] ?? ''));
        $variaveis = $dados['variaveis'] ?? [];

        if($metaId <= 0 || $templateId <= 0 || $telefone === ''){
            throw new Exception('Número remetente, destinatário e template são obrigatórios.');
        }

        $conta = $this->metas->buscarPorUsuario($metaId, $usuario);
        if(!$conta){
            throw new Exception('Conta Meta não permitida.');
        }

        $template = $this->templates->buscarAprovadoParaEnvioPorUsuario($templateId, $usuario, $metaId);
        if(!$template){
            throw new Exception('Template aprovado não encontrado para esta conta.');
        }

        $this->validarVariaveisObrigatorias($template, $variaveis);

        $meta = new MetaService($metaId, (int) $conta['CLI_ID']);
        $response = $meta->enviarTemplate($telefone, $template, $variaveis);
        $messageId = $response['messages'][0]['id'] ?? ($response['response']['messages'][0]['id'] ?? null);

        if(!$messageId){
            $erro = $response['error']['message'] ?? ($response['response']['error']['message'] ?? 'Erro ao enviar template pela Meta.');
            throw new Exception($erro);
        }

        try{
            $this->db->beginTransaction();

            $contato = $this->contatos->buscarPorTelefone((int) $conta['CLI_ID'], $telefone);
            if(!$contato){
                $contatoId = $this->contatos->salvar([
                    'cliente_id' => (int) $conta['CLI_ID'],
                    'nome' => $nome !== '' ? $nome : $telefone,
                    'telefone' => $telefone,
                    'dados_json' => json_encode(['origem' => 'conversa_template'], JSON_UNESCAPED_UNICODE)
                ]);
            }else{
                $contatoId = (int) $contato['CON_ID'];
                if($nome === ''){
                    $nome = $contato['CON_Nome'] ?? $telefone;
                }
            }

            $conversaId = $this->conversas->buscarOuCriar((int) $conta['CLI_ID'], $metaId, $telefone, $nome !== '' ? $nome : $telefone);
            $texto = $this->renderizarTemplate($template, $variaveis);

            $this->conversas->salvarMensagem([
                'conversa_id' => $conversaId,
                'direcao' => 'enviada',
                'tipo' => 'template',
                'texto' => $texto,
                'message_id' => $messageId,
                'status' => 'enviado',
                'retorno' => [
                    'message_id' => $messageId,
                    'template' => $template['TMP_Nome'],
                    'idioma' => $template['TMP_Idioma'],
                    'variaveis' => $variaveis,
                    'meta_http_code' => $response['http_code'] ?? null
                ],
                'data_mensagem' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();
        }catch(\Throwable $e){
            if($this->db->inTransaction()){
                $this->db->rollBack();
            }
            error_log('persistencia_conversa_template message_id=' . $messageId . ' erro=' . $e->getMessage());
            throw new Exception('Template enviado, mas houve erro ao registrar o histórico. Informe o suporte.');
        }

        return [
            'sucesso' => true,
            'conversa_id' => (int) $conversaId,
            'contato_id' => (int) $contatoId,
            'message_id' => $messageId
        ];
    }

    private function validarVariaveisObrigatorias(array $template, array $variaveis)
    {
        $exigidas = (new TemplateMeta())->extrairVariaveis($template['TMP_Componentes'] ?? '[]');
        foreach($exigidas as $var){
            if(!array_key_exists($var, $variaveis) || trim((string) $variaveis[$var]) === ''){
                throw new Exception('Preencha todas as variáveis obrigatórias do template.');
            }
        }
    }

    private function renderizarTemplate(array $template, array $variaveis)
    {
        $componentes = json_decode($template['TMP_Componentes'] ?? '[]', true);
        $partes = ['Template: ' . ($template['TMP_Nome'] ?? '') . ' (' . ($template['TMP_Idioma'] ?? '') . ')'];
        if(is_array($componentes)){
            foreach($componentes as $comp){
                if(in_array(($comp['type'] ?? ''), ['HEADER', 'BODY', 'FOOTER'], true) && !empty($comp['text'])){
                    $texto = $comp['text'];
                    foreach($variaveis as $nome => $valor){
                        $texto = str_replace('{{' . $nome . '}}', (string) $valor, $texto);
                    }
                    $partes[] = $texto;
                }
            }
        }
        return implode("\n", $partes);
    }
}
