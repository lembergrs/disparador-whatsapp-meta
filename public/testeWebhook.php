<?php

$phoneNumberId =
    '1012186325321264';

$host = $_SERVER['HTTP_HOST'] ?? '';

if ($host === 'disparador.test') {
    $urlWebhook = 'http://disparador.test/webhook/meta.php';
} else {
    $urlWebhook = 'ttps://disparador.rosemegamania.com/webhook/meta.php';
}


$retorno = null;

$numero =
    $_POST['numero']
    ?? '41999999999';

$nome =
    $_POST['nome']
    ?? 'Cliente Teste';

$mensagem =
    $_POST['mensagem']
    ?? '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $numeroLimpo =
        preg_replace(
            '/\D/',
            '',
            $numero
        );

    if(substr($numeroLimpo, 0, 2) != '55'){
        $numeroLimpo = '55' . $numeroLimpo;
    }

    $json = [

        'entry' => [

            [

                'changes' => [

                    [

                        'value' => [

                            'metadata' => [

                                'phone_number_id' =>
                                    $phoneNumberId

                            ],

                            'contacts' => [

                                [

                                    'profile' => [

                                        'name' =>
                                            $nome

                                    ],

                                    'wa_id' =>
                                        $numeroLimpo

                                ]

                            ],

                            'messages' => [

                                [

                                    'from' =>
                                        $numeroLimpo,

                                    'id' =>
                                        'wamid.TESTE_RECEBIDA_'
                                        . time(),

                                    'timestamp' =>
                                        time(),

                                    'type' =>
                                        'text',

                                    'text' => [

                                        'body' =>
                                            $mensagem

                                    ]

                                ]

                            ]

                        ]

                    ]

                ]

            ]

        ]

    ];

    $curl = curl_init();

    curl_setopt_array($curl,[

        CURLOPT_URL =>
            $urlWebhook,

        CURLOPT_POST => true,

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [

            'Content-Type: application/json'

        ],

        CURLOPT_POSTFIELDS =>
            json_encode($json)

    ]);

    $retorno =
        curl_exec($curl);

    curl_close($curl);
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Teste Webhook</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

</head>

<body class="p-4">

<div class="container">

<div class="card">

<div class="card-header">

<h3 class="card-title">
Simular Mensagem Recebida
</h3>

</div>

<div class="card-body">

<?php if($retorno){ ?>

<div class="alert alert-success">

Retorno do webhook:
<strong><?= $retorno; ?></strong>

</div>

<?php } ?>

<form method="POST">

<div class="form-group">

<label>Número</label>

<input
type="text"
name="numero"
class="form-control"
value="<?= htmlspecialchars($numero); ?>"
placeholder="(41) 99999-9999"
required
>

<small class="text-muted">
Digite apenas DDD + número. O sistema adiciona 55 automaticamente.
</small>

</div>

<div class="form-group">

<label>Nome do contato</label>

<input
type="text"
name="nome"
class="form-control"
value="<?= htmlspecialchars($nome); ?>"
required
>

</div>

<div class="form-group">

<label>Mensagem</label>

<textarea
name="mensagem"
class="form-control"
rows="4"
required
><?= htmlspecialchars($mensagem); ?></textarea>

</div>

<button
type="submit"
class="btn btn-success"
>

Enviar para Webhook

</button>

<a
href="http://disparador.test/index.php?url=conversa"
class="btn btn-secondary"
>

Abrir Conversas

</a>

</form>

</div>

</div>

</div>

</body>

</html>