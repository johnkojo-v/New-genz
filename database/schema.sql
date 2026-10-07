CREATE DATABASE IF NOT EXISTS metaverse_db;
USE metaverse_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_name VARCHAR(100) DEFAULT 'guest',
    avatar_skin VARCHAR(50) DEFAULT 'default',
    current_world_id INT DEFAULT 1,
    x FLOAT DEFAULT 0,
    y FLOAT DEFAULT 0,
    z FLOAT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS worlds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    spawn_x FLOAT DEFAULT 0,
    spawn_z FLOAT DEFAULT 0,
    terrain_type VARCHAR(50) DEFAULT 'grass',
    sky_color VARCHAR(7) DEFAULT '#0b1020',
    status ENUM('draft', 'active', 'archived') DEFAULT 'active',
    created_by INT,
    max_players INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS world_player_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    world_id INT NOT NULL,
    x FLOAT DEFAULT 0,
    y FLOAT DEFAULT 0,
    z FLOAT DEFAULT 0,
    yaw FLOAT DEFAULT 0,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_world (user_id, world_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

CREATE TABLE IF NOT EXISTS player_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    world_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

CREATE TABLE IF NOT EXISTS avatar_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    asset_name VARCHAR(100) NOT NULL,
    asset_type VARCHAR(50) NOT NULL,
    asset_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO worlds (name, slug, description, spawn_x, spawn_z, terrain_type, sky_color, status) VALUES
('Central Plaza', 'central-plaza', 'The heart of the metaverse', 0, 0, 'grass', '#0b1020', 'active'),
('Crystal Mountains', 'crystal-mountains', 'High altitude terrain with peaks', 50, 50, 'mountain', '#1a3a4a', 'active'),
('Neon City', 'neon-city', 'Futuristic urban landscape', -50, -50, 'urban', '#1a0a2e', 'active'),
('Forest Grove', 'forest-grove', 'Lush green environment', 0, -80, 'forest', '#0d3b0d', 'active')
ON DUPLICATE KEY UPDATE slug = slug;
