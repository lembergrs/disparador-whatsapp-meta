<?php

namespace Models;

use Core\Database;
use PDO;
use Services\ArtigoConteudoService;

class Artigo
{
    private $db;
    public function __construct($db = null){ $this->db = $db ?: Database::getInstance(); }

    private function selectBase()
    {
        return "SELECT a.*, c.ACG_Nome, c.ACG_Slug, u.USU_Nome AS AutorNome,
            GROUP_CONCAT(DISTINCT CONCAT(t.ATG_ID, ':', t.ATG_Nome, ':', t.ATG_Slug) ORDER BY t.ATG_Nome SEPARATOR '||') AS TagsAgrupadas
            FROM artigos a
            INNER JOIN artigos_categorias c ON c.ACG_ID = a.ACG_ID
            INNER JOIN usuarios u ON u.USU_ID = a.USU_Autor_ID
            LEFT JOIN artigos_tags_relacao r ON r.ART_ID = a.ART_ID
            LEFT JOIN artigos_tags t ON t.ATG_ID = r.ATG_ID AND t.ATG_Ativo = 'S'";
    }

    public function listarAdmin(array $filtros = [])
    {
        $where = ["a.ART_Ativo = 'S'"]; $params = [];
        if(in_array($filtros['status'] ?? '', ['rascunho','publicado'], true)){ $where[] = 'a.ART_Status = ?'; $params[] = $filtros['status']; }
        if(!empty($filtros['categoria'])){ $where[] = 'a.ACG_ID = ?'; $params[] = (int) $filtros['categoria']; }
        if(trim((string) ($filtros['titulo'] ?? '')) !== ''){ $where[] = 'a.ART_Titulo LIKE ?'; $params[] = '%' . trim($filtros['titulo']) . '%'; }
        $sql = $this->db->prepare($this->selectBase() . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY a.ART_ID ORDER BY a.ART_AtualizadoEm DESC');
        $sql->execute($params); return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarAdmin($id)
    {
        $sql = $this->db->prepare($this->selectBase() . " WHERE a.ART_ID = ? AND a.ART_Ativo = 'S' GROUP BY a.ART_ID LIMIT 1");
        $sql->execute([(int) $id]); return $this->normalizarTags($sql->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    public function buscarPublicadoPorSlug($slug)
    {
        $sql = $this->db->prepare($this->selectBase() . " WHERE a.ART_Slug = ? AND a.ART_Ativo = 'S' AND a.ART_Status = 'publicado' AND a.ART_DataPublicacao <= NOW() AND c.ACG_Ativo = 'S' GROUP BY a.ART_ID LIMIT 1");
        $sql->execute([$slug]); return $this->normalizarTags($sql->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    public function listarPublicados($pagina, $porPagina, $busca = '', $categoriaId = null)
    {
        $where = ["a.ART_Ativo = 'S'", "a.ART_Status = 'publicado'", 'a.ART_DataPublicacao <= NOW()']; $params = [];
        if($busca !== ''){ $where[] = '(a.ART_Titulo LIKE ? OR a.ART_Resumo LIKE ? OR a.ART_Conteudo LIKE ?)'; $termo = '%' . $busca . '%'; array_push($params, $termo, $termo, $termo); }
        if($categoriaId){ $where[] = 'a.ACG_ID = ?'; $params[] = (int) $categoriaId; }
        $whereSql = implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM artigos a INNER JOIN artigos_categorias c ON c.ACG_ID = a.ACG_ID AND c.ACG_Ativo = 'S' WHERE {$whereSql}"); $count->execute($params); $total = (int) $count->fetchColumn();
        $offset = max(0, ($pagina - 1) * $porPagina);
        $sql = $this->db->prepare("SELECT a.ART_ID, a.ART_Titulo, a.ART_Slug, a.ART_Resumo, a.ART_ImagemDestaque, a.ART_Destaque, a.ART_DataPublicacao, a.ART_AtualizadoEm, c.ACG_Nome, c.ACG_Slug FROM artigos a INNER JOIN artigos_categorias c ON c.ACG_ID = a.ACG_ID AND c.ACG_Ativo = 'S' WHERE {$whereSql} ORDER BY a.ART_Destaque DESC, a.ART_DataPublicacao DESC LIMIT {$porPagina} OFFSET {$offset}");
        $sql->execute($params); return ['itens'=>$sql->fetchAll(PDO::FETCH_ASSOC), 'total'=>$total];
    }

    public function navegacaoPublica(array $artigo)
    {
        $elegivel = "a.ART_Ativo = 'S' AND a.ART_Status = 'publicado' AND a.ART_DataPublicacao <= NOW() AND c.ACG_Ativo = 'S'";
        $sql = $this->db->prepare("(SELECT 'anterior' AS Direcao, a.ART_ID, a.ART_Titulo, a.ART_Slug, a.ART_DataPublicacao FROM artigos a INNER JOIN artigos_categorias c ON c.ACG_ID = a.ACG_ID WHERE {$elegivel} AND (a.ART_DataPublicacao < ? OR (a.ART_DataPublicacao = ? AND a.ART_ID < ?)) ORDER BY a.ART_DataPublicacao DESC, a.ART_ID DESC LIMIT 1) UNION ALL (SELECT 'proximo' AS Direcao, a.ART_ID, a.ART_Titulo, a.ART_Slug, a.ART_DataPublicacao FROM artigos a INNER JOIN artigos_categorias c ON c.ACG_ID = a.ACG_ID WHERE {$elegivel} AND (a.ART_DataPublicacao > ? OR (a.ART_DataPublicacao = ? AND a.ART_ID > ?)) ORDER BY a.ART_DataPublicacao ASC, a.ART_ID ASC LIMIT 1)");
        $params = [$artigo['ART_DataPublicacao'], $artigo['ART_DataPublicacao'], (int) $artigo['ART_ID'], $artigo['ART_DataPublicacao'], $artigo['ART_DataPublicacao'], (int) $artigo['ART_ID']];
        $sql->execute($params); $navegacao = ['anterior'=>null, 'proximo'=>null];
        foreach($sql->fetchAll(PDO::FETCH_ASSOC) as $item){ $navegacao[$item['Direcao']] = $item; }
        return $navegacao;
    }

    public function relacionados(array $artigo, $limite = 3, array $excluirIds = [])
    {
        $limite = max(1, min(3, (int) $limite));
        $ids = array_values(array_unique(array_filter(array_map('intval', array_merge([(int) $artigo['ART_ID']], $excluirIds)))));
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $sql = $this->db->prepare("SELECT a.ART_ID, a.ART_Titulo, a.ART_Slug, a.ART_Resumo, a.ART_ImagemDestaque, a.ART_DataPublicacao FROM artigos a INNER JOIN artigos_categorias c ON c.ACG_ID = a.ACG_ID AND c.ACG_Ativo = 'S' WHERE a.ART_ID NOT IN ({$marcadores}) AND a.ART_Ativo = 'S' AND a.ART_Status = 'publicado' AND a.ART_DataPublicacao <= NOW() ORDER BY (a.ACG_ID = ?) DESC, a.ART_DataPublicacao DESC LIMIT {$limite}");
        $sql->execute(array_merge($ids, [(int) $artigo['ACG_ID']])); return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvar(array $dados, array $tagIds)
    {
        $this->db->beginTransaction();
        try{
            $id = (int) ($dados['id'] ?? 0);
            $slug = $this->slugUnico($dados['slug'] ?: $dados['titulo'], $id ?: null);
            $campos = [$dados['categoria_id'], $dados['autor_id'], $dados['titulo'], $slug, $dados['resumo'], $dados['conteudo'], $dados['imagem'], $dados['status'], $dados['destaque'], $dados['data_publicacao'], $dados['meta_title'], $dados['meta_description'], $dados['url_canonica']];
            if($id){
                $sql = $this->db->prepare("UPDATE artigos SET ACG_ID=?, USU_Autor_ID=?, ART_Titulo=?, ART_Slug=?, ART_Resumo=?, ART_Conteudo=?, ART_ImagemDestaque=?, ART_Status=?, ART_Destaque=?, ART_DataPublicacao=?, ART_MetaTitle=?, ART_MetaDescription=?, ART_UrlCanonica=? WHERE ART_ID=? AND ART_Ativo='S'");
                $campos[] = $id; $sql->execute($campos);
            }else{
                $sql = $this->db->prepare("INSERT INTO artigos (ACG_ID, USU_Autor_ID, ART_Titulo, ART_Slug, ART_Resumo, ART_Conteudo, ART_ImagemDestaque, ART_Status, ART_Destaque, ART_DataPublicacao, ART_MetaTitle, ART_MetaDescription, ART_UrlCanonica) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $sql->execute($campos); $id = (int) $this->db->lastInsertId();
            }
            $this->db->prepare('DELETE FROM artigos_tags_relacao WHERE ART_ID = ?')->execute([$id]);
            $rel = $this->db->prepare('INSERT INTO artigos_tags_relacao (ART_ID, ATG_ID) SELECT ?, ATG_ID FROM artigos_tags WHERE ATG_ID = ? AND ATG_Ativo = \'S\'');
            foreach(array_unique(array_map('intval', $tagIds)) as $tagId){ if($tagId > 0) $rel->execute([$id, $tagId]); }
            $this->db->commit(); return $id;
        }catch(\Throwable $e){ if($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function alterarStatus($id, $status)
    {
        if(!in_array($status, ['rascunho','publicado'], true)) throw new \InvalidArgumentException('Status inválido.');
        $sql = $this->db->prepare("UPDATE artigos SET ART_Status=?, ART_DataPublicacao=CASE WHEN ?='publicado' THEN COALESCE(ART_DataPublicacao, NOW()) ELSE ART_DataPublicacao END WHERE ART_ID=? AND ART_Ativo='S'");
        return $sql->execute([$status, $status, (int) $id]);
    }

    public function inativar($id){ return $this->db->prepare("UPDATE artigos SET ART_Ativo='N', ART_Status='rascunho' WHERE ART_ID=?")->execute([(int) $id]); }

    public function slugsPublicados()
    {
        return $this->db->query("SELECT ART_Slug, ART_AtualizadoEm FROM artigos WHERE ART_Ativo='S' AND ART_Status='publicado' AND ART_DataPublicacao <= NOW() ORDER BY ART_DataPublicacao DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function slugUnico($texto, $ignorarId = null)
    {
        $base = ArtigoConteudoService::slug($texto); $slug = $base; $n = 2;
        if(in_array($base, ['index','categoria'], true)){ $base = 'artigo-' . $base; $slug = $base; }
        while(true){
            $sql = $this->db->prepare('SELECT 1 FROM artigos WHERE ART_Slug=?' . ($ignorarId ? ' AND ART_ID<>?' : '') . ' LIMIT 1');
            $params = [$slug]; if($ignorarId) $params[] = $ignorarId; $sql->execute($params);
            if(!$sql->fetchColumn()) return $slug; $slug = $base . '-' . $n++;
        }
    }

    private function normalizarTags($artigo)
    {
        if(!$artigo) return null; $artigo['tags'] = [];
        foreach(array_filter(explode('||', (string) ($artigo['TagsAgrupadas'] ?? ''))) as $tag){
            [$id,$nome,$slug] = array_pad(explode(':', $tag, 3), 3, ''); $artigo['tags'][] = ['ATG_ID'=>(int)$id,'ATG_Nome'=>$nome,'ATG_Slug'=>$slug];
        }
        unset($artigo['TagsAgrupadas']); return $artigo;
    }
}
