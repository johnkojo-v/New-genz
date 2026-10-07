<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class MetaverseSocket implements MessageComponentInterface
{
    protected $clients;
    protected $players = [];

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);

        $resourceId = $conn->resourceId;
        $this->players[$resourceId] = [
            'id' => $resourceId,
            'username' => 'guest',
            'x' => 0,
            'z' => 0,
            'yaw' => 0,
            'world_id' => 1
        ];

        $this->broadcastState();
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!is_array($data)) {
            return;
        }

        $resourceId = $from->resourceId;
        if (!isset($this->players[$resourceId])) {
            return;
        }

        switch ($data['type'] ?? '') {
            case 'join':
                $username = trim((string) ($data['username'] ?? 'guest'));
                $this->players[$resourceId]['username'] = $username !== '' ? $username : 'guest';
                break;

            case 'move':
                $this->players[$resourceId]['x'] = (float) ($data['x'] ?? 0);
                $this->players[$resourceId]['z'] = (float) ($data['z'] ?? 0);
                $this->players[$resourceId]['yaw'] = (float) ($data['yaw'] ?? 0);
                $this->players[$resourceId]['world_id'] = (int) ($data['world_id'] ?? 1);
                break;

            case 'chat':
                $this->broadcast([
                    'type' => 'chat',
                    'username' => $this->players[$resourceId]['username'],
                    'text' => (string) ($data['text'] ?? '')
                ]);
                return;

            default:
                break;
        }

        $this->broadcastState();
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $resourceId = $conn->resourceId;

        if (isset($this->players[$resourceId])) {
            unset($this->players[$resourceId]);
        }

        $this->clients->detach($conn);
        $this->broadcastState();
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function broadcastState(): void
    {
        $payload = [
            'type' => 'state',
            'players' => array_values($this->players)
        ];

        $this->broadcast($payload);
    }

    protected function broadcast(array $payload): void
    {
        $json = json_encode($payload);

        foreach ($this->clients as $client) {
            $client->send($json);
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new MetaverseSocket()
        )
    ),
    8080
);

echo "Metaverse WebSocket server running on ws://localhost:8080\n";
$server->run();
