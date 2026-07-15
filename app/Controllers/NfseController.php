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

        $this->view('nfse/index', [
            'titulo' => 'NFS-e',
            'emissoes' => $emissoes,
            'cobrancas' => $cobrancas,
            'clientes' => $clientes,
            'statusFiltro' => $status,
            'statusPermitidos' => NfseEmissao::statusPermitidos()
        ]);
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
}
