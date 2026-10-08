<section id="planos" class="py-5">
<div class="container"><div class="text-center mb-4"><span class="badge badge-success mb-3">Planos simples</span><h2 class="site-section-title">Escolha o plano ideal para começar</h2><p class="text-muted">Todos os planos oferecem as ferramentas essenciais. Escolha pela quantidade de números, usuários e mensagens que sua empresa precisa.</p></div>
<div class="alert alert-light border text-center mb-4"><strong>Em todos os planos:</strong> campanhas com templates oficiais, listas de contatos e central de atendimento. Números elegíveis podem continuar usando o WhatsApp Business no celular.</div>
<?php
$planos = is_array($planos ?? null) ? $planos : [];
$coresPermitidas = ['primary','secondary','success','danger','warning','info','light','dark'];
$formatarQuantidade = function($quantidade,$singular,$plural){ $quantidade=(int)$quantidade; return number_format($quantidade,0,',','.') . ' ' . ($quantidade===1?$singular:$plural); };
?>
<?php if(!empty($planos)){ ?><div class="site-planos-header-acoes" aria-label="Navegação dos planos"><button type="button" class="btn btn-outline-success site-planos-carousel-controle" id="sitePlanosAnterior" aria-label="Plano anterior"><i class="fas fa-chevron-left"></i></button><button type="button" class="btn btn-outline-success site-planos-carousel-controle" id="sitePlanosProximo" aria-label="Próximo plano"><i class="fas fa-chevron-right"></i></button></div>
<p class="text-center text-muted small mb-2">Compare a capacidade de cada plano. O teste gratuito permite conhecer a plataforma antes da contratação.</p>
<div class="site-planos-carousel mt-4" id="sitePlanosCarousel">
<?php foreach($planos as $plano){ $corPlano=in_array($plano['PLA_Cor']??'',$coresPermitidas,true)?$plano['PLA_Cor']:'primary'; $valorMensal=\Models\Plano::valorPorCiclo($plano,'mensal'); $ofertaMensal=$ofertasPublicasPlanos[(int)$plano['PLA_ID']]['mensal']??[]; $valorPrimeiroPagamento=((int)($ofertaMensal['primeira_cobranca_centavos']??0))/100; $valorDesconto=((int)($ofertaMensal['desconto_centavos']??0))/100; $recomendado=stripos((string)$plano['PLA_Nome'],'profissional')!==false; ?>
<div class="site-plano-carousel-item"><div class="card border-<?= $corPlano; ?> h-100"><div class="card-body p-4 text-center">
<?php
$nomePlano = mb_strtolower((string)($plano['PLA_Nome']??''),'UTF-8');
$perfilPlano = 'Para sua empresa';
if(strpos($nomePlano,'básico')!==false || strpos($nomePlano,'basico')!==false){ $perfilPlano = 'Para começar'; }
elseif(strpos($nomePlano,'profissional')!==false){ $perfilPlano = 'Para crescer'; }
elseif(strpos($nomePlano,'empresarial')!==false){ $perfilPlano = 'Para operações maiores'; }
$temDescontoPrimeiraMensalidade = $valorMensal > 0 && $valorPrimeiroPagamento > 0 && $valorPrimeiroPagamento < $valorMensal;
$percentualDesconto = $temDescontoPrimeiraMensalidade ? round((1 - $valorPrimeiroPagamento / $valorMensal) * 100) : 0;
?>
<?php if($recomendado){ ?><span class="badge badge-success d-block mb-2">Recomendado</span><?php } ?>
<h3 class="h5 font-weight-bold mb-1"><?= htmlspecialchars($plano['PLA_Nome'],ENT_QUOTES,'UTF-8'); ?></h3>
<p class="text-muted small mb-3"><?= htmlspecialchars($perfilPlano,ENT_QUOTES,'UTF-8'); ?></p>
<?php if($temDescontoPrimeiraMensalidade){ ?>
<div class="mb-1"><del class="text-muted">R$ <?= number_format($valorMensal,2,',','.'); ?></del> <span class="badge badge-success ml-1"><?= $percentualDesconto; ?>% OFF</span></div>
<p class="text-success font-weight-bold mb-1"><span class="site-valor-primeiro-pagamento">R$ <?= number_format($valorPrimeiroPagamento,2,',','.'); ?></span></p>
<p class="text-muted small mb-1">na primeira mensalidade</p>
<p class="mb-3">A partir do segundo mês: <strong>R$ <?= number_format($valorMensal,2,',','.'); ?>/mês</strong></p>
<?php }else{ ?>
<p class="text-success font-weight-bold mb-1"><span class="site-valor-primeiro-pagamento">R$ <?= number_format($valorMensal,2,',','.'); ?>/mês</span></p>
<p class="text-muted small mb-3">Mensalidade do Disparador.net</p>
<?php } ?>
<p class="text-muted"><?= $formatarQuantidade($plano['PLA_LimiteNumeros']??0,'número WhatsApp','números WhatsApp'); ?></p><hr><p><i class="fas fa-users text-success"></i> <?= $formatarQuantidade($plano['PLA_LimiteUsuarios']??0,'usuário','usuários'); ?></p><p><i class="fas fa-paper-plane text-primary"></i> <?= $formatarQuantidade($plano['PLA_LimiteMensagens']??0,'mensagem/mês','mensagens/mês'); ?></p><p><i class="fas fa-check text-success"></i> Campanhas, listas, templates e atendimento</p>
<a href="<?= BASE_URL; ?>/index.php?url=site/cadastro" class="btn btn-outline-success btn-block" data-analytics-event="select_trial" data-analytics-location="pricing" data-analytics-destination="registration" data-analytics-plan="<?= htmlspecialchars($plano['PLA_Nome'],ENT_QUOTES,'UTF-8'); ?>">Começar teste grátis</a>
</div></div></div><?php } ?></div>
<?php }else{ ?><div class="alert alert-light border text-center">Os planos estão sendo atualizados. Solicite acesso para receber uma proposta adequada à sua operação.</div><?php } ?>

<?php
$enterpriseMensagem = 'Olá! Conheci o plano Enterprise do Disparador.net e gostaria de conversar sobre uma configuração personalizada para minha empresa.';
$enterpriseWhatsappUrl = null;
if(!empty($whatsappSite['ativo']) && !empty($whatsappSite['telefone'])){
    $enterpriseWhatsappUrl = 'https://wa.me/'
        . rawurlencode($whatsappSite['telefone'])
        . '?text='
        . rawurlencode($enterpriseMensagem);
}
?>
<div class="card border-dark mt-4">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-8 text-center text-lg-left">
                <span class="badge badge-dark mb-2">Enterprise</span>
                <h3 class="h4 font-weight-bold mb-2">Precisa de mais números, usuários ou mensagens?</h3>
                <p class="text-muted mb-3 mb-lg-0">
                    Para empresas com necessidades que vão além dos planos disponíveis.
                    Conte para nós o que sua empresa precisa e estudamos uma configuração personalizada para sua operação.
                </p>
            </div>
            <div class="col-lg-4 text-center text-lg-right">
                <p class="font-weight-bold mb-2">Plano personalizado · Sob consulta</p>
                <?php if($enterpriseWhatsappUrl){ ?>
                    <a
                    href="<?= htmlspecialchars($enterpriseWhatsappUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    class="btn btn-success"
                    target="_blank"
                    rel="noopener noreferrer"
                    data-analytics-event="click_whatsapp"
                    data-analytics-location="pricing_enterprise"
                    data-analytics-plan="Enterprise"
                    >
                        <i class="fab fa-whatsapp mr-1"></i> Falar com nossa equipe
                    </a>
                <?php }else{ ?>
                    <a
                    href="<?= BASE_URL; ?>/index.php?url=site/cadastro"
                    class="btn btn-outline-success"
                    data-analytics-event="select_enterprise"
                    data-analytics-location="pricing_enterprise"
                    data-analytics-destination="registration"
                    data-analytics-plan="Enterprise"
                    >
                        Falar com nossa equipe
                    </a>
                <?php } ?>
            </div>
        </div>
        <hr>
        <div class="row text-center small">
            <div class="col-md-3 mb-2 mb-md-0"><i class="fas fa-check text-success mr-1"></i> Mais números de WhatsApp</div>
            <div class="col-md-3 mb-2 mb-md-0"><i class="fas fa-check text-success mr-1"></i> Mais usuários</div>
            <div class="col-md-3 mb-2 mb-md-0"><i class="fas fa-check text-success mr-1"></i> Maior volume de mensagens</div>
            <div class="col-md-3"><i class="fas fa-check text-success mr-1"></i> Limites ajustados à operação</div>
        </div>
    </div>
</div>

<p class="text-center text-muted mt-3 mb-0">Não encontrou a configuração ideal? Fale com nossa equipe sobre o plano Enterprise.</p><div class="alert alert-light border text-center mt-3"><strong>A franquia corresponde ao uso do Disparador.net.</strong> Tarifas cobradas pela Meta não estão incluídas e seguem a política oficial vigente.</div><div class="text-center"><h3 class="h4 font-weight-bold">E se minha empresa ultrapassar a franquia?</h3><p class="text-muted">As mensagens excedentes à franquia do plano são cobradas conforme o consumo, além das tarifas aplicáveis da Meta. O envio continua sujeito às regras e aos limites da plataforma e da Meta.</p></div>
</div></section>
