ALTER TABLE disparo_manual_itens
    ADD INDEX IDX_DMI_FilaWorker (DMI_Status, DMI_ProximaTentativa, DMI_ID),
    ADD INDEX IDX_DMI_LoteFilaWorker (DML_ID, DMI_Status, DMI_ProximaTentativa, DMI_ID);

ALTER TABLE fila_envio
    ADD INDEX IDX_FIL_CampanhaFilaWorker (CAM_ID, FIL_Status, FIL_ProximaTentativa, FIL_ID);

ALTER TABLE campanhas
    ADD INDEX IDX_CAM_FilaWorker (CAM_Status, CAM_DataAgendamento, CAM_ID);
