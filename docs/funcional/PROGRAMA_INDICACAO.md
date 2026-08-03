# Especificação funcional — Programa de Indicação

**Status:** especificação para aprovação e implementação futura

**Escopo desta versão:** documentação funcional; nenhuma entidade, tabela, rota, tela, job, notificação ou integração descrita aqui existe por força deste documento.

**Fonte de verdade:** regras aprovadas neste documento prevalecem sobre exemplos; itens em **Decisão pendente** não podem ser implementados como fatos sem aprovação.

## 1. Finalidade e princípios

O futuro Programa de Indicação do Disparador.net será um benefício promocional de fidelidade: clientes elegíveis indicam empresas reais e recebem créditos percentuais para cobranças futuras. O documento orienta produto, cadastro, financeiro, tarefas agendadas, notificações, interfaces, segurança, testes e regulamento público.

Princípios obrigatórios:

1. cliente indicado e não indicado recebem o mesmo produto, trial e benefício inicial;
2. cadastro sozinho não gera crédito;
3. confirmação financeira e permanência por sete dias completos antecedem a liberação;
4. crédito é percentual promocional, não dinheiro;
5. somente um crédito pode afetar cada cobrança elegível;
6. o valor do plano e da assinatura não é reescrito pelo benefício;
7. reserva do crédito e utilização definitiva são fatos diferentes;
8. histórico financeiro e administrativo nunca é apagado;
9. operações concorrentes precisam ser idempotentes e transacionais;
10. decisões pendentes devem permanecer configuráveis ou bloqueadas até aprovação.

Texto funcional obrigatório:

> **Os créditos de indicação possuem natureza exclusivamente promocional e destinam-se apenas à concessão de desconto em cobranças futuras elegíveis do Disparador.net.**

## 2. Diagnóstico do sistema atual

### 2.1 Nomenclatura e domínio existentes

A implementação futura deve respeitar os nomes reais abaixo:

| Conceito | Implementação atual | Identificadores/estados observados |
|---|---|---|
| Cliente | `Models\Cliente`, tabela `clientes` | `CLI_ID`; `CLI_StatusCadastro` usa `pendente`, `ativo`, `inativo` e o workflow financeiro também usa `suspenso`; `CLI_StatusPagamento` usa `pendente` e `pago`; `CLI_Ativo` usa `S`/`N`; `CLI_DataLiberacao` inicia o trial |
| Usuário | tabela `usuarios` | `USU_ID`, vínculo por `CLI_ID`; cadastro público cria usuário `cliente_admin` |
| Plano | `Models\Plano`, tabela `planos` | `PLA_ID`, `PLA_ValorMensal`, `PLA_ValorTrimestral`, `PLA_ValorSemestral`, `PLA_ValorAnual`, `PLA_ValorMensagemExcedente`; `PLA_Ativo` usa `S`/`N` |
| Assinatura | `Models\Assinatura`, tabela `assinaturas` | `ASS_ID`, `ASS_Ciclo`, `ASS_Valor`; estados `pendente`, `ativa`, `vencida`, `cancelada` |
| Cobrança | `Models\Cobranca`, tabela `cobrancas` | `COB_ID`, `COB_Valor`, `COB_DataVencimento`, `COB_DataPagamento`; estados `pendente`, `pago`, `vencido`, `cancelado`; tipo `mensalidade` quando a coluna opcional existe |
| Provedor | `Services\AsaasService` | cliente e cobrança externos, link/Pix, status e eventos do Asaas |
| Orquestração financeira | `Services\FinanceiroWorkflowService` | contratação, recorrência, webhook, pagamento manual, vencimento, cancelamento, reativação e troca de plano |
| Transação | `Models\FinanceiroTransacao` | encapsula operações locais atômicas do workflow |
| Notificações | `Services\NotificacaoService`, `EventoNotificacao`, `CanalNotificacao` | canais existentes: `email`, `whatsapp`, `interno`, `push`, `sms`; os canais efetivos dependem da configuração |
| Processamento | `worker-daemon.php`, `worker.php`, `processar_vencimentos.php`, `processar_notificacoes_onboarding.php` | não existe ainda uma infraestrutura genérica homologada de tarefas agendadas para o programa |

Os nomes de entidades e campos sugeridos mais adiante são **propostas conceituais**, não objetos existentes.

### 2.2 Cadastro, trial e primeiro pagamento

O cadastro público (`SiteController::salvar`) cria `clientes` e `usuarios` na mesma transação. O trial não nasce no cadastro: `Cliente::iniciarTrialSePendente()` preenche `CLI_DataLiberacao` apenas para cliente ativo, com pagamento pendente, depois da conexão Meta válida. A regra do programa deve reutilizar esse trial sem criar um fluxo paralelo.

A contratação usa `FinanceiroWorkflowService::contratarPlano()`: valida o ciclo e o plano, calcula o valor, cria/atualiza assinatura pendente, cria cobrança local pendente e depois integra com o Asaas. A confirmação ocorre por:

- `confirmarPagamentoManual()`, no administrativo; ou
- `processarPagamentoWebhook()`, que traduz `PAYMENT_RECEIVED` e `PAYMENT_CONFIRMED` para `pago`.

Ambos ativam a assinatura e atualizam o cliente após persistência. Esse ponto comum de sucesso deverá futuramente liberar o código no primeiro pagamento e iniciar a janela da indicação, sem depender apenas do webhook.

**Constatação importante:** o benefício inicial de 50% é regra aprovada deste programa, mas não foi identificado como cálculo central explícito no fluxo financeiro atual. Sua implementação futura exigirá modelagem auditável; este documento não presume que já exista.

### 2.3 Ciclos e valores

`Plano::CICLOS` define `mensal = 1`, `trimestral = 3`, `semestral = 6` e `anual = 12`. `Plano::valorPorCiclo()` usa o campo explícito do ciclo e, na ausência, multiplica `PLA_ValorMensal` pela quantidade de meses. Assinaturas preservam `ASS_Ciclo` e `ASS_Valor`; recorrência avança a próxima data pelo número de meses do ciclo.

Para o programa:

- **valor total do ciclo:** deve continuar vindo de `Plano::valorPorCiclo()`/snapshot da assinatura conforme a política financeira vigente;
- **mensalidade equivalente para o crédito:** recomenda-se uma única função de domínio baseada em `PLA_ValorMensal` (com fallback formalmente definido), e não dividir automaticamente o valor promocional do ciclo por 3, 6 ou 12;
- a mesma fonte deve alimentar cadastro de plano, assinatura, cobrança, página comercial e programa;
- valores monetários devem seguir o arredondamento financeiro do projeto. Como o domínio atual normaliza para `float`, a implementação deve centralizar arredondamento em duas casas ou, preferencialmente, operar em centavos internamente, sem espalhar regras.

### 2.4 Recorrência, inadimplência, cancelamento e reativação

`gerarCobrancasRecorrentes()` evita duplicidade por assinatura, competência e tipo, cria cobrança local e integra o provedor fora da transação local. `processarVencimentos()` identifica cobranças pendentes vencidas, marca cobrança/assinatura e bloqueia financeiramente o cliente conforme o fluxo atual. Cancelamento encerra assinaturas e cobranças pendentes; reativação cria ou reconcilia cobrança e assinatura pendentes. Troca de plano usa o workflow oficial.

O crédito deverá observar esses fluxos; não poderá fazer SQL no workflow nem alteração financeira direta em controller. A integração com desconto é uma das partes de maior risco do módulo.

### 2.5 Asaas e limitação crítica da pontualidade

Hoje o Asaas recebe `COB_Valor` e vencimento, e o webhook informa confirmação, vencimento, cancelamento e reembolso. Ainda precisa ser homologada a forma de:

1. mostrar uma cobrança com desconto reservado antes do vencimento;
2. cobrar o valor integral se o pagamento ocorrer depois do vencimento;
3. devolver o crédito à fila sem permitir que a mesma cobrança atrasada mantenha o desconto.

A solução não pode ser presumida. Deve avaliar recursos nativos do Asaas (desconto condicionado à data, substituição/cancelamento ou outra capacidade suportada), persistir a decisão local e evitar edição manual inconsistente.

## 3. Participação e benefício inicial — regras aprovadas

### 3.1 Benefício de entrada

Todo novo cliente, indicado ou não, recebe 50% de desconto no primeiro pagamento. A partir da segunda cobrança elegível, aplica-se o valor normal, salvo crédito de indicação ou promoção compatível futura.

O desconto inicial:

- não depende de indicação;
- ocorre antes da participação ativa no programa;
- não acumula com crédito de indicação;
- não acumula com promoção incompatível;
- não altera o preço cadastrado do plano;
- deve ser registrado na cobrança com origem, base, percentual e valor.

Prioridade aprovada:

1. primeira cobrança: desconto inicial de 50%;
2. cobranças seguintes: no máximo um crédito de indicação elegível;
3. excedentes e adicionais: sempre integrais.

Recomenda-se um serviço/política central de descontos, compartilhado por contratação e recorrência.

### 3.2 Liberação do programa e do código

Cadastro e trial não liberam código. O código só pode ser criado/liberado depois da confirmação definitiva do **primeiro pagamento** pelo fluxo financeiro, seja por webhook válido do provedor ou lançamento manual autorizado.

Antes disso, a área do cliente deve mostrar:

> O Programa de Indicação será disponibilizado após a confirmação do seu primeiro pagamento.

Não deve existir código válido compartilhável antes da confirmação. O evento precisa ser idempotente: reprocessamento do mesmo pagamento não pode criar outro código.

## 4. Campanhas de indicação — regras aprovadas

O programa será organizado por campanhas configuráveis, e não por percentual global fixo. A primeira campanha usa **15%**.

Cada campanha deverá conceitualmente preservar:

- nome e descrição interna;
- percentual do crédito;
- início e término opcional;
- estado ativo/inativo;
- versão ou snapshot das regras vigentes;
- datas de criação/atualização;
- administrador responsável.

O administrador poderá criar, editar informações futuras, ativar, inativar e consultar histórico. Alteração não pode mudar créditos ou indicações históricos.

Ao inativar uma campanha:

- cessam novas participações, códigos e indicações nela;
- site e área do cliente deixam de divulgá-la como disponível;
- créditos liberados continuam válidos;
- créditos reservados continuam no fluxo;
- cancelamento do indicador ou fraude ainda pode expirar/cancelar créditos;
- histórico permanece.

**Decisão pendente:** indicações registradas e ainda em confirmação na inativação. Recomendação: manter regras e percentual da campanha vinculada na data do cadastro.

## 5. Código e link

### 5.1 Propriedades

O código será único, automático, não editável, estável após mudanças cadastrais, digitável e compartilhável. A validação deverá normalizar para maiúsculas e ser case-insensitive, com unicidade sobre a forma normalizada.

Formato desejado: `PREFIXO-SUFIXO`, por exemplo `ROD-8XJ4P`, `LEM-Q72AZ`, `RL2-X8M4K`.

### 5.2 Algoritmo conceitual

**Prefixo determinístico:**

1. escolher, nessa ordem, nome fantasia não vazio, razão social não vazia, nome do cliente; essa ordem é proposta para estabilidade comercial;
2. transliterar acentos, converter para maiúsculas e remover tudo que não seja `A-Z` ou `0-9`;
3. separar palavras antes da remoção e ignorar sufixos societários comuns apenas se houver lista central versionada (`LTDA`, `ME`, `EPP` etc.);
4. para três ou mais palavras relevantes, usar a inicial das três primeiras;
5. para duas palavras, usar duas iniciais e completar com o próximo caractere alfanumérico disponível;
6. para uma palavra, usar os três primeiros caracteres;
7. para nome curto, completar com caracteres determinísticos da forma normalizada;
8. se não houver três caracteres úteis, usar prefixo neutro, por exemplo `DSP`;
9. o prefixo é congelado na criação e nunca recalculado.

**Sufixo aleatório:**

- cinco caracteres gerados por fonte criptograficamente segura;
- alfabeto sem ambíguos, por exemplo `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (exclui `I`, `O`, `0`, `1`);
- validar unicidade em índice único da forma normalizada;
- em colisão, gerar novo sufixo com limite de tentativas e falha controlada;
- nunca usar `CLI_ID`, CPF/CNPJ, telefone, timestamp previsível ou sequência.

A aleatoriedade do sufixo evita enumeração; o prefixo serve apenas à legibilidade.

### 5.3 Estados do código

| Estado conceitual | Significado | Novas indicações |
|---|---|---|
| `nao_liberado` | primeiro pagamento ainda não confirmado | não |
| `ativo` | campanha e cliente elegíveis | sim |
| `suspenso` | impedimento reversível | não enquanto suspenso |
| `cancelado` | cancelamento/inatividade definitiva ou encerramento | não; terminal para compartilhamento |

Cancelado, inativo, definitivamente suspenso ou sem contrato válido não compartilha código. Inadimplência temporária bloqueia uso de créditos, mas o efeito sobre **novas indicações** permanece pendente.

### 5.4 Link

URL conceitual: `https://disparador.net/cadastro?ref=ROD-8XJ4P`.

O link:

- preenche o campo opcional `Código de indicação`;
- preserva `ref` e valor normalizado em erros do formulário;
- permite edição manual até o envio, salvo decisão futura;
- não expõe IDs;
- não cria vínculo antes da conclusão transacional do cadastro;
- não deve usar cookie como fonte definitiva sem revalidação backend.

## 6. Cadastro indicado

O campo é opcional; pode receber entrada manual ou `ref`. Se informado:

1. normalizar e consultar no backend;
2. exigir código ativo e campanha elegível;
3. impedir autoindicação e duplicidade;
4. apresentar erro claro se inválido/inativo/inelegível;
5. não ignorar silenciosamente;
6. registrar indicador, indicado, campanha e snapshot da regra na transação do cadastro, sempre que tecnicamente possível;
7. limitar o indicado a um único indicador por restrição de domínio/banco;
8. tornar o vínculo imutável após commit.

**Decisão funcional adotada nesta especificação:** código informado e inválido impede a conclusão do cadastro até ser corrigido ou removido. Isso evita atribuição inesperada e atende à recomendação aprovada. Ausência de código nunca impede cadastro comum.

O indicado segue exatamente o mesmo trial, desconto inicial de 50%, cobrança normal subsequente e liberação do próprio código após primeiro pagamento.

## 7. Indicação e crédito

### 7.1 Quando nascem

Cadastro válido cria a indicação, não o desconto utilizável. Após o primeiro pagamento confirmado do indicado:

- registrar pagamento de qualificação;
- passar a indicação para confirmação;
- programar liberação para pagamento + sete dias completos;
- criar crédito pendente/em confirmação, ou criá-lo na liberação, conforme modelagem escolhida, sempre com idempotência.

Após sete dias, a tarefa revalida: cadastro/ativação, pagamento ainda válido, indicado ativo, ausência de cancelamento, estorno, reembolso, chargeback, fraude, duplicidade ou autoindicação. Só então aprova a indicação e libera um crédito ao indicador.

### 7.2 Percentual e campanha

Cada crédito preserva:

- campanha de origem;
- indicação de origem;
- percentual concedido;
- data de liberação;
- estado.

O percentual não é consultado apenas na campanha atual. Editar/inativar campanha não altera crédito histórico. O momento exato de congelamento é pendente; recomenda-se vincular a indicação e congelar a regra da campanha no cadastro.

O crédito não guarda antecipadamente valor monetário. Ao reservar, calcula-se o desconto pelo plano ativo e persiste-se o snapshot monetário na cobrança/reserva.

### 7.3 Natureza e limites

O crédito:

- não tem valor monetário próprio;
- não é sacável, reembolsável, vendável, transferível ou cedível;
- não migra entre cliente, CPF/CNPJ ou contrato reativado;
- não gera saldo a pagar;
- não expira enquanto indicador continuar ativo e elegível;
- limita-se a um por cobrança;
- segue FIFO: menor data de liberação e, no empate, menor identificador persistido.

Cinco créditos disponíveis beneficiam cinco cobranças elegíveis distintas; jamais formam 30%, 45%, 60% etc. na mesma cobrança.

## 8. Cobranças elegíveis e cálculo — regras aprovadas

Um crédito pode ser reservado quando:

- não é a primeira cobrança promocional;
- é mensalidade ou cobrança de ciclo do plano ativo;
- existe valor-base elegível;
- cliente está ativo e sem impedimento financeiro;
- cobrança ainda pode receber o desconto corretamente;
- não existe promoção incompatível;
- crédito FIFO mais antigo está `liberado`;
- campanha preserva créditos já gerados;
- desconto não afeta excedentes/adicionais.

### 8.1 Ciclos

Cada crédito desconta **uma mensalidade equivalente**, independentemente do ciclo:

| Ciclo | Total cobrado | Base do crédito |
|---|---|---|
| mensal | uma mensalidade | uma mensalidade equivalente |
| trimestral | três meses/valor comercial do ciclo | uma mensalidade equivalente |
| semestral | seis meses/valor comercial do ciclo | uma mensalidade equivalente |
| anual | doze meses/valor comercial do ciclo | uma mensalidade equivalente |

Fórmula:

```text
valor_desconto = arredondar(valor_mensal_equivalente_do_plano_ativo × percentual_do_credito)
valor_final = valor_total_do_ciclo - valor_desconto + excedentes_e_adicionais_integrais
```

O desconto nunca incide sobre todo o ciclo antecipado. A fonte recomendada para `valor_mensal_equivalente` é `PLA_ValorMensal`, por função única no domínio de Plano; `Plano::valorPorCiclo()` continua sendo fonte do total. A cobrança deve congelar os valores usados.

### 8.2 Excedentes

Excluem-se mensagens excedentes, consumo adicional, tarifas Meta, taxas, serviços avulsos, implantação, integrações, multas, juros e itens que não sejam mensalidade-base.

```text
valor-base do plano
- desconto de indicação
+ excedentes integrais
= valor final
```

A cobrança deve preservar valor-base, mensal equivalente, percentual, desconto, excedentes excluídos e valor final.

### 8.3 Plano atual e troca

O cálculo usa o plano ativo ao gerar a cobrança. Upgrade/downgrade não elimina créditos e não usa o plano antigo. FIFO e percentual do crédito permanecem.

- antes da integração definitiva: liberar reserva, recalcular no novo plano e recriar/atualizar pelo workflow;
- depois da integração definitiva: cancelar/substituir pelo procedimento oficial, sem editar valor diretamente; reservar novamente só depois de invalidar a cobrança anterior;
- nunca recalcular silenciosamente cobrança já integrada.

Mudança de **ciclo** com cobrança aberta ainda requer decisão específica.

### 8.4 Reserva, pontualidade e utilização

A cobrança deve ser preparada preferencialmente ao menos cinco dias antes do vencimento. Na geração:

```text
crédito liberado → reservado
```

A reserva mostra o desconto, vincula unicamente crédito e cobrança e congela o cálculo, mas **não consome definitivamente** o crédito.

Pagamento confirmado até a data de vencimento:

```text
reservado → utilizado
```

Vencimento sem pagamento, cancelamento/substituição da cobrança, falha definitiva de integração ou inelegibilidade financeira:

```text
reservado → liberado
```

Pagamento atrasado não causa multa/juros pelo programa, não utiliza o crédito e não o expira. O cliente deve pagar o valor integral da cobrança atrasada conforme mecanismo homologado no provedor; o crédito volta à próxima cobrança elegível, sem produzir desconto simultaneamente na atrasada e na futura.

## 9. Fluxos funcionais obrigatórios

### Fluxo A — Cliente comum

```text
Cadastro
→ trial iniciado pela conexão operacional da conta Meta
→ contratação e primeiro pagamento com 50%
→ pagamento confirmado pelo fluxo financeiro
→ código liberado na campanha ativa
→ participação ativa no programa
```

### Fluxo B — Cadastro por indicação

```text
Acesso por link `ref` ou código manual
→ normalização e validação backend
→ cadastro transacional
→ vínculo imutável com indicador e campanha
→ trial normal
→ primeiro pagamento com 50%
→ confirmação financeira
→ período de sete dias completos
→ revalidação de elegibilidade
→ indicação aprovada e crédito liberado ao indicador
```

### Fluxo C — Aplicação do crédito

```text
Preparação da próxima cobrança elegível
→ verificar cliente ativo, adimplência e prioridade de descontos
→ localizar o crédito liberado mais antigo (FIFO)
→ calcular 15% ou percentual histórico sobre uma mensalidade equivalente
→ vincular crédito e snapshot à cobrança
→ liberado → reservado
→ integrar a cobrança pelo fluxo financeiro oficial
→ pagamento pontual confirmado
→ reservado → utilizado
```

Em falha definitiva, cancelamento, substituição ou vencimento sem pagamento, a reserva retorna a `liberado` antes de nova aplicação.

### Fluxo D — Cancelamento antes dos sete dias

```text
Pagamento do indicado confirmado
→ crédito pendente/em confirmação
→ cancelamento, estorno, chargeback ou desfazimento antes do prazo
→ indicação e crédito cancelados
→ nenhum desconto para o indicador
```

### Fluxo E — Cancelamento do indicador

```text
Indicador com créditos e histórico
→ cancelamento do contrato/conta
→ créditos pendentes cancelados
→ créditos liberados ou bloqueados não utilizados expirados
→ créditos utilizados preservados no histórico
→ nenhum valor a receber ou restituir
```

## 10. Estados e transições

### 10.1 Indicação

| Estado | Definição | Entradas permitidas | Saídas principais |
|---|---|---|---|
| `cadastrada` | vínculo criado com cadastro concluído | criação | `aguardando_pagamento`, `inelegivel`, `fraude` |
| `aguardando_pagamento` | indicado ainda sem primeiro pagamento confirmado | `cadastrada` | `pagamento_confirmado`, `cancelada`, `fraude`, `inelegivel` |
| `pagamento_confirmado` | confirmação persistida; agenda deve ser criada | `aguardando_pagamento` | `em_confirmacao`, `cancelada`, `fraude` |
| `em_confirmacao` | janela de sete dias em curso | `pagamento_confirmado` | `aprovada`, `cancelada`, `fraude`, `inelegivel` |
| `aprovada` | janela concluída e crédito liberado | `em_confirmacao` | terminal comercial; fraude posterior fica auditada |
| `cancelada` | cancelamento/estorno/desfazimento | estados anteriores à aprovação | terminal |
| `fraude` | fraude administrativa comprovada/suspeita confirmada | qualquer pré-aprovação; excepcional pós-aprovação | terminal, com auditoria |
| `inelegivel` | regra objetiva não atendida | `cadastrada`, `aguardando_pagamento`, `em_confirmacao` | terminal |

`pagamento_confirmado` é fato financeiro; `em_confirmacao` é período temporal. Não devem ser usados como sinônimos.

### 10.2 Crédito

Estados conceituais aprovados: `pendente`, `em_confirmacao`, `liberado`, `bloqueado`, `reservado`, `utilizado`, `cancelado`, `expirado`.

```text
pendente → em_confirmacao → liberado
liberado → reservado
reservado → utilizado                         (pagamento pontual confirmado)
reservado → liberado                          (vencimento/falha/cancelamento/substituição)
liberado → bloqueado → liberado               (impedimento temporário/regularização)
liberado|bloqueado → expirado                 (cancelamento do indicador)
pendente|em_confirmacao|liberado|reservado → cancelado (fraude/inelegibilidade)
```

Restrições:

- uma cobrança não reserva dois créditos;
- um crédito não pertence a duas cobranças;
- `utilizado` exige pagamento pontual;
- atraso não cancela/expira;
- `utilizado` nunca regride automaticamente;
- fraude após uso é exceção administrativa, sem compensação automática na v1.

### 10.3 Disponível, bloqueado e expirado

- **disponível (`liberado`):** entra no FIFO e pode ser reservado;
- **temporariamente bloqueado:** preservado, fora do consumo enquanto houver impedimento;
- **expirado:** encerrado por cancelamento do indicador; nunca retorna, inclusive em reativação futura.

## 11. Cancelamento, inadimplência, fraude e grupos

### 11.1 Indicador cancelado

- pendentes/em confirmação são cancelados/encerrados;
- liberados/bloqueados não usados expiram;
- reservados exigem tratamento junto à cobrança, com liberação da reserva e expiração;
- utilizados permanecem históricos;
- não há pagamento, restituição ou conversão;
- reativação começa sem créditos antigos;
- histórico permanece integral no admin.

### 11.2 Indicador inadimplente

- mantém histórico e créditos;
- reserva volta a liberado ao vencer sem pagamento;
- liberados passam a bloqueados enquanto houver impedimento;
- não quitam retroativamente cobrança vencida;
- após regularização, bloqueados voltam ao FIFO;
- atraso não cria multa/juros do programa.

Se inadimplência também suspende **novas indicações**, permanece pendente.

### 11.3 Indicado cancelado ou fraudulento

Antes de sete dias: cancelamento, reembolso, chargeback, desfazimento, fraude ou duplicidade cancelam indicação/crédito. Após liberação, crédito é normalmente válido; fraude comprovada permite cancelamento administrativo somente se ainda não utilizado. Crédito usado demanda decisão humana auditada, sem estorno automático inicial.

A janela de sete dias reduz a necessidade normal de estornar benefício consumido.

### 11.4 Autoindicação e duplicidade

Bloquear autoindicação, mesma pessoa/empresa equivalente, usuários da mesma conta, cadastros artificiais e reutilização do indicado. Sinais, nunca todos como bloqueio automático isolado:

- CPF/CNPJ normalizado;
- e-mail e telefone normalizados;
- empresa e vínculo de conta;
- dados de pagamento (comparação segura, sem exposição);
- IP apenas auxiliar, nunca único;
- vínculo societário identificado em revisão.

Clientes distintos com CNPJ diferente podem participar mesmo no mesmo grupo, desde que contratos, cobranças e operações sejam reais. Abuso pode ser revisado. Admin marca fraude com motivo, operador, data e auditoria.

## 12. Arquitetura conceitual (não implementada)

### 12.1 Opções de código

**Opção A — coluna em `clientes`:** simples leitura, porém mistura ciclo de vida do programa ao cadastro, dificulta campanhas, histórico, suspensão e múltiplas vigências.

**Opção B — tabela própria (recomendada):** mantém campanha, estado, normalização, datas e auditoria; desacopla cliente e permite evolução sem poluir `clientes`.

Recomenda-se tabela própria porque o programa é orientado a campanhas e o código possui máquina de estados.

### 12.2 Entidades propostas

Os nomes abaixo são **propostas**, seguindo a convenção plural de tabelas e prefixos de colunas do projeto; deverão ser revisados na etapa de banco:

1. `indicacao_campanhas` (`ICP_*`): campanha, percentual, vigência, estado, regras versionadas e `USU_ID` administrativo;
2. `indicacao_codigos` (`ICD_*`): `CLI_ID`, campanha, código normalizado, estado e datas;
3. `indicacoes` (`IND_*`): indicador, indicado, código, campanha, percentual/snapshot, origem, estados e marcos financeiros/temporais;
4. `indicacao_creditos` (`ICR_*`): indicação, indicador, campanha, percentual, estado, liberação, reserva e vínculo com `COB_ID`/`ASS_ID`;
5. `indicacao_auditoria` (`IAU_*`): entidade, identificador, estado anterior/novo, motivo, operador e data.

Campos conceituais mínimos:

| Entidade | Campos sugeridos (não definitivos) |
|---|---|
| Campanha | ID, nome, descrição, percentual, início, fim, ativo, regras snapshot, criado/atualizado, admin |
| Código | ID, cliente, campanha, código exibido, código normalizado, estado, liberado/suspenso/cancelado em |
| Indicação | ID, código, indicador, indicado, campanha, percentual congelado, estado, origem (`link`/`manual`), cadastro, pagamento, confirmação até, aprovação/cancelamento/fraude, motivo |
| Crédito | ID, indicação, indicador, campanha, percentual, estado, liberação, cobrança/assinatura reservadas, base do plano, mensal equivalente, desconto calculado, reserva, uso, bloqueio/cancelamento/expiração |
| Auditoria | ID, tipo/ID da entidade, ação, antes/depois sanitizado, motivo, usuário, data, correlação |

Relacionamentos devem usar FKs com entidades reais (`CLI_ID`, `USU_ID`, `PLA_ID`, `ASS_ID`, `COB_ID`) quando aplicáveis. Restrições únicas devem garantir código normalizado, um indicador por indicado, um crédito por indicação, uma reserva ativa por crédito e no máximo um crédito por cobrança.

### 12.3 Configuração e snapshots

Campanha é a configuração central. Indicação deve apontar para campanha e guardar snapshot suficiente; crédito guarda o percentual concedido. Cobrança/reserva guarda base monetária calculada. Assim edições futuras não reescrevem história.

## 13. Integração financeira futura

Toda integração deverá ocorrer em serviços/modelos, orquestrada pelo `FinanceiroWorkflowService` ou equivalente:

1. selecionar crédito FIFO com lock;
2. validar cliente, campanha histórica, assinatura, cobrança, prioridade e itens;
3. reservar crédito e registrar snapshot dentro da mesma transação que cria/prepara a cobrança;
4. confirmar commit local;
5. chamar Asaas fora de transação longa;
6. se integração falhar definitivamente, transação compensatória libera reserva;
7. se integrar, preservar vínculo e valores;
8. webhook/manual confirmado até vencimento marca utilizado na mesma transação do sucesso financeiro;
9. vencimento/cancelamento/substituição libera a reserva de forma idempotente;
10. logs e auditoria usam dados sanitizados.

Nunca:

- SQL dentro do workflow;
- update de cobrança em controller;
- chamada externa sob lock/transação longa;
- marcar utilizado na criação da cobrança;
- recalcular cobrança integrada silenciosamente;
- permitir dois processos consumirem o mesmo crédito.

### 13.1 Requisito crítico: desconto exibido versus crédito utilizado

**Mostrar desconto** significa `reservado`. **Utilizar definitivamente** exige pagamento confirmado até o vencimento. Atraso devolve ao FIFO e a solução do provedor deve retirar/não honrar o desconto atrasado. A homologação deve provar que não ocorre simultaneamente:

- pagamento atrasado com desconto; e
- reutilização do mesmo crédito na próxima cobrança.

### 13.2 Campos financeiros futuros

Sem definir migration, a cobrança precisa preservar: valor original/total do ciclo, valor-base, mensal equivalente, percentual, desconto, valor final, origem, crédito, excedentes, vencimento e pontualidade. Hoje não foram identificados campos locais explícitos para todos esses snapshots; serão necessários na modelagem futura.

## 14. Tarefas agendadas

Dependência formal:

> **O Programa de Indicação deverá preferencialmente ser implementado sobre a infraestrutura central de tarefas agendadas, após sua criação e homologação.**

Pagamento confirmado agenda conceitualmente `liberar_credito_indicacao` para `data_pagamento + 7 dias completos`. A tarefa:

- revalida pagamento, indicado, cancelamento/estorno e fraude;
- trava indicação/crédito para impedir concorrência;
- libera uma única vez;
- aceita reprocessamento sem duplicar;
- registra auditoria;
- enfileira notificações apenas após commit;
- diferencia erro temporário, definitivo e item já processado.

A infraestrutura deve oferecer chave idempotente, agendamento, reserva/lock com expiração, tentativas/backoff, status, observabilidade e execução CLI/daemon. Não usar os workers atuais como se já fossem uma fila genérica.

## 15. Central de Notificações

Eventos conceituais futuros:

| Evento | Destinatário | Canais sugeridos | Observação |
|---|---|---|---|
| `programa_indicacao_liberado` | cliente elegível | interno, e-mail | disponibilidade geral |
| `codigo_indicacao_disponivel` | indicador | interno, e-mail | link/código; evitar envio inseguro |
| `indicacao_registrada` | indicador | interno | mensagem genérica ou nome comercial mínimo |
| `indicacao_pagamento_confirmado` | indicador | interno | informar início da janela, sem valor pago |
| `credito_indicacao_liberado` | indicador | interno, e-mail, WhatsApp | WhatsApp só com template Meta aprovado |
| `credito_indicacao_aplicado` | indicador | interno, e-mail | distinguir reserva de uso; idealmente notificar uso após pagamento pontual |

A configuração deve usar `EventoNotificacao`, `CanalNotificacao` e `NotificacaoService`, com idempotência. Não criar templates agora. Nome completo do indicado só pode ser usado conforme minimização; preferir nome comercial sanitizado ou texto genérico.

## 16. Área do cliente

Futura página **Programa de Indicação**.

### 16.1 Sem código/campanha

- antes do primeiro pagamento: mensagem de liberação futura;
- campanha inativa: impedir novos compartilhamentos e informar indisponibilidade, mas exibir créditos antigos e indicações anteriores válidas.

### 16.2 Campanha ativa e código liberado

Exibir código, link, copiar, campanha vigente, percentual, regras resumidas, link público/regulamento, totais de indicados, em andamento, disponíveis, reservados, bloqueados, utilizados, expirados/cancelados e próximo desconto previsto.

Lista do indicador mostra somente:

- nome/nome comercial mínimo;
- situação resumida;
- estágio do crédito;
- data relevante.

Rótulos: `Cadastro realizado`, `Aguardando pagamento`, `Em período de confirmação`, `Crédito disponível`, `Desconto reservado`, `Desconto utilizado`.

Não mostrar CPF/CNPJ, e-mail, telefone, endereço, plano, pagamento, Meta ou dados financeiros. Nome pode aparecer até uso do crédito. Depois, remover ou anonimizar como `Indicação concluída — crédito utilizado` (forma visual pendente). Cancelamento antes da validação remove o nome e pode deixar `Indicação não concluída` ou ocultar, conforme UX pendente. Admin preserva histórico.

## 17. Área administrativa

Filtros: indicador, indicado, campanha/estado, percentual, código, indicação/crédito, cobrança, período, fraude, cancelamento, bloqueio/reserva, pontualidade, alteração de plano.

Detalhes: origem, vigência, datas, pagamento qualificante, janela, crédito, cobrança de uso, valor-base, mensal equivalente, desconto, excedentes excluídos, mudança de plano, histórico, motivo e operador.

Campanhas: criar, editar futuro, ativar/inativar, consultar histórico e volumes de códigos/indicações/créditos. Proibir efeito retroativo.

Ações manuais exigem admin, CSRF, motivo obrigatório, autorização, transação e auditoria antes/depois. Nenhuma alteração silenciosa.

## 18. Site público e regulamento

### 18.1 Página comercial

URL sugerida: `/programa-indicacao`. Posicionamento: fidelidade e agradecimento por indicações reais. Não usar `renda extra`, `comissão`, `afiliado`, `ganhar dinheiro`, `lucrar` nem `Lançamento`.

Chamada sugerida: **Indique outras empresas e economize nas próximas mensalidades.** O programa aparece depois da explicação do produto, nunca como argumento principal do hero.

Explicar brevemente:

- código após primeiro pagamento;
- condições podem variar por campanha;
- indicação aprovada gera percentual divulgado;
- um crédito por cobrança;
- ciclos maiores descontam sobre uma mensalidade equivalente;
- apenas plano-base, sem excedentes;
- pagamento precisa ser pontual;
- atraso preserva crédito para próxima cobrança;
- sem dinheiro/transferência;
- inativar campanha não elimina créditos conquistados.

### 18.2 Regulamento

URL sugerida: `/programa-indicacao/regulamento`. Deve cobrir elegibilidade, campanha, qualificação, sete dias, pontualidade, cancelamento, fraude, privacidade, natureza promocional e alterações futuras sem retirar créditos liberados, salvo fraude/violação.

### 18.3 Texto-base público resumido

> O primeiro pagamento possui 50% de desconto. Após a confirmação desse pagamento, o cliente elegível recebe seu código de indicação. Cada indicação aprovada, após pagamento e permanência ativa por sete dias, gera um crédito com o percentual da campanha. É utilizado no máximo um crédito por cobrança futura elegível. Em ciclos maiores, o desconto considera uma mensalidade equivalente e não alcança excedentes. O pagamento deve ocorrer até o vencimento; em atraso, o crédito permanece disponível para outra cobrança. Créditos são promocionais, automáticos, intransferíveis e não podem ser convertidos em dinheiro.

## 19. Analytics futuros (não implementar nesta etapa)

Eventos propostos:

- `view_referral_program`;
- `copy_referral_code`;
- `copy_referral_link`;
- `referral_signup_started`;
- `referral_signup_completed`;
- `referral_credit_released`;
- `referral_credit_applied`.

Usar a infraestrutura GA4/GTM existente. Nunca enviar nome, e-mail, telefone, CPF/CNPJ, código completo, `CLI_ID`, ID do indicado ou outros identificadores pessoais. Parâmetros admissíveis devem ser agregados: origem da tela, status conceitual, campanha por identificador técnico não pessoal e ciclo.

## 20. Segurança, privacidade e auditoria

- sufixo aleatório não enumerável e índice único;
- URL sem ID interno;
- validação/autorização sempre backend;
- normalização única em gravação e consulta;
- prepared statements e transações;
- CSRF e permissão admin;
- locks e restrições contra dupla reserva/uso;
- código não manipulável após cadastro;
- logs sanitizados, sem código completo quando desnecessário;
- Analytics sem PII;
- exposição mínima e temporal do indicado;
- dados de pagamento usados como sinal antifraude sem exposição ao indicador;
- auditoria imutável de estados, motivos e operador;
- rate limit para validação de código e respostas que não facilitem enumeração em massa.

## 21. Relatórios futuros

- clientes com código ativo;
- campanhas ativas/inativas;
- total e conversão de indicações;
- aguardando pagamento/em confirmação;
- créditos liberados, reservados, bloqueados, utilizados, cancelados e expirados;
- receita originada por indicação;
- custo do desconto e base mensal equivalente;
- retenção de indicados;
- pagamentos pontuais/atrasados com reserva;
- fraude, cancelamento e reembolso;
- desempenho por campanha/ciclo sem expor PII indevida.

## 22. Casos de teste funcionais

### 22.1 Código e cadastro

1. prefixo para nomes simples, compostos, curtos, acentuados, razão vazia e prefixo neutro;
2. alfabeto sem ambíguos, unicidade, colisão/retry e concorrência;
3. estabilidade após mudar cadastro;
4. comparação case-insensitive;
5. link `ref`, edição e persistência após erro;
6. código manual válido, inválido, inativo, suspenso e campanha inativa;
7. código inválido bloqueia envio até corrigir/remover;
8. cadastro sem código permanece normal;
9. um indicador por indicado e vínculo transacional/imutável;
10. autoindicação, mesma conta, CPF/CNPJ duplicado e sinais antifraude.

### 22.2 Qualificação

11. cadastro não gera crédito;
12. trial igual para indicado/não indicado;
13. primeiro pagamento aplica somente 50%;
14. erro/pendência não libera código;
15. webhook e pagamento manual liberam código uma única vez;
16. indicado sem pagamento permanece aguardando;
17. pagamento confirmado agenda exatamente uma tarefa para +7 dias;
18. execução antes do prazo não libera;
19. execução após prazo libera uma vez;
20. cancelamento, estorno, chargeback, fraude e reembolso no prazo cancelam;
21. reprocessamento idempotente;
22. múltiplos indicados geram créditos independentes.

### 22.3 Crédito e cobrança

23. percentual/snapshot não muda ao editar campanha;
24. FIFO por liberação/ID;
25. um crédito por cobrança e exclusividade de reserva concorrente;
26. primeira cobrança nunca usa crédito;
27. mensal usa uma mensalidade;
28. trimestral/semestral/anual descontam somente uma mensalidade equivalente;
29. arredondamento e valores mínimos;
30. plano + excedentes desconta somente base;
31. upgrade/downgrade recalcula antes da integração;
32. cobrança integrada não é editada silenciosamente;
33. mudança de ciclo com cobrança aberta segue decisão aprovada futura;
34. falha local não reserva; falha Asaas libera reserva;
35. reconciliação/retry não duplica reserva;
36. pagamento até vencimento usa crédito;
37. atraso libera crédito e cobra integral sem desconto duplicado;
38. cancelamento/substituição/vencimento devolve ao FIFO;
39. inadimplência bloqueia e regularização libera;
40. crédito não quita vencida retroativamente.

### 22.4 Ciclo de vida e interfaces

41. cancelamento do indicador cancela pendentes, expira disponíveis e preserva usados;
42. reativação não restaura expirados;
43. fraude pré/pós-liberação e exceção após uso;
44. empresas do grupo não são bloqueadas apenas pelo grupo;
45. campanha inativa bloqueia novas participações e preserva créditos;
46. comportamento de indicações em confirmação aguarda decisão;
47. notificações após commit, idempotência e falha de canal;
48. WhatsApp sem template aprovado não é enviado;
49. área do cliente minimiza e remove/anonimiza nome no momento definido;
50. área admin exige permissão, CSRF, motivo e auditoria;
51. Analytics não contém PII/código/IDs pessoais;
52. relatórios conciliam crédito, cobrança e pagamento.

## 23. Decisões que precisam de aprovação antes da implementação

As decisões abaixo são as únicas pendentes identificadas. Ciclos, inadimplência sobre consumo, visibilidade mínima, campanhas, alteração de plano e exclusão de excedentes já estão aprovados.

| Decisão | Recomendação | Prós | Contras | Impacto técnico |
|---|---|---|---|---|
| Nome comercial | “Programa de Indicação Disparador.net” inicialmente | claro e juridicamente neutro | pouco distintivo | textos, URLs, templates e SEO |
| Início da primeira campanha | data configurada após homologação financeira | evita promessa sem infraestrutura | posterga divulgação | vigência, feature flag e regulamento |
| Indicações em confirmação na inativação | preservar regra/campanha do cadastro | previsibilidade e confiança | campanha continua gerando crédito residual | snapshot, job e filtro por vigência |
| Histórico após uso | manter linha anonimizada | transparência sem PII contínua | menos detalhe ao cliente | anonimização na consulta/view, não apagar admin |
| Inadimplente pode gerar novas indicações? | suspender novas indicações enquanto inadimplente | reduz risco e comunicação incoerente | reduz aquisição e exige mudança de estado do código | regra de elegibilidade e reativação automática |
| Mudança de ciclo com cobrança aberta | cancelar/substituir pelo workflow e reservar após invalidar anterior | consistência e auditoria | mais chamadas/reconciliação com Asaas | orquestração, locks e compensação |
| Futuros cupons/descontos | política central com prioridade explícita; incompatível preserva crédito | evita acúmulo inesperado | exige catálogo de promoções | serviço de desconto e snapshots |
| Campanhas simultâneas | uma campanha pública ativa por vez | atribuição simples | limita segmentação futura | unique/regra transacional de ativação |
| Congelamento do percentual | no cadastro, vinculando indicação à campanha | promessa estável desde a entrada | crédito pode nascer após campanha acabar | snapshot na indicação e cópia no crédito |
| Vigência mínima de código após inativação | código fica suspenso para novas indicações imediatamente, sem prazo mínimo; créditos preservados | comunicação simples | compartilhamentos antigos param | validação dinâmica e mensagem pública |
| Forma técnica da pontualidade no Asaas | homologar desconto condicionado ao vencimento antes do módulo financeiro | garante regra sem manipulação manual | pode exigir recurso/fluxo adicional do provedor | prova técnica, conciliação e testes end-to-end |

Nenhuma recomendação desta tabela é regra até aprovação formal.

## 24. Fora do escopo da primeira versão

Pagamento de comissão, saque, PIX ao indicador, afiliados/marketplace externo, níveis, ranking, gamificação, código customizado, transferência/cessão, conversão em dinheiro, múltiplos créditos na mesma cobrança, multinível, comissão de indicação de indicação, influenciadores e integrações de afiliados.

Também não fazem parte desta branch documental: código, banco, migration, tela, rota, financeiro, cadastro, worker, deploy, template ou tag Analytics.

## 25. Dependências e riscos

### Dependências

1. infraestrutura central de tarefas agendadas homologada;
2. política central de descontos e benefício inicial de 50%;
3. fonte única de total do ciclo e mensalidade equivalente;
4. modelagem/migrations com locks e unicidade;
5. homologação da pontualidade no Asaas;
6. pontos comuns de confirmação manual/webhook;
7. Central de Notificações e templates Meta aprovados;
8. decisões pendentes e regulamento aprovados.

### Riscos

- conceder desconto atrasado e reutilizar o mesmo crédito;
- corrida entre recorrência, webhook, vencimento, troca de plano e cancelamento;
- divergência entre valor comercial, assinatura e cobrança;
- edição retroativa de campanha;
- fraude/autoindicação com bloqueio excessivo de empresas legítimas;
- PII exposta ao indicador, logs ou Analytics;
- job duplicado liberar dois créditos;
- chamada Asaas sob transação longa;
- primeira cobrança receber dois benefícios;
- campanha inativa continuar divulgada por cache/interface.

## 26. Divisão futura sugerida

Cada item deve ser branch/PR próprio, após aprovação do anterior:

1. `feat/tarefas-agendadas-infraestrutura` — fila, idempotência, retry, CLI/daemon e observabilidade;
2. `feat/indicacao-banco-dominio` — campanhas, códigos, indicações, créditos e auditoria;
3. `feat/indicacao-cadastro` — `ref`, validação, vínculo transacional e antifraude inicial;
4. `feat/indicacao-codigo-primeiro-pagamento` — liberação idempotente por confirmação financeira;
5. `feat/indicacao-credito-sete-dias` — tarefa, revalidações e liberação;
6. `feat/indicacao-financeiro` — 50%, política central, FIFO, reserva, pontualidade e Asaas;
7. `feat/indicacao-area-cliente` — painel, lista minimizada e históricos;
8. `feat/indicacao-admin` — campanhas, revisão, fraude e auditoria;
9. `feat/indicacao-notificacoes` — eventos, canais e templates aprovados;
10. `feat/indicacao-site-regulamento` — página comercial e regulamento;
11. `feat/indicacao-analytics` — eventos sem PII na infraestrutura existente;
12. `test/indicacao-homologacao` — concorrência, financeiro/Asaas, segurança e ponta a ponta.

Nenhuma dessas etapas é implementada nesta branch.

## 27. Checklist de prontidão para implementação

Antes do primeiro PR de código:

- [ ] decisões da seção 23 aprovadas;
- [ ] regulamento revisado por responsável jurídico/comercial;
- [ ] desconto inicial de 50% modelado;
- [ ] mensalidade equivalente e arredondamento definidos em fonte única;
- [ ] comportamento Asaas após vencimento homologado;
- [ ] tarefas agendadas homologadas;
- [ ] estados, constraints e concorrência revisados;
- [ ] minimização de dados aprovada;
- [ ] campanha inicial, percentual e vigência aprovados;
- [ ] plano de migração/rollback, testes e observabilidade definido.
