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
                SELECT c.*,
                    a.ASS_Ciclo, a.ASS_Status, a.ASS_Valor, a.ASS_DataProximaCobranca,
                    p.PLA_Nome AS ASS_PlanoNome
                FROM clientes c
                LEFT JOIN assinaturas a
                    ON a.ASS_ID = (
                        SELECT ax.ASS_ID
                        FROM assinaturas ax
                        WHERE ax.CLI_ID = c.CLI_ID
                        AND ax.ASS_Status IN ('ativa','pendente','vencida')
                        ORDER BY FIELD(ax.ASS_Status, 'ativa','pendente','vencida'), ax.ASS_ID DESC
                        LIMIT 1
                    )
                LEFT JOIN planos p ON p.PLA_ID = a.PLA_ID
                WHERE c.CLI_StatusCadastro = :status
                ORDER BY c.CLI_ID DESC
            ");

            $sql->execute([
                ':status' => $status
            ]);

        }else{

            $sql = $this->db->query("
                SELECT c.*,
                    a.ASS_Ciclo, a.ASS_Status, a.ASS_Valor, a.ASS_DataProximaCobranca,
                    p.PLA_Nome AS ASS_PlanoNome
                FROM clientes c
                LEFT JOIN assinaturas a
                    ON a.ASS_ID = (
                        SELECT ax.ASS_ID
                        FROM assinaturas ax
                        WHERE ax.CLI_ID = c.CLI_ID
                        AND ax.ASS_Status IN ('ativa','pendente','vencida')
                        ORDER BY FIELD(ax.ASS_Status, 'ativa','pendente','vencida'), ax.ASS_ID DESC
                        LIMIT 1
                    )
                LEFT JOIN planos p ON p.PLA_ID = a.PLA_ID
                WHERE c.CLI_StatusCadastro IN ('pendente','ativo')
                ORDER BY c.CLI_ID DESC
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

    public function buscarComPlano($id)
    {
        $sql = $this->db->prepare("
            SELECT
                c.*,
                p.PLA_ID,
                p.PLA_Nome,
                p.PLA_Valor,
                p.PLA_Periodicidade,
                p.PLA_LimiteNumeros,
                p.PLA_LimiteUsuarios,
                p.PLA_LimiteMensagens,
                p.PLA_ValorMensagemExcedente
            FROM clientes c
            LEFT JOIN planos p
                ON p.PLA_ID = c.CLI_Plano_DR
            WHERE c.CLI_ID = ?
        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listarFinanceiro()
    {
        $sql = $this->db->query("
            SELECT
                c.*,
                p.PLA_Nome,
                p.PLA_LimiteMensagens,
                cm.CMS_Mensagens,
                ex.EXC_Mensagens,
                ex.EXC_ValorTotal
            FROM clientes c
            LEFT JOIN planos p
                ON p.PLA_ID = c.CLI_Plano_DR
            LEFT JOIN consumo_mensal cm
                ON cm.CLI_ID = c.CLI_ID
                AND cm.CMS_AnoMes = DATE_FORMAT(NOW(), '%Y%m')
            LEFT JOIN excedentes_mensais ex
                ON ex.CLI_ID = c.CLI_ID
                AND ex.EXC_AnoMes = DATE_FORMAT(NOW(), '%Y%m')
            ORDER BY c.CLI_ID DESC
        ");

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

}