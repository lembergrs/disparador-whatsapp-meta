<?php

namespace Services;

use Core\Database;

class MetaSharedRateLimiterService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function aguardarSlot(int $metaId): void
    {
        $enviosPorSegundo = max(1, (int) WHATSAPP_ENVIOS_POR_SEGUNDO);
        $intervaloMicrossegundos = (int) ceil(1000000 / $enviosPorSegundo);
        $lock = $this->nomeLock($metaId);

        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 5)');
        $stmt->execute([$lock]);

        if((int) $stmt->fetchColumn() !== 1){
            throw new \RuntimeException('Não foi possível reservar a janela compartilhada de envio para a Meta.');
        }

        try{
            usleep($intervaloMicrossegundos);
        }finally{
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lock]);
        }
    }

    private function nomeLock(int $metaId): string
    {
        $dbName = defined('DB_NAME') ? DB_NAME : 'disparador';
        $ambiente = defined('APP_ENV') ? APP_ENV : 'production';

        return 'meta_rate_' . md5($ambiente . '_' . $dbName . '_' . $metaId);
    }
}
