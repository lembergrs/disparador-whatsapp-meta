<?php
require __DIR__ . '/fixtures/OnboardingDashboardFixture.php';
define('BASE_URL', 'https://disparador.test');
$_SESSION = ['csrf_token'=>'token-fixture'];

function onboardingRender(array $onboardingChecklist, ?array $whatsappSuporte = null, array $avaliacaoDashboard = []): string
{
    ob_start();
    require dirname(__DIR__) . '/app/Views/dashboard/_onboarding.php';
    return ob_get_clean();
}

function onboardingDom(string $html): DOMXPath
{
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    return new DOMXPath($doc);
}

$db = onboardingDb();
$pre = onboardingCalculate($db, onboardingAccess(true));
$support = ['ativo'=>true,'telefone'=>'5541999999999'];
$html = onboardingRender($pre, $support);
$dom = onboardingDom($html);
onboardingAssert(str_contains($html, 'Seu período de avaliação ainda não começou.'), 'Falta texto claro de pré-trial.');
onboardingAssert(str_contains($html, 'Os 7 dias de avaliação começam quando a conexão do seu número do WhatsApp for concluída.'), 'Início do trial precisa mencionar número.');
onboardingAssert($dom->query('//*[@data-onboarding-state="conexao_iniciar"]//a')->item(0)->getAttribute('href') === BASE_URL . '/index.php?url=configuracao/meta', 'CTA pré-trial deve abrir conexão.');
onboardingAssert($dom->query('//ol//a')->length === 0, 'Etapas bloqueadas não podem encaminhar ao Financeiro.');
onboardingAssert($dom->query('//form[@method="post"]')->length === 0, 'Pré-trial não pode sugerir mutações extras.');
onboardingAssert(!str_contains($html, 'url=campanha') && !str_contains($html, 'url=listaContato'), 'Pré-trial não oferece módulos bloqueados.');
onboardingAssert(str_contains($html,'https://wa.me/5541999999999?text=') && str_contains($html,'combinaremos o melhor horário'), 'Suporte institucional e texto de agendamento informal.');
onboardingAssert(!preg_match('/atendimento imediato|fale conosco agora/i', $html), 'Não prometer atendimento imediato.');
onboardingAssert(!str_contains(onboardingRender($pre), 'https://wa.me/'), 'Sem configuração não inventar contato.');

$preHtml = $html;
onboardingAccount($db, 1, 'conectado', null);
$payment = onboardingCalculate($db);
$html = onboardingRender($payment, $support);
$dom = onboardingDom($html);
onboardingAssert(str_contains($html, 'RL2 Net') && str_contains($html,'diretamente pela Meta'), 'Separar assinatura e tarifas Meta.');
onboardingAssert(str_contains($html, 'não verifica tecnicamente') && str_contains($html, 'você declara'), 'Pagamento deve ser declaratório.');
onboardingAssert($dom->query('//form[@method="post"]')->length === 1, 'Usar apenas confirmação existente.');
onboardingAssert($dom->query('//form[@method="post"]')->item(0)->getAttribute('action') === BASE_URL . '/index.php?url=configuracao/confirmarPagamentoMeta', 'POST deve reutilizar endpoint.');
onboardingAssert($dom->query('//input[@name="conta_id"]')->item(0)->getAttribute('value') === '1', 'POST deve carregar a conta contextual.');
onboardingAssert($dom->query('//input[@name="csrf_token"]')->item(0)->getAttribute('value') === 'token-fixture', 'POST exige CSRF.');
onboardingAssert(str_contains($html,'rel="noopener noreferrer"'), 'Link externo deve proteger aba de origem.');
$paymentHtml = $html;

$db->exec("UPDATE meta_contas SET MTA_PagamentoMetaStatus='confirmado_cliente'");
foreach(['PENDING'=>'Atualizar situação','REJECTED'=>'Ver meus templates','APPROVED'=>'Enviar minha primeira mensagem'] as $status=>$label){
    $db->exec('DELETE FROM templates_meta'); onboardingTemplate($db, $status);
    $state = onboardingCalculate($db); $html = onboardingRender($state, $support);
    $dom = onboardingDom($html);
    onboardingAssert($dom->query('//*[@data-onboarding-state]//a')->item(0)->textContent === $label, 'CTA de template incorreto.');
    onboardingAssert(!str_contains($html, 'name="conta_id"'), 'Pagamento já declarado não deve pedir confirmação de novo.');
    if($status === 'PENDING'){
        onboardingAssert(str_contains($html, 'Enquanto isso') && str_contains($html, 'Estas ações são opcionais'), 'Contatos opcionais durante espera.');
        $pendingHtml = $html;
    }
    if($status === 'APPROVED'){
        onboardingAssert(str_contains($html, 'Não é necessário importar contatos'), 'Primeiro envio dispensa importação.');
        onboardingAssert($dom->query('//*[@data-onboarding-state]//a')->item(0)->getAttribute('href') === BASE_URL . '/index.php?url=disparo', 'Primeira mensagem usa Disparo Manual.');
    }
}

foreach(['pendente'=>'Estamos processando','aguardando_confirmacao'=>'aceita pela Meta','sent'=>'Sua mensagem foi enviada','failed'=>'Não foi possível concluir'] as $status=>$text){
    $db->exec('DELETE FROM conversa_mensagens'); onboardingMessage($db, $status);
    if($status === 'pendente') $db->exec('UPDATE conversa_mensagens SET MSG_MetaMessageId=NULL');
    $html = onboardingRender(onboardingCalculate($db), $support);
    onboardingAssert(str_contains($html, $text) && !str_contains($html, 'Primeira mensagem entregue!'), 'View não pode antecipar entrega.');
}
$db->exec("UPDATE conversa_mensagens SET MSG_Status='delivered'");
$delivered = onboardingCalculate($db); $html = onboardingRender($delivered, $support);
onboardingAssert(str_contains($html, 'Primeira mensagem entregue!') && !str_contains($html, 'PRÓXIMO PASSO'), 'Entrega reduz guia principal.');
onboardingAssert(str_contains($html, 'Criar minha primeira campanha') && !str_contains($html, 'conta incompleta'), 'Campanha é evolução opcional.');
onboardingAssert(onboardingDom($html)->query('//ol')->length === 0, 'Guia principal deve sair após ativação.');
$deliveredHtml = $html;
$db->exec("UPDATE meta_contas SET MTA_Status='desconectado'");
$html = onboardingRender(onboardingCalculate($db), $support);
onboardingAssert(str_contains($html, 'Primeira mensagem entregue!') && str_contains($html, 'Reconectar WhatsApp'), 'Recuperação preserva conquista.');
onboardingAssert(!str_contains($html,'Seu Disparador está pronto para uso.'), 'Não prometer aptidão com desconexão.');

onboardingAccount($db, 2);
$db->exec("UPDATE meta_contas SET MTA_Nome='<script>alert(1)</script>', MTA_NumeroTelefone='<img src=x onerror=alert(1)>' WHERE MTA_ID=2");
$html = onboardingRender(onboardingCalculate($db, null, 2), $support);
$dom = onboardingDom($html);
onboardingAssert($dom->query('//script')->length === 0 && !str_contains($html,'<img src=x'), 'Escapar nomes e números na view.');
onboardingAssert($dom->query('//select[@name="conta"]/option[@selected]')->item(0)->getAttribute('value') === '2', 'Seleção explícita deve ser visível.');

// Renderização da view inteira com avisos convertidos em falhas.
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
$onboardingChecklist = $pre; $whatsappSuporte = $support; $avaliacaoDashboard = [];
$usuario = ['nivel'=>'cliente_admin']; $cliente = ['CLI_StatusPagamento'=>'pendente'];
$metaConta = null; $consumo = null; $excedente = null; $ultimasCampanhas = [];
$clientes = $contasMeta = $templates = $contatos = $conversas = $naoLidas = $campanhas = $mensagensRecebidas = 0;
ob_start(); require dirname(__DIR__) . '/app/Views/dashboard/index.php'; $fullHtml = ob_get_clean();
restore_error_handler();
$dom = onboardingDom($fullHtml);
onboardingAssert($dom->query('//details[contains(@class,"dashboard-informacoes")]')->length === 1, 'Painel operacional deve ficar secundário.');
onboardingAssert($dom->query('//details[@open]')->length === 0, 'Painel deve iniciar recolhido no onboarding.');
onboardingAssert($dom->query('//details//section[contains(@class,"onboarding-guia")]')->length === 0, 'Próximo passo deve estar fora do painel recolhido.');
onboardingAssert(!str_contains($fullHtml, 'UNKNOWN') && !str_contains($fullHtml,'CONNECTED') && !str_contains($fullHtml, '>Nunca<'), 'Códigos e Nunca não devem confundir iniciantes.');
onboardingAssert(!str_contains($fullHtml, 'Plano:</strong> Não informado'), 'Plano ausente não deve dominar pré-trial.');

// Artefatos temporários opcionais para inspeção visual, sem executar aplicação ou integrações.
if($previewDir = getenv('ONBOARDING_PREVIEW_DIR')){
    foreach(['pretrial'=>$fullHtml,'pagamento'=>$paymentHtml,'analise'=>$pendingHtml,'ativado'=>$deliveredHtml] as $name=>$body){
        file_put_contents($previewDir . '/' . $name . '.html', '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Prévia de onboarding</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"></head><body style="background:#f4f6f9"><main class="container py-4">' . $body . '</main></body></html>');
    }
}
echo "OnboardingGuidedDashboardViewTest OK: CTAs, trial, pagamento, progresso, suporte, escaping e painel recolhido.\n";
