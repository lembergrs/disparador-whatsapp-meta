<?php

namespace Services;

use Core\Database;
use PDO;

class FinanceiroRecorrenciaService
{
    private $db;
    private $diasTolerancia;

    public function __construct($diasTolerancia = null)
    {
        $this->db = Database::getInstance();
        $this->diasTolerancia = (int) (
            $diasTolerancia
            ?? (defined('FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO')
                ? FINANCEIRO_DIAS_TOLERANCIA_VENCIMENTO
                : 5)
        );
    }

    public function processarVencimentos()
    {
        $resultado = [
            'cobrancas_vencidas' => 0,
            'assinaturas_vencidas' => 0,
            'clientes_atualizados' => 0,
            'dias_tolerancia' => $this->diasTolerancia
        ];

        $cobrancas = $this->buscarCobrancasPendentesVencidas();

        if(empty($cobrancas)){
            $this->registrarLog('Nenhuma cobrança pendente vencida encontrada.');
            return $resultado;
        }

        $this->db->beginTransaction();

        try{
            $clientesAtualizados = [];
            $assinaturasVencidas = [];

            foreach($cobrancas as $cobranca){
                if($this->marcarCobrancaVencida($cobranca['COB_ID'])){
                    $resultado['cobrancas_vencidas']++;
                }

                $clienteId = (int) $cobranca['CLI_ID'];
                $assinatura = $this->buscarAssinaturaVigenteCliente($clienteId);

                if(
                    $assinatura
                    && !isset($assinaturasVencidas[$assinatura['ASS_ID']])
                    && $this->marcarAssinaturaVencida($assinatura['ASS_ID'])
                ){
                    $assinaturasVencidas[$assinatura['ASS_ID']] = true;
                    $resultado['assinaturas_vencidas']++;
                }

                if(
                    !isset($clientesAtualizados[$clienteId])
                    && $this->marcarClientePendente($clienteId)
                ){
                    $clientesAtualizados[$clienteId] = true;
                    $resultado['clientes_atualizados']++;
                }
            }

            $this->db->commit();

            $this->registrarLog(
                'Vencimentos processados: ' . json_encode($resultado, JSON_UNESCAPED_UNICODE)
            );

            return $resultado;
        }catch(\Exception $e){
            $this->db->rollBack();
            $this->registrarLog('Erro ao processar vencimentos: ' . $e->getMessage());
            throw $e;
        }
    }

    private function buscarCobrancasPendentesVencidas()
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM cobrancas
            WHERE COB_Status = 'pendente'
            AND COB_DataVencimento < CURDATE()
            ORDER BY COB_DataVencimento ASC, COB_ID ASC
        ");

        $sql->execute();

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    private function marcarCobrancaVencida($cobrancaId)
    {
        $sql = $this->db->prepare("
            UPDATE cobrancas
            SET COB_Status = 'vencido'
            WHERE COB_ID = ?
            AND COB_Status = 'pendente'
        ");

        $sql->execute([$cobrancaId]);

        return $sql->rowCount() > 0;
    }

    private function buscarAssinaturaVigenteCliente($clienteId)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM assinaturas
            WHERE CLI_ID = ?
            AND ASS_Status IN ('ativa','pendente')
            ORDER BY
                CASE ASS_Status
                    WHEN 'ativa' THEN 1
                    WHEN 'pendente' THEN 2
                    ELSE 3
                END,
                ASS_ID DESC
            LIMIT 1
        ");

        $sql->execute([$clienteId]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    private function marcarAssinaturaVencida($assinaturaId)
    {
        $sql = $this->db->prepare("
            UPDATE assinaturas
            SET ASS_Status = 'vencida',
                ASS_DataFim = COALESCE(ASS_DataFim, CURDATE()),
                ASS_DataAtualizacao = NOW()
            WHERE ASS_ID = ?
            AND ASS_Status IN ('ativa','pendente')
        ");

        $sql->execute([$assinaturaId]);

        return $sql->rowCount() > 0;
    }

    private function marcarClientePendente($clienteId)
    {
        $sql = $this->db->prepare("
            UPDATE clientes
            SET CLI_StatusPagamento = 'pendente'
            WHERE CLI_ID = ?
            AND CLI_StatusPagamento <> 'pendente'
        ");

        $sql->execute([$clienteId]);

        return $sql->rowCount() > 0;
    }

    private function registrarLog($mensagem)
    {
        $diretorio = dirname(__DIR__, 2) . '/storage/logs';

        if(!is_dir($diretorio)){
            mkdir($diretorio, 0755, true);
        }

        file_put_contents(
            $diretorio . '/financeiro_vencimentos.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL,
            FILE_APPEND
        );
    }
}
