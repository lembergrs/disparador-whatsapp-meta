<?php

namespace Services;

use Core\Database;
use Exception;
use PDO;

class MetaMediaService
{
    const TIPO_IMAGE = 'IMAGE';
    const TIPO_VIDEO = 'VIDEO';
    const TIPO_DOCUMENT = 'DOCUMENT';

    private $db;
    private $conta;

    private $limites = [
        self::TIPO_IMAGE => 5 * 1024 * 1024,
        self::TIPO_VIDEO => 16 * 1024 * 1024,
        self::TIPO_DOCUMENT => 10 * 1024 * 1024,
    ];

    private $extensoes = [
        self::TIPO_IMAGE => ['jpg', 'jpeg', 'png', 'webp'],
        self::TIPO_VIDEO => ['mp4', '3gpp'],
        self::TIPO_DOCUMENT => ['pdf'],
    ];

    private $mimes = [
        self::TIPO_IMAGE => ['image/jpeg', 'image/png', 'image/webp'],
        self::TIPO_VIDEO => ['video/mp4', 'video/3gpp'],
        self::TIPO_DOCUMENT => ['application/pdf'],
    ];

    public function __construct($metaId, $clienteId = null)
    {
        $this->db = Database::getInstance();

        $whereCliente = '';
        $params = [$metaId];

        if($clienteId !== null){
            $whereCliente = ' AND CLI_ID = ? ';
            $params[] = $clienteId;
        }

        $sql = $this->db->prepare("SELECT * FROM meta_contas WHERE MTA_ID = ? AND MTA_Ativo = 'S' {$whereCliente} LIMIT 1");
        $sql->execute($params);
        $this->conta = $sql->fetch(PDO::FETCH_ASSOC);

        if(!$this->conta){
            throw new Exception('Conta Meta não encontrada.');
        }
    }

    public function validarArquivo(array $arquivo, $tipo)
    {
        $tipo = strtoupper((string) $tipo);

        if(!isset($this->limites[$tipo])){
            throw new Exception('Tipo de mídia inválido.');
        }

        if(empty($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE){
            throw new Exception('Selecione um arquivo de mídia.');
        }

        if(($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK){
            throw new Exception('Falha ao receber o arquivo de mídia.');
        }

        $nomeOriginal = $this->nomeSeguro($arquivo['name'] ?? 'arquivo');
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        if(!in_array($ext, $this->extensoes[$tipo], true)){
            throw new Exception('Formato de arquivo não permitido para ' . $tipo . '.');
        }

        $tamanho = (int) ($arquivo['size'] ?? 0);
        if($tamanho <= 0 || $tamanho > $this->limites[$tipo]){
            throw new Exception('Arquivo maior que o limite permitido para ' . $tipo . '.');
        }

        $mime = $this->detectarMime($arquivo['tmp_name'] ?? '');
        if(!in_array($mime, $this->mimes[$tipo], true)){
            throw new Exception('MIME type inválido para ' . $tipo . '.');
        }

        return [
            'tipo' => $tipo,
            'nome_original' => $nomeOriginal,
            'extensao' => $ext,
            'mime' => $mime,
            'tamanho' => $tamanho,
        ];
    }

    public function uploadTemplateHandle(array $arquivo, $tipo)
    {
        $info = $this->validarArquivo($arquivo, $tipo);
        $temporario = $this->salvarTemporario($arquivo, $info);

        try{
            $handle = $this->enviarParaResumableUpload($temporario, $info);
            return array_merge($info, ['handle' => $handle]);
        }catch(Exception $e){
            $this->registrarErro('template_handle', $e->getMessage(), $info);
            throw $e;
        }finally{
            $this->removerTemporario($temporario);
        }
    }

    public function uploadMensagemMedia(array $arquivo, $tipo)
    {
        $info = $this->validarArquivo($arquivo, $tipo);
        $temporario = $this->salvarTemporario($arquivo, $info);

        try{
            $mediaId = $this->enviarParaWhatsappMedia($temporario, $info);
            return array_merge($info, ['media_id' => $mediaId]);
        }catch(Exception $e){
            $this->registrarErro('message_media', $e->getMessage(), $info);
            throw $e;
        }finally{
            $this->removerTemporario($temporario);
        }
    }

    public function limitesPublicos()
    {
        return $this->limites;
    }

    private function salvarTemporario(array $arquivo, array $info)
    {
        $dir = dirname(__DIR__, 2) . '/storage/tmp/meta';
        if(!is_dir($dir)){
            mkdir($dir, 0770, true);
        }

        $destino = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $info['extensao'];

        if(!move_uploaded_file($arquivo['tmp_name'], $destino)){
            if(!copy($arquivo['tmp_name'], $destino)){
                throw new Exception('Não foi possível salvar o arquivo temporário.');
            }
        }

        return $destino;
    }

    private function enviarParaWhatsappMedia($arquivo, array $info)
    {
        $url = rtrim($this->conta['MTA_UrlBase'], '/') . '/' . $this->conta['MTA_PhoneNumberId'] . '/media';

        $post = [
            'messaging_product' => 'whatsapp',
            'type' => $info['mime'],
            'file' => new \CURLFile($arquivo, $info['mime'], $info['nome_original'])
        ];

        $retorno = $this->curlPostMultipart($url, $post);

        if(empty($retorno['id'])){
            throw new Exception($retorno['error']['message'] ?? 'Meta não retornou o ID da mídia.');
        }

        return $retorno['id'];
    }

    private function enviarParaResumableUpload($arquivo, array $info)
    {
        $appId = defined('META_APP_ID') ? META_APP_ID : '';

        if($appId === ''){
            throw new Exception('META_APP_ID não configurado para gerar header_handle de templates com mídia.');
        }

        $base = rtrim($this->conta['MTA_UrlBase'], '/');
        $start = $this->curlPostForm($base . '/' . $appId . '/uploads', [
            'file_name' => $info['nome_original'],
            'file_length' => $info['tamanho'],
            'file_type' => $info['mime'],
        ]);

        if(empty($start['id'])){
            throw new Exception($start['error']['message'] ?? 'Meta não iniciou o upload do arquivo do template.');
        }

        $uploadUrl = $base . '/' . $start['id'];
        $conteudo = file_get_contents($arquivo);
        $retorno = $this->curlUploadBytes($uploadUrl, $conteudo);

        if(!empty($retorno['h'])){
            return $retorno['h'];
        }

        if(!empty($retorno['handle'])){
            return $retorno['handle'];
        }

        throw new Exception($retorno['error']['message'] ?? 'Meta não retornou header_handle para o template.');
    }

    private function curlPostMultipart($url, array $post)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->conta['MTA_Token']],
        ]);

        return $this->finalizarCurl($curl);
    }

    private function curlPostForm($url, array $post)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->conta['MTA_Token']],
        ]);

        return $this->finalizarCurl($curl);
    }

    private function curlUploadBytes($url, $conteudo)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $conteudo,
            CURLOPT_HTTPHEADER => [
                'Authorization: OAuth ' . $this->conta['MTA_Token'],
                'file_offset: 0',
                'Content-Type: application/octet-stream',
            ],
        ]);

        return $this->finalizarCurl($curl);
    }

    private function finalizarCurl($curl)
    {
        $response = curl_exec($curl);
        $erro = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $retorno = json_decode($response, true);
        if(!is_array($retorno)){
            $retorno = [];
        }

        $retorno['http_code'] = $httpCode;

        if($erro){
            throw new Exception($erro);
        }

        if($httpCode >= 400){
            throw new Exception($retorno['error']['message'] ?? 'Erro de upload na Meta.');
        }

        return $retorno;
    }

    private function detectarMime($arquivo)
    {
        if(!is_file($arquivo)){
            return '';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return (string) $finfo->file($arquivo);
    }

    private function nomeSeguro($nome)
    {
        $nome = basename((string) $nome);
        $nome = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nome);
        return trim($nome, '._') ?: 'arquivo';
    }

    private function removerTemporario($arquivo)
    {
        if($arquivo && is_file($arquivo)){
            @unlink($arquivo);
        }
    }

    private function registrarErro($contexto, $erro, array $info = [])
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if(!is_dir($dir)){
            mkdir($dir, 0770, true);
        }

        unset($info['handle'], $info['media_id']);

        error_log(json_encode([
            'data_hora' => date('Y-m-d H:i:s'),
            'contexto' => $contexto,
            'erro' => $erro,
            'arquivo' => $info,
            'meta_id' => $this->conta['MTA_ID'] ?? null,
            'cli_id' => $this->conta['CLI_ID'] ?? null,
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $dir . '/meta_media.log');
    }
}
