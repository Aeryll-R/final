<?php
$title = "Borrowers";
$lead = "People who can borrow equipment. Add, edit, or remove borrowers here.";
include "header.php";

$message = "";
$error = "";
$types = ["Student", "Faculty", "Staff"];
$edit = ["borrower_id" => "", "borrower_number" => "", "full_name" => "", "borrower_type" => "Student"];

if (isset($_POST["save"])) {
    $id = $_POST["borrower_id"];
    $number = trim($_POST["borrower_number"]);
    $name = trim($_POST["full_name"]);
    $type = $_POST["borrower_type"];

    $edit = ["borrower_id" => $id, "borrower_number" => $number, "full_name" => $name, "borrower_type" => $type];

    if ($number == "" || $name == "") {
        $error = "Please fill in the borrower number and the full name.";
    } else {
        try {
            if ($id == "") {
                $stmt = $pdo->prepare("INSERT INTO borrowers (borrower_number, full_name, borrower_type) VALUES (?, ?, ?)");
                $stmt->execute([$number, $name, $type]);
                $message = "Borrower added.";
            } else {
                $stmt = $pdo->prepare("UPDATE borrowers SET borrower_number = ?, full_name = ?, borrower_type = ? WHERE borrower_id = ?");
                $stmt->execute([$number, $name, $type, $id]);
                $message = "Borrower updated.";
            }
            $edit = ["borrower_id" => "", "borrower_number" => "", "full_name" => "", "borrower_type" => "Student"];
        } catch (PDOException $e) {
            $error = friendly_error($e);
        }
    }
}

if (isset($_GET["delete"])) {
    if (!ctype_digit($_GET["delete"])) {
        $error = "Invalid borrower ID.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM borrowers WHERE borrower_id = ?");
            $stmt->execute([$_GET["delete"]]);
            if ($stmt->rowCount() > 0) {
                $message = "Borrower deleted.";
            } else {
                $error = "No borrower found with that ID.";
            }
        } catch (PDOException $e) {
            $error = "This borrower cannot be deleted because they still have transactions. Delete those first.";
        }
    }
}

if (isset($_GET["edit"])) {
    if (!ctype_digit($_GET["edit"])) {
        $error = "Invalid borrower ID.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM borrowers WHERE borrower_id = ?");
        $stmt->execute([$_GET["edit"]]);
        $row = $stmt->fetch();
        if ($row) {
            $edit = $row;
        } else {
            $error = "No borrower found with that ID.";
        }
    }
}

$rows = $pdo->query("SELECT * FROM borrowers ORDER BY borrower_id")->fetchAll();

show_messages($message, $error);
?>

<div class="panel">
  <div class="panel-title"><?= $edit["borrower_id"] == "" ? "Add a borrower" : "Edit borrower" ?></div>
  <form method="post" class="inline">
    <input type="hidden" name="borrower_id" value="<?= h($edit["borrower_id"]) ?>">

    <label>Borrower number
      <input type="text" name="borrower_number" value="<?= h($edit["borrower_number"]) ?>" placeholder="B-007">
    </label>
    <label>Full name
      <input type="text" name="full_name" value="<?= h($edit["full_name"]) ?>" placeholder="Juan Dela Cruz">
    </label>
    <label>Type
      <select name="borrower_type">
        <?php foreach ($types as $t) { ?>
          <option <?= ($edit["borrower_type"] == $t) ? "selected" : "" ?>><?= h($t) ?></option>
        <?php } ?>
      </select>
    </label>

    <button type="submit" name="save"><?= $edit["borrower_id"] == "" ? "Add borrower" : "Save changes" ?></button>
    <?php if ($edit["borrower_id"] != "") { ?>
      <a class="button plain" href="borrowers.php">Cancel</a>
    <?php } ?>
  </form>
</div>

<div class="table-wrap">
<table>
  <tr><th>ID</th><th>Borrower number</th><th>Full name</th><th>Type</th><th>Actions</th></tr>
  <?php foreach ($rows as $r) { ?>
  <tr>
    <td class="mono"><?= h($r["borrower_id"]) ?></td>
    <td class="mono"><?= h($r["borrower_number"]) ?></td>
    <td><?= h($r["full_name"]) ?></td>
    <td><?= h($r["borrower_type"]) ?></td>
    <td>
      <a href="borrowers.php?edit=<?= h($r["borrower_id"]) ?>">Edit</a> |
      <a href="borrowers.php?delete=<?= h($r["borrower_id"]) ?>" onclick="return confirm('Delete this borrower?')">Delete</a>
    </td>
  </tr>
  <?php } ?>
</table>
</div>
<?php if (count($rows) == 0) { echo "<p class='empty'>No borrowers yet. Add one above.</p>"; } ?>

<?php include "footer.php"; ?>
