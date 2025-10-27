<?php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$resource = $request[0] ?? '';
$id = $request[1] ?? null;

switch ($resource) {
    case 'desks':
        handleDesks($method, $id);
        break;
    case 'videos':
        handleVideos($method, $id);
        break;
    default:
        echo json_encode(['error' => 'Invalid resource']);
        break;
}

function handleDesks($method, $id) {
    global $pdo;

    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single desk with videos
                $stmt = $pdo->prepare("SELECT d.*, JSON_ARRAYAGG(JSON_OBJECT('id', v.id, 'title', v.title, 'url', v.url)) as videos FROM desks d LEFT JOIN videos v ON d.id = v.desk_id WHERE d.id = ? GROUP BY d.id");
                $stmt->execute([$id]);
                $desk = $stmt->fetch();
                if ($desk) {
                    $desk['videos'] = json_decode($desk['videos']);
                    if ($desk['videos'][0]->id === null) {
                        $desk['videos'] = [];
                    }
                }
                echo json_encode($desk);
            } else {
                // Get all desks with video counts
                $stmt = $pdo->query("SELECT d.*, COUNT(v.id) as video_count FROM desks d LEFT JOIN videos v ON d.id = v.desk_id GROUP BY d.id ORDER BY d.created_at DESC");
                $desks = $stmt->fetchAll();
                echo json_encode($desks);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO desks (name, description) VALUES (?, ?)");
            $stmt->execute([$data['name'], $data['description']]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'message' => 'Desk created']);
            break;

        case 'PUT':
            if (!$id) {
                echo json_encode(['error' => 'Desk ID required']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE desks SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['description'], $id]);
            echo json_encode(['message' => 'Desk updated']);
            break;

        case 'DELETE':
            if (!$id) {
                echo json_encode(['error' => 'Desk ID required']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM desks WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['message' => 'Desk deleted']);
            break;

        default:
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handleVideos($method, $id) {
    global $pdo;

    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single video
                $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode($stmt->fetch());
            } else {
                // Get videos for a desk
                $deskId = $_GET['desk_id'] ?? null;
                if ($deskId) {
                    $stmt = $pdo->prepare("SELECT * FROM videos WHERE desk_id = ? ORDER BY created_at");
                    $stmt->execute([$deskId]);
                    echo json_encode($stmt->fetchAll());
                } else {
                    echo json_encode(['error' => 'desk_id parameter required']);
                }
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO videos (desk_id, title, url) VALUES (?, ?, ?)");
            $stmt->execute([$data['desk_id'], $data['title'], $data['url']]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'message' => 'Video added']);
            break;

        case 'PUT':
            if (!$id) {
                echo json_encode(['error' => 'Video ID required']);
                return;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE videos SET title = ?, url = ? WHERE id = ?");
            $stmt->execute([$data['title'], $data['url'], $id]);
            echo json_encode(['message' => 'Video updated']);
            break;

        case 'DELETE':
            if (!$id) {
                echo json_encode(['error' => 'Video ID required']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['message' => 'Video deleted']);
            break;

        default:
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}
?>