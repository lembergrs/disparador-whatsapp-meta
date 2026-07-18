<?php

namespace Services;

use Core\Database;

class OnboardingChecklistService
{
    public function calcular($clienteId)
    {
        $db = Database::getInstance();

        $checks = [
            [
                'label' => 'Cadastro concluído',
                'url' => 'dashboard',
                'done' => true
            ],
            [
                'label' => 'Conta Meta conectada',
                'url' => 'configuracao/meta',
                'done' => $this->existe(
                    $db,
                    'meta_contas',
                    "CLI_ID = ? AND MTA_Ativo = 'S'",
                    [$clienteId]
                )
            ],
            [
                'label' => 'Número registrado',
                'url' => 'configuracao/meta',
                'done' => $this->existe(
                    $db,
                    'meta_contas',
                    "CLI_ID = ? AND MTA_Status = 'conectado' AND MTA_Ativo = 'S'",
                    [$clienteId]
                )
            ],
            [
                'label' => 'Criar primeiro template',
                'url' => 'template',
                'done' => $this->existeTemplateDoCliente($db, $clienteId)
            ],
            [
                'label' => 'Importar contatos',
                'url' => 'importacao',
                'done' => $this->existe(
                    $db,
                    'contatos',
                    "CLI_ID = ? AND CON_Ativo = 'S'",
                    [$clienteId]
                )
            ],
            [
                'label' => 'Criar primeira lista',
                'url' => 'listaContato',
                'done' => $this->existe(
                    $db,
                    'listas_contatos',
                    'CLI_ID = ?',
                    [$clienteId]
                )
            ],
            [
                'label' => 'Fazer primeiro disparo',
                'url' => 'disparo',
                'done' => $this->existe(
                    $db,
                    'disparos',
                    'CLI_ID = ?',
                    [$clienteId]
                )
            ],
            [
                'label' => 'Criar primeira campanha',
                'url' => 'campanha',
                'done' => $this->existe(
                    $db,
                    'campanhas',
                    'CLI_ID = ?',
                    [$clienteId]
                )
            ],
        ];

        $done = count(array_filter($checks, function ($item) {
            return $item['done'];
        }));

        $total = count($checks);

        return [
            'itens' => $checks,
            'total' => $total,
            'concluidos' => $done,
            'percentual' => $total > 0
                ? (int) round(($done / $total) * 100)
                : 0,
            'concluido' => $done === $total
        ];
    }

    private function existeTemplateDoCliente($db, $clienteId)
    {
        $sql = $db->prepare("
            SELECT 1
            FROM templates_meta t
            INNER JOIN meta_contas m
                ON m.MTA_ID = t.MTA_ID
            WHERE m.CLI_ID = ?
              AND t.TMP_Ativo = 'S'
              AND m.MTA_Ativo = 'S'
            LIMIT 1
        ");

        $sql->execute([$clienteId]);

        return (bool) $sql->fetchColumn();
    }

    private function existe($db, $tabela, $where, array $params)
    {
        $sql = $db->prepare("
            SELECT 1
            FROM {$tabela}
            WHERE {$where}
            LIMIT 1
        ");

        $sql->execute($params);

        return (bool) $sql->fetchColumn();
    }
}