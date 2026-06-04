<?php

$json = [

    'entry' => [

        [

            'changes' => [

                [

                    'value' => [

                        'metadata' => [

                            'phone_number_id' =>
                                '1012186325321264'

                        ],

                        'contacts' => [

                            [

                                'profile' => [

                                    'name' =>
                                        'Cliente Teste'

                                ],

                                'wa_id' =>
                                    '5541999999999'

                            ]

                        ],

                        'messages' => [

                            [

                                'from' =>
                                    '5541999999999',

                                'id' =>
                                    'wamid.TESTE_RECEBIDA_001',

                                'timestamp' =>
                                    time(),

                                'type' =>
                                    'text',

                                'text' => [

                                    'body' =>
                                        'Olá, estou respondendo a mensagem de teste.'

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
        'http://disparador.test/webhook/meta.php',

    CURLOPT_POST => true,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_HTTPHEADER => [

        'Content-Type: application/json'

    ],

    CURLOPT_POSTFIELDS =>
        json_encode($json)

]);

echo curl_exec($curl);

curl_close($curl);