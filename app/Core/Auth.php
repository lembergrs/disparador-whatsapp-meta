<?php

namespace Core;

class Auth
{
    private const DIAS_AVALIACAO = 7;
    private const LIMITE_MENSAGENS_AVALIACAO = 200;

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

        if(
            ($usuario['CLI_StatusPagamento'] ?? null) == 'pago'
            &&
            ($usuario['CLI_StatusCadastro'] ?? null) == 'ativo'
        ){
            return true;
        }

        if(
            ($usuario['CLI_StatusPagamento'] ?? null) != 'pendente'
            ||
            ($usuario['CLI_StatusCadastro'] ?? null) != 'ativo'
        ){
            return false;
        }

        $avaliacao = self::dadosAvaliacaoCliente(false);

        return $avaliacao['ativo'];
    }

    public static function dadosAvaliacaoCliente($atualizar = true)
    {
        if($atualizar){
            self::atualizarStatusCliente();
        }

        $usuario = self::usuario();

        $dados = [
            'ativo' => false,
            'dias_decorridos' => null,
            'dias_restantes' => 0,
            'mensagens_usadas' => 0,
            'mensagens_restantes' => 0,
            'limite_dias' => self::DIAS_AVALIACAO,
            'limite_mensagens' => self::LIMITE_MENSAGENS_AVALIACAO
        ];

        if(
            !$usuario
            ||
            ($usuario['nivel'] ?? null) != 'cliente'
            ||
            ($usuario['CLI_StatusPagamento'] ?? null) != 'pendente'
            ||
            ($usuario['CLI_StatusCadastro'] ?? null) != 'ativo'
        ){
            return $dados;
        }

        $dataBase = $usuario['CLI_DataLiberacao'] ?? null;

        if(empty($dataBase)){
            return $dados;
        }

        $timestampBase = strtotime($dataBase);

        if(!$timestampBase){
            return $dados;
        }

        $segundosDecorridos = time() - $timestampBase;

        if($segundosDecorridos < 0){
            $segundosDecorridos = 0;
        }

        $diasDecorridos = (int) floor(
            $segundosDecorridos / 86400
        );

        $mensagensUsadas = (int) (
            $usuario['CMS_MensagensMesAtual']
            ?? 0
        );

        $diasRestantes = max(
            0,
            self::DIAS_AVALIACAO - $diasDecorridos
        );

        $mensagensRestantes = max(
            0,
            self::LIMITE_MENSAGENS_AVALIACAO - $mensagensUsadas
        );

        $dados['dias_decorridos'] = $diasDecorridos;
        $dados['dias_restantes'] = $diasRestantes;
        $dados['mensagens_usadas'] = $mensagensUsadas;
        $dados['mensagens_restantes'] = $mensagensRestantes;
        $dados['ativo'] = (
            $diasDecorridos < self::DIAS_AVALIACAO
            &&
            $mensagensUsadas < self::LIMITE_MENSAGENS_AVALIACAO
        );

        return $dados;
    }

    public static function clientePodeConectarMeta()
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            ($usuario['nivel'] ?? null) != 'cliente'
        ){
            return false;
        }

        self::atualizarStatusCliente();

        $usuario = self::usuario();

        return (
            ($usuario['CLI_StatusPagamento'] ?? null) == 'pendente'
            &&
            ($usuario['CLI_StatusCadastro'] ?? null) == 'ativo'
            &&
            empty($usuario['CLI_DataLiberacao'])
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
                c.CLI_StatusPagamento,
                c.CLI_StatusCadastro,
                c.CLI_DataLiberacao,
                c.CLI_DataCadastro,
                c.CLI_Plano_DR,
                COALESCE(cm.CMS_Mensagens, 0) AS CMS_MensagensMesAtual
            FROM clientes c
            LEFT JOIN consumo_mensal cm
                ON cm.CLI_ID = c.CLI_ID
                AND cm.CMS_AnoMes = ?
            WHERE c.CLI_ID = ?
            LIMIT 1
        ");

        $sql->execute([
            date('Ym'),
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

        $_SESSION['usuario']['CLI_DataCadastro'] =
            $cliente['CLI_DataCadastro'];

        $_SESSION['usuario']['CLI_Plano_DR'] =
            $cliente['CLI_Plano_DR'];

        $_SESSION['usuario']['CMS_MensagensMesAtual'] =
            (int) $cliente['CMS_MensagensMesAtual'];
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

        if(
            in_array(
                $controller,
                [
                    'dashboard',
                    'financeiro',
                    'login',
                    'site'
                ],
                true
            )
        ){
            return true;
        }

        return (
            $controller == 'configuracao'
            &&
            self::clientePodeConectarMeta()
        );
    }
}
