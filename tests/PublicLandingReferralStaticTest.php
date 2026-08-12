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
$assert(strpos($home, 'R$ <?= number_format($valorPrimeiroPagamento, 2, \',\', \'.\'); ?> no primeiro pagamento') !== false, 'preço promocional do primeiro pagamento ausente');
$assert(strpos($home, '<del>R$ <?= number_format($valorMensal, 2, \',\', \'.\'); ?></del>') !== false, 'preço mensal regular deve permanecer visível');
$assert(strpos($home, 'A partir do 2º mês: <strong>R$ <?= number_format($valorMensal, 2, \',\', \'.\'); ?>/mês</strong>') !== false, 'preço mensal a partir do segundo mês ausente');
$assert(strpos($home, '50% de desconto na primeira mensalidade para novos clientes.') !== false, 'benefício inicial de 50% ausente');
$assert(strpos($home, 'Este benefício é válido com ou sem indicação.') !== false, 'benefício inicial deve ser independente de indicação');
$assert(strpos($home, 'crédito de 15% de desconto em mensalidades futuras elegíveis') !== false, 'benefício de indicação deve informar desconto futuro e elegível');
$assert(strpos($home, 'Quando uma indicação elegível é confirmada conforme as regras do programa') !== false, 'benefício de indicação deve depender de confirmação');
$assert(strpos($home, 'data-analytics-location="referral_program"') !== false && strpos($home, 'href="<?= BASE_URL; ?>/index.php?url=site/cadastro"') !== false, 'CTA do programa deve usar o cadastro público');
$assert(strpos($home, 'IndicacaoDescontoService') === false && strpos($home, 'IndicacaoWorkflowService') === false, 'Landing não deve conter cálculo ou regra de domínio');

echo "Public landing referral static checks passed\n";
