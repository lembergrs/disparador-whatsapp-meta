<?php

namespace Services;

use Exception;

class MetaHealthService
{
    const META_WHATSAPP_MANAGER_URL = 'https://business.facebook.com/wa/manage/home/';
    const META_BUSINESS_SETTINGS_URL = 'https://business.facebook.com/settings/';
    const META_BUSINESS_SUPPORT_URL = 'https://business.facebook.com/business-support-home/';

    public static function consultarConta(array $conta)
    {
        if(empty($conta['MTA_Token']) || empty($conta['MTA_WabaId'])){
            return [
                'disponivel' => false,
                'can_send_message' => null,
                'erros' => [],
                'mensagem' => 'O diagnóstico ficará disponível após a conclusão da conexão com a Meta.'
            ];
        }

        try{
            $retorno = self::graphGet(
                $conta,
                (string) $conta['MTA_WabaId'],
                ['fields' => 'id,name,health_status']
            );

            $healthStatus = isset($retorno['health_status']) && is_array($retorno['health_status'])
                ? $retorno['health_status']
                : [];

            if(empty($healthStatus)){
                return [
                    'disponivel' => false,
                    'can_send_message' => null,
                    'erros' => [],
                    'mensagem' => 'A Meta não retornou informações de saúde para esta conta neste momento.'
                ];
            }

            $erros = [];

            foreach(($healthStatus['entities'] ?? []) as $entidade){
                if(!is_array($entidade)){
                    continue;
                }

                foreach(($entidade['errors'] ?? []) as $erro){
                    if(!is_array($erro)){
                        continue;
                    }

                    $erros[] = self::normalizarErro(
                        $erro,
                        (string) ($entidade['entity_type'] ?? ''),
                        (string) ($entidade['can_send_message'] ?? '')
                    );
                }
            }

            return [
                'disponivel' => true,
                'can_send_message' => strtoupper((string) ($healthStatus['can_send_message'] ?? '')),
                'erros' => $erros,
                'mensagem' => null
            ];
        }catch(Exception $e){
            return [
                'disponivel' => false,
                'can_send_message' => null,
                'erros' => [],
                'mensagem' => 'Não foi possível consultar o diagnóstico da Meta agora. Tente novamente mais tarde.'
            ];
        }
    }

    private static function normalizarErro(array $erro, $entityType, $entityCanSendMessage)
    {
        $codigo = (string) ($erro['error_code'] ?? '');
        $descricaoOriginal = trim((string) ($erro['error_description'] ?? ''));
        $solucaoOriginal = trim((string) ($erro['possible_solution'] ?? ''));

        $catalogo = self::catalogoErros();
        $conhecido = $catalogo[$codigo] ?? null;

        return [
            'codigo' => $codigo,
            'entidade' => strtoupper($entityType),
            'can_send_message' => strtoupper($entityCanSendMessage),
            'titulo' => $conhecido['titulo'] ?? 'Atenção necessária na Meta',
            'descricao' => $conhecido['descricao'] ?? ($descricaoOriginal !== '' ? $descricaoOriginal : 'A Meta identificou uma pendência nesta conta.'),
            'solucao' => $conhecido['solucao'] ?? $solucaoOriginal,
            'acao' => $conhecido['acao'] ?? 'Abrir WhatsApp Manager',
            'url' => $conhecido['url'] ?? self::META_WHATSAPP_MANAGER_URL,
            'nivel' => $conhecido['nivel'] ?? 'warning'
        ];
    }

    private static function catalogoErros()
    {
        return [
            '141006' => [
                'titulo' => 'Problema na forma de pagamento',
                'descricao' => 'A Meta informou um problema na forma de pagamento desta conta. Isso pode bloquear mensagens iniciadas pela empresa.',
                'solucao' => 'Acesse a Meta e revise ou substitua a forma de pagamento vinculada à conta do WhatsApp Business.',
                'acao' => 'Corrigir pagamento na Meta',
                'url' => self::META_WHATSAPP_MANAGER_URL,
                'nivel' => 'danger'
            ],
            '131042' => [
                'titulo' => 'Problema de cobrança na Meta',
                'descricao' => 'A Meta informou uma falha relacionada à cobrança ou à forma de pagamento da conta do WhatsApp Business.',
                'solucao' => 'Revise a configuração de cobrança e a forma de pagamento diretamente na Meta.',
                'acao' => 'Corrigir pagamento na Meta',
                'url' => self::META_WHATSAPP_MANAGER_URL,
                'nivel' => 'danger'
            ],
            '141010' => [
                'titulo' => 'Empresa ainda não verificada',
                'descricao' => 'A Meta informou que a empresa ainda não concluiu a verificação empresarial.',
                'solucao' => 'Acesse as Configurações do Negócio da Meta e conclua ou resolva a verificação da empresa.',
                'acao' => 'Verificar empresa na Meta',
                'url' => self::META_BUSINESS_SETTINGS_URL,
                'nivel' => 'warning'
            ],
            '141014' => [
                'titulo' => 'Conta do WhatsApp Business desabilitada',
                'descricao' => 'A Meta informou que esta conta do WhatsApp Business está banida ou desabilitada.',
                'solucao' => 'Acesse o Business Support Home para consultar a restrição e, quando disponível, solicitar uma análise.',
                'acao' => 'Abrir suporte da Meta',
                'url' => self::META_BUSINESS_SUPPORT_URL,
                'nivel' => 'danger'
            ],
            '368' => [
                'titulo' => 'Conta restringida pela Meta',
                'descricao' => 'A conta do WhatsApp Business associada foi restringida ou desabilitada por uma política da plataforma.',
                'solucao' => 'Consulte a restrição no Business Support Home e siga as orientações da Meta.',
                'acao' => 'Abrir suporte da Meta',
                'url' => self::META_BUSINESS_SUPPORT_URL,
                'nivel' => 'danger'
            ],
            '131031' => [
                'titulo' => 'Conta restringida ou dados não verificados',
                'descricao' => 'A Meta restringiu a conta ou não conseguiu validar dados informados na solicitação.',
                'solucao' => 'Consulte o Business Support Home e revise as configurações da conta antes de tentar novamente.',
                'acao' => 'Abrir suporte da Meta',
                'url' => self::META_BUSINESS_SUPPORT_URL,
                'nivel' => 'danger'
            ]
        ];
    }

    private static function graphGet(array $conta, $endpoint, array $params)
    {
        $base = rtrim((string) ($conta['MTA_UrlBase'] ?? ''), '/');

        if($base === ''){
            throw new Exception('URL base da Meta não configurada.');
        }

        $url = $base . '/' . ltrim((string) $endpoint, '/') . '?' . http_build_query($params);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $conta['MTA_Token']
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12
        ]);

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if($curlError){
            throw new Exception('Falha de comunicação com a Meta.');
        }

        $json = json_decode((string) $response, true);

        if($httpCode >= 400 || !is_array($json)){
            throw new Exception('A Meta não retornou o diagnóstico da conta.');
        }

        return $json;
    }
}
