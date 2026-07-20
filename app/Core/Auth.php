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
                "/index.php?url=login"
            );

            exit;
        }

        self::validarUsuarioAtivo();

        self::validarBloqueioFinanceiro();
    }

    private static function validarUsuarioAtivo()
    {
        $usuario = self::usuario();

        if(!$usuario || empty($usuario['id'])){
            return;
        }

        $db = Database::getInstance();

        $sql = $db->prepare("
            SELECT USU_Ativo
            FROM usuarios
            WHERE USU_ID = ?
            LIMIT 1
        "
        );

        $sql->execute([
            $usuario['id']
        ]);

        $ativo = $sql->fetchColumn();

        if($ativo !== 'S'){
            session_destroy();
            session_start();

            header(
                "Location: " .
                BASE_URL .
                "/index.php?url=login"
            );

            exit;
        }
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

    public static function nivelCliente($nivel = null)
    {
        return in_array(
            $nivel,
            ['cliente', 'cliente_admin', 'cliente_usuario'],
            true
        );
    }

    public static function clienteAdmin()
    {
        self::check();

        if(
            !in_array(
                $_SESSION['usuario']['nivel'] ?? null,
                ['admin', 'cliente_admin', 'cliente'],
                true
            )
        ){

            die('Acesso negado');

        }
    }

    public static function cliente()
    {
        self::check();

        if(
            !self::nivelCliente(
                $_SESSION['usuario']['nivel'] ?? null
            )
        ){

            die('Acesso negado');

        }
    }


    public static function idsContasMetaPermitidas($usuario = null)
    {
        $usuario = $usuario ?: self::usuario();

        if(!$usuario){
            return [];
        }

        $nivel = $usuario['nivel'] ?? null;

        if($nivel !== 'admin' && !self::nivelCliente($nivel)){
            return [];
        }

        $clienteId = (int) ($usuario['CLI_ID'] ?? ($usuario['cliente_id'] ?? 0));

        if($clienteId <= 0){
            return [];
        }

        $db = Database::getInstance();

        $sql = $db->prepare("
            SELECT MTA_ID
            FROM meta_contas
            WHERE CLI_ID = ?
            AND MTA_Ativo = 'S'
        ");

        $sql->execute([
            $clienteId
        ]);

        return array_map(
            'intval',
            $sql->fetchAll(\PDO::FETCH_COLUMN)
        );
    }

    public static function clienteLiberado()
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            !self::nivelCliente($usuario['nivel'] ?? null)
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

    public static function clienteEmPreTrial()
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            !self::nivelCliente($usuario['nivel'] ?? null)
        ){
            return false;
        }

        self::atualizarStatusCliente();
        $usuario = self::usuario();

        return (
            ($usuario['CLI_StatusCadastro'] ?? null) == 'ativo'
            &&
            ($usuario['CLI_StatusPagamento'] ?? null) == 'pendente'
            &&
            empty($usuario['CLI_DataLiberacao'])
        );
    }

    public static function trialEncerradoCliente()
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            !self::nivelCliente($usuario['nivel'] ?? null)
            ||
            ($usuario['CLI_StatusPagamento'] ?? null) != 'pendente'
            ||
            ($usuario['CLI_StatusCadastro'] ?? null) != 'ativo'
            ||
            empty($usuario['CLI_DataLiberacao'])
        ){
            return false;
        }

        $avaliacao = self::dadosAvaliacaoCliente(false);

        return !$avaliacao['ativo'];
    }


    private static function clienteEmToleranciaFinanceira($clienteId)
    {
        if(empty($clienteId)){
            return false;
        }

        $diasTolerancia = defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO')
            ? (int) FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO
            : 5;

        $db = Database::getInstance();

        $sql = $db->prepare("
            SELECT COB_ID
            FROM cobrancas
            WHERE CLI_ID = ?
            AND COB_Status = 'vencido'
            AND COB_DataVencimento >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY COB_DataVencimento DESC
            LIMIT 1
        ");

        $sql->execute([
            $clienteId,
            $diasTolerancia
        ]);

        return (bool) $sql->fetch(\PDO::FETCH_ASSOC);
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
            !self::nivelCliente($usuario['nivel'] ?? null)
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
            !in_array(
                $usuario['nivel'] ?? null,
                ['cliente', 'cliente_admin'],
                true
            )
        ){
            return false;
        }

        self::atualizarStatusCliente();

        $usuario = self::usuario();

        return self::clienteEmPreTrial();
    }

    public static function podeConectarPrimeiroNumero($clienteId, $numerosAtivos)
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            (int) ($usuario['CLI_ID'] ?? 0) !== (int) $clienteId
            ||
            (int) $numerosAtivos !== 0
        ){
            return false;
        }

        return self::clienteEmPreTrial();
    }

    public static function validarBloqueioFinanceiro()
    {
        $usuario = self::usuario();

        if(
            !$usuario
            ||
            !self::nivelCliente($usuario['nivel'] ?? null)
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
            self::trialEncerradoCliente()
                ? 'Seu período de avaliação terminou. Escolha ou regularize um plano para continuar utilizando a plataforma.'
                : 'Conecte seu número do WhatsApp para iniciar seu período de avaliação ou regularize seu financeiro.'
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
            !self::nivelCliente($usuario['nivel'] ?? null)
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
                    'conta',
                    'login',
                    'site'
                ],
                true
            )
        ){
            return true;
        }

        if($controller == 'nfse'){
            $metodo = $partes[1] ?? 'index';
            return in_array($metodo, ['pdf', 'xml'], true);
        }

        if($controller == 'configuracao'){
            return self::clientePodeConectarMeta();
        }

        return false;
    }
}
