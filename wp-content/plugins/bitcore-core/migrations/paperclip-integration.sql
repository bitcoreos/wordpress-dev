-- Paperclip Integration Tables for BITCORE
-- Generated: 2026-03-29
-- 
-- Run via: wp db query < migrations/paperclip-integration.sql
-- Or via PHP: require_once('migrations/run-migration.php');

-- 1. Checkouts Table (Exclusive task execution locks)
CREATE TABLE IF NOT EXISTS `{table_prefix}bitcore_checkouts` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `issue_id` bigint(20) unsigned NOT NULL,
    `agent_id` varchar(100) NOT NULL,
    `run_id` varchar(100) DEFAULT NULL,
    `checkout_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime NOT NULL,
    `completed_at` datetime DEFAULT NULL,
    `status` enum('active','completed','expired','released') DEFAULT 'active',
    PRIMARY KEY (`id`),
    UNIQUE KEY `issue_id` (`issue_id`),
    KEY `agent_id` (`agent_id`),
    KEY `status` (`status`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Budget Policies Table (Per-agent budget limits)
CREATE TABLE IF NOT EXISTS `{table_prefix}bitcore_budget_policies` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `agent_id` varchar(100) NOT NULL,
    `monthly_limit` decimal(15,4) NOT NULL DEFAULT '0.0000',
    `current_spend` decimal(15,4) NOT NULL DEFAULT '0.0000',
    `period_start` date NOT NULL,
    `period_end` date NOT NULL,
    `enforcement_level` enum('company','agent','project') DEFAULT 'agent',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `agent_period` (`agent_id`, `period_start`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Budget Incidents Table (Over-budget events)
CREATE TABLE IF NOT EXISTS `{table_prefix}bitcore_budget_incidents` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `policy_id` bigint(20) unsigned NOT NULL,
    `agent_id` varchar(100) NOT NULL,
    `incident_type` enum('warning','threshold','exceeded','blocked') DEFAULT 'warning',
    `amount` decimal(15,4) NOT NULL,
    `limit_at_time` decimal(15,4) NOT NULL,
    `message` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `policy_id` (`policy_id`),
    KEY `agent_id` (`agent_id`),
    KEY `incident_type` (`incident_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Retired Goals Table
-- Task unification retired the legacy hierarchical goals table.
-- Use canonical WordPress agent_task storage via /bitcore/v1/tasks.

-- 5. Approvals Table (Governance approval queue)
CREATE TABLE IF NOT EXISTS `{table_prefix}bitcore_approvals` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `workflow_id` bigint(20) unsigned DEFAULT NULL,
    `agent_id` varchar(100) NOT NULL,
    `request_type` varchar(100) NOT NULL,
    `request_data` longtext DEFAULT NULL,
    `status` enum('pending','approved','rejected','expired') DEFAULT 'pending',
    `approver_id` varchar(100) DEFAULT NULL,
    `approved_at` datetime DEFAULT NULL,
    `expires_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `workflow_id` (`workflow_id`),
    KEY `agent_id` (`agent_id`),
    KEY `status` (`status`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Sessions Table (Agent session persistence)
CREATE TABLE IF NOT EXISTS `{table_prefix}bitcore_sessions` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `agent_id` varchar(100) NOT NULL,
    `session_key` varchar(100) NOT NULL,
    `context_data` longtext DEFAULT NULL,
    `heartbeat_at` datetime DEFAULT NULL,
    `expires_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `agent_session` (`agent_id`, `session_key`),
    KEY `heartbeat_at` (`heartbeat_at`),
    KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
