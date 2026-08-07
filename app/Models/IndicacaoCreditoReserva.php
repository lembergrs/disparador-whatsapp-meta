<?php

namespace Models;

use Core\Database;
use PDO;

class IndicacaoCreditoReserva
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function inserir(array $dados): int
    {
        $sql = $this->db->prepare("INSERT INTO indicacao_credito_reservas (ICR_ID,ICRR_ReferenciaTipo,ICRR_ReferenciaID,ICRR_Ciclo,ICRR_MesesCiclo,ICRR_ValorBaseCicloCentavos,ICRR_MensalidadeEquivalenteCentavos,ICRR_Percentual,ICRR_DescontoCentavos,ICRR_Status,ICRR_ReservadoEm) VALUES (?,?,?,?,?,?,?,?,?,'reservada',?)");
        $sql->execute([(int)$dados['credito_id'],$dados['referencia_tipo'],$dados['referencia_id'],$dados['ciclo'],(int)$dados['meses_ciclo'],(int)$dados['valor_base_centavos'],(int)$dados['mensalidade_equivalente_centavos'],$dados['percentual'],(int)$dados['desconto_centavos'],$dados['reservado_em']]);
        return (int)$this->db->lastInsertId();
    }

    public function buscar($id, $lock = false)
    {
        $sql = $this->db->prepare('SELECT * FROM indicacao_credito_reservas WHERE ICRR_ID=?' . $this->forUpdate($lock));
        $sql->execute([(int)$id]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listarPorReferencia($tipo, $id, $lock = false): array
    {
        $sql = $this->db->prepare('SELECT * FROM indicacao_credito_reservas WHERE ICRR_ReferenciaTipo=? AND ICRR_ReferenciaID=? ORDER BY ICRR_ID' . $this->forUpdate($lock));
        $sql->execute([$tipo,$id]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarPorCredito($creditoId, $lock = false): array
    {
        $sql = $this->db->prepare('SELECT * FROM indicacao_credito_reservas WHERE ICR_ID=? ORDER BY ICRR_ID' . $this->forUpdate($lock));
        $sql->execute([(int)$creditoId]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function transicionar($id, $anterior, $novo): bool
    {
        $campos = ['utilizada'=>'ICRR_UtilizadoEm','liberada'=>'ICRR_LiberadoEm','cancelada'=>'ICRR_CanceladoEm'];
        $extra = isset($campos[$novo]) ? ",{$campos[$novo]}=CURRENT_TIMESTAMP" : '';
        $sql = $this->db->prepare("UPDATE indicacao_credito_reservas SET ICRR_Status=?{$extra} WHERE ICRR_ID=? AND ICRR_Status=?");
        $sql->execute([$novo,(int)$id,$anterior]);
        return $sql->rowCount() === 1;
    }

    public function adquirirLockReferencia($tipo, $id, $timeout = 10): bool
    {
        if($this->driver() !== 'mysql') return true;
        $chave = 'indicacao_reserva:' . hash('sha256', $tipo . ':' . $id);
        $sql = $this->db->prepare('SELECT GET_LOCK(?,?)');
        $sql->execute([$chave,max(0,(int)$timeout)]);
        return (int)$sql->fetchColumn() === 1;
    }

    public function liberarLockReferencia($tipo, $id): void
    {
        if($this->driver() !== 'mysql') return;
        $chave = 'indicacao_reserva:' . hash('sha256', $tipo . ':' . $id);
        $sql = $this->db->prepare('SELECT RELEASE_LOCK(?)');
        $sql->execute([$chave]);
    }

    private function forUpdate($lock): string
    {
        return $lock && $this->driver() === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function driver(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
