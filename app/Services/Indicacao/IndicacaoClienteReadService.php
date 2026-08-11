<?php

namespace Services\Indicacao;

use Core\Database;
use PDO;

class IndicacaoClienteReadService
{
    private $db;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    public function obterParaCliente($clienteId): array
    {
        $clienteId = (int) $clienteId;
        if($clienteId <= 0){
            throw new \InvalidArgumentException('Cliente inválido.');
        }

        $cliente = $this->buscarCliente($clienteId);
        if(!$cliente){
            throw new \DomainException('Cliente não encontrado.');
        }

        $campanha = $this->buscarCampanhaPublicaElegivel();
        $codigo = $campanha ? $this->buscarCodigoDoCliente($clienteId, (int) $campanha['ICP_ID']) : null;

        return [
            'compartilhamento'=>$this->montarCompartilhamento($cliente, $campanha, $codigo),
            'resumo'=>$this->buscarResumo($clienteId),
            'indicacoes'=>$this->listarIndicacoes($clienteId),
            'creditos'=>$this->listarCreditos($clienteId)
        ];
    }

    private function buscarCliente(int $clienteId): ?array
    {
        $s = $this->db->prepare("SELECT c.CLI_ID, EXISTS(SELECT 1 FROM cobrancas cb WHERE cb.CLI_ID=c.CLI_ID AND cb.COB_Status='pago') AS CLI_TevePagamentoConfirmado FROM clientes c WHERE c.CLI_ID=? LIMIT 1");
        $s->execute([$clienteId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function buscarCampanhaPublicaElegivel(): ?array
    {
        $s = $this->db->query("SELECT ICP_ID, ICP_Nome, ICP_Percentual FROM indicacao_campanhas WHERE ICP_Ativo='S' AND ICP_Publica='S' AND (ICP_DataInicio IS NULL OR ICP_DataInicio <= CURRENT_TIMESTAMP) AND (ICP_DataFim IS NULL OR ICP_DataFim >= CURRENT_TIMESTAMP) LIMIT 1");
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function buscarCodigoDoCliente(int $clienteId, int $campanhaId): ?array
    {
        $s = $this->db->prepare('SELECT ICD_Codigo, ICD_Status FROM indicacao_codigos WHERE CLI_ID=? AND ICP_ID=? LIMIT 1');
        $s->execute([$clienteId, $campanhaId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function montarCompartilhamento(array $cliente, ?array $campanha, ?array $codigo): array
    {
        if(!$campanha){
            return ['disponivel'=>false, 'estado'=>'campanha_indisponivel', 'mensagem'=>'Não há uma campanha de indicação disponível para compartilhamento no momento.'];
        }
        if($codigo && $codigo['ICD_Status'] === 'ativo'){
            return [
                'disponivel'=>true,
                'codigo'=>$codigo['ICD_Codigo'],
                'campanha_nome'=>$campanha['ICP_Nome'],
                'percentual'=>$campanha['ICP_Percentual']
            ];
        }
        if($codigo){
            $mensagens = [
                'nao_liberado'=>'Seu código de indicação ainda não foi liberado.',
                'suspenso'=>'Seu código de indicação está temporariamente indisponível.',
                'cancelado'=>'Seu código de indicação não está disponível para compartilhamento.'
            ];
            return ['disponivel'=>false, 'estado'=>$codigo['ICD_Status'], 'mensagem'=>$mensagens[$codigo['ICD_Status']] ?? 'Seu código de indicação não está disponível para compartilhamento.'];
        }
        if(empty($cliente['CLI_TevePagamentoConfirmado'])){
            return ['disponivel'=>false, 'estado'=>'primeiro_pagamento_pendente', 'mensagem'=>'Seu código ficará disponível após a confirmação do seu primeiro pagamento.'];
        }
        return ['disponivel'=>false, 'estado'=>'codigo_nao_encontrado', 'mensagem'=>'Seu código de indicação ainda está sendo preparado.'];
    }

    private function buscarResumo(int $clienteId): array
    {
        $s = $this->db->prepare("SELECT COUNT(*) AS total_indicacoes, SUM(IND_Status='aguardando_pagamento') AS aguardando_pagamento, SUM(IND_Status='em_confirmacao') AS em_confirmacao, SUM(IND_Status='aprovada') AS aprovadas FROM indicacoes WHERE CLI_Indicador_ID=?");
        $s->execute([$clienteId]);
        $indicacoes = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        $s = $this->db->prepare("SELECT SUM(ICR_Status='liberado') AS creditos_disponiveis, SUM(ICR_Status='reservado') AS creditos_reservados, SUM(ICR_Status='utilizado') AS creditos_utilizados FROM indicacao_creditos WHERE CLI_Indicador_ID=?");
        $s->execute([$clienteId]);
        $creditos = $s->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_indicacoes'=>(int) ($indicacoes['total_indicacoes'] ?? 0),
            'aguardando_pagamento'=>(int) ($indicacoes['aguardando_pagamento'] ?? 0),
            'em_confirmacao'=>(int) ($indicacoes['em_confirmacao'] ?? 0),
            'aprovadas'=>(int) ($indicacoes['aprovadas'] ?? 0),
            'creditos_disponiveis'=>(int) ($creditos['creditos_disponiveis'] ?? 0),
            'creditos_reservados'=>(int) ($creditos['creditos_reservados'] ?? 0),
            'creditos_utilizados'=>(int) ($creditos['creditos_utilizados'] ?? 0)
        ];
    }

    private function listarIndicacoes(int $clienteId): array
    {
        $s = $this->db->prepare("SELECT i.IND_Status, i.IND_CadastradaEm, c.CLI_Nome, c.CLI_NomeFantasia, cr.ICR_Status FROM indicacoes i INNER JOIN clientes c ON c.CLI_ID=i.CLI_Indicado_ID LEFT JOIN indicacao_creditos cr ON cr.IND_ID=i.IND_ID WHERE i.CLI_Indicador_ID=? ORDER BY i.IND_CadastradaEm DESC, i.IND_ID DESC");
        $s->execute([$clienteId]);
        return array_map(function(array $item){
            $item['exibicao_nome'] = $this->nomeSeguro($item);
            $item['status_indicacao'] = $this->rotuloIndicacao($item['IND_Status']);
            $item['badge_indicacao'] = $this->badgeIndicacao($item['IND_Status']);
            $item['status_credito'] = $this->rotuloCredito($item['ICR_Status'] ?? null);
            $item['badge_credito'] = $this->badgeCredito($item['ICR_Status'] ?? null);
            unset($item['CLI_Nome'], $item['CLI_NomeFantasia']);
            return $item;
        }, $s->fetchAll(PDO::FETCH_ASSOC));
    }

    private function listarCreditos(int $clienteId): array
    {
        $s = $this->db->prepare("SELECT cr.ICR_Percentual, cr.ICR_Status, cr.ICR_LiberadoEm, cr.ICR_CriadoEm, r.ICRR_ReservadoEm, r.ICRR_UtilizadoEm FROM indicacao_creditos cr LEFT JOIN indicacao_credito_reservas r ON r.ICRR_ID=(SELECT r2.ICRR_ID FROM indicacao_credito_reservas r2 WHERE r2.ICR_ID=cr.ICR_ID ORDER BY r2.ICRR_ID DESC LIMIT 1) WHERE cr.CLI_Indicador_ID=? ORDER BY cr.ICR_CriadoEm DESC, cr.ICR_ID DESC");
        $s->execute([$clienteId]);
        return array_map(function(array $item){
            $item['status'] = $this->rotuloCredito($item['ICR_Status']);
            $item['badge'] = $this->badgeCredito($item['ICR_Status']);
            return $item;
        }, $s->fetchAll(PDO::FETCH_ASSOC));
    }

    private function nomeSeguro(array $item): string
    {
        if(in_array($item['IND_Status'], ['cancelada','fraude','inelegivel'], true)){
            return 'Indicação não concluída';
        }
        if(($item['ICR_Status'] ?? null) === 'utilizado'){
            return 'Indicação concluída — crédito utilizado';
        }
        $nome = trim((string) ($item['CLI_NomeFantasia'] ?: $item['CLI_Nome']));
        if($nome === ''){
            return 'Cliente indicado';
        }
        $tamanho = mb_strlen($nome, 'UTF-8');
        return $tamanho <= 2 ? mb_substr($nome, 0, 1, 'UTF-8') . '*' : mb_substr($nome, 0, min(3, $tamanho), 'UTF-8') . str_repeat('*', max(2, min(6, $tamanho - 3)));
    }

    private function rotuloIndicacao(?string $status): string
    {
        return ['cadastrada'=>'Cadastro realizado', 'aguardando_pagamento'=>'Aguardando pagamento', 'pagamento_confirmado'=>'Pagamento confirmado', 'em_confirmacao'=>'Em período de confirmação', 'aprovada'=>'Indicação confirmada', 'cancelada'=>'Indicação não concluída', 'fraude'=>'Indicação não concluída', 'inelegivel'=>'Indicação não concluída'][$status] ?? 'Em análise';
    }

    private function badgeIndicacao(?string $status): string
    {
        return ['cadastrada'=>'secondary', 'aguardando_pagamento'=>'warning', 'pagamento_confirmado'=>'info', 'em_confirmacao'=>'info', 'aprovada'=>'success', 'cancelada'=>'secondary', 'fraude'=>'danger', 'inelegivel'=>'secondary'][$status] ?? 'secondary';
    }

    private function rotuloCredito(?string $status): string
    {
        return ['liberado'=>'Disponível', 'reservado'=>'Desconto reservado', 'utilizado'=>'Desconto utilizado', 'bloqueado'=>'Bloqueado', 'pendente'=>'Em análise', 'em_confirmacao'=>'Em confirmação', 'cancelado'=>'Cancelado', 'expirado'=>'Expirado'][$status] ?? '-';
    }

    private function badgeCredito(?string $status): string
    {
        return ['liberado'=>'success', 'reservado'=>'info', 'utilizado'=>'primary', 'bloqueado'=>'warning', 'pendente'=>'secondary', 'em_confirmacao'=>'info', 'cancelado'=>'secondary', 'expirado'=>'secondary'][$status] ?? 'secondary';
    }
}
