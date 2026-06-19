<?php

namespace Models;

use Core\Database;
use PDO;
use PDOException;

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





    private function colunaExiste($coluna)
    {
        try{

            $sql = $this->db->prepare("

                SELECT COUNT(*)

                FROM INFORMATION_SCHEMA.COLUMNS

                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'meta_contas'
                AND COLUMN_NAME = ?

            " );

            $sql->execute([
                $coluna
            ]);

            return (int) $sql->fetchColumn() > 0;

        }catch(PDOException $e){

            return false;
        }
    }

    public function colunaWebhookVerifyTokenExiste()
    {
        return $this->colunaExiste(
            'MTA_WebhookVerifyToken'
        );
    }

    public function colunasAutoRespostaExistem()
    {
        return
            $this->colunaExiste('MTA_AutoRespostaAtiva')
            &&
            $this->colunaExiste('MTA_AutoRespostaTexto')
            &&
            $this->colunaExiste('MTA_AutoRespostaIntervaloMinutos');
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
                MTA_WebhookVerifyToken,
                MTA_AutoRespostaAtiva,
                MTA_AutoRespostaTexto,
                MTA_AutoRespostaIntervaloMinutos,
                MTA_Status,
                MTA_Ativo

            )

            VALUES
            (

                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'desconectado', 'S'

            )

        ");





        return $sql->execute([

            $dados['cliente'],

            $dados['nome'],

            $dados['phone_number_id'],

            $dados['waba_id'],

            $dados['token'],

            $dados['url_base'],

            $dados['numero'],

            $dados['webhook_verify_token'],

            $dados['auto_resposta_ativa'],

            $dados['auto_resposta_texto'],

            $dados['auto_resposta_intervalo_minutos']

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

    public function validarLimiteNumerosPlano(
        $clienteId,
        $limitePlano
    )
    {
        $utilizados =
            $this->contarAtivasPorCliente(
                $clienteId
            );

        $limitePlano =
            (int) $limitePlano;

        $permitido =
            $utilizados <= $limitePlano;

        return [
            'permitido' => $permitido,
            'utilizados' => $utilizados,
            'limite' => $limitePlano,
            'mensagem' => $permitido
                ? null
                : sprintf(
                    'Para migrar para este plano, reduza a quantidade de números conectados para no máximo %d. Atualmente sua conta possui %d números conectados.',
                    $limitePlano,
                    $utilizados
                )
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

                MTA_NumeroTelefone = ?,

                MTA_WebhookVerifyToken = ?,

                MTA_AutoRespostaAtiva = ?,

                MTA_AutoRespostaTexto = ?,

                MTA_AutoRespostaIntervaloMinutos = ?

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

            $dados['webhook_verify_token'],

            $dados['auto_resposta_ativa'],

            $dados['auto_resposta_texto'],

            $dados['auto_resposta_intervalo_minutos'],

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
