<?php

namespace Models;

use Core\Database;
use PDO;

class Cliente
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }





    public function listar($status = null)
    {
        if($status){

            $sql = $this->db->prepare("
                SELECT *
                FROM clientes
                WHERE CLI_StatusCadastro = :status
                ORDER BY CLI_ID DESC
            ");

            $sql->execute([
                ':status' => $status
            ]);

        }else{

            $sql = $this->db->query("
                SELECT *
                FROM clientes
                WHERE CLI_StatusCadastro IN ('pendente','ativo')
                ORDER BY CLI_ID DESC
            ");

        }

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }





    public function buscar($id)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM clientes

            WHERE CLI_ID = ?

        ");

        $sql->execute([$id]);

        return $sql->fetch(
            PDO::FETCH_ASSOC
        );
    }





    public function salvar($dados)
    {
        $sql = $this->db->prepare("

            INSERT INTO clientes
            (

                CLI_TipoPessoa,
                CLI_CPF_CNPJ,
                CLI_Nome,
                CLI_RazaoSocial,
                CLI_Email,
                CLI_Telefone,
                CLI_ValorMensalidade,
                CLI_Vencimento,
                CLI_StatusPagamento,
                CLI_Observacoes,
                CLI_Ativo

            )

            VALUES
            (

                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'S'

            )

        ");





        $mensalidade =
            str_replace(
                ',',
                '.',
                str_replace(
                    '.',
                    '',
                    $dados['mensalidade']
                )
            );





        $sql->execute([

            $dados['tipo_pessoa'],

            preg_replace(
                '/\D/',
                '',
                $dados['cpf_cnpj']
            ),

            $dados['nome'],

            $dados['razao_social']
            ?? null,

            $dados['email'],

            preg_replace(
                '/\D/',
                '',
                $dados['telefone']
            ),

            $mensalidade,

            $dados['vencimento']
            ?: null,

            $dados['status'],

            $dados['observacoes']
            ?? null

        ]);





        return $this->db->lastInsertId();
    }





    public function atualizar($id, $dados)
    {
        $sql = $this->db->prepare("

            UPDATE clientes SET

                CLI_TipoPessoa = ?,
                CLI_CPF_CNPJ = ?,
                CLI_Nome = ?,
                CLI_RazaoSocial = ?,
                CLI_Email = ?,
                CLI_Telefone = ?,
                CLI_ValorMensalidade = ?,
                CLI_Vencimento = ?,
                CLI_StatusPagamento = ?,
                CLI_Observacoes = ?

            WHERE CLI_ID = ?

        ");





        $mensalidade =
            str_replace(
                ',',
                '.',
                str_replace(
                    '.',
                    '',
                    $dados['mensalidade']
                )
            );





        return $sql->execute([

            $dados['tipo_pessoa'],

            preg_replace(
                '/\D/',
                '',
                $dados['cpf_cnpj']
            ),

            $dados['nome'],

            $dados['razao_social']
            ?? null,

            $dados['email'],

            preg_replace(
                '/\D/',
                '',
                $dados['telefone']
            ),

            $mensalidade,

            $dados['vencimento']
            ?: null,

            $dados['status'],

            $dados['observacoes']
            ?? null,

            $id

        ]);
    }





    public function inativar($id)
    {
        $sql = $this->db->prepare("

            UPDATE clientes

            SET CLI_Ativo = 'N'

            WHERE CLI_ID = ?

        ");

        return $sql->execute([$id]);
    }
}