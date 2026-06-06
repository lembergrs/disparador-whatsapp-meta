# CONTEXTO_PROJETO.md

## Projeto

Disparador WhatsApp Meta

## Repositório

https://github.com/lembergrs/disparador-whatsapp-meta

## Produção

https://disparador.rosemegamania.com

## Desenvolvimento

http://disparador.test

---

## Objetivo

Plataforma SaaS para envio de mensagens e gerenciamento de atendimento via WhatsApp Cloud API da Meta.

Arquitetura multiempresa:

* 1 App Meta
* N clientes
* N números WhatsApp por cliente
* Webhook único
* Embedded Signup

---

## Stack

### Backend

* PHP puro MVC
* MariaDB / MySQL

### Frontend

* AdminLTE 3.2
* jQuery
* Bootstrap 4
* DataTables

### Integrações

* WhatsApp Cloud API
* Meta Embedded Signup

### Ambientes

* Desenvolvimento: Laragon
* Produção: Hostinger

---

## Estrutura

app/
├── Controllers
├── Models
├── Views
├── Core

public/
├── assets/
│   ├── css
│   ├── js
│   └── img

config/

---

## Router

Padrão:

index.php?url=controller/metodo

Exemplos:

index.php?url=login
index.php?url=cliente
index.php?url=conversa
index.php?url=campanha

---

## Convenções

### Importante

Não realizar refatorações grandes sem necessidade.

Priorizar continuidade do projeto existente.

Manter compatibilidade com a estrutura atual.

---

### Requires

Sempre utilizar:

**DIR**

Evitar caminhos relativos como:

../app/Views

---

### Assets

Produção:

/public/assets

Exemplo:

<?= BASE_URL; ?>/public/assets/css/style.css

---

## Banco de Dados

### clientes

Controle dos clientes SaaS.

Campo de status:

CLI_StatusCadastro

Valores:

* pendente
* ativo
* inativo

---

### usuarios

Controle de acesso.

Perfis:

* admin
* cliente

USU_Ativo:

* S
* N

---

## Módulos concluídos

### Autenticação

* Login
* Logout
* Controle de sessão

### Clientes

* Cadastro
* Edição
* Inativação
* Reativação
* Aprovação

### Site Público

* Landing Page
* Cadastro Público

### WhatsApp

* Contas Meta
* Templates
* Disparo Manual
* Campanhas

### Listas

* Importação
* Duplicação
* Renomeação
* Inativação

### Conversas

* Caixa estilo WhatsApp
* Etiquetas
* Não lidas
* Filtros

### Dashboard

* Cliente
* Números WhatsApp

### Deploy

* GitHub Actions
* Hostinger

---

## Status Atual

Sistema publicado.

Banco funcionando.

Login funcionando.

Listas funcionando.

Conversas funcionando.

Deploy automatizado funcionando.

---

## Próximo Marco

Implementar Embedded Signup da Meta.
