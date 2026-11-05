<?php
// Run: php tests/TemplateHeaderMediaTest.php
require_once dirname(__DIR__) . '/app/Services/MetaService.php';

function headerAssert($condition, $message)
{
    if(!$condition){
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$reflection = new ReflectionClass(\\Services\\MetaService::class);
$service = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('montarHeaderMidiaEnvio');
$method->setAccessible(true);
$template = [
    'TMP_HeaderTipo' => 'IMAGE',
    'TMP_HeaderMidiaUrlExemplo' => '',
    'TMP_HeaderDocumentoNome' => '',
    'TMP_Componentes' => json_encode([['type'=>'HEADER','format'=>'IMAGE']]),
];

foreach([
    [[], 'missing image'],
    [['link'=>'https://scontent.whatsapp.net/v/t61.29466-34/example.jpg'], 'temporary Meta example'],
] as $case){
    $failed = false;
    try{
        $method->invoke($service, $template, $case[0]);
    }catch(\\Exception $e){
        $failed = true;
    }
    headerAssert($failed, $case[1] . ' must be rejected');
}

$result = $method->invoke($service, $template, ['media_id'=>'123456']);
headerAssert(($result['parameters'][0]['image']['id'] ?? null) === '123456', 'media ID should be sent as image header');

$result = $method->invoke($service, $template, ['link'=>'https://example.org/image.jpg']);
headerAssert(($result['parameters'][0]['image']['link'] ?? null) === 'https://example.org/image.jpg', 'valid HTTPS link should be sent as image header');

$root = dirname(__DIR__);
$model = file_get_contents($root . '/app/Models/TemplateMeta.php');
$controller = file_get_contents($root . '/app/Controllers/TemplateController.php');
$preview = file_get_contents($root . '/app/Services/TemplateMediaPreviewService.php');
$view = file_get_contents($root . '/app/Views/templates/index.php');
headerAssert(strpos($model, "\$componente['media_url']") !== false, 'sync must preserve component media URL');
headerAssert(strpos($controller, 'if(!$salvo)') !== false, 'controller must detect persistence failures');
headerAssert(strpos($preview, 'is_writable($dir)') !== false, 'preview directory must be writable');
headerAssert(strpos($view, 'function renderizarHeaderMidiaTemplate') !== false, 'preview must render media');
echo "Template header media tests passed" . PHP_EOL;
