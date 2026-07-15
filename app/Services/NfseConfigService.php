<?php

namespace Services;

class NfseConfigService
{
    public static function baseUrl()
    {
        return defined('NFSE_API_BASE_URL') ? rtrim((string) NFSE_API_BASE_URL, '/') : '';
    }

    public static function dpsSerie()
    {
        return defined('NFSE_DPS_SERIE') ? (string) NFSE_DPS_SERIE : '900';
    }

    public static function ambiente()
    {
        return defined('NFSE_AMBIENTE') ? (string) NFSE_AMBIENTE : 'production';
    }

    public static function prestadorCnpj()
    {
        return preg_replace('/\D/', '', (string) (defined('NFSE_PRESTADOR_CNPJ') ? NFSE_PRESTADOR_CNPJ : ''));
    }

    public static function dadosPublicos()
    {
        return [
            'base_url' => self::baseUrl(),
            'prestador_cnpj_configurado' => self::prestadorCnpj() !== '',
            'prestador_im_configurado' => defined('NFSE_PRESTADOR_IM') && trim((string) NFSE_PRESTADOR_IM) !== '',
            'opt_simples_configurado' => defined('NFSE_PRESTADOR_OP_SIMPLES') && trim((string) NFSE_PRESTADOR_OP_SIMPLES) !== '',
            'local_emissao_configurado' => defined('NFSE_LOCAL_EMISSAO_IBGE') && trim((string) NFSE_LOCAL_EMISSAO_IBGE) !== '',
            'serie' => self::dpsSerie(),
            'cert_path_configurado' => defined('NFSE_CERT_PATH') && trim((string) NFSE_CERT_PATH) !== '',
            'cert_password_configurado' => defined('NFSE_CERT_PASSWORD') && trim((string) NFSE_CERT_PASSWORD) !== '',
            'connect_timeout' => defined('NFSE_CONNECT_TIMEOUT') ? (int) NFSE_CONNECT_TIMEOUT : 10,
            'request_timeout' => defined('NFSE_REQUEST_TIMEOUT') ? (int) NFSE_REQUEST_TIMEOUT : 30
        ];
    }
}
