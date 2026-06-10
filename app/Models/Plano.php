<?php

namespace Models;

use Core\Database;
use PDO;

class Plano
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listarAtivos()
    {
        $sql = $this->db->query("
            SELECT *
            FROM planos
            WHERE PLA_Ativo = 'S'
            ORDER BY PLA_Valor ASC
        ");

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM planos
            WHERE PLA_ID = ?
            AND PLA_Ativo = 'S'
        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function salvar($dados)
    {
        $sql = $this->db->prepare("
            INSERT INTO planos
            (
                PLA_Nome,
                PLA_Periodicidade,
                PLA_Valor,
                PLA_LimiteNumeros,
                PLA_LimiteUsuarios,
                PLA_LimiteMensagens,
                PLA_ValorMensagemExcedente
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?
            )
        ");

        return $sql->execute([
            $dados['nome'],
            $dados['periodicidade'],
            str_replace(',', '.', $dados['valor']),
            $dados['numeros'],
            $dados['usuarios'],
            $dados['mensagens'],
            str_replace(',', '.', $dados['excedente'])
        ]);
    }

}