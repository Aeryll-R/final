<?php
$title = "New borrowing";
$lead = "Record a whole borrowing in one step. The header and every device are saved together, or nothing is saved.";
include "header.php";

$message = "";
$error = "";
$form = [
    "borrower_id" => "",
    "borrow_date" => date("Y-m-d"),
    "due_date" => date("Y-m-d", strtotime("+7 days"))
];
$kept_qty = [];   // quantities typed by the user, kept if something goes wrong

// ---------- SAVE: header + items inside ONE database transaction ----------
if (isset($_POST["save"])) {
    $borrower = $_POST["borrower_id"];
    $borrow_date = $_POST["borrow_date"];
    $due_date = $_POST["due_date"];
    $qty = isset($_POST["qty"]) ? $_POST["qty"] : [];

    $form = ["borrower_id" => $borrower, "borrow_date" => $borrow_date, "due_date" => $due_date];
    $kept_qty = $qty;

    // Collect only the devices that have a quantity of 1 or more
    $chosen = [];
    foreach ($qty as $equipment_id => $q) {
        $q = trim($q);
        if ($q === "" || $q === "0") {
            continue;
        }
        if (!ctype_digit((string)$equipment_id) || !ctype_digit($q)) {
            $error = "Quantities must be whole numbers.";
            break;
        }
        $chosen[$equipment_id] = $q;
    }

    if ($error != "") {
        // an invalid quantity was found above
    } elseif ($borrower == "" || $borrow_date == "" || $due_date == "") {
        $error = "Please choose a borrower and fill in both dates.";
    } elseif ($due_date < $borrow_date) {
        $error = "The due date cannot be earlier than the borrow date.";
    } elseif (count($chosen) == 0) {
        $error = "Please enter a quantity for at least one device.";
    } else {
        try {
            $pdo->beginTransaction();                                   // BEGIN

            // Step 1: the header
            $stmt = $pdo->prepare("INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
                                   VALUES (?, ?, ?, 'Borrowed') RETURNING borrow_id");
            $stmt->execute([$borrower, $borrow_date, $due_date]);
            $new_id = $stmt->fetchColumn();

            // Step 2: one row for every chosen device
            $stmt = $pdo->prepare("INSERT INTO borrow_items (borrow_id, equipment_id, quantity) VALUES (?, ?, ?)");
            foreach ($chosen as $equipment_id => $q) {
                $stmt->execute([$new_id, $equipment_id, $q]);
            }

            $pdo->commit();                                             // COMMIT

            $message = "Borrowing #$new_id saved. The header and all " . count($chosen) . " device(s) were committed together.";
            $form = ["borrower_id" => "", "borrow_date" => date("Y-m-d"), "due_date" => date("Y-m-d", strtotime("+7 days"))];
            $kept_qty = [];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();                                       // ROLLBACK
            }
            $error = "Nothing was saved (rolled back). " . friendly_error($e);
        }
    }
}

// ---------- READ ----------
$borrowers = $pdo->query("SELECT borrower_id, borrower_number, full_name FROM borrowers ORDER BY full_name")->fetchAll();
$equipment = $pdo->query("SELECT equipment_id, equipment_name, property_number, available_quantity FROM equipment WHERE available_quantity > 0 ORDER BY equipment_name")->fetchAll();

show_messages($message, $error);
?>

<form method="post">
  <div class="panel">
    <div class="panel-title">1. Who is borrowing?</div>
    <div class="inline">
      <label>Borrower
        <select name="borrower_id">
          <option value="">Choose a borrower</option>
          <?php foreach ($borrowers as $b) { ?>
            <option value="<?= h($b["borrower_id"]) ?>" <?= ($form["borrower_id"] == $b["borrower_id"]) ? "selected" : "" ?>>
              <?= h($b["borrower_number"]) ?> - <?= h($b["full_name"]) ?>
            </option>
          <?php } ?>
        </select>
      </label>
      <label>Borrow date
        <input type="date" name="borrow_date" value="<?= h($form["borrow_date"]) ?>">
      </label>
      <label>Due date
        <input type="date" name="due_date" value="<?= h($form["due_date"]) ?>">
      </label>
    </div>
  </div>

  <div class="panel-title">2. Which devices? Enter a quantity for each one you want.</div>
  <div class="table-wrap">
  <table>
    <tr><th>Type</th><th>Brand and model</th><th>Property number</th><th>In stock</th><th>Quantity</th></tr>
    <?php foreach ($equipment as $e) {
        $id = $e["equipment_id"];
        $value = isset($kept_qty[$id]) ? $kept_qty[$id] : "0";
    ?>
    <tr>
      <td><?= type_chip($e["equipment_name"]) ?></td>
      <td><?= h(device_model($e["equipment_name"])) ?></td>
      <td class="mono"><?= h($e["property_number"]) ?></td>
      <td class="mono"><?= h($e["available_quantity"]) ?></td>
      <td><input type="number" name="qty[<?= h($id) ?>]" min="0" value="<?= h($value) ?>" style="min-width:90px"></td>
    </tr>
    <?php } ?>
  </table>
  </div>
  <?php if (count($equipment) == 0) { echo "<p class='empty'>No equipment is in stock right now.</p>"; } ?>

  <p style="margin-top:16px">
    <button type="submit" name="save">Save borrowing</button>
  </p>
</form>

<?php include "footer.php"; ?>