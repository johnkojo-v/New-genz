# NewGenz Metaverse

A PHP + Three.js metaverse starter with:
- login/register system
- 3D avatar movement
- WebSocket multiplayer
- world selection and multiple environments
- PWA installability

## Stack
- PHP 8+
- MySQL
- Ratchet WebSockets
- Three.js
- Service Worker + Manifest for installable app

## Setup

1. Create MySQL database and import schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Start the web app:
   ```bash
   php -S localhost:80
   ```

4. Start the WebSocket server:
   ```bash
   php backend/websocket_server.php
   ```

5. Open:
   ```text
   http://localhost/
   ```

## Included features
- User registration and login
- 3D world rendering with camera controls
- Basic multiplayer avatars
- In-world chat
- Multiple worlds / world switching
- Installable web app shell
