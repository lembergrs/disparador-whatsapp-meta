<?php

$view = file_get_contents(__DIR__ . '/../app/Views/dashboard/index.php');
$service = file_get_contents(__DIR__ . '/../app/Services/OnboardingChecklistService.php');
assert(strpos($view, 'Primeiros passos') !== false);
assert(strpos($view, "empty($" . "onboardingChecklist['concluido'])") !== false);
assert(strpos($service, 'percentual') !== false);
assert(strpos($service, 'Cadastro concluído') !== false);

echo "OnboardingChecklistStaticTest OK\n";
