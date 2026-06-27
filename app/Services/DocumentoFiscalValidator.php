<?php

namespace Services;

class DocumentoFiscalValidator
{
    public static function valido($documento)
    {
        $documento = self::somenteNumeros($documento);

        if(strlen($documento) === 11){
            return self::cpfValido($documento);
        }

        if(strlen($documento) === 14){
            return self::cnpjValido($documento);
        }

        return false;
    }

    public static function somenteNumeros($documento)
    {
        return preg_replace('/\D/', '', (string) $documento);
    }

    private static function cpfValido($cpf)
    {
        if(preg_match('/^(\d)\1{10}$/', $cpf)){
            return false;
        }

        for($t = 9; $t < 11; $t++){
            $soma = 0;

            for($i = 0; $i < $t; $i++){
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }

            $digito = ((10 * $soma) % 11) % 10;

            if((int) $cpf[$t] !== $digito){
                return false;
            }
        }

        return true;
    }

    private static function cnpjValido($cnpj)
    {
        if(preg_match('/^(\d)\1{13}$/', $cnpj)){
            return false;
        }

        $pesosPrimeiro = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesosSegundo = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $digito1 = self::calcularDigitoCnpj(substr($cnpj, 0, 12), $pesosPrimeiro);
        $digito2 = self::calcularDigitoCnpj(substr($cnpj, 0, 12) . $digito1, $pesosSegundo);

        return substr($cnpj, -2) === $digito1 . $digito2;
    }

    private static function calcularDigitoCnpj($base, $pesos)
    {
        $soma = 0;

        foreach($pesos as $indice => $peso){
            $soma += (int) $base[$indice] * $peso;
        }

        $resto = $soma % 11;

        return (string) ($resto < 2 ? 0 : 11 - $resto);
    }
}
