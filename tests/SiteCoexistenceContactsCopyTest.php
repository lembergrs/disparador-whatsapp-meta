<?php

$raiz = dirname(__DIR__);
$home = file_get_contents($raiz . '/app/Views/site/home.php');
$whatsappBusiness = file_get_contents($raiz . '/app/Views/site/whatsapp_business.php');

$chamada = 'Seus contatos do WhatsApp Business podem ser sincronizados automaticamente com o Disparador.net.';
$ressalva = 'A sincronização depende dos contatos e eventos disponibilizados pela Meta para o número conectado.';

if(strpos($home, $chamada) === false){
    fwrite(STDERR, "Falha: a home não destaca a sincronização de contatos do WhatsApp Business.\n");
    exit(1);
}

if(strpos($whatsappBusiness, $chamada) === false){
    fwrite(STDERR, "Falha: a página WhatsApp Business não destaca a sincronização de contatos.\n");
    exit(1);
}

if(strpos($home, $ressalva) === false || strpos($whatsappBusiness, $ressalva) === false){
    fwrite(STDERR, "Falha: a comunicação precisa manter a ressalva de disponibilidade dos dados pela Meta.\n");
    exit(1);
}

if(strpos($whatsappBusiness, 'sincronização integral de todos os contatos') === false){
    fwrite(STDERR, "Falha: a página WhatsApp Business deve evitar promessa de sincronização integral.\n");
    exit(1);
}

echo "OK: comunicação de sincronização de contatos validada.\n";
