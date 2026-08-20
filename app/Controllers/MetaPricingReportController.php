<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Models\MetaPricingReport;

class MetaPricingReportController extends Controller
{
    private $relatorio;

    public function __construct(){ $this->relatorio=new MetaPricingReport(); }

    public function index()
    {
        Auth::admin();
        $filtros=$this->filtros();
        $opcoes=$this->relatorio->opcoesFiltros();
        $categorias=array_values(array_unique(array_merge(['marketing','utility','authentication','service'],$opcoes['categorias'])));
        $this->view('meta_pricing_report/index',[
            'titulo'=>'Pricing Meta','filtros'=>$filtros,'opcoes'=>$opcoes,'categorias'=>$categorias,
            'resumo'=>$this->relatorio->resumo($filtros),
            'porCategoria'=>$this->relatorio->porCategoria($filtros),
            'porPricingType'=>$this->relatorio->porPricingType($filtros)
        ]);
    }

    public function dados()
    {
        Auth::admin();
        $filtros=$this->filtros(); $busca=$_GET['search']['value']??'';
        $inicio=(int)($_GET['start']??0); $limite=(int)($_GET['length']??25);
        $colunas=['DataHora','Cliente','ContaMeta','Destino','TipoMensagem','Categoria','Billable','PricingModel','PricingType','PricingMarket','PricingCurrency','StatusRank','MetaMessageId'];
        $indice=(int)($_GET['order'][0]['column']??0); $ordem=$colunas[$indice]??'DataHora'; $direcao=$_GET['order'][0]['dir']??'desc';
        $total=$this->relatorio->contarDetalhes($filtros,'');
        $filtrado=trim((string)$busca)===''?$total:$this->relatorio->contarDetalhes($filtros,$busca);
        $linhas=array_map([$this,'linha'],$this->relatorio->listarDetalhes($filtros,$busca,$inicio,$limite,$ordem,$direcao));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['draw'=>(int)($_GET['draw']??0),'recordsTotal'=>$total,'recordsFiltered'=>$filtrado,'data'=>$linhas],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
    }

    private function filtros()
    {
        $inicio=$this->data($_GET['data_inicial']??'') ?: date('Y-m-01');
        $fim=$this->data($_GET['data_final']??'') ?: date('Y-m-d');
        if($inicio>$fim){ $temporaria=$inicio; $inicio=$fim; $fim=$temporaria; }
        $inicioData=new \DateTimeImmutable($inicio); $fimData=new \DateTimeImmutable($fim); $periodoAjustado=false;
        if((int)$inicioData->diff($fimData)->days>365){ $inicioData=$fimData->modify('-365 days'); $inicio=$inicioData->format('Y-m-d'); $periodoAjustado=true; }
        return ['data_inicial'=>$inicio,'data_final'=>$fim,'data_final_exclusiva'=>$fimData->modify('+1 day')->format('Y-m-d'),'periodo_ajustado'=>$periodoAjustado,'meta_id'=>max(0,(int)($_GET['meta_id']??0)),'cliente_id'=>max(0,(int)($_GET['cliente_id']??0)),'categoria'=>trim((string)($_GET['categoria']??'')),'billable'=>trim((string)($_GET['billable']??''))];
    }

    private function data($valor){ $valor=trim((string)$valor); $d=\DateTime::createFromFormat('Y-m-d',$valor); return $d&&$d->format('Y-m-d')===$valor?$valor:null; }
    private function e($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
    private function linha(array $r)
    {
        $billable=$r['Billable'];
        $badge=$billable===null?'<span class="badge badge-secondary">Não informado</span>':((int)$billable===1?'<span class="badge badge-primary">Sim</span>':'<span class="badge badge-info">Não</span>');
        $status=[0=>'Pendente',1=>'Falha',2=>'Enviada',3=>'Entregue',4=>'Lida'][(int)$r['StatusRank']]??'Não informado';
        $wamid=(string)$r['MetaMessageId']; $curto=mb_strlen($wamid,'UTF-8')>34?mb_substr($wamid,0,31,'UTF-8').'...':$wamid;
        return [
            'data'=>$r['DataHora']?date('d/m/Y H:i',strtotime($r['DataHora'])):'-', 'cliente'=>$this->e($r['Cliente']??'-'),
            'conta'=>$this->e(($r['ContaMeta']??'Conta Meta').(!empty($r['ContaNumero'])?' — '.$r['ContaNumero']:'')), 'destino'=>$this->e($r['Destino']??'-'),
            'tipo'=>$this->e($r['TipoMensagem']??'-'), 'categoria'=>$this->e($r['Categoria']??'Sem categoria informada'), 'billable'=>$badge,
            'modelo'=>$this->e($r['PricingModel']??'Não informado'), 'pricing_type'=>$this->e($r['PricingType']??'Não informado'),
            'market'=>$this->e($r['PricingMarket']??'Não informado'), 'currency'=>$this->e($r['PricingCurrency']??'Não informado'), 'status'=>$this->e($status),
            'wamid'=>'<code title="'.$this->e($wamid).'">'.$this->e($curto).'</code>'
        ];
    }
}
