<?php

namespace Services;

class NfseSanitizer
{
    public static function mensagem($valor)
    {
        if($valor === null){
            return null;
        }

        if(is_array($valor)){
            return self::dados($valor);
        }

        $mensagem = (string) $valor;
        $padroes = [
            '/Authorization\s*[:=]\s*Bearer\s+[^,;\s]+/i' => 'Authorization: Bearer ***',
            '/Bearer\s+[^,;\s]+/i' => 'Bearer ***',
            '/(API_AUTH_TOKEN|senhaCert|CERT_PASSWORD|password|senha|cert|PFX|base64|token)\s*[:=]\s*[^,;\s]+/i' => '$1=***',
            '/(\/[^\s,;]*)(cert|pfx|nfse)[^\s,;]*/i' => '[caminho_sensivel]'
        ];

        foreach($padroes as $padrao => $substituicao){
            $mensagem = preg_replace($padrao, $substituicao, $mensagem);
        }

        $mensagem = preg_replace('/[\r\n\t]+/', ' ', $mensagem);

        return mb_substr(trim($mensagem), 0, 1000);
    }

    public static function dados(array $dados)
    {
        $resultado = [];

        foreach($dados as $chave => $valor){
            $chaveTexto = (string) $chave;
            if(preg_match('/authorization|bearer|api_auth_token|senhaCert|cert_password|password|senha|cert|pfx|base64|token/i', $chaveTexto)){
                $resultado[$chave] = '***';
                continue;
            }

            if(is_array($valor)){
                $resultado[$chave] = self::dados($valor);
            }elseif(is_string($valor)){
                $resultado[$chave] = self::mensagem($valor);
            }else{
                $resultado[$chave] = $valor;
            }
        }

        return $resultado;
    }

    public static function documento($documento)
    {
        $digitos = preg_replace('/\D/', '', (string) $documento);
        if(strlen($digitos) <= 4){
            return '***';
        }
        return str_repeat('*', max(0, strlen($digitos) - 4)) . substr($digitos, -4);
    }
}
