<?php

$root = dirname(__DIR__);
$home = file_get_contents($root . '/app/Views/site/home.php');
$pricing = file_get_contents($root . '/app/Views/site/precos_whatsapp_meta.php');
$publicCopy = $home . "\n" . $pricing;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$assert(strpos($home, 'Tarifas da WhatsApp Business Platform cobradas pela Meta não estão incluídas na mensalidade') !== false, 'independência entre franquia e tarifas da Meta ausente');
$assert(strpos($home, '1º de outubro de 2026') !== false, 'data da atualização da Meta ausente');
$assert(strpos($home, 'mensagens de Serviço') !== false, 'mensagens de Serviço ausentes');
$assert(strpos($home, 'templates de Utilidade') !== false, 'templates de Utilidade ausentes');
$assert(!preg_match('/R\$\s*0[,.]0(?:3(?:5)?|5)\b/', $home), 'tarifa fixa da Meta encontrada');
$assert(strpos($pricing, 'dentro ou fora da janela de atendimento passam a ser cobrados por mensagem') !== false, 'Utilidade deve ser cobrada dentro e fora da janela a partir da vigência');
$assert(strpos($pricing, 'janela gratuita de 72 horas') !== false, 'Free Entry Point de 72 horas ausente');
$assert(strpos($publicCopy, '1.000 mensagens de Serviço') !== false, 'franquia mensal de Serviço ausente');
$assert(strpos($publicCopy, '1.001ª mensagem de Serviço') !== false, 'início da cobrança de Serviço após a franquia ausente');
$assert(strpos($publicCopy, 'não utilizam essa franquia') !== false, 'Utilidade deve ficar fora da franquia de Serviço');
$assert(!preg_match('/(?:Serviço|Service)[^.]{0,120}(?:desde a primeira|todas?[^.]{0,20}cobrad)/iu', $publicCopy), 'Serviço não pode ser apresentado como cobrado desde a primeira mensagem');
$assert(!preg_match('/(?:Utility|Utilidade)[^.]{0,100}(?:grátis|gratuit)[^.]{0,40}(?:24\s*(?:h|horas)|janela)/iu', $publicCopy), 'Utilidade não pode ser apresentada como gratuita dentro de 24 horas após a vigência');
$assert(strpos($publicCopy, 'mensalidade do Disparador.net') !== false && strpos($publicCopy, 'tarifas') !== false, 'mensalidade Disparador e tarifas Meta devem permanecer separadas');
$assert(substr_count($home, '<h1') === 1, 'Home deve manter somente um H1');

foreach(['header', 'hero', 'pricing', 'final_cta'] as $localizacao){
    $assert(strpos($home, 'data-analytics-location="' . $localizacao . '"') !== false, 'CTA principal ausente: ' . $localizacao);
}

echo "Public landing Meta pricing static checks passed\n";
