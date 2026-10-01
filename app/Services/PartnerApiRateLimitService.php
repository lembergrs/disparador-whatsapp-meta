<?php

namespace Services;

class PartnerApiRateLimitService
{
    private $baseDir;

    public function __construct($baseDir = null)
    {
        $this->baseDir = $baseDir ?: dirname(__DIR__,2) . '/storage/cache/partner-rate-limit';
    }

    public function consumir($parceiroId, $apiKeyId, $grupo)
    {
        $config=$this->configuracao($grupo);
        $limite=$config['limit'];
        $janela=$config['window'];
        $agora=time();
        $inicio=(int)(floor($agora/$janela)*$janela);
        $reset=$inicio+$janela;

        if(!is_dir($this->baseDir) && !@mkdir($this->baseDir,0770,true) && !is_dir($this->baseDir)){
            throw new \RuntimeException('Não foi possível inicializar o rate limit da Partner API.');
        }

        $chave=hash('sha256',(int)$parceiroId.':'.(int)$apiKeyId.':'.$grupo.':'.$inicio);
        $arquivo=$this->baseDir.'/'.$chave.'.json';
        $fp=@fopen($arquivo,'c+');
        if(!$fp || !flock($fp,LOCK_EX)){
            if($fp) fclose($fp);
            throw new \RuntimeException('Não foi possível aplicar o rate limit da Partner API.');
        }

        $raw=stream_get_contents($fp);
        $estado=json_decode((string)$raw,true);
        $usados=is_array($estado) ? (int)($estado['count']??0) : 0;
        $permitido=$usados<$limite;
        if($permitido) $usados++;

        ftruncate($fp,0);
        rewind($fp);
        fwrite($fp,json_encode(['count'=>$usados,'reset'=>$reset]));
        fflush($fp);
        flock($fp,LOCK_UN);
        fclose($fp);

        if(mt_rand(1,100)===1) $this->limparExpirados($agora);

        return [
            'limit'=>$limite,
            'remaining'=>max(0,$limite-$usados),
            'reset'=>$reset,
            'retry_after'=>$permitido ? 0 : max(1,$reset-$agora),
            'allowed'=>$permitido
        ];
    }

    private function configuracao($grupo)
    {
        $configs=[
            'messages'=>['limit'=>60,'window'=>60],
            'media_upload'=>['limit'=>20,'window'=>60],
            'read'=>['limit'=>120,'window'=>60]
        ];
        if(!isset($configs[$grupo])) throw new \InvalidArgumentException('Grupo de rate limit inválido.');
        return $configs[$grupo];
    }

    private function limparExpirados($agora)
    {
        foreach((array)glob($this->baseDir.'/*.json') as $arquivo){
            if(@filemtime($arquivo)!==false && @filemtime($arquivo)<($agora-300)) @unlink($arquivo);
        }
    }
}
