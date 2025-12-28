<?php
require_once 'db.php';
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$resource = $request[0] ?? '';
$id = $request[1] ?? null;

// Check if user is logged in for protected routes
$user_id = $_SESSION['user_id'] ?? null;

switch ($resource) {
    case 'desks':
        handleDesks($method, $id, $user_id);
        break;
    case 'videos':
        handleVideos($method, $id, $user_id);
        break;
    case 'users':
        handleUsers($method, $id);
        break;
    case 'shares':
        handleShares($method, $id, $user_id);
        break;
    default:
        echo json_encode(['error' => 'Invalid resource']);
        break;
}

function handleDesks($method, $id, $user_id) {
    global $pdo;

    if (!$user_id) {
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                // First check if user has access to this desk
                $accessStmt = $pdo->prepare("
                    SELECT d.id, d.user_id, d.is_public,
                           CASE WHEN d.user_id = ? THEN TRUE
                                WHEN ds.shared_with_user_id = ? AND ds.can_edit = TRUE THEN TRUE
                                ELSE FALSE END as can_edit
                    FROM desks d
                    LEFT JOIN desk_shares ds ON d.id = ds.desk_id AND ds.shared_with_user_id = ?
                    WHERE d.id = ? AND (d.user_id = ? OR d.is_public = TRUE OR ds.id IS NOT NULL)
                    LIMIT 1
                ");
                $accessStmt->execute([$user_id, $user_id, $user_id, $id, $user_id]);
                $accessCheck = $accessStmt->fetch();

                if (!$accessCheck) {
                    echo json_encode(['error' => 'Desk not found or access denied']);
                    return;
                }

                // Get desk with owner info
                $deskStmt = $pdo->prepare("
                    SELECT d.*, u.username as owner_username
                    FROM desks d
                    LEFT JOIN users u ON d.user_id = u.id
                    WHERE d.id = ?
                ");
                $deskStmt->execute([$id]);
                $desk = $deskStmt->fetch();

                if ($desk) {
                    // Get videos for this desk
                    $videosStmt = $pdo->prepare("SELECT id, title, url, created_at FROM videos WHERE desk_id = ? ORDER BY created_at");
                    $videosStmt->execute([$id]);
                    $desk['videos'] = $videosStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Set can_edit permission
                    $desk['can_edit'] = $accessCheck['can_edit'];
                }
                echo json_encode($desk);
            } else {
                // Get all accessible desks with video counts
                $stmt = $pdo->prepare("
                    SELECT d.*, u.username as owner_username, COUNT(v.id) as video_count,
                           CASE WHEN d.user_id = ? THEN TRUE
                                WHEN ds.shared_with_user_id = ? AND ds.can_edit = TRUE THEN TRUE
                                ELSE FALSE END as can_edit
                    FROM desks d
                    LEFT JOIN users u ON d.user_id = u.id
                    LEFT JOIN videos v ON d.id = v.desk_id
                    LEFT JOIN desk_shares ds ON d.id = ds.desk_id AND ds.shared_with_user_id = ?
                    WHERE d.user_id = ? OR d.is_public = TRUE OR ds.id IS NOT NULL
                    GROUP BY d.id
                    ORDER BY d.created_at DESC
                ");
                $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
                $desks = $stmt->fetchAll();
                echo json_encode($desks);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO desks (user_id, name, description, is_public) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $data['name'], $data['description'], $data['is_public'] ?? false]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'message' => 'Desk created']);
            break;

        case 'PUT':
            if (!$id) {
                echo json_encode(['error' => 'Desk ID required']);
                return;
            }
            // Check if user can edit this desk
            $stmt = $pdo->prepare("
                SELECT d.id FROM desks d
                LEFT JOIN desk_shares ds ON d.id = ds.desk_id AND ds.shared_with_user_id = ? AND ds.can_edit = TRUE
                WHERE d.id = ? AND (d.user_id = ? OR ds.id IS NOT NULL)
            ");
            $stmt->execute([$user_id, $id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Permission denied']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE desks SET name = ?, description = ?, is_public = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['description'], $data['is_public'] ?? false, $id]);
            echo json_encode(['message' => 'Desk updated']);
            break;

        case 'DELETE':
            if (!$id) {
                echo json_encode(['error' => 'Desk ID required']);
                return;
            }
            // Check if user owns this desk
            $stmt = $pdo->prepare("SELECT id FROM desks WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Permission denied']);
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

function handleVideos($method, $id, $user_id) {
    global $pdo;

    if (!$user_id) {
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single video - check desk access
                $stmt = $pdo->prepare("
                    SELECT v.* FROM videos v
                    JOIN desks d ON v.desk_id = d.id
                    LEFT JOIN desk_shares ds ON d.id = ds.desk_id AND ds.shared_with_user_id = ?
                    WHERE v.id = ? AND (d.user_id = ? OR d.is_public = TRUE OR ds.id IS NOT NULL)
                ");
                $stmt->execute([$user_id, $id, $user_id]);
                echo json_encode($stmt->fetch());
            } else {
                // Get videos for a desk - check desk access
                $deskId = $_GET['desk_id'] ?? null;
                if ($deskId) {
                    $stmt = $pdo->prepare("
                        SELECT v.* FROM videos v
                        JOIN desks d ON v.desk_id = d.id
                        LEFT JOIN desk_shares ds ON d.id = ds.desk_id AND ds.shared_with_user_id = ?
                        WHERE v.desk_id = ? AND (d.user_id = ? OR d.is_public = TRUE OR ds.id IS NOT NULL)
                        ORDER BY v.created_at
                    ");
                    $stmt->execute([$user_id, $deskId, $user_id]);
                    echo json_encode($stmt->fetchAll());
                } else {
                    echo json_encode(['error' => 'desk_id parameter required']);
                }
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            // Check if user can edit this desk
            $stmt = $pdo->prepare("
                SELECT d.id FROM desks d
                LEFT JOIN desk_shares ds ON d.id = ds.desk_id AND ds.shared_with_user_id = ? AND ds.can_edit = TRUE
                WHERE d.id = ? AND (d.user_id = ? OR ds.id IS NOT NULL)
            ");
            $stmt->execute([$user_id, $data['desk_id'], $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Permission denied']);
                return;
            }

            $stmt = $pdo->prepare("INSERT INTO videos (desk_id, title, url) VALUES (?, ?, ?)");
            $stmt->execute([$data['desk_id'], $data['title'], $data['url']]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'message' => 'Video added']);
            break;

        case 'PUT':
            if (!$id) {
                echo json_encode(['error' => 'Video ID required']);
                return;
            }
            // Check if user can edit this video's desk
            $stmt = $pdo->prepare("
                SELECT v.id FROM videos v
                JOIN desks d ON v.desk_id = d.id
                LEFT JOIN desk_shares ds ON d.id = ds.desk_id AND ds.shared_with_user_id = ? AND ds.can_edit = TRUE
                WHERE v.id = ? AND (d.user_id = ? OR ds.id IS NOT NULL)
            ");
            $stmt->execute([$user_id, $id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Permission denied']);
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
            // Check if user can edit this video's desk
            $stmt = $pdo->prepare("
                SELECT v.id FROM videos v
                JOIN desks d ON v.desk_id = d.id
                LEFT JOIN desk_shares ds ON d.id = ds.desk_id AND ds.shared_with_user_id = ? AND ds.can_edit = TRUE
                WHERE v.id = ? AND (d.user_id = ? OR ds.id IS NOT NULL)
            ");
            $stmt->execute([$user_id, $id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Permission denied']);
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

function handleUsers($method, $id) {
    global $pdo;

    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid request data']);
                return;
            }

            $username = trim($data['username']);
            $email = trim($data['email']);
            $password = $data['password'];

            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
                return;
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password_hash]);

            echo json_encode(['success' => true, 'message' => 'User registered successfully']);
            break;

        default:
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handleShares($method, $id, $user_id) {
    global $pdo;

    if (!$user_id) {
        echo json_encode(['error' => 'Authentication required']);
        return;
    }

    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['desk_id']) || !isset($data['username'])) {
                echo json_encode(['error' => 'Invalid request data']);
                return;
            }

            // Check if user owns the desk
            $stmt = $pdo->prepare("SELECT id FROM desks WHERE id = ? AND user_id = ?");
            $stmt->execute([$data['desk_id'], $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Permission denied']);
                return;
            }

            // Find user to share with
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            $sharedUser = $stmt->fetch();
            if (!$sharedUser) {
                echo json_encode(['error' => 'User not found']);
                return;
            }

            // Share the desk
            $stmt = $pdo->prepare("INSERT INTO desk_shares (desk_id, shared_with_user_id, can_edit) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_edit = ?");
            $stmt->execute([$data['desk_id'], $sharedUser['id'], $data['can_edit'] ?? false, $data['can_edit'] ?? false]);

            echo json_encode(['message' => 'Desk shared successfully']);
            break;

        case 'DELETE':
            if (!$id) {
                echo json_encode(['error' => 'Share ID required']);
                return;
            }
            // Check if user owns the desk being unshared
            $stmt = $pdo->prepare("
                SELECT ds.id FROM desk_shares ds
                JOIN desks d ON ds.desk_id = d.id
                WHERE ds.id = ? AND d.user_id = ?
            ");
            $stmt->execute([$id, $user_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Permission denied']);
                return;
            }

            $stmt = $pdo->prepare("DELETE FROM desk_shares WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['message' => 'Share removed']);
            break;

        default:
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}
?>