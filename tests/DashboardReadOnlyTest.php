<?php
// Read-only de DOMÍNIO: SQL da feature protegido contra mutações.
// Auth, models auxiliares e layout são simulados neste teste isolado.
// Não proíbe sessão/log/auditoria técnica nem comprova o request integral.
// DashboardSuspendedClientTest complementa com Auth, política e layout reais.
namespace Core {
    class Auth {
        public static $operacional=false;
        public static $preTrial=true;
        public static function check() {}
        public static function usuario() { return ['nivel'=>'cliente_admin','CLI_ID'=>10]; }
        public static function clienteLiberado() { return self::$operacional; }
        public static function clienteEmPreTrial() { return self::$preTrial; }
        public static function clientePodeConectarMeta() { return self::$preTrial; }
        public static function dadosAvaliacaoCliente($atualizar=true) { return ['ativo'=>false]; }
    }
    class Database {
        public static $connection;
        public static function getInstance() { return self::$connection; }
    }
}
namespace Models {
    class Cliente { public function buscarComPlano($id) { return ['CLI_ID'=>$id,'CLI_StatusPagamento'=>'pendente']; } }
    class ConsumoMensal { public function buscarMesAtual($id) { return null; } }
    class ExcedenteMensal { public function buscarMesAtual($id) { return null; } }
    class ConfiguracaoSite { public function obterConfiguracaoWhatsappSite() { return null; } }
}
namespace {
    require __DIR__ . '/fixtures/OnboardingDashboardFixture.php';
    $db=onboardingDb();
    $db->exec("CREATE TABLE contatos (CLI_ID INTEGER, CON_Ativo TEXT);
        CREATE TABLE campanhas (CLI_ID INTEGER, CAM_ID INTEGER);
        ALTER TABLE conversas ADD COLUMN CVS_Ativo TEXT;
        ALTER TABLE conversas ADD COLUMN CVS_NaoLida TEXT;");
    $connection = new OnboardingSelectOnlyConnection($db);
    \Core\Database::$connection = $connection;
    $controller = new class extends \Controllers\DashboardController {
        public $result;
        protected function view($view,$dados=[],$layout=true) { $this->result=$dados; }
    };
    $db->exec('PRAGMA query_only=ON');
    $_GET=['url'=>'dashboard']; $controller->index();
    onboardingAssert($controller->result['onboardingChecklist']['proxima']['id']==='conexao_iniciar','Controller pré-trial deve conectar.');
    $controller->index();
    onboardingAssert($controller->result['metaConta']===null,'Controller sem conta não deve criar conta.');
    $db->exec('PRAGMA query_only=OFF');
    onboardingAccount($db,1,'conectado',null); onboardingAccount($db,2);
    onboardingTemplate($db,'APPROVED',2); onboardingMessage($db,'delivered',2);
    \Core\Auth::$operacional=true; \Core\Auth::$preTrial=false;
    $db->exec('PRAGMA query_only=ON');
    $_GET=['url'=>'dashboard','conta'=>'1']; $controller->index();
    onboardingAssert($controller->result['acessoOperacionalDashboard']===true,'Controller deve compartilhar decisão de acesso com o layout.');
    onboardingAssert($controller->result['onboardingChecklist']['proxima']['id']==='pagamento_meta','Controller deve respeitar conta selecionada.');
    onboardingAssert((int)$controller->result['metaConta']['MTA_ID']===1,'Cards devem usar mesma conta do guia.');
    $_GET=['url'=>'dashboard','conta'=>'2']; $controller->index();
    onboardingAssert($controller->result['onboardingChecklist']['concluido'],'Controller deve interpretar entrega persistida.');
    $_GET=['url'=>'dashboard','conta'=>['2']]; $controller->index();
    onboardingAssert($controller->result['onboardingChecklist']['contexto_invalido'],'Entrada não escalar deve ser rejeitada.');
    $db->exec('PRAGMA query_only=OFF');
    echo 'DashboardReadOnlyTest OK: controller isolado, ' . count($connection->queries) . " SELECTs em cinco chamadas; sem escrita de domínio da feature.\n";
}
