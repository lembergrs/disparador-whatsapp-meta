<?php

namespace Services;

class MetaErrorMessageService
{
    public static function amigavel($codigo, $mensagem = null): string
    {
        $codigo=trim((string)$codigo);
        if($codigo==='131026'){
            return 'Mensagem não entregue. O número pode não possuir WhatsApp ativo.';
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
