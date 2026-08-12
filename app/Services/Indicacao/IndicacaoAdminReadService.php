<?php

namespace Services\Indicacao;

use Core\Database;
use PDO;

class IndicacaoAdminReadService
{
    private $db;
    public function __construct(PDO $db=null){$this->db=$db?:Database::getInstance();}

    public function obter(): array
    {
        return ['resumo'=>$this->resumo(),'campanhas'=>$this->campanhas(),'indicacoes'=>$this->indicacoes(),'creditos'=>$this->creditos(),'auditoria'=>$this->auditoria()];
    }

    private function resumo(): array
    {
        $i=$this->db->query("SELECT COUNT(*) total, SUM(IND_Status='aguardando_pagamento') aguardando, SUM(IND_Status='em_confirmacao') confirmacao, SUM(IND_Status='aprovada') aprovadas, SUM(IND_Status IN ('cancelada','inelegivel','fraude')) encerradas FROM indicacoes")->fetch(PDO::FETCH_ASSOC)?:[];
        $c=$this->db->query("SELECT SUM(ICR_Status='liberado') disponiveis, SUM(ICR_Status='reservado') reservados, SUM(ICR_Status='utilizado') utilizados FROM indicacao_creditos")->fetch(PDO::FETCH_ASSOC)?:[];
        $ativos=(int)$this->db->query("SELECT COUNT(*) FROM indicacao_codigos WHERE ICD_Status='ativo'")->fetchColumn();
        return ['total'=>(int)($i['total']??0),'aguardando'=>(int)($i['aguardando']??0),'confirmacao'=>(int)($i['confirmacao']??0),'aprovadas'=>(int)($i['aprovadas']??0),'encerradas'=>(int)($i['encerradas']??0),'disponiveis'=>(int)($c['disponiveis']??0),'reservados'=>(int)($c['reservados']??0),'utilizados'=>(int)($c['utilizados']??0),'codigos_ativos'=>$ativos];
    }
    private function campanhas(): array{return $this->db->query('SELECT ICP_ID,ICP_Nome,ICP_Percentual,ICP_DataInicio,ICP_DataFim,ICP_Ativo,ICP_Publica,ICP_CriadoEm FROM indicacao_campanhas ORDER BY ICP_CriadoEm DESC,ICP_ID DESC')->fetchAll(PDO::FETCH_ASSOC);}
    private function indicacoes(): array{return $this->db->query("SELECT i.IND_ID,ind.CLI_Nome indicador_nome,ref.CLI_Nome indicado_nome,i.IND_Status,i.IND_CadastradaEm,i.IND_PagamentoConfirmadoEm,i.IND_ConfirmacaoAte,i.IND_AprovadaEm,c.ICR_Status FROM indicacoes i JOIN clientes ind ON ind.CLI_ID=i.CLI_Indicador_ID JOIN clientes ref ON ref.CLI_ID=i.CLI_Indicado_ID LEFT JOIN indicacao_creditos c ON c.IND_ID=i.IND_ID ORDER BY i.IND_CadastradaEm DESC,i.IND_ID DESC")->fetchAll(PDO::FETCH_ASSOC);}
    private function creditos(): array{return $this->db->query("SELECT c.ICR_ID,ind.CLI_Nome indicador_nome,ref.CLI_Nome indicado_nome,c.ICR_Percentual,c.ICR_Status,c.ICR_CriadoEm,c.ICR_LiberadoEm,r.ICRR_Status reserva_status,r.ICRR_ReservadoEm,r.ICRR_UtilizadoEm FROM indicacao_creditos c JOIN indicacoes i ON i.IND_ID=c.IND_ID JOIN clientes ind ON ind.CLI_ID=c.CLI_Indicador_ID JOIN clientes ref ON ref.CLI_ID=i.CLI_Indicado_ID LEFT JOIN indicacao_credito_reservas r ON r.ICRR_ID=(SELECT r2.ICRR_ID FROM indicacao_credito_reservas r2 WHERE r2.ICR_ID=c.ICR_ID ORDER BY r2.ICRR_ID DESC LIMIT 1) ORDER BY c.ICR_CriadoEm DESC,c.ICR_ID DESC")->fetchAll(PDO::FETCH_ASSOC);}
    private function auditoria(): array{return $this->db->query("SELECT a.IAU_CriadoEm,a.IAU_Entidade,a.IAU_Acao,a.IAU_StatusAnterior,a.IAU_StatusNovo,a.IAU_Motivo,u.USU_Nome FROM indicacao_auditoria a LEFT JOIN usuarios u ON u.USU_ID=a.USU_ID ORDER BY a.IAU_CriadoEm DESC,a.IAU_ID DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);}
}
