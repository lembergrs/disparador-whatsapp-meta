<?php

$view = file_get_contents(__DIR__ . '/../app/Views/dashboard/index.php') . file_get_contents(__DIR__ . '/../app/Views/dashboard/_onboarding.php');
$service = file_get_contents(__DIR__ . '/../app/Services/OnboardingChecklistService.php');
assert(strpos($view, 'Configure seu Disparador.net') !== false);
assert(strpos($view, "empty($" . "onboardingChecklist['concluido'])") !== false);
assert(strpos($service, 'percentual') !== false);
assert(strpos($service, 'Cadastro realizado') !== false);
assert(strpos($service, "'Primeira mensagem entregue'") !== false);

echo "OnboardingChecklistStaticTest OK\n";
