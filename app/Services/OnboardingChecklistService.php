<?php

namespace Services;

use Core\Database;

class OnboardingChecklistService
{
    public function calcular($clienteId)
    {
        $db = Database::getInstance();
        $checks = [
            ['label'=>'Cadastro concluído','url'=>'dashboard','done'=>true],
            ['label'=>'Conta Meta conectada','url'=>'configuracao/meta','done'=>$this->existe($db, 'meta_contas', 'CLI_ID = ? AND MTA_Ativo = \'S\'', [$clienteId])],
            ['label'=>'Número registrado','url'=>'configuracao/meta','done'=>$this->existe($db, 'meta_contas', 'CLI_ID = ? AND MTA_Status = \'conectado\' AND MTA_Ativo = \'S\'', [$clienteId])],
            ['label'=>'Criar primeiro template','url'=>'template','done'=>$this->existe($db, 'templates_meta', 'CLI_ID = ? AND TMP_Ativo = \'S\'', [$clienteId])],
            ['label'=>'Importar contatos','url'=>'importacao','done'=>$this->existe($db, 'contatos', 'CLI_ID = ? AND CON_Ativo = \'S\'', [$clienteId])],
            ['label'=>'Criar primeira lista','url'=>'listaContato','done'=>$this->existe($db, 'listas_contatos', 'CLI_ID = ?', [$clienteId])],
            ['label'=>'Fazer primeiro disparo','url'=>'disparo','done'=>$this->existe($db, 'disparos', 'CLI_ID = ?', [$clienteId])],
            ['label'=>'Criar primeira campanha','url'=>'campanha','done'=>$this->existe($db, 'campanhas', 'CLI_ID = ?', [$clienteId])],
        ];
        $done = count(array_filter($checks, function($i){ return $i['done']; }));
        return ['itens'=>$checks, 'total'=>count($checks), 'concluidos'=>$done, 'percentual'=>(int) round(($done / count($checks)) * 100), 'concluido'=>$done === count($checks)];
    }

    private function existe($db, $tabela, $where, array $params)
    {
        $sql = $db->prepare("SELECT 1 FROM {$tabela} WHERE {$where} LIMIT 1");
        $sql->execute($params);
        return (bool) $sql->fetchColumn();
    }
}
