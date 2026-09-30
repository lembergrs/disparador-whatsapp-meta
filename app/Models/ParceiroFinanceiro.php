<?php
namespace Models;

use Core\Database;
use PDO;

class ParceiroFinanceiro
{
    private $db;
    public function __construct($db=null){ $this->db=$db ?: Database::getInstance(); }

    public function listarPlanosAdmin()
    {
        return $this->db->query("SELECT * FROM parceiro_planos ORDER BY PPL_Ativo DESC,PPL_MinClientes ASC,PPL_ID ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarPlanoAdmin($nome,$min,$max,$valor)
    {
        $min=max(1,(int)$min);
        $max=$max === null || $max === '' ? null : (int)$max;
        if($max !== null && $max < $min){ throw new \DomainException('O máximo de clientes não pode ser menor que o mínimo.'); }
        if((float)$valor < 0){ throw new \DomainException('O valor mensal não pode ser negativo.'); }
        $fim=$max === null ? 2147483647 : $max;
        $overlap=$this->db->prepare("SELECT 1 FROM parceiro_planos WHERE PPL_Ativo='S' AND PPL_MinClientes<=? AND COALESCE(PPL_MaxClientes,2147483647)>=? LIMIT 1");
        $overlap->execute([$fim,$min]);
        if($overlap->fetchColumn()){ throw new \DomainException('Esta faixa sobrepõe outra faixa Partner ativa.'); }
        $sql=$this->db->prepare("INSERT INTO parceiro_planos (PPL_Nome,PPL_MinClientes,PPL_MaxClientes,PPL_Valor,PPL_Ativo) VALUES (?,?,?,?,'S')");
        $sql->execute([trim($nome),$min,$max,number_format((float)$valor,2,'.','')]);
        return (int)$this->db->lastInsertId();
    }

    public function inativarPlanoAdmin($id)
    {
        return $this->db->prepare("UPDATE parceiro_planos SET PPL_Ativo='N' WHERE PPL_ID=?")->execute([(int)$id]);
    }

    public function criarOuAtualizarAssinaturaAdmin($parceiroId,$valorImplantacao,$diaVencimento)
    {
        $diaVencimento=max(1,min(28,(int)$diaVencimento));
        $assinatura=$this->assinaturaAtiva($parceiroId);
        if($assinatura){
            $sql=$this->db->prepare("UPDATE parceiro_assinaturas SET PAS_ValorImplantacao=?,PAS_DiaVencimento=? WHERE PAS_ID=? AND PAR_ID=?");
            $sql->execute([number_format((float)$valorImplantacao,2,'.',''),$diaVencimento,(int)$assinatura['PAS_ID'],(int)$parceiroId]);
            return (int)$assinatura['PAS_ID'];
        }
        $sql=$this->db->prepare("INSERT INTO parceiro_assinaturas (PAR_ID,PAS_Status,PAS_ValorImplantacao,PAS_ValorMensal,PAS_DiaVencimento) VALUES (?,'pendente',?,0,?)");
        $sql->execute([(int)$parceiroId,number_format((float)$valorImplantacao,2,'.',''),$diaVencimento]);
        return (int)$this->db->lastInsertId();
    }

    public function listarCobrancasAdmin($parceiroId)
    {
        $sql=$this->db->prepare("SELECT COB_ID,COB_Tipo,COB_Valor,COB_Status,COB_DataVencimento,COB_ProviderStatus,COB_LinkPagamento,COB_DataPagamento FROM cobrancas WHERE PAR_ID=? AND COB_Origem='partner_api' ORDER BY COB_ID DESC LIMIT 30");
        $sql->execute([(int)$parceiroId]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarCobrancaImplantacaoAberta($parceiroId,$assinaturaId)
    {
        $sql=$this->db->prepare("SELECT * FROM cobrancas WHERE PAR_ID=? AND PAS_ID=? AND COB_Origem='partner_api' AND COB_Tipo='implantacao_partner' AND COB_Status IN ('pendente','vencido','pago') ORDER BY COB_ID DESC LIMIT 1");
        $sql->execute([(int)$parceiroId,(int)$assinaturaId]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function contarClientesFaturaveis($parceiroId, $dataReferencia=null)
    {
        $data=$dataReferencia ?: date('Y-m-d H:i:s');
        $sql=$this->db->prepare("SELECT COUNT(DISTINCT CLI_ID) FROM parceiro_clientes WHERE PAR_ID=? AND PAC_Status='ativo' AND PAC_Ativo='S' AND PAC_FaturavelDesde IS NOT NULL AND PAC_FaturavelDesde<=? AND (PAC_FaturavelAte IS NULL OR PAC_FaturavelAte>?)");
        $sql->execute([(int)$parceiroId,$data,$data]);
        return (int)$sql->fetchColumn();
    }

    public function planoPorQuantidade($quantidade)
    {
        $sql=$this->db->prepare("SELECT * FROM parceiro_planos WHERE PPL_Ativo='S' AND PPL_MinClientes<=? AND (PPL_MaxClientes IS NULL OR PPL_MaxClientes>=?) ORDER BY PPL_MinClientes DESC LIMIT 1");
        $sql->execute([(int)$quantidade,(int)$quantidade]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function assinaturaAtiva($parceiroId)
    {
        $sql=$this->db->prepare("SELECT * FROM parceiro_assinaturas WHERE PAR_ID=? AND PAS_Status IN ('ativa','pendente') ORDER BY CASE PAS_Status WHEN 'ativa' THEN 1 ELSE 2 END,PAS_ID DESC LIMIT 1");
        $sql->execute([(int)$parceiroId]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function registrarCompetencia($assinaturaId,$parceiroId,$competencia,$quantidade,array $plano)
    {
        $sql=$this->db->prepare("INSERT INTO parceiro_faturamento_competencias (PAS_ID,PAR_ID,PFC_Competencia,PFC_ClientesFaturaveis,PPL_ID,PFC_Valor,PFC_Status) VALUES (?,?,?,?,?,?,'calculada') ON DUPLICATE KEY UPDATE PFC_ID=LAST_INSERT_ID(PFC_ID)");
        $sql->execute([(int)$assinaturaId,(int)$parceiroId,$competencia,(int)$quantidade,(int)$plano['PPL_ID'],$plano['PPL_Valor']]);
        return (int)$this->db->lastInsertId();
    }

    public function buscarCompetencia($id)
    {
        $sql=$this->db->prepare("SELECT * FROM parceiro_faturamento_competencias WHERE PFC_ID=? LIMIT 1");
        $sql->execute([(int)$id]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function vincularCobrancaCompetencia($competenciaId,$cobrancaId)
    {
        return $this->db->prepare("UPDATE parceiro_faturamento_competencias SET COB_ID=?,PFC_Status='cobrada' WHERE PFC_ID=? AND COB_ID IS NULL")->execute([(int)$cobrancaId,(int)$competenciaId]);
    }


    public function marcarPagamentoPartner(array $cobranca)
    {
        $parceiroId=(int)($cobranca['PAR_ID'] ?? 0);
        $assinaturaId=(int)($cobranca['PAS_ID'] ?? 0);
        if($parceiroId < 1 || $assinaturaId < 1){ throw new \LogicException('Cobrança Partner sem parceiro/assinatura.'); }

        $tipo=(string)($cobranca['COB_Tipo'] ?? '');
        if($tipo === 'implantacao_partner'){
            $this->db->prepare("UPDATE parceiro_assinaturas SET PAS_Status='ativa',PAS_DataInicio=COALESCE(PAS_DataInicio,CURDATE()) WHERE PAS_ID=? AND PAR_ID=? AND PAS_Status IN ('pendente','ativa')")->execute([$assinaturaId,$parceiroId]);
            $this->db->prepare("UPDATE parceiros_api SET PAR_StatusImplantacao='paga',PAR_StatusApi=IF(PAR_StatusApi='bloqueada','homologacao',PAR_StatusApi) WHERE PAR_ID=?")->execute([$parceiroId]);
            return;
        }

        if($tipo === 'mensalidade_partner'){
            $this->db->prepare("UPDATE parceiro_assinaturas SET PAS_Status='ativa',PAS_DataInicio=COALESCE(PAS_DataInicio,CURDATE()) WHERE PAS_ID=? AND PAR_ID=? AND PAS_Status IN ('pendente','ativa','suspensa')")->execute([$assinaturaId,$parceiroId]);
            $this->db->prepare("UPDATE parceiro_faturamento_competencias SET PFC_Status='paga' WHERE COB_ID=? AND PAR_ID=?")->execute([(int)$cobranca['COB_ID'],$parceiroId]);
            return;
        }

        throw new \LogicException('Tipo de cobrança Partner não reconhecido.');
    }

    public function marcarStatusCobrancaPartner(array $cobranca, $status)
    {
        if(($cobranca['COB_Origem'] ?? '') !== 'partner_api'){ return; }
        $parceiroId=(int)($cobranca['PAR_ID'] ?? 0);
        if($parceiroId < 1){ return; }

        if(($cobranca['COB_Tipo'] ?? '') === 'mensalidade_partner'){
            $pfcStatus=$status === 'cancelado' ? 'cancelada' : 'cobrada';
            $this->db->prepare("UPDATE parceiro_faturamento_competencias SET PFC_Status=? WHERE COB_ID=? AND PAR_ID=? AND PFC_Status<>'paga'")->execute([$pfcStatus,(int)$cobranca['COB_ID'],$parceiroId]);
        }
        // O vencimento isolado não suspende a API imediatamente. A suspensão Partner
        // será aplicada por uma política própria de tolerância, sem misturar o status
        // comercial da integração com o primeiro evento de atraso.
    }

    public function marcarClienteFaturavel($parceiroId,$vinculoId)
    {
        return $this->db->prepare("UPDATE parceiro_clientes SET PAC_Status='ativo',PAC_FaturavelDesde=COALESCE(PAC_FaturavelDesde,NOW()),PAC_FaturavelAte=NULL WHERE PAR_ID=? AND PAC_ID=? AND PAC_Ativo='S'")->execute([(int)$parceiroId,(int)$vinculoId]);
    }

    public function encerrarClienteFaturavel($parceiroId,$vinculoId,$status='cancelado')
    {
        if(!in_array($status,['suspenso','cancelado'],true)){ $status='cancelado'; }
        return $this->db->prepare("UPDATE parceiro_clientes SET PAC_Status=?,PAC_FaturavelAte=COALESCE(PAC_FaturavelAte,NOW()) WHERE PAR_ID=? AND PAC_ID=?")->execute([$status,(int)$parceiroId,(int)$vinculoId]);
    }
}
