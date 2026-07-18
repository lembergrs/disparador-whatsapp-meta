# Roteiro manual — gerenciamento administrativo de notificações

1. Aplicar as migrations `20260718_create_notificacoes.sql`, `20260718_create_notificacoes_configuracoes.sql` e `20260718_add_notificacoes_admin_indexes.sql` no ambiente seguro.
2. Entrar como administrador.
3. Abrir o menu **Notificações**.
4. Verificar o estado vazio, caso ainda não existam registros.
5. Cadastrar um cliente novo.
6. Confirmar a criação do registro `BOAS_VINDAS`.
7. Atualizar a listagem.
8. Filtrar pelo cliente.
9. Abrir os detalhes.
10. Confirmar assunto, destino, status e tentativa.
11. Realizar integração Meta do cliente.
12. Confirmar registro `META_CONECTADA`.
13. Simular falha SMTP em ambiente seguro, sem alterar SMTP de produção.
14. Verificar status de erro.
15. Clicar em **Reenviar**.
16. Confirmar atualização da tentativa.
17. Abrir **Configuração de canais**.
18. Desativar **E-mail** para `BOAS_VINDAS`.
19. Cadastrar outro cliente de teste.
20. Confirmar que o evento não chamou `EmailService`.
21. Reativar o canal.
22. Confirmar que WhatsApp, Interno, Push e SMS permanecem desabilitados como **Em breve**.
23. Confirmar que cliente comum não acessa o módulo, detalhes, reenvio ou configuração.
