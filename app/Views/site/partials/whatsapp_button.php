<?php if(!empty($whatsappSite['ativo']) && !empty($whatsappSite['telefone']) && !empty($whatsappSite['mensagem'])){ ?>
<a
href="https://wa.me/<?= htmlspecialchars($whatsappSite['telefone'], ENT_QUOTES, 'UTF-8'); ?>?text=<?= rawurlencode($whatsappSite['mensagem']); ?>"
class="whatsapp-floating-button"
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
