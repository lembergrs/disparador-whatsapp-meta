<?php

$root = dirname(__DIR__);
$home = file_get_contents($root . '/app/Views/site/home.php');
$controller = file_get_contents($root . '/app/Controllers/SiteController.php');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(substr_count($home, '<h1') === 1, 'Home deve manter somente um H1');
$assert(strpos($home, "ofertasPublicasPlanos") !== false, 'landing deve receber a oferta calculada pelo backend');
$assert(strpos($home, '50% da primeira mensalidade') !== false, 'landing deve explicar a base do desconto');
$assert(strpos($home, '.site-valor-primeiro-pagamento') !== false && strpos($home, 'font-size: 2.125rem;') !== false, 'valor promocional deve ter destaque visual moderado');
$assert(strpos($home, 'Economia de R$ <?= number_format($valorDesconto, 2, \',\', \'.\'); ?>: 50% da primeira mensalidade') !== false, 'economia da primeira mensalidade deve permanecer visível');
$assert(strpos($home, 'Renovação: <strong>R$ <?= number_format($valorMensal, 2, \',\', \'.\'); ?>/mês</strong>') !== false, 'preço integral da renovação ausente');
$assert(strpos($home, '50% de desconto na primeira mensalidade para novos clientes.') !== false, 'benefício inicial de 50% ausente');
$assert(strpos($home, 'Este benefício é válido com ou sem indicação.') !== false, 'benefício inicial deve ser independente de indicação');
$assert(strpos($home, 'O indicado faz o cadastro') !== false, 'passo quatro deve descrever o cadastro do indicado');
$assert(strpos($home, 'A empresa indicada acessa o cadastro pelo link ou informa o código de indicação manualmente.') !== false, 'passo quatro deve informar link ou código manual');
$assert(strpos($controller, '(new IndicacaoCampanha())->buscarPublicaElegivel()') !== false, 'controller deve usar a campanha pública elegível existente');
$assert(strpos($controller, "'disponivel' => \$campanhaIndicacao !== null") !== false && strpos($controller, "'percentual' => \$campanhaIndicacao !== null") !== false, 'controller deve expor somente disponibilidade e percentual da campanha');
$assert(strpos($home, "if(!empty(\$campanhaIndicacaoPublica['disponivel']))") !== false, 'seção de indicação deve depender de campanha pública elegível');
$assert(substr_count($home, "htmlspecialchars(\$percentualIndicacao, ENT_QUOTES, 'UTF-8'); ?>%") === 3, 'percentual da indicação deve ser dinâmico em todos os textos comerciais');
$inicioPrograma = strpos($home, '<section id="programa-indicacao"');
$fimPrograma = strpos($home, '</section>', $inicioPrograma);
$programa = substr($home, $inicioPrograma, $fimPrograma - $inicioPrograma);
$assert(strpos($programa, '15%') === false, 'oferta do programa não pode fixar percentual de indicação');
$assert(strpos($programa, 'mensalidades futuras elegíveis') !== false, 'benefício de indicação deve informar desconto futuro e elegível');
$renderizarHome = function(array $campanhaIndicacaoPublica) use ($root){
    $planos = [];
    $whatsappSite = null;
    ob_start();
    require $root . '/app/Views/site/home.php';
    return ob_get_clean();
};
if(!defined('BASE_URL')) define('BASE_URL', 'https://disparador.test');
if(!defined('ASSET_URL')) define('ASSET_URL', 'https://disparador.test/public/assets');
$homeSemCampanha = $renderizarHome(['disponivel'=>false, 'percentual'=>null]);
$assert(strpos($homeSemCampanha, 'id="programa-indicacao"') === false, 'sem campanha elegível não deve exibir oferta de indicação');
$homeComCampanha = $renderizarHome(['disponivel'=>true, 'percentual'=>12.5]);
$assert(strpos($homeComCampanha, 'id="programa-indicacao"') !== false && strpos($homeComCampanha, '12,5% de desconto em mensalidades futuras elegíveis') !== false, 'campanha elegível deve exibir seu percentual configurado');
$assert(strpos($home, 'Quando uma indicação elegível é confirmada conforme as regras do programa') !== false, 'benefício de indicação deve depender de confirmação');
$assert(strpos($home, 'data-analytics-location="referral_program"') !== false && strpos($home, 'href="<?= BASE_URL; ?>/index.php?url=site/cadastro"') !== false, 'CTA do programa deve usar o cadastro público');
$assert(strpos($home, 'IndicacaoDescontoService') === false && strpos($home, 'IndicacaoWorkflowService') === false, 'Landing não deve conter cálculo ou regra de domínio');

echo "Public landing referral static checks passed\n";
