<?php

require_once __DIR__ . '/../app/Models/Cliente.php';
require_once __DIR__ . '/../app/Models/ConfiguracaoSite.php';

use Models\Cliente;
use Models\ConfiguracaoSite;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$renderButton = function($configuracao){
    $whatsappSite = $configuracao;
    ob_start();
    require __DIR__ . '/../app/Views/site/partials/whatsapp_button.php';
    return ob_get_clean();
};

$assert($renderButton(null) === '', 'botão não aparece sem configuração');
$assert($renderButton(['ativo'=>false, 'telefone'=>'5541999999999', 'mensagem'=>'Olá']) === '', 'botão não aparece desativado');
$html = $renderButton(['ativo'=>true, 'telefone'=>'5541999999999', 'mensagem'=>"Olá! Teste com acento.\nNova linha"]);
$assert(strpos($html, 'https://wa.me/5541999999999?text=Ol%C3%A1%21%20Teste%20com%20acento.%0ANova%20linha') !== false, 'URL wa.me deve normalizar telefone e codificar mensagem');
$assert(strpos($html, 'target="_blank"') !== false && strpos($html, 'noopener noreferrer') !== false, 'link deve abrir nova aba com proteção');
$assert(strpos($html, 'aria-label=') !== false && strpos($html, 'aria-hidden="true"') !== false, 'botão deve ser acessível');

$assert(ConfiguracaoSite::normalizarTelefone('+55 (41) 99999-9999') === '5541999999999', 'telefone internacional deve conter somente dígitos');
$assert(ConfiguracaoSite::normalizarTelefone('123') === null, 'telefone implausível deve ser recusado');

class WhatsappSiteFakeStatement
{
    private $row;
    public function __construct($row){ $this->row = $row; }
    public function execute($params = []){ return true; }
    public function fetch($mode = null){ return $this->row; }
}

class WhatsappSiteFakeDb
{
    private $configuracao;
    private $conta;
    public function __construct($configuracao, $conta){ $this->configuracao = $configuracao; $this->conta = $conta; }
    public function query($sql){ return new WhatsappSiteFakeStatement($this->configuracao); }
    public function prepare($sql){ return new WhatsappSiteFakeStatement($this->conta); }
}

$inativa = new ConfiguracaoSite(new WhatsappSiteFakeDb(['CWS_Ativo'=>'N'], null));
$assert($inativa->obterConfiguracaoWhatsappSite() === null, 'configuração desativada não deve retornar botão');
$semNumero = new ConfiguracaoSite(new WhatsappSiteFakeDb(['CWS_Ativo'=>'S','MTA_ID'=>9,'CWS_Mensagem'=>'Olá'], null));
$assert($semNumero->obterConfiguracaoWhatsappSite() === null, 'conta desconectada ou ausente não deve retornar botão');
$valida = new ConfiguracaoSite(new WhatsappSiteFakeDb(['CWS_Ativo'=>'S','MTA_ID'=>9,'CWS_Mensagem'=>" Olá\nMundo "], ['MTA_ID'=>9,'MTA_NumeroTelefone'=>'+55 41 99999-9999']));
$assert($valida->obterConfiguracaoWhatsappSite() === ['ativo'=>true,'telefone'=>'5541999999999','mensagem'=>"Olá\nMundo"], 'configuração válida deve expor apenas telefone normalizado e mensagem');

$assert(count(Cliente::ORIGENS_CADASTRO) === 12, 'lista de origens deve conter todas as opções previstas');
$assert(Cliente::validarOrigemCadastro('google', 'texto indevido') === ['origem'=>'google', 'outro'=>null], 'complemento deve ser descartado para origem conhecida');
$assert(Cliente::validarOrigemCadastro('outro', '  Canal regional  ') === ['origem'=>'outro', 'outro'=>'Canal regional'], 'outro deve ser normalizado');

foreach([['adulterado',''], ['outro',''], ['outro',str_repeat('a', 151)]] as $invalido){
    try{
        Cliente::validarOrigemCadastro($invalido[0], $invalido[1]);
        $assert(false, 'origem inválida deveria ser recusada');
    }catch(InvalidArgumentException $e){
        $assert(true, 'origem inválida recusada');
    }
}

$assert(Cliente::formatarOrigemCadastro(null) === 'Não informado', 'cliente antigo deve exibir Não informado');
$assert(Cliente::formatarOrigemCadastro('outro', 'Feira local') === 'Outro — Feira local', 'admin deve exibir detalhe de outro');

$controller = file_get_contents(__DIR__ . '/../app/Controllers/SiteController.php');
$master = file_get_contents(__DIR__ . '/../app/Views/layouts/master.php');
$assert(strpos($controller, 'CLI_OrigemCadastro') !== false && strpos($controller, ':origem_cadastro') !== false, 'cadastro deve persistir origem com prepared statement');
$assert(strpos($master, 'whatsapp_button.php') === false, 'área autenticada não deve renderizar botão público');

echo "Public WhatsApp and registration origin checks passed\n";
