<?php

namespace Controllers;

use Core\Controller;

class AsaasController extends Controller
{
    public function webhook()
    {
        if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'){
            $this->responderJson(['sucesso' => false, 'erro' => 'Método não permitido'], 405);
        }

        $corpoBruto = file_get_contents('php://input');
        $payload = json_decode($corpoBruto ?: '', true);

        if(!is_array($payload)){
            $payload = [];
        }

        $validacaoToken = $this->validarTokenWebhook();
        $this->registrarWebhook($payload, $validacaoToken);

        if(!$validacaoToken['valido']){
            $this->responderJson(['sucesso' => false, 'erro' => 'Token inválido'], 403);
        }

        // TODO: processar o evento de forma idempotente em uma fila interna.
        // TODO: atualizar cobrança pelo webhook quando asaas_payment_id existir.
        // TODO: liberar cliente após pagamento confirmado.

        $this->responderJson(['sucesso' => true], 200);
    }

    private function validarTokenWebhook()
    {
        $tokenConfigurado = trim((string) ASAAS_WEBHOOK_TOKEN);
        $tokenRecebido = $this->obterHeader('asaas-access-token');

        // O Asaas envia o authToken configurado no webhook no header
        // "asaas-access-token". Se a documentação mudar, ajustar somente este ponto.
        // Nunca validar token via query string.
        if($tokenConfigurado === ''){
            return ['valido' => true, 'resultado' => 'token_nao_configurado'];
        }

        if($tokenRecebido === null || trim($tokenRecebido) === ''){
            return ['valido' => false, 'resultado' => 'token_ausente'];
        }

        return [
            'valido' => hash_equals($tokenConfigurado, trim($tokenRecebido)),
            'resultado' => hash_equals($tokenConfigurado, trim($tokenRecebido)) ? 'token_valido' : 'token_invalido'
        ];
    }

    private function obterHeader($nome)
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        foreach($headers as $header => $valor){
            if(strtolower($header) === strtolower($nome)){
                return $valor;
            }
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $nome));

        return $_SERVER[$serverKey] ?? null;
    }

    private function registrarWebhook($payload, $validacaoToken)
    {
        $diretorioLog = diretorioLogsProjeto();

        if(!is_dir($diretorioLog)){
            mkdir($diretorioLog, 0770, true);
        }

        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $customer = $payment['customer'] ?? ($payload['customer'] ?? null);

        $linha = [
            'data_hora' => date('Y-m-d H:i:s'),
            'evento' => $this->limparValorLog($payload['event'] ?? ''),
            'payment_id' => $this->limparValorLog($payment['id'] ?? ''),
            'customer_id' => $this->limparValorLog($customer ?? ''),
            'status' => $this->limparValorLog($payment['status'] ?? ''),
            'valor' => $this->limparValorLog($payment['value'] ?? ''),
            'ip' => $this->limparValorLog($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => $this->limparValorLog($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'validacao' => $validacaoToken['resultado']
        ];

        error_log(json_encode($linha, JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $diretorioLog . '/asaas-webhook.log');
    }

    private function limparValorLog($valor)
    {
        $valor = is_scalar($valor) ? (string) $valor : '';
        $valor = preg_replace('/[\r\n\t]+/', ' ', $valor);

        return mb_substr($valor, 0, 255);
    }

    private function responderJson($dados, $status)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
