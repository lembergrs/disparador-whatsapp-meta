<?php

require_once __DIR__ . '/../app/Services/TelefoneService.php';
require_once __DIR__ . '/../app/Services/SegmentacaoDestinatariosService.php';

use Services\SegmentacaoDestinatariosService as Segmentacao;

function segmentacaoAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

$a = ['CON_ID'=>1,'CON_Telefone'=>'41999990000','CON_Ativo'=>'S'];
$b = ['CON_ID'=>2,'CON_Telefone'=>'41988880000','CON_Ativo'=>'S'];
$c = ['CON_ID'=>3,'CON_Telefone'=>'41977770000','CON_Ativo'=>'N'];
$lista = [$a,$b,$c,array_merge($a,['CON_ID'=>4,'CON_Telefone'=>'+55 41 99999-0000'])];
$conversas = [
    ['CVS_Numero'=>'5541999990000','ETQ_ID'=>8],
    ['CVS_Numero'=>'41999990000','ETQ_ID'=>12],
    ['CVS_Numero'=>'41988880000','ETQ_ID'=>8],
    ['CVS_Numero'=>'41977770000','ETQ_ID'=>8],
    ['CVS_Numero'=>'41966660000','ETQ_ID'=>12],
];
$somenteLista = Segmentacao::consolidar($lista, [], [], [], 'OR');
segmentacaoAssert(count($somenteLista) === 2, 'Lista deve excluir inativos e duplicados.');
$or = Segmentacao::consolidar($lista, $conversas, [], [8,12], 'OR');
segmentacaoAssert(count($or) === 2, 'Interseção com qualquer etiqueta.');
$and = Segmentacao::consolidar($lista, $conversas, [], [8,12], 'AND');
segmentacaoAssert(count($and) === 1 && $and[0]['contato_id'] === 1, 'Interseção com todas as etiquetas.');
$tags = Segmentacao::consolidar(null, $conversas, [$a,$b,$c], [8,12], 'OR');
segmentacaoAssert(count($tags) === 3, 'Etiquetas devem excluir inativos e incluir conversa sem cadastro.');
segmentacaoAssert($tags[2]['contato_id'] === null, 'Conversa sem contato deve ser identificada.');
segmentacaoAssert(Segmentacao::consolidar([], $conversas, [], [8], 'OR') === [], 'Lista vazia não pode ampliar público.');
segmentacaoAssert(Segmentacao::consolidar(null, [], [], [8], 'OR') === [], 'Etiqueta sem conversas não pode ampliar público.');
segmentacaoAssert(Segmentacao::assinatura(['5541999990000','5541988880000']) === Segmentacao::assinatura(['5541988880000','5541999990000','5541999990000']), 'Assinatura independente da ordem e duplicidade.');
segmentacaoAssert(Segmentacao::assinatura(['5541999990000','5541988880000']) !== Segmentacao::assinatura(['5541999990000','5541977770000']), 'Substituição com mesmo total deve mudar assinatura.');
echo "Segmentacao destinatarios tests passed\n";
