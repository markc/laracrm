1. GENERAL LEDGER - Core Data Structures & Flows
Key Data Tables:

    Accounts Table: Chart of Accounts with account codes, names, types (Asset/Liability/Equity/Income/Expense), header/detail flags, tax codes
    Transactions Table: All journal entries with date, account, debit/credit amounts, reference, description, source module
    Financial Periods Table: Period definitions, open/closed status, year-end flags

Transaction Flow:

Source Entry (AP/AR/Inventory/Payroll) 
  → Generate Journal Entry
    → Post to Transactions Table
      → Update Account Balances
        → Reflect in Trial Balance/Financial Reports

Integration Points:

    All subsidiary modules post journal entries to GL
    Period-end processes consolidate transactions
    Budget vs Actual comparisons draw from both budgets and actuals tables

2. ACCOUNTS PAYABLE - Data Relationships
Key Data Tables:

    Supplier Master: Supplier details, terms, tax status, account codes (expense/liability)
    Purchase Invoices: Header (supplier, date, total, due date) + Line Items (GL account, amount, tax)
    Payments Table: Payment batches, cheque numbers, amounts, applied invoices
    Supplier Transactions: Running ledger of all supplier activity

Transaction Flow:

Enter Purchase Invoice
  → Create Invoice Header + Line Items
    → Post to Supplier Account (increase liability)
      → Post to Expense GL Accounts
        → Invoice appears in Unpaid Invoices
          → Process Payment
            → Reduce Supplier Balance
              → Post to Bank Account

Data Relationships:

    Supplier Master links to all invoices via Supplier ID
    Each invoice line links to GL Account Code
    Payments link to multiple invoices through allocation table
    Age analysis calculated from invoice dates and current date

3. ACCOUNTS RECEIVABLE - Transaction Patterns
Key Data Tables:

    Customer Master: Customer details, credit limits, terms, pricing levels
    Sales Invoices: Header + Line Items (item/account, quantity, price, tax)
    Receipts Table: Receipt batches, amounts, bank account, applied invoices
    Customer Transactions: Complete customer ledger history

Transaction Flow:

Create Sales Invoice (or from Sales Order)
  → Generate Invoice Header + Lines
    → Post to Customer Account (increase receivable)
      → Post to Income GL Accounts
        → Update Inventory (if applicable)
          → Invoice in Unpaid Invoices
            → Receive Payment
              → Reduce Customer Balance
                → Post to Bank Account

Integration Points:

    Links to Inventory for stock items
    Links to GL for income/asset accounts
    Statement generation pulls from customer transactions
    Aging reports calculate from invoice due dates

4. INVENTORY - Complex Data Relationships
Key Data Tables:

    Item Master: Item codes, descriptions, cost method (FIFO/Average), reorder points, pricing
    Stock Locations: Multiple warehouses/bins if applicable
    Inventory Transactions: All movements (purchases, sales, adjustments, transfers)
    Cost Layers: For FIFO costing - tracks cost by purchase batch
    Bills of Materials: For assembled items - component relationships

Transaction Flow:

Purchase Flow:

Receive Inventory (from Purchase Order)
  → Increase Quantity on Hand
    → Update Cost (Average or add FIFO layer)
      → Post to Inventory Asset Account
        → Link to Supplier Invoice when received

Sales Flow:

Sell Inventory (from Sales Invoice)
  → Decrease Quantity on Hand
    → Calculate Cost of Goods Sold (FIFO/Average)
      → Post COGS to Expense Account
        → Reduce Inventory Asset Account
          → Post Sales Revenue to Income Account

Adjustment Flow:

Inventory Adjustment Entry
  → Increase/Decrease Quantity
    → Post variance to Adjustment Account
      → Update Inventory Asset value

Key Relationships:

    Item Master → Inventory Transactions (one-to-many)
    Inventory Transactions → GL Postings (automatically generated)
    Sales Invoices → Inventory Transactions → COGS calculation
    Purchase Orders → Goods Received → Invoices (three-way matching)

5. SALES ORDER PROCESSING - Workflow Integration
Data Structure:

    Sales Orders: Header (customer, date, ship date) + Lines (items, quantities, prices)
    Order Status: Open/Partial/Complete/Invoiced/Cancelled
    Backorder Table: Items not yet fulfilled

Process Flow:

Enter Sales Order
  → Check Inventory Availability
    → Reserve Stock (optional)
      → Pick/Pack Items
        → Ship Goods
          → Convert to Invoice
            → Post AR & Inventory transactions
              → Close Order (or partial if backordered)

6. PURCHASE ORDER PROCESSING
Process Flow:

Create Purchase Order
  → Send to Supplier
    → Receive Goods (full or partial)
      → Update Inventory
        → Receive Supplier Invoice
          → Match to PO and Receipt
            → Post to AP
              → Close PO when complete

Three-Way Match:

    Purchase Order quantities/prices
    Goods Receipt quantities
    Supplier Invoice quantities/amounts
    Variances flagged for review

7. PAYROLL - Data Structures
Key Tables:

    Employee Master: Personal details, pay rates, tax file numbers, super funds
    Pay Categories: Wages, overtime, allowances, deductions, employer costs
    Timesheets/Pay Runs: Hours worked, leave taken
    Payroll Transactions: Individual pay line items
    Year-to-Date Tables: Cumulative earnings, tax, super for reporting

Process Flow:

Enter Timesheet/Salary data
  → Calculate Gross Pay
    → Calculate PAYG Tax
      → Calculate Superannuation
        → Calculate Net Pay
          → Generate Payment file
            → Post to GL (Wages Expense, Tax Payable, Super Payable, Bank)
              → Update YTD totals

8. BANKING & RECONCILIATION
Data Structures:

    Bank Accounts: GL account links, current balances
    Bank Transactions: Deposits, withdrawals, transfers
    Reconciliation Table: Statement date, cleared transactions, adjustments

Reconciliation Flow:

Import/Enter Bank Statement
  → Match transactions to existing entries
    → Identify unmatched items
      → Create adjustment entries as needed
        → Mark transactions as reconciled
          → Balance to statement
            → Close reconciliation period

9. INTEGRATION ARCHITECTURE
Internal Integration:

All modules connect through:

    GL Posting Engine: Standardized journal entry creation
    Account Code Validation: Ensures all transactions use valid GL accounts
    Period Control: Prevents posting to closed periods
    Transaction Audit Trail: Links subsidiary transactions to GL postings

External Integration Points:

Data Import/Export:

    General Journal entries (CSV/text)
    Customer/Supplier lists
    Inventory items
    Bank statements
    Payroll timesheets

Common Export Formats:

    Financial statements (Excel/PDF)
    Aged receivables/payables
    Tax reports (BAS/GST)
    Payroll summaries for super/tax authorities

API/File-based Integration:

    MYOB 12 primarily uses file-based imports/exports
    Common integration: Excel → MYOB via import wizards
    Bank feeds: Import OFX/QIF files
    Payroll: Export to super clearing houses

10. CRITICAL DESIGN PATTERNS
Double-Entry Enforcement:

Every transaction creates balanced debits and credits automatically
Master-Detail Relationships:

    Invoices have headers and multiple line items
    Orders link to multiple deliveries/invoices
    Payments apply to multiple invoices

Status Tracking:

    Invoices: Open/Partial/Paid/Written Off
    Orders: Open/Partial/Complete/Invoiced
    Periods: Open/Closed/Locked

Audit Trail:

    Transaction numbers are sequential and immutable
    Deletions typically "void" rather than remove
    User stamps on creation/modification

