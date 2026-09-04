<?php
// Integração de domínio: Auth, política financeira, models, controller e layout reais.
// Apenas Database é redirecionado para SQLite em memória. Logs técnicos são permitidos.
namespace Core {
    class Database {
        public static $connection;
        public static function getInstance() { return self::$connection; }
    }
}
namespace Services {
    // Sentinela do transporte usado pelos services Meta/envio: não deixa sair HTTP.
    function curl_init($url = null) {
        $GLOBALS['dashboardExternalCalls']++;
        throw new \LogicException('Dashboard tentou iniciar uma chamada externa de negócio.');
    }
}
namespace {
    require __DIR__ . '/fixtures/OnboardingDashboardFixture.php';
    define('BASE_URL', 'https://disparador.test');
    define('ASSET_URL', BASE_URL . '/assets');
    define('META_APP_ID', 'fixture-app');
    define('META_CONFIGURATION_ID', 'fixture-configuration');
    define('META_EMBEDDED_SIGNUP_REDIRECT_URI', BASE_URL);
    define('META_GRAPH_VERSION', 'v00.0');

    // Usa a API existente de diretório de logs; não suprime nem simula a observabilidade.
    $dashboardTestLogDir = sys_get_temp_dir() . '/dashboard-suspended-' . bin2hex(random_bytes(8));
    function diretorioLogsProjeto() { return $GLOBALS['dashboardTestLogDir']; }

    class DashboardSuspendedConnection extends OnboardingSelectOnlyConnection {
        public function prepare($sql) {
            // A única adaptação de dialeto é a introspecção de coluna do Cobranca real.
            if(preg_match('/^\s*SHOW COLUMNS FROM cobrancas LIKE \?\s*$/i', $sql)){
                $sql = "SELECT name AS Field FROM pragma_table_info('cobrancas') WHERE name = ?";
            }
            return parent::prepare($sql);
        }
    }

    $db = onboardingDb();
    $db->exec("CREATE TABLE usuarios (USU_ID INTEGER PRIMARY KEY, USU_Ativo TEXT);
        CREATE TABLE clientes (CLI_ID INTEGER PRIMARY KEY, CLI_StatusCadastro TEXT, CLI_StatusPagamento TEXT,
            CLI_DataLiberacao TEXT, CLI_DataCadastro TEXT, CLI_Plano_DR INTEGER);
        CREATE TABLE planos (PLA_ID INTEGER PRIMARY KEY, PLA_Nome TEXT, PLA_Valor REAL, PLA_Periodicidade TEXT,
            PLA_LimiteNumeros INTEGER, PLA_LimiteUsuarios INTEGER, PLA_LimiteMensagens INTEGER, PLA_ValorMensagemExcedente REAL);
        CREATE TABLE assinaturas (ASS_ID INTEGER PRIMARY KEY, CLI_ID INTEGER, ASS_Status TEXT);
        CREATE TABLE cobrancas (COB_ID INTEGER PRIMARY KEY, CLI_ID INTEGER, ASS_ID INTEGER, COB_Status TEXT,
            COB_DataVencimento TEXT, COB_DataVencimentoEfetivo TEXT);
        CREATE TABLE consumo_mensal (CLI_ID INTEGER, CMS_AnoMes TEXT, CMS_Mensagens INTEGER);
        CREATE TABLE excedentes_mensais (CLI_ID INTEGER, EXC_AnoMes TEXT);
        CREATE TABLE contatos (CLI_ID INTEGER, CON_Ativo TEXT);
        CREATE TABLE campanhas (CLI_ID INTEGER, CAM_ID INTEGER);
        CREATE TABLE configuracao_whatsapp_site (CWS_ID INTEGER, CWS_Ativo TEXT, MTA_ID INTEGER, CWS_Mensagem TEXT);
        ALTER TABLE conversas ADD COLUMN CVS_Ativo TEXT;
        ALTER TABLE conversas ADD COLUMN CVS_NaoLida TEXT;");
    onboardingInsert($db, 'usuarios', ['USU_ID'=>100,'USU_Ativo'=>'S']);
    onboardingInsert($db, 'clientes', ['CLI_ID'=>10,'CLI_StatusCadastro'=>'ativo','CLI_StatusPagamento'=>'pago',
        'CLI_DataLiberacao'=>'2026-01-01 10:00:00','CLI_DataCadastro'=>'2026-01-01 09:00:00']);
    onboardingInsert($db, 'assinaturas', ['ASS_ID'=>20,'CLI_ID'=>10,'ASS_Status'=>'ativa']);
    onboardingInsert($db, 'cobrancas', ['COB_ID'=>30,'CLI_ID'=>10,'ASS_ID'=>20,'COB_Status'=>'vencido',
        'COB_DataVencimento'=>date('Y-m-d', strtotime('-30 days'))]);
    onboardingAccount($db);
    onboardingTemplate($db);
    onboardingMessage($db, 'sent'); // Existe envio; ainda não existe primeira entrega.

    $_SESSION = ['usuario'=>['id'=>100,'nome'=>'Cliente de teste','nivel'=>'cliente_admin','CLI_ID'=>10]];
    $_GET = ['url'=>'dashboard','conta'=>'1'];
    $connection = new DashboardSuspendedConnection($db);
    \Core\Database::$connection = $connection;
    $controller = new class extends \Controllers\DashboardController {
        public $result;
        protected function view($view, $dados=[], $layout=true) {
            $this->result = $dados;
            parent::view($view, $dados, $layout);
        }
    };

    $snapshot = static function() use ($db) {
        $result = [];
        foreach($db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table){
            $result[$table] = $db->query('SELECT * FROM ' . $table . ' ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
        }
        return $result;
    };
    $before = $snapshot();
    $dashboardExternalCalls = 0;
    $db->exec('PRAGMA query_only=ON');
    $bufferLevel = ob_get_level();
    try {
        ob_start();
        $controller->index();
        $html = ob_get_clean();
        $guia = $controller->result['onboardingChecklist'];
        onboardingAssert($guia['proxima']['id']==='financeiro', 'Cliente suspenso deve ser orientado ao Financeiro pela política real.');
        onboardingAssert($controller->result['acessoOperacionalDashboard']===false, 'Decisão negativa deve chegar ao layout.');
        onboardingAssert(!$guia['concluido'] && !$guia['itens'][5]['done'] && $guia['estado_envio']==='sent', 'Suspensão/abertura não pode transformar envio em entrega.');
        onboardingAssert($snapshot()===$before, 'Abertura modificou estado de negócio.');
        onboardingAssert($dashboardExternalCalls===0, 'Onboarding não pode consultar Graph nem enviar mensagens.');
        onboardingAssert(str_contains($html, 'data-onboarding-state="financeiro"') && str_contains($html, 'Ver meu plano e acesso'), 'View real deve apresentar orientação financeira.');
        onboardingAssert(!str_contains($html, 'Primeira mensagem entregue!'), 'Abertura não pode anunciar ativação inexistente.');
        $avaliacoes = count(array_filter($connection->queries, static function($sql){
            return str_contains($sql, "SELECT * FROM assinaturas WHERE CLI_ID = ? AND ASS_Status = 'ativa'");
        }));
        onboardingAssert($avaliacoes===1, 'Controller e layout devem compartilhar uma única avaliação financeira, inclusive quando false.');
        echo "DashboardSuspendedClientTest OK: Auth/política/models/layout reais; uma avaliação; CTA Financeiro; estado de negócio preservado. Logs técnicos permitidos.\n";
    } finally {
        while(ob_get_level() > $bufferLevel) ob_end_clean();
        $db->exec('PRAGMA query_only=OFF');
        // Limpeza somente do diretório exclusivo criado por este teste.
        foreach(glob($dashboardTestLogDir . '/{*,.*}', GLOB_BRACE) ?: [] as $file){
            if(is_file($file)) unlink($file);
        }
        if(is_dir($dashboardTestLogDir)) rmdir($dashboardTestLogDir);
    }
}
