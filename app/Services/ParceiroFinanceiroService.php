<?php
namespace Services;

use Core\Database;
use Models\Cobranca;
use Models\ParceiroFinanceiro;

class ParceiroFinanceiroService
{
    private $db;
    private $financeiro;
    private $cobrancas;

    public function __construct($financeiro=null,$cobrancas=null,$db=null)
    {
        $this->db=$db ?: Database::getInstance();
        $this->financeiro=$financeiro ?: new ParceiroFinanceiro($this->db);
        $this->cobrancas=$cobrancas ?: new Cobranca();
    }

    public function calcularCompetencia($parceiroId,$competencia=null)
    {
        $competencia=$competencia ?: date('Ym');
        $assinatura=$this->financeiro->assinaturaAtiva($parceiroId);
        if(!$assinatura){ throw new \DomainException('Parceiro sem assinatura financeira.'); }

        $quantidade=$this->financeiro->contarClientesFaturaveis($parceiroId);
        $plano=$this->financeiro->planoPorQuantidade(max(1,$quantidade));
        if(!$plano){ throw new \DomainException('Nenhuma faixa Partner configurada para a quantidade atual de clientes.'); }

        $id=$this->financeiro->registrarCompetencia($assinatura['PAS_ID'],$parceiroId,$competencia,$quantidade,$plano);
        return ['competencia_id'=>$id,'clientes_faturaveis'=>$quantidade,'plano'=>$plano,'valor'=>(float)$plano['PPL_Valor']];
    }

    public function criarCobrancaImplantacao($parceiroId,$valor,$vencimento)
    {
        $parceiro=$this->buscarParceiro($parceiroId);
        $assinatura=$this->financeiro->assinaturaAtiva($parceiroId);
        if(!$assinatura){ throw new \DomainException('Crie a assinatura Partner antes da cobrança de implantação.'); }
        return (int)$this->cobrancas->criar([
            'cliente'=>(int)$parceiro['CLI_ID'],'plano'=>null,'valor'=>$valor,'vencimento'=>$vencimento,
            'tipo'=>'implantacao_partner','parceiro'=>$parceiroId,'assinatura_partner'=>(int)$assinatura['PAS_ID'],
            'origem'=>'partner_api','provider'=>'asaas','provider_status'=>'local_pendente'
        ]);
    }

    public function criarCobrancaMensal($parceiroId,$competencia=null,$vencimento=null)
    {
        $calculo=$this->calcularCompetencia($parceiroId,$competencia);
        $snapshot=$this->financeiro->buscarCompetencia($calculo['competencia_id']);
        if(!empty($snapshot['COB_ID'])){ return (int)$snapshot['COB_ID']; }

        $parceiro=$this->buscarParceiro($parceiroId);
        $assinatura=$this->financeiro->assinaturaAtiva($parceiroId);
        $cobrancaId=(int)$this->cobrancas->criar([
            'cliente'=>(int)$parceiro['CLI_ID'],'plano'=>null,'valor'=>$calculo['valor'],
            'vencimento'=>$vencimento ?: date('Y-m-d',strtotime('+3 days')),
            'tipo'=>'mensalidade_partner','parceiro'=>$parceiroId,'assinatura_partner'=>(int)$assinatura['PAS_ID'],
            'origem'=>'partner_api','provider'=>'asaas','provider_status'=>'local_pendente'
        ]);
        $this->financeiro->vincularCobrancaCompetencia($calculo['competencia_id'],$cobrancaId);
        return $cobrancaId;
    }

    private function buscarParceiro($id)
    {
        $sql=$this->db->prepare("SELECT * FROM parceiros_api WHERE PAR_ID=? AND PAR_Ativo='S' LIMIT 1");
        $sql->execute([(int)$id]);
        $p=$sql->fetch(\PDO::FETCH_ASSOC);
        if(!$p){ throw new \DomainException('Parceiro não encontrado ou inativo.'); }
        return $p;
    }
}
