<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Session;
use Models\DepoimentoCliente;

class DepoimentoAdminController extends Controller
{
    public function index()
    {
        Auth::admin();
        $this->view('depoimentos_admin/index', [
            'titulo' => 'Depoimentos de clientes',
            'depoimentos' => (new DepoimentoCliente())->listarParaAdministracao()
        ]);
    }

    public function aprovar() { $this->decidir('aprovado'); }
    public function rejeitar() { $this->decidir('rejeitado'); }

    public function desativar()
    {
        $this->validarCsrfPost();
        Auth::admin();
        $ok = (new DepoimentoCliente())->desativar((int) ($_POST['id'] ?? 0), (int) (Auth::usuario()['id'] ?? 0));
        Session::flash($ok ? 'success' : 'error', $ok ? 'Depoimento desativado.' : 'Não foi possível desativar o depoimento.');
        $this->redirect('depoimentoAdmin');
    }

    private function decidir($status)
    {
        $this->validarCsrfPost();
        Auth::admin();
        $ok = (new DepoimentoCliente())->decidir((int) ($_POST['id'] ?? 0), $status, (int) (Auth::usuario()['id'] ?? 0));
        Session::flash($ok ? 'success' : 'error', $ok ? 'Depoimento ' . $status . '.' : 'O depoimento já foi analisado ou não existe.');
        $this->redirect('depoimentoAdmin');
    }
}
