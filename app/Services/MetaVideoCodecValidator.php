<?php

namespace Services;

use Exception;

class MetaVideoCodecValidator
{
    public static function validar($caminho)
    {
        if(!is_file($caminho)){
            throw new Exception('Arquivo de vídeo não encontrado para validação.');
        }

        // A verificação do MIME não identifica o codec real dentro do MP4.
        // Sem ffprobe não podemos garantir compatibilidade: bloquear o upload.
        $binario = getenv('FFPROBE_BIN') ?: 'ffprobe';
        $comando = escapeshellarg($binario)
            . ' -v error -show_entries stream=codec_type,codec_name'
            . ' -of json ' . escapeshellarg($caminho) . ' 2>&1';
        $linhas = [];
        $codigo = 1;
        exec($comando, $linhas, $codigo);
        $dados = $codigo === 0 ? json_decode(implode("\n", $linhas), true) : null;

        if(!is_array($dados) || !isset($dados['streams']) || !is_array($dados['streams'])){
            throw new Exception('Não foi possível validar o codec do vídeo. Verifique se o ffprobe está instalado no servidor.');
        }

        $video = null;
        $audios = [];
        foreach($dados['streams'] as $stream){
            if(($stream['codec_type'] ?? '') === 'video' && $video === null){
                $video = strtolower((string) ($stream['codec_name'] ?? ''));
            }
            if(($stream['codec_type'] ?? '') === 'audio'){
                $audios[] = strtolower((string) ($stream['codec_name'] ?? ''));
            }
        }

        if($video !== 'h264' || count(array_diff($audios, ['aac'])) > 0){
            throw new Exception('Vídeo incompatível com o WhatsApp. Converta o arquivo para MP4 com vídeo H.264 (AVC) e áudio AAC antes de enviar.');
        }
    }
}
