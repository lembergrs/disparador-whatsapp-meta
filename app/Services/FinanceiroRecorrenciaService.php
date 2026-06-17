<?php

namespace Services;

use Core\Database;
use Models\Cobranca;
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


    public function gerarCobrancasRecorrentes()
    {
        $resultado = [
            'assinaturas_processadas' => 0,
            'cobrancas_geradas' => 0,
            'cobrancas_ignoradas_duplicidade' => 0,
            'erros' => 0
        ];

        $assinaturas = $this->buscarAssinaturasParaRecorrencia();

        if(empty($assinaturas)){
            $this->registrarLogRecorrencia('Nenhuma assinatura ativa com próxima cobrança vencida encontrada.');
            return $resultado;
        }

        $this->db->beginTransaction();

        try{
            $cobrancaModel = new Cobranca();

            foreach($assinaturas as $assinatura){
                $resultado['assinaturas_processadas']++;
                $vencimento = $assinatura['ASS_DataProximaCobranca'] ?: date('Y-m-d');
                $tipo = 'mensalidade';

                if($this->existeCobrancaRecorrente($assinatura, $vencimento, $tipo)){
                    $resultado['cobrancas_ignoradas_duplicidade']++;
                    $this->registrarLogRecorrencia(
                        'Duplicidade ignorada para assinatura ' . $assinatura['ASS_ID'] .
                        ' no vencimento ' . $vencimento
                    );
                    continue;
                }

                $cobrancaId = $cobrancaModel->criar([
                    'cliente' => $assinatura['CLI_ID'],
                    'plano' => $assinatura['PLA_ID'],
                    'valor' => $assinatura['ASS_Valor'],
                    'vencimento' => $vencimento,
                    'tipo' => $tipo
                ]);

                $this->atualizarProximaCobrancaAssinatura($assinatura);
                $resultado['cobrancas_geradas']++;

                $this->registrarLogRecorrencia(
                    'Cobrança ' . $cobrancaId .
                    ' gerada para assinatura ' . $assinatura['ASS_ID'] .
                    ' no vencimento ' . $vencimento
                );
            }

            $this->db->commit();

            $this->registrarLogRecorrencia(
                'Geração recorrente finalizada: ' . json_encode($resultado, JSON_UNESCAPED_UNICODE)
            );

            return $resultado;
        }catch(\Exception $e){
            $this->db->rollBack();
            $resultado['erros']++;
            $this->registrarLogRecorrencia('Erro ao gerar cobranças recorrentes: ' . $e->getMessage());
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


    private function buscarAssinaturasParaRecorrencia()
    {
        $sql = $this->db->prepare("
            SELECT a.*
            FROM assinaturas a
            INNER JOIN clientes c ON c.CLI_ID = a.CLI_ID
            WHERE a.ASS_Status = 'ativa'
            AND a.ASS_DataProximaCobranca IS NOT NULL
            AND a.ASS_DataProximaCobranca <= CURDATE()
            AND a.PLA_ID IS NOT NULL
            AND c.CLI_StatusCadastro = 'ativo'
            ORDER BY a.ASS_DataProximaCobranca ASC, a.ASS_ID ASC
        ");

        $sql->execute();

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    private function existeCobrancaRecorrente($assinatura, $vencimento, $tipo)
    {
        $params = [
            $assinatura['CLI_ID'],
            $assinatura['PLA_ID'],
            $vencimento
        ];
        $filtroTipo = '';

        if($this->colunaExiste('cobrancas', 'COB_Tipo')){
            $filtroTipo = ' AND COB_Tipo = ?';
            $params[] = $tipo;
        }

        $sql = $this->db->prepare("
            SELECT COB_ID
            FROM cobrancas
            WHERE CLI_ID = ?
            AND PLA_ID = ?
            AND COB_DataVencimento = ?
            AND COB_Status <> 'cancelado'
            {$filtroTipo}
            LIMIT 1
        ");

        $sql->execute($params);

        return (bool) $sql->fetch(PDO::FETCH_ASSOC);
    }

    private function atualizarProximaCobrancaAssinatura($assinatura)
    {
        $meses = $this->mesesPorCiclo($assinatura['ASS_Ciclo']);
        $base = $assinatura['ASS_DataProximaCobranca'] ?: date('Y-m-d');
        $novaData = date('Y-m-d', strtotime('+' . $meses . ' months', strtotime($base)));

        $sql = $this->db->prepare("
            UPDATE assinaturas
            SET ASS_DataProximaCobranca = ?,
                ASS_DataAtualizacao = NOW()
            WHERE ASS_ID = ?
            AND ASS_Status = 'ativa'
        ");

        return $sql->execute([
            $novaData,
            $assinatura['ASS_ID']
        ]);
    }

    private function mesesPorCiclo($ciclo)
    {
        $mapa = [
            'mensal' => 1,
            'trimestral' => 3,
            'semestral' => 6,
            'anual' => 12
        ];

        return $mapa[$ciclo] ?? 1;
    }

    private function colunaExiste($tabela, $coluna)
    {
        static $cache = [];

        $chave = $tabela . '.' . $coluna;

        if(array_key_exists($chave, $cache)){
            return $cache[$chave];
        }

        $sql = $this->db->prepare("
            SHOW COLUMNS FROM {$tabela} LIKE ?
        ");

        $sql->execute([$coluna]);

        $cache[$chave] = (bool) $sql->fetch(PDO::FETCH_ASSOC);

        return $cache[$chave];
    }

    private function registrarLogRecorrencia($mensagem)
    {
        $this->registrarLogArquivo('financeiro_recorrencia.log', $mensagem);
    }

    private function registrarLog($mensagem)
    {
        $this->registrarLogArquivo('financeiro_vencimentos.log', $mensagem);
    }

    private function registrarLogArquivo($arquivo, $mensagem)
    {
        $diretorio = dirname(__DIR__, 2) . '/storage/logs';

        if(!is_dir($diretorio)){
            mkdir($diretorio, 0755, true);
        }

        file_put_contents(
            $diretorio . '/' . $arquivo,
            '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL,
            FILE_APPEND
        );
    }
}
