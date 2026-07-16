<?php

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Core\Session;
use Core\Database;
use Models\Plano;
use Models\Cobranca;
use Models\MetaConta;
use Models\Assinatura;
use Models\Cliente;
use Models\NfseEmissao;
use Services\AsaasService;

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

        $this->view(
            'financeiro/index',
            [
                'titulo' => 'Financeiro',
                'planos' => $planos,
                'cobranca' => $cobranca,
                'faturasPerPageDefault' => 5,
                'excedente' => $excedente,
                'numerosAtivos' => $numerosAtivos,
                'assinaturaAtual' => $assinaturaAtual
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

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->redirect('financeiro');
        }

        $usuario = Auth::usuario();

        $planoId = (int) ($_POST['plano'] ?? 0);
        $ciclo = $_POST['ciclo'] ?? 'mensal';

        if(!Plano::cicloValido($ciclo)){

            Session::flash(
                'error',
                'Ciclo de cobrança inválido.'
            );

            $this->redirect('financeiro');
        }

        $cobrancaModel = new Cobranca();

        $cobrancaExistente =
            $cobrancaModel->buscarPendentePorCliente(
                $usuario['CLI_ID']
            );

        if($cobrancaExistente){

            Session::flash(
                'error',
                'Já existe uma cobrança pendente para este cliente.'
            );

            $this->redirect('financeiro');

        }

        $planoModel = new Plano();
        $plano = $planoModel->buscar($planoId);

        if(!$plano){

            Session::flash(
                'error',
                'Plano inválido.'
            );

            $this->redirect('financeiro');
        }

        $metaContaModel = new MetaConta();
        $validacaoNumeros =
            $metaContaModel->validarLimiteNumerosPlano(
                $usuario['CLI_ID'],
                $plano['PLA_LimiteNumeros']
            );

        if(!$validacaoNumeros['permitido']){

            Session::flash(
                'error',
                $validacaoNumeros['mensagem']
            );

            $this->redirect('financeiro');
        }

        $valorCiclo = Plano::valorPorCiclo($plano, $ciclo);
        $proximaCobranca = date(
            'Y-m-d',
            strtotime('+' . Plano::mesesPorCiclo($ciclo) . ' months')
        );

        $db = Database::getInstance();

        $db->beginTransaction();

        try{

            $sql = $db->prepare("
                UPDATE clientes
                SET
                    CLI_Plano_DR = ?,
                    CLI_StatusPagamento = 'pendente'
                WHERE CLI_ID = ?
            ");

            $sql->execute([
                $plano['PLA_ID'],
                $usuario['CLI_ID']
            ]);

            $assinaturaModel = new Assinatura();
            $assinaturaModel->criarOuAtualizarPorCliente(
                $usuario['CLI_ID'],
                $plano,
                'pendente',
                [
                    'ciclo' => $ciclo,
                    'valor' => $valorCiclo,
                    'proxima_cobranca' => $proximaCobranca
                ]
            );

            $assinaturaPagamento = $assinaturaModel->buscarParaPagamento(
                $usuario['CLI_ID'],
                $plano['PLA_ID']
            );

            $cobrancaId = $cobrancaModel->criar([
                'cliente' => $usuario['CLI_ID'],
                'plano' => $plano['PLA_ID'],
                'assinatura' => $assinaturaPagamento['ASS_ID'] ?? null,
                'valor' => $valorCiclo,
                'vencimento' => date('Y-m-d', strtotime('+3 days')),
                'tipo' => 'mensalidade',
                'provider' => 'asaas',
                'provider_status' => 'local_pendente'
            ]);

            $resultadoAsaas = $this->sincronizarCobrancaAsaas(
                $usuario['CLI_ID'],
                $cobrancaId,
                $plano
            );

            $db->commit();

            $_SESSION['usuario']['CLI_Plano_DR'] =
                $plano['PLA_ID'];

            $_SESSION['usuario']['CLI_StatusPagamento'] =
                'pendente';

            Session::flash(
                $resultadoAsaas['sucesso'] ? 'success' : 'warning',
                $resultadoAsaas['mensagem']
            );

        }catch(\Exception $e){

            $db->rollBack();

            Session::flash(
                'error',
                'Erro ao gerar cobrança.'
            );
        }

        $this->redirect('financeiro');
    }



    private function sincronizarCobrancaAsaas($clienteId, $cobrancaId, $plano)
    {
        $clienteModel = new Cliente();
        $cobrancaModel = new Cobranca();
        $asaasService = new AsaasService();

        $cliente = $clienteModel->buscar($clienteId);
        $cobranca = $cobrancaModel->buscar($cobrancaId);

        if(!$cliente || !$cobranca){
            return [
                'sucesso' => false,
                'mensagem' => 'Plano selecionado, mas não foi possível sincronizar a cobrança neste momento.'
            ];
        }

        $providerCustomerId = trim((string) ($cliente['CLI_ProviderCustomerId'] ?? ''));

        if($providerCustomerId === ''){
            $validacaoCliente = $this->validarDadosClienteAsaas($cliente);

            if(!$validacaoCliente['valido']){
                $cobrancaModel->atualizarIntegracaoProvider($cobrancaId, [
                    'provider' => 'asaas',
                    'provider_status' => 'erro_cliente',
                    'provider_payload' => $this->payloadErroProvider(
                        0,
                        $validacaoCliente['erros'],
                        'Dados cadastrais obrigatórios ausentes ou inválidos para criar cliente no Asaas.',
                        '/customers'
                    )
                ]);

                return [
                    'sucesso' => false,
                    'mensagem' => 'Não foi possível gerar a cobrança automaticamente. Verifique os dados cadastrais ou entre em contato com o suporte.'
                ];
            }

            $resultadoCliente = $asaasService->criarOuAtualizarCliente($cliente);

            if(!$resultadoCliente['sucesso'] || empty($resultadoCliente['response']['id'])){
                $cobrancaModel->atualizarIntegracaoProvider($cobrancaId, [
                    'provider' => 'asaas',
                    'provider_status' => 'erro_cliente',
                    'provider_payload' => $this->payloadErroProvider(
                        $resultadoCliente['http_code'] ?? 0,
                        $resultadoCliente['response']['errors'] ?? ($resultadoCliente['response'] ?? []),
                        $resultadoCliente['erro'] ?? 'Falha ao criar cliente no Asaas.',
                        '/customers'
                    )
                ]);

                return [
                    'sucesso' => false,
                    'mensagem' => 'Não foi possível gerar a cobrança automaticamente. Verifique os dados cadastrais ou entre em contato com o suporte.'
                ];
            }

            $providerCustomerId = $resultadoCliente['response']['id'];
            $clienteModel->atualizarProviderPagamento($clienteId, 'asaas', $providerCustomerId);
            $cliente['CLI_ProviderPagamento'] = 'asaas';
            $cliente['CLI_ProviderCustomerId'] = $providerCustomerId;
        }

        $cobranca['descricao'] = 'Mensalidade ' . ($plano['PLA_Nome'] ?? 'Disparador.net');
        $resultadoCobranca = $asaasService->criarCobranca($cliente, $cobranca);

        if(!$resultadoCobranca['sucesso'] || empty($resultadoCobranca['response']['id'])){
            $cobrancaModel->atualizarIntegracaoProvider($cobrancaId, [
                'provider' => 'asaas',
                'provider_customer_id' => $providerCustomerId,
                'provider_status' => 'erro_cobranca',
                'provider_payload' => $this->payloadProviderSeguro($resultadoCobranca['response'] ?? [])
            ]);

            return [
                'sucesso' => false,
                'mensagem' => 'Plano selecionado, mas o Asaas não retornou o link de pagamento. Tente novamente em instantes ou fale com o suporte.'
            ];
        }

        $pagamento = $resultadoCobranca['response'];
        $pix = [];

        if(!empty($pagamento['id'])){
            $resultadoPix = $asaasService->buscarPixQrCode($pagamento['id']);

            if($resultadoPix['sucesso'] && is_array($resultadoPix['response'])){
                $pix = $resultadoPix['response'];
            }
        }

        $cobrancaModel->atualizarIntegracaoProvider($cobrancaId, [
            'provider' => 'asaas',
            'provider_customer_id' => $providerCustomerId,
            'provider_payment_id' => $pagamento['id'] ?? null,
            'provider_status' => $pagamento['status'] ?? null,
            'provider_payload' => $this->payloadProviderSeguro($pagamento),
            'link_pagamento' => $pagamento['invoiceUrl'] ?? ($pagamento['bankSlipUrl'] ?? null),
            'pix_copia_cola' => $pix['payload'] ?? null,
            'qr_code' => $pix['encodedImage'] ?? null,
            'linha_digitavel' => $pagamento['identificationField'] ?? null,
            'status' => 'pendente'
        ]);

        return [
            'sucesso' => true,
            'mensagem' => 'Plano selecionado. A cobrança foi criada e o link de pagamento está disponível.'
        ];
    }


    private function validarDadosClienteAsaas($cliente)
    {
        $erros = [];
        $nome = trim((string) ($cliente['CLI_RazaoSocial'] ?? ''));

        if($nome === ''){
            $nome = trim((string) ($cliente['CLI_Nome'] ?? ''));
        }

        $documento = preg_replace('/\D/', '', (string) ($cliente['CLI_CPF_CNPJ'] ?? ''));
        $email = trim((string) ($cliente['CLI_Email'] ?? ''));

        if($nome === ''){
            $erros[] = 'Informe nome ou razão social.';
        }

        if($documento === ''){
            $erros[] = 'Informe CPF/CNPJ.';
        }

        if($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)){
            $erros[] = 'Informe um e-mail válido.';
        }

        return [
            'valido' => empty($erros),
            'erros' => $erros
        ];
    }

    private function payloadErroProvider($httpCode, $erros, $mensagem, $endpoint)
    {
        return json_encode([
            'http_code' => (int) $httpCode,
            'erros' => $this->normalizarErrosProvider($erros),
            'mensagem' => $mensagem,
            'endpoint' => $endpoint
        ], JSON_UNESCAPED_UNICODE);
    }

    private function normalizarErrosProvider($erros)
    {
        if(!is_array($erros)){
            return [];
        }

        if(isset($erros['errors']) && is_array($erros['errors'])){
            $erros = $erros['errors'];
        }

        $normalizados = [];

        foreach($erros as $erro){
            if(is_array($erro)){
                $normalizados[] = [
                    'code' => $erro['code'] ?? null,
                    'description' => $erro['description'] ?? ($erro['message'] ?? null)
                ];
            }elseif(is_scalar($erro)){
                $normalizados[] = ['description' => (string) $erro];
            }
        }

        return $normalizados;
    }

    private function payloadProviderSeguro($payload)
    {
        if(!is_array($payload)){
            return null;
        }

        $permitidos = [
            'id', 'status', 'billingType', 'value', 'netValue', 'dueDate',
            'invoiceUrl', 'bankSlipUrl', 'transactionReceiptUrl',
            'customer', 'externalReference', 'description', 'dateCreated',
            'paymentDate', 'clientPaymentDate', 'confirmedDate', 'deleted'
        ];

        return json_encode(
            array_intersect_key($payload, array_flip($permitidos)),
            JSON_UNESCAPED_UNICODE
        );
    }

}
