<?php
$title = "Borrowed items";
$lead = "Choose a transaction, then pick devices from the list of available equipment.";
include "header.php";

$message = "";
$error = "";

$borrow_id = "";
if (isset($_GET["borrow_id"])) {
    $borrow_id = $_GET["borrow_id"];
} elseif (isset($_POST["borrow_id"])) {
    $borrow_id = $_POST["borrow_id"];
}

$transactions = $pdo->query("
    SELECT t.borrow_id, b.full_name, t.borrow_date, t.status
    FROM borrow_transactions t
    JOIN borrowers b ON t.borrower_id = b.borrower_id
    ORDER BY t.borrow_id DESC
")->fetchAll();

$header = false;
if ($borrow_id != "") {
    if (!ctype_digit($borrow_id)) {
        $error = "Invalid transaction ID.";
    } else {
        $stmt = $pdo->prepare("
            SELECT t.borrow_id, b.borrower_number, b.full_name, t.borrow_date, t.due_date, t.status
            FROM borrow_transactions t
            JOIN borrowers b ON t.borrower_id = b.borrower_id
            WHERE t.borrow_id = ?
        ");
        $stmt->execute([$borrow_id]);
        $header = $stmt->fetch();
        if (!$header) {
            $error = "No transaction found with that ID.";
        }
    }
}

show_messages($message, $error);
?>

<div class="panel">
  <form method="get" class="inline">
    <label>Transaction
      <select name="borrow_id">
        <option value="">Choose a transaction</option>
        <?php foreach ($transactions as $t) { ?>
          <option value="<?= h($t["borrow_id"]) ?>" <?= ($borrow_id == $t["borrow_id"]) ? "selected" : "" ?>>
            #<?= h($t["borrow_id"]) ?> - <?= h($t["full_name"]) ?> (<?= h($t["borrow_date"]) ?>, <?= h($t["status"]) ?>)
          </option>
        <?php } ?>
      </select>
    </label>
    <button type="submit">Open</button>
  </form>
</div>

<?php
if (!$header) {
    echo "<p class='empty'>Choose a transaction above to see and manage its equipment.</p>";
    include "footer.php";
    exit;
}

// Stock and the 3-item borrower limit are enforced in PostgreSQL by the
// trigger trg_validate_borrow_quantity; its message is shown via friendly_error().
// trg_borrow_items_stock then moves the units off / back onto the shelf.

$message = "";
$error = "";
$edit = ["item_id" => "", "equipment_id" => "", "quantity" => "1"];

if (isset($_GET["pick"]) && ctype_digit($_GET["pick"])) {
    $edit["equipment_id"] = $_GET["pick"];
}

if (isset($_POST["save"])) {
    $item_id = $_POST["item_id"];
    $equipment_id = $_POST["equipment_id"];
    $quantity = trim($_POST["quantity"]);

    $edit = ["item_id" => $item_id, "equipment_id" => $equipment_id, "quantity" => $quantity];

    if ($equipment_id == "") {
        $error = "Please choose an equipment item.";
    } elseif (!ctype_digit($quantity) || $quantity < 1) {
        $error = "Quantity must be a whole number of 1 or more.";
    } else {
        try {
            if ($item_id == "") {
                $stmt = $pdo->prepare("INSERT INTO borrow_items (borrow_id, equipment_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$borrow_id, $equipment_id, $quantity]);
                $message = "Item added to the transaction.";
            } else {
                $stmt = $pdo->prepare("UPDATE borrow_items SET equipment_id = ?, quantity = ? WHERE item_id = ? AND borrow_id = ?");
                $stmt->execute([$equipment_id, $quantity, $item_id, $borrow_id]);
                $message = "Item updated.";
            }
            $edit = ["item_id" => "", "equipment_id" => "", "quantity" => "1"];
        } catch (PDOException $e) {
            $error = friendly_error($e);
        }
    }
}

if (isset($_GET["delete"])) {
    if (!ctype_digit($_GET["delete"])) {
        $error = "Invalid item ID.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM borrow_items WHERE item_id = ? AND borrow_id = ?");
            $stmt->execute([$_GET["delete"], $borrow_id]);
            if ($stmt->rowCount() > 0) {
                $message = "Item removed from the transaction.";
            } else {
                $error = "No item found with that ID in this transaction.";
            }
        } catch (PDOException $e) {
            $error = friendly_error($e);
        }
    }
}

if (isset($_GET["edit"])) {
    if (!ctype_digit($_GET["edit"])) {
        $error = "Invalid item ID.";
    } else {
        $stmt = $pdo->prepare("SELECT item_id, equipment_id, quantity FROM borrow_items WHERE item_id = ? AND borrow_id = ?");
        $stmt->execute([$_GET["edit"], $borrow_id]);
        $row = $stmt->fetch();
        if ($row) {
            $edit = $row;
        } else {
            $error = "No item found with that ID in this transaction.";
        }
    }
}

$equipment = $pdo->query("SELECT equipment_id, equipment_name, property_number, available_quantity FROM equipment ORDER BY equipment_name")->fetchAll();

$stmt = $pdo->prepare("
    SELECT bi.item_id, e.equipment_name, e.property_number, bi.quantity, e.available_quantity AS stock
    FROM borrow_items bi
    JOIN equipment e ON bi.equipment_id = e.equipment_id
    WHERE bi.borrow_id = ?
    ORDER BY bi.item_id
");
$stmt->execute([$borrow_id]);
$items = $stmt->fetchAll();

show_messages($message, $error);
?>

<div class="panel">
  <strong>Transaction #<?= h($header["borrow_id"]) ?></strong> for
  <span class="mono"><?= h($header["borrower_number"]) ?></span> <?= h($header["full_name"]) ?>.
  Borrowed <span class="mono"><?= h($header["borrow_date"]) ?></span>,
  due <span class="mono"><?= h($header["due_date"]) ?></span>,
  <?= status_badge($header["status"]) ?>
</div>

<h3>Available equipment</h3>
<p class="note">Devices that can be borrowed right now. Choose Select to fill the form below.</p>
<div class="catalog" style="margin-bottom:18px">
  <?php
  $listed = 0;
  foreach ($equipment as $e) {
      if ($e["available_quantity"] < 1) {
          continue;
      }
      $listed++;
  ?>
    <div class="eq">
      <div><?= type_chip($e["equipment_name"]) ?></div>
      <div class="model"><?= h(device_model($e["equipment_name"])) ?></div>
      <div class="pn"><?= h($e["property_number"]) ?></div>
      <div class="stock"><?= h($e["available_quantity"]) ?> in stock</div>
      <a class="button" href="borrow_items.php?borrow_id=<?= h($borrow_id) ?>&pick=<?= h($e["equipment_id"]) ?>#form">Select</a>
    </div>
  <?php } ?>
</div>
<?php if ($listed == 0) { echo "<p class='empty'>No equipment is in stock. Add or restock devices under Equipment.</p>"; } ?>

<div class="panel" id="form">
  <div class="panel-title"><?= $edit["item_id"] == "" ? "Add a device to this transaction" : "Edit item" ?></div>
  <form method="post" class="inline">
    <input type="hidden" name="borrow_id" value="<?= h($borrow_id) ?>">
    <input type="hidden" name="item_id" value="<?= h($edit["item_id"]) ?>">

    <label>Equipment
      <select name="equipment_id">
        <option value="">Choose equipment</option>
        <?php foreach ($equipment as $e) { ?>
          <option value="<?= h($e["equipment_id"]) ?>" <?= ($edit["equipment_id"] == $e["equipment_id"]) ? "selected" : "" ?>>
            <?= h($e["equipment_name"]) ?> (<?= h($e["property_number"]) ?>, stock <?= h($e["available_quantity"]) ?>)
          </option>
        <?php } ?>
      </select>
    </label>
    <label>Quantity
      <input type="number" name="quantity" min="1" value="<?= h($edit["quantity"]) ?>">
    </label>

    <button type="submit" name="save"><?= $edit["item_id"] == "" ? "Add item" : "Save changes" ?></button>
    <?php if ($edit["item_id"] != "") { ?>
      <a class="button plain" href="borrow_items.php?borrow_id=<?= h($borrow_id) ?>">Cancel</a>
    <?php } ?>
  </form>
</div>

<h3>Devices in this transaction</h3>
<div class="table-wrap">
<table>
  <tr><th>Item ID</th><th>Type</th><th>Brand and model</th><th>Property number</th><th>Quantity</th><th>Left on shelf</th><th>Actions</th></tr>
  <?php foreach ($items as $i) { ?>
  <tr>
    <td class="mono"><?= h($i["item_id"]) ?></td>
    <td><?= type_chip($i["equipment_name"]) ?></td>
    <td><?= h(device_model($i["equipment_name"])) ?></td>
    <td class="mono"><?= h($i["property_number"]) ?></td>
    <td class="mono"><?= h($i["quantity"]) ?></td>
    <td class="mono"><?= h($i["stock"]) ?></td>
    <td>
      <a href="borrow_items.php?borrow_id=<?= h($borrow_id) ?>&edit=<?= h($i["item_id"]) ?>#form">Edit</a> |
      <a href="borrow_items.php?borrow_id=<?= h($borrow_id) ?>&delete=<?= h($i["item_id"]) ?>" onclick="return confirm('Remove this item?')">Delete</a>
    </td>
  </tr>
  <?php } ?>
</table>
</div>
<?php if (count($items) == 0) { echo "<p class='empty'>This transaction has no equipment yet. Select a device above.</p>"; } ?>

<?php include "footer.php"; ?>
