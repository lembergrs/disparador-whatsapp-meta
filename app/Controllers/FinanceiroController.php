<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Models\Plano;
use Models\Cobranca;
use Models\MetaConta;
use Models\Assinatura;
use Models\NfseEmissao;
use Services\FinanceiroWorkflowService;

class FinanceiroController extends Controller
{
    public function index()
    {
        Auth::clienteAdmin();

        $usuario = Auth::usuario();

        $planoModel = new Plano();
        $cobrancaModel = new Cobranca();
        $metaContaModel = new MetaConta();
        $assinaturaModel = new Assinatura();

        $planos = $planoModel->listarAtivos();
        $numerosAtivos =
            $metaContaModel->contarAtivasPorCliente(
                $usuario['CLI_ID']
            );

        $cobranca = $cobrancaModel
            ->buscarPendentePorCliente($usuario['CLI_ID']);

        $excedenteModel =
            new \Models\ExcedenteMensal();

        $excedente =
            $excedenteModel->buscarMesAtual(
                $usuario['CLI_ID']
            );

        $assinaturaAtual =
            $assinaturaModel->buscarAtualPorCliente(
                $usuario['CLI_ID']
            );

        $ofertasPlanos = (new FinanceiroWorkflowService())->ofertasParaContratacao(
            (int) $usuario['CLI_ID'],
            $planos,
            $cobranca ?: null
        );

        $this->view(
            'financeiro/index',
            [
                'titulo' => 'Financeiro',
                'planos' => $planos,
                'cobranca' => $cobranca,
                'faturasPerPageDefault' => 5,
                'excedente' => $excedente,
                'numerosAtivos' => $numerosAtivos,
                'assinaturaAtual' => $assinaturaAtual,
                'ofertasPlanos' => $ofertasPlanos
            ]
        );
    }



    public function faturasAjax()
    {
        Auth::clienteAdmin();

        header('Content-Type: application/json; charset=utf-8');

        try{
            $usuario = Auth::usuario();
            $clienteId = (int) ($usuario['CLI_ID'] ?? 0);

            if($clienteId <= 0){
                http_response_code(403);
                echo json_encode([
                    'sucesso' => false,
                    'erro' => 'Acesso negado.'
                ]);
                return;
            }

            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
            $page = max(1, (int) $page);

            $perPage = filter_input(INPUT_GET, 'per_page', FILTER_VALIDATE_INT) ?: 5;
            $permitidos = [5, 10, 20, 50];

            if(!in_array($perPage, $permitidos, true)){
                $perPage = $perPage > 50 ? 50 : 5;
            }

            if(!in_array($perPage, $permitidos, true)){
                $perPage = 5;
            }

            $cobrancaModel = new Cobranca();
            $totalRegistros = $cobrancaModel->contarPorCliente($clienteId);
            $totalPaginas = max(1, (int) ceil($totalRegistros / $perPage));
            $page = min($page, $totalPaginas);
            $offset = ($page - 1) * $perPage;

            $faturas = $cobrancaModel->listarPorClientePaginado(
                $clienteId,
                $perPage,
                $offset
            );
            $nfsePorCobranca = (new NfseEmissao())->buscarVigentesPorCobrancas(
                array_column($faturas, 'COB_ID'),
                $clienteId
            );

            echo json_encode([
                'sucesso' => true,
                'html' => $this->renderFaturasRows($faturas, $nfsePorCobranca),
                'paginacao_html' => $this->renderFaturasPaginacao($page, $totalPaginas),
                'contador_html' => $this->renderFaturasContador($page, $perPage, $totalRegistros),
                'pagina_atual' => $page,
                'total_paginas' => $totalPaginas,
                'total_registros' => $totalRegistros,
                'per_page' => $perPage
            ]);
        }catch(\Throwable $e){
            $this->registrarErroFaturasAjax($e);

            http_response_code(500);
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Não foi possível carregar as faturas no momento.'
            ]);
        }
    }

    private function registrarErroFaturasAjax(\Throwable $e)
    {
        error_log(sprintf(
            '[financeiro/faturasAjax] exception=%s mensagem=%s arquivo=%s linha=%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    private function renderFaturasRows(array $faturas, array $nfsePorCobranca = [])
    {
        if(empty($faturas)){
            return '<tr><td colspan="8" class="text-center text-muted py-4">Nenhuma fatura encontrada.</td></tr>';
        }

        $html = '';

        foreach($faturas as $fatura){
            $statusFatura = (string) ($fatura['COB_Status'] ?? '');
            $linkPagamento = (string) ($fatura['COB_LinkPagamento'] ?? '');
            $nfse = $nfsePorCobranca[(int) ($fatura['COB_ID'] ?? 0)] ?? null;

            $html .= '<tr>';
            $html .= '<td>' . $this->formatarDataFatura($fatura['COB_DataVencimento'] ?? null) . '</td>';
            $html .= '<td>R$ ' . number_format((float) ($fatura['COB_Valor'] ?? 0), 2, ',', '.') . '</td>';
            $html .= '<td><span class="badge badge-' . $this->badgeFatura($statusFatura) . '">' . $this->e($this->statusFatura($statusFatura)) . '</span></td>';
            $html .= '<td>' . $this->e($fatura['COB_Forma'] ?? '-') . '</td>';
            $html .= '<td>' . $this->formatarDataFatura($fatura['COB_DataPagamento'] ?? null) . '</td>';
            $html .= '<td>' . $this->renderNfseStatusCliente($nfse) . '</td>';
            $html .= '<td>' . $this->renderNfseDocumentosCliente($nfse) . '</td>';
            $html .= '<td>';

            if($statusFatura === 'pendente' && $this->linkPagamentoValido($linkPagamento)){
                $html .= '<button type="button" class="btn btn-sm btn-success btn-pagar-agora" data-link-pagamento="' . $this->e($linkPagamento) . '">Pagar agora</button>';
            }else{
                $html .= '<span class="text-muted">-</span>';
            }

            $html .= '</td>';
            $html .= '</tr>';

            if(($fatura['COB_ProviderStatus'] ?? '') === 'erro_cliente'){
                $html .= '<tr><td colspan="8"><div class="alert alert-danger mb-0">Não foi possível gerar a cobrança automaticamente. Verifique os dados cadastrais ou entre em contato com o suporte.</div></td></tr>';
            }
        }

        return $html;
    }


    private function renderNfseStatusCliente($nfse)
    {
        if(empty($nfse)){
            return '<span class="badge badge-secondary">Não emitida</span>';
        }

        $status = (string) ($nfse['NFE_Status'] ?? '');
        $mapa = [
            'emitida' => ['Emitida', 'success'],
            'cancelada' => ['Cancelada', 'secondary'],
            'processando' => ['Emitindo', 'info'],
            'cancelamento_pendente' => ['Processando', 'info'],
            'reconciliacao_pendente' => ['Processando', 'info'],
            'pendente_dados' => ['Pendente', 'warning'],
            'pendente' => ['Pendente', 'warning'],
            'erro_temporario' => ['Processando nota fiscal', 'info'],
            'erro_definitivo' => ['Nota fiscal pendente', 'warning']
        ];

        [$label, $classe] = $mapa[$status] ?? ['Pendente', 'warning'];
        $tooltip = '';
        if($status === 'emitida' && !empty($nfse['NFE_DataEmissao'])){
            $tooltip = ' title="' . $this->e('Emitida em ' . date('d/m/Y \\à\\s H:i', strtotime((string) $nfse['NFE_DataEmissao']))) . '"';
        }

        return '<span class="badge badge-' . $classe . '"' . $tooltip . '>' . $this->e($label) . '</span>';
    }

    private function renderNfseDocumentosCliente($nfse)
    {
        if(empty($nfse) || (string) ($nfse['NFE_Status'] ?? '') !== 'emitida'){
            return '<span class="text-muted">-</span>';
        }

        $nfseId = (int) ($nfse['NFE_ID'] ?? 0);
        if($nfseId <= 0){
            return '<span class="text-muted">-</span>';
        }

        $links = [];
        if(!empty($nfse['tem_pdf'])){
            $links[] = '<a class="btn btn-xs btn-outline-primary" href="' . BASE_URL . '/index.php?url=nfse/pdf/' . $nfseId . '" title="Baixar PDF da NFS-e"><i class="fas fa-file-pdf"></i> PDF</a>';
        }
        if(!empty($nfse['tem_xml'])){
            $links[] = '<a class="btn btn-xs btn-outline-success" href="' . BASE_URL . '/index.php?url=nfse/xml/' . $nfseId . '" title="Baixar XML da NFS-e"><i class="fas fa-file-code"></i> XML</a>';
        }

        return !empty($links) ? implode(' ', $links) : '<span class="text-muted">Indisponível</span>';
    }

    private function renderFaturasPaginacao($paginaAtual, $totalPaginas)
    {
        if($totalPaginas <= 1){
            return '';
        }

        $html = '';
        $html .= $this->itemPaginacaoFatura('Anterior', max(1, $paginaAtual - 1), $paginaAtual <= 1);

        $inicio = max(1, $paginaAtual - 2);
        $fim = min($totalPaginas, $paginaAtual + 2);

        if($inicio > 1){
            $html .= $this->itemPaginacaoFatura('1', 1, false, $paginaAtual === 1);

            if($inicio > 2){
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        for($i = $inicio; $i <= $fim; $i++){
            $html .= $this->itemPaginacaoFatura((string) $i, $i, false, $paginaAtual === $i);
        }

        if($fim < $totalPaginas){
            if($fim < ($totalPaginas - 1)){
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }

            $html .= $this->itemPaginacaoFatura((string) $totalPaginas, $totalPaginas, false, $paginaAtual === $totalPaginas);
        }

        $html .= $this->itemPaginacaoFatura('Próxima', min($totalPaginas, $paginaAtual + 1), $paginaAtual >= $totalPaginas);

        return $html;
    }

    private function itemPaginacaoFatura($label, $pagina, $disabled = false, $active = false)
    {
        $classes = ['page-item'];

        if($disabled){
            $classes[] = 'disabled';
        }

        if($active){
            $classes[] = 'active';
        }

        if($disabled){
            return '<li class="' . implode(' ', $classes) . '"><span class="page-link">' . $this->e($label) . '</span></li>';
        }

        return '<li class="' . implode(' ', $classes) . '"><a class="page-link pagina-faturas" href="#" data-page="' . (int) $pagina . '">' . $this->e($label) . '</a></li>';
    }

    private function renderFaturasContador($paginaAtual, $perPage, $totalRegistros)
    {
        if($totalRegistros <= 0){
            return 'Mostrando 0 faturas';
        }

        $inicio = (($paginaAtual - 1) * $perPage) + 1;
        $fim = min($totalRegistros, $paginaAtual * $perPage);

        return 'Mostrando ' . $inicio . ' a ' . $fim . ' de ' . $totalRegistros . ' faturas';
    }

    private function statusFatura($status)
    {
        $mapa = [
            'pendente' => 'Pendente',
            'pago' => 'Pago',
            'vencido' => 'Vencido',
            'cancelado' => 'Cancelado',
            'erro' => 'Erro'
        ];

        return $mapa[$status] ?? ucfirst($status ?: '-');
    }

    private function badgeFatura($status)
    {
        $classes = [
            'pendente' => 'warning',
            'pago' => 'success',
            'vencido' => 'danger',
            'cancelado' => 'secondary',
            'erro' => 'danger'
        ];

        return $classes[$status] ?? 'secondary';
    }

    private function formatarDataFatura($data)
    {
        return $data ? date('d/m/Y', strtotime($data)) : '-';
    }

    private function e($valor)
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    private function linkPagamentoValido($link)
    {
        $link = trim((string) $link);

        if($link === ''){
            return false;
        }

        $partesUrl = parse_url($link);

        if(
            !is_array($partesUrl)
            || empty($partesUrl['scheme'])
            || empty($partesUrl['host'])
        ){
            return false;
        }

        return (bool) filter_var($link, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) $partesUrl['scheme']), ['http', 'https'], true);
    }

    public function escolherPlano()
    {
        $this->validarCsrfPost();
        Auth::clienteAdmin();

        try{
            $usuario = Auth::usuario();
            $resultado = (new FinanceiroWorkflowService())->contratarPlano(
                (int) $usuario['CLI_ID'],
                (int) ($_POST['plano'] ?? 0),
                (string) ($_POST['ciclo'] ?? 'mensal')
            );
            $_SESSION['usuario']['CLI_Plano_DR'] = $resultado['plano']['PLA_ID'];
            $_SESSION['usuario']['CLI_StatusPagamento'] = 'pendente';
            Session::flash($resultado['sucesso'] ? 'success' : 'warning', $resultado['mensagem']);
        }catch(\DomainException $e){
            Session::flash('error', $e->getMessage());
        }catch(\Throwable $e){
            Session::flash('error', 'Erro ao gerar cobrança.');
        }

        $this->redirect('financeiro');
    }

}
