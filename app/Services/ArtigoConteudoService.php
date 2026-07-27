<?php

namespace Services;

class ArtigoConteudoService
{
    private const TAGS_PERMITIDAS = [
        'p','br','strong','b','em','i','u','h2','h3','h4','ul','ol','li',
        'blockquote','pre','code','table','thead','tbody','tr','th','td','a','img','hr'
    ];

    public static function slug($texto)
    {
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim((string) $texto));
        $texto = strtolower((string) $texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
        return trim((string) $texto, '-') ?: 'artigo';
    }

    public static function sanitizarHtml($html)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="artigo-raiz">' . (string) $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $raiz = $dom->getElementById('artigo-raiz');
        if(!$raiz){
            return '';
        }

        self::sanitizarNo($raiz);
        $resultado = '';
        foreach($raiz->childNodes as $filho){
            $resultado .= $dom->saveHTML($filho);
        }
        return trim($resultado);
    }

    private static function sanitizarNo(\DOMNode $no)
    {
        for($i = $no->childNodes->length - 1; $i >= 0; $i--){
            $filho = $no->childNodes->item($i);
            if($filho instanceof \DOMElement){
                $tag = strtolower($filho->tagName);
                if(!in_array($tag, self::TAGS_PERMITIDAS, true)){
                    if(in_array($tag, ['script','iframe','object','embed','style','form'], true)){
                        $no->removeChild($filho);
                        continue;
                    }
                    while($filho->firstChild){
                        $no->insertBefore($filho->firstChild, $filho);
                    }
                    $no->removeChild($filho);
                    continue;
                }

                for($a = $filho->attributes->length - 1; $a >= 0; $a--){
                    $atributo = $filho->attributes->item($a);
                    $nome = strtolower($atributo->name);
                    $permitido = ($tag === 'a' && in_array($nome, ['href','title','target','rel'], true))
                        || ($tag === 'img' && in_array($nome, ['src','alt','width','height','loading'], true))
                        || (in_array($tag, ['th','td'], true) && in_array($nome, ['colspan','rowspan','scope'], true));
                    if(!$permitido){
                        $filho->removeAttribute($atributo->name);
                    }
                }

                if($tag === 'a'){
                    $href = trim($filho->getAttribute('href'));
                    if(!preg_match('#^(https?://|mailto:|\#|/)#i', $href)){
                        $filho->removeAttribute('href');
                    }
                    if($filho->getAttribute('target') === '_blank'){
                        $filho->setAttribute('rel', 'noopener noreferrer');
                    }
                }
                if($tag === 'img'){
                    $src = trim($filho->getAttribute('src'));
                    if(!preg_match('#^(https://|/public/uploads/blog/)#i', $src)){
                        $no->removeChild($filho);
                        continue;
                    }
                    $filho->setAttribute('loading', 'lazy');
                }
                self::sanitizarNo($filho);
            }
        }
    }

    public static function prepararSumario($html)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="artigo-raiz">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $titulos = $xpath->query('//*[@id="artigo-raiz"]//h2 | //*[@id="artigo-raiz"]//h3');
        $sumario = [];
        $usados = [];
        foreach($titulos as $titulo){
            $base = self::slug($titulo->textContent);
            $id = $base;
            $n = 2;
            while(isset($usados[$id])){ $id = $base . '-' . $n++; }
            $usados[$id] = true;
            $titulo->setAttribute('id', $id);
            $sumario[] = ['id'=>$id, 'texto'=>trim($titulo->textContent), 'nivel'=>strtolower($titulo->nodeName)];
        }
        $raiz = $dom->getElementById('artigo-raiz');
        $conteudo = '';
        foreach($raiz->childNodes as $filho){ $conteudo .= $dom->saveHTML($filho); }
        return ['conteudo'=>$conteudo, 'sumario'=>count($sumario) >= 3 ? $sumario : []];
    }
}
