<?php
$title = "Dashboard";
$lead = "Live overview of the borrowing system. Every number is read from PostgreSQL.";
include "header.php";

$tables = [
    "borrowers" => "borrowers.php",
    "equipment" => "equipment.php",
    "borrow_transactions" => "transactions.php",
    "borrow_items" => "borrow_items.php"
];

$stock = ["PC" => 0, "Laptop" => 0, "Tablet" => 0, "Phone" => 0];
$all_equipment = $pdo->query("SELECT equipment_name, available_quantity FROM equipment")->fetchAll();
foreach ($all_equipment as $e) {
    $type = device_type($e["equipment_name"]);
    if (isset($stock[$type])) {
        $stock[$type] += $e["available_quantity"];
    }
}
?>

<div class="stats">
<?php
foreach ($tables as $table => $page) {
    $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    echo "<a class='stat' href='$page'>";
    echo "<div class='num'>" . h($count) . "</div>";
    echo "<div class='label'>" . h($table) . "</div>";
    echo "</a>";
}
?>
</div>

<div class="panel">
  <div class="panel-title">Units available to borrow</div>
  <div class="stats" style="margin-bottom:0">
    <?php foreach ($stock as $type => $units) { ?>
      <a class="stat" href="equipment.php?type=<?= h($type) ?>">
        <div class="num"><?= h($units) ?></div>
        <div class="label"><?= type_chip($type . " - x") ?> available</div>
      </a>
    <?php } ?>
  </div>
</div>

<div class="panel">
  <div class="panel-title">How to record a borrowing</div>
  <ol class="steps">
    <li><span>Make sure the borrower exists in <a href="borrowers.php">Borrowers</a>.</span></li>
    <li><span>Open <a href="transactions.php">Transactions</a> and create a transaction for that borrower.</span></li>
    <li><span>Open <a href="borrow_items.php">Borrowed items</a>, choose the transaction, and add one or more devices with their quantities.</span></li>
    <li><span>When the devices come back, set the transaction status to Returned.</span></li>
  </ol>
</div>

<h3>Latest borrowing activity</h3>
<p class="note">Read from the view vw_borrowing_details.</p>
<?php
try {
    $rows = $pdo->query("SELECT * FROM vw_borrowing_details ORDER BY borrow_date DESC LIMIT 5")->fetchAll();
    print_table($rows);
} catch (PDOException $e) {
    echo "<div class='msg bad'>" . h(friendly_error($e)) . "</div>";
}
?>

<?php include "footer.php"; ?>
