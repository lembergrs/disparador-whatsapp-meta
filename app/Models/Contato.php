<?php

namespace Models;

use Core\Database;
use PDO;

class Contato
{
    private $db;

    public function __construct()
    {
        $this->db =
            Database::getInstance();
    }

    public function listarPorCliente($clienteID)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM contatos
            WHERE CLI_ID = ?
            ORDER BY CON_ID DESC
        ");

        $sql->execute([$clienteID]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvar($dados)
    {
        $sql = $this->db->prepare("
            INSERT INTO contatos (
                CLI_ID,
                CON_Nome,
                CON_Telefone,
                CON_DadosJson
            ) VALUES (
                ?, ?, ?, ?
            )
        ");

        $sql->execute([
            $dados['cliente_id'],
            $dados['nome'],
            $dados['telefone'],
            $dados['dados_json']
        ]);

        return $this->db->lastInsertId();
    }

    public function telefoneExiste(
        $clienteID,
        $telefone
    ){

        $sql = $this->db->prepare("
            SELECT CON_ID
            FROM contatos
            WHERE CLI_ID = ?
            AND CON_Telefone = ?
            LIMIT 1
        ");

        $sql->execute([
            $clienteID,
            $telefone
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }


    public function listarIdsPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT CON_ID

            FROM contatos

            WHERE CLI_ID = ?
            AND CON_Ativo = 'S'

        ");

        $sql->execute([
            $clienteId
        ]);

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function camposJsonPorCliente($clienteId)
    {
        $sql = $this->db->prepare("

            SELECT CON_DadosJson

            FROM contatos

            WHERE CLI_ID = ?
            AND CON_Ativo = 'S'
            AND CON_DadosJson IS NOT NULL

            ORDER BY CON_ID DESC

            LIMIT 1

        ");

        $sql->execute([
            $clienteId
        ]);

        $contato =
            $sql->fetch(PDO::FETCH_ASSOC);

        if(!$contato){
            return [];
        }

        $dados =
            json_decode(
                $contato['CON_DadosJson'],
                true
            );

        if(!is_array($dados)){
            return [];
        }

        return array_keys($dados);
    }

    public function buscarPorTelefone($clienteId, $telefone)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM contatos
            WHERE CLI_ID = ?
            AND CON_Telefone = ?
            LIMIT 1
        ");

        $sql->execute([
            $clienteId,
            $telefone
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }



    public function buscarPorClienteId($clienteId, $contatoId)
    {
        $sql = $this->db->prepare("\n            SELECT *\n            FROM contatos\n            WHERE CLI_ID = ?\n            AND CON_ID = ?\n            AND CON_Ativo = 'S'\n            LIMIT 1\n        ");

        $sql->execute([
            (int) $clienteId,
            (int) $contatoId
        ]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function pesquisarPorUsuarioMeta($usuario, $metaId, $termo = '', $limite = 20, $pagina = 1)
    {
        $metaIds = \Core\Auth::idsContasMetaPermitidas($usuario);
        $clienteId = (int) ($usuario['CLI_ID'] ?? ($usuario['cliente_id'] ?? 0));
        $metaId = (int) $metaId;

        if($clienteId <= 0 || empty($metaIds) || !in_array($metaId, $metaIds, true)){
            return [];
        }

        $limite = max(1, min((int) $limite, 30));
        $pagina = max(1, (int) $pagina);
        $offset = ($pagina - 1) * $limite;
        $termo = trim((string) $termo);
        $digitos = preg_replace('/\D/', '', $termo);

        $where = ["CLI_ID = ?", "CON_Ativo = 'S'"];
        $params = [$clienteId];

        if($termo !== ''){
            $condicoes = ['CON_Nome LIKE ?'];
            $params[] = '%' . $termo . '%';

            if($digitos !== ''){
                $condicoes[] = 'CON_Telefone LIKE ?';
                $params[] = '%' . $digitos . '%';

                if(substr($digitos, 0, 2) === '55'){
                    $semDdi = substr($digitos, 2);
                    if($semDdi !== ''){
                        $condicoes[] = 'CON_Telefone LIKE ?';
                        $params[] = '%' . $semDdi . '%';
                    }
                }elseif(strlen($digitos) >= 10){
                    $condicoes[] = 'CON_Telefone LIKE ?';
                    $params[] = '%55' . $digitos . '%';
                }
            }

            $where[] = '(' . implode(' OR ', $condicoes) . ')';
        }

        $sql = $this->db->prepare("\n            SELECT CON_ID, CON_Nome, CON_Telefone\n            FROM contatos\n            WHERE " . implode(' AND ', $where) . "\n            ORDER BY CON_Nome ASC, CON_ID DESC\n            LIMIT ? OFFSET ?\n        ");

        $pos = 1;
        foreach($params as $param){
            $sql->bindValue($pos++, $param);
        }
        $sql->bindValue($pos++, $limite, PDO::PARAM_INT);
        $sql->bindValue($pos, $offset, PDO::PARAM_INT);
        $sql->execute();

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

}
