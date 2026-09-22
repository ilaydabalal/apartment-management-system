ALTER TABLE `apartments` DROP FOREIGN KEY IF EXISTS `apartments_ibfk_1`;

ALTER TABLE `apartment_units` DROP FOREIGN KEY IF EXISTS `apartment_units_ibfk_1`;

ALTER TABLE `dues` DROP FOREIGN KEY IF EXISTS `dues_ibfk_1`;
ALTER TABLE `dues` DROP FOREIGN KEY IF EXISTS `dues_ibfk_2`;
ALTER TABLE `dues` DROP FOREIGN KEY IF EXISTS `dues_ibfk_3`;

ALTER TABLE `dues_definitions` DROP FOREIGN KEY IF EXISTS `dues_definitions_ibfk_1`;

ALTER TABLE `expenses` DROP FOREIGN KEY IF EXISTS `expenses_ibfk_1`;
ALTER TABLE `expenses` DROP FOREIGN KEY IF EXISTS `expenses_ibfk_2`;
ALTER TABLE `expenses` DROP FOREIGN KEY IF EXISTS `expenses_ibfk_3`;

ALTER TABLE `incomes` DROP FOREIGN KEY IF EXISTS `incomes_ibfk_1`;
ALTER TABLE `incomes` DROP FOREIGN KEY IF EXISTS `incomes_ibfk_2`;
ALTER TABLE `incomes` DROP FOREIGN KEY IF EXISTS `incomes_ibfk_3`;

ALTER TABLE `payments` DROP FOREIGN KEY IF EXISTS `payments_ibfk_1`;
ALTER TABLE `payments` DROP FOREIGN KEY IF EXISTS `payments_ibfk_2`;
ALTER TABLE `payments` DROP FOREIGN KEY IF EXISTS `payments_ibfk_3`;
ALTER TABLE `payments` DROP FOREIGN KEY IF EXISTS `payments_ibfk_4`;

ALTER TABLE `system_logs` DROP FOREIGN KEY IF EXISTS `system_logs_ibfk_1`;

ALTER TABLE `users` DROP FOREIGN KEY IF EXISTS `users_ibfk_1`;

ALTER TABLE `user_sessions` DROP FOREIGN KEY IF EXISTS `user_sessions_ibfk_1`; 