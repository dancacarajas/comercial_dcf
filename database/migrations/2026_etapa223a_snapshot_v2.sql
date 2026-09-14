-- Etapa 22.3-A — Snapshot V2 (ADITIVO / nullable)
-- Não altera registros V1. Não converte histórico.

ALTER TABLE `sponsorship_simulations`
    ADD COLUMN `scenario_result_snapshot` LONGTEXT NULL DEFAULT NULL AFTER `display_snapshot`,
    ADD COLUMN `result_type` VARCHAR(64) NULL DEFAULT NULL AFTER `scenario_result_snapshot`;

ALTER TABLE `sponsorship_simulations`
    ADD KEY `idx_sponsorship_sim_result_type` (`result_type`);
