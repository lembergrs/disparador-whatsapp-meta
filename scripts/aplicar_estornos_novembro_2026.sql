-- Executar em janela de manutenção, sem envios ou faturamento concorrentes.
-- Requer MariaDB; qualquer erro deve encerrar o cliente sem --force.
CREATE TEMPORARY TABLE retro_candidatos (
 wamid VARBINARY(255) PRIMARY KEY, CLI_ID INT NOT NULL, origem_id INT NOT NULL,
 codigo VARCHAR(64) NOT NULL, periodo VARCHAR(12) NOT NULL
) ENGINE=InnoDB;
CREATE TEMPORARY TABLE retro_totais (CLI_ID INT PRIMARY KEY, pago INT, avaliacao INT) ENGINE=InnoDB;
START TRANSACTION;
SELECT CMS_ID FROM consumo_mensal WHERE CMS_AnoMes='202611' AND CLI_ID IN (7,55,88,96,105) FOR UPDATE;
SELECT EXC_ID FROM excedentes_mensais WHERE EXC_AnoMes='202611' AND CLI_ID IN (7,55,88,96,105) FOR UPDATE;
SELECT COB_ID FROM cobrancas WHERE CLI_ID IN (7,55,88,96,105) FOR UPDATE;
INSERT INTO retro_candidatos
SELECT BINARY d.DMI_MessageId,d.CLI_ID,MIN(d.DMI_ID),
 MIN(JSON_UNQUOTE(JSON_EXTRACT(d.DMI_Retorno,'$.error_code'))),
 CASE WHEN d.DMI_DataEnvio < c.CMS_InicioPlanoPago THEN 'avaliacao' ELSE 'pago' END
FROM disparo_manual_itens d
JOIN consumo_mensal c ON c.CLI_ID=d.CLI_ID AND c.CMS_AnoMes='202611'
WHERE d.CLI_ID IN (7,55,88,96,105)
 AND d.DMI_DataEnvio >= '2026-11-01' AND d.DMI_DataEnvio < '2026-12-01'
 AND DATE_FORMAT(CURRENT_DATE,'%Y%m')='202611'
 AND c.CMS_InicioPlanoPago IS NOT NULL
 AND d.DMI_Status IN ('failed','erro') AND d.DMI_MessageId <> ''
 AND CASE WHEN JSON_VALID(d.DMI_Retorno) THEN JSON_UNQUOTE(JSON_EXTRACT(d.DMI_Retorno,'$.error_code')) END IN ('131026','131042','131048','131049')
 AND EXISTS (SELECT 1 FROM disparos s WHERE s.CLI_ID=d.CLI_ID AND BINARY s.DSP_MessageId=BINARY d.DMI_MessageId)
 AND EXISTS (SELECT 1 FROM conversa_mensagens m WHERE BINARY m.MSG_MetaMessageId=BINARY d.DMI_MessageId AND m.MSG_Direcao='enviada' AND m.MSG_EntregueEm IS NULL AND m.MSG_LidaEm IS NULL)
 AND NOT EXISTS (SELECT 1 FROM conversa_mensagens m WHERE BINARY m.MSG_MetaMessageId=BINARY d.DMI_MessageId AND (m.MSG_EntregueEm IS NOT NULL OR m.MSG_LidaEm IS NOT NULL))
 AND NOT EXISTS (SELECT 1 FROM consumo_estornos_meta e WHERE BINARY e.CEM_MessageId=BINARY d.DMI_MessageId)
 AND NOT EXISTS (SELECT 1 FROM excedentes_mensais e WHERE e.CLI_ID=d.CLI_ID AND e.EXC_AnoMes='202611' AND e.EXC_Status<>'pendente')
 AND NOT EXISTS (SELECT 1 FROM cobrancas b WHERE b.CLI_ID=d.CLI_ID AND b.COB_Status<>'cancelado' AND (b.COB_Tipo='excedente' OR b.COB_AdicionaisCentavos>0) AND (b.COB_DataVencimento IS NULL OR b.COB_DataVencimento>='2026-11-01'))

 AND NOT EXISTS (SELECT 1 FROM consumo_envios_meta v WHERE BINARY v.CEV_MessageId=BINARY d.DMI_MessageId)
GROUP BY BINARY d.DMI_MessageId,d.CLI_ID,CASE WHEN d.DMI_DataEnvio < c.CMS_InicioPlanoPago THEN 'avaliacao' ELSE 'pago' END;
INSERT INTO retro_totais SELECT CLI_ID,SUM(periodo='pago'),SUM(periodo='avaliacao') FROM retro_candidatos GROUP BY CLI_ID;
-- Falhar de forma segura se a seleção divergir da auditoria autorizada.
CREATE TEMPORARY TABLE retro_guard (ok INT NOT NULL CHECK(ok=1)) ENGINE=InnoDB;
INSERT INTO retro_guard SELECT IF(COUNT(*)=5 AND SUM(pago)=1286 AND SUM(avaliacao)=20 AND SUM(CASE WHEN CLI_ID=7 AND pago=326 AND avaliacao=0 OR CLI_ID=55 AND pago=247 AND avaliacao=0 OR CLI_ID=88 AND pago=589 AND avaliacao=20 OR CLI_ID=96 AND pago=42 AND avaliacao=0 OR CLI_ID=105 AND pago=82 AND avaliacao=0 THEN 1 ELSE 0 END)=5,1,0) FROM retro_totais;
INSERT INTO retro_guard SELECT IF(COUNT(*)=5 AND SUM(c.CMS_Mensagens>=t.pago AND c.CMS_MensagensAvaliacao>=t.avaliacao AND c.CMS_LimiteMensagens IS NOT NULL)=5,1,0) FROM retro_totais t JOIN consumo_mensal c ON c.CLI_ID=t.CLI_ID AND c.CMS_AnoMes='202611';
INSERT INTO consumo_estornos_meta (CEM_MessageId,CLI_ID,CEM_AnoMes,CEM_Origem,CEM_OrigemId,CEM_CodigoErro,CEM_DataCadastro)
SELECT wamid,CLI_ID,'202611','disparo_manual',origem_id,codigo,NOW() FROM retro_candidatos;
UPDATE consumo_mensal c JOIN retro_totais t ON t.CLI_ID=c.CLI_ID
SET c.CMS_Mensagens=c.CMS_Mensagens-t.pago,c.CMS_MensagensAvaliacao=c.CMS_MensagensAvaliacao-t.avaliacao,c.CMS_AtualizadoEm=NOW()
WHERE c.CMS_AnoMes='202611';
UPDATE excedentes_mensais e JOIN retro_totais t ON t.CLI_ID=e.CLI_ID JOIN consumo_mensal c ON c.CLI_ID=e.CLI_ID AND c.CMS_AnoMes='202611'
SET e.EXC_Mensagens=GREATEST(c.CMS_Mensagens-c.CMS_LimiteMensagens,0),e.EXC_ValorTotal=GREATEST(c.CMS_Mensagens-c.CMS_LimiteMensagens,0)*e.EXC_ValorUnitario
WHERE e.EXC_AnoMes='202611' AND e.EXC_Status='pendente';
SELECT * FROM retro_totais ORDER BY CLI_ID;
COMMIT;
