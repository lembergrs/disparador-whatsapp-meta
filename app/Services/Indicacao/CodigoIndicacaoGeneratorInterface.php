<?php

namespace Services\Indicacao;

interface CodigoIndicacaoGeneratorInterface
{
    public function gerar(array $cliente): string;
}
