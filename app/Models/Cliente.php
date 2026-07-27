<?php

namespace Models;

use Services\AnalyticsService;

use Core\Database;
use PDO;

class Cliente
{
    public const ORIGENS_CADASTRO = [
        'indicacao' => 'Indicação de amigo ou conhecido',
        'google' => 'Google',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'youtube' => 'YouTube',
        'anuncio_internet' => 'Anúncio na internet',
        'evento' => 'Evento, feira ou palestra',
        'cliente_parceiro' => 'Cliente ou parceiro comercial',
        'contato_equipe' => 'Contato da equipe do Disparador.net',
        'whatsapp' => 'WhatsApp',
        'outro' => 'Outro'
    ];

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public static function validarOrigemCadastro($origem, $outro = '')
    {
        $origem = trim((string) $origem);
        $outro = trim(strip_tags((string) $outro));

        if(!array_key_exists($origem, self::ORIGENS_CADASTRO)){
            throw new \InvalidArgumentException('Selecione como você conheceu o Disparador.net.');
        }

        if($origem === 'outro'){
            if($outro === ''){
                throw new \InvalidArgumentException('Conte como você conheceu o Disparador.net.');
            }
            if(mb_strlen($outro) > 150){
                throw new \InvalidArgumentException('A descrição da origem deve possuir no máximo 150 caracteres.');
            }
        }else{
            $outro = null;
        }

        return ['origem' => $origem, 'outro' => $outro];
    }

    public static function formatarOrigemCadastro($origem, $outro = null)
    {
        if(empty($origem) || !isset(self::ORIGENS_CADASTRO[$origem])){
            return 'Não informado';
        }

        return $origem === 'outro' && trim((string) $outro) !== ''
            ? 'Outro — ' . trim((string) $outro)
            : self::ORIGENS_CADASTRO[$origem];
    }





    public function listar($status = null)
    {
        if($status){

            $sql = $this->db->prepare("
                SELECT c.*,
                    a.ASS_Ciclo, a.ASS_Status, a.ASS_Valor, a.ASS_DataProximaCobranca,
                    p.PLA_Nome AS ASS_PlanoNome
                FROM clientes c
                LEFT JOIN assinaturas a
                    ON a.ASS_ID = (
                        SELECT ax.ASS_ID
                        FROM assinaturas ax
                        WHERE ax.CLI_ID = c.CLI_ID
                        AND ax.ASS_Status IN ('ativa','pendente','vencida')
                        ORDER BY FIELD(ax.ASS_Status, 'ativa','pendente','vencida'), ax.ASS_ID DESC
                        LIMIT 1
                    )
                LEFT JOIN planos p ON p.PLA_ID = a.PLA_ID
                WHERE c.CLI_StatusCadastro = :status
                ORDER BY c.CLI_ID DESC
            ");

            $sql->execute([
                ':status' => $status
            ]);

        }else{

            $sql = $this->db->query("
                SELECT c.*,
                    a.ASS_Ciclo, a.ASS_Status, a.ASS_Valor, a.ASS_DataProximaCobranca,
                    p.PLA_Nome AS ASS_PlanoNome
                FROM clientes c
                LEFT JOIN assinaturas a
                    ON a.ASS_ID = (
                        SELECT ax.ASS_ID
                        FROM assinaturas ax
                        WHERE ax.CLI_ID = c.CLI_ID
                        AND ax.ASS_Status IN ('ativa','pendente','vencida')
                        ORDER BY FIELD(ax.ASS_Status, 'ativa','pendente','vencida'), ax.ASS_ID DESC
                        LIMIT 1
                    )
                LEFT JOIN planos p ON p.PLA_ID = a.PLA_ID
                WHERE c.CLI_StatusCadastro IN ('pendente','ativo')
                ORDER BY c.CLI_ID DESC
            ");

        }

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }





    public function buscar($id)
    {
        $sql = $this->db->prepare("

            SELECT *

            FROM clientes

            WHERE CLI_ID = ?

        ");

        $sql->execute([$id]);

        return $sql->fetch(
            PDO::FETCH_ASSOC
        );
    }





    public function salvar($dados)
    {
        $sql = $this->db->prepare("

            INSERT INTO clientes
            (

                CLI_TipoPessoa,
                CLI_CPF_CNPJ,
                CLI_Nome,
                CLI_RazaoSocial,
                CLI_Email,
                CLI_Telefone,
                CLI_ValorMensalidade,
                CLI_Vencimento,
                CLI_StatusPagamento,
                CLI_Observacoes,
                CLI_Ativo

            )

            VALUES
            (

                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'S'

            )

        ");





        $mensalidade =
            str_replace(
                ',',
                '.',
                str_replace(
                    '.',
                    '',
                    $dados['mensalidade']
                )
            );





        $sql->execute([

            $dados['tipo_pessoa'],

            preg_replace(
                '/\D/',
                '',
                $dados['cpf_cnpj']
            ),

            $dados['nome'],

            $dados['razao_social']
            ?? null,

            $dados['email'],

            preg_replace(
                '/\D/',
                '',
                $dados['telefone']
            ),

            $mensalidade,

            $dados['vencimento']
            ?: null,

            $dados['status'],

            $dados['observacoes']
            ?? null

        ]);





        $clienteId = $this->db->lastInsertId();
        $this->atualizarCamposFiscaisNfse($clienteId, $dados);

        return $clienteId;
    }





    public function atualizar($id, $dados)
    {
        $sql = $this->db->prepare("

            UPDATE clientes SET

                CLI_TipoPessoa = ?,
                CLI_CPF_CNPJ = ?,
                CLI_Nome = ?,
                CLI_RazaoSocial = ?,
                CLI_Email = ?,
                CLI_Telefone = ?,
                CLI_ValorMensalidade = ?,
                CLI_Vencimento = ?,
                CLI_StatusPagamento = ?,
                CLI_Observacoes = ?

            WHERE CLI_ID = ?

        ");





        $mensalidade =
            str_replace(
                ',',
                '.',
                str_replace(
                    '.',
                    '',
                    $dados['mensalidade']
                )
            );





        $atualizado = $sql->execute([

            $dados['tipo_pessoa'],

            preg_replace(
                '/\D/',
                '',
                $dados['cpf_cnpj']
            ),

            $dados['nome'],

            $dados['razao_social']
            ?? null,

            $dados['email'],

            preg_replace(
                '/\D/',
                '',
                $dados['telefone']
            ),

            $mensalidade,

            $dados['vencimento']
            ?: null,

            $dados['status'],

            $dados['observacoes']
            ?? null,

            $id

        ]);

        $this->atualizarCamposFiscaisNfse($id, $dados);

        return $atualizado;
    }


    public function atualizarDadosConta($id, array $dados)
    {
        $sets = [
            'CLI_Nome = :nome',
            'CLI_Telefone = :telefone'
        ];

        $params = [
            ':id' => $id,
            ':nome' => $dados['nome'],
            ':telefone' => preg_replace('/\D/', '', (string) ($dados['telefone'] ?? ''))
        ];

        if($this->colunaExiste('clientes', 'CLI_NomeFantasia')){
            $sets[] = 'CLI_NomeFantasia = :nome_fantasia';
            $params[':nome_fantasia'] = $dados['nome_fantasia'] ?? null;
        }

        $camposEndereco = [
            'CLI_CEP' => 'cep',
            'CLI_Logradouro' => 'logradouro',
            'CLI_Numero' => 'numero',
            'CLI_Complemento' => 'complemento',
            'CLI_Bairro' => 'bairro',
            'CLI_Cidade' => 'cidade',
            'CLI_UF' => 'uf'
        ];

        foreach($camposEndereco as $coluna => $chave){
            if($this->colunaExiste('clientes', $coluna) && array_key_exists($chave, $dados)){
                $param = ':' . $chave;
                $sets[] = $coluna . ' = ' . $param;
                $params[$param] = $chave === 'cep'
                    ? preg_replace('/\D/', '', (string) $dados[$chave])
                    : trim((string) $dados[$chave]);
            }
        }

        $sql = $this->db->prepare("
            UPDATE clientes
            SET " . implode(', ', $sets) . "
            WHERE CLI_ID = :id
        ");

        return $sql->execute($params);
    }

    public function colunasExistem(array $colunas)
    {
        $resultado = [];

        foreach($colunas as $coluna){
            $resultado[$coluna] = $this->colunaExiste('clientes', $coluna);
        }

        return $resultado;
    }






    private function camposFiscaisNfse(array $dados)
    {
        $mapa = [
            'CLI_NFSe_CNPJ' => ['cnpj_fiscal', true],
            'CLI_NFSe_RazaoSocial' => ['razao_social_fiscal', false],
            'CLI_NFSe_CEP' => ['cep_fiscal', true],
            'CLI_NFSe_Logradouro' => ['logradouro_fiscal', false],
            'CLI_NFSe_Numero' => ['numero_fiscal', false],
            'CLI_NFSe_Complemento' => ['complemento_fiscal', false],
            'CLI_NFSe_Bairro' => ['bairro_fiscal', false],
            'CLI_NFSe_Municipio' => ['municipio_fiscal', false],
            'CLI_NFSe_UF' => ['uf_fiscal', false],
            'CLI_NFSe_CodigoIBGE' => ['codigo_ibge_fiscal', true],
            'CLI_NFSe_Telefone' => ['telefone_fiscal', true],
            'CLI_NFSe_Email' => ['email_fiscal', false]
        ];

        $campos = [];

        foreach($mapa as $coluna => [$chave, $numerico]){
            if(!$this->colunaExiste('clientes', $coluna) || !array_key_exists($chave, $dados)){
                continue;
            }

            $valor = (string) ($dados[$chave] ?? '');
            $campos[$coluna] = $numerico
                ? preg_replace('/\D/', '', $valor)
                : trim($valor);
        }

        if(isset($campos['CLI_NFSe_UF'])){
            $campos['CLI_NFSe_UF'] = strtoupper(substr($campos['CLI_NFSe_UF'], 0, 2));
        }

        return $campos;
    }

    private function atualizarCamposFiscaisNfse($id, array $dados)
    {
        $campos = $this->camposFiscaisNfse($dados);

        if(empty($campos)){
            return true;
        }

        $sets = [];
        $params = [':id' => $id];

        foreach($campos as $coluna => $valor){
            $param = ':' . strtolower($coluna);
            $sets[] = $coluna . ' = ' . $param;
            $params[$param] = $valor !== '' ? $valor : null;
        }

        $sql = $this->db->prepare("\n            UPDATE clientes\n            SET " . implode(', ', $sets) . "\n            WHERE CLI_ID = :id\n        ");

        return $sql->execute($params);
    }

    public function iniciarTrialSePendente($id)
    {
        $sql = $this->db->prepare("
            UPDATE clientes
            SET CLI_DataLiberacao = NOW()
            WHERE CLI_ID = ?
            AND CLI_StatusCadastro = 'ativo'
            AND CLI_StatusPagamento = 'pendente'
            AND (CLI_DataLiberacao IS NULL OR CLI_DataLiberacao = '')
        ");

        $executado = $sql->execute([$id]);

        if($executado && $sql->rowCount() > 0){
            $this->registrarInicioTrial($id);
            AnalyticsService::registrar('trial_started');
        }

        return $executado;
    }




    private function registrarInicioTrial($id)
    {
        $linha = [
            'timestamp' => date('c'),
            'evento' => 'trial_iniciado',
            'CLI_ID' => (int) $id
        ];

        $diretorio = __DIR__ . '/../../storage/logs';

        if(!is_dir($diretorio)){
            @mkdir($diretorio, 0775, true);
        }

        @file_put_contents(
            $diretorio . '/trial.log',
            json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }




    public function inativar($id)
    {
        $sql = $this->db->prepare("

            UPDATE clientes

            SET CLI_Ativo = 'N'

            WHERE CLI_ID = ?

        ");

        return $sql->execute([$id]);
    }

    public function buscarComPlano($id)
    {
        $sql = $this->db->prepare("
            SELECT
                c.*,
                p.PLA_ID,
                p.PLA_Nome,
                p.PLA_Valor,
                p.PLA_Periodicidade,
                p.PLA_LimiteNumeros,
                p.PLA_LimiteUsuarios,
                p.PLA_LimiteMensagens,
                p.PLA_ValorMensagemExcedente
            FROM clientes c
            LEFT JOIN planos p
                ON p.PLA_ID = c.CLI_Plano_DR
            WHERE c.CLI_ID = ?
        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function listarFinanceiro()
    {
        $sql = $this->db->query("
            SELECT
                c.*,
                p.PLA_Nome,
                p.PLA_LimiteMensagens,
                cm.CMS_Mensagens,
                ex.EXC_Mensagens,
                ex.EXC_ValorTotal
            FROM clientes c
            LEFT JOIN planos p
                ON p.PLA_ID = c.CLI_Plano_DR
            LEFT JOIN consumo_mensal cm
                ON cm.CLI_ID = c.CLI_ID
                AND cm.CMS_AnoMes = DATE_FORMAT(NOW(), '%Y%m')
            LEFT JOIN excedentes_mensais ex
                ON ex.CLI_ID = c.CLI_ID
                AND ex.EXC_AnoMes = DATE_FORMAT(NOW(), '%Y%m')
            ORDER BY c.CLI_ID DESC
        ");

        return $sql->fetchAll(
            PDO::FETCH_ASSOC
        );
    }




    public function atualizarProviderPagamento($id, $provider, $providerCustomerId)
    {
        $sets = [];
        $params = [':id' => $id];

        if($this->colunaExiste('clientes', 'CLI_ProviderPagamento')){
            $sets[] = 'CLI_ProviderPagamento = :provider';
            $params[':provider'] = $provider;
        }

        if($this->colunaExiste('clientes', 'CLI_ProviderCustomerId')){
            $sets[] = 'CLI_ProviderCustomerId = :customer_id';
            $params[':customer_id'] = $providerCustomerId;
        }

        if($this->colunaExiste('clientes', 'CLI_DataSincronizacaoProvider')){
            $sets[] = 'CLI_DataSincronizacaoProvider = NOW()';
        }

        if(empty($sets)){
            return false;
        }

        $sql = $this->db->prepare("
            UPDATE clientes
            SET " . implode(', ', $sets) . "
            WHERE CLI_ID = :id
        ");

        return $sql->execute($params);
    }

    public function marcarPagamentoProviderConfirmado($id)
    {
        $sql = $this->db->prepare("
            UPDATE clientes
            SET CLI_StatusPagamento = 'pago'
            WHERE CLI_ID = ?
        ");

        return $sql->execute([$id]);
    }

    public function atualizarEstadoFinanceiro($id, array $dados)
    {
        $permitidos = [
            'CLI_StatusPagamento' => 'status_pagamento',
            'CLI_StatusCadastro' => 'status_cadastro',
            'CLI_Ativo' => 'ativo',
            'CLI_Plano_DR' => 'plano',
            'CLI_DataLiberacao' => 'data_liberacao'
        ];
        $sets = [];
        $params = [':id' => $id];

        foreach($permitidos as $coluna => $chave){
            if(array_key_exists($chave, $dados)){
                $param = ':' . $chave;
                $sets[] = $coluna . ' = ' . $param;
                $params[$param] = $dados[$chave];
            }
        }

        if(!empty($dados['liberar_se_vazio'])){
            $sets[] = 'CLI_DataLiberacao = COALESCE(CLI_DataLiberacao, NOW())';
        }

        if(empty($sets)){
            return false;
        }

        $sql = $this->db->prepare("UPDATE clientes SET " . implode(', ', $sets) . " WHERE CLI_ID = :id");
        return $sql->execute($params);
    }

    public function atualizarAtivacaoComUsuarios($id, $ativo, $statusCadastro)
    {
        $iniciadaAqui = !$this->db->inTransaction();
        if($iniciadaAqui){ $this->db->beginTransaction(); }
        try{
            $this->db->prepare("UPDATE clientes SET CLI_Ativo = ?, CLI_StatusCadastro = ? WHERE CLI_ID = ?")
                ->execute([$ativo, $statusCadastro, $id]);
            $resultado = $this->db->prepare("UPDATE usuarios SET USU_Ativo = ? WHERE CLI_ID = ?")
                ->execute([$ativo, $id]);
            if($iniciadaAqui){ $this->db->commit(); }
            return $resultado;
        }catch(\Throwable $e){
            if($iniciadaAqui && $this->db->inTransaction()){ $this->db->rollBack(); }
            throw $e;
        }
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

}
