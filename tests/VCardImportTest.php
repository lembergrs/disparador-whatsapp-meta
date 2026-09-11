<?php

require_once dirname(__DIR__) . '/app/Core/VCard.php';

use Core\VCard;

$assert = function($condition, $message){
    if(!$condition){
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ImportacaoController.php');
$upload = file_get_contents($root . '/app/Core/Upload.php');
$view = file_get_contents($root . '/app/Views/importacao/index.php');

$vcf = <<<'VCF'
BEGIN:VCARD
VERSION:3.0
FN:Maria da Silva
TEL;TYPE=CELL:+55 (41) 99999-1111
EMAIL:maria@example.com
END:VCARD
BEGIN:VCARD
VERSION:4.0
N:Souza;João;;Sr.;
item1.TEL;TYPE=cell;VALUE=uri:tel:+5541988882222
TEL;TYPE=work:+55 41 3333-4444
END:VCARD
BEGIN:VCARD
VERSION:2.1
N;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Oliveira;Jos=C3=A9;;;
TEL;CELL:41977773333
END:VCARD
BEGIN:VCARD
VERSION:3.0
FN:Contato sem telefone
EMAIL:semtelefone@example.com
END:VCARD
VCF;

$arquivo = tempnam(sys_get_temp_dir(), 'vcf_test_');
file_put_contents($arquivo, $vcf);

try{
    $linhas = VCard::ler($arquivo);
}finally{
    @unlink($arquivo);
}

$assert(count($linhas) === 6, 'VCF deve gerar cabeçalho, uma linha por telefone e registro sem telefone para contabilizar ignorados.');
$assert($linhas[0] === ['Nome', 'Telefone', 'Email'], 'Cabeçalho VCF deve ser compatível com o importador atual.');
$assert($linhas[1][0] === 'Maria da Silva', 'FN deve ser usado como nome principal.');
$assert($linhas[1][1] === '+55 (41) 99999-1111', 'Telefone deve ser preservado para normalização pelo importador.');
$assert($linhas[1][2] === 'maria@example.com', 'E-mail deve ser preservado nos dados do contato.');
$assert($linhas[2][0] === 'Sr. João Souza', 'N deve ser usado como fallback quando FN não existir.');
$assert($linhas[2][1] === '+5541988882222', 'URI tel: deve ser removida do valor do telefone.');
$assert($linhas[3][1] === '+55 41 3333-4444', 'Múltiplos telefones do mesmo contato devem ser processados separadamente.');
$assert($linhas[4][0] === 'José Oliveira', 'Quoted-printable do vCard 2.1 deve ser decodificado.');
$assert($linhas[5][1] === '', 'Contato sem telefone deve chegar ao importador para ser contabilizado como ignorado.');

$arquivoInvalido = tempnam(sys_get_temp_dir(), 'vcf_invalid_');
file_put_contents($arquivoInvalido, "arquivo sem vcard\n");
$erroVcfInvalido = false;

try{
    VCard::ler($arquivoInvalido);
}catch(\Exception $e){
    $erroVcfInvalido = strpos($e->getMessage(), 'Nenhum contato válido') !== false;
}finally{
    @unlink($arquivoInvalido);
}

$assert($erroVcfInvalido, 'Arquivo sem blocos VCARD deve ser rejeitado.');
$assert(strpos($upload, "'vcf'") !== false, 'Upload deve aceitar extensão VCF.');
$assert(strpos($upload, "'text/vcard'") !== false, 'Upload deve aceitar MIME text/vcard.');
$assert(strpos($controller, 'use Core\\VCard;') !== false, 'Controller deve carregar o parser VCard.');
$assert(strpos($controller, "if(\$extensao === 'vcf')") !== false, 'Controller deve selecionar parser VCard por extensão.');
$assert(strpos($controller, 'VCard::ler($arquivo)') !== false, 'Controller deve ler o arquivo VCF com o parser dedicado.');
$assert(strpos($view, '.xls,.xlsx,.vcf') !== false, 'Campo de upload deve anunciar suporte a VCF.');
$assert(strpos($view, 'exporte-os como arquivo .vcf') !== false, 'Tela deve orientar importação de contatos do celular.');

echo "VCard import checks passed\n";
