-- Simulação somente leitura. Competência novembro/2026.
-- Evidência histórica complementar, não comprovante individual.
SELECT d.CLI_ID,
 CASE WHEN d.DMI_DataEnvio < c.CMS_InicioPlanoPago THEN 'avaliacao' ELSE 'pago' END periodo,
 COUNT(DISTINCT BINARY d.DMI_MessageId) candidatos
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
GROUP BY d.CLI_ID, periodo ORDER BY d.CLI_ID, periodo;
