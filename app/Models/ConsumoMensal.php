<?php

namespace Models;

use Core\Database;
use PDO;

class ConsumoMensal
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }

    public function registrarMensagem($cliId, $plano = null)
    {
        if(!$plano){
            $clienteModel = new Cliente();
            $plano = $clienteModel->buscarComPlano($cliId);
        }

        $anoMes =
            date('Ym');

        $sql =
            $this->db->prepare("
                SELECT *
                FROM consumo_mensal
                WHERE CLI_ID = ?
                AND CMS_AnoMes = ?
            ");

        $sql->execute([
            $cliId,
            $anoMes
        ]);

        $registro =
            $sql->fetch(
                PDO::FETCH_ASSOC
            );

        if($registro){

            $camposAtualizacao = [
                'CMS_Mensagens = CMS_Mensagens + 1',
                'CMS_AtualizadoEm = NOW()'
            ];
            $paramsAtualizacao = [];

            if(
                $plano
                && $this->colunaExiste('consumo_mensal', 'CMS_PLA_ID')
            ){
                $camposAtualizacao[] = 'CMS_PLA_ID = COALESCE(CMS_PLA_ID, ?)';
                $paramsAtualizacao[] = $plano['PLA_ID'];
            }

            if(
                $plano
                && $this->colunaExiste('consumo_mensal', 'CMS_LimiteMensagens')
            ){
                $camposAtualizacao[] = 'CMS_LimiteMensagens = COALESCE(CMS_LimiteMensagens, ?)';
                $paramsAtualizacao[] = (int) $plano['PLA_LimiteMensagens'];
            }

            if(
                $plano
                && $this->colunaExiste('consumo_mensal', 'CMS_ValorMensagemExcedente')
            ){
                $camposAtualizacao[] = 'CMS_ValorMensagemExcedente = COALESCE(CMS_ValorMensagemExcedente, ?)';
                $paramsAtualizacao[] = (float) $plano['PLA_ValorMensagemExcedente'];
            }

            $paramsAtualizacao[] = $registro['CMS_ID'];

            $sql =
                $this->db->prepare("
                    UPDATE consumo_mensal
                    SET " . implode(', ', $camposAtualizacao) . "
                    WHERE CMS_ID = ?
                ");

            return $sql->execute($paramsAtualizacao);
        }

        $campos = [
            'CLI_ID',
            'CMS_AnoMes',
            'CMS_Mensagens'
        ];
        $placeholders = ['?', '?', '1'];
        $params = [$cliId, $anoMes];

        if(
            $plano
            && $this->colunaExiste('consumo_mensal', 'CMS_PLA_ID')
        ){
            $campos[] = 'CMS_PLA_ID';
            $placeholders[] = '?';
            $params[] = $plano['PLA_ID'];
        }

        if(
            $plano
            && $this->colunaExiste('consumo_mensal', 'CMS_LimiteMensagens')
        ){
            $campos[] = 'CMS_LimiteMensagens';
            $placeholders[] = '?';
            $params[] = (int) $plano['PLA_LimiteMensagens'];
        }

        if(
            $plano
            && $this->colunaExiste('consumo_mensal', 'CMS_ValorMensagemExcedente')
        ){
            $campos[] = 'CMS_ValorMensagemExcedente';
            $placeholders[] = '?';
            $params[] = (float) $plano['PLA_ValorMensagemExcedente'];
        }

        $sql =
            $this->db->prepare("
                INSERT INTO consumo_mensal
                (" . implode(', ', $campos) . ")
                VALUES
                (" . implode(', ', $placeholders) . ")
            ");

        return $sql->execute($params);
    }

    public function buscarMesAtual($cliId)
    {
        $anoMes =
            date('Ym');

        $sql =
            $this->db->prepare("
                SELECT *
                FROM consumo_mensal
                WHERE CLI_ID = ?
                AND CMS_AnoMes = ?
            ");

        $sql->execute([
            $cliId,
            $anoMes
        ]);

        return $sql->fetch(
            PDO::FETCH_ASSOC
        );
    }


    private function colunaExiste($tabela, $coluna)
    {
        static $cache = [];

        $chave = $tabela . '.' . $coluna;

        if(array_key_exists($chave, $cache)){
            return $cache[$chave];
        }

        $sql = $this->db->prepare("
            SHOW COLUMNS FROM {$tabela} LIKE ?
        ");

        $sql->execute([$coluna]);

        $cache[$chave] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$chave];
    }
}
