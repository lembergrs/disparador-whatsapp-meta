<?php

namespace Services\Indicacao;

class CodigoIndicacaoPadraoGenerator implements CodigoIndicacaoGeneratorInterface
{
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function gerar(array $cliente): string
    {
        return $this->prefixo($cliente) . '-' . $this->sufixo(5);
    }

    private function prefixo(array $cliente): string
    {
        $nome = '';
        foreach(['CLI_NomeFantasia','CLI_RazaoSocial','CLI_Nome','nome_fantasia','razao_social','nome'] as $campo){
            if(!empty($cliente[$campo])){ $nome = trim((string)$cliente[$campo]); break; }
        }
        if($nome === '') return 'DSP';

        $nome = function_exists('mb_strtoupper') ? mb_strtoupper($nome, 'UTF-8') : strtoupper($nome);
        if(function_exists('iconv')){
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
            if($ascii !== false) $nome = $ascii;
        }
        $palavras = preg_split('/[^A-Z0-9]+/', $nome, -1, PREG_SPLIT_NO_EMPTY);
        $ignorar = ['LTDA','ME','EPP','SA','S/A'];
        $palavras = array_values(array_filter($palavras, fn($p) => !in_array($p, $ignorar, true)));
        if(!$palavras) return 'DSP';

        if(count($palavras) >= 3) return substr($palavras[0],0,1).substr($palavras[1],0,1).substr($palavras[2],0,1);
        if(count($palavras) === 2){
            $base = substr($palavras[0],0,1).substr($palavras[1],0,1);
            $extra = substr($palavras[0],1,1) ?: (substr($palavras[1],1,1) ?: 'X');
            return substr($base.$extra,0,3);
        }
        $unica = preg_replace('/[^A-Z0-9]/', '', $palavras[0]);
        if(strlen($unica) >= 3) return substr($unica,0,3);
        return str_pad($unica, 3, 'X');
    }

    private function sufixo(int $tamanho): string
    {
        $saida = '';
        $limite = strlen(self::ALFABETO);
        $maxAceitavel = intdiv(256, $limite) * $limite;
        while(strlen($saida) < $tamanho){
            $byte = ord(random_bytes(1));
            if($byte >= $maxAceitavel) continue;
            $saida .= self::ALFABETO[$byte % $limite];
        }
        return $saida;
    }
}
