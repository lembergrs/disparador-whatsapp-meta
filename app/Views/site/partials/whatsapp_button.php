<?php if(!empty($whatsappSite['ativo']) && !empty($whatsappSite['telefone']) && !empty($whatsappSite['mensagem'])){ ?>
<a
href="https://wa.me/<?= htmlspecialchars($whatsappSite['telefone'], ENT_QUOTES, 'UTF-8'); ?>?text=<?= rawurlencode($whatsappSite['mensagem']); ?>"
class="whatsapp-floating-button"
data-analytics-event="click_whatsapp"
data-analytics-location="<?= htmlspecialchars($analyticsWhatsappLocation ?? 'floating_button', ENT_QUOTES, 'UTF-8'); ?>"
target="_blank"
rel="noopener noreferrer"
aria-label="Falar com o Disparador.net pelo WhatsApp"
title="Fale conosco pelo WhatsApp"
>
    <i class="fab fa-whatsapp" aria-hidden="true"></i>
    <span class="whatsapp-floating-label">Fale conosco</span>
    <span class="sr-only">Fale conosco pelo WhatsApp</span>
</a>
<?php } ?>
