<?php

namespace Core;

class Auth
{
    public static function check()
    {
        if(!isset($_SESSION['usuario'])){

            header(
                "Location: " .
                BASE_URL .
                "/login"
            );

            exit;
        }

        self::validarBloqueioFinanceiro();
    }

    public static function admin()
    {
        self::check();

        if(
            $_SESSION['usuario']['nivel']
            != 'admin'
        ){
            die('Acesso negado');
        }
    }

    public static function usuario()
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function logout()
    {
        session_destroy();
    }

    public static function cliente()
    {
        self::check();

        if(
            $_SESSION['usuario']['nivel']
            != 'cliente'
        ){

            die('Acesso negado');

        }
    }

    public static function clienteLiberado()
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            ($usuario['nivel'] ?? null) != 'cliente'
        ){
            return true;
        }

        self::atualizarStatusCliente();

        $usuario = self::usuario();

        return (
            ($usuario['CLI_StatusPagamento'] ?? null) == 'pago'
            &&
            ($usuario['CLI_StatusCadastro'] ?? null) == 'ativo'
        );
    }

    public static function validarBloqueioFinanceiro()
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            ($usuario['nivel'] ?? null) != 'cliente'
        ){
            return;
        }

        self::atualizarStatusCliente();

        if(self::rotaFinanceiraLiberada()){
            return;
        }

        if(self::clienteLiberado()){
            return;
        }

        Session::flash(
            'error',
            'Regularize seu financeiro para acessar esta funcionalidade.'
        );

        header(
            "Location: " .
            BASE_URL .
            "/index.php?url=financeiro"
        );

        exit;
    }

    public static function atualizarStatusCliente()
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            ($usuario['nivel'] ?? null) != 'cliente'
            ||
            empty($usuario['CLI_ID'])
        ){
            return;
        }

        $db = Database::getInstance();

        $sql = $db->prepare("
            SELECT
                CLI_StatusPagamento,
                CLI_StatusCadastro,
                CLI_DataLiberacao,
                CLI_Plano_DR
            FROM clientes
            WHERE CLI_ID = ?
            LIMIT 1
        ");

        $sql->execute([
            $usuario['CLI_ID']
        ]);

        $cliente = $sql->fetch(\PDO::FETCH_ASSOC);

        if(!$cliente){
            return;
        }

        $_SESSION['usuario']['CLI_StatusPagamento'] =
            $cliente['CLI_StatusPagamento'];

        $_SESSION['usuario']['CLI_StatusCadastro'] =
            $cliente['CLI_StatusCadastro'];

        $_SESSION['usuario']['CLI_DataLiberacao'] =
            $cliente['CLI_DataLiberacao'];

        $_SESSION['usuario']['CLI_Plano_DR'] =
            $cliente['CLI_Plano_DR'];
    }

    private static function rotaFinanceiraLiberada()
    {
        $url = trim(
            $_GET['url']
            ??
            'dashboard',
            '/'
        );

        if($url == ''){
            $url = 'dashboard';
        }

        $partes = explode('/', $url);
        $controller = $partes[0] ?? 'dashboard';

        return in_array(
            $controller,
            [
                'dashboard',
                'financeiro',
                'login',
                'site'
            ],
            true
        );
    }
}
