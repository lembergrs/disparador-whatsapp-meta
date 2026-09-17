<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\Cobranca;
use Models\Cliente;
use Models\NfseEmissao;
use Services\NfseAptidaoFiscalService;
use Services\NfseEmissionService;
use Services\NfseExternalReconciliationService;
use Services\NfsePdfOnDemandService;
use Services\NfseConfigService;
use Services\NfseSanitizer;

class NfseController extends Controller
{
    public function index()
    {
        Auth::admin();

        $status = $_GET['status'] ?? null;
        $emissoes = (new NfseEmissao())->listarAdmin($status);
        $cobrancas = (new Cobranca())->listar();
        $clientes = (new Cliente())->listar();
        $cobrancasElegiveisPorCliente = $this->mapearCobrancasElegiveisPorCliente($cobrancas);
        $nfseConfigPublica = NfseConfigService::dadosPublicos();
        $nfseFiscalPreview = [
            'codigo_tributacao_nacional' => NfseConfigService::codigoTributacaoNacional(),
            'descricao_servico' => NfseConfigService::descricaoServico()
        ];

        $this->view('nfse/index', [
            'titulo' => 'NFS-e',
            'emissoes' => $emissoes,
            'cobrancas' => $cobrancas,
            'cobrancasElegiveisPorCliente' => $cobrancasElegiveisPorCliente,
            'clientes' => $clientes,
            'nfseConfigPublica' => $nfseConfigPublica,
            'nfseFiscalPreview' => $nfseFiscalPreview,
            'statusFiltro' => $status,
            'statusPermitidos' => NfseEmissao::statusPermitidos()
        ]);
    }

    private function mapearCobrancasElegiveisPorCliente(array $cobrancas)
    {
        $mapa = [];
        $ids = [];

        foreach($cobrancas as $cobranca){
            $cobrancaId = (int) ($cobranca['COB_ID'] ?? 0);
            if($cobrancaId > 0){
                $ids[] = $cobrancaId;
            }
        }

        $vigentes = (new NfseEmissao())->buscarVigentesPorCobrancas($ids);

        foreach($cobrancas as $cobranca){
            $clienteId = (int) ($cobranca['CLI_ID'] ?? 0);
            $cobrancaId = (int) ($cobranca['COB_ID'] ?? 0);
            $valor = (float) ($cobranca['COB_Valor'] ?? 0);
            $status = (string) ($cobranca['COB_Status'] ?? '');

            if($clienteId <= 0 || $cobrancaId <= 0 || $status !== 'pago' || $valor <= 0 || isset($vigentes[$cobrancaId])){
                continue;
            }

            $dataReferencia = $cobranca['COB_DataPagamento']
                ?? $cobranca['COB_DataVencimento']
                ?? null;

            $mapa[(string) $clienteId][] = [
                'COB_ID' => $cobrancaId,
                'CLI_ID' => $clienteId,
                'descricao' => $this->descricaoCobrancaNfse($cobranca, $valor, $dataReferencia),
                'valor' => $valor,
                'status' => $status,
                'data_referencia' => $dataReferencia ? substr((string) $dataReferencia, 0, 10) : null
            ];
        }

        return $mapa;
    }

    private function descricaoCobrancaNfse(array $cobranca, $valor, $dataReferencia)
    {
        $partes = [
            '#' . (int) ($cobranca['COB_ID'] ?? 0),
            'R$ ' . number_format((float) $valor, 2, ',', '.'),
            'pago'
        ];

        if(!empty($dataReferencia)){
            $partes[] = 'ref. ' . date('d/m/Y', strtotime((string) $dataReferencia));
        }

        return implode(' - ', $partes);
    }

    public function aptidao()
    {
        Auth::admin();

        $clienteId = (int) ($_GET['cliente_id'] ?? 0);
        $cliente = (new Cliente())->buscar($clienteId);
        $resultado = (new NfseAptidaoFiscalService())->validarCliente($cliente ?: null);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(NfseSanitizer::dados($resultado), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function emitir()
    {
        $this->validarCsrfPost();
        Auth::admin();

        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->redirect('nfse');
        }

        try{
            $resultado = (new NfseEmissionService())->emitirManual(
                (int) ($_POST['cliente_id'] ?? 0),
                (int) ($_POST['cobranca_id'] ?? 0),
                Auth::usuario() ?: []
            );

            Session::flash(!empty($resultado['sucesso']) ? 'success' : 'error', $resultado['mensagem'] ?? (!empty($resultado['sucesso']) ? 'NFS-e emitida com sucesso.' : 'NFS-e não emitida. Verifique o resultado.'));
        }catch(\Throwable $e){
            Session::flash('error', NfseSanitizer::mensagem($e->getMessage()));
        }

        $this->redirect('nfse');
    }

    public function registrarExterna()
    {
        Auth::admin();

        if(($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'){
            $emissao = (new NfseEmissao())->buscarPorId((int) ($_GET['nfse_id'] ?? 0));
            $status = (string) ($emissao['NFE_Status'] ?? '');
            $statusReconciliaveis = [NfseEmissao::STATUS_ERRO_TEMPORARIO, NfseEmissao::STATUS_ERRO_DEFINITIVO];

            if(!$emissao || (int) ($emissao['NFE_EmissaoAtiva'] ?? 0) !== 1 || !in_array($status, $statusReconciliaveis, true)){
                Session::flash('error', 'Tentativa fiscal ativa apta para reconciliação não encontrada.');
                $this->redirect('nfse');
            }

            $this->view('nfse/externa', [
                'titulo' => 'Registrar NFS-e externa',
                'emissao' => $emissao
            ]);
            return;
        }

        $this->validarCsrfPost();

        try{
            $resultado = (new NfseExternalReconciliationService())->registrar(
                (int) ($_POST['nfse_id'] ?? 0),
                (string) ($_POST['chave_acesso'] ?? ''),
                Auth::usuario() ?: []
            );
            Session::flash('success', $resultado['mensagem'] ?? 'NFS-e externa reconciliada com sucesso.');
        }catch(\Throwable $e){
            Session::flash('error', NfseSanitizer::mensagem($e->getMessage()));
        }

        $this->redirect('nfse');
    }

    public function consultarPdf()
    {
        $this->validarCsrfPost();
        Auth::admin();
        Session::flash('error', 'O PDF não é mais armazenado. Use a ação PDF para gerá-lo sob demanda a partir do XML armazenado.');
        $this->redirect('nfse');
    }

    public function reconsultar()
    {
        $this->validarCsrfPost();
        Auth::admin();

        try{
            $resultado = (new NfseEmissionService())->reconsultarManual((int) ($_POST['nfse_id'] ?? 0), Auth::usuario() ?: []);
            $sucesso = !empty($resultado['sucesso']);
            Session::flash($sucesso ? 'success' : 'error', $sucesso ? 'Reconsulta concluída.' : 'Reconsulta não retornou dados atualizados.');
        }catch(\Throwable $e){
            Session::flash('error', NfseSanitizer::mensagem($e->getMessage()));
        }

        $this->redirect('nfse');
    }

    public function consultarXml()
    {
        $this->validarCsrfPost();
        Auth::admin();

        try{
            $resultado = (new NfseEmissionService())->consultarXmlManual((int) ($_POST['nfse_id'] ?? 0), Auth::usuario() ?: []);
            Session::flash(!empty($resultado['sucesso']) ? 'success' : 'error', !empty($resultado['sucesso']) ? 'XML consultado e armazenado com sucesso.' : ($resultado['error_message'] ?? 'XML não consultado.'));
        }catch(\Throwable $e){
            Session::flash('error', NfseSanitizer::mensagem($e->getMessage()));
        }

        $this->redirect('nfse');
    }

    public function cancelar()
    {
        $this->validarCsrfPost();
        Auth::admin();

        try{
            $resultado = (new NfseEmissionService())->cancelarManual(
                (int) ($_POST['nfse_id'] ?? 0),
                (int) ($_POST['codigo_motivo'] ?? 0),
                (string) ($_POST['motivo'] ?? ''),
                Auth::usuario() ?: []
            );
            Session::flash(!empty($resultado['sucesso']) ? 'success' : 'error', !empty($resultado['sucesso']) ? 'Cancelamento solicitado com sucesso.' : ($resultado['error_message'] ?? 'Cancelamento não concluído.'));
        }catch(\Throwable $e){
            Session::flash('error', NfseSanitizer::mensagem($e->getMessage()));
        }

        $this->redirect('nfse');
    }

    public function pdf()
    {
        Auth::check();
        $partes = explode('/', $_GET['url'] ?? '');
        $nfseId = (int) ($partes[2] ?? ($_GET['id'] ?? 0));

        try{
            $arquivo = (new NfsePdfOnDemandService())->gerar($nfseId, Auth::usuario() ?: []);
            header('Cache-Control: private, no-store');
            header('Content-Type: ' . $arquivo['content_type']);
            header('Content-Disposition: attachment; filename="' . $arquivo['filename'] . '"');
            header('Content-Length: ' . strlen($arquivo['conteudo']));
            header('X-Content-Type-Options: nosniff');
            echo $arquivo['conteudo'];
            exit;
        }catch(\Throwable $e){
            http_response_code(404);
            exit('PDF da NFS-e não pôde ser gerado.');
        }
    }

    public function xml()
    {
        $this->download('xml');
    }

    private function download($tipo)
    {
        Auth::check();
        $partes = explode('/', $_GET['url'] ?? '');
        $nfseId = (int) ($partes[2] ?? ($_GET['id'] ?? 0));

        try{
            $arquivo = (new NfseEmissionService())->arquivoDownload($nfseId, $tipo, Auth::usuario() ?: []);
            header('Cache-Control: private, no-store');
            header('Content-Type: ' . $arquivo['content_type']);
            header('Content-Disposition: attachment; filename="' . $arquivo['filename'] . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($arquivo['path']);
            exit;
        }catch(\Throwable $e){
            http_response_code(404);
            exit('Documento fiscal não encontrado.');
        }
    }

}
