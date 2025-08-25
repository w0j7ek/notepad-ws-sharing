<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NotesRealtimeServer implements MessageComponentInterface
{
    protected $rooms = []; // room_key => [resourceId => conn]
    protected $notesByRoom = []; // room_key => note (['note_id', 'title', 'content'])
    protected $usersByRoom = []; // room_key => [resourceId => ['user_id', 'user_name', 'user_type']]

    public function onOpen(ConnectionInterface $conn)
    {
        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);

        if (!empty($params['room_key'])) {
            $roomKey = $params['room_key'];
            $this->rooms[$roomKey][$conn->resourceId] = $conn;
            if (isset($this->notesByRoom[$roomKey])) {
                $note = $this->notesByRoom[$roomKey];
                $conn->send(json_encode([
                    'type' => 'note_update',
                    'note_id' => $note['note_id'],
                    'title' => $note['title'],
                    'content' => $note['content']
                ]));
            }
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);


        if (!empty($data['room_key']) && $data['type'] === 'join') {
            $roomKey = $data['room_key'];
            $this->usersByRoom[$roomKey][$from->resourceId] = [
                'user_id' => $data['user_id'],
                'user_name' => $data['user_name'],
                'user_type' => $data['user_type']
            ];

            $this->broadcastUsersList($roomKey);
        }

        if (!empty($data['room_key']) && $data['type'] === 'note_update') {
            $roomKey = $data['room_key'];
            $this->notesByRoom[$roomKey] = [
                'note_id' => $data['note_id'],
                'title' => $data['title'],
                'content' => $data['content']
            ];
            foreach ($this->rooms[$roomKey] ?? [] as $client) {
                $client->send(json_encode([
                    'type' => 'note_update',
                    'note_id' => $data['note_id'],
                    'title' => $data['title'],
                    'content' => $data['content']
                ]));
            }
        }

        if (!empty($data['room_key']) && $data['type'] === 'edit_permission_changed') {
            $roomKey = $data['room_key'];
            $can_edit = $data['can_edit'];
            foreach ($this->rooms[$roomKey] ?? [] as $client) {
                $client->send(json_encode([
                    'type' => 'edit_permission_changed',
                    'can_edit' => $can_edit
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        foreach ($this->rooms as $roomKey => $conns) {
            if (isset($this->rooms[$roomKey][$conn->resourceId])) {
                unset($this->rooms[$roomKey][$conn->resourceId]);
            }
            if (isset($this->usersByRoom[$roomKey][$conn->resourceId])) {
                unset($this->usersByRoom[$roomKey][$conn->resourceId]);
                $this->broadcastUsersList($roomKey);
            }
            if (empty($this->rooms[$roomKey]))
                unset($this->rooms[$roomKey]);
            if (empty($this->usersByRoom[$roomKey]))
                unset($this->usersByRoom[$roomKey]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    protected function broadcastUsersList($roomKey)
    {
        $users = array_values($this->usersByRoom[$roomKey] ?? []);
        foreach ($this->rooms[$roomKey] ?? [] as $client) {
            $client->send(json_encode([
                'type' => 'users_list',
                'users' => $users
            ]));
        }
    }
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NotesRealtimeServer()
        )
    ),
    8080
);

echo "Ratchet WebSocket server started on port 8080\n";
$server->run();