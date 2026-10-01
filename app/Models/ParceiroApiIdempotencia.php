<?php
namespace Models;

use Core\Database;
use PDO;

class ParceiroApiIdempotencia
{
    private $db;
    public function __construct($db=null){$this->db=$db ?: Database::getInstance();}

    public function iniciar($parceiroId,$chave,$requestHash)
    {
        try{
            $q=$this->db->prepare("INSERT INTO parceiro_api_idempotencias (PAR_ID,PAI_Chave,PAI_RequestHash) VALUES (?,?,?)");
            $q->execute([(int)$parceiroId,$chave,$requestHash]);
            return ['novo'=>true,'registro'=>null];
        }catch(\PDOException $e){
            if((string)$e->getCode()!=='23000') throw $e;
            $q=$this->db->prepare("SELECT PAI_RequestHash,PAI_Status,PAI_HttpStatus,PAI_Resposta FROM parceiro_api_idempotencias WHERE PAR_ID=? AND PAI_Chave=? LIMIT 1");
            $q->execute([(int)$parceiroId,$chave]);
            return ['novo'=>false,'registro'=>$q->fetch(PDO::FETCH_ASSOC) ?: null];
        }
    }

    public function concluir($parceiroId,$chave,$httpStatus,array $resposta)
    {
        $json=json_encode($resposta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $q=$this->db->prepare("UPDATE parceiro_api_idempotencias SET PAI_Status='concluido',PAI_HttpStatus=?,PAI_Resposta=? WHERE PAR_ID=? AND PAI_Chave=?");
        $q->execute([(int)$httpStatus,$json,(int)$parceiroId,$chave]);
    }

    public function removerProcessando($parceiroId,$chave)
    {
        $q=$this->db->prepare("DELETE FROM parceiro_api_idempotencias WHERE PAR_ID=? AND PAI_Chave=? AND PAI_Status='processando'");
        $q->execute([(int)$parceiroId,$chave]);
    }
}
