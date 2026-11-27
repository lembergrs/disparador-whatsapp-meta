<?php

namespace Services;

use Core\Auth;
use Core\Database;
use PDO;
use InvalidArgumentException;

class SegmentacaoDestinatariosService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Resolve o público sem modificar contatos, listas ou conversas.
     * A identidade deve vir da sessão autenticada, nunca do formulário.
     */
    public function resolver(array $usuario, int $metaId, ?int $listaId = null, array $etiquetas = [], string $operador = 'OR'): array
    {
        $clienteId = (int) ($usuario['CLI_ID'] ?? $usuario['cliente_id'] ?? 0);
        if ($clienteId <= 0 || $metaId <= 0 || !in_array($metaId, Auth::idsContasMetaPermitidas($usuario), true)) {
            throw new InvalidArgumentException('Conta Meta não permitida.');
        }
        $responsavel = null;
        if (($usuario['nivel'] ?? '') === 'cliente_usuario') {
            $responsavel = (int) ($usuario['id'] ?? 0);
            if ($responsavel <= 0) throw new InvalidArgumentException('Usuário inválido.');
        }
        return $this->resolverEscopo($clienteId, $metaId, $listaId, $etiquetas, $operador, $responsavel);
    }

    public static function assinatura(array $numeros): string
    {
        $numeros = array_values(array_unique(array_map('strval', $numeros)));
        sort($numeros, SORT_STRING);
        return hash('sha256', json_encode($numeros, JSON_THROW_ON_ERROR));
    }

    private function consultar(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function resolverEscopo(int $clienteId, int $metaId, ?int $listaId, array $etiquetas, string $operador, ?int $responsavel): array
    {
        if (!in_array($operador, ['OR', 'AND'], true) || ($listaId !== null && $listaId <= 0)) {
            throw new InvalidArgumentException('Critérios de segmentação inválidos.');
        }
        foreach ($etiquetas as $id) {
            if ((!is_int($id) && (!is_string($id) || !ctype_digit($id))) || (int) $id <= 0) {
                throw new InvalidArgumentException('Etiqueta inválida.');
            }
        }
        $etiquetas = array_values(array_unique(array_map('intval', $etiquetas)));
        if ($listaId === null && !$etiquetas) {
            throw new InvalidArgumentException('Selecione uma lista ou etiqueta.');
        }

        $lista = null;
        if ($listaId !== null) {
            if (!$this->consultar("SELECT LST_ID FROM listas_contatos WHERE LST_ID = ? AND CLI_ID = ? AND LST_Ativo = 'S'", [$listaId, $clienteId])) {
                throw new InvalidArgumentException('Lista não disponível.');
            }
            $lista = $this->consultar("SELECT c.* FROM contatos c INNER JOIN lista_contatos_itens i ON i.CON_ID = c.CON_ID INNER JOIN listas_contatos l ON l.LST_ID = i.LST_ID AND l.CLI_ID = c.CLI_ID WHERE l.LST_ID = ? AND c.CLI_ID = ? AND l.LST_Ativo = 'S' AND c.CON_Ativo = 'S' ORDER BY c.CON_ID", [$listaId, $clienteId]);
        }

        $conversas = [];
        $contatos = [];
        if ($etiquetas) {
            $in = implode(',', array_fill(0, count($etiquetas), '?'));
            $validas = $this->consultar("SELECT ETQ_ID FROM conversa_etiquetas WHERE CLI_ID = ? AND ETQ_Ativo = 'S' AND ETQ_ID IN ($in)", array_merge([$clienteId], $etiquetas));
            if (count($validas) !== count($etiquetas)) {
                throw new InvalidArgumentException('Etiqueta não disponível.');
            }
            $filtroResponsavel = $responsavel === null ? "" : " AND c.CON_Responsavel_USU_ID = ?";
            $paramsConversas = array_merge([$clienteId, $metaId], $etiquetas, $responsavel === null ? [] : [$responsavel]);
            $conversas = $this->consultar("SELECT c.CVS_ID, c.CVS_Numero, c.CVS_Nome, e.ETQ_ID FROM conversas c INNER JOIN conversa_etiqueta_vinculos v ON v.CVS_ID = c.CVS_ID INNER JOIN conversa_etiquetas e ON e.ETQ_ID = v.ETQ_ID AND e.CLI_ID = c.CLI_ID WHERE c.CLI_ID = ? AND c.MTA_ID = ? AND c.CVS_Ativo = 'S' AND e.ETQ_Ativo = 'S' AND e.ETQ_ID IN ($in) $filtroResponsavel ORDER BY c.CVS_ID, e.ETQ_ID", $paramsConversas);
            if ($lista === null) {
                $contatos = $this->consultar("SELECT * FROM contatos WHERE CLI_ID = ? ORDER BY CON_ID", [$clienteId]);
            }
        }
        return self::consolidar($lista, $conversas, $contatos, $etiquetas, $operador);
    }

    private static function numero($valor): string
    {
        $numero = TelefoneService::normalizar($valor);
        return preg_match('/^[1-9][0-9]{7,14}$/D', $numero) ? $numero : '';
    }

    /**
     * Consolidação pura de registros previamente autorizados.
     * CON_ID nulo indica conversa sem cadastro correspondente.
     */
    public static function consolidar(?array $lista, array $conversas, array $contatos, array $etiquetas, string $operador): array
    {
        $porNumero = [];
        $bloqueados = [];
        foreach ($lista ?? $contatos as $contato) {
            $numero = self::numero($contato['CON_Telefone'] ?? '');
            if ($numero === '') continue;
            if (($contato['CON_Ativo'] ?? '') !== 'S') {
                $bloqueados[$numero] = true;
                continue;
            }
            if (!isset($porNumero[$numero])) {
                $porNumero[$numero] = [
                    'numero' => $numero,
                    'contato_id' => (int) $contato['CON_ID'],
                    'nome' => $contato['CON_Nome'] ?? '',
                    'dados_json' => $contato['CON_DadosJson'] ?? null,
                ];
            }
        }

        $classificados = [];
        foreach ($conversas as $conversa) {
            $numero = self::numero($conversa['CVS_Numero'] ?? '');
            $id = (int) $conversa['ETQ_ID'];
            if ($numero === '' || !in_array($id, $etiquetas, true)) continue;
            $classificados[$numero]['etiquetas'][$id] = true;
            $classificados[$numero]['nome'] = $conversa['CVS_Nome'] ?? '';
        }

        $resultado = [];
        $numeros = $lista !== null ? array_keys($porNumero) : array_keys($classificados);
        foreach ($numeros as $numero) {
            if (isset($bloqueados[$numero])) continue;
            $numero = (string) $numero;
            $tags = $classificados[$numero]['etiquetas'] ?? [];
            if ($etiquetas && (!$tags || ($operador === 'AND' && count($tags) !== count($etiquetas)))) continue;
            $resultado[] = $porNumero[$numero] ?? [
                'numero' => $numero,
                'contato_id' => null,
                'nome' => $classificados[$numero]['nome'] ?? '',
                'dados_json' => null,
            ];
        }
        return $resultado;
    }
}
