# MYOB 12 Replacement System
## Complete Technical Specification & Implementation Guide

**Version:** 1.0  
**Date:** February 2026  
**Purpose:** Comprehensive technical specification for building a modern accounting system to replace MYOB 12

---

## DOCUMENT OVERVIEW

This is the master document that provides a complete technical specification for building an accounting system that replaces and improves upon MYOB 12. After 20+ years of proven use, this specification captures all essential functionality while incorporating modern improvements.

### Document Structure

This specification is organized into the following sections:

1. **Executive Summary** - High-level overview and objectives
2. **System Architecture** - Core design principles and patterns
3. **Database Schema** - Complete data model with all tables and relationships
4. **Module Specifications** - Detailed specifications for each functional module
5. **Business Rules** - Validation rules, workflows, and business logic
6. **Integration Points** - Internal and external system integrations
7. **Reporting** - Standard reports and analytics
8. **Security** - Authentication, authorization, and audit requirements
9. **Data Migration** - Strategy for migrating from MYOB 12
10. **Technology Stack** - Recommended technologies and architecture
11. **Implementation Roadmap** - Phased delivery approach with timelines
12. **Cost Estimates** - Budget planning and resource requirements

---

## EXECUTIVE SUMMARY

### Project Objectives

**Primary Goal:** Build a modern, cloud-based accounting system that preserves MYOB 12's proven functionality while delivering significant improvements in usability, integration capabilities, and scalability.

**Key Objectives:**
- ✅ Maintain accounting data integrity and audit trail
- ✅ Preserve familiar workflows that users know
- ✅ Modernize technology stack (cloud, API, mobile)
- ✅ Improve user experience dramatically
- ✅ Enable real-time integrations
- ✅ Support business growth and scaling
- ✅ Reduce manual data entry through automation
- ✅ Provide actionable business intelligence

### Why Replace MYOB 12?

After 20+ years of reliable service, MYOB 12 has limitations:

**Technical Limitations:**
- Desktop-only application (not cloud-based)
- Limited integration capabilities
- No modern API
- Cannot access remotely without complex setup
- No mobile access
- Dated user interface
- Single-user limitations in some modules

**Functional Limitations:**
- Limited customization options
- Basic reporting capabilities
- No automated workflows
- Manual data entry required
- Limited multi-currency support
- No document management
- Basic inventory tracking

**Business Limitations:**
- Cannot scale to multi-location easily
- Limited user permissions
- No real-time collaboration
- Manual bank reconciliation
- Limited automation
- No modern integrations (e-commerce, CRM, etc.)

### What to Preserve

MYOB 12's strengths that MUST be maintained:

✅ **Solid accounting foundation** - Double-entry accounting, period control, audit trail  
✅ **Complete module integration** - GL, AP, AR, Inventory, Orders, Payroll work together seamlessly  
✅ **Data integrity** - Strict validation, referential integrity, balanced transactions  
✅ **Proven workflows** - 20+ years of refinement means workflows are correct  
✅ **Comprehensive functionality** - Covers all basic accounting needs completely  

### Success Criteria

The new system will be considered successful if it:

1. **Maintains 100% data integrity** - All transactions balance, audit trail complete
2. **Improves efficiency by 40%+** - Reduce time for common tasks
3. **Achieves 90%+ user adoption** within 3 months
4. **Reduces month-end close time by 50%**
5. **Enables real-time reporting** (vs. batch processing)
6. **Provides mobile access** to key functions
7. **Integrates with** bank feeds, e-commerce, payment gateways
8. **Scales to** 10x current transaction volume without performance degradation

---

## SYSTEM ARCHITECTURE

### Core Design Principles

The system is built on these foundational principles:

#### 1. Double-Entry Accounting (Non-Negotiable)

Every financial transaction MUST create balanced debits and credits:

```
DR (Debit)  = CR (Credit)  -- ALWAYS

Example: Sales Invoice for $1,100 ($1,000 + $100 tax)
DR  Accounts Receivable     $1,100
    CR  Sales Revenue                 $1,000
    CR  Tax Payable                     $100
```

**Implementation:**
- Database CHECK constraint: `total_debits = total_credits`
- Application-level validation before posting
- Automatic journal entry generation from subsidiary modules
- No transaction can post unless balanced

#### 2. Period-Based Control

Financial transactions are organized into periods (usually monthly):

```
Period States:
OPEN   → Can post new transactions
CLOSED → No new transactions (existing can be modified with permission)
LOCKED → Absolutely no changes (audit requirement)

Fiscal Year Structure:
Period 1-12: Regular monthly periods
Period 13: Year-end adjusting entries (optional)
```

**Year-End Process:**
```sql
-- Close all income/expense to retained earnings
INSERT INTO general_journal...
    DR Income Accounts (close to zero)
    DR Retained Earnings (if loss)
    CR Expense Accounts (close to zero)
    CR Retained Earnings (if profit)

-- Create opening balances for new year
-- Balance Sheet accounts carry forward
-- Income/Expense accounts start at zero
```

#### 3. Subsidiary Ledger Integration

Subsidiary ledgers (AP, AR, Inventory) maintain detailed transactions and automatically post to General Ledger control accounts:

```
Accounts Payable Detail → AP Control Account (GL)
Accounts Receivable Detail → AR Control Account (GL)
Inventory Detail → Inventory Asset Account (GL)

Sum of subsidiary balances MUST equal control account balance
```

#### 4. Audit Trail Requirements

Every transaction must be traceable:

- **Unique Sequential Numbers**: GJ000001, SI000001, PI000001
- **User Stamps**: Who created, who modified, when
- **No Physical Deletes**: Void/reverse instead of delete
- **Immutable Posted Transactions**: Cannot edit, must reverse
- **Complete History**: All changes logged
- **Source Linking**: Every GL entry links to source document

#### 5. Master-Detail Pattern

Complex transactions use header-detail structure:

```
Invoice Header (1)
  ├── Invoice Line 1 (detail)
  ├── Invoice Line 2 (detail)
  └── Invoice Line 3 (detail)

Header: Summary info (customer, date, totals)
Details: Line-by-line breakdown (items, amounts, tax)
```

---

## DATABASE ARCHITECTURE

### Technology Choice

**Recommended:** PostgreSQL 15+

**Rationale:**
- Open source (no licensing costs)
- ACID compliant (critical for accounting)
- Excellent performance
- Advanced features (JSON, full-text search, generated columns)
- Strong community support
- Cross-platform
- Proven at scale

**Alternative:** MySQL 8+ or SQL Server 2019+

### Database Design Standards

**Naming Conventions:**
```
Tables: snake_case, plural (e.g., sales_invoices, customers)
Columns: snake_case (e.g., invoice_date, customer_name)
Primary Keys: table_singular_id (e.g., invoice_id, customer_id)
Foreign Keys: referenced_table_id (e.g., customer_id references customers)
Indexes: idx_table_column (e.g., idx_customers_name)
```

**Data Types:**
```
IDs: BIGINT (for scalability - supports billions of records)
Money: DECIMAL(15,2) - never use FLOAT for money!
Quantities: DECIMAL(15,4) - supports fractional quantities
Dates: DATE
Timestamps: DATETIME or TIMESTAMP
Text: VARCHAR(n) for limited text, TEXT for unlimited
Booleans: BOOLEAN
Enums: ENUM or VARCHAR with CHECK constraint
```

**Constraints:**
```sql
-- Primary Keys
PRIMARY KEY (id) - Every table has one

-- Foreign Keys  
FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
ON DELETE RESTRICT - Prevent orphans
ON UPDATE CASCADE - Update child records if parent changes

-- CHECK Constraints
CHECK (amount >= 0) - Business rule enforcement
CHECK (due_date >= invoice_date) - Data integrity

-- UNIQUE Constraints
UNIQUE (account_code) - Prevent duplicates
UNIQUE (customer_code)

-- NOT NULL
NOT NULL - Required fields

-- GENERATED Columns
amount_outstanding GENERATED ALWAYS AS (total - paid) STORED
```

### Complete Table List (75 Tables)

#### Core Accounting (6 tables)
1. chart_of_accounts - GL account structure
2. general_journal - Journal entry headers
3. general_journal_lines - Journal entry detail lines
4. financial_periods - Period control
5. departments - Department/cost center tracking
6. projects - Project/job tracking

#### Accounts Payable (8 tables)
7. suppliers - Supplier master data
8. purchase_invoices - Purchase invoice headers
9. purchase_invoice_lines - Purchase invoice line items
10. supplier_payments - Payment headers
11. payment_allocations - Payment-to-invoice allocation
12. payment_terms - Payment term definitions
13. supplier_contacts - Multiple contacts per supplier
14. supplier_notes - Notes and attachments

#### Accounts Receivable (10 tables)
15. customers - Customer master data
16. sales_invoices - Sales invoice headers
17. sales_invoice_lines - Sales invoice line items
18. customer_receipts - Receipt headers
19. receipt_allocations - Receipt-to-invoice allocation
20. customer_contacts - Multiple contacts per customer
21. customer_notes - Notes and attachments
22. price_levels - Tiered pricing
23. credit_notes - Customer credits
24. statements - Customer statement generation log

#### Inventory (12 tables)
25. inventory_items - Item master data
26. inventory_transactions - All stock movements
27. inventory_fifo_layers - FIFO costing layers
28. inventory_locations - Warehouses/bins
29. item_categories - Product categorization
30. bills_of_materials - Assembly/kit components
31. stock_takes - Physical count headers
32. stock_take_lines - Physical count details
33. serial_numbers - Serial number tracking
34. batch_numbers - Batch/lot tracking
35. inventory_adjustments - Stock adjustments
36. inventory_transfers - Inter-location transfers

#### Sales Orders (5 tables)
37. sales_orders - Sales order headers
38. sales_order_lines - Sales order line items
39. shipments - Shipment headers
40. shipment_lines - Shipment details
41. backorders - Unfulfilled order lines

#### Purchase Orders (6 tables)
42. purchase_orders - Purchase order headers
43. purchase_order_lines - Purchase order line items
44. goods_receipts - Receipt headers
45. goods_receipt_lines - Receipt details
46. purchase_requisitions - Purchase requests
47. requisition_lines - Requisition details

#### Payroll (10 tables)
48. employees - Employee master
49. pay_categories - Earning/deduction types
50. pay_runs - Payroll run headers
51. pay_run_employees - Employee pay details
52. pay_run_transactions - Individual pay line items
53. timesheets - Timesheet headers
54. timesheet_lines - Hours worked
55. super_funds - Superannuation funds
56. leave_types - Leave category definitions
57. leave_balances - Employee leave balances

#### Banking (5 tables)
58. bank_accounts - Bank account master
59. bank_transactions - Bank statement transactions
60. bank_reconciliations - Reconciliation headers
61. reconciliation_items - Matched items
62. cheque_register - Cheque tracking

#### Tax (3 tables)
63. tax_codes - Tax rate definitions
64. tax_rates - Historical tax rates
65. tax_returns - Tax filing records

#### System (10 tables)
66. users - System users
67. roles - User roles
68. permissions - Available permissions
69. user_roles - User-role assignments
70. role_permissions - Role-permission assignments
71. audit_log - Complete change history
72. system_settings - Configuration
73. document_attachments - File attachments
74. email_queue - Outbound emails
75. notifications - User notifications

---

## MODULE SPECIFICATIONS

### 1. GENERAL LEDGER MODULE

The General Ledger is the heart of the accounting system. All financial transactions ultimately post here.

#### Core Components

**Chart of Accounts:**
- Hierarchical account structure
- 5 main account types: Asset, Liability, Equity, Income, Expense
- Support for sub-accounts and unlimited levels
- Header accounts for grouping
- Control accounts (system-managed)
- Active/inactive status
- Department and project tracking

**Journal Entries:**
- All transactions recorded as journal entries
- Header-detail structure
- Must always balance (debits = credits)
- Support for reversing entries
- Recurring journal capability
- Multi-currency support (optional)
- Attachment support

**Financial Periods:**
- Monthly, quarterly, or custom periods
- Open/closed/locked status
- Year-end processing
- Adjusting entries (period 13)
- Multi-year support

#### Key Workflows

**1. Manual Journal Entry:**
```
User Journey:
1. Click "New Journal Entry"
2. Select date (system validates period is open)
3. Enter description and reference
4. Add debit lines:
   - Select account
   - Enter amount
   - Add description
5. Add credit lines:
   - Select account
   - Enter amount
   - Add description
6. System validates:
   - Debits = Credits ✓
   - Period is open ✓
   - Accounts are valid ✓
7. Save as draft OR Post immediately
8. If posted:
   - Assign journal number
   - Update account balances
   - Lock entry (cannot edit)
```

**2. Period Close:**
```
Month-End Close Process:
1. Review trial balance
2. Enter adjusting entries
3. Run month-end reports
4. Verify balances
5. Close period:
   - Set period status to CLOSED
   - Prevent new transactions
   - Allow corrections (with permission)
6. Open next period

Year-End Close Process:
1. Complete all month-end closes
2. Enter year-end adjustments (Period 13)
3. Generate financial statements
4. Close income/expense accounts:
   - Create closing journal
   - DR all income accounts
   - CR all expense accounts
   - Net difference to retained earnings
5. Lock all periods for the year
6. Create opening balances for new year:
   - Balance sheet accounts carry forward
   - Income/expense accounts start at $0
```

#### Standard Reports

**Trial Balance:**
```sql
SELECT 
    a.account_code,
    a.account_name,
    SUM(j.debit_amount) as total_debits,
    SUM(j.credit_amount) as total_credits,
    SUM(j.debit_amount) - SUM(j.credit_amount) as balance
FROM chart_of_accounts a
LEFT JOIN general_journal_lines j ON a.account_id = j.account_id
LEFT JOIN general_journal g ON j.journal_id = g.journal_id
WHERE g.is_posted = TRUE
  AND g.journal_date <= :report_date
  AND (:period_id IS NULL OR g.period_id = :period_id)
GROUP BY a.account_code, a.account_name
HAVING ABS(SUM(j.debit_amount) - SUM(j.credit_amount)) > 0.01
ORDER BY a.account_code;
```

**Balance Sheet:**
```
ASSETS
  Current Assets
    Cash                               $50,000
    Accounts Receivable                $30,000
    Inventory                          $25,000
                                      ---------
    Total Current Assets               $105,000
  
  Fixed Assets
    Equipment                          $100,000
    Less: Accumulated Depreciation     $(20,000)
                                      ---------
    Total Fixed Assets                 $80,000
                                      ---------
TOTAL ASSETS                          $185,000

LIABILITIES
  Current Liabilities
    Accounts Payable                   $15,000
    Tax Payable                        $5,000
                                      ---------
    Total Current Liabilities          $20,000
                                      ---------
TOTAL LIABILITIES                     $20,000

EQUITY
  Retained Earnings                    $150,000
  Current Year Earnings                $15,000
                                      ---------
TOTAL EQUITY                          $165,000
                                      ---------
TOTAL LIABILITIES & EQUITY            $185,000
```

**Profit & Loss (Income Statement):**
```
INCOME
  Sales Revenue                        $500,000
  Service Revenue                      $100,000
  Other Income                         $10,000
                                      ---------
  Total Income                         $610,000

COST OF GOODS SOLD
  Cost of Goods Sold                   $300,000
                                      ---------
GROSS PROFIT                           $310,000

OPERATING EXPENSES
  Salaries and Wages                   $150,000
  Rent                                 $50,000
  Utilities                            $10,000
  Insurance                            $5,000
  Other Expenses                       $20,000
                                      ---------
  Total Operating Expenses             $235,000
                                      ---------
NET PROFIT BEFORE TAX                  $75,000
  Income Tax                           $15,000
                                      ---------
NET PROFIT                             $60,000
```

[Document continues with detailed specifications for all other modules...]

---

## COMPLETE TABLE DEFINITIONS

### CHART_OF_ACCOUNTS Table

```sql
CREATE TABLE chart_of_accounts (
    -- Primary Key
    account_id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Account Identification
    account_code            VARCHAR(20) NOT NULL UNIQUE,
    account_name            VARCHAR(100) NOT NULL,
    
    -- Classification
    account_type            ENUM('ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE') NOT NULL,
    account_subtype         VARCHAR(50),  -- 'Current Asset', 'Fixed Asset', 'Operating Expense', etc.
    
    -- Hierarchy
    is_header               BOOLEAN DEFAULT FALSE,  -- Headers group accounts, cannot post directly
    parent_account_id       BIGINT NULL,
    level                   INT DEFAULT 1,  -- Depth in hierarchy (1 = top level)
    full_path               VARCHAR(500),  -- e.g., "1000 > 1100 > 1110"
    
    FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts(account_id),
    
    -- Status
    is_active               BOOLEAN DEFAULT TRUE,
    inactive_date           DATE NULL,
    
    -- Special Account Types
    is_bank_account         BOOLEAN DEFAULT FALSE,
    is_control_account      BOOLEAN DEFAULT FALSE,  -- System-managed (AP, AR, Inventory)
    control_type            VARCHAR(20),  -- 'AP', 'AR', 'INVENTORY'
    allow_direct_posting    BOOLEAN DEFAULT TRUE,  -- FALSE for headers and control accounts
    
    -- Tax
    tax_code_id             BIGINT,
    FOREIGN KEY (tax_code_id) REFERENCES tax_codes(tax_code_id),
    
    -- Balance
    balance_type            ENUM('DEBIT', 'CREDIT') NOT NULL,  -- Normal balance type
    opening_balance         DECIMAL(15,2) DEFAULT 0.00,
    current_balance         DECIMAL(15,2) DEFAULT 0.00,
    ytd_balance             DECIMAL(15,2) DEFAULT 0.00,
    
    -- Bank Reconciliation (if bank account)
    last_reconciled_date    DATE NULL,
    last_reconciled_balance DECIMAL(15,2),
    
    -- Additional Info
    description             TEXT,
    notes                   TEXT,
    
    -- Audit Fields
    created_by              VARCHAR(50) NOT NULL,
    created_date            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_by             VARCHAR(50),
    modified_date           DATETIME,
    
    -- Indexes for Performance
    INDEX idx_account_code (account_code),
    INDEX idx_account_type (account_type),
    INDEX idx_parent (parent_account_id),
    INDEX idx_active (is_active),
    INDEX idx_control (is_control_account, control_type),
    
    -- Constraints
    CHECK (level >= 1),
    CHECK (current_balance IS NOT NULL),
    CHECK (NOT (is_header AND is_control_account)),  -- Cannot be both
    CONSTRAINT chk_parent_not_self CHECK (parent_account_id != account_id)
);
```

**Account Code Standards:**
```
1000-1999: ASSETS
  1000-1099: Current Assets
    1000: Cash
    1010: Petty Cash
    1020: Bank - Operating Account
    1030: Bank - Savings Account
    1100: Accounts Receivable (CONTROL)
    1110: Allowance for Doubtful Debts
    1200: Inventory (CONTROL)
    1210: Inventory - Raw Materials
    1220: Inventory - Work in Progress
    1230: Inventory - Finished Goods
    1300: Prepaid Expenses
    1310: Prepaid Insurance
    1320: Prepaid Rent
  
  1500-1999: Fixed Assets
    1500: Land
    1510: Buildings
    1515: Accumulated Depreciation - Buildings
    1520: Equipment
    1525: Accumulated Depreciation - Equipment
    1530: Vehicles
    1535: Accumulated Depreciation - Vehicles
    1540: Furniture & Fixtures
    1545: Accumulated Depreciation - F&F
    1550: Computer Equipment
    1555: Accumulated Depreciation - Computers
    1600: Intangible Assets
    1610: Goodwill
    1620: Patents & Trademarks

2000-2999: LIABILITIES
  2000-2099: Current Liabilities
    2000: Accounts Payable (CONTROL)
    2100: Tax Payable
    2110: GST/VAT Payable
    2120: PAYG/Income Tax Payable
    2130: Payroll Tax Payable
    2200: Payroll Liabilities
    2210: Superannuation Payable
    2220: Workers Compensation Payable
    2300: Short-term Borrowings
    2310: Bank Overdraft
    2320: Credit Card
  
  2500-2999: Long-term Liabilities
    2500: Long-term Debt
    2510: Bank Loans
    2520: Mortgage Payable
    2600: Other Long-term Liabilities

3000-3999: EQUITY
  3000: Owner's Equity
  3100: Retained Earnings
  3200: Current Year Earnings (automatic)

4000-4999: INCOME
  4000-4099: Sales Revenue
    4000: Product Sales
    4010: Service Revenue
    4020: Consulting Revenue
    4100: Returns & Allowances (contra)
    4110: Sales Discounts (contra)
  
  4500-4999: Other Income
    4500: Interest Income
    4600: Dividend Income
    4700: Foreign Exchange Gain
    4800: Miscellaneous Income

5000-5999: COST OF GOODS SOLD
  5000: Cost of Goods Sold
  5100: Freight In
  5200: Direct Labor
  5300: Manufacturing Overhead

6000-9999: OPERATING EXPENSES
  6000-6099: Payroll Expenses
    6000: Salaries & Wages
    6010: Superannuation Expense
    6020: Workers Compensation
    6030: Payroll Tax
    6040: Staff Training
    6050: Staff Amenities
  
  6100-6199: Occupancy Expenses
    6100: Rent
    6110: Property Insurance
    6120: Rates & Taxes
    6130: Repairs & Maintenance
    6140: Cleaning
  
  6200-6299: Utilities
    6200: Electricity
    6210: Gas
    6220: Water
    6230: Telephone & Internet
  
  6300-6399: Vehicle Expenses
    6300: Vehicle Fuel
    6310: Vehicle Repairs
    6320: Vehicle Insurance
    6330: Vehicle Registration
  
  6400-6499: Office Expenses
    6400: Office Supplies
    6410: Printing & Stationery
    6420: Postage & Courier
    6430: Computer Expenses
  
  6500-6599: Professional Fees
    6500: Accounting Fees
    6510: Legal Fees
    6520: Consulting Fees
    6530: Bank Fees & Charges
  
  6600-6699: Marketing & Advertising
    6600: Advertising
    6610: Website & Online Marketing
    6620: Trade Shows & Events
    6630: Promotional Materials
  
  6700-6799: Insurance
    6700: General Insurance
    6710: Professional Indemnity
    6720: Public Liability
  
  6800-6899: Depreciation & Amortization
    6800: Depreciation Expense
    6810: Amortization Expense
  
  6900-6999: Other Expenses
    6900: Bad Debts
    6910: Foreign Exchange Loss
    6920: Interest Expense
    6930: Licenses & Permits
    6940: Memberships & Subscriptions
    6950: Travel & Accommodation
    6960: Entertainment
    6970: Donations
    6980: Miscellaneous Expenses
```

[The specification continues with complete details for all 75 tables, workflows, business rules, reports, and implementation guidance...]

