# Revisão da Política de Cancelamento e Reembolso

Data da revisão: 14/07/2026.

## 1. Situação atual após a implementação

O Disparador.net passa a contar com uma página pública específica para a Política de Cancelamento e Reembolso, integrada ao controller público, aos Termos de Uso, ao cadastro e aos links institucionais da home.

## 2. Páginas públicas relacionadas

| Página | Rota | Controller | View | Observação |
|---|---|---|---|---|
| Home | `site` | `SiteController::index()` | `app/Views/site/home.php` | Contém links institucionais no rodapé. |
| Cadastro | `site/cadastro` | `SiteController::cadastro()` | `app/Views/site/cadastro.php` | Checkbox de aceite referencia Termos, Privacidade e Cancelamento. |
| Termos de Uso | `site/termosUso` | `SiteController::termosUso()` | `app/Views/site/termos_uso.php` | Inclui seção resumida de cancelamento/reembolso e link para a política detalhada. |
| Política de Privacidade | `site/politicaPrivacidade` | `SiteController::politicaPrivacidade()` | `app/Views/site/politica_privacidade.php` | Mantida sem alteração de conteúdo material nesta sprint. |
| Política de Cancelamento e Reembolso | `site/politicaCancelamento` | `SiteController::politicaCancelamento()` | `app/Views/site/politica_cancelamento.php` | Nova página pública da política. |

Não foram criadas páginas Sobre ou Contato nesta sprint.

## 3. Checkbox de aceite no cadastro

O checkbox existente foi mantido e seu texto passa a indicar concordância com:

- Termos de Uso;
- Política de Privacidade;
- Política de Cancelamento e Reembolso.

Os links abrem em nova aba, preservando o fluxo de preenchimento do cadastro.

## 4. Validação backend do aceite

Além do atributo `required` no frontend e do controle visual do botão de cadastro, o backend passa a validar obrigatoriamente `aceiteTermos` antes de concluir o cadastro. Se o campo não for enviado, o cadastro é recusado e o usuário retorna ao formulário com mensagem de erro.

Não foi criada auditoria de aceite nesta sprint porque não há campos prontos no banco para essa finalidade e a sprint não prevê migration.

## 5. Política adotada

A política implementada define que:

1. o cliente pode solicitar cancelamento a qualquer momento;
2. sem prejuízo dos direitos assegurados pela legislação aplicável, inclusive o direito de arrependimento quando cabível, a RL2 Net adota política comercial de reembolso integral do primeiro pagamento quando não houver conexão bem-sucedida de número WhatsApp, envio de mensagens ou utilização efetiva;
3. após utilização efetiva, o cancelamento interrompe cobranças futuras e o acesso pode permanecer até o fim do período pago;
4. os pedidos são analisados conforme a legislação aplicável e as circunstâncias da contratação, sem prejuízo dos direitos legalmente assegurados ao consumidor;
5. o período de avaliação inicia somente após a conexão operacional do primeiro número WhatsApp;
6. solicitações devem ser analisadas em até 2 dias úteis;
7. estornos aprovados devem ocorrer preferencialmente pelo mesmo meio de pagamento, sujeitos aos prazos financeiros;
8. casos excepcionais podem ser analisados individualmente.

## 6. Itens não implementados nesta sprint

- Botão de cancelamento dentro do painel;
- automação de estorno no Asaas;
- tabela de solicitações de cancelamento;
- migration de auditoria do aceite;
- encerramento automático de conta;
- página Sobre;
- página Contato.

## 7. Lacunas futuras

- Criar auditoria persistente de aceite com versão, data/hora, IP, user agent, cliente e usuário;
- criar fluxo administrativo estruturado para solicitação, análise e decisão de cancelamento/reembolso;
- automatizar cancelamento/estorno no Asaas quando a operação decidir seguir por esse caminho;
- criar indicadores objetivos consolidados de utilização efetiva, considerando conexão de número WhatsApp e envio de mensagens.

## 8. Arquivos afetados nesta sprint

- `app/Controllers/SiteController.php`;
- `app/Views/site/politica_cancelamento.php`;
- `app/Views/site/termos_uso.php`;
- `app/Views/site/cadastro.php`;
- `app/Views/site/home.php`;
- `docs/politica-cancelamento-reembolso.md`;
- `docs/revisao-politica-cancelamento.md`;
- `tests/SitePoliticaCancelamentoTest.php`.
