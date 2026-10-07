CREATE TABLE IF NOT EXISTS bank_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  balance DECIMAL(12, 2) DEFAULT 0.00,
  account_number VARCHAR(20) UNIQUE,
  account_type VARCHAR(50) DEFAULT 'savings',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bank_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  transaction_type VARCHAR(50) NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  description TEXT,
  reference_id VARCHAR(100),
  status VARCHAR(50) DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (reference_id)
);

CREATE TABLE IF NOT EXISTS paystack_deposits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'NGN',
  paystack_reference VARCHAR(100) UNIQUE,
  paystack_access_code VARCHAR(255),
  authorization_url VARCHAR(500),
  status VARCHAR(50) DEFAULT 'pending',
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (paystack_reference)
);

CREATE TABLE IF NOT EXISTS house_rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  house_id INT NOT NULL,
  room_name VARCHAR(50) NOT NULL,
  room_type VARCHAR(50) NOT NULL,
  floor INT DEFAULT 0,
  position_x FLOAT DEFAULT 0,
  position_z FLOAT DEFAULT 0,
  size_x FLOAT DEFAULT 5,
  size_z FLOAT DEFAULT 5,
  color_hex VARCHAR(7) DEFAULT '#1f2937',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
  UNIQUE KEY unique_room (house_id, room_name)
);

INSERT IGNORE INTO house_rooms (house_id, room_name, room_type, floor, position_x, position_z, size_x, size_z, color_hex)
VALUES
  (1, 'living_room', 'living', 0, 0, 0, 8, 10, '#2d1b4e'),
  (1, 'bedroom', 'sleeping', 1, 0, 12, 8, 8, '#1a1a2e'),
  (1, 'kitchen', 'cooking', 0, 10, 0, 6, 8, '#0f3d1b'),
  (1, 'bathroom', 'bathroom', 1, 10, 12, 4, 5, '#1b3a5a');
