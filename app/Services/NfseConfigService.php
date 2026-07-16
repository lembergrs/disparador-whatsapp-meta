<?php

namespace Services;

class NfseConfigService
{
    public static function baseUrl()
    {
        return defined('NFSE_API_BASE_URL') ? rtrim((string) NFSE_API_BASE_URL, '/') : '';
    }

    public static function authToken()
    {
        return defined('NFSE_API_AUTH_TOKEN') ? (string) NFSE_API_AUTH_TOKEN : '';
    }

    public static function certPath()
    {
        return defined('NFSE_CERT_PATH') ? (string) NFSE_CERT_PATH : '';
    }

    public static function certPassword()
    {
        return defined('NFSE_CERT_PASSWORD') ? (string) NFSE_CERT_PASSWORD : '';
    }

    public static function prestadorIm()
    {
        return defined('NFSE_PRESTADOR_IM') ? trim((string) NFSE_PRESTADOR_IM) : '';
    }

    public static function optSimplesNacional()
    {
        return defined('NFSE_PRESTADOR_OP_SIMPLES') ? (int) NFSE_PRESTADOR_OP_SIMPLES : 0;
    }

    public static function localEmissaoIbge()
    {
        return preg_replace('/\D/', '', (string) (defined('NFSE_LOCAL_EMISSAO_IBGE') ? NFSE_LOCAL_EMISSAO_IBGE : ''));
    }

    public static function connectTimeout()
    {
        return max(1, defined('NFSE_CONNECT_TIMEOUT') ? (int) NFSE_CONNECT_TIMEOUT : 10);
    }

    public static function requestTimeout()
    {
        return max(1, defined('NFSE_REQUEST_TIMEOUT') ? (int) NFSE_REQUEST_TIMEOUT : 30);
    }

    public static function dpsSerie()
    {
        $serie = defined('NFSE_DPS_SERIE') ? preg_replace('/\D/', '', (string) NFSE_DPS_SERIE) : '900';
        return $serie !== '' ? $serie : '900';
    }

    public static function ambiente()
    {
        $ambiente = defined('NFSE_AMBIENTE') ? strtolower(trim((string) NFSE_AMBIENTE)) : 'production';
        return in_array($ambiente, ['production', 'sandbox', 'homologation', 'local'], true) ? $ambiente : 'production';
    }

    public static function prestadorCnpj()
    {
        return preg_replace('/\D/', '', (string) (defined('NFSE_PRESTADOR_CNPJ') ? NFSE_PRESTADOR_CNPJ : ''));
    }


    public static function codigoTributacaoNacional()
    {
        return defined('NFSE_CODIGO_TRIBUTACAO_NACIONAL') ? trim((string) NFSE_CODIGO_TRIBUTACAO_NACIONAL) : '';
    }

    public static function descricaoServico()
    {
        return defined('NFSE_DESCRICAO_SERVICO') ? trim((string) NFSE_DESCRICAO_SERVICO) : '';
    }

    public static function configuracaoFiscalParametrizada()
    {
        $pendencias = [];
        if(self::codigoTributacaoNacional() === ''){
            $pendencias[] = 'codigo_tributacao';
        }
        if(self::descricaoServico() === ''){
            $pendencias[] = 'descricao_servico';
        }

        return [
            'completa' => empty($pendencias),
            'pendencias' => $pendencias,
            'codigo_tributacao_configurado' => self::codigoTributacaoNacional() !== '',
            'descricao_servico_configurada' => self::descricaoServico() !== ''
        ];
    }

    public static function dadosPublicos()
    {
        return [
            'base_url' => self::baseUrl(),
            'prestador_cnpj_configurado' => self::prestadorCnpj() !== '',
            'prestador_im_configurado' => defined('NFSE_PRESTADOR_IM') && trim((string) NFSE_PRESTADOR_IM) !== '',
            'opt_simples_configurado' => defined('NFSE_PRESTADOR_OP_SIMPLES') && trim((string) NFSE_PRESTADOR_OP_SIMPLES) !== '',
            'local_emissao_configurado' => defined('NFSE_LOCAL_EMISSAO_IBGE') && trim((string) NFSE_LOCAL_EMISSAO_IBGE) !== '',
            'codigo_tributacao_configurado' => self::codigoTributacaoNacional() !== '',
            'descricao_servico_configurada' => self::descricaoServico() !== '',
            'serie' => self::dpsSerie(),
            'cert_path_configurado' => defined('NFSE_CERT_PATH') && trim((string) NFSE_CERT_PATH) !== '',
            'cert_password_configurado' => defined('NFSE_CERT_PASSWORD') && trim((string) NFSE_CERT_PASSWORD) !== '',
            'connect_timeout' => max(1, defined('NFSE_CONNECT_TIMEOUT') ? (int) NFSE_CONNECT_TIMEOUT : 10),
            'request_timeout' => max(1, defined('NFSE_REQUEST_TIMEOUT') ? (int) NFSE_REQUEST_TIMEOUT : 30)
        ];
    }
}
