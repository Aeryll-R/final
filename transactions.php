<?php
$title = "Transactions";
$lead = "Each transaction is one borrowing by one borrower. Add the devices under Borrowed items.";
include "header.php";

$message = "";
$error = "";
$statuses = ["Borrowed", "Returned", "Overdue"];
$edit = [
    "borrow_id" => "",
    "borrower_id" => "",
    "borrow_date" => date("Y-m-d"),
    "due_date" => date("Y-m-d", strtotime("+7 days")),
    "status" => "Borrowed"
];

if (isset($_POST["save"])) {
    $id = $_POST["borrow_id"];
    $borrower = $_POST["borrower_id"];
    $borrow_date = $_POST["borrow_date"];
    $due_date = $_POST["due_date"];
    $status = $_POST["status"];

    $edit = ["borrow_id" => $id, "borrower_id" => $borrower, "borrow_date" => $borrow_date, "due_date" => $due_date, "status" => $status];

    if ($borrower == "" || $borrow_date == "" || $due_date == "") {
        $error = "Please choose a borrower and fill in both dates.";
    } elseif ($due_date < $borrow_date) {
        $error = "The due date cannot be earlier than the borrow date.";
    } else {
        try {
            if ($id == "") {
                $stmt = $pdo->prepare("INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$borrower, $borrow_date, $due_date, $status]);
                $message = "Transaction created. Now add the equipment under Borrowed items.";
            } else {
                $stmt = $pdo->prepare("UPDATE borrow_transactions SET borrower_id = ?, borrow_date = ?, due_date = ?, status = ? WHERE borrow_id = ?");
                $stmt->execute([$borrower, $borrow_date, $due_date, $status, $id]);
                $message = "Transaction updated.";
            }
            $edit = ["borrow_id" => "", "borrower_id" => "", "borrow_date" => date("Y-m-d"), "due_date" => date("Y-m-d", strtotime("+7 days")), "status" => "Borrowed"];
        } catch (PDOException $e) {
            $error = friendly_error($e);
        }
    }
}

if (isset($_GET["delete"])) {
    if (!ctype_digit($_GET["delete"])) {
        $error = "Invalid transaction ID.";
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM borrow_items WHERE borrow_id = ?")->execute([$_GET["delete"]]);
            $stmt = $pdo->prepare("DELETE FROM borrow_transactions WHERE borrow_id = ?");
            $stmt->execute([$_GET["delete"]]);
            $pdo->commit();

            if ($stmt->rowCount() > 0) {
                $message = "Transaction and its borrowed items were deleted.";
            } else {
                $error = "No transaction found with that ID.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = friendly_error($e);
        }
    }
}

if (isset($_GET["edit"])) {
    if (!ctype_digit($_GET["edit"])) {
        $error = "Invalid transaction ID.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM borrow_transactions WHERE borrow_id = ?");
        $stmt->execute([$_GET["edit"]]);
        $row = $stmt->fetch();
        if ($row) {
            $edit = $row;
        } else {
            $error = "No transaction found with that ID.";
        }
    }
}

$borrowers = $pdo->query("SELECT borrower_id, borrower_number, full_name FROM borrowers ORDER BY full_name")->fetchAll();

$rows = $pdo->query("
    SELECT t.borrow_id, b.borrower_number, b.full_name, t.borrow_date, t.due_date, t.status,
           COUNT(bi.item_id) AS items
    FROM borrow_transactions t
    JOIN borrowers b ON t.borrower_id = b.borrower_id
    LEFT JOIN borrow_items bi ON t.borrow_id = bi.borrow_id
    GROUP BY t.borrow_id, b.borrower_id
    ORDER BY t.borrow_id DESC
")->fetchAll();

show_messages($message, $error);
?>

<div class="panel">
  <div class="panel-title"><?= $edit["borrow_id"] == "" ? "Create a transaction" : "Edit transaction" ?></div>
  <form method="post" class="inline">
    <input type="hidden" name="borrow_id" value="<?= h($edit["borrow_id"]) ?>">

    <label>Borrower
      <select name="borrower_id">
        <option value="">Choose a borrower</option>
        <?php foreach ($borrowers as $b) { ?>
          <option value="<?= h($b["borrower_id"]) ?>" <?= ($edit["borrower_id"] == $b["borrower_id"]) ? "selected" : "" ?>>
            <?= h($b["borrower_number"]) ?> - <?= h($b["full_name"]) ?>
          </option>
        <?php } ?>
      </select>
    </label>
    <label>Borrow date
      <input type="date" name="borrow_date" value="<?= h($edit["borrow_date"]) ?>">
    </label>
    <label>Due date
      <input type="date" name="due_date" value="<?= h($edit["due_date"]) ?>">
    </label>
    <label>Status
      <select name="status">
        <?php foreach ($statuses as $s) { ?>
          <option <?= ($edit["status"] == $s) ? "selected" : "" ?>><?= h($s) ?></option>
        <?php } ?>
      </select>
    </label>

    <button type="submit" name="save"><?= $edit["borrow_id"] == "" ? "Create transaction" : "Save changes" ?></button>
    <?php if ($edit["borrow_id"] != "") { ?>
      <a class="button plain" href="transactions.php">Cancel</a>
    <?php } ?>
  </form>
</div>

<div class="table-wrap">
<table>
  <tr><th>ID</th><th>Borrower</th><th>Borrow date</th><th>Due date</th><th>Status</th><th>Items</th><th>Actions</th></tr>
  <?php foreach ($rows as $r) { ?>
  <tr>
    <td class="mono"><?= h($r["borrow_id"]) ?></td>
    <td><span class="mono"><?= h($r["borrower_number"]) ?></span> <?= h($r["full_name"]) ?></td>
    <td class="mono"><?= h($r["borrow_date"]) ?></td>
    <td class="mono"><?= h($r["due_date"]) ?></td>
    <td><?= status_badge($r["status"]) ?></td>
    <td><a href="borrow_items.php?borrow_id=<?= h($r["borrow_id"]) ?>"><?= h($r["items"]) ?> item(s)</a></td>
    <td>
      <a href="transactions.php?edit=<?= h($r["borrow_id"]) ?>">Edit</a> |
      <a href="transactions.php?delete=<?= h($r["borrow_id"]) ?>" onclick="return confirm('Delete this transaction and all of its items?')">Delete</a>
    </td>
  </tr>
  <?php } ?>
</table>
</div>
<?php if (count($rows) == 0) { echo "<p class='empty'>No transactions yet. Create one above.</p>"; } ?>

<?php include "footer.php"; ?>
