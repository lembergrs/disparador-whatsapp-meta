<?php

namespace Models;

use Core\Database;
use PDO;

class DashboardAdmin
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function obter()
    {
        return [
            'resumo' => $this->resumo(),
            'funil' => $this->funil(),
            'situacao' => $this->situacao(),
            'cadastros' => $this->cadastros30Dias(),
            'pagamentos' => $this->primeirosPagamentos30Dias(),
            'assinaturas' => $this->assinaturas6Meses(),
            'ultimosClientes' => $this->ultimosClientes(),
            'contasMetaRecentes' => $this->contasMetaRecentes()
        ];
    }

    private function valor($sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function resumo()
    {
        $novos = (int) $this->valor("SELECT COUNT(*) FROM clientes WHERE CLI_DataCadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $novosAnterior = (int) $this->valor("SELECT COUNT(*) FROM clientes WHERE CLI_DataCadastro >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND CLI_DataCadastro < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $meta = (int) $this->valor("SELECT COUNT(DISTINCT CLI_ID) FROM meta_contas WHERE MTA_Ativo='S' AND MTA_Status='conectado'");
        $trial = (int) $this->valor("SELECT COUNT(*) FROM clientes WHERE CLI_Ativo='S' AND CLI_StatusCadastro='ativo' AND CLI_StatusPagamento='pendente' AND CLI_DataLiberacao IS NOT NULL AND CLI_DataLiberacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $ativas = (int) $this->valor("SELECT COUNT(*) FROM assinaturas WHERE ASS_Status='ativa'");
        $pagantes = (int) $this->valor("SELECT COUNT(*) FROM (SELECT CLI_ID, MIN(COB_DataPagamento) primeira FROM cobrancas WHERE COB_Status='pago' AND COB_Tipo='mensalidade' AND COB_DataPagamento IS NOT NULL GROUP BY CLI_ID HAVING primeira >= DATE_SUB(NOW(), INTERVAL 30 DAY)) x");
        $mrr = (float) $this->valor("SELECT COALESCE(SUM(CASE ASS_Ciclo WHEN 'trimestral' THEN ASS_Valor/3 WHEN 'semestral' THEN ASS_Valor/6 WHEN 'anual' THEN ASS_Valor/12 ELSE ASS_Valor END),0) FROM assinaturas WHERE ASS_Status='ativa'");

        return [
            'novos30'=>$novos,
            'novosAnterior'=>$novosAnterior,
            'metaConectadas'=>$meta,
            'emAvaliacao'=>$trial,
            'assinaturasAtivas'=>$ativas,
            'novosPagantes30'=>$pagantes,
            'mrr'=>$mrr
        ];
    }

    private function funil()
    {
        return [
            'cadastros'=>(int)$this->valor("SELECT COUNT(*) FROM clientes"),
            'contasCriadas'=>(int)$this->valor("SELECT COUNT(DISTINCT CLI_ID) FROM meta_contas"),
            'metaConectada'=>(int)$this->valor("SELECT COUNT(DISTINCT CLI_ID) FROM meta_contas WHERE MTA_Ativo='S' AND MTA_Status='conectado'"),
            'avaliacao'=>(int)$this->valor("SELECT COUNT(*) FROM clientes WHERE CLI_DataLiberacao IS NOT NULL"),
            'pagamento'=>(int)$this->valor("SELECT COUNT(DISTINCT CLI_ID) FROM cobrancas WHERE COB_Status='pago' AND COB_Tipo='mensalidade' AND COB_DataPagamento IS NOT NULL"),
            'assinaturaAtiva'=>(int)$this->valor("SELECT COUNT(DISTINCT CLI_ID) FROM assinaturas WHERE ASS_Status='ativa'")
        ];
    }

    private function situacao()
    {
        return [
            'ativa'=>(int)$this->valor("SELECT COUNT(DISTINCT CLI_ID) FROM assinaturas WHERE ASS_Status='ativa'"),
            'avaliacao'=>(int)$this->valor("SELECT COUNT(*) FROM clientes c WHERE c.CLI_Ativo='S' AND c.CLI_StatusCadastro='ativo' AND c.CLI_StatusPagamento='pendente' AND c.CLI_DataLiberacao IS NOT NULL AND c.CLI_DataLiberacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
            'meta'=>(int)$this->valor("SELECT COUNT(*) FROM clientes c WHERE c.CLI_Ativo='S' AND c.CLI_StatusCadastro='ativo' AND c.CLI_DataLiberacao IS NULL AND EXISTS(SELECT 1 FROM meta_contas m WHERE m.CLI_ID=c.CLI_ID AND m.MTA_Ativo='S' AND m.MTA_Status='conectado')"),
            'aguardandoMeta'=>(int)$this->valor("SELECT COUNT(*) FROM clientes c WHERE c.CLI_Ativo='S' AND c.CLI_StatusCadastro='ativo' AND NOT EXISTS(SELECT 1 FROM meta_contas m WHERE m.CLI_ID=c.CLI_ID AND m.MTA_Ativo='S' AND m.MTA_Status='conectado')"),
            'pendente'=>(int)$this->valor("SELECT COUNT(*) FROM clientes WHERE CLI_StatusCadastro='pendente'"),
            'inativo'=>(int)$this->valor("SELECT COUNT(*) FROM clientes WHERE CLI_StatusCadastro IN ('inativo','suspenso') OR CLI_Ativo='N'")
        ];
    }

    private function serie30Dias($sql)
    {
        $stmt = $this->db->query($sql);
        $dados = [];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){ $dados[$row['dia']] = (int)$row['total']; }
        $labels=[]; $valores=[];
        for($i=29;$i>=0;$i--){
            $data=date('Y-m-d', strtotime("-{$i} days"));
            $labels[]=date('d/m', strtotime($data));
            $valores[]=$dados[$data] ?? 0;
        }
        return ['labels'=>$labels,'valores'=>$valores];
    }

    private function cadastros30Dias()
    {
        return $this->serie30Dias("SELECT DATE(CLI_DataCadastro) dia, COUNT(*) total FROM clientes WHERE CLI_DataCadastro >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(CLI_DataCadastro)");
    }

    private function primeirosPagamentos30Dias()
    {
        return $this->serie30Dias("SELECT DATE(primeira) dia, COUNT(*) total FROM (SELECT CLI_ID, MIN(COB_DataPagamento) primeira FROM cobrancas WHERE COB_Status='pago' AND COB_Tipo='mensalidade' AND COB_DataPagamento IS NOT NULL GROUP BY CLI_ID) p WHERE primeira >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(primeira)");
    }

    private function assinaturas6Meses()
    {
        $labels=[]; $novas=[]; $canceladas=[];
        for($i=5;$i>=0;$i--){
            $inicio=date('Y-m-01', strtotime("-{$i} months"));
            $fim=date('Y-m-01', strtotime($inicio.' +1 month'));
            $meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
            $labels[]=$meses[(int)date('n', strtotime($inicio))].'/'.date('y', strtotime($inicio));
            $novas[]=(int)$this->valor("SELECT COUNT(*) FROM assinaturas WHERE ASS_DataCadastro >= ? AND ASS_DataCadastro < ?", [$inicio,$fim]);
            $canceladas[]=(int)$this->valor("SELECT COUNT(*) FROM assinaturas WHERE ASS_Status='cancelada' AND ASS_DataAtualizacao >= ? AND ASS_DataAtualizacao < ?", [$inicio,$fim]);
        }
        return ['labels'=>$labels,'novas'=>$novas,'canceladas'=>$canceladas];
    }

    private function ultimosClientes()
    {
        return $this->db->query("SELECT c.CLI_ID,c.CLI_Nome,c.CLI_NomeFantasia,c.CLI_DataCadastro,c.CLI_StatusCadastro,c.CLI_StatusPagamento,c.CLI_DataLiberacao,
            EXISTS(SELECT 1 FROM meta_contas m WHERE m.CLI_ID=c.CLI_ID AND m.MTA_Ativo='S' AND m.MTA_Status='conectado') meta_conectada,
            (SELECT p.PLA_Nome FROM assinaturas a JOIN planos p ON p.PLA_ID=a.PLA_ID WHERE a.CLI_ID=c.CLI_ID ORDER BY a.ASS_ID DESC LIMIT 1) plano,
            (SELECT a.ASS_Status FROM assinaturas a WHERE a.CLI_ID=c.CLI_ID ORDER BY a.ASS_ID DESC LIMIT 1) assinatura_status
            FROM clientes c ORDER BY c.CLI_DataCadastro DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function contasMetaRecentes()
    {
        return $this->db->query("SELECT m.MTA_ID,m.CLI_ID,m.MTA_NumeroTelefone,m.MTA_DisplayName,m.MTA_Status,m.MTA_DataCadastro,c.CLI_Nome
            FROM meta_contas m JOIN clientes c ON c.CLI_ID=m.CLI_ID WHERE m.MTA_Ativo='S' ORDER BY m.MTA_DataCadastro DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    }
}
