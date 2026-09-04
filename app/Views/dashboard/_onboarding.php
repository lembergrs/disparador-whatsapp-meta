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
            <p class="small mb-2">Registre seu pedido e informe quando é melhor entrarmos em contato. Nossa equipe acompanhará a solicitação e falará com você pelo WhatsApp.</p>
            <button type="button" class="btn btn-outline-success btn-sm" data-toggle="modal" data-target="#modalAjudaOnboarding">
                <i class="fas fa-headset mr-1" aria-hidden="true"></i> Solicitar ajuda
            </button>
        </aside>

        <div class="modal fade" id="modalAjudaOnboarding" tabindex="-1" role="dialog" aria-labelledby="tituloModalAjudaOnboarding" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="post" action="<?= $urlGuia('onboardingSuporte/solicitar'); ?>" class="modal-content">
                    <?= \Core\Csrf::input(); ?>
                    <input type="hidden" name="conta_id" value="<?= $contaGuia ? (int) $contaGuia['MTA_ID'] : ''; ?>">
                    <input type="hidden" name="etapa" value="<?= $escGuia($proxima['id'] ?? 'onboarding_concluido_recuperacao'); ?>">
                    <div class="modal-header">
                        <h4 class="modal-title h5" id="tituloModalAjudaOnboarding">Solicitar ajuda com esta etapa</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Etapa atual: <strong><?= $escGuia($proxima['titulo'] ?? 'Recuperação da configuração'); ?></strong></p>

                        <div class="form-group">
                            <label for="ajudaAssunto">Como podemos ajudar?</label>
                            <select class="form-control" id="ajudaAssunto" name="assunto" required>
                                <option value="">Selecione</option>
                                <option value="nao_consigo_avancar">Não consigo avançar nesta etapa</option>
                                <option value="duvida_configuracao">Tenho uma dúvida sobre a configuração</option>
                                <option value="mensagem_erro">Recebi uma mensagem de erro</option>
                                <option value="orientacao">Preciso de orientação</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="ajudaDescricao">Conte um pouco mais <span class="text-muted">(opcional)</span></label>
                            <textarea class="form-control" id="ajudaDescricao" name="descricao" rows="3" maxlength="1000" placeholder="Ex.: aparece uma mensagem de erro quando tento concluir esta etapa."></textarea>
                            <small class="form-text text-muted">Não envie senhas, códigos de verificação ou tokens de acesso.</small>
                        </div>

                        <div class="form-group">
                            <label for="ajudaPeriodo">Melhor período para contato</label>
                            <select class="form-control" id="ajudaPeriodo" name="periodo" required>
                                <option value="manha">Manhã</option>
                                <option value="tarde">Tarde</option>
                                <option value="noite">Noite</option>
                                <option value="qualquer" selected>Qualquer horário</option>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <label for="ajudaHorario">Detalhe de horário <span class="text-muted">(opcional)</span></label>
                            <input class="form-control" id="ajudaHorario" name="horario" maxlength="120" placeholder="Ex.: dias úteis, entre 14h e 17h">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Enviar solicitação</button>
                    </div>
                </form>
            </div>
        </div>
        <?php } ?>
    </div>
</section>
