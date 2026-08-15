<?php

namespace Services;

use Models\Plano;
use Models\Cobranca;

class DescontoBoasVindasService
{
    private $cobrancas;

    public function __construct($cobrancas = null)
    {
        $this->cobrancas = $cobrancas;
    }

    public function clienteElegivel(int $clienteId, ?int $cobrancaAtualId = null): bool
    {
        if(!$this->cobrancas){ $this->cobrancas = new Cobranca(); }

        // Canceladas não consomem o benefício. Quando há uma cobrança candidata,
        // somente cobranças válidas anteriores a ela compõem o histórico.
        return $this->cobrancas->contarAnterioresDoCliente($clienteId, $cobrancaAtualId) === 0;
    }

    public function calcular(array $plano, string $ciclo, ?int $valorBaseCentavos = null): array
    {
        if(!Plano::cicloValido($ciclo)){
            throw new \DomainException('Ciclo de cobrança inválido.');
        }

        $mensalCentavos = $this->valorEmCentavos(Plano::valorPorCiclo($plano, 'mensal'));
        $baseCentavos = $valorBaseCentavos === null
            ? $this->valorEmCentavos(Plano::valorPorCiclo($plano, $ciclo))
            : max(0, $valorBaseCentavos);
        $descontoCentavos = min($baseCentavos, intdiv(max(0, $mensalCentavos) + 1, 2));

        return [
            'ciclo'=>$ciclo,
            'valor_base_centavos'=>$baseCentavos,
            'valor_mensal_centavos'=>$mensalCentavos,
            'desconto_centavos'=>$descontoCentavos,
            'primeira_cobranca_centavos'=>max(0, $baseCentavos - $descontoCentavos)
        ];
    }

    public function calcularPlanos(array $planos): array
    {
        $ofertas = [];
        foreach($planos as $plano){
            $planoId = (int) ($plano['PLA_ID'] ?? 0);
            foreach(array_keys(Plano::CICLOS) as $ciclo){
                $ofertas[$planoId][$ciclo] = $this->calcular($plano, $ciclo);
            }
        }
        return $ofertas;
    }

    private function valorEmCentavos($valor): int
    {
        if(!is_numeric($valor)){
            return 0;
        }
        return max(0, (int) round(((float) $valor) * 100, 0, PHP_ROUND_HALF_UP));
    }
}
