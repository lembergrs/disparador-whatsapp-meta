<?php
$guia = $onboardingChecklist;
$proxima = $guia['proxima'];
$contaGuia = $guia['conta'];
$escGuia = static function($valor){ return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); };
$urlGuia = static function($rota) use ($escGuia){ return $escGuia(BASE_URL . '/index.php?url=' . $rota); };
$numeroGuia = $contaGuia ? ($contaGuia['MTA_NumeroTelefone'] ?: $contaGuia['MTA_Nome']) : '';
$suporteTelefone = !empty($whatsappSuporte['ativo'])
    ? \Models\ConfiguracaoSite::normalizarTelefone($whatsappSuporte['telefone'] ?? '') : null;
$mensagemAjuda = 'Olá! Preciso de ajuda com o onboarding do Disparador.net. Etapa: '
    . ($proxima['titulo'] ?? 'Primeira mensagem entregue')
    . '. Gostaria de combinar um horário para atendimento.';
?>
<style>
.onboarding-guia { border-top: 3px solid #147d64; border-radius: .6rem; }
.onboarding-guia .guia-layout { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(240px, 1fr); gap: 1.5rem; }
.onboarding-guia .guia-proxima { padding: 1.5rem; background: #f0f8f5; border: 1px solid #c5e2d8; border-radius: .5rem; }
.onboarding-guia .guia-selo { color: #14664f; font-size: .8rem; font-weight: 700; letter-spacing: .07em; }
.onboarding-guia .guia-proxima h3 { font-size: 1.55rem; margin: .6rem 0 .85rem; }
.onboarding-guia .guia-proxima p { max-width: 68ch; }
.onboarding-guia .guia-acoes { display: flex; flex-wrap: wrap; gap: .65rem; align-items: center; }
.onboarding-guia .guia-acoes .btn { white-space: normal; }
.onboarding-guia .guia-etapas { list-style: none; padding: 0; margin: 0; }
.onboarding-guia .guia-etapas li { padding: .7rem 0; border-bottom: 1px solid #edf0f2; display: flex; gap: .65rem; align-items: baseline; }
.onboarding-guia .guia-etapas li:last-child { border: 0; }
.onboarding-guia .guia-etapas .atual { font-weight: 600; color: #14664f; }
.onboarding-guia .guia-etapas .aguardando { color: #626b73; }
.onboarding-guia .guia-contexto { padding: .85rem 1rem; background: #f6f7f8; border-radius: .4rem; margin-bottom: 1.25rem; }
.onboarding-guia .guia-ajuda { border-top: 1px solid #e3e8ec; padding-top: 1rem; margin-top: 1.5rem; }
.onboarding-guia .guia-opcionais { border-top: 1px solid #e3e8ec; padding-top: 1rem; margin-top: 1.5rem; }
.dashboard-informacoes > summary { cursor: pointer; padding: 1rem; font-weight: 600; color: #495057; }
.dashboard-informacoes > .dashboard-operacional { padding: 0 1rem 1rem; }
@media(max-width: 767px) {
    .onboarding-guia .guia-layout { grid-template-columns: minmax(0, 1fr); }
    .onboarding-guia .guia-proxima { padding: 1rem; }
    .onboarding-guia .guia-proxima h3 { font-size: 1.3rem; }
}
</style>

<section class="card onboarding-guia mb-4" aria-labelledby="tituloOnboarding">
    <div class="card-body">
        <?php if($guia['concluido']){ ?>
            <h2 id="tituloOnboarding" class="h4 text-success"><i class="fas fa-check-circle mr-1" aria-hidden="true"></i> Primeira mensagem entregue! ✓</h2>
            <p><?= $guia['recuperacao'] ? 'Sua primeira entrega continua registrada. Resolva a pendência abaixo para continuar usando este número.' : 'Seu Disparador está pronto para uso.'; ?></p>
        <?php }else{ ?>
            <h2 id="tituloOnboarding" class="h4">Configure seu Disparador.net</h2>
            <p class="text-muted">Vamos deixar sua conta pronta para enviar mensagens pela API Oficial do WhatsApp. Acompanhe as etapas abaixo e avance uma etapa por vez.</p>
        <?php } ?>

        <?php if($contaGuia || count($guia['contas']) > 1){ ?>
        <div class="guia-contexto">
            <?php if(count($guia['contas']) > 1){ ?>
                <form method="get" action="<?= $escGuia(BASE_URL); ?>/index.php" class="mb-0">
                    <input type="hidden" name="url" value="dashboard">
                    <label for="contaOnboarding">WhatsApp desta configuração</label>
                    <div class="d-flex flex-wrap align-items-center" style="gap:.5rem">
                        <select id="contaOnboarding" name="conta" class="form-control" style="max-width:420px" required>
                            <?php if(!$contaGuia){ ?><option value="">Selecione seu número</option><?php } ?>
                            <?php foreach($guia['contas'] as $opcao){ ?>
                            <option value="<?= (int) $opcao['MTA_ID']; ?>" <?= $contaGuia && (int) $contaGuia['MTA_ID'] === (int) $opcao['MTA_ID'] ? 'selected' : ''; ?>><?= $escGuia($opcao['MTA_Nome'] . ' · ' . $opcao['MTA_NumeroTelefone']); ?></option>
                            <?php } ?>
                        </select>
                        <button class="btn btn-outline-secondary" type="submit">Usar este WhatsApp</button>
                    </div>
                </form>
            <?php }else{ ?>
                <strong>WhatsApp desta configuração:</strong> <?= $escGuia($numeroGuia); ?>
            <?php } ?>
            <?php if($contaGuia && $proxima){ ?><p class="small text-muted mt-2 mb-0">Nas próximas telas, selecione este mesmo número: <strong><?= $escGuia($numeroGuia); ?></strong>.</p><?php } ?>
        </div>
        <?php } ?>

        <?php if($guia['pre_trial'] && !$guia['conectado']){ ?>
            <div class="alert alert-info">
                <strong>Seu período de avaliação ainda não começou.</strong>
                <p class="mb-0">Os 7 dias de avaliação começam quando a conexão do seu número do WhatsApp for concluída. A avaliação permite até 200 mensagens, conforme as regras atuais da plataforma.</p>
            </div>
        <?php }elseif(!empty($avaliacaoDashboard['ativo'])){ ?>
            <p class="text-muted"><strong>Seu período de avaliação começou.</strong>
                Você ainda tem <?= (int) $avaliacaoDashboard['dias_restantes']; ?> dia(s) de avaliação e
                <?= (int) $avaliacaoDashboard['mensagens_restantes']; ?> mensagem(ns) disponíveis no limite da avaliação.
            </p>
        <?php } ?>

        <div class="<?= !$guia['concluido'] ? 'guia-layout' : ''; ?>">
            <?php if($proxima){ ?>
            <div class="guia-proxima" data-onboarding-state="<?= $escGuia($proxima['id']); ?>">
                <span class="guia-selo"><?= $guia['recuperacao'] ? 'CONTINUE USANDO SEU WHATSAPP' : 'PRÓXIMO PASSO'; ?></span>
                <h3><?= $escGuia($proxima['titulo']); ?></h3>
                <p><?= $escGuia($proxima['descricao']); ?></p>
                <div class="guia-acoes">
                    <?php if($proxima['url']){ ?>
                    <a class="btn btn-success" href="<?= $proxima['tipo'] === 'externo' ? $escGuia($proxima['url']) : $urlGuia($proxima['url']); ?>" <?= $proxima['tipo'] === 'externo' ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?= $escGuia($proxima['label']); ?></a>
                    <?php } ?>
                    <?php if($proxima['id'] === 'pagamento_meta'){ ?>
                    <form method="post" action="<?= $urlGuia('configuracao/confirmarPagamentoMeta'); ?>" class="mb-0">
                        <?= \Core\Csrf::input(); ?>
                        <input type="hidden" name="conta_id" value="<?= (int) $contaGuia['MTA_ID']; ?>">
                        <button type="submit" class="btn btn-outline-success">Já configurei</button>
                    </form>
                    <?php } ?>
                    <?php if(strpos($proxima['id'], 'envio_') === 0){ ?>
                        <a class="btn btn-outline-secondary" href="<?= $urlGuia('dashboard&conta=' . (int) $contaGuia['MTA_ID']); ?>">Atualizar Dashboard</a>
                    <?php } ?>
                </div>
                <?php if($proxima['id'] === 'pagamento_meta'){ ?><p class="small text-muted mt-3 mb-0">Ao clicar em “Já configurei”, você declara que concluiu essa configuração. O Disparador não verifica tecnicamente a forma de pagamento da Meta.</p><?php } ?>
            </div>
            <?php } ?>

            <?php if(!$guia['concluido']){ ?>
            <div>
                <h3 class="h6">Seu progresso</h3>
                <div class="progress mb-2" style="height:8px" role="progressbar" aria-label="Progresso da configuração" aria-valuenow="<?= (int) $guia['percentual']; ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width:<?= (int) $guia['percentual']; ?>%"></div>
                </div>
                <p class="small text-muted"><?= (int) $guia['concluidos']; ?> de <?= (int) $guia['total']; ?> etapas concluídas</p>
                <ol class="guia-etapas">
                    <?php foreach($guia['itens'] as $etapa){ ?>
                    <li class="<?= $etapa['done'] ? 'text-success' : ($etapa['atual'] ? 'atual' : 'aguardando'); ?>" <?= $etapa['atual'] ? 'aria-current="step"' : ''; ?>>
                        <i class="<?= $etapa['done'] ? 'fas fa-check-circle' : 'far fa-circle'; ?>" aria-hidden="true"></i>
                        <span><?= $escGuia($etapa['label']); ?><?= $etapa['done'] ? '<span class="sr-only"> — concluída</span>' : ''; ?></span>
                    </li>
                    <?php } ?>
                </ol>
            </div>
            <?php } ?>
        </div>

        <?php if($guia['opcionais']){ ?>
        <div class="guia-opcionais">
            <h3 class="h6"><?= $guia['concluido'] ? 'Continue configurando, se fizer sentido para você' : 'Enquanto isso, você pode organizar seus contatos'; ?></h3>
            <p class="small text-muted">Você pode adicionar contatos manualmente ou importar uma planilha para uma lista. Estas ações são opcionais e não impedem a conclusão da ativação.</p>
            <div class="guia-acoes">
                <a class="btn btn-outline-secondary btn-sm" href="<?= $urlGuia('listaContato'); ?>">Organizar contatos</a>
                <?php if($guia['concluido'] && !$guia['recuperacao']){ ?><a class="btn btn-outline-secondary btn-sm" href="<?= $urlGuia('campanha'); ?>">Criar minha primeira campanha</a><?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if(!$guia['concluido'] || $guia['recuperacao']){ ?>
        <aside class="guia-ajuda" aria-label="Ajuda com a configuração">
            <h3 class="h6">Precisa de ajuda?</h3>
            <p class="small mb-2">O suporte de onboarding é realizado pelo WhatsApp. Envie uma mensagem e combinaremos o melhor horário para ajudar você.</p>
            <?php if($suporteTelefone){ ?>
                <a class="btn btn-outline-success btn-sm" target="_blank" rel="noopener noreferrer" href="<?= $escGuia('https://wa.me/' . $suporteTelefone . '?text=' . rawurlencode($mensagemAjuda)); ?>">Falar com o suporte pelo WhatsApp</a>
            <?php }else{ ?>
                <p class="small text-muted mb-0">O link do WhatsApp de suporte não está disponível nesta conta no momento. Utilize o canal oficial de contato informado pela RL2 Net.</p>
            <?php } ?>
        </aside>
        <?php } ?>
    </div>
</section>
