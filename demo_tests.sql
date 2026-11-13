-- =====================================================================
-- DEMO TESTS FOR THE RUBRIC (run in pgAdmin Query Tool)
-- Run ONE numbered block at a time: highlight it, then press F5.
-- Blocks marked "ERROR EXPECTED" are supposed to fail. That is the proof
-- that PostgreSQL rejects the bad data.
-- =====================================================================


-- ===================== WEEK 8: TRANSACTIONS =====================

-- 1. Successful borrowing transaction (COMMIT)
BEGIN;
INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-004'),
        CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed');
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MAX(borrow_id) FROM borrow_transactions),
        (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PH-4002'), 1);
COMMIT;
-- Check: header and item were saved together
SELECT * FROM vw_borrowing_details WHERE borrower_number = 'B-004';


-- 2. Failed borrowing transaction (ROLLBACK)
-- Run lines BEGIN to the failing INSERT, then run ROLLBACK by itself.
BEGIN;
INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-005'),
        CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed');
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MAX(borrow_id) FROM borrow_transactions), 999999, 1);   -- ERROR EXPECTED (no such equipment)
ROLLBACK;
-- Check: B-005 must still have no transaction (0 rows)
SELECT * FROM borrow_transactions
WHERE borrower_id = (SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-005');


-- ===================== WEEK 8: CONSTRAINTS =====================
-- Each block below must be rejected by PostgreSQL.

-- 3. Duplicate property number            ERROR EXPECTED (unique)
INSERT INTO equipment (equipment_name, property_number, available_quantity)
VALUES ('Laptop - Duplicate Test', 'PN-LT-1001', 1);

-- 4. Negative available quantity          ERROR EXPECTED (check)
INSERT INTO equipment (equipment_name, property_number, available_quantity)
VALUES ('Laptop - Negative Test', 'PN-TEST-1', -5);

-- 5. Invalid foreign key (borrower)       ERROR EXPECTED (foreign key)
INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
VALUES (999999, CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed');

-- 6. Invalid foreign key (equipment)      ERROR EXPECTED (foreign key)
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MIN(borrow_id) FROM borrow_transactions), 999999, 1);

-- 7. Duplicate equipment in one transaction   ERROR EXPECTED (unique borrow_id + equipment_id)
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
SELECT borrow_id, equipment_id, 1 FROM borrow_items LIMIT 1;

-- 8. Invalid borrowed quantity (0)        ERROR EXPECTED (check)
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MIN(borrow_id) FROM borrow_transactions),
        (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PC-2002'), 0);

-- 9. Equipment that is not a PC, Laptop, Tablet, or Phone   ERROR EXPECTED (check)
INSERT INTO equipment (equipment_name, property_number, available_quantity)
VALUES ('Projector - Epson X', 'PN-TEST-2', 1);


-- ===================== WEEK 10: FUNCTIONS =====================

-- 10. SQL function with different inputs
SELECT equipment_id, equipment_name, fn_total_borrowed(equipment_id) AS borrowed_now
FROM equipment ORDER BY equipment_id;

-- 11. PL/pgSQL function with different inputs
SELECT fn_check_equipment_availability(1, 1)      AS ask_1,
       fn_check_equipment_availability(1, 5)      AS ask_5,
       fn_check_equipment_availability(1, 100000) AS ask_100000,
       fn_check_equipment_availability(999999, 1) AS no_such_item;


-- ===================== WEEK 10: LIVE TRIGGER TESTS =====================
-- Uses B-006, who has no transactions, so it never touches other data.

-- 12. Insert a borrowing item with a valid quantity  -> accepted, audit row INSERT
INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-006'),
        CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed');
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MAX(borrow_id) FROM borrow_transactions),
        (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PC-2002'), 2);
SELECT * FROM borrow_items_audit ORDER BY audit_id DESC LIMIT 3;

-- 13. Insert an item that exceeds the stock          ERROR EXPECTED (BEFORE trigger)
INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
VALUES ((SELECT MAX(borrow_id) FROM borrow_transactions),
        (SELECT equipment_id FROM equipment WHERE property_number = 'PN-LT-1003'), 100000);
-- Check: stock is unchanged and no new item was saved
SELECT equipment_name, available_quantity FROM equipment WHERE property_number = 'PN-LT-1003';

-- 14. Update the item's quantity  -> accepted, audit row UPDATE with old and new quantity
UPDATE borrow_items SET quantity = 3
WHERE borrow_id = (SELECT MAX(borrow_id) FROM borrow_transactions)
  AND equipment_id = (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PC-2002');
SELECT * FROM borrow_items_audit ORDER BY audit_id DESC LIMIT 3;

-- 14b. Update to a quantity above the stock          ERROR EXPECTED (BEFORE trigger on UPDATE)
UPDATE borrow_items SET quantity = 100000
WHERE borrow_id = (SELECT MAX(borrow_id) FROM borrow_transactions);

-- 15. Delete the item  -> audit row DELETE with the old values
DELETE FROM borrow_items
WHERE borrow_id = (SELECT MAX(borrow_id) FROM borrow_transactions);
SELECT * FROM borrow_items_audit ORDER BY audit_id DESC LIMIT 3;

-- 16. Verify trigger automation: PHP never inserts into borrow_items_audit,
--     yet every step above produced an audit row.
SELECT operation, COUNT(*) AS events FROM borrow_items_audit GROUP BY operation ORDER BY operation;
