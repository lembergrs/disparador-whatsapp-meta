<?php

namespace Services\Indicacao;

class IndicacaoCalculoDescontoService
{
    private const MESES = ['mensal'=>1,'trimestral'=>3,'semestral'=>6,'anual'=>12];

    public function meses($ciclo): int
    {
        if(!is_string($ciclo) || !isset(self::MESES[$ciclo])) throw new \InvalidArgumentException('Ciclo inválido.');
        return self::MESES[$ciclo];
    }

    public function distribuirMensalidades($valorBaseCentavos, $meses): array
    {
        $valorBaseCentavos = (int)$valorBaseCentavos;
        $meses = (int)$meses;
        if($valorBaseCentavos <= 0 || $meses <= 0) throw new \InvalidArgumentException('Valor-base e meses devem ser positivos.');
        $base = intdiv($valorBaseCentavos, $meses);
        $resto = $valorBaseCentavos % $meses;
        $parcelas = array_fill(0, $meses, $base);
        for($i=0; $i<$resto; $i++) $parcelas[$i]++;
        return $parcelas;
    }

    public function desconto($mensalidadeCentavos, $percentual): int
    {
        $centésimos = $this->percentualEmCentesimos($percentual);
        return intdiv(((int)$mensalidadeCentavos * $centésimos) + 5000, 10000);
    }

    public function percentualEmCentesimos($percentual): int
    {
        $texto = trim((string)$percentual);
        if(!preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $texto, $m)) throw new \InvalidArgumentException('Percentual inválido.');
        $valor = ((int)$m[1] * 100) + (int)str_pad($m[2] ?? '', 2, '0');
        if($valor <= 0 || $valor > 10000) throw new \InvalidArgumentException('Percentual inválido.');
        return $valor;
    }
}
