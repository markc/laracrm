# MYOB 12 Replacement - SQL Examples & Report Queries

This document provides complete SQL queries for all standard reports and common operations.

---

## GENERAL LEDGER REPORTS

### Trial Balance
```sql
-- Trial Balance as at a specific date
SELECT 
    coa.account_code,
    coa.account_name,
    coa.account_type,
    COALESCE(SUM(gjl.debit_amount), 0) as total_debits,
    COALESCE(SUM(gjl.credit_amount), 0) as total_credits,
    CASE 
        WHEN coa.balance_type = 'DEBIT' THEN 
            COALESCE(SUM(gjl.debit_amount), 0) - COALESCE(SUM(gjl.credit_amount), 0)
        ELSE 
            COALESCE(SUM(gjl.credit_amount), 0) - COALESCE(SUM(gjl.debit_amount), 0)
    END as balance
FROM chart_of_accounts coa
LEFT JOIN general_journal_lines gjl ON coa.account_id = gjl.account_id
LEFT JOIN general_journal gj ON gjl.journal_id = gj.journal_id
WHERE (gj.is_posted = TRUE OR gj.journal_id IS NULL)
  AND (gj.journal_date <= :report_date OR gj.journal_date IS NULL)
  AND coa.is_header = FALSE  -- Exclude header accounts
  AND coa.is_active = TRUE
GROUP BY coa.account_code, coa.account_name, coa.account_type, coa.balance_type
HAVING ABS(
    CASE 
        WHEN coa.balance_type = 'DEBIT' THEN 
            COALESCE(SUM(gjl.debit_amount), 0) - COALESCE(SUM(gjl.credit_amount), 0)
        ELSE 
            COALESCE(SUM(gjl.credit_amount), 0) - COALESCE(SUM(gjl.debit_amount), 0)
    END
) > 0.01  -- Exclude zero balances
ORDER BY coa.account_code;
```

### Balance Sheet (Statement of Financial Position)
```sql
-- Balance Sheet as at a specific date
WITH account_balances AS (
    SELECT 
        coa.account_id,
        coa.account_code,
        coa.account_name,
        coa.account_type,
        coa.account_subtype,
        coa.balance_type,
        COALESCE(SUM(gjl.debit_amount), 0) as total_debits,
        COALESCE(SUM(gjl.credit_amount), 0) as total_credits,
        CASE 
            WHEN coa.balance_type = 'DEBIT' THEN 
                COALESCE(SUM(gjl.debit_amount), 0) - COALESCE(SUM(gjl.credit_amount), 0)
            ELSE 
                COALESCE(SUM(gjl.credit_amount), 0) - COALESCE(SUM(gjl.debit_amount), 0)
        END as balance
    FROM chart_of_accounts coa
    LEFT JOIN general_journal_lines gjl ON coa.account_id = gjl.account_id
    LEFT JOIN general_journal gj ON gjl.journal_id = gj.journal_id
    WHERE (gj.is_posted = TRUE OR gj.journal_id IS NULL)
      AND (gj.journal_date <= :report_date OR gj.journal_date IS NULL)
      AND coa.is_header = FALSE
      AND coa.is_active = TRUE
      AND coa.account_type IN ('ASSET', 'LIABILITY', 'EQUITY')
    GROUP BY coa.account_id, coa.account_code, coa.account_name, 
             coa.account_type, coa.account_subtype, coa.balance_type
)
SELECT 
    account_type,
    account_subtype,
    account_code,
    account_name,
    balance
FROM account_balances
WHERE ABS(balance) > 0.01
ORDER BY 
    CASE account_type
        WHEN 'ASSET' THEN 1
        WHEN 'LIABILITY' THEN 2
        WHEN 'EQUITY' THEN 3
    END,
    account_subtype,
    account_code;

-- Summary totals
SELECT 
    account_type,
    SUM(balance) as total
FROM account_balances
WHERE ABS(balance) > 0.01
GROUP BY account_type
ORDER BY 
    CASE account_type
        WHEN 'ASSET' THEN 1
        WHEN 'LIABILITY' THEN 2
        WHEN 'EQUITY' THEN 3
    END;
```

### Profit & Loss (Income Statement)
```sql
-- P&L for a date range
WITH account_balances AS (
    SELECT 
        coa.account_id,
        coa.account_code,
        coa.account_name,
        coa.account_type,
        coa.account_subtype,
        CASE 
            WHEN coa.account_type = 'INCOME' THEN 
                COALESCE(SUM(gjl.credit_amount), 0) - COALESCE(SUM(gjl.debit_amount), 0)
            ELSE  -- EXPENSE or COGS
                COALESCE(SUM(gjl.debit_amount), 0) - COALESCE(SUM(gjl.credit_amount), 0)
        END as amount
    FROM chart_of_accounts coa
    LEFT JOIN general_journal_lines gjl ON coa.account_id = gjl.account_id
    LEFT JOIN general_journal gj ON gjl.journal_id = gj.journal_id
    WHERE gj.is_posted = TRUE
      AND gj.journal_date BETWEEN :start_date AND :end_date
      AND coa.is_header = FALSE
      AND coa.is_active = TRUE
      AND coa.account_type IN ('INCOME', 'EXPENSE')
    GROUP BY coa.account_id, coa.account_code, coa.account_name, 
             coa.account_type, coa.account_subtype
)
SELECT 
    account_type,
    account_subtype,
    account_code,
    account_name,
    amount
FROM account_balances
WHERE ABS(amount) > 0.01
ORDER BY 
    CASE account_type
        WHEN 'INCOME' THEN 1
        WHEN 'EXPENSE' THEN 2
    END,
    account_code;

-- Profit calculation
SELECT 
    (SELECT COALESCE(SUM(amount), 0) FROM account_balances WHERE account_type = 'INCOME') as total_income,
    (SELECT COALESCE(SUM(amount), 0) FROM account_balances WHERE account_type = 'EXPENSE') as total_expenses,
    (SELECT COALESCE(SUM(amount), 0) FROM account_balances WHERE account_type = 'INCOME') -
    (SELECT COALESCE(SUM(amount), 0) FROM account_balances WHERE account_type = 'EXPENSE') as net_profit;
```

### General Ledger Detail
```sql
-- General Ledger detail for specific account(s)
SELECT 
    gj.journal_date,
    gj.journal_number,
    gj.period_id,
    fp.period_name,
    gj.source_module,
    gj.source_document_number,
    gj.description,
    gjl.description as line_description,
    gjl.debit_amount,
    gjl.credit_amount,
    -- Running balance
    SUM(gjl.debit_amount - gjl.credit_amount) OVER (
        PARTITION BY gjl.account_id 
        ORDER BY gj.journal_date, gj.journal_id, gjl.line_id
    ) as running_balance
FROM general_journal gj
JOIN general_journal_lines gjl ON gj.journal_id = gjl.journal_id
JOIN chart_of_accounts coa ON gjl.account_id = coa.account_id
JOIN financial_periods fp ON gj.period_id = fp.period_id
WHERE gj.is_posted = TRUE
  AND gjl.account_id = :account_id
  AND gj.journal_date BETWEEN :start_date AND :end_date
ORDER BY gj.journal_date, gj.journal_id, gjl.line_id;
```

---

## ACCOUNTS PAYABLE REPORTS

### Aged Payables
```sql
-- Aged Payables as at a specific date
SELECT 
    s.supplier_code,
    s.supplier_name,
    s.phone,
    s.email,
    -- Current (not yet due)
    SUM(CASE 
        WHEN DATEDIFF(:report_date, pi.due_date) < 0 
        THEN pi.amount_outstanding 
        ELSE 0 
    END) as current_amount,
    -- 1-30 days overdue
    SUM(CASE 
        WHEN DATEDIFF(:report_date, pi.due_date) BETWEEN 0 AND 30 
        THEN pi.amount_outstanding 
        ELSE 0 
    END) as days_1_30,
    -- 31-60 days overdue
    SUM(CASE 
        WHEN DATEDIFF(:report_date, pi.due_date) BETWEEN 31 AND 60 
        THEN pi.amount_outstanding 
        ELSE 0 
    END) as days_31_60,
    -- 61-90 days overdue
    SUM(CASE 
        WHEN DATEDIFF(:report_date, pi.due_date) BETWEEN 61 AND 90 
        THEN pi.amount_outstanding 
        ELSE 0 
    END) as days_61_90,
    -- Over 90 days overdue
    SUM(CASE 
        WHEN DATEDIFF(:report_date, pi.due_date) > 90 
        THEN pi.amount_outstanding 
        ELSE 0 
    END) as over_90_days,
    -- Total outstanding
    SUM(pi.amount_outstanding) as total_outstanding
FROM suppliers s
LEFT JOIN purchase_invoices pi ON s.supplier_id = pi.supplier_id
WHERE pi.status IN ('POSTED', 'PARTIAL')
  AND pi.invoice_date <= :report_date
  AND pi.amount_outstanding > 0.01
GROUP BY s.supplier_id, s.supplier_code, s.supplier_name, s.phone, s.email
HAVING SUM(pi.amount_outstanding) > 0.01
ORDER BY total_outstanding DESC;

-- Summary totals
SELECT 
    SUM(CASE WHEN DATEDIFF(:report_date, due_date) < 0 THEN amount_outstanding ELSE 0 END) as total_current,
    SUM(CASE WHEN DATEDIFF(:report_date, due_date) BETWEEN 0 AND 30 THEN amount_outstanding ELSE 0 END) as total_1_30,
    SUM(CASE WHEN DATEDIFF(:report_date, due_date) BETWEEN 31 AND 60 THEN amount_outstanding ELSE 0 END) as total_31_60,
    SUM(CASE WHEN DATEDIFF(:report_date, due_date) BETWEEN 61 AND 90 THEN amount_outstanding ELSE 0 END) as total_61_90,
    SUM(CASE WHEN DATEDIFF(:report_date, due_date) > 90 THEN amount_outstanding ELSE 0 END) as total_over_90,
    SUM(amount_outstanding) as grand_total
FROM purchase_invoices
WHERE status IN ('POSTED', 'PARTIAL')
  AND invoice_date <= :report_date
  AND amount_outstanding > 0.01;
```

### Supplier Transaction History
```sql
-- Complete transaction history for a supplier
SELECT 
    pi.invoice_date as transaction_date,
    'Invoice' as transaction_type,
    pi.invoice_number as document_number,
    pi.supplier_invoice_number as reference,
    pi.total_amount as debit,
    0.00 as credit,
    pi.amount_outstanding as balance
FROM purchase_invoices pi
WHERE pi.supplier_id = :supplier_id
  AND pi.invoice_date BETWEEN :start_date AND :end_date
  AND pi.status != 'VOID'

UNION ALL

SELECT 
    sp.payment_date as transaction_date,
    'Payment' as transaction_type,
    sp.payment_number as document_number,
    sp.reference,
    0.00 as debit,
    sp.payment_amount as credit,
    0.00 as balance
FROM supplier_payments sp
WHERE sp.supplier_id = :supplier_id
  AND sp.payment_date BETWEEN :start_date AND :end_date
  AND sp.is_posted = TRUE

ORDER BY transaction_date, transaction_type;

-- Current balance
SELECT 
    current_balance,
    ytd_purchases
FROM suppliers
WHERE supplier_id = :supplier_id;
```

---

## ACCOUNTS RECEIVABLE REPORTS

### Aged Receivables
```sql
-- Aged Receivables as at a specific date
SELECT 
    c.customer_code,
    c.customer_name,
    c.phone,
    c.email,
    c.credit_limit,
    -- Current (not yet due)
    SUM(CASE 
        WHEN DATEDIFF(:report_date, si.due_date) < 0 
        THEN si.amount_outstanding 
        ELSE 0 
    END) as current_amount,
    -- 1-30 days overdue
    SUM(CASE 
        WHEN DATEDIFF(:report_date, si.due_date) BETWEEN 0 AND 30 
        THEN si.amount_outstanding 
        ELSE 0 
    END) as days_1_30,
    -- 31-60 days overdue
    SUM(CASE 
        WHEN DATEDIFF(:report_date, si.due_date) BETWEEN 31 AND 60 
        THEN si.amount_outstanding 
        ELSE 0 
    END) as days_31_60,
    -- 61-90 days overdue
    SUM(CASE 
        WHEN DATEDIFF(:report_date, si.due_date) BETWEEN 61 AND 90 
        THEN si.amount_outstanding 
        ELSE 0 
    END) as days_61_90,
    -- Over 90 days overdue
    SUM(CASE 
        WHEN DATEDIFF(:report_date, si.due_date) > 90 
        THEN si.amount_outstanding 
        ELSE 0 
    END) as over_90_days,
    -- Total outstanding
    SUM(si.amount_outstanding) as total_outstanding,
    -- Credit status
    CASE 
        WHEN c.credit_hold THEN 'ON HOLD'
        WHEN SUM(si.amount_outstanding) > c.credit_limit THEN 'OVER LIMIT'
        ELSE 'OK'
    END as credit_status
FROM customers c
LEFT JOIN sales_invoices si ON c.customer_id = si.customer_id
WHERE si.status IN ('POSTED', 'PARTIAL')
  AND si.invoice_date <= :report_date
  AND si.amount_outstanding > 0.01
GROUP BY c.customer_id, c.customer_code, c.customer_name, 
         c.phone, c.email, c.credit_limit, c.credit_hold
HAVING SUM(si.amount_outstanding) > 0.01
ORDER BY total_outstanding DESC;
```

### Sales by Customer
```sql
-- Sales by customer for a period
SELECT 
    c.customer_code,
    c.customer_name,
    COUNT(DISTINCT si.invoice_id) as invoice_count,
    SUM(si.subtotal) as sales_excl_tax,
    SUM(si.tax_amount) as tax,
    SUM(si.total_amount) as sales_incl_tax,
    -- Cost and margin (from invoice lines)
    SUM(sil.total_cost) as total_cost,
    SUM(si.total_amount) - SUM(sil.total_cost) as gross_profit,
    CASE 
        WHEN SUM(si.total_amount) > 0 THEN
            ((SUM(si.total_amount) - SUM(sil.total_cost)) / SUM(si.total_amount) * 100)
        ELSE 0
    END as margin_percent
FROM customers c
LEFT JOIN sales_invoices si ON c.customer_id = si.customer_id
LEFT JOIN sales_invoice_lines sil ON si.invoice_id = sil.invoice_id
WHERE si.invoice_date BETWEEN :start_date AND :end_date
  AND si.status != 'VOID'
  AND c.is_active = TRUE
GROUP BY c.customer_id, c.customer_code, c.customer_name
HAVING SUM(si.total_amount) > 0
ORDER BY sales_incl_tax DESC;
```

---

## INVENTORY REPORTS

### Stock on Hand
```sql
-- Current stock levels with reorder status
SELECT 
    ii.item_code,
    ii.item_name,
    ic.category_name,
    ii.unit_of_measure,
    ii.quantity_on_hand,
    ii.quantity_allocated,
    ii.quantity_on_order,
    ii.quantity_available,
    ii.reorder_point,
    ii.reorder_quantity,
    -- Costing
    CASE 
        WHEN ii.costing_method = 'AVERAGE' THEN ii.average_cost
        WHEN ii.costing_method = 'FIFO' THEN (
            SELECT COALESCE(
                SUM(quantity_remaining * unit_cost) / NULLIF(SUM(quantity_remaining), 0), 
                0
            )
            FROM inventory_fifo_layers
            WHERE item_id = ii.item_id
            AND quantity_remaining > 0
        )
        WHEN ii.costing_method = 'STANDARD' THEN ii.standard_cost
    END as current_cost,
    -- Value
    ii.quantity_on_hand * (
        CASE 
            WHEN ii.costing_method = 'AVERAGE' THEN ii.average_cost
            WHEN ii.costing_method = 'STANDARD' THEN ii.standard_cost
            WHEN ii.costing_method = 'FIFO' THEN (
                SELECT COALESCE(
                    SUM(quantity_remaining * unit_cost) / NULLIF(SUM(quantity_remaining), 0), 
                    0
                )
                FROM inventory_fifo_layers
                WHERE item_id = ii.item_id
                AND quantity_remaining > 0
            )
        END
    ) as total_value,
    -- Status
    CASE 
        WHEN ii.quantity_available <= 0 THEN 'OUT OF STOCK'
        WHEN ii.quantity_available <= ii.reorder_point THEN 'REORDER'
        WHEN ii.quantity_available <= ii.minimum_stock_level THEN 'LOW'
        ELSE 'OK'
    END as stock_status,
    -- Supplier
    s.supplier_name as preferred_supplier
FROM inventory_items ii
LEFT JOIN item_categories ic ON ii.category_id = ic.category_id
LEFT JOIN suppliers s ON ii.preferred_supplier_id = s.supplier_id
WHERE ii.is_active = TRUE
  AND ii.item_type = 'STOCK'
ORDER BY 
    CASE 
        WHEN ii.quantity_available <= 0 THEN 1
        WHEN ii.quantity_available <= ii.reorder_point THEN 2
        WHEN ii.quantity_available <= ii.minimum_stock_level THEN 3
        ELSE 4
    END,
    ii.item_code;
```

### Stock Movement
```sql
-- Stock movement for an item over a period
SELECT 
    it.transaction_date,
    it.transaction_type,
    it.transaction_number,
    it.reference,
    it.quantity,
    it.unit_cost,
    it.total_cost,
    it.quantity_before,
    it.quantity_after,
    -- Source document
    CASE it.source_type
        WHEN 'PURCHASE_INVOICE' THEN 'Purchase'
        WHEN 'SALES_INVOICE' THEN 'Sale'
        WHEN 'ADJUSTMENT' THEN 'Adjustment'
        WHEN 'TRANSFER' THEN 'Transfer'
        WHEN 'BUILD' THEN 'Build'
        ELSE it.source_type
    END as source,
    il.location_name
FROM inventory_transactions it
LEFT JOIN inventory_locations il ON it.location_id = il.location_id
WHERE it.item_id = :item_id
  AND it.transaction_date BETWEEN :start_date AND :end_date
ORDER BY it.transaction_date, it.transaction_id;
```

### Inventory Valuation
```sql
-- Complete inventory valuation
SELECT 
    ic.category_name,
    ii.item_code,
    ii.item_name,
    ii.costing_method,
    ii.quantity_on_hand,
    -- Unit cost by method
    CASE 
        WHEN ii.costing_method = 'AVERAGE' THEN ii.average_cost
        WHEN ii.costing_method = 'STANDARD' THEN ii.standard_cost
        WHEN ii.costing_method = 'FIFO' THEN (
            SELECT COALESCE(
                SUM(fl.quantity_remaining * fl.unit_cost) / NULLIF(SUM(fl.quantity_remaining), 0),
                0
            )
            FROM inventory_fifo_layers fl
            WHERE fl.item_id = ii.item_id
            AND fl.quantity_remaining > 0
        )
    END as unit_cost,
    -- Total value
    CASE 
        WHEN ii.costing_method = 'AVERAGE' THEN 
            ii.quantity_on_hand * ii.average_cost
        WHEN ii.costing_method = 'STANDARD' THEN 
            ii.quantity_on_hand * ii.standard_cost
        WHEN ii.costing_method = 'FIFO' THEN (
            SELECT COALESCE(SUM(fl.quantity_remaining * fl.unit_cost), 0)
            FROM inventory_fifo_layers fl
            WHERE fl.item_id = ii.item_id
            AND fl.quantity_remaining > 0
        )
    END as total_value
FROM inventory_items ii
LEFT JOIN item_categories ic ON ii.category_id = ic.category_id
WHERE ii.is_active = TRUE
  AND ii.item_type = 'STOCK'
  AND ii.quantity_on_hand != 0
ORDER BY ic.category_name, ii.item_code;

-- Total inventory value
SELECT 
    SUM(
        CASE 
            WHEN ii.costing_method = 'AVERAGE' THEN 
                ii.quantity_on_hand * ii.average_cost
            WHEN ii.costing_method = 'STANDARD' THEN 
                ii.quantity_on_hand * ii.standard_cost
            WHEN ii.costing_method = 'FIFO' THEN (
                SELECT COALESCE(SUM(fl.quantity_remaining * fl.unit_cost), 0)
                FROM inventory_fifo_layers fl
                WHERE fl.item_id = ii.item_id
                AND fl.quantity_remaining > 0
            )
        END
    ) as total_inventory_value
FROM inventory_items ii
WHERE ii.is_active = TRUE
  AND ii.item_type = 'STOCK';
```

---

## SALES ANALYSIS

### Sales by Item
```sql
-- Sales by item for a period
SELECT 
    ii.item_code,
    ii.item_name,
    ic.category_name,
    COUNT(DISTINCT si.invoice_id) as invoice_count,
    SUM(sil.quantity) as total_quantity_sold,
    AVG(sil.unit_price) as average_sell_price,
    SUM(sil.line_amount) as total_sales,
    AVG(sil.unit_cost) as average_cost,
    SUM(sil.total_cost) as total_cost,
    SUM(sil.line_amount) - SUM(sil.total_cost) as gross_profit,
    CASE 
        WHEN SUM(sil.line_amount) > 0 THEN
            ((SUM(sil.line_amount) - SUM(sil.total_cost)) / SUM(sil.line_amount) * 100)
        ELSE 0
    END as margin_percent
FROM inventory_items ii
JOIN sales_invoice_lines sil ON ii.item_id = sil.item_id
JOIN sales_invoices si ON sil.invoice_id = si.invoice_id
LEFT JOIN item_categories ic ON ii.category_id = ic.category_id
WHERE si.invoice_date BETWEEN :start_date AND :end_date
  AND si.status != 'VOID'
GROUP BY ii.item_id, ii.item_code, ii.item_name, ic.category_name
ORDER BY total_sales DESC;
```

---

[Document continues with more examples...]

