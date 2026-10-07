import * as THREE from 'https://unpkg.com/three@0.159.0/build/three.module.js';

const canvas = document.querySelector('#scene');
const statusText = document.querySelector('#status');
const installButton = document.querySelector('#installButton');
const logoutButton = document.querySelector('#logoutButton');
const authPanel = document.querySelector('#authPanel');
const authTabs = document.querySelectorAll('.tab');
const loginForm = document.querySelector('#loginForm');
const registerForm = document.querySelector('#registerForm');
const authMessage = document.querySelector('#authMessage');
const chatMessages = document.querySelector('#chatMessages');
const chatForm = document.querySelector('#chatForm');
const chatInput = document.querySelector('#chatInput');
const worldPanel = document.querySelector('#worldPanel');
const worldList = document.querySelector('#worldList');
const avatarForm = document.querySelector('#avatarForm');
const skinToneSelect = document.querySelector('#skinTone');
const outfitColorSelect = document.querySelector('#outfitColor');
const avatarStyleSelect = document.querySelector('#avatarStyle');
const avatarAccessorySelect = document.querySelector('#avatarAccessory');
const presenceList = document.querySelector('#presenceList');

let deferredPrompt = null;
let currentUser = null;
let socket = null;
let myPlayerId = null;
let remoteAvatars = new Map();
let lastMovementSentAt = 0;
let worldData = [];
let activeWorldId = 1;
let accessoryMesh = null;

const avatarConfig = {
  skin: 'light',
  outfit: 'blue',
  style: 'classic',
  accessory: 'none'
};

const SKIN_COLORS = {
  light: '#f4d7b5',
  tan: '#d9a066',
  brown: '#8b5e3c',
  dark: '#4a2d20'
};

const OUTFIT_COLORS = {
  blue: '#7dd3fc',
  purple: '#a78bfa',
  green: '#34d399',
  red: '#f87171',
  gold: '#fbbf24'
};

const styleScale = {
  classic: 1,
  hero: 1.12,
  street: 0.94,
  royal: 1.08
};

const accessoryModels = {
  none: null,
  glasses: { width: 0.5, height: 0.12, y: 1.5 },
  hat: { width: 0.7, height: 0.22, y: 1.95 },
  visor: { width: 0.6, height: 0.12, y: 1.65 }
};

statusText.textContent = 'Initializing 3D world...';

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0b1020);

const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
camera.position.set(0, 3, 10);

const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(window.innerWidth, window.innerHeight);

const ambientLight = new THREE.AmbientLight(0xffffff, 1.1);
scene.add(ambientLight);

const sunLight = new THREE.DirectionalLight(0xffffff, 1.2);
sunLight.position.set(5, 10, 7);
scene.add(sunLight);

const ground = new THREE.Mesh(
  new THREE.PlaneGeometry(100, 100),
  new THREE.MeshStandardMaterial({ color: 0x1f2937 })
);
ground.rotation.x = -Math.PI / 2;
scene.add(ground);

const player = {
  x: 0,
  z: 0,
  yaw: 0,
  pitch: -0.25,
  speed: 4
};

const avatar = new THREE.Group();
const body = new THREE.Mesh(
  new THREE.CapsuleGeometry(0.6, 1.4, 4, 8),
  new THREE.MeshStandardMaterial({ color: 0x7dd3fc })
);
avatar.add(body);

const head = new THREE.Mesh(
  new THREE.SphereGeometry(0.38, 24, 24),
  new THREE.MeshStandardMaterial({ color: 0xf4d7b5 })
);
head.position.y = 1.5;
avatar.add(head);
scene.add(avatar);

const clock = new THREE.Clock();
const keys = {};

function loadAvatarConfig() {
  const saved = localStorage.getItem('newgenz-avatar');
  if (!saved) {
    applyAvatarStyle(avatarConfig);
    return;
  }

  try {
    const parsed = JSON.parse(saved);
    Object.assign(avatarConfig, parsed);
    skinToneSelect.value = avatarConfig.skin;
    outfitColorSelect.value = avatarConfig.outfit;
    avatarStyleSelect.value = avatarConfig.style;
    avatarAccessorySelect.value = avatarConfig.accessory;
    applyAvatarStyle(avatarConfig);
  } catch (error) {
    applyAvatarStyle(avatarConfig);
  }
}

function saveAvatarConfig() {
  localStorage.setItem('newgenz-avatar', JSON.stringify(avatarConfig));
}

function applyAvatarStyle(config) {
  body.material.color.set(OUTFIT_COLORS[config.outfit] || '#7dd3fc');
  head.material.color.set(SKIN_COLORS[config.skin] || '#f4d7b5');
  avatar.scale.setScalar(styleScale[config.style] || 1);

  if (accessoryMesh) {
    avatar.remove(accessoryMesh);
    accessoryMesh = null;
  }

  const accessory = accessoryModels[config.accessory];
  if (accessory) {
    const mesh = new THREE.Mesh(
      new THREE.BoxGeometry(accessory.width, accessory.height, 0.12),
      new THREE.MeshStandardMaterial({ color: 0x111827 })
    );
    mesh.position.set(0, accessory.y, 0.42);
    accessoryMesh = mesh;
    avatar.add(mesh);
  }

  switch (config.style) {
    case 'hero':
      body.scale.set(1.08, 1.1, 1.08);
      break;
    case 'street':
      body.scale.set(0.96, 0.94, 1);
      break;
    case 'royal':
      body.scale.set(1.04, 1.12, 1.04);
      break;
    default:
      body.scale.set(1, 1, 1);
      break;
  }
}

async function loadUserAvatarProfile() {
  try {
    const response = await fetch('/api/avatar');
    const data = await response.json();

    if (!response.ok || !data.success) {
      return;
    }

    const profile = data.profile || {};
    avatarConfig.skin = profile.avatar_skin || avatarConfig.skin;
    avatarConfig.outfit = profile.avatar_outfit || avatarConfig.outfit;
    avatarConfig.style = profile.avatar_style || avatarConfig.style;
    avatarConfig.accessory = profile.avatar_accessory || avatarConfig.accessory;

    skinToneSelect.value = avatarConfig.skin;
    outfitColorSelect.value = avatarConfig.outfit;
    avatarStyleSelect.value = avatarConfig.style;
    avatarAccessorySelect.value = avatarConfig.accessory;

    applyAvatarStyle(avatarConfig);
    saveAvatarConfig();
  } catch (error) {
    console.warn('Unable to load avatar profile', error);
  }
}

avatarForm.addEventListener('submit', async (event) => {
  event.preventDefault();

  avatarConfig.skin = skinToneSelect.value;
  avatarConfig.outfit = outfitColorSelect.value;
  avatarConfig.style = avatarStyleSelect.value;
  avatarConfig.accessory = avatarAccessorySelect.value;

  applyAvatarStyle(avatarConfig);
  saveAvatarConfig();

  try {
    const response = await fetch('/api/avatar/save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        avatar_skin: avatarConfig.skin,
        avatar_outfit: avatarConfig.outfit,
        avatar_style: avatarConfig.style,
        avatar_accessory: avatarConfig.accessory
      })
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Failed to save avatar');
    }

    setAuthMessage('Avatar saved to profile');
  } catch (error) {
    setAuthMessage(error.message, true);
  }
});

function renderPresence(players) {
  presenceList.innerHTML = '';

  players.forEach((playerData) => {
    const li = document.createElement('li');
    li.textContent = playerData.username || 'guest';
    presenceList.appendChild(li);
  });
}

function setWorldAppearance(world) {
  if (!world) return;

  scene.background = new THREE.Color(world.sky_color || '#0b1020');

  const terrainMap = {
    grass: 0x1f2937,
    mountain: 0x3b4a5a,
    urban: 0x2d1b4e,
    forest: 0x0f3d1b
  };

  ground.material.color.setHex(terrainMap[world.terrain_type] || 0x1f2937);
  avatar.position.set(world.spawn_x || 0, 0, world.spawn_z || 0);
  player.x = Number(world.spawn_x || 0);
  player.z = Number(world.spawn_z || 0);
  statusText.textContent = `World: ${world.name}`;
}

function renderWorldButtons(worlds) {
  worldList.innerHTML = '';

  worlds.forEach((world) => {
    const button = document.createElement('button');
    button.className = 'world-button' + (Number(world.id) === Number(activeWorldId) ? ' active' : '');
    button.textContent = world.name;
    button.addEventListener('click', async () => {
      if (!currentUser) {
        setAuthMessage('Please log in first', true);
        return;
      }

      await joinWorld(Number(world.id));
    });
    worldList.appendChild(button);
  });
}

async function loadWorlds() {
  try {
    const response = await fetch('/api/worlds');
    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Unable to load worlds');
    }

    worldData = data.worlds || [];
    if (worldData.length > 0) {
      activeWorldId = Number(worldData[0].id);
      renderWorldButtons(worldData);
      setWorldAppearance(worldData[0]);
    }
  } catch (error) {
    console.warn('Worlds not available:', error.message);
  }
}

async function joinWorld(worldId) {
  try {
    const response = await fetch('/api/worlds/join', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ world_id: worldId })
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Unable to join world');
    }

    activeWorldId = Number(worldId);
    const world = worldData.find((item) => Number(item.id) === Number(worldId));
    renderWorldButtons(worldData);
    setWorldAppearance(world || worldData[0]);

    if (socket && socket.readyState === WebSocket.OPEN) {
      socket.send(JSON.stringify({
        type: 'move',
        x: player.x,
        z: player.z,
        yaw: player.yaw,
        world_id: activeWorldId
      }));
    }
  } catch (error) {
    setAuthMessage(error.message, true);
  }
}

document.addEventListener('keydown', (event) => {
  const key = event.key.toLowerCase();
  if (['w', 'a', 's', 'd', 'arrowup', 'arrowdown', 'arrowleft', 'arrowright'].includes(key)) {
    event.preventDefault();
  }
  keys[key] = true;
});

document.addEventListener('keyup', (event) => {
  keys[event.key.toLowerCase()] = false;
});

canvas.addEventListener('click', () => {
  if (document.pointerLockElement !== canvas) {
    canvas.requestPointerLock();
  }
});

document.addEventListener('mousemove', (event) => {
  if (document.pointerLockElement !== canvas) return;

  player.yaw -= event.movementX * 0.0024;
  player.pitch = THREE.MathUtils.clamp(
    player.pitch - event.movementY * 0.0016,
    -1.1,
    1.1
  );
});

function updateMovement(delta) {
  let forward = 0;
  let strafe = 0;

  if (keys.w || keys.arrowup) forward += 1;
  if (keys.s || keys.arrowdown) forward -= 1;
  if (keys.d || keys.arrowright) strafe += 1;
  if (keys.a || keys.arrowleft) strafe -= 1;

  if (forward === 0 && strafe === 0) return;

  const magnitude = Math.hypot(forward, strafe) || 1;
  const nx = forward / magnitude;
  const nz = strafe / magnitude;

  const moveX = Math.sin(player.yaw) * nx + Math.cos(player.yaw) * nz;
  const moveZ = Math.cos(player.yaw) * nx - Math.sin(player.yaw) * nz;

  player.x += moveX * player.speed * delta;
  player.z += moveZ * player.speed * delta;

  avatar.position.x = player.x;
  avatar.position.z = player.z;
  avatar.rotation.y = player.yaw;
}

function updateCamera() {
  const target = new THREE.Vector3(player.x, 1.65, player.z);
  const desiredPosition = new THREE.Vector3(
    player.x - Math.sin(player.yaw) * 6,
    3.2 + player.pitch * 2.0,
    player.z - Math.cos(player.yaw) * 6
  );

  camera.position.lerp(desiredPosition, 0.12);
  camera.lookAt(target);
}

function addChatMessage(username, text) {
  const item = document.createElement('div');
  item.className = 'chatMessage';
  item.textContent = `${username}: ${text}`;
  chatMessages.appendChild(item);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

chatForm.addEventListener('submit', (event) => {
  event.preventDefault();

  const text = chatInput.value.trim();
  if (!text || !socket || socket.readyState !== WebSocket.OPEN) {
    return;
  }

  socket.send(JSON.stringify({
    type: 'chat',
    text
  }));

  chatInput.value = '';
});

function createRemoteAvatar(id, username) {
  const group = new THREE.Group();

  const bodyMesh = new THREE.Mesh(
    new THREE.CapsuleGeometry(0.6, 1.4, 4, 8),
    new THREE.MeshStandardMaterial({ color: 0x34d399 })
  );
  group.add(bodyMesh);

  const headMesh = new THREE.Mesh(
    new THREE.SphereGeometry(0.38, 24, 24),
    new THREE.MeshStandardMaterial({ color: 0xf1f5f9 })
  );
  headMesh.position.y = 1.5;
  group.add(headMesh);

  group.userData = { id, username };
  group.position.set(0, 0, 0);
  scene.add(group);

  remoteAvatars.set(id, group);
  return group;
}

function updateRemotePlayers(players) {
  const activeIds = new Set();

  players.forEach((playerData) => {
    const id = playerData.id;

    if (String(id) === String(myPlayerId)) {
      return;
    }

    activeIds.add(String(id));

    if (!remoteAvatars.has(id)) {
      createRemoteAvatar(id, playerData.username || 'guest');
    }

    const remote = remoteAvatars.get(id);
    if (!remote) return;

    remote.position.set(playerData.x || 0, 0, playerData.z || 0);
    remote.rotation.y = playerData.yaw || 0;
  });

  for (const [id, avatarNode] of remoteAvatars.entries()) {
    if (!activeIds.has(String(id))) {
      scene.remove(avatarNode);
      remoteAvatars.delete(id);
    }
  }
}

function updateMultiplayerState() {
  if (!socket || socket.readyState !== WebSocket.OPEN) {
    return;
  }

  const now = performance.now();
  if (now - lastMovementSentAt < 80) {
    return;
  }

  lastMovementSentAt = now;

  socket.send(JSON.stringify({
    type: 'move',
    x: player.x,
    z: player.z,
    yaw: player.yaw,
    world_id: activeWorldId
  }));
}

function setupSocket() {
  const isSecure = location.protocol === 'https:';
  const url = `${isSecure ? 'wss' : 'ws'}://${location.hostname}:8080`;

  socket = new WebSocket(url);

  socket.addEventListener('open', () => {
    statusText.textContent = 'Multiplayer connected';
    const username = currentUser ? currentUser.username : 'guest';
    socket.send(JSON.stringify({
      type: 'join',
      username,
      world_id: activeWorldId
    }));
  });

  socket.addEventListener('message', (event) => {
    try {
      const payload = JSON.parse(event.data);
      if (!payload || typeof payload !== 'object') {
        return;
      }

      if (payload.type === 'state') {
        myPlayerId = payload.myId || myPlayerId;
        const players = (payload.players || []).filter((data) => Number(data.world_id) === Number(activeWorldId));
        renderPresence(players);
        updateRemotePlayers(players);
      }

      if (payload.type === 'chat') {
        addChatMessage(payload.username || 'guest', payload.text || '');
      }
    } catch (error) {
      console.warn('Invalid socket message:', error);
    }
  });

  socket.addEventListener('close', () => {
    statusText.textContent = 'Multiplayer disconnected';
  });

  socket.addEventListener('error', () => {
    statusText.textContent = 'Multiplayer error';
  });
}

function animate() {
  const delta = Math.min(clock.getDelta(), 0.033);

  updateMovement(delta);
  updateCamera();
  updateMultiplayerState();
  renderer.render(scene, camera);

  requestAnimationFrame(animate);
}

animate();

async function checkBackend() {
  try {
    const response = await fetch('/api/health');
    if (!response.ok) {
      throw new Error('Backend unavailable');
    }

    const data = await response.json();
    statusText.textContent = 'World ready: backend connected';
    console.log('Backend status:', data);
  } catch (error) {
    statusText.textContent = 'World ready: local offline mode';
    console.warn('Backend unavailable:', error.message);
  }
}

async function getCurrentUser() {
  try {
    const response = await fetch('/api/me', {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' }
    });

    if (!response.ok) {
      authPanel.classList.remove('hidden');
      return;
    }

    const data = await response.json();
    if (data.success && data.user) {
      currentUser = data.user;
      authPanel.classList.add('hidden');
      logoutButton.classList.remove('hidden');
      worldPanel.classList.remove('hidden');
      statusText.textContent = `Welcome, ${data.user.username}`;
      await loadUserAvatarProfile();
      setupSocket();
      await loadWorlds();
    }
  } catch (error) {
    authPanel.classList.remove('hidden');
    console.warn('User check failed:', error.message);
  }
}

function setAuthMessage(message, isError = false) {
  authMessage.textContent = message;
  authMessage.classList.toggle('error', isError);
}

authTabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    const mode = tab.dataset.mode;
    authTabs.forEach((btn) => btn.classList.toggle('active', btn === tab));
    loginForm.classList.toggle('hidden', mode !== 'login');
    registerForm.classList.toggle('hidden', mode !== 'register');
    setAuthMessage('');
  });
});

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();

  const formData = new FormData(loginForm);
  const payload = {
    login: formData.get('login'),
    password: formData.get('password')
  };

  try {
    const response = await fetch('/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Login failed');
    }

    currentUser = data.user;
    authPanel.classList.add('hidden');
    logoutButton.classList.remove('hidden');
    worldPanel.classList.remove('hidden');
    statusText.textContent = `Welcome, ${data.user.username}`;
    setAuthMessage('Login successful');

    await loadUserAvatarProfile();

    if (!socket) {
      setupSocket();
    } else if (socket.readyState === WebSocket.OPEN) {
      socket.send(JSON.stringify({
        type: 'join',
        username: currentUser.username,
        world_id: activeWorldId
      }));
    }

    await loadWorlds();
  } catch (error) {
    setAuthMessage(error.message, true);
  }
});

registerForm.addEventListener('submit', async (event) => {
  event.preventDefault();

  const formData = new FormData(registerForm);
  const payload = {
    username: formData.get('username'),
    email: formData.get('email'),
    password: formData.get('password')
  };

  try {
    const response = await fetch('/api/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Registration failed');
    }

    setAuthMessage('Account created! Please login.');
    registerForm.reset();
    authTabs.forEach((btn) => btn.classList.toggle('active', btn.dataset.mode === 'login'));
    loginForm.classList.remove('hidden');
    registerForm.classList.add('hidden');
  } catch (error) {
    setAuthMessage(error.message, true);
  }
});

logoutButton.addEventListener('click', async () => {
  try {
    const response = await fetch('/api/logout', {
      method: 'POST'
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Logout failed');
    }

    currentUser = null;
    authPanel.classList.remove('hidden');
    worldPanel.classList.add('hidden');
    logoutButton.classList.add('hidden');
    statusText.textContent = 'Logged out';
    setAuthMessage('Logged out successfully');

    if (socket && socket.readyState === WebSocket.OPEN) {
      socket.close();
      socket = null;
    }
  } catch (error) {
    setAuthMessage(error.message, true);
  }
});

window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  deferredPrompt = event;
  installButton.classList.remove('hidden');
});

installButton.addEventListener('click', async () => {
  if (!deferredPrompt) return;

  deferredPrompt.prompt();
  const choice = await deferredPrompt.userChoice;

  if (choice.outcome === 'accepted') {
    statusText.textContent = 'App install accepted';
  } else {
    statusText.textContent = 'App install dismissed';
  }

  deferredPrompt = null;
  installButton.classList.add('hidden');
});

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js').catch((err) => {
      console.error('Service Worker registration failed:', err);
    });
  });
}

loadAvatarConfig();
checkBackend();
getCurrentUser();
loadWorlds();
