<?php

namespace Services;

class InterService
{
    public function gerarCobranca($cobranca)
    {
        return [
            'sucesso' => false,
            'mensagem' => 'Integração Inter ainda não configurada.'
        ];
    }

    public function consultarCobranca($txid)
    {
        return [
            'sucesso' => false,
            'mensagem' => 'Integração Inter ainda não configurada.'
        ];
    }

    public function registrarWebhook()
    {
        return [
            'sucesso' => false,
            'mensagem' => 'Integração Inter ainda não configurada.'
        ];
    }
}