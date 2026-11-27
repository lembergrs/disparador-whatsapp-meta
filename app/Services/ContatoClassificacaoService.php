<?php
namespace Services;

use Core\Auth;
use Core\Database;
use Models\Conversa;
use PDO;

class ContatoClassificacaoService
{
    public function listasDaConversa(array $usuario, int $id): array
    {
        $cliente = (int) ($usuario['CLI_ID'] ?? $usuario['cliente_id'] ?? 0);
        $conversa = (new Conversa())->buscarAcessivel($id, $cliente, $usuario);
        if (!$conversa || ($conversa['CVS_Ativo'] ?? '') !== 'S') return [];
        $variantes = TelefoneService::variantes($conversa['CVS_Numero']);
        if (!$variantes) return [];
        $in = implode(',', array_fill(0, count($variantes), '?'));
        $stmt = Database::getInstance()->prepare("SELECT DISTINCT l.LST_ID, l.LST_Nome
            FROM listas_contatos l JOIN lista_contatos_itens i ON i.LST_ID=l.LST_ID
            JOIN contatos c ON c.CON_ID=i.CON_ID AND c.CLI_ID=l.CLI_ID
            WHERE l.CLI_ID=? AND l.LST_Ativo='S' AND c.CON_Ativo='S'
            AND (c.CON_TelefoneNormalizado IN ($in) OR c.CON_Telefone IN ($in))
            ORDER BY l.LST_Nome, l.LST_ID");
        $stmt->execute(array_merge([$cliente], $variantes, $variantes));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function etiquetasDoContato(array $usuario, int $lista, int $contato): array
    {
        $cliente = (int) ($usuario['CLI_ID'] ?? $usuario['cliente_id'] ?? 0);
        $metas = Auth::idsContasMetaPermitidas($usuario);
        if ($cliente <= 0 || !$metas) return [];
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT c.CON_Telefone FROM contatos c
            JOIN lista_contatos_itens i ON i.CON_ID=c.CON_ID
            JOIN listas_contatos l ON l.LST_ID=i.LST_ID AND l.CLI_ID=c.CLI_ID
            WHERE c.CLI_ID=? AND c.CON_ID=? AND l.LST_ID=? AND c.CON_Ativo='S' AND l.LST_Ativo='S'");
        $stmt->execute([$cliente, $contato, $lista]);
        $numero = $stmt->fetchColumn();
        if (!$numero) return [];
        $variantes = TelefoneService::variantes($numero);
        if (!$variantes) return [];
        $in = implode(',', array_fill(0, count($variantes), '?'));
        $inMeta = implode(',', array_fill(0, count($metas), '?'));
        $where = '';
        $params = array_merge([$cliente], $metas, $variantes, $variantes);
        if (($usuario['nivel'] ?? '') === 'cliente_usuario') {
            $where = ' AND c.CON_Responsavel_USU_ID=?';
            $params[] = (int) ($usuario['id'] ?? 0);
        }
        $stmt = $db->prepare("SELECT DISTINCT m.MTA_ID, m.MTA_Nome, e.ETQ_ID, e.ETQ_Nome
            FROM conversas c JOIN conversa_etiqueta_vinculos v ON v.CVS_ID=c.CVS_ID
            JOIN conversa_etiquetas e ON e.ETQ_ID=v.ETQ_ID AND e.CLI_ID=c.CLI_ID
            JOIN meta_contas m ON m.MTA_ID=c.MTA_ID AND m.CLI_ID=c.CLI_ID
            WHERE c.CLI_ID=? AND c.MTA_ID IN ($inMeta) AND c.CVS_Ativo='S' AND e.ETQ_Ativo='S'
            AND m.MTA_Ativo='S' AND (c.CVS_NumeroNormalizado IN ($in) OR c.CVS_Numero IN ($in)) $where
            ORDER BY m.MTA_Nome, m.MTA_ID, e.ETQ_Nome, e.ETQ_ID");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
