<?php

namespace Models;

use Core\Database;

class FinanceiroTransacao
{
    public function executar(callable $operacao)
    {
        $db = Database::getInstance();
        $db->beginTransaction();

        try{
            $resultado = $operacao();
            $db->commit();
            return $resultado;
        }catch(\Throwable $e){
            if($db->inTransaction()){
                $db->rollBack();
            }
            throw $e;
        }
    }
}
