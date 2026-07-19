<?php

namespace Models;

use Core\Database;
use PDO;
use Services\CanalNotificacao;
use Services\EventoNotificacao;

class NotificacaoModelo
{
    private $db;
    public function __construct($db = null){ $this->db = $db ?: Database::getInstance(); }

    public function buscarAtivo($evento, $canal)
    {
        try{
            $sql = $this->db->prepare("SELECT * FROM notificacoes_modelos WHERE NOM_Evento = ? AND NOM_Canal = ? AND NOM_Ativo = 'S' LIMIT 1");
            $sql->execute([(string)$evento, (string)$canal]);
            return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
        }catch(\Throwable $e){ return null; }
    }

    public function personalizadosMapa($canal = CanalNotificacao::EMAIL)
    {
        try{
            $sql = $this->db->prepare("SELECT NOM_Evento FROM notificacoes_modelos WHERE NOM_Canal = ? AND NOM_Ativo = 'S'");
            $sql->execute([(string)$canal]);
            return array_fill_keys($sql->fetchAll(PDO::FETCH_COLUMN), true);
        }catch(\Throwable $e){ return []; }
    }

    public function salvar(array $dados)
    {
        $sql = $this->db->prepare("INSERT INTO notificacoes_modelos (NOM_Evento, NOM_Canal, NOM_Assunto, NOM_Titulo, NOM_Corpo, NOM_TextoBotao, NOM_LinkBotao, NOM_Ativo) VALUES (:evento, :canal, :assunto, :titulo, :corpo, :botao, :link, 'S') ON DUPLICATE KEY UPDATE NOM_Assunto = VALUES(NOM_Assunto), NOM_Titulo = VALUES(NOM_Titulo), NOM_Corpo = VALUES(NOM_Corpo), NOM_TextoBotao = VALUES(NOM_TextoBotao), NOM_LinkBotao = VALUES(NOM_LinkBotao), NOM_Ativo = 'S', NOM_AtualizadoEm = NOW()");
        return $sql->execute([
            ':evento'=>$dados['evento'], ':canal'=>$dados['canal'], ':assunto'=>$dados['assunto'], ':titulo'=>$dados['titulo'], ':corpo'=>$dados['corpo'], ':botao'=>$dados['botao'], ':link'=>$dados['link']
        ]);
    }

    public function restaurar($evento, $canal)
    {
        $sql = $this->db->prepare("UPDATE notificacoes_modelos SET NOM_Ativo = 'N', NOM_AtualizadoEm = NOW() WHERE NOM_Evento = ? AND NOM_Canal = ?");
        $sql->execute([(string)$evento, (string)$canal]);
        return true;
    }
}
