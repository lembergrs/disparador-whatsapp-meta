<?php

namespace Services;

class MetaErrorMessageService
{
    public static function amigavel($codigo, $mensagem = null): string
    {
        $codigo=trim((string)$codigo);
        $mensagem = MensagemStatusService::sanitizarErro($mensagem);
        if ($codigo === '' && stripos($mensagem, 'healthy ecosystem engagement') !== false) {
            $codigo = '131049';
        }
        $mensagens = [
            '131026' => 'Mensagem não entregue. Confirme se o destinatário possui WhatsApp ativo e atualizado.',
            '131047' => 'Janela de atendimento de 24 horas encerrada. Utilize um modelo de mensagem aprovado.',
            '131048' => 'A Meta restringiu os envios deste número por questões de qualidade. Verifique a qualidade da conta no Gerenciador do WhatsApp.',
            '131049' => 'A Meta restringiu a entrega desta mensagem de marketing ao destinatário (código 131049). Aguarde pelo menos 24 horas antes de tentar novamente. A entrega não é garantida.',
            '131050' => 'O destinatário optou por não receber mensagens de marketing desta empresa. Não reenvie mensagens de marketing enquanto essa preferência estiver ativa.',
            '131042' => 'Problema no faturamento da conta WhatsApp. Verifique a forma de pagamento na Meta.',
            '131056' => 'Muitas mensagens enviadas ao mesmo destinatário em pouco tempo. Aguarde antes de tentar novamente.',
            '132000' => 'Quantidade de parâmetros diferente da definida no modelo. Revise as variáveis.',
            '132001' => 'Modelo não encontrado ou não aprovado no idioma selecionado. Confira o modelo na Meta.',
            '132015' => 'Modelo pausado pela Meta por baixa qualidade. Verifique sua situação.',
            '132016' => 'Modelo desativado pela Meta. Utilize outro modelo aprovado.',
        ];
        if (isset($mensagens[$codigo])) {
            return $mensagens[$codigo];
        }

        $mensagem=MensagemStatusService::sanitizarErro($mensagem);
        return $mensagem !== '' ? $mensagem : 'Não foi possível entregar a mensagem.';
    }

    public static function codigoDoRetorno($retorno): string
    {
        if(is_string($retorno)){
            $retorno=json_decode($retorno,true);
        }
        if(!is_array($retorno)) return '';

        foreach([
            $retorno['error_code']??null,
            $retorno['error']['code']??null,
            $retorno['response']['error']['code']??null
        ] as $codigo){
            if($codigo!==null && $codigo!=='') return preg_replace('/[^A-Za-z0-9_.-]/','',(string)$codigo);
        }
        return '';
    }
}
