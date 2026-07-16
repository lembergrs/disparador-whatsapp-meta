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

        $this->view('nfse/index', [
            'titulo' => 'NFS-e',
            'emissoes' => $emissoes,
            'cobrancas' => $cobrancas,
            'cobrancasElegiveisPorCliente' => $cobrancasElegiveisPorCliente,
            'clientes' => $clientes,
            'statusFiltro' => $status,
            'statusPermitidos' => NfseEmissao::statusPermitidos()
        ]);
    }


    private function mapearCobrancasElegiveisPorCliente(array $cobrancas)
    {
        $mapa = [];

        foreach($cobrancas as $cobranca){
            $clienteId = (int) ($cobranca['CLI_ID'] ?? 0);
            $cobrancaId = (int) ($cobranca['COB_ID'] ?? 0);
            $valor = (float) ($cobranca['COB_Valor'] ?? 0);
            $status = (string) ($cobranca['COB_Status'] ?? '');

            if($clienteId <= 0 || $cobrancaId <= 0 || $status !== 'pago' || $valor <= 0){
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

    public function consultarPdf()
    {
        $this->validarCsrfPost();
        Auth::admin();

        try{
            $resultado = (new NfseEmissionService())->consultarPdfManual((int) ($_POST['nfse_id'] ?? 0), Auth::usuario() ?: []);
            Session::flash(!empty($resultado['sucesso']) ? 'success' : 'error', !empty($resultado['sucesso']) ? 'PDF consultado e armazenado com sucesso.' : ($resultado['error_message'] ?? 'PDF não consultado.'));
        }catch(\Throwable $e){
            Session::flash('error', NfseSanitizer::mensagem($e->getMessage()));
        }

        $this->redirect('nfse');
    }

    public function reconsultar()
    {
        $this->validarCsrfPost();
        Auth::admin();

        try{
            $resultado = (new NfseEmissionService())->reconsultarManual((int) ($_POST['nfse_id'] ?? 0), Auth::usuario() ?: []);
            Session::flash(!empty($resultado['sucesso']) ? 'success' : 'error', !empty($resultado['sucesso']) ? 'Reconsulta concluída.' : 'Reconsulta não retornou documentos atualizados.');
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
        $this->download('pdf');
    }

    public function xml()
    {
        $this->download('xml');
    }

    private function download($tipo)
    {
        Auth::admin();
        $partes = explode('/', $_GET['url'] ?? '');
        $nfseId = (int) ($partes[2] ?? ($_GET['id'] ?? 0));

        try{
            $arquivo = (new NfseEmissionService())->arquivoDownload($nfseId, $tipo, Auth::usuario() ?: []);
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
