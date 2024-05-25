<?php
require_once 'db.php';

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

function callAPI($method, $url, $data = false) {
    $curl = curl_init();
    switch ($method) {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "DELETE":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        default:
            if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    curl_close($curl);

    return json_decode($result, true);
}

function getAllClasses() {
    global $conn;
    $sql = "SELECT * FROM class";
    $result = pg_query($conn, $sql);
    $classes = array();
    if (pg_num_rows($result) > 0) {
        while ($row = pg_fetch_assoc($result)) {
            $classes[] = $row;
        }
    }
    return $classes;
}

function createClass($classCode, $className) {
    global $conn;

    $student_data = callAPI('GET', 'http://localhost:8080/student/getAll');
    $lecturer_data = callAPI('GET', 'http://localhost:5000/lecturer/getAll');

    $numberOfStudents = count(array_filter($student_data, function ($s) use ($classCode) {
        return $s['classCode'] === $classCode;
    }));

    $lecturerId = null;
    foreach ($lecturer_data as $lecturer) {
        if ($lecturer['classCode'] === $classCode) {
            $lecturerId = $lecturer['_id'];
            break;
        }
    }

    $lecturerIdPart = $lecturerId ? "'$lecturerId'" : "NULL";

    $sql = "INSERT INTO class (\"classCode\", \"className\", \"numberOfStudents\", \"lecturerId\") VALUES ('$classCode', '$className', $numberOfStudents, '$lecturerId')";
    $result = pg_query($conn, $sql);
    if (!$result) {
        return "Error: " . pg_last_error($conn);
    }

    return "New records created successfully";
}

function updateClass($classCode, $className = null) {
    global $conn;

    if ($className) {
        $sql = "UPDATE class SET \"className\" = '$className' WHERE \"classCode\" = '$classCode'";
        $result = pg_query($conn, $sql);
        if (!$result) {
            return "Error: " . pg_last_error($conn);
        }
        return "Class name updated successfully";
    } else {
        $sql = "SELECT \"numberOfStudents\" FROM class WHERE \"classCode\" = '$classCode'";
        $result = pg_query($conn, $sql);
        if (!$result) {
            return "Error: " . pg_last_error($conn);
        }
        $row = pg_fetch_assoc($result);
        $currentNumberOfStudents = $row['numberOfStudents'];
        $newNumberOfStudents = $currentNumberOfStudents + 1;

        $lecturer_data = callAPI('GET', 'http://localhost:5000/lecturer/getAll');
        $lecturerId = null;
        foreach ($lecturer_data as $lecturer) {
            if ($lecturer['classCode'] === $classCode) {
                $lecturerId = $lecturer['_id'];
                break;
            }
        }

        $sql = "UPDATE class SET \"numberOfStudents\" = $newNumberOfStudents WHERE \"classCode\" = '$classCode'";
        $sql = "UPDATE class SET \"lecturerId\" = '$lecturerId' WHERE \"classCode\" = '$classCode'";
        $result = pg_query($conn, $sql);
        if (!$result) {
            return "Error: " . pg_last_error($conn);
        }

        return "Class updated successfully";
    }
}

function deleteClass($classCode) {
    global $conn;

    $sql = "DELETE FROM class WHERE \"classCode\" = '$classCode'";
    $result = pg_query($conn, $sql);
    if (!$result) {
        return "Error: " . pg_last_error($conn);
    }

    return "Class deleted successfully";
}

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

if ($request_method === 'GET' && $request_uri === '/class/getAll') {
    $classes = getAllClasses();
    header('Content-Type: application/json');
    echo json_encode($classes, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} elseif ($request_method === 'POST' && $request_uri === '/class/add') {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    $classCode = $data['classCode'];
    $className = $data['className'];
    $response = createClass($classCode, $className);
    header('Content-Type: application/json');
    echo json_encode(["message" => $response]);
} elseif ($request_method === 'PUT' && preg_match('/\/class\/update\/([A-Za-z0-9]+)/', $request_uri, $matches)) {
    $classCode = $matches[1];
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    $className = $data['className'] ?? null;
    $response = updateClass($classCode, $className);
    header('Content-Type: application/json');
    echo json_encode(["message" => $response]);
} elseif ($request_method === 'DELETE' && preg_match('/\/class\/([A-Za-z0-9]+)/', $request_uri, $matches)) {
    $classCode = $matches[1];
    $response = deleteClass($classCode);
    header('Content-Type: application/json');
    echo json_encode(["message" => $response]);
} else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint not found"]);
}
?>
