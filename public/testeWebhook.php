<?php

$json = [

    'entry' => [

        [

            'changes' => [

                [

                    'value' => [

                        'messages' => [

                            [

                                'from' =>
                                    '5541998121080',

                                'id' =>
                                    'wamid.TESTE123',

                                'timestamp' =>
                                    time(),

                                'type' =>
                                    'text',

                                'text' => [

                                    'body' =>
                                        'Olá, gostaria de saber mais informações.'

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