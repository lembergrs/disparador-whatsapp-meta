# Relatório administrativo de Pricing Meta

O relatório `metaPricingReport` apresenta fatos de pricing retornados pela Meta, sem cálculo de custo, tarifa, preço ou impacto no consumo comercial dos planos. O acesso é exclusivo de administradores.

A fonte canônica é `conversa_mensagens`, relacionada a `conversas`, `meta_contas` e `clientes`. Entram nas métricas somente mensagens enviadas com `wamid`. A data lógica usa `MSG_EnviadaEm` e, quando ausente, `MSG_DataMensagem` como fallback.

## Deduplicação

Para cada `conversas.MTA_ID + conversa_mensagens.MSG_MetaMessageId`, a consulta escolhe uma única linha física de `conversa_mensagens`. A ordem canônica é: (1) maior completude entre categoria, billable, pricing model, pricing type, market e currency, somando um ponto por campo não `NULL`; (2) `MSG_AtualizadoEm` mais recente; (3) maior `MSG_ID`. `MSG_PricingBillable=0` conta como campo presente. Todos os campos exibidos, inclusive data, destino, tipo e status, vêm dessa mesma linha; não há consolidação independente de colunas.

A escolha usa `ROW_NUMBER()` particionado pela chave lógica. A janela processa somente `MSG_ID` e os campos necessários para ordenar; os dados de exibição são associados depois da escolha do ID canônico, evitando materializar linhas largas durante o `filesort`. A compatibilidade foi validada no MariaDB 11.8.8 utilizado pelo ambiente do projeto.

O período é aplicado somente depois da seleção canônica, sobre `COALESCE(MSG_EnviadaEm, MSG_DataMensagem)`. O intervalo é semiaberto: inclui a data inicial à meia-noite e vai até, sem incluir, a meia-noite seguinte à data final. Consultas são limitadas a 366 dias inclusivos; quando o pedido excede esse limite, a data inicial é ajustada, preservando a data final, e a tela informa o ajuste.

Essa estratégia não altera nem remove dados históricos. Enquanto duplicidades forem possíveis, qualquer relatório de pricing deve manter a mesma chave lógica de deduplicação.

## Significado de billable

- `1`: a Meta informou que a mensagem é faturável;
- `0`: a Meta informou que a mensagem não é faturável;
- `NULL`: a Meta não informou ou a informação não foi registrada — não significa gratuidade.

Categorias e pricing types são dinâmicos: valores futuros retornados pela Meta aparecem automaticamente nos resumos e filtros. O relatório não armazena tarifas e não apresenta valores monetários.

As agregações e a paginação da tabela detalhada são executadas no banco. Quando não existe busca textual, a DataTable reutiliza `recordsTotal` como `recordsFiltered`, evitando uma consulta idêntica. Em bases grandes, índices adicionais devem ser avaliados novamente com dados representativos; nenhum índice foi criado nesta etapa porque a janela estreita atingiu a meta local usando os índices existentes.
