<?php

$root = dirname(__DIR__);
$home = file_get_contents($root . '/app/Views/site/home.php');

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(substr_count($home, '<h1') === 1, 'Home deve manter somente um H1');
$assert(strpos($home, '$valorPrimeiroPagamento = $valorMensal / 2;') !== false, 'valor do primeiro pagamento deve derivar do preço mensal');
$assert(strpos($home, '<span class="site-valor-primeiro-pagamento">R$ <?= number_format($valorPrimeiroPagamento, 2, \',\', \'.\'); ?></span><span> no primeiro pagamento</span>') !== false, 'preço promocional do primeiro pagamento ausente');
$assert(strpos($home, '.site-valor-primeiro-pagamento') !== false && strpos($home, 'font-size: 2.125rem;') !== false, 'valor promocional deve ter destaque visual moderado');
$assert(strpos($home, '<del>R$ <?= number_format($valorMensal, 2, \',\', \'.\'); ?></del>') !== false, 'preço mensal regular deve permanecer visível');
$assert(strpos($home, 'A partir do 2º mês: <strong>R$ <?= number_format($valorMensal, 2, \',\', \'.\'); ?>/mês</strong>') !== false, 'preço mensal a partir do segundo mês ausente');
$assert(strpos($home, '50% de desconto na primeira mensalidade para novos clientes.') !== false, 'benefício inicial de 50% ausente');
$assert(strpos($home, 'Este benefício é válido com ou sem indicação.') !== false, 'benefício inicial deve ser independente de indicação');
$assert(strpos($home, 'O indicado faz o cadastro') !== false, 'passo quatro deve descrever o cadastro do indicado');
$assert(strpos($home, 'A empresa indicada acessa o cadastro pelo link ou informa o código de indicação manualmente.') !== false, 'passo quatro deve informar link ou código manual');
$assert(strpos($home, 'Depois que a indicação for confirmada conforme as regras do programa, você recebe o crédito de 15% para mensalidades futuras elegíveis.') !== false, 'fluxo deve encerrar com o crédito futuro elegível');
$assert(strpos($home, 'crédito de 15% de desconto em mensalidades futuras elegíveis') !== false, 'benefício de indicação deve informar desconto futuro e elegível');
$assert(strpos($home, 'Quando uma indicação elegível é confirmada conforme as regras do programa') !== false, 'benefício de indicação deve depender de confirmação');
$assert(strpos($home, 'data-analytics-location="referral_program"') !== false && strpos($home, 'href="<?= BASE_URL; ?>/index.php?url=site/cadastro"') !== false, 'CTA do programa deve usar o cadastro público');
$assert(strpos($home, 'IndicacaoDescontoService') === false && strpos($home, 'IndicacaoWorkflowService') === false, 'Landing não deve conter cálculo ou regra de domínio');

echo "Public landing referral static checks passed\n";
