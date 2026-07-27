<?php

namespace Models;

use Core\Database;
use PDO;
use Services\ArtigoConteudoService;

class ArtigoCategoria
{
    private $db;
    public function __construct($db = null){ $this->db = $db ?: Database::getInstance(); }

    public function listar($somenteAtivas = true)
    {
        $where = $somenteAtivas ? "WHERE ACG_Ativo = 'S'" : '';
        return $this->db->query("SELECT ACG_ID, ACG_Nome, ACG_Slug, ACG_Ativo FROM artigos_categorias {$where} ORDER BY ACG_Nome")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarPorSlug($slug)
    {
        $sql = $this->db->prepare("SELECT ACG_ID, ACG_Nome, ACG_Slug FROM artigos_categorias WHERE ACG_Slug = ? AND ACG_Ativo = 'S' LIMIT 1");
        $sql->execute([$slug]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buscar($id)
    {
        $sql = $this->db->prepare("SELECT ACG_ID, ACG_Nome, ACG_Slug FROM artigos_categorias WHERE ACG_ID = ? AND ACG_Ativo = 'S' LIMIT 1");
        $sql->execute([(int) $id]);
        return $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function salvar($nome, $id = 0)
    {
        $nome = trim(strip_tags((string) $nome));
        if($nome === '' || mb_strlen($nome) > 120){ throw new \InvalidArgumentException('Informe uma categoria válida.'); }
        $base = ArtigoConteudoService::slug($nome);
        $slug = $this->slugUnico($base, (int) $id ?: null);
        if($id){
            return $this->db->prepare("UPDATE artigos_categorias SET ACG_Nome=?, ACG_Slug=? WHERE ACG_ID=?")->execute([$nome, $slug, (int) $id]);
        }
        return $this->db->prepare("INSERT INTO artigos_categorias (ACG_Nome, ACG_Slug) VALUES (?, ?)")->execute([$nome, $slug]);
    }

    public function inativar($id)
    {
        return $this->db->prepare("UPDATE artigos_categorias SET ACG_Ativo = 'N' WHERE ACG_ID = ?")->execute([(int) $id]);
    }

    private function slugUnico($base, $ignorarId = null)
    {
        $slug = $base; $n = 2;
        while(true){
            $sql = $this->db->prepare("SELECT 1 FROM artigos_categorias WHERE ACG_Slug = ?" . ($ignorarId ? ' AND ACG_ID <> ?' : '') . " LIMIT 1");
            $params = [$slug]; if($ignorarId) $params[] = $ignorarId; $sql->execute($params);
            if(!$sql->fetchColumn()) return $slug; $slug = $base . '-' . $n++;
        }
    }
}
