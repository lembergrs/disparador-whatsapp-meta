# Confirmação da configuração de pagamento da Meta

O Disparador.net não possui permissão BSP para consultar diretamente o método de pagamento da WABA e não pretende adotar o modelo BSP ou credit sharing.

A RL2 Net cobra somente a mensalidade do Disparador.net. As tarifas do WhatsApp Business Platform são pagas diretamente pelo cliente à Meta. O sistema apenas orienta a configuração e registra a confirmação declarada pelo cliente; não valida tecnicamente o pagamento.

## Estados

- `NULL`: conta legada que ainda não passou pelo fluxo declaratório;
- `pendente_confirmacao`: conta nova orientada a configurar diretamente na Meta;
- `confirmado_cliente`: o cliente declarou que concluiu a configuração na Meta.

`confirmado_cliente` não significa confirmação ou validação técnica pela Meta.

Após um Embedded Signup que cria uma conta nova, o estado passa a `pendente_confirmacao`. Uma reconexão da mesma conta preserva o estado existente e nunca remove uma confirmação anterior. Na tela de configuração, o cliente acessa o WhatsApp Manager oficial e usa a ação POST protegida por CSRF “Já configurei”. A ação valida `MTA_ID + CLI_ID` e grava apenas `confirmado_cliente` e `MTA_PagamentoMetaConfirmadoEm`.

`MTA_PagamentoMetaConfirmadoEm` representa a última vez em que o cliente declarou “Já configurei”. Cada confirmação legítima atualiza o timestamp; chamadas repetidas são consideradas sucesso mesmo quando ocorrem no mesmo segundo e o MariaDB não contabiliza uma linha modificada. Reconexões não alteram esse timestamp.

O destino oficial é `https://business.facebook.com/wa/manage/home/`, sem deep link baseado na WABA. Nenhum identificador financeiro, cartão, billing ou resposta financeira é consultado ou armazenado.

No futuro, um erro de envio comprovadamente relacionado à cobrança poderá reabrir o estado como pendente em uma branch específica. Essa automação não faz parte desta implementação.

A migration `20260820_add_meta_payment_status.sql` é reexecutável e deve ser aplicada em produção antes do código.
