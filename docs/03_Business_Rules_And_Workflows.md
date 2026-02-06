# MYOB 12 - Complete Business Rules & Workflows

## ACCOUNTS PAYABLE WORKFLOWS

### 1. Purchase Invoice Entry & Posting

**Validation Rules:**
```
BEFORE SAVE:
- Supplier must exist and be active
- Invoice date must be valid date
- Due date must be >= invoice date
- Line items must exist (minimum 1)
- Each line must have either item_id OR account_id
- Subtotal must equal sum of line amounts
- Tax must equal sum of line tax amounts
- Total must equal subtotal + tax

BEFORE POST:
- Invoice must pass all SAVE validations
- Period must be open (not closed or locked)
- Cannot post to future periods (configurable)
- Supplier cannot be on hold (if configured)
- GL accounts must be valid and active
- Total debits must equal total credits in generated journal
```

**Posting Process:**
```sql
BEGIN TRANSACTION;

-- 1. Validate invoice
IF NOT validate_invoice(invoice_id) THEN
    ROLLBACK;
    RETURN 'Validation failed';
END IF;

-- 2. Create journal entry header
INSERT INTO general_journal (
    journal_number, journal_date, period_id,
    source_module, source_document_id,
    description, total_debits, total_credits
) VALUES (
    get_next_journal_number('GJ'),
    invoice.invoice_date,
    get_period_for_date(invoice.invoice_date),
    'AP',
    invoice.invoice_id,
    'Purchase Invoice: ' || invoice.invoice_number,
    invoice.total_amount,
    invoice.total_amount
);

-- 3. Create journal lines for each invoice line
FOR each line IN invoice_lines LOOP
    -- Debit expense or inventory
    INSERT INTO general_journal_lines (
        journal_id, line_number, account_id,
        debit_amount, credit_amount, description
    ) VALUES (
        journal_id, line_counter,
        line.account_id,
        line.line_amount, 0,
        line.description
    );
    
    -- Debit tax (if applicable)
    IF line.tax_amount > 0 THEN
        INSERT INTO general_journal_lines (
            journal_id, line_number, account_id,
            debit_amount, credit_amount
        ) VALUES (
            journal_id, line_counter + 1,
            get_tax_account(line.tax_code_id),
            line.tax_amount, 0
        );
    END IF;
END LOOP;

-- 4. Credit AP Control
INSERT INTO general_journal_lines (
    journal_id, line_number, account_id,
    debit_amount, credit_amount, description
) VALUES (
    journal_id, line_counter,
    supplier.ap_control_account_id,
    0, invoice.total_amount,
    'AP: ' || supplier.supplier_name
);

-- 5. Update invoice status
UPDATE purchase_invoices
SET status = 'POSTED',
    is_posted = TRUE,
    posted_date = NOW(),
    posted_by = current_user(),
    journal_id = journal_id
WHERE invoice_id = invoice.invoice_id;

-- 6. Update supplier balance
UPDATE suppliers
SET current_balance = current_balance + invoice.total_amount,
    ytd_purchases = ytd_purchases + invoice.total_amount
WHERE supplier_id = invoice.supplier_id;

-- 7. If inventory items, update inventory
FOR each line WHERE line_type = 'ITEM' LOOP
    CALL receive_inventory(
        line.item_id,
        line.quantity,
        line.unit_cost,
        invoice.invoice_id
    );
END LOOP;

COMMIT;
```

### 2. Supplier Payment Processing

**Payment Workflow:**
```
1. SELECT INVOICES TO PAY:
   - Display unpaid/partially paid invoices
   - Filter by supplier, due date, amount
   - Show available discounts (early payment)
   - Calculate total payment amount

2. CREATE PAYMENT:
   - Select payment method (CHEQUE, EFT, CASH)
   - Select bank account
   - Enter payment date
   - Enter reference/cheque number
   - Allocate to invoices

3. ALLOCATION:
   - Can pay multiple invoices with one payment
   - Can partially pay invoices
   - Can take early payment discount
   - Total allocations must equal payment amount
   - Track unallocated amounts (overpayments)

4. POST PAYMENT:
   - Create journal entry:
     DR Accounts Payable
     DR Discount Taken (if applicable)
     CR Bank Account
   - Update invoice amount_paid
   - Update invoice status (PARTIAL or PAID)
   - Update supplier current_balance
   - Create payment allocations

5. PRINT/EXPORT:
   - Print cheque (if applicable)
   - Generate EFT file (ABA format)
   - Email remittance advice to supplier
```

**Payment Allocation Logic:**
```sql
-- When applying payment to invoices
BEGIN TRANSACTION;

DECLARE remaining_amount DECIMAL(15,2);
SET remaining_amount = payment.payment_amount;

FOR each allocation IN payment_allocations LOOP
    -- Cannot allocate more than invoice outstanding
    IF allocation.amount_allocated > invoice.amount_outstanding THEN
        RAISE ERROR 'Cannot allocate more than outstanding';
    END IF;
    
    -- Cannot allocate more than remaining payment
    IF allocation.amount_allocated > remaining_amount THEN
        RAISE ERROR 'Insufficient payment amount';
    END IF;
    
    -- Update invoice
    UPDATE purchase_invoices
    SET amount_paid = amount_paid + allocation.amount_allocated
    WHERE invoice_id = allocation.invoice_id;
    
    -- Update status if fully paid
    IF invoice.amount_outstanding = 0 THEN
        UPDATE purchase_invoices
        SET status = 'PAID'
        WHERE invoice_id = allocation.invoice_id;
    ELSIF invoice.amount_paid > 0 THEN
        UPDATE purchase_invoices
        SET status = 'PARTIAL'
        WHERE invoice_id = allocation.invoice_id;
    END IF;
    
    SET remaining_amount = remaining_amount - allocation.amount_allocated;
END LOOP;

-- Store unallocated amount
UPDATE supplier_payments
SET total_allocated = payment_amount - remaining_amount
WHERE payment_id = payment.payment_id;

COMMIT;
```

---

## ACCOUNTS RECEIVABLE WORKFLOWS

### 1. Sales Invoice Processing

**Invoice Creation Workflow:**
```
1. CUSTOMER SELECTION:
   - Validate customer is active
   - Check credit limit
   - Check credit hold status
   - Load customer defaults (terms, tax, pricing)

2. ITEM SELECTION:
   - Check item is active and sellable
   - Check inventory availability
   - Apply customer pricing level
   - Calculate line total
   - Apply discount (if authorized)

3. CALCULATION:
   - Subtotal = sum of line amounts
   - Tax = calculate based on tax codes
   - Freight = additional charge
   - Total = subtotal + tax + freight

4. VALIDATION:
   - Customer not over credit limit
   - Items in stock (or allow backorder)
   - Prices not below minimum
   - All required fields populated

5. SAVE/POST:
   - Save as DRAFT for later
   - POST to commit transaction
```

**Credit Limit Check:**
```sql
FUNCTION check_customer_credit(customer_id, new_invoice_amount)
RETURNS BOOLEAN

-- Get customer details
SELECT credit_limit, current_balance, credit_hold
INTO limit, balance, on_hold
FROM customers
WHERE customer_id = customer_id;

-- Check if on credit hold
IF on_hold THEN
    RETURN FALSE, 'Customer on credit hold';
END IF;

-- Calculate total exposure
total_exposure = current_balance + 
                 get_open_orders_total(customer_id) +
                 new_invoice_amount;

-- Check against limit
IF total_exposure > credit_limit THEN
    RETURN FALSE, 'Exceeds credit limit';
END IF;

RETURN TRUE, 'OK';
```

**Invoice Posting:**
```sql
BEGIN TRANSACTION;

-- Create journal header
INSERT INTO general_journal...

-- For each invoice line
FOR each line LOOP
    -- Credit sales revenue
    INSERT INTO general_journal_lines
    VALUES (... account_id = line.income_account_id,
               credit_amount = line.line_amount ...);
    
    -- Credit tax payable
    IF line.tax_amount > 0 THEN
        INSERT INTO general_journal_lines
        VALUES (... account_id = tax_payable_account,
                   credit_amount = line.tax_amount ...);
    END IF;
    
    -- If inventory item, record COGS
    IF line.item_id IS NOT NULL THEN
        -- Debit COGS
        INSERT INTO general_journal_lines
        VALUES (... account_id = item.cogs_account_id,
                   debit_amount = calculate_cogs(line) ...);
        
        -- Credit Inventory
        INSERT INTO general_journal_lines
        VALUES (... account_id = item.inventory_account_id,
                   credit_amount = calculate_cogs(line) ...);
        
        -- Create inventory transaction (reduce stock)
        CALL reduce_inventory(line.item_id, line.quantity);
    END IF;
END LOOP;

-- Debit AR Control
INSERT INTO general_journal_lines
VALUES (... account_id = customer.ar_control_account_id,
           debit_amount = invoice.total_amount ...);

-- Update invoice
UPDATE sales_invoices
SET status = 'POSTED',
    is_posted = TRUE,
    posted_date = NOW(),
    journal_id = journal_id;

-- Update customer balance
UPDATE customers
SET current_balance = current_balance + invoice.total_amount,
    ytd_sales = ytd_sales + invoice.total_amount,
    last_sale_date = invoice.invoice_date;

COMMIT;
```

### 2. Customer Receipt Processing

Similar to AP payments but in reverse...

[Document continues with detailed workflows for all modules...]

