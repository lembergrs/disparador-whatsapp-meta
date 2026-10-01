<?php
namespace Models;

use Core\Database;
use PDO;

class ParceiroWebhookEvento
{
    private $db;
    public function __construct($db=null){ $this->db=$db ?: Database::getInstance(); }

    public function parceirosDoCanal($clienteId,$metaId)
    {
        $q=$this->db->prepare("
            SELECT p.PAR_ID,p.PAR_WebhookUrl,p.PAR_WebhookEventos,p.PAR_WebhookSecretSalt
            FROM parceiro_clientes pc
            INNER JOIN parceiros_api p ON p.PAR_ID=pc.PAR_ID
            WHERE pc.CLI_ID=? AND pc.MTA_ID=? AND pc.PAC_Ativo='S' AND pc.PAC_Status='ativo'
              AND p.PAR_Ativo='S' AND p.PAR_StatusCadastro='aprovado'
              AND p.PAR_WebhookAtivo='S' AND p.PAR_WebhookUrl IS NOT NULL AND p.PAR_WebhookUrl<>''
        ");
        $q->execute([(int)$clienteId,(int)$metaId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function mensagemPartnerPorMetaId($metaId,$messageId)
    {
        $q=$this->db->prepare("SELECT m.MSG_ID,m.MSG_Origem FROM conversa_mensagens m INNER JOIN conversas c ON c.CVS_ID=m.CVS_ID WHERE c.MTA_ID=? AND m.MSG_MetaMessageId=? ORDER BY m.MSG_ID ASC LIMIT 1");
        $q->execute([(int)$metaId,trim((string)$messageId)]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function enfileirar($eventId,$parceiroId,$clienteId,$metaId,$tipo,array $payload)
    {
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        try{
            $q=$this->db->prepare("INSERT INTO parceiro_webhook_eventos (PWE_EventId,PAR_ID,CLI_ID,MTA_ID,PWE_Tipo,PWE_Payload,PWE_ProximaTentativaEm) VALUES (?,?,?,?,?,?,NOW())");
            $q->execute([$eventId,(int)$parceiroId,(int)$clienteId,(int)$metaId,$tipo,$json]);
            return ['criado'=>true,'id'=>(int)$this->db->lastInsertId()];
        }catch(\PDOException $e){
            if((string)$e->getCode()==='23000') return ['criado'=>false,'id'=>0];
            throw $e;
        }
    }

    public function recuperarTravados($minutos=15)
    {
        $q=$this->db->prepare("UPDATE parceiro_webhook_eventos SET PWE_Status='pendente',PWE_ReservadaEm=NULL,PWE_ProximaTentativaEm=NOW(),PWE_UltimoErro='reserva_expirada' WHERE PWE_Status='processando' AND PWE_ReservadaEm<DATE_SUB(NOW(),INTERVAL ? MINUTE) AND PWE_Tentativas<PWE_MaxTentativas");
        $q->execute([max(1,(int)$minutos)]);
        return $q->rowCount();
    }

    public function reservarProximo()
    {
        $this->db->beginTransaction();
        try{
            $q=$this->db->query("SELECT e.*,p.PAR_WebhookUrl,p.PAR_WebhookEventos,p.PAR_WebhookSecretSalt FROM parceiro_webhook_eventos e INNER JOIN parceiros_api p ON p.PAR_ID=e.PAR_ID WHERE e.PWE_Status='pendente' AND e.PWE_ProximaTentativaEm<=NOW() AND e.PWE_Tentativas<e.PWE_MaxTentativas AND p.PAR_Ativo='S' AND p.PAR_WebhookAtivo='S' ORDER BY e.PWE_CriadoEm,e.PWE_ID LIMIT 1 FOR UPDATE");
            $r=$q->fetch(PDO::FETCH_ASSOC);
            if(!$r){$this->db->commit();return null;}
            $u=$this->db->prepare("UPDATE parceiro_webhook_eventos SET PWE_Status='processando',PWE_Tentativas=PWE_Tentativas+1,PWE_ReservadaEm=NOW() WHERE PWE_ID=? AND PWE_Status='pendente'");
            $u->execute([(int)$r['PWE_ID']]);
            if($u->rowCount()!==1){$this->db->rollBack();return null;}
            $this->db->commit();
            $r['PWE_Tentativas']=(int)$r['PWE_Tentativas']+1;
            return $r;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function marcarEntregue($id,$httpStatus)
    {
        $q=$this->db->prepare("UPDATE parceiro_webhook_eventos SET PWE_Status='entregue',PWE_EntregueEm=NOW(),PWE_ReservadaEm=NULL,PWE_UltimoHttpStatus=?,PWE_UltimoErro=NULL WHERE PWE_ID=? AND PWE_Status='processando'");
        $q->execute([(int)$httpStatus,(int)$id]);
    }

    public function marcarFalhaOuRetry(array $evento,$httpStatus,$erro,$atrasoSegundos)
    {
        $erro=mb_substr(preg_replace('/[\r\n\t]+/',' ',(string)$erro),0,500,'UTF-8');
        $final=(int)$evento['PWE_Tentativas'] >= (int)$evento['PWE_MaxTentativas'];
        if($final){
            $q=$this->db->prepare("UPDATE parceiro_webhook_eventos SET PWE_Status='falha',PWE_ReservadaEm=NULL,PWE_UltimoHttpStatus=?,PWE_UltimoErro=? WHERE PWE_ID=?");
            $q->execute([$httpStatus ?: null,$erro,(int)$evento['PWE_ID']]);
        }else{
            $quando=date('Y-m-d H:i:s',time()+max(1,(int)$atrasoSegundos));
            $q=$this->db->prepare("UPDATE parceiro_webhook_eventos SET PWE_Status='pendente',PWE_ReservadaEm=NULL,PWE_ProximaTentativaEm=?,PWE_UltimoHttpStatus=?,PWE_UltimoErro=? WHERE PWE_ID=?");
            $q->execute([$quando,$httpStatus ?: null,$erro,(int)$evento['PWE_ID']]);
        }
    }

    public function listarPartner($parceiroId,$limite=50,$offset=0)
    {
        $limite=max(1,min(200,(int)$limite));
        $offset=max(0,(int)$offset);
        $q=$this->db->prepare("SELECT PWE_EventId,PWE_Tipo,PWE_Status,PWE_Tentativas,PWE_UltimoHttpStatus,PWE_UltimoErro,PWE_CriadoEm,PWE_EntregueEm FROM parceiro_webhook_eventos WHERE PAR_ID=? ORDER BY PWE_ID DESC LIMIT {$limite} OFFSET {$offset}");
        $q->execute([(int)$parceiroId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarPartner($parceiroId)
    {
        $q=$this->db->prepare("SELECT COUNT(*) FROM parceiro_webhook_eventos WHERE PAR_ID=?");
        $q->execute([(int)$parceiroId]);
        return (int)$q->fetchColumn();
    }
}
