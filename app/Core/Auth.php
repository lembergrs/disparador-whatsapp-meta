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
            self::logout('sessao_expirada');
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

    public static function logout($motivo = 'logout')
    {
        if(self::isImpersonating()){
            $impersonacao = self::impersonacao();
            try{
                (new \Models\SuporteAcesso())->encerrar(
                    (int) ($impersonacao['auditoria_id'] ?? 0),
                    $motivo
                );
            }catch(\Throwable $e){
                error_log('Erro ao encerrar auditoria no logout do modo suporte: ' . $e->getMessage());
            }
        }

        unset($_SESSION['impersonacao'], $_SESSION['usuario']);
        session_destroy();
    }

    public static function isImpersonating()
    {
        return !empty($_SESSION['impersonacao'])
            && !empty($_SESSION['impersonacao']['admin'])
            && !empty($_SESSION['impersonacao']['auditoria_id']);
    }

    public static function impersonacao()
    {
        return self::isImpersonating() ? $_SESSION['impersonacao'] : null;
    }

    public static function getOriginalAdmin()
    {
        $impersonacao = self::impersonacao();
        return $impersonacao['admin'] ?? null;
    }

    public static function startImpersonation(array $identidade, $auditoriaId)
    {
        $admin = self::usuario();

        if(
            self::isImpersonating()
            || !$admin
            || ($admin['nivel'] ?? null) !== 'admin'
            || (int) $auditoriaId <= 0
            || empty($identidade['USU_ID'])
            || empty($identidade['CLI_ID'])
            || !in_array($identidade['USU_Nivel'] ?? null, ['cliente_admin', 'cliente'], true)
        ){
            throw new \RuntimeException('Impersonação inválida.');
        }

        $_SESSION['impersonacao'] = [
            'admin' => $admin,
            'cliente_id' => (int) $identidade['CLI_ID'],
            'cliente_nome' => (string) ($identidade['CLI_Nome'] ?? ''),
            'usuario_cliente_id' => (int) $identidade['USU_ID'],
            'auditoria_id' => (int) $auditoriaId,
            'inicio' => date('c')
        ];

        $_SESSION['usuario'] = [
            'id' => (int) $identidade['USU_ID'],
            'nome' => (string) $identidade['USU_Nome'],
            'cliente_id' => (int) $identidade['CLI_ID'],
            'nivel' => (string) $identidade['USU_Nivel'],
            'CLI_ID' => (int) $identidade['CLI_ID'],
            'CLI_StatusPagamento' => $identidade['CLI_StatusPagamento'] ?? null,
            'CLI_StatusCadastro' => $identidade['CLI_StatusCadastro'] ?? null,
            'CLI_DataLiberacao' => $identidade['CLI_DataLiberacao'] ?? null,
            'CLI_DataCadastro' => $identidade['CLI_DataCadastro'] ?? null,
            'CLI_Plano_DR' => $identidade['CLI_Plano_DR'] ?? null,
            'CMS_MensagensMesAtual' => (int) ($identidade['CMS_MensagensMesAtual'] ?? 0)
        ];

        if(session_status() === PHP_SESSION_ACTIVE){
            session_regenerate_id(true);
        }
    }

    public static function stopImpersonation()
    {
        $admin = self::getOriginalAdmin();
        if(!$admin || ($admin['nivel'] ?? null) !== 'admin'){
            throw new \RuntimeException('Sessão administrativa original inválida.');
        }

        unset($_SESSION['impersonacao']);
        $_SESSION['usuario'] = $admin;

        if(session_status() === PHP_SESSION_ACTIVE){
            session_regenerate_id(true);
        }
    }

    public static function bloquearAcaoSensivelEmImpersonacao()
    {
        if(!self::isImpersonating()){
            return;
        }

        Session::flash('error', 'Esta ação não está disponível durante o modo suporte.');
        header('Location: ' . BASE_URL . '/index.php?url=dashboard');
        exit;
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

    public static function podeGerenciarPropriaConfiguracaoMeta($usuario = null)
    {
        $usuario = $usuario ?: self::usuario();

        return $usuario
            && in_array($usuario['nivel'] ?? null, ['cliente', 'cliente_admin', 'admin'], true)
            && (int) ($usuario['CLI_ID'] ?? 0) > 0;
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

        if(($usuario['CLI_StatusCadastro'] ?? null) != 'ativo'){
            return false;
        }

        $financeiro = (new \Services\FinanceiroAccessPolicyService())->avaliar((int) $usuario['CLI_ID']);
        if(!empty($financeiro['vinculo_ativo'])){
            return !empty($financeiro['acesso_operacional']);
        }

        if(
            ($usuario['CLI_StatusPagamento'] ?? null) == 'pago'
        ){
            return true;
        }

        if(
            ($usuario['CLI_StatusPagamento'] ?? null) != 'pendente'
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
                    'onboardingSuporte',
                    'financeiro',
                    'indicacao',
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
