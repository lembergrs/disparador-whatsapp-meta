<?php

namespace Controllers;

use Models\Artigo;

class SitemapController
{
    public function index()
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $urls = [
            ['loc'=>'https://disparador.net/', 'lastmod'=>null],
            ['loc'=>'https://disparador.net/whatsapp-business', 'lastmod'=>null],
            ['loc'=>'https://disparador.net/limites-whatsapp', 'lastmod'=>null],
            ['loc'=>'https://disparador.net/precos-whatsapp-meta', 'lastmod'=>null],
            ['loc'=>'https://disparador.net/blog', 'lastmod'=>null],
            ['loc'=>'https://disparador.net/site/cadastro', 'lastmod'=>null],
            ['loc'=>'https://disparador.net/site/termosUso', 'lastmod'=>null],
            ['loc'=>'https://disparador.net/site/politicaPrivacidade', 'lastmod'=>null],
            ['loc'=>'https://disparador.net/site/politicaCancelamento', 'lastmod'=>null]
        ];
        try{
            foreach((new Artigo())->slugsPublicados() as $artigo){
                $urls[] = ['loc'=>'https://disparador.net/blog/' . rawurlencode($artigo['ART_Slug']), 'lastmod'=>date('c', strtotime($artigo['ART_AtualizadoEm']))];
            }
        }catch(\Throwable $e){ error_log('Sitemap do blog indisponível: ' . $e->getMessage()); }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach($urls as $url){
            echo '  <url><loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>';
            if($url['lastmod']) echo '<lastmod>' . $url['lastmod'] . '</lastmod>';
            echo '</url>' . "\n";
        }
        echo '</urlset>';
    }
}
