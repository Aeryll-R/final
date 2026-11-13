<?php
$title = "Admin reports";
$lead = "Every report below is a live PostgreSQL query. Add or change records and the results update.";
include "header.php";

// Runs one query and prints it under a heading. A broken query shows an error
// under its own heading instead of stopping the whole page.
function report($pdo, $id, $heading, $note, $sql, $params = []) {
    echo "<section class='report' id='$id'>";
    echo "<h3>" . h($heading) . "</h3>";
    echo "<p class='note'>" . h($note) . "</p>";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        print_table($rows);
        echo "<p class='note'>" . count($rows) . " row(s)</p>";
    } catch (PDOException $e) {
        echo "<div class='msg bad'>" . h(friendly_error($e)) . "</div>";
    }
    echo "</section>";
}

// Values for the filter form in the filtered borrowing report
$f_status = isset($_GET["status"]) ? $_GET["status"] : "";
$f_from = (isset($_GET["from"]) && $_GET["from"] != "") ? $_GET["from"] : "2000-01-01";
$f_to = (isset($_GET["to"]) && $_GET["to"] != "") ? $_GET["to"] : "2100-12-31";
?>

<?php
$s_borrowers = $pdo->query("SELECT COUNT(*) FROM borrowers")->fetchColumn();
$s_stock = $pdo->query("SELECT COALESCE(SUM(available_quantity), 0) FROM equipment")->fetchColumn();
$s_out = $pdo->query("SELECT COUNT(*) FROM borrow_transactions WHERE status = 'Borrowed'")->fetchColumn();
$s_late = $pdo->query("SELECT COUNT(*) FROM borrow_transactions WHERE due_date < CURRENT_DATE AND status = 'Borrowed'")->fetchColumn();
?>

<div class="stats">
  <div class="stat"><div class="num"><?= h($s_borrowers) ?></div><div class="label">Registered borrowers</div></div>
  <div class="stat"><div class="num"><?= h($s_stock) ?></div><div class="label">Devices in stock</div></div>
  <div class="stat"><div class="num"><?= h($s_out) ?></div><div class="label">Active borrowings</div></div>
  <div class="stat"><div class="num"><?= h($s_late) ?></div><div class="label">Overdue</div></div>
</div>

<div class="jump">
  <a href="#inventory-borrowers">Inventory and borrowers</a>
  <a href="#borrowing-activity">Borrowing activity</a>
  <a href="#saved-views">Saved views</a>
  <a href="#automation">Functions and audit log</a>
</div>

<!-- ===================== INVENTORY AND BORROWERS ===================== -->
<h2 class="sec" id="inventory-borrowers">Inventory and borrowers</h2>

<?php
report($pdo, "inventory", "Equipment Inventory Listing",
    "All devices with their property number and stock, sorted by name.",
    "SELECT equipment_name AS \"Equipment\",
            property_number AS \"Property number\",
            available_quantity AS \"Available quantity\"
     FROM equipment
     ORDER BY equipment_name ASC, available_quantity DESC");

report($pdo, "distinct", "Borrower types",
    "The kinds of borrowers in the system.",
    "SELECT DISTINCT borrower_type AS \"Borrower type\" FROM borrowers ORDER BY borrower_type");

report($pdo, "search", "Available computers",
    "Laptops and PCs that are in stock right now.",
    "SELECT equipment_name AS \"Equipment\", property_number AS \"Property number\", available_quantity AS \"Available\"
     FROM equipment
     WHERE available_quantity > 0
       AND (equipment_name LIKE 'Laptop%' OR equipment_name LIKE 'PC%')
     ORDER BY equipment_name");

report($pdo, "intypes", "Students and faculty",
    "Borrowers who are students or faculty.",
    "SELECT borrower_number AS \"Borrower number\", full_name AS \"Full name\", borrower_type AS \"Type\"
     FROM borrowers
     WHERE borrower_type IN ('Student', 'Faculty')
     ORDER BY full_name");
?>

<section class="report" id="filtered">
  <h3>Filtered Borrowing Records</h3>
  <p class="note">Filter transactions by status and date range, then press Apply.</p>
  <div class="panel">
    <form method="get" class="inline">
      <label>Status
        <select name="status">
          <option value="">All</option>
          <?php foreach (["Borrowed", "Returned", "Overdue"] as $s) { ?>
            <option <?= ($f_status == $s) ? "selected" : "" ?>><?= h($s) ?></option>
          <?php } ?>
        </select>
      </label>
      <label>From <input type="date" name="from" value="<?= h($_GET["from"] ?? "") ?>"></label>
      <label>To <input type="date" name="to" value="<?= h($_GET["to"] ?? "") ?>"></label>
      <button type="submit">Apply</button>
    </form>
  </div>
  <?php
  try {
      $stmt = $pdo->prepare("
          SELECT borrow_id, borrower_id, borrow_date, due_date, status
          FROM borrow_transactions
          WHERE (? = '' OR status = ?)
            AND borrow_date BETWEEN CAST(? AS date) AND CAST(? AS date)
          ORDER BY borrow_date DESC, borrow_id DESC");
      $stmt->execute([$f_status, $f_status, $f_from, $f_to]);
      $rows = $stmt->fetchAll();
      print_table($rows);
      echo "<p class='note'>" . count($rows) . " row(s)</p>";
  } catch (PDOException $e) {
      echo "<div class='msg bad'>" . h(friendly_error($e)) . "</div>";
  }
  ?>
</section>

<?php
report($pdo, "typesummary", "Borrower Type Summary",
    "Number of borrowers in each category.",
    "SELECT borrower_type AS \"Borrower type\", COUNT(*) AS \"Total borrowers\"
     FROM borrowers
     GROUP BY borrower_type
     ORDER BY COUNT(*) DESC");

report($pdo, "statussummary", "Transaction Status Summary",
    "Number of transactions for each status.",
    "SELECT status AS \"Status\", COUNT(*) AS \"Total transactions\"
     FROM borrow_transactions
     GROUP BY status
     ORDER BY status");

report($pdo, "grouped", "Grouped Borrowing Analysis",
    "Borrowers with at least two transactions.",
    "SELECT b.borrower_number, b.full_name, COUNT(*) AS total_transactions
     FROM borrow_transactions t
     JOIN borrowers b ON t.borrower_id = b.borrower_id
     GROUP BY b.borrower_number, b.full_name
     HAVING COUNT(*) >= 2
     ORDER BY total_transactions DESC");
?>

<h2 class="sec" id="borrowing-activity">Borrowing activity</h2>

<?php
report($pdo, "complete", "Complete Borrowing Report",
    "Every borrowing with the borrower, device, quantity, dates, and status.",
    "SELECT b.borrower_number,
            UPPER(b.full_name) AS borrower_name,
            e.equipment_name,
            bi.quantity,
            TO_CHAR(t.borrow_date, 'Mon DD, YYYY') AS borrowed_on,
            TO_CHAR(t.due_date, 'Mon DD, YYYY') AS due_on,
            t.status
     FROM borrowers b
     INNER JOIN borrow_transactions t ON b.borrower_id = t.borrower_id
     INNER JOIN borrow_items bi ON t.borrow_id = bi.borrow_id
     INNER JOIN equipment e ON bi.equipment_id = e.equipment_id
     ORDER BY t.borrow_date DESC, t.borrow_id DESC");

report($pdo, "eqsummary", "Equipment Borrowing Summary",
    "How often each device was borrowed, with the total, average, smallest, and largest quantity.",
    "SELECT e.equipment_name,
            COUNT(bi.item_id) AS times_borrowed,
            SUM(bi.quantity) AS total_quantity,
            ROUND(AVG(bi.quantity), 2) AS avg_quantity,
            MIN(bi.quantity) AS min_quantity,
            MAX(bi.quantity) AS max_quantity
     FROM equipment e
     INNER JOIN borrow_items bi ON e.equipment_id = bi.equipment_id
     GROUP BY e.equipment_name
     ORDER BY total_quantity DESC");

report($pdo, "noborrowers", "Borrowers Without Transactions",
    "Registered borrowers who have never borrowed anything.",
    "SELECT b.borrower_number, b.full_name, b.borrower_type
     FROM borrowers b
     LEFT JOIN borrow_transactions t ON b.borrower_id = t.borrower_id
     WHERE t.borrow_id IS NULL
     ORDER BY b.borrower_number");

report($pdo, "noequipment", "Equipment Without Borrowing History",
    "Devices that have never been borrowed.",
    "SELECT e.equipment_name, e.property_number, e.available_quantity
     FROM equipment e
     LEFT JOIN borrow_items bi ON e.equipment_id = bi.equipment_id
     WHERE bi.item_id IS NULL
     ORDER BY e.equipment_name");

report($pdo, "union", "Combined Borrower Activity (UNION)",
    "Everyone who ever borrowed combined with everyone who has a device out now. UNION lists each person once.",
    "SELECT b.borrower_number, b.full_name
     FROM borrowers b JOIN borrow_transactions t ON b.borrower_id = t.borrower_id
     UNION
     SELECT b.borrower_number, b.full_name
     FROM borrowers b JOIN borrow_transactions t ON b.borrower_id = t.borrower_id
     WHERE t.status = 'Borrowed'
     ORDER BY borrower_number");

report($pdo, "unionall", "Combined Borrower Activity (UNION ALL)",
    "The same two lists combined. UNION ALL keeps repeats, so it returns more rows than UNION above.",
    "SELECT b.borrower_number, b.full_name
     FROM borrowers b JOIN borrow_transactions t ON b.borrower_id = t.borrower_id
     UNION ALL
     SELECT b.borrower_number, b.full_name
     FROM borrowers b JOIN borrow_transactions t ON b.borrower_id = t.borrower_id
     WHERE t.status = 'Borrowed'
     ORDER BY borrower_number");

report($pdo, "strings", "Borrower labels",
    "Borrower numbers and names with standardized capitalization and spacing.",
    "SELECT CONCAT_WS(' - ', UPPER(borrower_number), INITCAP(TRIM(full_name))) AS borrower_label,
            LOWER(borrower_type) AS borrower_type
     FROM borrowers
     ORDER BY borrower_number");

report($pdo, "monthly", "Monthly Borrowing Report",
    "Number of transactions in each month.",
    "SELECT TO_CHAR(DATE_TRUNC('month', borrow_date), 'FMMonth YYYY') AS month,
            COUNT(*) AS total_transactions
     FROM borrow_transactions
     GROUP BY DATE_TRUNC('month', borrow_date)
     ORDER BY DATE_TRUNC('month', borrow_date)");

report($pdo, "periods", "Borrowing periods",
    "Borrow year and month for each transaction, and how many days the loan allows.",
    "SELECT borrow_id,
            EXTRACT(YEAR FROM borrow_date) AS borrow_year,
            EXTRACT(MONTH FROM borrow_date) AS borrow_month,
            due_date - borrow_date AS days_allowed
     FROM borrow_transactions
     ORDER BY borrow_id");

report($pdo, "overdue", "Overdue Records",
    "Active borrowings that are past their due date, and how many days late.",
    "SELECT t.borrow_id, b.full_name, t.due_date,
            CURRENT_DATE - t.due_date AS days_overdue
     FROM borrow_transactions t
     JOIN borrowers b ON t.borrower_id = b.borrower_id
     WHERE t.due_date < CURRENT_DATE AND t.status = 'Borrowed'
     ORDER BY days_overdue DESC");
?>

<h2 class="sec" id="saved-views">Saved views</h2>

<?php
report($pdo, "vw1", "Borrowing details",
    "Borrower, device, quantity, dates, and status in one list. Source: view vw_borrowing_details.",
    "SELECT * FROM vw_borrowing_details ORDER BY borrow_date DESC");

report($pdo, "vw2", "Equipment usage",
    "Usage for each device with a stock status of Available, Low Stock, or Out of Stock. Source: view vw_equipment_usage.",
    "SELECT * FROM vw_equipment_usage ORDER BY equipment_name");
?>

<h2 class="sec" id="automation">Functions and audit log</h2>

<?php
report($pdo, "fn1", "Devices currently borrowed",
    "Units of each device that are out on loan right now. Source: function fn_total_borrowed.",
    "SELECT equipment_id, equipment_name, fn_total_borrowed(equipment_id) AS total_borrowed_now
     FROM equipment
     ORDER BY equipment_id");

report($pdo, "fn2", "Availability check",
    "Whether 1, 5, or 10 units of each device can be borrowed with the current stock. Source: function fn_check_equipment_availability.",
    "SELECT equipment_id, equipment_name, available_quantity,
            fn_check_equipment_availability(equipment_id, 1) AS can_borrow_1,
            fn_check_equipment_availability(equipment_id, 5) AS can_borrow_5,
            fn_check_equipment_availability(equipment_id, 10) AS can_borrow_10
     FROM equipment
     ORDER BY equipment_id");

?>

<?php include "footer.php"; ?>