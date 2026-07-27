<?php

namespace Models;

use Core\Database;
use PDO;
use Services\ArtigoConteudoService;

class ArtigoTag
{
    private $db;
    public function __construct($db = null){ $this->db = $db ?: Database::getInstance(); }

    public function listar($somenteAtivas = true)
    {
        $where = $somenteAtivas ? "WHERE ATG_Ativo = 'S'" : '';
        return $this->db->query("SELECT ATG_ID, ATG_Nome, ATG_Slug, ATG_Ativo FROM artigos_tags {$where} ORDER BY ATG_Nome")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvar($nome, $id = 0)
    {
        $nome = trim(preg_replace('/[:|]+/', ' ', strip_tags((string) $nome)));
        if($nome === '' || mb_strlen($nome) > 80){ throw new \InvalidArgumentException('Informe uma tag válida.'); }
        $base = ArtigoConteudoService::slug($nome); $slug = $base; $n = 2;
        while(true){
            $check = $this->db->prepare("SELECT 1 FROM artigos_tags WHERE ATG_Slug = ?" . ($id ? ' AND ATG_ID <> ?' : '') . " LIMIT 1");
            $params = [$slug]; if($id) $params[] = (int) $id; $check->execute($params); if(!$check->fetchColumn()) break; $slug = $base . '-' . $n++;
        }
        if($id){ return $this->db->prepare("UPDATE artigos_tags SET ATG_Nome=?, ATG_Slug=? WHERE ATG_ID=?")->execute([$nome,$slug,(int)$id]); }
        return $this->db->prepare("INSERT INTO artigos_tags (ATG_Nome, ATG_Slug) VALUES (?, ?)")->execute([$nome, $slug]);
    }

    public function inativar($id)
    {
        return $this->db->prepare("UPDATE artigos_tags SET ATG_Ativo = 'N' WHERE ATG_ID = ?")->execute([(int) $id]);
    }
}
