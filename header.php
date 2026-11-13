<?php
require_once "db.php";

$current = basename($_SERVER["PHP_SELF"]);

// The only kinds of equipment this system lends out
$device_types = ["PC", "Laptop", "Tablet", "Phone"];

function nav_link($file, $label) {
    global $current;
    $class = ($current == $file) ? "active" : "";
    echo "<a class='$class' href='$file'>$label</a>";
}

// Equipment names are saved as "Type - Model", for example "Laptop - Dell Latitude 5420"
function device_type($name) {
    global $device_types;
    $first = explode(" - ", $name, 2)[0];
    return in_array($first, $device_types) ? $first : "Other";
}

function device_model($name) {
    $parts = explode(" - ", $name, 2);
    return isset($parts[1]) ? $parts[1] : $name;
}

// Small colored label for the device type
function type_chip($name) {
    $type = device_type($name);
    return "<span class='chip chip-" . strtolower($type) . "'>" . h($type) . "</span>";
}

// Colored pill for a status such as Borrowed, Returned, Low stock
function status_badge($text) {
    $class = str_replace(" ", "-", strtolower($text));
    return "<span class='badge badge-" . h($class) . "'>" . h($text) . "</span>";
}

function stock_label($qty) {
    if ($qty <= 0) {
        return "Out of stock";
    } elseif ($qty <= 2) {
        return "Low stock";
    }
    return "Available";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> | Equipment Borrowing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@400;600&display=swap">
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="shell">
  <aside class="side">
    <div class="brand">
      <div class="logo">EB</div>
      <div>
        <div class="brand-name">Equipment Borrowing</div>
        <div class="brand-sub">PC, laptop, tablet, phone loans</div>
      </div>
    </div>
    <nav>
      <?php
      nav_link("index.php", "Dashboard");
      nav_link("borrowers.php", "Borrowers");
      nav_link("equipment.php", "Equipment");
      nav_link("transactions.php", "Transactions");
      nav_link("borrow_items.php", "Borrowed items");
      nav_link("admin.php", "Admin reports");
      ?>
    </nav>
    <div class="dbstatus">
      <?= h($dbname) ?><br><?= h($host) ?>:<?= h($port) ?>
    </div>
  </aside>

  <main>
    <h1><?= h($title) ?></h1>
    <?php if (!empty($lead)) { ?>
      <p class="lead"><?= h($lead) ?></p>
    <?php } ?>
