ALTER TABLE users
  ADD COLUMN avatar_skin VARCHAR(20) DEFAULT 'light',
  ADD COLUMN avatar_outfit VARCHAR(20) DEFAULT 'blue',
  ADD COLUMN avatar_style VARCHAR(20) DEFAULT 'classic',
  ADD COLUMN avatar_accessory VARCHAR(20) DEFAULT 'none';
