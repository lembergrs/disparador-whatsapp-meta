<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Csrf;
use Models\Notificacao;
use Models\NotificacaoConfiguracao;
use Services\CanalNotificacao;
use Services\EventoNotificacao;
use Services\NotificacaoFormatador;
use Services\NotificacaoService;

class NotificacaoController extends Controller
{
    private $notificacoes;
    private $configuracoes;

    public function __construct()
    {
        Auth::admin();
        $this->notificacoes = new Notificacao();
        $this->configuracoes = new NotificacaoConfiguracao();
    }

    public function index()
    {
        $configPadrao = require __DIR__ . '/../../config/notificacoes.php';
        $this->view('notificacoes/index', [
            'titulo' => 'Notificações',
            'resumo' => $this->notificacoes->resumoAdmin(),
            'eventos' => EventoNotificacao::todos(),
            'canais' => $this->configuracoes->canaisConhecidos(),
            'matrizConfiguracao' => $this->configuracoes->matriz($configPadrao),
        ]);
    }

    public function dados()
    {
        $filtros = $this->filtrosRequest();
        $draw = (int) ($_GET['draw'] ?? 1);
        $inicio = max(0, (int) ($_GET['start'] ?? 0));
        $limite = max(10, min(100, (int) ($_GET['length'] ?? 25)));
        if(!empty($_GET['search']['value'])) $filtros['q'] = trim((string) $_GET['search']['value']);

        $total = $this->notificacoes->contarAdmin([]);
        $filtrado = $this->notificacoes->contarAdmin($filtros);
        $linhas = array_map([$this, 'linhaDataTable'], $this->notificacoes->listarAdmin($filtros, $limite, $inicio));

        $this->json(['draw'=>$draw, 'recordsTotal'=>$total, 'recordsFiltered'=>$filtrado, 'data'=>$linhas]);
    }

    public function detalhe()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $notificacao = $id > 0 ? $this->notificacoes->buscarAdmin($id) : null;
        if(!$notificacao){
            $this->json(['ok'=>false, 'message'=>'Notificação não encontrada.'], 404);
        }
        $this->json(['ok'=>true, 'html'=>$this->renderDetalhe($notificacao)]);
    }

    public function reenviar()
    {
        Csrf::exigirPost();
        $id = (int) ($_POST['id'] ?? 0);
        $notificacao = $id > 0 ? $this->notificacoes->buscarAdmin($id) : null;
        if(!$notificacao){ $this->json(['ok'=>false, 'message'=>'Notificação não encontrada.'], 404); }
        if(($notificacao['NOT_Canal'] ?? '') !== CanalNotificacao::EMAIL){ $this->json(['ok'=>false, 'message'=>'Canal ainda não disponível para reenvio.'], 422); }
        if(!in_array(($notificacao['NOT_Status'] ?? ''), ['pendente','erro_temporario','erro_definitivo'], true)){ $this->json(['ok'=>false, 'message'=>'Esta notificação não está em status seguro para reenvio.'], 422); }
        if(!$this->notificacoes->marcarReenvioProcessando($id)){ $this->json(['ok'=>false, 'message'=>'A notificação já está em processamento. Aguarde antes de tentar novamente.'], 409); }

        $atualizada = $this->notificacoes->buscarAdmin($id);
        $resultado = (new NotificacaoService())->reenviarEmailAdmin($atualizada);
        $this->notificacoes->finalizarReenvio($id, $resultado);
        $final = $this->notificacoes->buscarAdmin($id);
        $this->json(['ok'=>!empty($resultado['sucesso']), 'message'=>!empty($resultado['sucesso']) ? 'Notificação reenviada com sucesso.' : NotificacaoFormatador::sanitizarTexto($resultado['mensagem'] ?? 'Não foi possível reenviar.'), 'row'=>$this->linhaDataTable($final)]);
    }

    public function salvarConfiguracao()
    {
        Csrf::exigirPost();
        $eventosEmail = array_values(array_intersect($_POST['email'] ?? [], EventoNotificacao::todos()));
        $this->configuracoes->salvarEmailPorEvento($eventosEmail);
        $this->json(['ok'=>true, 'message'=>'Configuração de canais atualizada com sucesso.']);
    }

    private function filtrosRequest()
    {
        return [
            'cliente_id' => $_GET['cliente_id'] ?? '',
            'evento' => $_GET['evento'] ?? '',
            'canal' => $_GET['canal'] ?? '',
            'status' => $_GET['status'] ?? '',
            'data_inicial' => $_GET['data_inicial'] ?? '',
            'data_final' => $_GET['data_final'] ?? '',
            'destino' => trim((string) ($_GET['destino'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    private function linhaDataTable(array $n)
    {
        $status = (string) ($n['NOT_Status'] ?? '');
        $podeReenviar = ($n['NOT_Canal'] ?? '') === CanalNotificacao::EMAIL && in_array($status, ['pendente','erro_temporario','erro_definitivo'], true);
        return [
            'data' => $this->data($n['NOT_CriadoEm'] ?? null),
            'cliente' => htmlspecialchars($this->cliente($n), ENT_QUOTES, 'UTF-8'),
            'evento' => htmlspecialchars(NotificacaoFormatador::evento($n['NOT_Tipo'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'canal' => htmlspecialchars(NotificacaoFormatador::canal($n['NOT_Canal'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'destino' => htmlspecialchars($n['NOT_Destino'] ?? '-', ENT_QUOTES, 'UTF-8'),
            'assunto' => htmlspecialchars($n['NOT_Assunto'] ?? '-', ENT_QUOTES, 'UTF-8'),
            'status' => '<span class="badge badge-' . NotificacaoFormatador::badgeStatus($status) . '">' . htmlspecialchars(NotificacaoFormatador::status($status), ENT_QUOTES, 'UTF-8') . '</span>',
            'tentativas' => (int) ($n['NOT_Tentativas'] ?? 0),
            'atualizado' => $this->data($n['NOT_AtualizadoEm'] ?? null),
            'acoes' => '<button class="btn btn-sm btn-info js-detalhe" data-id="' . (int) $n['NOT_ID'] . '">Detalhes</button> ' . ($podeReenviar ? '<button class="btn btn-sm btn-warning js-reenviar" data-id="' . (int) $n['NOT_ID'] . '">Reenviar</button>' : ''),
        ];
    }

    private function renderDetalhe(array $n)
    {
        $dados = NotificacaoFormatador::dadosSeguros($n['NOT_Dados'] ?? null);
        $erro = NotificacaoFormatador::sanitizarTexto($n['NOT_Erro'] ?? '');
        ob_start(); ?>
        <dl class="row mb-0">
            <dt class="col-sm-4">ID</dt><dd class="col-sm-8"><?= (int) $n['NOT_ID']; ?></dd>
            <dt class="col-sm-4">Cliente</dt><dd class="col-sm-8"><?= htmlspecialchars($this->cliente($n)); ?></dd>
            <dt class="col-sm-4">Evento</dt><dd class="col-sm-8"><?= htmlspecialchars(NotificacaoFormatador::evento($n['NOT_Tipo'] ?? '')); ?></dd>
            <dt class="col-sm-4">Canal</dt><dd class="col-sm-8"><?= htmlspecialchars(NotificacaoFormatador::canal($n['NOT_Canal'] ?? '')); ?></dd>
            <dt class="col-sm-4">Assunto</dt><dd class="col-sm-8"><?= htmlspecialchars($n['NOT_Assunto'] ?? '-'); ?></dd>
            <dt class="col-sm-4">Destino</dt><dd class="col-sm-8"><?= htmlspecialchars($n['NOT_Destino'] ?? '-'); ?></dd>
            <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= htmlspecialchars(NotificacaoFormatador::status($n['NOT_Status'] ?? '')); ?></dd>
            <dt class="col-sm-4">Tentativas</dt><dd class="col-sm-8"><?= (int) ($n['NOT_Tentativas'] ?? 0); ?></dd>
            <dt class="col-sm-4">Criação</dt><dd class="col-sm-8"><?= $this->data($n['NOT_CriadoEm'] ?? null); ?></dd>
            <dt class="col-sm-4">Envio</dt><dd class="col-sm-8"><?= $this->data($n['NOT_DataEnvio'] ?? null); ?></dd>
            <dt class="col-sm-4">Leitura</dt><dd class="col-sm-8"><?= $this->data($n['NOT_DataLeitura'] ?? null); ?></dd>
            <dt class="col-sm-4">Atualização</dt><dd class="col-sm-8"><?= $this->data($n['NOT_AtualizadoEm'] ?? null); ?></dd>
            <dt class="col-sm-4">Erro</dt><dd class="col-sm-8"><code><?= htmlspecialchars($erro ?: '-'); ?></code></dd>
            <dt class="col-sm-4">Dados</dt><dd class="col-sm-8"><pre class="bg-light p-2 border rounded mb-0"><?= htmlspecialchars(json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre></dd>
        </dl>
        <?php return ob_get_clean();
    }

    private function cliente(array $n)
    {
        foreach(['CLI_NomeFantasia','CLI_RazaoSocial','CLI_Nome','CLI_Email'] as $campo){ if(!empty($n[$campo])) return (string) $n[$campo]; }
        return !empty($n['CLI_ID']) ? 'Cliente não disponível' : 'Sistema';
    }

    private function data($data){ return $data ? date('d/m/Y H:i', strtotime($data)) : '-'; }

    private function json(array $payload, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
