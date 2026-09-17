# Reconciliação de NFS-e emitida externamente

Fluxo administrativo para casos em que uma cobrança já possui tentativa automática local, mas a NFS-e real foi emitida fora do Disparador.

## Segurança

- A chave informada deve ter 50 dígitos.
- O XML é consultado no ambiente nacional antes de qualquer alteração local.
- O XML deve corresponder à chave, conter o CNPJ do prestador configurado, o documento do cliente (quando disponível) e o mesmo valor da cobrança.
- A tentativa automática anterior é preservada como `erro_definitivo`, inativa, com código `substituida_por_emissao_externa`; sua série/DPS nunca é reaproveitada.
- A nota externa é criada como novo registro ativo e `emitida`.
- A chave de acesso passa a ter proteção UNIQUE no banco.

## Acesso administrativo

A tela de confirmação é acessível por `index.php?url=nfse/registrarExterna&nfse_id=<NFE_ID>` para uma tentativa fiscal ativa. O POST usa CSRF e repete todas as validações no servidor.

Para o incidente que motivou esta implementação, usar `NFE_ID=6` somente depois de aplicar a migration e validar a branch em ambiente de teste. Não acionar novamente o fluxo normal de emissão da cobrança enquanto a reconciliação não estiver concluída.

## Documentos após reconciliação

O XML oficial é armazenado junto com sua referência e hash no registro fiscal. A reconciliação não gera PDF. A ação **PDF** fica disponível quando há XML e gera o DANFSe sob demanda em `GeraDanfse.php`, retornando os bytes ao navegador sem persistir o PDF. **Reconsultar** atualiza somente XML e eventos. Ver [documentação operacional](NFSE_ETAPA_3_OPERACIONAL.md) para autorização e testes.
