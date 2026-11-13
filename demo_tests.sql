BEGIN;
INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-004'),
        CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed');
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MAX(borrow_id) FROM borrow_transactions),
        (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PH-4002'), 1);
COMMIT;
SELECT * FROM vw_borrowing_details WHERE borrower_number = 'B-004';

BEGIN;
INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-005'),
        CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed');
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MAX(borrow_id) FROM borrow_transactions), 999999, 1);
ROLLBACK;
SELECT * FROM borrow_transactions
WHERE borrower_id = (SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-005');

INSERT INTO equipment (equipment_name, property_number, available_quantity)
VALUES ('Laptop - Duplicate Test', 'PN-LT-1001', 1);

INSERT INTO equipment (equipment_name, property_number, available_quantity)
VALUES ('Laptop - Negative Test', 'PN-TEST-1', -5);

INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
VALUES (999999, CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed');

INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MIN(borrow_id) FROM borrow_transactions), 999999, 1);

INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
SELECT borrow_id, equipment_id, 1 FROM borrow_items LIMIT 1;

INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MIN(borrow_id) FROM borrow_transactions),
        (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PC-2002'), 0);

INSERT INTO equipment (equipment_name, property_number, available_quantity)
VALUES ('Projector - Epson X', 'PN-TEST-2', 1);

SELECT equipment_id, equipment_name, fn_total_borrowed(equipment_id) AS borrowed_now
FROM equipment ORDER BY equipment_id;

SELECT fn_check_equipment_availability(1, 1)      AS ask_1,
       fn_check_equipment_availability(1, 5)      AS ask_5,
       fn_check_equipment_availability(1, 100000) AS ask_100000,
       fn_check_equipment_availability(999999, 1) AS no_such_item;

INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-006'),
        CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed');
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MAX(borrow_id) FROM borrow_transactions),
        (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PC-2002'), 2);
SELECT * FROM borrow_items_audit ORDER BY audit_id DESC LIMIT 3;

INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MAX(borrow_id) FROM borrow_transactions),
        (SELECT equipment_id FROM equipment WHERE property_number = 'PN-LT-1003'), 100000);
SELECT equipment_name, available_quantity FROM equipment WHERE property_number = 'PN-LT-1003';

UPDATE borrow_items SET quantity = 3
WHERE borrow_id = (SELECT MAX(borrow_id) FROM borrow_transactions)
  AND equipment_id = (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PC-2002');
SELECT * FROM borrow_items_audit ORDER BY audit_id DESC LIMIT 3;

UPDATE borrow_items SET quantity = 100000
WHERE borrow_id = (SELECT MAX(borrow_id) FROM borrow_transactions);

DELETE FROM borrow_items
WHERE borrow_id = (SELECT MAX(borrow_id) FROM borrow_transactions);
SELECT * FROM borrow_items_audit ORDER BY audit_id DESC LIMIT 3;

SELECT operation, COUNT(*) AS events FROM borrow_items_audit GROUP BY operation ORDER BY operation;
