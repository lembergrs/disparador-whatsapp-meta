<?php

namespace Services;

use Models\OnboardingReadModel;

/** Interpreta evidências persistidas sem executar etapas do onboarding. */
class OnboardingChecklistService
{
    private $leitura;

    public function __construct($leitura = null) { $this->leitura = $leitura; }

    public function calcular($clienteId, array $acesso = [], $contaId = null): array
    {
        $leitura = $this->leitura ?: new OnboardingReadModel();
        $contas = $leitura->contas((int) $clienteId);
        $conta = self::selecionarConta($contas, (int) $clienteId, $contaId);
        $templates = $conta ? $leitura->templates((int) $clienteId, (int) $conta['MTA_ID']) : [];
        $envio = $conta ? $leitura->envio((int) $clienteId, (int) $conta['MTA_ID']) : null;
        $resultado = self::interpretar($conta, $templates, $envio, $acesso);
        $resultado['contas'] = $contas;
        $resultado['contexto_invalido'] = $contaId !== null && !$conta;
        if($resultado['contexto_invalido']){
            $resultado['proxima'] = self::acao('selecionar_conta', 'Selecione seu WhatsApp',
                'A conta solicitada não está disponível para este cliente. Escolha um dos seus números abaixo.',
                'Escolher meu WhatsApp', 'dashboard');
        }
        return $resultado;
    }

    public static function selecionarConta(array $contas, int $clienteId, $contaId = null): ?array
    {
        $contas = array_values(array_filter($contas, function($c) use ($clienteId){
            return (int) $c['CLI_ID'] === $clienteId && ($c['MTA_Ativo'] ?? '') === 'S';
        }));
        if($contaId !== null){
            foreach($contas as $c) if((int) $c['MTA_ID'] === (int) $contaId) return $c;
            return null;
        }
        // Uma desconexão não apaga a entrega comprovada na mesma conta ainda ativa.
        usort($contas, function($a, $b){
            return [!empty($b['entregue']), ($b['MTA_Status'] ?? '') === 'conectado', (int) $b['MTA_ID']]
                <=> [!empty($a['entregue']), ($a['MTA_Status'] ?? '') === 'conectado', (int) $a['MTA_ID']];
        });
        return $contas[0] ?? null;
    }

    public static function interpretar(?array $conta, array $templates, ?array $envio, array $acesso): array
    {
        $operacional = !empty($acesso['operacional']);
        $conectado = $conta && ($conta['MTA_Ativo'] ?? '') === 'S' && ($conta['MTA_Status'] ?? '') === 'conectado';
        $pagamento = $conta && ($conta['MTA_PagamentoMetaStatus'] ?? null) === 'confirmado_cliente';
        $templates = array_values(array_filter($templates, function($t) use ($conta){
            return $conta && (int) ($t['MTA_ID'] ?? 0) === (int) $conta['MTA_ID']
                && ($t['TMP_Ativo'] ?? '') === 'S' && !empty($t['TMP_MetaId']);
        }));
        $statusTemplates = array_column($templates, 'TMP_Status');
        $aprovado = in_array('APPROVED', $statusTemplates, true);
        $pendente = in_array('PENDING', $statusTemplates, true);
        $rejeitado = in_array('REJECTED', $statusTemplates, true);
        $envio = $envio && $conta && (int) ($envio['MTA_ID'] ?? 0) === (int) $conta['MTA_ID'] ? $envio : null;
        $estadoEnvio = self::estadoEnvio($envio);
        $entregue = !empty($conta['entregue']) || $estadoEnvio === 'delivered';
        $rotaEnvio = ($envio['origem'] ?? '') === 'conversa' ? 'conversa' : 'disparo/historico';

        if(!$conectado){
            $proxima = self::conexao($conta);
        }elseif(!$pagamento){
            $proxima = self::acao('pagamento_meta', 'Configure o pagamento das mensagens na Meta',
                'A mensalidade do Disparador.net e as tarifas do WhatsApp são independentes. Você paga o uso do Disparador.net à RL2 Net, enquanto as mensagens da WhatsApp Business Platform são cobradas diretamente pela Meta.',
                'Configurar na Meta', 'https://business.facebook.com/wa/manage/home/', 'externo');
        }elseif($entregue){
            $proxima = null;
        }elseif(in_array($estadoEnvio, ['processing', 'accepted', 'sent'], true)){
            $titulos = ['processing'=>'Estamos processando sua primeira mensagem', 'accepted'=>'Sua mensagem foi aceita pela Meta', 'sent'=>'Sua mensagem foi enviada'];
            $proxima = self::acao('envio_' . $estadoEnvio, $titulos[$estadoEnvio],
                $estadoEnvio === 'processing' ? 'O envio está na fila. Acompanhe o resultado antes de tentar novamente.' : 'Estamos aguardando a confirmação de entrega. A aceitação pela Meta ainda não confirma que a mensagem chegou.',
                'Acompanhar envio', $rotaEnvio);
        }elseif(!$aprovado){
            if($pendente){
                $proxima = self::acao('template_pending', 'Seu template está em análise pela Meta',
                    'A solicitação foi enviada. A aprovação é realizada pela Meta e pode levar algum tempo. Abra seus templates para atualizar a situação.', 'Atualizar situação', 'template');
            }elseif($rejeitado){
                $proxima = self::acao('template_rejected', 'Seu template não foi aprovado pela Meta',
                    'A decisão de aprovação é da Meta. Consulte o motivo e prepare outro template, se necessário.', 'Ver meus templates', 'template');
            }elseif($templates){
                $proxima = self::acao('template_indisponivel', 'Seu template ainda não está disponível para envio',
                    'Um template pausado, desativado ou em outra situação precisa ser revisto. Escolha um modelo aprovado ou crie outro.', 'Ver meus templates', 'template');
            }else{
                $proxima = self::acao('template_criar', 'Prepare sua primeira mensagem',
                    'Para iniciar uma conversa pela API Oficial, você precisa usar um template aprovado pela Meta. Você pode criar um novo template e enviá-lo para análise. Se já tem modelos na Meta, consulte a sincronização na tela de templates.', 'Criar meu primeiro template', 'template');
            }
        }elseif($estadoEnvio === 'failed'){
            $proxima = self::acao('envio_failed', 'Não foi possível concluir seu primeiro envio',
                'Consulte o resultado para entender o motivo. Depois de corrigir a pendência, tente novamente pelo Disparo Manual.', 'Ver motivo e tentar novamente', $rotaEnvio);
            if(($envio['origem'] ?? '') === 'legado'){
                $proxima = self::acao('envio_failed', 'Não foi possível concluir seu primeiro envio',
                    'Há um registro antigo de falha, sem histórico detalhado disponível nesta jornada. Confira sua configuração e tente novamente pelo Disparo Manual.', 'Tentar novamente no Disparo Manual', 'disparo');
            }
        }else{
            $proxima = self::acao('primeiro_envio', 'Envie sua primeira mensagem',
                'Agora vamos testar sua configuração. Digite um número e envie uma mensagem usando seu template aprovado. Não é necessário importar contatos, criar lista ou campanha para este teste.', 'Enviar minha primeira mensagem', 'disparo');
        }

        // As permissões vêm do Auth; não se reproduzem cálculos de trial/financeiro.
        if(!$operacional && empty($acesso['pre_trial'])){
            $proxima = self::acao('financeiro', 'Regularize seu acesso para continuar',
                'Seu acesso operacional está indisponível. Consulte o período de avaliação e a situação do seu plano no Financeiro. Esse pagamento é independente das tarifas da Meta.', 'Ver meu plano e acesso', 'financeiro');
        }elseif(!$operacional && $conectado){
            $proxima = self::acao('acesso_pendente', 'Precisamos conferir a liberação da sua avaliação',
                'Seu número consta como conectado, mas o acesso operacional ainda não foi liberado. Solicite ajuda para conferir essa situação; abrir o Dashboard não inicia nem reinicia a avaliação.', 'Ver minha conexão', 'configuracao/meta');
        }
        if($proxima && empty($acesso['gerenciar']) && !in_array($proxima['url'], ['disparo', 'disparo/historico', 'conversa'], true)){
            $proxima = self::acao('responsavel', 'Peça ajuda ao responsável pela sua conta',
                'O responsável pela conta pode concluir a configuração ou conferir o plano. Você também pode usar o suporte de onboarding pelo WhatsApp.', null, null);
        }elseif($proxima && $proxima['url'] === 'configuracao/meta' && empty($acesso['configuracao'])){
            $proxima = self::acao('responsavel', 'Peça ajuda ao responsável pela sua conta',
                'A configuração deste número precisa ser conferida pelo responsável pela conta.', null, null);
        }

        $definicoes = [
            ['cadastro', 'Cadastro realizado', true],
            ['conexao', 'WhatsApp conectado', (bool) $conectado],
            ['pagamento', $pagamento ? 'Pagamento Meta confirmado por você' : 'Confirmar configuração de pagamento Meta', (bool) $pagamento],
            ['template', $aprovado ? 'Template pronto' : 'Disponibilizar primeiro template', !empty($templates)],
            ['aprovacao', 'Template aprovado', $aprovado],
            ['envio', 'Primeira mensagem entregue', $entregue],
        ];
        $itens = []; $encontrouPendente = false;
        foreach($definicoes as [$id, $label, $done]){
            $atual = !$entregue && !$done && !$encontrouPendente;
            $itens[] = ['id'=>$id, 'label'=>$label, 'done'=>$done, 'atual'=>$atual, 'bloqueada'=>!$done && !$atual];
            if(!$done) $encontrouPendente = true;
        }
        $concluidos = count(array_filter($itens, function($item){ return $item['done']; }));
        return [
            'conta'=>$conta, 'itens'=>$itens, 'total'=>6, 'concluidos'=>$concluidos,
            'percentual'=>$entregue ? 100 : (int) round($concluidos / 6 * 100),
            'concluido'=>$entregue, 'proxima'=>$proxima, 'recuperacao'=>$entregue && $proxima !== null,
            'estado_envio'=>$estadoEnvio, 'pre_trial'=>!empty($acesso['pre_trial']),
            'operacional'=>$operacional, 'conectado'=>(bool) $conectado,
            'opcionais'=>$operacional && ($entregue || $pendente),
        ];
    }

    private static function estadoEnvio(?array $envio): string
    {
        if(!$envio) return 'none';
        $status = strtolower(trim((string) ($envio['status'] ?? '')));
        $messageId = trim((string) ($envio['message_id'] ?? ''));
        if(in_array($status, ['failed', 'erro', 'falha', 'cancelado'], true)) return 'failed';
        if($messageId !== '' && in_array($status, ['delivered', 'entregue', 'read', 'lido'], true)) return 'delivered';
        if($messageId !== '' && in_array($status, ['sent', 'enviado'], true)) return 'sent';
        if($messageId !== '' && in_array($status, ['aguardando_confirmacao', 'pending', 'pendente'], true)) return 'accepted';
        if(in_array($status, ['processing', 'processando', 'pending', 'pendente', 'fila'], true)) return 'processing';
        return 'none';
    }

    private static function conexao(?array $conta): array
    {
        $status = $conta['MTA_Status'] ?? '';
        $coexistencia = ($conta['MTA_OnboardingType'] ?? '') === 'coexistence';
        if($status === 'pendente_registro' && !$coexistencia){
            return self::acao('conexao_registro', 'Falta concluir a conexão', 'Seu número já foi vinculado à Meta. Agora precisamos concluir o registro.', 'Concluir registro', 'configuracao/meta');
        }
        if($status === 'erro_registro' && !$coexistencia){
            return self::acao('conexao_erro', 'Não foi possível concluir o registro', 'Confira o PIN de seis dígitos na tela de conexão e tente novamente.', 'Tentar novamente', 'configuracao/meta');
        }
        if(in_array($status, ['requer_acao', 'pendente_registro', 'erro_registro'], true)){
            return self::acao('conexao_acao', 'Sua conexão precisa de atenção',
                $coexistencia ? 'A Meta ainda precisa confirmar a conexão com seu WhatsApp Business. Confira as orientações e atualize a situação na tela de conexão. Esse fluxo não usa o registro por PIN do Disparador.' : 'Confira as orientações na tela de conexão. Pode ser necessário concluir o registro ou resolver uma pendência na Meta.',
                'Ver pendência da conexão', 'configuracao/meta');
        }
        if($conta) return self::acao('conexao_reconectar', 'Reconecte seu WhatsApp', 'A conexão deste número precisa ser restabelecida para voltar a enviar pelo Disparador.', 'Reconectar WhatsApp', 'configuracao/meta');
        return self::acao('conexao_iniciar', 'Conecte seu WhatsApp', 'Vamos vincular o número que você usará para enviar mensagens pela API Oficial do WhatsApp.', 'Conectar meu WhatsApp', 'configuracao/meta');
    }

    private static function acao(string $id, string $titulo, string $descricao, ?string $label, ?string $url, string $tipo = 'interno'): array
    {
        return compact('id', 'titulo', 'descricao', 'label', 'url', 'tipo');
    }
}
