<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require '../src/vendor/autoload.php';
$app = new \Slim\App;

$key = 'server_hack'; // Secret key for JWT

// Database connection function
function getConnection() {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "library";
    $port = 3306;
    return new PDO("mysql:host=$servername; port=$port; dbname=$dbname", $username, $password);
}

// Token generation function
function generateToken($userid, $key) {
    $iat = time();
    $exp = $iat + 3600; // 1 hour expiration
    $payload = [
        'iss' => 'http://library.org',
        'aud' => 'http://library.com',
        'iat' => $iat,
        'exp' => $exp,
        'data' => ['userid' => $userid]
    ];
    return JWT::encode($payload, $key, 'HS256');
}


// User registration endpoint
$app->post('/user/register', function (Request $request, Response $response) {
    $data = json_decode($request->getBody());
    $uname = $data->username;
    $pass = hash('sha256', $data->password);

    try {
        $conn = getConnection();
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = "INSERT INTO users (username, password) VALUES (:uname, :pass)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['uname' => $uname, 'pass' => $pass]);

        $response->getBody()->write(json_encode(["status" => "success", "data" => null]));
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(["status" => "fail", "data" => ["title" => $e->getMessage()]]));
    }

    return $response;
});

// User authentication endpoint
$app->post('/user/auth', function (Request $request, Response $response) use ($key) {
    $data = json_decode($request->getBody());
    $uname = $data->username;
    $pass = hash('sha256', $data->password);

    try {
        $conn = getConnection();
        $sql = "SELECT * FROM users WHERE username = :uname AND password = :pass";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['uname' => $uname, 'pass' => $pass]);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $user = $stmt->fetch();

        if ($user) {
            $jwt = generateToken($user['userid'], $key);

            // Store the token in the database
            $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['token' => $jwt, 'userid' => $user['userid'], 'exp' => time() + 3600]);

            $response->getBody()->write(json_encode(["status" => "success", "token" => $jwt, "data" => null]));
        } else {
            $response->getBody()->write(json_encode(["status" => "fail", "data" => ["title" => "Authentication Failed"]]));
        }
    } catch (PDOException $e) {
        $response->getBody()->write(json_encode(["status" => "fail", "data" => ["title" => $e->getMessage()]]));
    }

    return $response;
});

// Middleware to validate the token
$validateToken = function (Request $request, Response $response, $next) use ($key) {
    $authHeader = $request->getHeader('Authorization');
    $token = $authHeader ? trim(str_replace('Bearer', '', $authHeader[0])) : '';

    if (!$token) {
        return $response->withStatus(401)->write('Unauthorized: Token not provided');
    }

    try {
        $decoded = JWT::decode($token, new Key($key, 'HS256'));

        if (time() > $decoded->exp) {
            return $response->withStatus(401)->write('Token expired');
        }

        // Check if token is in the database and not used
        $conn = getConnection();
        $sql = "SELECT * FROM tokens WHERE token = :token AND used = 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['token' => $token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            return $response->withStatus(401)->write('Unauthorized or Token already used');
        }

        // Mark token as used
        $sql = "UPDATE tokens SET used = 1 WHERE token = :token";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['token' => $token]);

        $request = $request->withAttribute('userid', $decoded->data->userid);
        return $next($request, $response);
    } catch (Exception $e) {
        return $response->withStatus(401)->write('Unauthorized');
    }
};



// Helper function to remove expired tokens
function removeExpiredTokens() {
    try {
        $conn = getConnection();
        $sql = "DELETE FROM tokens WHERE exp < :current_time";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['current_time' => time()]);
    } catch (PDOException $e) {
        // Log error or handle it
    }
}


// Add new author
$app->post('/addauthors', function (Request $request, Response $response) use ($key) {
    removeExpiredTokens();  // Clean up expired tokens
    $userid = $request->getAttribute('userid');

    $data = json_decode($request->getBody());
    $name = $data->name;

    $conn = getConnection();
    $sql = "INSERT INTO authors (name) VALUES (:name)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':name' => $name]);

    // Generate new token for further requests
    $new_jwt = generateToken($userid, $key);
    
    // Store the new token
    $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $new_jwt, 'userid' => $userid, 'exp' => time() + 3600]);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $new_jwt,
        "data" => null
    ]));
    return $response;
})->add($validateToken);

// Update author by ID
$app->put('/updateauthors/{id}', function (Request $request, Response $response, array $args) use ($key) {
    removeExpiredTokens();  // Clean up expired tokens
    $userid = $request->getAttribute('userid');

    $id = $args['id'];
    $data = json_decode($request->getBody());
    $name = $data->name;

    $conn = getConnection();
    $sql = "UPDATE authors SET name = :name WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':name' => $name, ':id' => $id]);

    // Generate new token for further requests
    $new_jwt = generateToken($userid, $key);

    // Store the new token
    $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $new_jwt, 'userid' => $userid, 'exp' => time() + 3600]);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $new_jwt,
        "data" => null
    ]));
    return $response;
})->add($validateToken);

// Get all authors
$app->get('/getauthors', function (Request $request, Response $response) use ($key) {
    removeExpiredTokens();  // Clean up expired tokens
    $userid = $request->getAttribute('userid');

    $conn = getConnection();
    $sql = "SELECT * FROM authors";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate new token for further requests
    $new_jwt = generateToken($userid, $key);

    // Store the new token
    $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $new_jwt, 'userid' => $userid, 'exp' => time() + 3600]);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $new_jwt,
        "data" => $authors
    ]));
    return $response;
})->add($validateToken);

// Delete author by ID
$app->delete('/deleteauthors/{id}', function (Request $request, Response $response, array $args) use ($key) {
    removeExpiredTokens();  // Clean up expired tokens
    $userid = $request->getAttribute('userid');

    $id = $args['id'];

    $conn = getConnection();
    $sql = "DELETE FROM authors WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);

    // Generate new token for further requests
    $new_jwt = generateToken($userid, $key);

    // Store the new token
    $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $new_jwt, 'userid' => $userid, 'exp' => time() + 3600]);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $new_jwt,
        "data" => null
    ]));
    return $response;
})->add($validateToken);


// Add new book
$app->post('/addbooks', function (Request $request, Response $response) use ($key) {
    removeExpiredTokens();  // Clean up expired tokens
    $userid = $request->getAttribute('userid');

    $data = json_decode($request->getBody());
    $title = $data->title;
    $author_id = $data->author_id;

    $conn = getConnection();
    $sql = "INSERT INTO books (title, author_id) VALUES (:title, :author_id)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':title' => $title, ':author_id' => $author_id]);

    // Generate new token for further requests
    $new_jwt = generateToken($userid, $key);

    // Store the new token
    $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $new_jwt, 'userid' => $userid, 'exp' => time() + 3600]);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $new_jwt,
        "data" => null
    ]));
    return $response;
})->add($validateToken);

// Update book by ID
$app->put('/updatebooks/{id}', function (Request $request, Response $response, array $args) use ($key) {
    removeExpiredTokens();  // Clean up expired tokens
    $userid = $request->getAttribute('userid');

    $id = $args['id'];
    $data = json_decode($request->getBody());
    $title = $data->title;
    $author_id = $data->author_id;

    $conn = getConnection();
    $sql = "UPDATE books SET title = :title, author_id = :author_id WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':title' => $title, ':author_id' => $author_id, ':id' => $id]);

    // Generate new token for further requests
    $new_jwt = generateToken($userid, $key);

    // Store the new token
    $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $new_jwt, 'userid' => $userid, 'exp' => time() + 3600]);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $new_jwt,
        "data" => null
    ]));
    return $response;
})->add($validateToken);

// Get all books
$app->get('/getbooks', function (Request $request, Response $response) use ($key) {
    removeExpiredTokens();  // Clean up expired tokens
    $userid = $request->getAttribute('userid');

    $conn = getConnection();
    $sql = "SELECT * FROM books";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate new token for further requests
    $new_jwt = generateToken($userid, $key);

    // Store the new token
    $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $new_jwt, 'userid' => $userid, 'exp' => time() + 3600]);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $new_jwt,
        "data" => $books
    ]));
    return $response;
})->add($validateToken);

// Delete book by ID
$app->delete('/deletebooks/{id}', function (Request $request, Response $response, array $args) use ($key) {
    removeExpiredTokens();  // Clean up expired tokens
    $userid = $request->getAttribute('userid');

    $id = $args['id'];

    $conn = getConnection();
    $sql = "DELETE FROM books WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);

    // Generate new token for further requests
    $new_jwt = generateToken($userid, $key);

    // Store the new token
    $sql = "INSERT INTO tokens (token, userid, exp, used) VALUES (:token, :userid, :exp, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['token' => $new_jwt, 'userid' => $userid, 'exp' => time() + 3600]);

    $response->getBody()->write(json_encode([
        "status" => "success",
        "token" => $new_jwt,
        "data" => null
    ]));
    return $response;
})->add($validateToken);


$app->run();
?>