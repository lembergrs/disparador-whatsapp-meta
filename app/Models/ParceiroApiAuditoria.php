<?php
namespace Models;

use Core\Database;
use PDO;

class ParceiroApiAuditoria
{
    private $db;
    public function __construct($db=null){ $this->db=$db ?: Database::getInstance(); }

    public function registrar(array $d)
    {
        $sql=$this->db->prepare("INSERT INTO parceiro_api_auditoria
            (PAR_ID,PAK_ID,PAA_Severidade,PAA_Evento,PAA_Metodo,PAA_Endpoint,PAA_HttpStatus,PAA_Ip,PAA_ErroCodigo,PAA_ErroMensagem,PAA_Payload,PAA_PayloadTruncado)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        return $sql->execute([
            $d['parceiro_id']?:null,$d['api_key_id']?:null,$d['severidade'],$d['evento'],$d['metodo']?:null,$d['endpoint']?:null,
            $d['http_status']?:null,$d['ip']?:null,$d['erro_codigo']?:null,$d['erro_mensagem']?:null,$d['payload']?:null,$d['payload_truncado']?'S':'N'
        ]);
    }

    public function listarAdmin(array $filtros=[], $limite=200)
    {
        $where=[];$params=[];
        if(!empty($filtros['parceiro_id'])){$where[]='a.PAR_ID=?';$params[]=(int)$filtros['parceiro_id'];}
        if(!empty($filtros['evento'])){$where[]='a.PAA_Evento=?';$params[]=$filtros['evento'];}
        if(!empty($filtros['severidade'])){$where[]='a.PAA_Severidade=?';$params[]=$filtros['severidade'];}
        if(!empty($filtros['data_inicio'])){$where[]='a.PAA_DataHora>=?';$params[]=$filtros['data_inicio'].' 00:00:00';}
        if(!empty($filtros['data_fim'])){$where[]='a.PAA_DataHora<=?';$params[]=$filtros['data_fim'].' 23:59:59';}
        $sql="SELECT a.*,p.PAR_Nome,k.PAK_Nome,k.PAK_Prefixo FROM parceiro_api_auditoria a
              LEFT JOIN parceiros_api p ON p.PAR_ID=a.PAR_ID
              LEFT JOIN parceiro_api_keys k ON k.PAK_ID=a.PAK_ID";
        if($where)$sql.=' WHERE '.implode(' AND ',$where);
        $sql.=' ORDER BY a.PAA_ID DESC LIMIT '.max(1,min(500,(int)$limite));
        $st=$this->db->prepare($sql);$st->execute($params);return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
