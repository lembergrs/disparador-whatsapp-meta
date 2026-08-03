<?php
$gtmId = defined('GOOGLE_TAG_MANAGER_ID') ? trim((string) GOOGLE_TAG_MANAGER_ID) : '';
if($gtmId !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', $gtmId)){
    $gtmId = '';
}
$gtmSection = $googleTagManagerSection ?? 'head';
$analyticsEventosPendentes = $gtmSection === 'head' && class_exists('Services\\AnalyticsService')
    ? \Services\AnalyticsService::consumir()
    : [];
?>
<?php if($gtmSection === 'head'){ ?>
<script>
window.dataLayer = window.dataLayer || [];
window.Disparador = window.Disparador || {};
window.Disparador.analytics = window.Disparador.analytics || {
    push: function(evento, dados) {
        window.dataLayer = window.dataLayer || [];
        dados = dados || {};
        var payload = {event: evento};
        Object.keys(dados).forEach(function(chave) { payload[chave] = dados[chave]; });
        window.dataLayer.push(payload);
    }
};
document.addEventListener('click', function(e) {
    var alvo = e.target.closest ? e.target.closest('[data-analytics-event]') : null;
    if(!alvo) return;
    var evento = alvo.getAttribute('data-analytics-event');
    var dados = {location: alvo.getAttribute('data-analytics-location') || 'unknown'};
    if(evento === 'select_trial') {
        dados = {
            cta_location: alvo.getAttribute('data-analytics-location') || 'unknown',
            destination_type: alvo.getAttribute('data-analytics-destination') || 'registration'
        };
        var plano = alvo.getAttribute('data-analytics-plan');
        if(plano) dados.plan_name = plano;
    }
    window.Disparador.analytics.push(evento, dados);
});
<?php foreach($analyticsEventosPendentes as $analyticsEvento){ ?>
window.Disparador.analytics.push(
    <?= json_encode($analyticsEvento['evento'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    <?= json_encode($analyticsEvento['dados'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
);
<?php } ?>
</script>
<?php if($gtmId !== ''){ ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer',<?= json_encode($gtmId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);</script>
<!-- End Google Tag Manager -->
<?php } ?>
<?php }elseif($gtmSection === 'body' && $gtmId !== ''){ ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= htmlspecialchars($gtmId, ENT_QUOTES, 'UTF-8'); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php } ?>
