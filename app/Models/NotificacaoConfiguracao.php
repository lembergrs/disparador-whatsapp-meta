<?php

namespace Models;

use Core\Database;
use Services\CanalNotificacao;
use Services\EventoNotificacao;

class NotificacaoConfiguracao
{
    private $db;

    public function __construct($db = null){ $this->db = $db ?: Database::getInstance(); }

    public function canaisEfetivos(array $configPadrao)
    {
        $eventos = EventoNotificacao::todos();
        $canais = $this->canaisConhecidos();
        $sobrescritas = $this->sobrescritas();
        $resultado = [];
        foreach($eventos as $evento){
            $resultado[$evento] = [];
            foreach($canais as $canal){
                $padraoAtivo = in_array($canal, $configPadrao['eventos'][$evento] ?? [], true);
                $ativo = $sobrescritas[$evento][$canal] ?? $padraoAtivo;
                if($ativo && $this->canalImplementadoParaEvento($canal, $evento)) $resultado[$evento][] = $canal;
            }
        }
        return $resultado;
    }

    public function matriz(array $configPadrao)
    {
        $sobrescritas = $this->sobrescritas();
        $linhas = [];
        foreach(EventoNotificacao::todos() as $evento){
            $linha = ['evento' => $evento, 'canais' => []];
            foreach($this->canaisConhecidos() as $canal){
                $padraoAtivo = in_array($canal, $configPadrao['eventos'][$evento] ?? [], true);
                $linha['canais'][$canal] = [
                    'ativo' => (bool) ($sobrescritas[$evento][$canal] ?? $padraoAtivo),
                    'implementado' => $this->canalImplementadoParaEvento($canal, $evento),
                    'sobrescrito' => isset($sobrescritas[$evento][$canal]),
                ];
            }
            $linhas[] = $linha;
        }
        return $linhas;
    }

    public function salvarCanaisSuportados(array $emailAtivos, array $whatsappAtivos)
    {
        foreach(EventoNotificacao::todos() as $evento){
            foreach([CanalNotificacao::EMAIL=>$emailAtivos, CanalNotificacao::WHATSAPP=>$whatsappAtivos] as $canal=>$ativos){
                if(!$this->canalImplementadoParaEvento($canal, $evento)) continue;
                $ativo = in_array($evento, $ativos, true) ? 'S' : 'N';
                $sql = $this->db->prepare("INSERT INTO notificacoes_configuracoes (NOC_Evento, NOC_Canal, NOC_Ativo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE NOC_Ativo = VALUES(NOC_Ativo), NOC_AtualizadoEm = NOW()");
                $sql->execute([$evento, $canal, $ativo]);
            }
        }
    }

    public function salvarEmailPorEvento(array $ativos)
    {
        foreach(EventoNotificacao::todos() as $evento){
            $ativo = in_array($evento, $ativos, true) ? 'S' : 'N';
            $sql = $this->db->prepare("INSERT INTO notificacoes_configuracoes (NOC_Evento, NOC_Canal, NOC_Ativo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE NOC_Ativo = VALUES(NOC_Ativo), NOC_AtualizadoEm = NOW()");
            $sql->execute([$evento, CanalNotificacao::EMAIL, $ativo]);
        }
    }

    public function canaisConhecidos(){ return [CanalNotificacao::EMAIL, CanalNotificacao::WHATSAPP, CanalNotificacao::INTERNO, CanalNotificacao::PUSH, CanalNotificacao::SMS]; }
    public function canalImplementado($canal){ return in_array($canal, [CanalNotificacao::EMAIL, CanalNotificacao::WHATSAPP], true); }
    public function canalImplementadoParaEvento($canal, $evento)
    {
        if($canal === CanalNotificacao::EMAIL) return true;
        return $canal === CanalNotificacao::WHATSAPP && \Services\WhatsAppInstitucionalService::suporta($evento);
    }

    private function sobrescritas()
    {
        try{
            $rows = $this->db->query("SELECT NOC_Evento, NOC_Canal, NOC_Ativo FROM notificacoes_configuracoes")->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Throwable $e){
            return [];
        }
        $map = [];
        foreach($rows as $row){ $map[$row['NOC_Evento']][$row['NOC_Canal']] = $row['NOC_Ativo'] === 'S'; }
        return $map;
    }
}
