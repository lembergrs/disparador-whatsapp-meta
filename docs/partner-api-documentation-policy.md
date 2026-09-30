# Manutenção da documentação da Partner API

Toda alteração que modifique o contrato público da Partner API deve atualizar `docs/partner-api-v1.md` no mesmo Pull Request.

Documentar, quando aplicável:

- método e endpoint;
- autenticação;
- parâmetros obrigatórios e opcionais;
- exemplo mínimo de request;
- exemplo de sucesso;
- principais erros e códigos HTTP;
- efeitos assíncronos/webhooks;
- diferenças entre produção e homologação.

A documentação deve ser suficiente para um desenvolvedor integrar sem precisar conhecer a estrutura interna do Disparador.net.

Detalhes internos, credenciais, tokens Meta, nomes de tabelas desnecessários ao contrato e segredos não devem ser publicados na documentação externa.
