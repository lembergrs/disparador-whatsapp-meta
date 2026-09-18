-- Registra o último login real do usuário para exibição no dashboard.
-- A impersonação administrativa não passa pelo LoginController::autenticar(),
-- portanto não atualiza este campo.
ALTER TABLE usuarios
    ADD COLUMN USU_UltimoAcesso DATETIME NULL AFTER USU_Ativo;
