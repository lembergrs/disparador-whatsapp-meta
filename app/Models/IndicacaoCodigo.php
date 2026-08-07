<?php

namespace Models;

use Core\Database;
use PDO;

class IndicacaoCodigo
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function buscar($id)
    {
        $s = $this->db->prepare('SELECT * FROM indicacao_codigos WHERE ICD_ID=?');
        $s->execute([(int) $id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscarPorNormalizado($codigo, $forUpdate = false)
    {
        $sql = 'SELECT * FROM indicacao_codigos WHERE ICD_CodigoNormalizado=? LIMIT 1';
        if($forUpdate && $this->driver() === 'mysql'){
            $sql .= ' FOR UPDATE';
        }
        $s = $this->db->prepare($sql);
        $s->execute([$codigo]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function criar(array $d)
    {
        $s = $this->db->prepare('INSERT INTO indicacao_codigos (CLI_ID,ICP_ID,ICD_Codigo,ICD_CodigoNormalizado,ICD_Status) VALUES (?,?,?,?,?)');
        $s->execute([
            (int) $d['cliente_id'],
            (int) $d['campanha_id'],
            $d['codigo'],
            $d['codigo_normalizado'],
            $d['status'] ?? 'nao_liberado'
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function alterarStatus($id, $anterior, $novo, array $datas = [])
    {
        $sets = ['ICD_Status=?'];
        $args = [$novo];
        foreach(['ICD_LiberadoEm'=>'liberado_em','ICD_SuspensoEm'=>'suspenso_em','ICD_CanceladoEm'=>'cancelado_em'] as $c=>$k){
            if(array_key_exists($k, $datas)){
                $sets[] = "$c=?";
                $args[] = $datas[$k];
            }
        }
        $args[] = (int) $id;
        $args[] = $anterior;
        $s = $this->db->prepare('UPDATE indicacao_codigos SET '.implode(',', $sets).' WHERE ICD_ID=? AND ICD_Status=?');
        $s->execute($args);
        return $s->rowCount() === 1;
    }

    private function driver()
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
