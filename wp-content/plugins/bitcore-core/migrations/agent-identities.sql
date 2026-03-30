-- Migration: Agent Identities and Knowledge Graph Tables
-- Pattern extracted from DashClaw and flux
-- Version: 1.0.0
-- Created: 2026-03-01

-- Agent Identities Table
-- Stores cryptographic identities for agent authentication
CREATE TABLE IF NOT EXISTS {wpdb_prefix}bitcore_agent_identities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agent_id VARCHAR(255) NOT NULL,
    public_key TEXT NOT NULL,
    algorithm VARCHAR(64) NOT NULL DEFAULT 'RSASSA-PKCS1-v1_5',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY agent_id (agent_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Knowledge Graph Nodes Table
-- Stores entities in the knowledge graph
CREATE TABLE IF NOT EXISTS {wpdb_prefix}bitcore_knowledge_nodes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    node_id VARCHAR(255) NOT NULL,
    type VARCHAR(64) NOT NULL,
    data LONGTEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY node_id (node_id),
    KEY type (type),
    KEY updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Knowledge Graph Edges Table
-- Stores relationships between nodes
CREATE TABLE IF NOT EXISTS {wpdb_prefix}bitcore_knowledge_edges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_id VARCHAR(255) NOT NULL,
    target_id VARCHAR(255) NOT NULL,
    type VARCHAR(64) NOT NULL,
    metadata LONGTEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY source_id (source_id),
    KEY target_id (target_id),
    KEY type (type),
    KEY source_target (source_id, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Health Snapshots Log Table (optional, for historical tracking)
CREATE TABLE IF NOT EXISTS {wpdb_prefix}bitcore_health_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot_data LONGTEXT NOT NULL,
    health_score INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY health_score (health_score),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
