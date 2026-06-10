<?php

namespace Services;

use Models\Cliente;
use Models\ConsumoMensal;
use Models\ExcedenteMensal;

class ControlePlanoService
{
    public function registrarUso($cliId)
    {
        $clienteModel =
            new Cliente();

        $cliente =
            $clienteModel->buscarComPlano(
                $cliId
            );

        if(!$cliente){
            return;
        }

        if(
            empty(
                $cliente['PLA_LimiteMensagens']
            )
        ){
            return;
        }

        $consumoModel =
            new ConsumoMensal();

        $consumo =
            $consumoModel->buscarMesAtual(
                $cliId
            );

        $mensagensUtilizadas =
            (int)(
                $consumo['CMS_Mensagens']
                ?? 0
            );

        $limitePlano =
            (int)
            $cliente['PLA_LimiteMensagens'];

        if(
            $mensagensUtilizadas
            <=
            $limitePlano
        ){
            return;
        }

        $valorExcedente =
            (float)
            $cliente['PLA_ValorMensagemExcedente'];

        $excedenteModel =
            new ExcedenteMensal();

        $excedenteModel
            ->registrarExcedente(
                $cliId,
                $valorExcedente
            );
    }
}