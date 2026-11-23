-- Somente leitura. Candidatos, não comprovação de cobrança individual.
SELECT x.CLI_ID, x.ano_mes, x.origem, x.codigo, COUNT(DISTINCT x.wamid) AS candidatos
FROM (
 SELECT CLI_ID, DATE_FORMAT(DMI_DataEnvio,'%Y%m') ano_mes, 'manual' origem,
 DMI_MessageId wamid, CASE WHEN JSON_VALID(DMI_Retorno) THEN JSON_UNQUOTE(JSON_EXTRACT(DMI_Retorno,'$.error_code')) END codigo
 FROM disparo_manual_itens WHERE DMI_Status IN ('failed','erro') AND DMI_DataEnvio IS NOT NULL AND DMI_MessageId IS NOT NULL
 UNION ALL
 SELECT c.CLI_ID, DATE_FORMAT(f.FIL_DataEnvio,'%Y%m'), 'campanha',
 f.FIL_MessageId, CASE WHEN JSON_VALID(f.FIL_Retorno) THEN JSON_UNQUOTE(JSON_EXTRACT(f.FIL_Retorno,'$.error_code')) END
 FROM fila_envio f JOIN campanhas c ON c.CAM_ID=f.CAM_ID
 WHERE f.FIL_Status IN ('failed','erro') AND f.FIL_DataEnvio IS NOT NULL AND f.FIL_MessageId IS NOT NULL
) x LEFT JOIN consumo_estornos_meta e ON e.CEM_MessageId=x.wamid
WHERE e.CEM_ID IS NULL AND x.wamid <> ''
GROUP BY x.CLI_ID,x.ano_mes,x.origem,x.codigo
ORDER BY x.ano_mes DESC,x.CLI_ID;
