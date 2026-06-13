<?php

namespace Models;

use Core\Database;
use PDO;

class MetaConta
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }





    public function listar()
    {
        $sql = $this->db->query("

            SELECT
                m.*,
                c.CLI_Nome

            FROM meta_contas m

            INNER JOIN clientes c
            ON c.CLI_ID = m.CLI_ID

            WHERE m.MTA_Ativo = 'S'

            ORDER BY m.MTA_ID DESC

        ");

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }





    public function salvar($dados)
    {
        $sql = $this->db->prepare("

            INSERT INTO meta_contas
            (

                CLI_ID,
                MTA_Nome,
                MTA_PhoneNumberId,
                MTA_WabaId,
                MTA_Token,
                MTA_UrlBase,
                MTA_NumeroTelefone,
                MTA_Status,
                MTA_Ativo

            )

            VALUES
            (

                ?, ?, ?, ?, ?, ?, ?, 'desconectado', 'S'

            )

        ");





        return $sql->execute([

            $dados['cliente'],

            $dados['nome'],

            $dados['phone_number_id'],

            $dados['waba_id'],

            $dados['token'],

            $dados['url_base'],

            $dados['numero']

        ]);
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM meta_contas

            WHERE MTA_ID = ?

            LIMIT 1

        ");

        $sql->execute([
            $id
        ]);

        return $sql->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public function contarAtivasPorCliente(
        $clienteId,
        $ignorarContaId = null
    )
    {
        $sqlExtra = '';
        $parametros = [
            $clienteId
        ];

        if(!empty($ignorarContaId)){

            $sqlExtra =
                " AND MTA_ID <> ? ";

            $parametros[] =
                $ignorarContaId;
        }

        $sql = $this->db->prepare("

            SELECT COUNT(*) AS total

            FROM meta_contas

            WHERE CLI_ID = ?
            AND MTA_Ativo = 'S'
            {$sqlExtra}

        ");

        $sql->execute(
            $parametros
        );

        return (int) $sql->fetchColumn();
    }

    public function avaliarLimiteNumerosPorCliente(
        $clienteId,
        $ignorarContaId = null
    )
    {
        $sql = $this->db->prepare("

            SELECT
                c.CLI_ID,
                c.CLI_Plano_DR,
                p.PLA_LimiteNumeros

            FROM clientes c

            LEFT JOIN planos p
            ON p.PLA_ID = c.CLI_Plano_DR
            AND p.PLA_Ativo = 'S'

            WHERE c.CLI_ID = ?

            LIMIT 1

        ");

        $sql->execute([
            $clienteId
        ]);

        $cliente =
            $sql->fetch(
                PDO::FETCH_ASSOC
            );

        $utilizados =
            $this->contarAtivasPorCliente(
                $clienteId,
                $ignorarContaId
            );

        $mensagemLimite =
            'Você atingiu o limite de números do seu plano. Faça upgrade para conectar mais números.';

        if(
            !$cliente
            ||
            empty($cliente['CLI_Plano_DR'])
            ||
            empty($cliente['PLA_LimiteNumeros'])
        ){

            return [
                'permitido' => false,
                'sem_plano' => true,
                'utilizados' => $utilizados,
                'limite' => 0,
                'disponiveis' => 0,
                'mensagem' => 'Escolha um plano para conectar seu número WhatsApp.'
            ];
        }

        $limite =
            (int) $cliente['PLA_LimiteNumeros'];

        $disponiveis =
            max(
                0,
                $limite - $utilizados
            );

        return [
            'permitido' => $utilizados < $limite,
            'sem_plano' => false,
            'utilizados' => $utilizados,
            'limite' => $limite,
            'disponiveis' => $disponiveis,
            'mensagem' => $utilizados < $limite
                ? null
                : $mensagemLimite
        ];
    }





    public function inativar($id)
    {
        $sql = $this->db->prepare("

            UPDATE meta_contas

            SET MTA_Ativo = 'N'

            WHERE MTA_ID = ?

        ");

        return $sql->execute([$id]);
    }

    public function atualizar($id, $dados)
    {
        $sql = $this->db->prepare("

            UPDATE meta_contas SET

                CLI_ID = ?,

                MTA_Nome = ?,

                MTA_PhoneNumberId = ?,

                MTA_WabaId = ?,

                MTA_Token = ?,

                MTA_UrlBase = ?,

                MTA_NumeroTelefone = ?

            WHERE MTA_ID = ?

        ");





        return $sql->execute([

            $dados['cliente'],

            $dados['nome'],

            $dados['phone_number_id'],

            $dados['waba_id'],

            $dados['token'],

            $dados['url_base'],

            $dados['numero'],

            $id

        ]);
    }

    public function listarPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM meta_contas

            WHERE CLI_ID = ?
            AND MTA_Ativo = 'S'

            ORDER BY MTA_ID DESC

        ");





        $sql->execute([
            $clienteId
        ]);





        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

}
