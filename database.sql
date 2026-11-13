-- =====================================================================
-- EQUIPMENT BORROWING DATABASE  (PostgreSQL)
-- Run this whole file once in pgAdmin: Query Tool on Enrollment_Activity.
-- It is safe to run again: it only creates what is missing and
-- replaces views, functions and triggers.
-- =====================================================================


-- =====================================================================
-- PART 1 (Week 4 and 5): FOUR TABLES, KEYS, AND CONSTRAINTS
-- =====================================================================

CREATE TABLE IF NOT EXISTS borrowers (
    borrower_id     SERIAL PRIMARY KEY,
    borrower_number VARCHAR(20)  NOT NULL UNIQUE,
    full_name       VARCHAR(120) NOT NULL,
    borrower_type   VARCHAR(20)  NOT NULL
                    CHECK (borrower_type IN ('Student', 'Faculty', 'Staff'))
);

CREATE TABLE IF NOT EXISTS equipment (
    equipment_id       SERIAL PRIMARY KEY,
    equipment_name     VARCHAR(120) NOT NULL,
    property_number    VARCHAR(30)  NOT NULL UNIQUE,
    available_quantity INTEGER      NOT NULL DEFAULT 0
                       CHECK (available_quantity >= 0)
);

CREATE TABLE IF NOT EXISTS borrow_transactions (
    borrow_id   SERIAL PRIMARY KEY,
    borrower_id INTEGER     NOT NULL REFERENCES borrowers (borrower_id),
    borrow_date DATE        NOT NULL DEFAULT CURRENT_DATE,
    due_date    DATE        NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'Borrowed'
                CHECK (status IN ('Borrowed', 'Returned', 'Overdue')),
    CHECK (due_date >= borrow_date)
);

CREATE TABLE IF NOT EXISTS borrow_items (
    item_id      SERIAL PRIMARY KEY,
    borrow_id    INTEGER NOT NULL REFERENCES borrow_transactions (borrow_id),
    equipment_id INTEGER NOT NULL REFERENCES equipment (equipment_id),
    quantity     INTEGER NOT NULL CHECK (quantity > 0),
    CONSTRAINT uq_borrow_equipment UNIQUE (borrow_id, equipment_id)
);

-- Only PC, Laptop, Tablet, and Phone can be added as equipment.
-- NOT VALID means old rows are not re-checked, but every new or edited row is.
ALTER TABLE equipment DROP CONSTRAINT IF EXISTS chk_equipment_type;
ALTER TABLE equipment ADD CONSTRAINT chk_equipment_type
    CHECK (equipment_name ~ '^(PC|Laptop|Tablet|Phone) - .+') NOT VALID;


-- =====================================================================
-- SAMPLE DATA (equipment that can be borrowed is listed already)
-- =====================================================================

INSERT INTO equipment (equipment_name, property_number, available_quantity) VALUES
    ('Laptop - Dell Latitude 5420',        'PN-LT-1001', 6),
    ('Laptop - Lenovo ThinkPad E14',       'PN-LT-1002', 5),
    ('Laptop - HP ProBook 450',            'PN-LT-1003', 4),
    ('PC - Dell OptiPlex 3080',            'PN-PC-2001', 8),
    ('PC - HP ProDesk 400 G7',             'PN-PC-2002', 6),
    ('PC - Lenovo ThinkCentre M70',        'PN-PC-2003', 2),
    ('Tablet - iPad 9th Gen',              'PN-TB-3001', 5),
    ('Tablet - Samsung Galaxy Tab A8',     'PN-TB-3002', 4),
    ('Tablet - Lenovo Tab M10',            'PN-TB-3003', 0),
    ('Phone - Samsung Galaxy A14',         'PN-PH-4001', 6),
    ('Phone - Xiaomi Redmi 12',            'PN-PH-4002', 5),
    ('Phone - iPhone SE',                  'PN-PH-4003', 3)
ON CONFLICT (property_number) DO NOTHING;

INSERT INTO borrowers (borrower_number, full_name, borrower_type) VALUES
    ('B-001', 'Juan Dela Cruz',     'Student'),
    ('B-002', 'Maria Santos',       'Student'),
    ('B-003', 'Ana Reyes',          'Faculty'),
    ('B-004', 'Carlo Mendoza',      'Staff'),
    ('B-005', 'Liza Garcia',        'Student'),
    ('B-006', 'Paolo Villanueva',   'Student')
ON CONFLICT (borrower_number) DO NOTHING;

-- Optional clean-up: remove old equipment that is not a PC, Laptop, Tablet, or Phone
-- and was never borrowed. Remove the two dashes in front of the lines to use it.
-- DELETE FROM equipment
-- WHERE equipment_name !~ '^(PC|Laptop|Tablet|Phone) - '
--   AND equipment_id NOT IN (SELECT equipment_id FROM borrow_items);


-- =====================================================================
-- WEEK 10: TRIGGERS (created before the sample transactions on purpose,
-- so the audit table already shows real events)
-- =====================================================================

-- Audit table (stores the item, the operation, old and new values, and the time)
CREATE TABLE IF NOT EXISTS borrow_items_audit (
    audit_id         SERIAL PRIMARY KEY,
    item_id          INTEGER,
    operation        VARCHAR(10) NOT NULL,
    old_equipment_id INTEGER,
    new_equipment_id INTEGER,
    old_quantity     INTEGER,
    new_quantity     INTEGER,
    event_time       TIMESTAMP NOT NULL DEFAULT NOW()
);

-- 5.2 PL/pgSQL function: can the requested quantity be accommodated?
CREATE OR REPLACE FUNCTION fn_check_equipment_availability(p_equipment_id INTEGER, p_requested_quantity INTEGER)
RETURNS BOOLEAN AS $$
DECLARE
    v_stock INTEGER;
BEGIN
    SELECT available_quantity INTO v_stock
    FROM equipment
    WHERE equipment_id = p_equipment_id;

    IF v_stock IS NULL THEN
        RETURN FALSE;               -- equipment does not exist
    ELSIF p_requested_quantity <= v_stock THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- 5.1 SQL function: quantity of one equipment item that is out right now
CREATE OR REPLACE FUNCTION fn_total_borrowed(p_equipment_id INTEGER)
RETURNS INTEGER AS $$
    SELECT COALESCE(SUM(bi.quantity), 0)::INTEGER
    FROM borrow_items bi
    JOIN borrow_transactions t ON bi.borrow_id = t.borrow_id
    WHERE bi.equipment_id = $1
      AND t.status = 'Borrowed';
$$ LANGUAGE sql;

-- 5.3 BEFORE trigger function: reject a quantity that is more than the stock
CREATE OR REPLACE FUNCTION fn_validate_borrow_quantity()
RETURNS TRIGGER AS $$
DECLARE
    v_stock INTEGER;
BEGIN
    SELECT available_quantity INTO v_stock
    FROM equipment
    WHERE equipment_id = NEW.equipment_id;

    IF NEW.quantity > v_stock THEN
        RAISE EXCEPTION 'Requested quantity (%) is more than the available stock (%).', NEW.quantity, v_stock;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_validate_borrow_quantity ON borrow_items;
CREATE TRIGGER trg_validate_borrow_quantity
    BEFORE INSERT OR UPDATE ON borrow_items
    FOR EACH ROW EXECUTE PROCEDURE fn_validate_borrow_quantity();

-- 5.4 AFTER trigger function: write every INSERT, UPDATE, and DELETE to the audit table
CREATE OR REPLACE FUNCTION fn_borrow_items_audit()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO borrow_items_audit (item_id, operation, new_equipment_id, new_quantity)
        VALUES (NEW.item_id, TG_OP, NEW.equipment_id, NEW.quantity);
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO borrow_items_audit (item_id, operation, old_equipment_id, new_equipment_id, old_quantity, new_quantity)
        VALUES (NEW.item_id, TG_OP, OLD.equipment_id, NEW.equipment_id, OLD.quantity, NEW.quantity);
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO borrow_items_audit (item_id, operation, old_equipment_id, old_quantity)
        VALUES (OLD.item_id, TG_OP, OLD.equipment_id, OLD.quantity);
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_borrow_items_audit ON borrow_items;
CREATE TRIGGER trg_borrow_items_audit
    AFTER INSERT OR UPDATE OR DELETE ON borrow_items
    FOR EACH ROW EXECUTE PROCEDURE fn_borrow_items_audit();


-- =====================================================================
-- SAMPLE TRANSACTIONS (only added when the table is empty)
-- Gives the reports real data: two borrowers with 2 transactions,
-- one overdue record, and borrowers and equipment with no history.
-- =====================================================================
DO $$
DECLARE
    t INTEGER;
BEGIN
    IF (SELECT COUNT(*) FROM borrow_transactions) = 0 THEN

        -- B-001, returned laptop
        INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
        VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-001'),
                CURRENT_DATE - 30, CURRENT_DATE - 23, 'Returned')
        RETURNING borrow_id INTO t;
        INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
        VALUES (t, (SELECT equipment_id FROM equipment WHERE property_number = 'PN-LT-1001'), 1);

        -- B-001, tablet and phone out now
        INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
        VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-001'),
                CURRENT_DATE - 3, CURRENT_DATE + 4, 'Borrowed')
        RETURNING borrow_id INTO t;
        INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
        VALUES (t, (SELECT equipment_id FROM equipment WHERE property_number = 'PN-TB-3001'), 1),
               (t, (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PH-4001'), 1);

        -- B-002, two PCs, late (overdue record)
        INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
        VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-002'),
                CURRENT_DATE - 10, CURRENT_DATE - 3, 'Borrowed')
        RETURNING borrow_id INTO t;
        INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
        VALUES (t, (SELECT equipment_id FROM equipment WHERE property_number = 'PN-PC-2001'), 2);

        -- B-002, returned laptop
        INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
        VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-002'),
                CURRENT_DATE - 45, CURRENT_DATE - 38, 'Returned')
        RETURNING borrow_id INTO t;
        INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
        VALUES (t, (SELECT equipment_id FROM equipment WHERE property_number = 'PN-LT-1002'), 1);

        -- B-003, two laptops out now
        INSERT INTO borrow_transactions (borrower_id, borrow_date, due_date, status)
        VALUES ((SELECT borrower_id FROM borrowers WHERE borrower_number = 'B-003'),
                CURRENT_DATE, CURRENT_DATE + 7, 'Borrowed')
        RETURNING borrow_id INTO t;
        INSERT INTO borrow_items (borrow_id, equipment_id, quantity)
        VALUES (t, (SELECT equipment_id FROM equipment WHERE property_number = 'PN-LT-1001'), 2);

    END IF;
END $$;


-- =====================================================================
-- WEEK 8: VIEWS (with ROUND, ABS, NULLIF, and CASE)
-- =====================================================================

DROP VIEW IF EXISTS vw_borrowing_details;
CREATE VIEW vw_borrowing_details AS
SELECT t.borrow_id,
       b.borrower_number,
       b.full_name,
       b.borrower_type,
       e.equipment_name,
       e.property_number,
       bi.quantity,
       t.borrow_date,
       t.due_date,
       t.status
FROM borrowers b
INNER JOIN borrow_transactions t ON b.borrower_id = t.borrower_id
INNER JOIN borrow_items bi ON t.borrow_id = bi.borrow_id
INNER JOIN equipment e ON bi.equipment_id = e.equipment_id;

DROP VIEW IF EXISTS vw_equipment_usage;
CREATE VIEW vw_equipment_usage AS
SELECT e.equipment_id,
       e.equipment_name,
       e.property_number,
       e.available_quantity,
       COUNT(bi.item_id) AS total_transactions,
       COALESCE(SUM(bi.quantity), 0) AS total_quantity_borrowed,
       ROUND(100.0 * COALESCE(SUM(bi.quantity), 0)
             / NULLIF(e.available_quantity + COALESCE(SUM(bi.quantity), 0), 0), 1) AS usage_percent,
       ABS(e.available_quantity - COALESCE(SUM(bi.quantity), 0)) AS stock_gap,
       CASE
           WHEN e.available_quantity = 0 THEN 'Out of Stock'
           WHEN e.available_quantity <= 2 THEN 'Low Stock'
           ELSE 'Available'
       END AS availability
FROM equipment e
LEFT JOIN borrow_items bi ON e.equipment_id = bi.equipment_id
GROUP BY e.equipment_id, e.equipment_name, e.property_number, e.available_quantity;
