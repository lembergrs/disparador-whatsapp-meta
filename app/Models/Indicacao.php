<?php

namespace Models;

use Core\Database;
use PDO;

class Indicacao
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function buscar($id, $forUpdate = false)
    {
        $sql = 'SELECT * FROM indicacoes WHERE IND_ID=?';
        if($forUpdate && $this->driver() === 'mysql'){
            $sql .= ' FOR UPDATE';
        }
        $s = $this->db->prepare($sql);
        $s->execute([(int) $id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscarPorIndicado($clienteId, $forUpdate = false)
    {
        $sql = 'SELECT * FROM indicacoes WHERE CLI_Indicado_ID=? LIMIT 1';
        if($forUpdate && $this->driver() === 'mysql'){
            $sql .= ' FOR UPDATE';
        }
        $s = $this->db->prepare($sql);
        $s->execute([(int) $clienteId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function criar(array $d)
    {
        $s = $this->db->prepare('INSERT INTO indicacoes (ICD_ID,ICP_ID,CLI_Indicador_ID,CLI_Indicado_ID,IND_PercentualSnapshot,IND_Origem,IND_Status) VALUES (?,?,?,?,?,?,?)');
        $s->execute([
            (int) $d['codigo_id'],
            (int) $d['campanha_id'],
            (int) $d['indicador_id'],
            (int) $d['indicado_id'],
            $d['percentual'],
            $d['origem'],
            $d['status'] ?? 'cadastrada'
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function confirmarPagamento($id, $pagoEm, $confirmacaoAte)
    {
        $s = $this->db->prepare("UPDATE indicacoes SET IND_Status='pagamento_confirmado', IND_PagamentoConfirmadoEm=?, IND_ConfirmacaoAte=? WHERE IND_ID=? AND IND_Status='aguardando_pagamento'");
        $s->execute([$pagoEm, $confirmacaoAte, (int) $id]);
        return $s->rowCount() === 1;
    }

    public function alterarStatus($id, $anterior, $novo, $motivo = null, array $datas = [])
    {
        $sets = ['IND_Status=?', 'IND_Motivo=?'];
        $args = [$novo, $motivo];
        $map = [
            'IND_PagamentoConfirmadoEm'=>'pagamento_confirmado_em',
            'IND_ConfirmacaoAte'=>'confirmacao_ate',
            'IND_AprovadaEm'=>'aprovada_em',
            'IND_CanceladaEm'=>'cancelada_em',
            'IND_FraudeEm'=>'fraude_em',
            'IND_InelegivelEm'=>'inelegivel_em'
        ];
        foreach($map as $c=>$k){
            if(array_key_exists($k, $datas)){
                $sets[] = "$c=?";
                $args[] = $datas[$k];
            }
        }
        $args[] = (int) $id;
        $args[] = $anterior;
        $s = $this->db->prepare('UPDATE indicacoes SET '.implode(',', $sets).' WHERE IND_ID=? AND IND_Status=?');
        $s->execute($args);
        return $s->rowCount() === 1;
    }

    private function driver()
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
