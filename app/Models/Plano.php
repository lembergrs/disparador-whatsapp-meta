<?php

namespace Models;

use Core\Database;
use PDO;

class Plano
{
    private $db;

    public const CICLOS = [
        'mensal' => 1,
        'trimestral' => 3,
        'semestral' => 6,
        'anual' => 12
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listarAtivos()
    {
        $sql = $this->db->query("
            SELECT *
            FROM planos
            WHERE PLA_Ativo = 'S'
            ORDER BY COALESCE(PLA_ValorMensal, PLA_Valor) ASC
        ");

        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM planos
            WHERE PLA_ID = ?
            AND PLA_Ativo = 'S'
        ");

        $sql->execute([$id]);

        return $sql->fetch(PDO::FETCH_ASSOC);
    }

    public function salvar($dados)
    {
        $valores = $this->normalizarValoresCiclo($dados);

        $sql = $this->db->prepare("
            INSERT INTO planos
            (
                PLA_Nome,
                PLA_Periodicidade,
                PLA_Valor,
                PLA_ValorMensal,
                PLA_ValorTrimestral,
                PLA_ValorSemestral,
                PLA_ValorAnual,
                PLA_LimiteNumeros,
                PLA_LimiteUsuarios,
                PLA_LimiteMensagens,
                PLA_ValorMensagemExcedente,
                PLA_Cor
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        return $sql->execute([
            $dados['nome'],
            $dados['periodicidade'] ?? 'mensal',
            $valores['mensal'],
            $valores['mensal'],
            $valores['trimestral'],
            $valores['semestral'],
            $valores['anual'],
            $dados['numeros'],
            $dados['usuarios'],
            $dados['mensagens'],
            $this->normalizarValor($dados['excedente']),
            $dados['cor']
        ]);
    }

    public function editar($id, $dados)
    {
        $valores = $this->normalizarValoresCiclo($dados);

        $sql = $this->db->prepare("
            UPDATE planos
            SET
                PLA_Nome = ?,
                PLA_Periodicidade = ?,
                PLA_Valor = ?,
                PLA_ValorMensal = ?,
                PLA_ValorTrimestral = ?,
                PLA_ValorSemestral = ?,
                PLA_ValorAnual = ?,
                PLA_LimiteNumeros = ?,
                PLA_LimiteUsuarios = ?,
                PLA_LimiteMensagens = ?,
                PLA_ValorMensagemExcedente = ?,
                PLA_Cor = ?
            WHERE PLA_ID = ?
        ");

        return $sql->execute([
            $dados['nome'],
            $dados['periodicidade'] ?? 'mensal',
            $valores['mensal'],
            $valores['mensal'],
            $valores['trimestral'],
            $valores['semestral'],
            $valores['anual'],
            $dados['numeros'],
            $dados['usuarios'],
            $dados['mensagens'],
            $this->normalizarValor($dados['excedente']),
            $dados['cor'],
            $id
        ]);
    }

    public function inativar($id)
    {
        $sql = $this->db->prepare("
            UPDATE planos
            SET PLA_Ativo = 'N'
            WHERE PLA_ID = ?
        ");

        return $sql->execute([$id]);
    }

    public static function cicloValido($ciclo)
    {
        return array_key_exists($ciclo, self::CICLOS);
    }

    public static function mesesPorCiclo($ciclo)
    {
        return self::CICLOS[$ciclo] ?? self::CICLOS['mensal'];
    }

    public static function valorPorCiclo(array $plano, $ciclo)
    {
        $mensal = self::normalizarValorEstatico(
            $plano['PLA_ValorMensal']
            ?? $plano['PLA_Valor']
            ?? 0
        );

        if($mensal <= 0){
            $mensal = self::normalizarValorEstatico($plano['PLA_Valor'] ?? 0);
        }

        $campo = [
            'mensal' => 'PLA_ValorMensal',
            'trimestral' => 'PLA_ValorTrimestral',
            'semestral' => 'PLA_ValorSemestral',
            'anual' => 'PLA_ValorAnual'
        ][$ciclo] ?? 'PLA_ValorMensal';

        $valor = self::normalizarValorEstatico($plano[$campo] ?? null);

        if($valor > 0){
            return $valor;
        }

        return $mensal * self::mesesPorCiclo($ciclo);
    }

    private function normalizarValoresCiclo($dados)
    {
        $mensal = $this->normalizarValor(
            $dados['valor_mensal']
            ?? $dados['valor']
            ?? 0
        );

        return [
            'mensal' => $mensal,
            'trimestral' => $this->normalizarValorOpcional(
                $dados['valor_trimestral'] ?? null,
                $mensal * 3
            ),
            'semestral' => $this->normalizarValorOpcional(
                $dados['valor_semestral'] ?? null,
                $mensal * 6
            ),
            'anual' => $this->normalizarValorOpcional(
                $dados['valor_anual'] ?? null,
                $mensal * 12
            )
        ];
    }

    private function normalizarValorOpcional($valor, $padrao)
    {
        if($valor === null || trim((string) $valor) === ''){
            return $padrao;
        }

        return $this->normalizarValor($valor);
    }

    private function normalizarValor($valor)
    {
        return self::normalizarValorEstatico($valor);
    }

    private static function normalizarValorEstatico($valor)
    {
        if($valor === null || $valor === ''){
            return 0.0;
        }

        if(is_numeric($valor)){
            return (float) $valor;
        }

        $valor = str_replace('.', '', (string) $valor);
        $valor = str_replace(',', '.', $valor);

        return (float) $valor;
    }
}
