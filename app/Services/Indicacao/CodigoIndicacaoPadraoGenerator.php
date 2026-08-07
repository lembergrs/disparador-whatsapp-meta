<?php
namespace Services\Indicacao;
class CodigoIndicacaoPadraoGenerator implements CodigoIndicacaoGeneratorInterface
{
    const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    public function gerar(array $cliente): string
    {
        return $this->prefixo($cliente) . '-' . $this->sufixo();
    }
    public function prefixo(array $cliente): string
    {
        $nome = '';
        foreach(['nome_fantasia','razao_social','nome'] as $campo){
            if(trim((string)($cliente[$campo] ?? '')) !== ''){ $nome = trim((string)$cliente[$campo]); break; }
        }
        $ascii = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$nome);
        $palavras = preg_split('/[^A-Za-z0-9]+/', strtoupper($ascii ?: $nome), -1, PREG_SPLIT_NO_EMPTY);
        $ignoradas = ['LTDA','ME','EPP']; $palavras = array_values(array_filter($palavras,function($p)use($ignoradas){return !in_array($p,$ignoradas,true);}));
        if(count($palavras)>=3) $base=$palavras[0][0].$palavras[1][0].$palavras[2][0];
        elseif(count($palavras)===2) $base=$palavras[0][0].$palavras[1][0].substr($palavras[0],1,1);
        else $base=substr($palavras[0] ?? '',0,3);
        $base=preg_replace('/[^A-Z0-9]/','',$base);
        if($base === '') return 'DSP';
        return strlen($base)>=3 ? substr($base,0,3) : str_pad($base,3,'X');
    }
    private function sufixo(): string
    {
        $saida=''; $max=strlen(self::ALFABETO);
        while(strlen($saida)<5){ foreach(str_split(random_bytes(8)) as $byte){ $n=ord($byte); if($n>=256-(256%$max)) continue; $saida.=self::ALFABETO[$n%$max]; if(strlen($saida)===5) break; } }
        return $saida;
    }
}
