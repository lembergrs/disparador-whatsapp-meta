<?php

namespace Models;

use Core\Database;
use PDO;

class MetaPricingReport
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function opcoesFiltros()
    {
        $clientes = $this->db->query("SELECT CLI_ID, COALESCE(NULLIF(CLI_NomeFantasia,''), NULLIF(CLI_RazaoSocial,''), CLI_Nome, CLI_Email) AS nome FROM clientes ORDER BY nome, CLI_ID")->fetchAll(PDO::FETCH_ASSOC);
        $contas = $this->db->query("SELECT m.MTA_ID, m.CLI_ID, m.MTA_Nome, m.MTA_NumeroTelefone, COALESCE(NULLIF(c.CLI_NomeFantasia,''), NULLIF(c.CLI_RazaoSocial,''), c.CLI_Nome, c.CLI_Email) AS cliente FROM meta_contas m INNER JOIN clientes c ON c.CLI_ID=m.CLI_ID ORDER BY cliente, m.MTA_Nome, m.MTA_ID")->fetchAll(PDO::FETCH_ASSOC);
        $categorias = $this->db->query("SELECT DISTINCT MSG_MetaCategoria categoria FROM conversa_mensagens WHERE MSG_MetaCategoria IS NOT NULL AND MSG_MetaCategoria<>'' ORDER BY MSG_MetaCategoria")->fetchAll(PDO::FETCH_COLUMN);
        return ['clientes'=>$clientes, 'contas'=>$contas, 'categorias'=>$categorias];
    }

    public function resumo(array $filtros)
    {
        [$deduplicada,$params] = $this->consultaDeduplicada($filtros);
        [$where,$externos] = $this->filtrosExternos($filtros);
        $sql = $this->db->prepare("SELECT COUNT(*) total, SUM(CASE WHEN Categoria IS NOT NULL OR Billable IS NOT NULL OR PricingModel IS NOT NULL OR PricingType IS NOT NULL OR PricingMarket IS NOT NULL OR PricingCurrency IS NOT NULL THEN 1 ELSE 0 END) com_pricing, SUM(CASE WHEN Billable=1 THEN 1 ELSE 0 END) faturaveis, SUM(CASE WHEN Billable=0 THEN 1 ELSE 0 END) nao_faturaveis, SUM(CASE WHEN Billable IS NULL THEN 1 ELSE 0 END) sem_informacao FROM ({$deduplicada}) d {$where}");
        $sql->execute(array_merge($params,$externos));
        $linha = $sql->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach(['total','com_pricing','faturaveis','nao_faturaveis','sem_informacao'] as $campo) $linha[$campo]=(int)($linha[$campo]??0);
        return $linha;
    }

    public function porCategoria(array $filtros)
    {
        [$deduplicada,$params] = $this->consultaDeduplicada($filtros);
        [$where,$externos] = $this->filtrosExternos($filtros);
        $sql = $this->db->prepare("SELECT Categoria, COUNT(*) total, SUM(CASE WHEN Billable=1 THEN 1 ELSE 0 END) faturaveis, SUM(CASE WHEN Billable=0 THEN 1 ELSE 0 END) nao_faturaveis, SUM(CASE WHEN Billable IS NULL THEN 1 ELSE 0 END) sem_informacao FROM ({$deduplicada}) d {$where} GROUP BY Categoria ORDER BY total DESC, Categoria");
        $sql->execute(array_merge($params,$externos));
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function porPricingType(array $filtros)
    {
        [$deduplicada,$params] = $this->consultaDeduplicada($filtros);
        [$where,$externos] = $this->filtrosExternos($filtros);
        $sql = $this->db->prepare("SELECT PricingType, COUNT(*) total FROM ({$deduplicada}) d {$where} GROUP BY PricingType ORDER BY total DESC, PricingType");
        $sql->execute(array_merge($params,$externos));
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarDetalhes(array $filtros, $busca = '')
    {
        [$sql,$params] = $this->consultaDetalhes($filtros,$busca);
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM ({$sql}) x"); $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function listarDetalhes(array $filtros, $busca, $inicio, $limite, $ordem = 'DataHora', $direcao = 'DESC')
    {
        [$sql,$params] = $this->consultaDetalhes($filtros,$busca);
        $permitidas=['DataHora','Cliente','ContaMeta','Destino','TipoMensagem','Categoria','Billable','PricingModel','PricingType','PricingMarket','PricingCurrency','StatusRank','MetaMessageId'];
        if(!in_array($ordem,$permitidas,true)) $ordem='DataHora';
        $direcao=strtoupper((string)$direcao)==='ASC'?'ASC':'DESC';
        $inicio=max(0,(int)$inicio); $limite=max(10,min(100,(int)$limite));
        $stmt=$this->db->prepare("SELECT * FROM ({$sql}) x ORDER BY {$ordem} {$direcao}, MTA_ID, MetaMessageId LIMIT {$limite} OFFSET {$inicio}");
        $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function consultaDetalhes(array $filtros, $busca)
    {
        [$deduplicada,$params]=$this->consultaDeduplicada($filtros);
        [$where,$externos]=$this->filtrosExternos($filtros);
        $condicoes=$where ? [substr($where,6)] : [];
        $busca=trim((string)$busca);
        if($busca!==''){
            $condicoes[]='(Cliente LIKE ? OR ContaMeta LIKE ? OR Destino LIKE ? OR Categoria LIKE ? OR PricingModel LIKE ? OR PricingType LIKE ? OR MetaMessageId LIKE ?)';
            $termo='%'.$busca.'%'; $externos=array_merge($externos,array_fill(0,7,$termo));
        }
        return ["SELECT * FROM ({$deduplicada}) d".($condicoes?' WHERE '.implode(' AND ',$condicoes):''),array_merge($params,$externos)];
    }

    private function consultaDeduplicada(array $filtros)
    {
        $where=["m.MSG_Direcao='enviada'","m.MSG_MetaMessageId IS NOT NULL","m.MSG_MetaMessageId<>''"];
        $params=[];
        if(!empty($filtros['meta_id'])){ $where[]='c.MTA_ID=?'; $params[]=(int)$filtros['meta_id']; }
        if(!empty($filtros['cliente_id'])){ $where[]='c.CLI_ID=?'; $params[]=(int)$filtros['cliente_id']; }
        $score="((m.MSG_MetaCategoria IS NOT NULL)+(m.MSG_PricingBillable IS NOT NULL)+(m.MSG_PricingModel IS NOT NULL)+(m.MSG_PricingType IS NOT NULL)+(m.MSG_PricingMarket IS NOT NULL)+(m.MSG_PricingCurrency IS NOT NULL))";
        $candidatas="SELECT m.MSG_ID, ROW_NUMBER() OVER (PARTITION BY c.MTA_ID,m.MSG_MetaMessageId ORDER BY {$score} DESC, m.MSG_AtualizadoEm DESC, m.MSG_ID DESC) CanonicalRank FROM conversa_mensagens m INNER JOIN conversas c ON c.CVS_ID=m.CVS_ID WHERE ".implode(' AND ',$where);
        $sql="SELECT c.MTA_ID, m.MSG_MetaMessageId MetaMessageId, COALESCE(m.MSG_EnviadaEm,m.MSG_DataMensagem) DataHora, c.CLI_ID, COALESCE(NULLIF(cli.CLI_NomeFantasia,''),NULLIF(cli.CLI_RazaoSocial,''),cli.CLI_Nome,cli.CLI_Email) Cliente, mc.MTA_Nome ContaMeta, mc.MTA_NumeroTelefone ContaNumero, c.CVS_Numero Destino, m.MSG_Tipo TipoMensagem, NULLIF(m.MSG_MetaCategoria,'') Categoria, m.MSG_PricingBillable Billable, NULLIF(m.MSG_PricingModel,'') PricingModel, NULLIF(m.MSG_PricingType,'') PricingType, NULLIF(m.MSG_PricingMarket,'') PricingMarket, NULLIF(m.MSG_PricingCurrency,'') PricingCurrency, CASE WHEN m.MSG_Status IN ('read','lido') THEN 4 WHEN m.MSG_Status IN ('delivered','entregue') THEN 3 WHEN m.MSG_Status IN ('sent','enviado') THEN 2 WHEN m.MSG_Status IN ('failed','erro','falha') THEN 1 ELSE 0 END StatusRank FROM ({$candidatas}) candidatas INNER JOIN conversa_mensagens m ON m.MSG_ID=candidatas.MSG_ID INNER JOIN conversas c ON c.CVS_ID=m.CVS_ID INNER JOIN meta_contas mc ON mc.MTA_ID=c.MTA_ID INNER JOIN clientes cli ON cli.CLI_ID=c.CLI_ID WHERE candidatas.CanonicalRank=1";
        return [$sql,$params];
    }

    private function filtrosExternos(array $filtros)
    {
        $where=[]; $params=[];
        if(!empty($filtros['data_inicial'])){ $where[]='DataHora>=?'; $params[]=$filtros['data_inicial'].' 00:00:00'; }
        if(!empty($filtros['data_final_exclusiva'])){ $where[]='DataHora<?'; $params[]=$filtros['data_final_exclusiva'].' 00:00:00'; }
        if(($filtros['categoria']??'')!==''){ if($filtros['categoria']==='__null__') $where[]='Categoria IS NULL'; else { $where[]='Categoria=?'; $params[]=$filtros['categoria']; } }
        if(($filtros['billable']??'')!==''){
            if($filtros['billable']==='null') $where[]='Billable IS NULL';
            elseif(in_array((string)$filtros['billable'],['0','1'],true)){ $where[]='Billable=?'; $params[]=(int)$filtros['billable']; }
        }
        return [$where?' WHERE '.implode(' AND ',$where):'',$params];
    }
}
