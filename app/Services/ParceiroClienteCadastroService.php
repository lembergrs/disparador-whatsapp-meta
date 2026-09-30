<?php
namespace Services;

use Core\Database;
use Models\ParceiroConvite;

class ParceiroClienteCadastroService
{
    private $db;
    public function __construct($db=null){ $this->db=$db ?: Database::getInstance(); }

    public function cadastrar($token,array $d)
    {
        $convites=new ParceiroConvite($this->db);
        $convite=$convites->buscarPorToken($token);
        if(!$convite){ throw new \DomainException('Convite inválido, expirado ou já utilizado.'); }

        $email=strtolower(trim((string)($d['email']??'')));
        $cnpj=preg_replace('/\D/','',(string)($d['cnpj']??''));
        $telefone=preg_replace('/\D/','',(string)($d['telefone']??''));
        $cep=preg_replace('/\D/','',(string)($d['cep']??''));
        $ibge=preg_replace('/\D/','',(string)($d['codigo_ibge']??''));
        $uf=strtoupper(trim((string)($d['uf']??'')));
        $nome=trim((string)($d['nome']??''));
        $razao=trim((string)($d['razao_social']??''));
        $senha=(string)($d['senha']??'');

        if($nome===''||$razao===''||strlen($cep)!==8||strlen($ibge)!==7){ throw new \InvalidArgumentException('Preencha corretamente os dados empresariais e fiscais obrigatórios.'); }
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ throw new \InvalidArgumentException('Informe um e-mail válido.'); }
        if(strlen($telefone)!==10&&strlen($telefone)!==11){ throw new \InvalidArgumentException('Informe um WhatsApp / telefone válido com DDD.'); }
        if(strlen($cnpj)!==14||!DocumentoFiscalValidator::valido($cnpj)){ throw new \InvalidArgumentException('Informe um CNPJ válido.'); }
        if(!SenhaForteValidator::forte($senha)){ throw new \InvalidArgumentException(SenhaForteValidator::mensagem()); }
        foreach(['logradouro','numero','bairro','municipio'] as $campo){ if(trim((string)($d[$campo]??''))===''){ throw new \InvalidArgumentException('Preencha o endereço fiscal completo.'); } }
        if(!preg_match('/^[A-Z]{2}$/',$uf)){ throw new \InvalidArgumentException('Informe uma UF válida.'); }

        $q=$this->db->prepare("SELECT USU_ID FROM usuarios WHERE USU_Email=? LIMIT 1");$q->execute([$email]);if($q->fetchColumn()){throw new \DomainException('Já existe uma conta cadastrada com este e-mail.');}
        $q=$this->db->prepare("SELECT CLI_ID FROM clientes WHERE CLI_CPF_CNPJ=? LIMIT 1");$q->execute([$cnpj]);if($q->fetchColumn()){throw new \DomainException('Este CNPJ já possui cadastro no Disparador.net.');}

        $this->db->beginTransaction();
        try{
            $sql=$this->db->prepare("INSERT INTO clientes
            (CLI_TipoConta,CLI_TipoPessoa,CLI_CPF_CNPJ,CLI_Nome,CLI_RazaoSocial,CLI_NomeFantasia,CLI_Email,CLI_Telefone,CLI_ValorMensalidade,CLI_StatusPagamento,CLI_StatusCadastro,CLI_Observacoes,CLI_Ativo,
             CLI_NFSe_CNPJ,CLI_NFSe_RazaoSocial,CLI_NFSe_CEP,CLI_NFSe_Logradouro,CLI_NFSe_Numero,CLI_NFSe_Complemento,CLI_NFSe_Bairro,CLI_NFSe_Municipio,CLI_NFSe_UF,CLI_NFSe_CodigoIBGE,CLI_NFSe_Telefone,CLI_NFSe_Email)
            VALUES ('cliente_partner_vinculado','PJ',?,?,?,?,?,?,0.00,'pendente','ativo','Cliente vinculado cadastrado por convite Partner.','S',?,?,?,?,?,?,?,?,?,?,?,?)");
            $sql->execute([$cnpj,$nome,$razao,trim((string)($d['nome_fantasia']??''))?:$nome,$email,$telefone,$cnpj,$razao,$cep,trim($d['logradouro']),trim($d['numero']),trim((string)($d['complemento']??'')),trim($d['bairro']),trim($d['municipio']),$uf,$ibge,$telefone,$email]);
            $clienteId=(int)$this->db->lastInsertId();
            $u=$this->db->prepare("INSERT INTO usuarios (CLI_ID,USU_Nome,USU_Email,USU_Senha,USU_Nivel,USU_Ativo) VALUES (?,?,?,?, 'cliente_admin','S')");
            $u->execute([$clienteId,trim((string)($d['responsavel']??$nome)),$email,password_hash($senha,PASSWORD_DEFAULT)]);
            $convites->aceitar((int)$convite['PCI_ID'],$clienteId);
            $this->db->commit();
            return $clienteId;
        }catch(\Throwable $e){ if($this->db->inTransaction()){$this->db->rollBack();} throw $e; }
    }
}
