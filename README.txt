EQUIPMENT BORROWING WEBSITE (PHP + PostgreSQL)
Equipment is limited to PC, Laptop, Tablet, and Phone.

=====================================================================
FILES
=====================================================================
db.php             UNCHANGED. Connection and helper functions.
header.php         layout, sidebar navigation, device-type helpers
footer.php         closes the layout
style.css          the new look
index.php          dashboard: live counts, units by device type, latest activity
borrowers.php      create, read, update, delete borrowers
equipment.php      create, read, update, delete equipment (type dropdown)
transactions.php   create, read, update, delete transactions
borrow_items.php   list of available equipment + add, edit, remove items
admin.php          ALL reports (Week 6, 7, 8, 10) in one page
database.sql       tables, constraints, sample data, views, functions, triggers
demo_tests.sql     COMMIT / ROLLBACK / constraint / trigger demos for pgAdmin

Deleted: tests.php (database tests) and reports.php (now admin.php).

=====================================================================
SETUP ON THE XAMPP COMPUTER (the one that runs PHP)
=====================================================================
1. Copy this folder into C:\xampp\htdocs\borrowing
2. XAMPP Control Panel > Apache > Config > PHP (php.ini). Remove the
   semicolon in front of these two lines, save, then restart Apache:
       extension=pdo_pgsql
       extension=pgsql
3. Open db.php and check the password. Keep db.php out of GitHub.
4. Open http://localhost/borrowing/

=====================================================================
SETUP ON THE DATABASE COMPUTER (the one that runs PostgreSQL)
=====================================================================
1. In pgAdmin open Query Tool on the Enrollment_Activity database, open
   database.sql, and run it once (F5). It creates the four tables, the
   audit table, the constraints, the sample equipment and borrowers, the
   two views, both functions, and both triggers.
2. Check it worked:  SELECT COUNT(*) FROM equipment;  (should be 12 or more)

=====================================================================
REMOTE DATABASE (Part 1, criterion 1: 7 points)
PHP and PostgreSQL must be on two different physical computers.
Call them PC-WEB (XAMPP) and PC-DB (PostgreSQL).
=====================================================================
A. Find the IP addresses. On each computer run  ipconfig  in Command
   Prompt and write down the IPv4 Address. Both must be on the same
   network (same Wi-Fi or router). Example: PC-WEB 192.168.1.20,
   PC-DB 192.168.1.30.

B. On PC-DB, let PostgreSQL listen on the network (2 pts):
   1. Open postgresql.conf (C:\Program Files\PostgreSQL\<version>\data).
   2. Change   listen_addresses = 'localhost'   to   listen_addresses = '*'
      (or to the PC-DB IP address only).

C. On PC-DB, allow ONLY the web computer (restriction, 2 pts):
   1. Open pg_hba.conf in the same folder.
   2. Add this line at the end, using the PC-WEB IP address:
         host   Enrollment_Activity   postgres   192.168.1.20/32   scram-sha-256
      /32 means that one computer only. Do not use 0.0.0.0/0.
   3. Restart PostgreSQL: Windows Services > postgresql-x64-<version> > Restart.

D. On PC-DB, open the firewall for that one computer only:
   Windows Defender Firewall > Advanced settings > Inbound Rules > New Rule
   > Port > TCP 5432 > Allow > name it "PostgreSQL" > after it is created,
   open its Properties > Scope > Remote IP address > add 192.168.1.20.

E. On PC-WEB, open db.php and change ONLY this line (the comment inside
   db.php says the same):
         $host = "192.168.1.30";      // the IP address of PC-DB
   Nothing else in the code changes.

F. Prove it for the instructor (working connection, 2 pts):
   1. On PC-WEB open http://localhost/borrowing/. The sidebar shows
      the PC-DB IP address under the database name.
   2. Add a borrower on the website, then on PC-DB run in pgAdmin:
         SELECT * FROM borrowers ORDER BY borrower_id DESC;
      The new row appears on the other computer.
   3. Show that a third computer is refused (the pg_hba.conf line allows
      only PC-WEB).

Note: a database on localhost of the XAMPP computer does not count.

=====================================================================
LIVE DEMONSTRATION ORDER (what the instructor asks for)
=====================================================================
1. Borrowers page: add a borrower, edit it, then delete it.
2. Equipment page: add a device (choose a type), edit it, filter by type.
3. Transactions page: create a transaction for a borrower.
4. Borrowed items page: open the transaction, press Select on two
   different devices, and add each one with a quantity.
5. Edit one item's quantity, then delete one item.
6. Try a quantity larger than the stock: the trigger message appears.
7. Delete a permitted record (a transaction deletes its items first).
8. Admin reports page: show every week's report, add a record, refresh,
   and point out the numbers changed.
9. pgAdmin: run demo_tests.sql block by block (COMMIT, ROLLBACK,
   constraints, triggers).

=====================================================================
RUBRIC CHECKLIST: WHERE EACH REQUIREMENT IS ANSWERED
=====================================================================
PART 1 (30 points)
1. Remote database architecture ........ steps above (db.php $host,
                                         postgresql.conf, pg_hba.conf, firewall)
2. Structure and relationships ......... database.sql, Part 1 (4 tables,
                                         primary keys, foreign keys, UNIQUE,
                                         NOT NULL, CHECK)
3. CRUD borrowers and equipment ........ borrowers.php, equipment.php
4. CRUD transactions and items ......... transactions.php, borrow_items.php
5. PDO and validation .................. every query uses prepare/execute;
                                         ctype_digit checks IDs; friendly_error()
                                         handles constraint and trigger errors
6. Interface and usability ............. sidebar navigation, clear workflow
                                         (dashboard steps), colored status
                                         badges, green and red message boxes

WEEK 6 (12 points)  admin.php, section "Week 6"
Retrieval + aliases + DISTINCT, WHERE with AND / OR / IN / LIKE and a date
range filter, ORDER BY ascending and descending (multi-column), GROUP BY
(borrower type, status), HAVING (borrowers with 2 or more transactions).
Required reports: Equipment Inventory Listing, Filtered Borrowing Records,
Borrower Type Summary, Transaction Status Summary, Grouped Borrowing Analysis.

WEEK 7 (18 points)  admin.php, section "Week 7"
COUNT, SUM, AVG, MIN, MAX (Equipment Borrowing Summary); INNER JOIN on four
tables (Complete Borrowing Report); LEFT JOIN (Borrowers Without
Transactions, Equipment Without Borrowing History); UNION and UNION ALL
side by side (compare the row counts); string functions; date functions
(CURRENT_DATE, EXTRACT, DATE_TRUNC, TO_CHAR); Monthly Borrowing Report.

WEEK 8 (20 points)
Math functions + CASE ... vw_equipment_usage (ROUND, ABS, NULLIF, CASE)
Two views ................ vw_borrowing_details, vw_equipment_usage
                           shown in admin.php; the dashboard reads from
                           vw_borrowing_details (reuse in the application)
BEGIN/COMMIT/ROLLBACK .... demo_tests.sql blocks 1 and 2; transactions.php
                           deletes items and header as one transaction
Constraints .............. database.sql; rejected inputs shown in
                           demo_tests.sql blocks 3 to 9

WEEK 10 (20 points)
5.1 SQL function ......... fn_total_borrowed (admin.php, demo block 10)
5.2 PL/pgSQL function .... fn_check_equipment_availability, tried with 1, 5,
                           and 10 in admin.php and more in demo block 11
5.3 BEFORE trigger ....... trg_validate_borrow_quantity (try an oversized
                           quantity on Borrowed items; demo blocks 13, 14b)
5.4 AFTER audit trigger .. trg_borrow_items_audit with NEW, OLD, TG_OP;
                           table borrow_items_audit (admin.php, last report)
5.5 Integration .......... add, edit, delete items on the website, then open
                           Admin reports: audit rows appear automatically
Live trigger tests ....... demo_tests.sql blocks 12 to 16
