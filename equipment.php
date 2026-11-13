<?php
$title = "Equipment";
$lead = "Devices available for borrowing. Only PCs, laptops, tablets, and phones are allowed.";
include "header.php";

$message = "";
$error = "";
$edit = ["equipment_id" => "", "equipment_name" => "", "property_number" => "", "available_quantity" => ""];
$form_type = "Laptop";
$form_model = "";

if (isset($_POST["save"])) {
    $id = $_POST["equipment_id"];
    $type = $_POST["device_type"];
    $model = trim($_POST["model"]);
    $property = trim($_POST["property_number"]);
    $qty = trim($_POST["available_quantity"]);

    $name = $type . " - " . $model;

    $edit = ["equipment_id" => $id, "equipment_name" => $name, "property_number" => $property, "available_quantity" => $qty];
    $form_type = $type;
    $form_model = $model;

    if (!in_array($type, $device_types)) {
        $error = "Please choose PC, Laptop, Tablet, or Phone.";
    } elseif ($model == "" || $property == "") {
        $error = "Please fill in the model and the property number.";
    } elseif (!ctype_digit($qty)) {
        $error = "Available quantity must be a whole number, 0 or higher.";
    } else {
        try {
            if ($id == "") {
                $stmt = $pdo->prepare("INSERT INTO equipment (equipment_name, property_number, available_quantity) VALUES (?, ?, ?)");
                $stmt->execute([$name, $property, $qty]);
                $message = "Equipment added.";
            } else {
                $stmt = $pdo->prepare("UPDATE equipment SET equipment_name = ?, property_number = ?, available_quantity = ? WHERE equipment_id = ?");
                $stmt->execute([$name, $property, $qty, $id]);
                $message = "Equipment updated.";
            }
            $edit = ["equipment_id" => "", "equipment_name" => "", "property_number" => "", "available_quantity" => ""];
            $form_type = "Laptop";
            $form_model = "";
        } catch (PDOException $e) {
            $error = friendly_error($e);
        }
    }
}

if (isset($_GET["delete"])) {
    if (!ctype_digit($_GET["delete"])) {
        $error = "Invalid equipment ID.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM equipment WHERE equipment_id = ?");
            $stmt->execute([$_GET["delete"]]);
            if ($stmt->rowCount() > 0) {
                $message = "Equipment deleted.";
            } else {
                $error = "No equipment found with that ID.";
            }
        } catch (PDOException $e) {
            $error = "This equipment cannot be deleted because it appears in borrowed items.";
        }
    }
}

if (isset($_GET["edit"])) {
    if (!ctype_digit($_GET["edit"])) {
        $error = "Invalid equipment ID.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM equipment WHERE equipment_id = ?");
        $stmt->execute([$_GET["edit"]]);
        $row = $stmt->fetch();
        if ($row) {
            $edit = $row;
            $form_type = device_type($row["equipment_name"]);
            if ($form_type == "Other") {
                $form_type = "Laptop";
            }
            $form_model = device_model($row["equipment_name"]);
        } else {
            $error = "No equipment found with that ID.";
        }
    }
}

$all_rows = $pdo->query("SELECT * FROM equipment ORDER BY equipment_id")->fetchAll();

$filter = isset($_GET["type"]) ? $_GET["type"] : "";
$rows = [];
foreach ($all_rows as $r) {
    if ($filter == "" || device_type($r["equipment_name"]) == $filter) {
        $rows[] = $r;
    }
}

show_messages($message, $error);
?>

<div class="panel">
  <div class="panel-title"><?= $edit["equipment_id"] == "" ? "Add equipment" : "Edit equipment" ?></div>
  <form method="post" class="inline">
    <input type="hidden" name="equipment_id" value="<?= h($edit["equipment_id"]) ?>">

    <label>Device type
      <select name="device_type">
        <?php foreach ($device_types as $t) { ?>
          <option <?= ($form_type == $t) ? "selected" : "" ?>><?= h($t) ?></option>
        <?php } ?>
      </select>
    </label>
    <label>Brand and model
      <input type="text" name="model" value="<?= h($form_model) ?>" placeholder="Dell Latitude 5420">
    </label>
    <label>Property number
      <input type="text" name="property_number" value="<?= h($edit["property_number"]) ?>" placeholder="PN-LT-1004">
    </label>
    <label>Available quantity
      <input type="number" name="available_quantity" min="0" value="<?= h($edit["available_quantity"]) ?>">
    </label>

    <button type="submit" name="save"><?= $edit["equipment_id"] == "" ? "Add equipment" : "Save changes" ?></button>
    <?php if ($edit["equipment_id"] != "") { ?>
      <a class="button plain" href="equipment.php">Cancel</a>
    <?php } ?>
  </form>
</div>

<div class="filters">
  <a href="equipment.php" class="<?= $filter == "" ? "on" : "" ?>">All</a>
  <?php foreach ($device_types as $t) { ?>
    <a href="equipment.php?type=<?= h($t) ?>" class="<?= $filter == $t ? "on" : "" ?>"><?= h($t) ?></a>
  <?php } ?>
</div>

<div class="table-wrap">
<table>
  <tr><th>ID</th><th>Type</th><th>Brand and model</th><th>Property number</th><th>Available</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($rows as $r) { ?>
  <tr>
    <td class="mono"><?= h($r["equipment_id"]) ?></td>
    <td><?= type_chip($r["equipment_name"]) ?></td>
    <td><?= h(device_model($r["equipment_name"])) ?></td>
    <td class="mono"><?= h($r["property_number"]) ?></td>
    <td class="mono"><?= h($r["available_quantity"]) ?></td>
    <td><?= status_badge(stock_label($r["available_quantity"])) ?></td>
    <td>
      <a href="equipment.php?edit=<?= h($r["equipment_id"]) ?>">Edit</a> |
      <a href="equipment.php?delete=<?= h($r["equipment_id"]) ?>" onclick="return confirm('Delete this equipment?')">Delete</a>
    </td>
  </tr>
  <?php } ?>
</table>
</div>
<?php if (count($rows) == 0) { echo "<p class='empty'>No equipment to show. Add some above.</p>"; } ?>

<?php include "footer.php"; ?>
