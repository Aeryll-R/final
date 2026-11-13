<?php
$host = "192.168.1.161";
$port = "5432";
$dbname = "Enrollment_Activity";
$user = "postgres";
$password = "041205";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<p style='font-family:sans-serif'>Could not connect to PostgreSQL: " . htmlspecialchars($e->getMessage()) . "</p>");
}

function h($value) {
    return htmlspecialchars((string)$value);
}

function friendly_error($e) {
    $code = $e->getCode();
    $text = $e->getMessage();

    if (strpos($text, "ERROR:") !== false) {
        $text = trim(substr($text, strpos($text, "ERROR:") + 6));
    }
    $text = explode("\n", $text)[0];

    if ($code == "23505") {
        return "Duplicate value not allowed. " . $text;
    } elseif ($code == "23503") {
        return "Invalid reference, or this record is still being used. " . $text;
    } elseif ($code == "23514") {
        return "A database rule was broken. " . $text;
    } elseif ($code == "P0001") {
        return $text;
    }
    return "Database error. " . $text;
}

function show_messages($message, $error) {
    if ($message != "") {
        echo "<div class='msg ok'>" . h($message) . "</div>";
    }
    if ($error != "") {
        echo "<div class='msg bad'>" . h($error) . "</div>";
    }
}

function print_table($rows) {
    if (count($rows) == 0) {
        echo "<p class='empty'>No rows to show yet.</p>";
        return;
    }
    echo "<div class='table-wrap'><table><tr>";
    foreach (array_keys($rows[0]) as $column) {
        echo "<th>" . h($column) . "</th>";
    }
    echo "</tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $value) {
            if ($value === null) {
                $value = "-";
            } elseif ($value === true) {
                $value = "true";
            } elseif ($value === false) {
                $value = "false";
            }
            echo "<td>" . h($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table></div>";
}
?>
