<?php

namespace Core;

class VCard
{
    public static function ler($arquivo)
    {
        if(!is_file($arquivo) || !is_readable($arquivo)){
            throw new \Exception('Não foi possível ler o arquivo VCF.');
        }

        $conteudo = file_get_contents($arquivo);

        if($conteudo === false || trim($conteudo) === ''){
            throw new \Exception('Arquivo VCF vazio.');
        }

        $conteudo = str_replace(["\r\n", "\r"], "\n", $conteudo);
        $linhas = self::desdobrarLinhas(explode("\n", $conteudo));

        $resultado = [
            ['Nome', 'Telefone', 'Email']
        ];

        $cartao = null;
        $cartoesEncontrados = 0;

        foreach($linhas as $linha){
            $linha = rtrim($linha, "\r\n");

            if(strcasecmp(trim($linha), 'BEGIN:VCARD') === 0){
                $cartao = [
                    'fn' => '',
                    'n' => '',
                    'email' => '',
                    'telefones' => []
                ];
                continue;
            }

            if(strcasecmp(trim($linha), 'END:VCARD') === 0){
                if($cartao !== null){
                    $cartoesEncontrados++;

                    $nome = $cartao['fn'] !== ''
                        ? $cartao['fn']
                        : self::nomePorN($cartao['n']);

                    $telefones = array_values(array_unique($cartao['telefones']));

                    if(empty($telefones)){
                        $resultado[] = [
                            $nome,
                            '',
                            $cartao['email']
                        ];
                    }else{
                        foreach($telefones as $telefone){
                            $resultado[] = [
                                $nome,
                                $telefone,
                                $cartao['email']
                            ];
                        }
                    }
                }

                $cartao = null;
                continue;
            }

            if($cartao === null || trim($linha) === ''){
                continue;
            }

            $posicao = strpos($linha, ':');

            if($posicao === false){
                continue;
            }

            $chaveCompleta = substr($linha, 0, $posicao);
            $valor = substr($linha, $posicao + 1);
            $partesChave = explode(';', $chaveCompleta);
            $propriedade = array_shift($partesChave);

            if(strpos($propriedade, '.') !== false){
                $partesPropriedade = explode('.', $propriedade);
                $propriedade = end($partesPropriedade);
            }

            $propriedade = strtoupper($propriedade);
            $parametros = implode(';', $partesChave);

            $valor = self::decodificarValor($valor, $parametros);

            if($propriedade === 'FN'){
                $cartao['fn'] = self::desescapar($valor);
                continue;
            }

            if($propriedade === 'N'){
                $cartao['n'] = $valor;
                continue;
            }

            if($propriedade === 'EMAIL' && $cartao['email'] === ''){
                $cartao['email'] = self::desescapar($valor);
                continue;
            }

            if($propriedade === 'TEL'){
                $valor = preg_replace('/^tel:/i', '', trim($valor));
                $valor = self::desescapar($valor);

                if($valor !== ''){
                    $cartao['telefones'][] = $valor;
                }
            }
        }

        if($cartoesEncontrados === 0){
            throw new \Exception('Nenhum contato válido encontrado no arquivo VCF.');
        }

        return $resultado;
    }

    private static function desdobrarLinhas(array $linhas)
    {
        $resultado = [];

        foreach($linhas as $linha){
            if(!empty($resultado) && preg_match('/^[ \t]/', $linha)){
                $ultimo = count($resultado) - 1;
                $resultado[$ultimo] .= substr($linha, 1);
                continue;
            }

            if(!empty($resultado) && substr($resultado[count($resultado) - 1], -1) === '='){
                $ultimo = count($resultado) - 1;
                $resultado[$ultimo] = substr($resultado[$ultimo], 0, -1) . $linha;
                continue;
            }

            $resultado[] = $linha;
        }

        return $resultado;
    }

    private static function decodificarValor($valor, $parametros)
    {
        if(stripos($parametros, 'ENCODING=QUOTED-PRINTABLE') !== false){
            $valor = quoted_printable_decode($valor);
        }

        if(preg_match('/CHARSET=([^;:]+)/i', $parametros, $match)){
            $charset = trim($match[1], " \t\"'");

            if($charset !== '' && strcasecmp($charset, 'UTF-8') !== 0 && function_exists('mb_convert_encoding')){
                $convertido = @mb_convert_encoding($valor, 'UTF-8', $charset);

                if($convertido !== false){
                    $valor = $convertido;
                }
            }
        }

        return $valor;
    }

    private static function desescapar($valor)
    {
        $valor = str_replace(['\\n', '\\N'], "\n", $valor);
        $valor = str_replace(['\\,', '\\;', '\\\\'], [',', ';', '\\'], $valor);

        return trim($valor);
    }

    private static function nomePorN($valor)
    {
        if(trim($valor) === ''){
            return '';
        }

        $partes = explode(';', $valor);
        $familia = self::desescapar($partes[0] ?? '');
        $nome = self::desescapar($partes[1] ?? '');
        $adicional = self::desescapar($partes[2] ?? '');
        $prefixo = self::desescapar($partes[3] ?? '');
        $sufixo = self::desescapar($partes[4] ?? '');

        return trim(implode(' ', array_filter([
            $prefixo,
            $nome,
            $adicional,
            $familia,
            $sufixo
        ], function($parte){
            return $parte !== '';
        })));
    }
}
