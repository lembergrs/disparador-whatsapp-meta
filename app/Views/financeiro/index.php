<?php
if(!function_exists('valorPlanoCicloFinanceiro')){
    function valorPlanoCicloFinanceiro($plano, $ciclo)
    {
        return \Models\Plano::valorPorCiclo($plano, $ciclo);
    }
}

if(!function_exists('statusFaturaFinanceiro')){
    function statusFaturaFinanceiro($status)
    {
        $status = (string) $status;
        $mapa = [
            'pendente' => 'Pendente',
            'pago' => 'Pago',
            'vencido' => 'Vencido',
            'cancelado' => 'Cancelado',
            'erro' => 'Erro'
        ];

        return $mapa[$status] ?? ucfirst($status ?: '-');
    }
}

if(!function_exists('badgeFaturaFinanceiro')){
    function badgeFaturaFinanceiro($status)
    {
        $classes = [
            'pendente' => 'warning',
            'pago' => 'success',
            'vencido' => 'danger',
            'cancelado' => 'secondary',
            'erro' => 'danger'
        ];

        return $classes[$status] ?? 'secondary';
    }
}

if(!function_exists('dataFinanceiro')){
    function dataFinanceiro($data)
    {
        return $data ? date('d/m/Y', strtotime($data)) : '-';
    }
}
?>
<?php

$avaliacao = \Core\Auth::dadosAvaliacaoCliente();
$assinaturaAtiva = !empty($assinaturaAtual) && ($assinaturaAtual['ASS_Status'] ?? '') === 'ativa';

?>

<?php if($avaliacao['ativo']){ ?>

    <div class="alert alert-info">

        <h5>
            <i class="fas fa-clock"></i>
            Período de avaliação ativo
        </h5>

        <p class="mb-1">
            Você pode usar o sistema durante a avaliação enquanto estiver dentro de
            <strong><?= $avaliacao['limite_dias']; ?> dias</strong>
            e abaixo de
            <strong><?= number_format($avaliacao['limite_mensagens'], 0, ',', '.'); ?> mensagens</strong>.
        </p>

        <p class="mb-0">
            Restam
            <strong><?= $avaliacao['dias_restantes']; ?> dia(s)</strong>
            e
            <strong><?= number_format($avaliacao['mensagens_restantes'], 0, ',', '.'); ?> mensagem(ns)</strong>
            até o limite gratuito.
        </p>

    </div>

<?php } ?>



<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Minha Assinatura</h3>
    </div>
    <div class="card-body">
        <?php if(!empty($assinaturaAtual)){ ?>
            <div class="row">
                <div class="col-md-2"><small class="text-muted d-block">Plano contratado</small><strong><?= htmlspecialchars($assinaturaAtual['PLA_Nome']); ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Ciclo</small><strong><?= htmlspecialchars($assinaturaAtual['ASS_Ciclo']); ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Valor</small><strong>R$ <?= number_format($assinaturaAtual['ASS_Valor'], 2, ',', '.'); ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Status</small><strong><?= ucfirst($assinaturaAtual['ASS_Status']); ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Próxima cobrança</small><strong><?= $assinaturaAtual['ASS_DataProximaCobranca'] ? date('d/m/Y', strtotime($assinaturaAtual['ASS_DataProximaCobranca'])) : '-'; ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Data de início</small><strong><?= $assinaturaAtual['ASS_DataInicio'] ? date('d/m/Y', strtotime($assinaturaAtual['ASS_DataInicio'])) : '-'; ?></strong></div>
            </div>
        <?php }else{ ?>
            <div class="alert alert-info mb-0">Você ainda não possui uma assinatura ativa.</div>
        <?php } ?>
    </div>
</div>

<div class="card mb-3" id="cardMinhasFaturas">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Minhas Faturas</h3>

        <div class="d-flex align-items-center">
            <label for="faturasPerPage" class="mb-0 mr-2 text-muted small">Exibir:</label>
            <select id="faturasPerPage" class="form-control form-control-sm" style="width:auto;">
                <?php foreach([5, 10, 20, 50] as $opcaoPerPage){ ?>
                    <option value="<?= $opcaoPerPage; ?>" <?= ((int) ($faturasPerPageDefault ?? 5)) === $opcaoPerPage ? 'selected' : ''; ?>>
                        <?= $opcaoPerPage; ?>
                    </option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Vencimento</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Forma</th>
                        <th>Data pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="faturasTabelaCorpo">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Carregando faturas...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <span id="faturasContador" class="text-muted small mb-2 mb-md-0">Carregando faturas...</span>

        <nav aria-label="Paginação de faturas">
            <ul class="pagination pagination-sm mb-0" id="faturasPaginacao"></ul>
        </nav>
    </div>
</div>

<?php if(
    !empty($excedente)
    &&
    $excedente['EXC_Mensagens'] > 0
){ ?>

    <div class="alert alert-info">

        <h5>

            Consumo Excedente

        </h5>

        <p class="mb-1">

            Mensagens excedentes:

            <strong>

                <?= number_format(
                    $excedente['EXC_Mensagens'],
                    0,
                    ',',
                    '.'
                ); ?>

            </strong>

        </p>

        <p class="mb-0">

            Valor acumulado:

            <strong>

                R$
                <?= number_format(
                    $excedente['EXC_ValorTotal'],
                    2,
                    ',',
                    '.'
                ); ?>

            </strong>

        </p>

    </div>

<?php } ?>


<?php if($assinaturaAtiva){ ?>
    <div class="mb-3">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnMostrarPlanosFinanceiro">
            Ver planos / Alterar plano
        </button>
    </div>
<?php } ?>

<style>
.planos-carousel-wrapper {
    position: relative;
}

.planos-header-acoes {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
    margin-left: auto;
}

.planos-carousel {
    display: flex;
    flex-wrap: nowrap;
    gap: 1rem;
    overflow-x: auto;
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
    padding-bottom: 0.5rem;
    scrollbar-width: thin;
}

.planos-carousel-item {
    flex: 0 0 calc((100% - 2rem) / 3);
    min-width: 280px;
    scroll-snap-align: start;
}

.planos-carousel-controle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    opacity: 0.92;
}

.planos-carousel-controle:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

@media (max-width: 991.98px) {
    .planos-carousel-item {
        flex-basis: calc((100% - 1rem) / 2);
    }
}

@media (max-width: 575.98px) {
    .planos-carousel-item {
        flex-basis: 100%;
        min-width: 100%;
    }

    .planos-header-acoes {
        gap: 0.1rem;
    }
}
</style>

<div class="card" id="cardPlanosFinanceiro" <?= $assinaturaAtiva ? 'style="display:none;"' : ''; ?>>

    <div class="card-header d-flex align-items-center justify-content-between">

        <h3 class="card-title mb-0">
            Planos disponíveis
        </h3>

        <div class="planos-header-acoes">
            <button type="button" class="btn btn-light btn-sm shadow-sm planos-carousel-controle" id="btnPlanosAnterior" aria-label="Plano anterior">
                <i class="fas fa-chevron-left"></i>
            </button>

            <button type="button" class="btn btn-light btn-sm shadow-sm planos-carousel-controle" id="btnPlanosProximo" aria-label="Próximo plano">
                <i class="fas fa-chevron-right"></i>
            </button>

            <?php if($assinaturaAtiva){ ?>
                <button type="button" class="btn btn-tool text-muted ml-2" id="btnFecharPlanosFinanceiro" aria-label="Fechar planos">
                    <i class="fas fa-times"></i>
                    <span class="d-none d-sm-inline ml-1">Fechar</span>
                </button>
            <?php } ?>
        </div>

    </div>

    <div class="card-body">

        <div class="planos-carousel-wrapper">
            <div class="planos-carousel" id="planosCarouselFinanceiro">

            <?php foreach($planos as $plano){ ?>

                <?php
                $numerosAtivosPlano =
                    (int) ($numerosAtivos ?? 0);

                $limiteNumerosPlano =
                    (int) $plano['PLA_LimiteNumeros'];

                $planoIncompativelNumeros =
                    $numerosAtivosPlano
                    >
                    $limiteNumerosPlano;

                $valorMensalPlano = valorPlanoCicloFinanceiro($plano, 'mensal');
                $valorTrimestralPlano = valorPlanoCicloFinanceiro($plano, 'trimestral');
                $valorSemestralPlano = valorPlanoCicloFinanceiro($plano, 'semestral');
                $valorAnualPlano = valorPlanoCicloFinanceiro($plano, 'anual');
                ?>

                <div class="planos-carousel-item mb-3">

                    <div class="card h-100">

                        <div class="card-body text-center">

                            <h4>
                                <?= htmlspecialchars($plano['PLA_Nome']); ?>
                            </h4>

                            <h2 class="text-success">
                                R$ <span class="valor-plano-ciclo">
                                    <?= number_format($valorMensalPlano, 2, ',', '.'); ?>
                                </span>
                            </h2>

                            <p>
                                <i class="fab fa-whatsapp text-success"></i>
                                <?= $plano['PLA_LimiteNumeros']; ?>
                                número(s) WhatsApp
                            </p>

                            <p>
                                <i class="fas fa-user text-info"></i>
                                <?= $plano['PLA_LimiteUsuarios']; ?>
                                usuário(s)
                            </p>

                            <p>
                                <i class="fas fa-paper-plane text-primary"></i>
                                <?= number_format(
                                    $plano['PLA_LimiteMensagens'],
                                    0,
                                    ',',
                                    '.'
                                ); ?>
                                mensagens/mês
                            </p>

                            <p class="text-muted small">
                                Excedente:
                                R$ <?= number_format(
                                    $plano['PLA_ValorMensagemExcedente'],
                                    4,
                                    ',',
                                    '.'
                                ); ?>
                                por mensagem adicional
                            </p>

                            <?php if($planoIncompativelNumeros){ ?>

                                <div class="alert alert-warning small">
                                    Plano incompatível com sua utilização atual.
                                    Reduza para no máximo
                                    <?= $limiteNumerosPlano; ?>
                                    número(s) conectado(s).
                                    Atualmente sua conta possui
                                    <?= $numerosAtivosPlano; ?>
                                    número(s) conectado(s).
                                </div>

                            <?php } ?>

                            <form
                            method="post"
                            action="<?= BASE_URL; ?>/index.php?url=financeiro/escolherPlano"
                            class="form-escolher-plano"
                            >

                                <input
                                type="hidden"
                                name="plano"
                                value="<?= $plano['PLA_ID']; ?>"
                                >

                                <div class="form-group text-left">
                                    <label>Ciclo de cobrança</label>
                                    <select
                                    name="ciclo"
                                    class="form-control select-ciclo-plano"
                                    data-mensal="<?= $valorMensalPlano; ?>"
                                    data-trimestral="<?= $valorTrimestralPlano; ?>"
                                    data-semestral="<?= $valorSemestralPlano; ?>"
                                    data-anual="<?= $valorAnualPlano; ?>"
                                    >
                                        <option value="mensal">Mensal</option>
                                        <option value="trimestral">Trimestral</option>
                                        <option value="semestral">Semestral</option>
                                        <option value="anual">Anual</option>
                                    </select>
                                </div>

                                <button
                                type="submit"
                                class="btn btn-success btn-block"
                                <?= $planoIncompativelNumeros ? 'disabled' : ''; ?>
                                >
                                    Escolher plano
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            <?php } ?>

            </div>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const btnMostrarPlanos = document.getElementById('btnMostrarPlanosFinanceiro');
    const btnFecharPlanos = document.getElementById('btnFecharPlanosFinanceiro');
    const cardPlanos = document.getElementById('cardPlanosFinanceiro');
    const planosCarousel = document.getElementById('planosCarouselFinanceiro');
    const btnPlanosAnterior = document.getElementById('btnPlanosAnterior');
    const btnPlanosProximo = document.getElementById('btnPlanosProximo');
    const faturasTabelaCorpo = document.getElementById('faturasTabelaCorpo');
    const faturasPaginacao = document.getElementById('faturasPaginacao');
    const faturasContador = document.getElementById('faturasContador');
    const faturasPerPage = document.getElementById('faturasPerPage');
    let faturasRequest = null;

    function carregarFaturas(pagina){
        if(!faturasTabelaCorpo || !faturasPaginacao || !faturasContador || !faturasPerPage || !window.jQuery){
            return;
        }

        const perPage = parseInt(faturasPerPage.value, 10) || 5;

        if(faturasRequest){
            faturasRequest.abort();
        }

        faturasTabelaCorpo.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Carregando faturas...</td></tr>';
        faturasContador.textContent = 'Carregando faturas...';

        faturasRequest = window.jQuery.ajax({
            url: '<?= BASE_URL; ?>/index.php?url=financeiro/faturasAjax',
            method: 'GET',
            dataType: 'json',
            data: {
                page: pagina || 1,
                per_page: perPage
            }
        })
        .done(function(resposta){
            if(!resposta || !resposta.sucesso){
                faturasTabelaCorpo.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Não foi possível carregar as faturas.</td></tr>';
                faturasPaginacao.innerHTML = '';
                faturasContador.textContent = 'Erro ao carregar faturas';
                return;
            }

            faturasTabelaCorpo.innerHTML = resposta.html || '';
            faturasPaginacao.innerHTML = resposta.paginacao_html || '';
            faturasContador.textContent = resposta.contador_html || '';
        })
        .fail(function(xhr, status){
            if(status === 'abort'){
                return;
            }

            faturasTabelaCorpo.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Não foi possível carregar as faturas.</td></tr>';
            faturasPaginacao.innerHTML = '';
            faturasContador.textContent = 'Erro ao carregar faturas';
        })
        .always(function(){
            faturasRequest = null;
        });
    }

    if(faturasPerPage){
        faturasPerPage.addEventListener('change', function(){
            carregarFaturas(1);
        });
    }

    if(faturasPaginacao){
        faturasPaginacao.addEventListener('click', function(event){
            const link = event.target.closest('.pagina-faturas');

            if(!link){
                return;
            }

            event.preventDefault();
            carregarFaturas(parseInt(link.dataset.page, 10) || 1);
        });
    }

    carregarFaturas(1);

    function atualizarControlesPlanos(){
        if(!planosCarousel || !btnPlanosAnterior || !btnPlanosProximo){
            return;
        }

        const maxScroll = planosCarousel.scrollWidth - planosCarousel.clientWidth;
        const deveExibirControles = maxScroll > 2;

        btnPlanosAnterior.style.display = deveExibirControles ? 'flex' : 'none';
        btnPlanosProximo.style.display = deveExibirControles ? 'flex' : 'none';
        btnPlanosAnterior.disabled = planosCarousel.scrollLeft <= 2;
        btnPlanosProximo.disabled = planosCarousel.scrollLeft >= (maxScroll - 2);
    }

    function rolarPlanos(direcao){
        if(!planosCarousel){
            return;
        }

        const item = planosCarousel.querySelector('.planos-carousel-item');
        const deslocamento = item ? item.getBoundingClientRect().width + 16 : planosCarousel.clientWidth;

        planosCarousel.scrollBy({
            left: direcao * deslocamento,
            behavior: 'smooth'
        });
    }

    if(btnMostrarPlanos && cardPlanos){
        btnMostrarPlanos.addEventListener('click', function(){
            btnMostrarPlanos.style.display = 'none';

            if(window.jQuery){
                window.jQuery(cardPlanos)
                    .stop(true, true)
                    .slideDown(250, atualizarControlesPlanos);
            }else{
                cardPlanos.style.display = '';
                atualizarControlesPlanos();
            }
        });
    }

    if(btnFecharPlanos && cardPlanos){
        btnFecharPlanos.addEventListener('click', function(){
            if(window.jQuery){
                window.jQuery(cardPlanos)
                    .stop(true, true)
                    .slideUp(250, function(){
                        if(btnMostrarPlanos){
                            btnMostrarPlanos.style.display = '';
                        }
                    });
            }else{
                cardPlanos.style.display = 'none';

                if(btnMostrarPlanos){
                    btnMostrarPlanos.style.display = '';
                }
            }
        });
    }

    if(btnPlanosAnterior){
        btnPlanosAnterior.addEventListener('click', function(){
            rolarPlanos(-1);
        });
    }

    if(btnPlanosProximo){
        btnPlanosProximo.addEventListener('click', function(){
            rolarPlanos(1);
        });
    }

    if(planosCarousel){
        planosCarousel.addEventListener('scroll', function(){
            window.requestAnimationFrame(atualizarControlesPlanos);
        });
    }

    window.addEventListener('resize', atualizarControlesPlanos);
    atualizarControlesPlanos();

    document.querySelectorAll('.form-escolher-plano').forEach(function(form){
        form.addEventListener('submit', function(event){
            if(form.dataset.enviando === 'S'){
                event.preventDefault();
                return false;
            }

            form.dataset.enviando = 'S';

            const botao = form.querySelector('button[type="submit"]');

            if(botao){
                botao.disabled = true;
                botao.dataset.textoOriginal = botao.textContent;
                botao.textContent = 'Processando...';
            }
        });
    });

    document.querySelectorAll('.select-ciclo-plano').forEach(function(select){
        select.addEventListener('change', function(){
            const card = select.closest('.card-body');
            const valor = parseFloat(select.dataset[select.value] || '0');
            const alvo = card ? card.querySelector('.valor-plano-ciclo') : null;

            if(alvo){
                alvo.textContent = valor.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        });
    });
});
</script>
