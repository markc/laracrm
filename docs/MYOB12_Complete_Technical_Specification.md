# MYOB 12 Complete Technical Specification
## Comprehensive Guide for Building a Replacement System

**Document Version**: 1.0  
**Last Updated**: February 2026  
**Purpose**: Complete technical specification for building MYOB 12 replacement system

---

## TABLE OF CONTENTS

1. [Executive Summary](#executive-summary)
2. [System Architecture Overview](#system-architecture-overview)
3. [Database Schema - Complete ERD](#database-schema)
4. [Module Deep Dives](#module-deep-dives)
   - General Ledger
   - Accounts Payable
   - Accounts Receivable
   - Inventory Management
   - Sales Orders
   - Purchase Orders
   - Payroll
   - Banking & Reconciliation
5. [Business Rules & Validation](#business-rules)
6. [Reporting Engine](#reporting-engine)
7. [Security & Access Control](#security)
8. [Data Migration Strategy](#data-migration)
9. [Modern Improvements](#modern-improvements)
10. [Technology Recommendations](#technology-recommendations)
11. [Implementation Roadmap](#implementation-roadmap)
12. [Cost Estimates](#cost-estimates)

---

## EXECUTIVE SUMMARY

This document provides a complete technical specification for building a modern accounting system to replace MYOB 12. After 20+ years of proven use, this specification captures all essential functionality while recommending modern improvements.

**Key Objectives:**
- Preserve proven accounting workflows and data integrity
- Modernize technology stack (cloud-based, API-first)
- Enhance user experience with intuitive interfaces
- Enable scalability and growth
- Provide robust integration capabilities
- Ensure compliance and security

---

## SYSTEM ARCHITECTURE OVERVIEW

### Core Design Principles

**1. Double-Entry Accounting Foundation**
- Every transaction MUST create balanced debits and credits
- System prevents imbalanced entries at database constraint level
- All subsidiary ledgers reconcile to General Ledger
- Audit trail for all transactions

**2. Period-Based Control**
- Financial periods control posting authorization
- Closed periods locked from further posting
- Year-end process creates new fiscal year structure
- Historical periods remain immutable

**3. Master-Detail Pattern**
- Headers contain summary/common information
- Detail lines contain itemized transactional data
- Referential integrity constraints prevent orphaned records
- Cascading updates maintain consistency

**4. Audit Trail Requirements**
- Unique sequential transaction numbers
- User stamps (created by, modified by, timestamps)
- Deletion = void/reverse, not physical delete
- Complete modification history

**5. Data Integrity**
- Referential integrity strictly enforced
- Cascading updates where appropriate
- No orphaned transactions
- Multi-level balance validation

---

## DATABASE SCHEMA

Below is the complete database schema with all tables, relationships, and constraints.

### CORE ACCOUNTING TABLES

#### 1. CHART_OF_ACCOUNTS

The chart of accounts is the foundation of the accounting system.

```sql
CREATE TABLE chart_of_accounts (
    account_id              INT PRIMARY KEY AUTO_INCREMENT,
    account_code            VARCHAR(20) NOT NULL UNIQUE,
    account_name            VARCHAR(100) NOT NULL,
    account_type            ENUM('ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE') NOT NULL,
    account_subtype         VARCHAR(50), -- 'Current Asset', 'Fixed Asset', 'Inventory', etc.
    is_header               BOOLEAN DEFAULT FALSE,  -- Cannot post directly to headers
    parent_account_id       INT NULL REFERENCES chart_of_accounts(account_id),
    level                   INT DEFAULT 1,  -- Account hierarchy level
    is_active               BOOLEAN DEFAULT TRUE,
    is_bank_account         BOOLEAN DEFAULT FALSE,
    is_control_account      BOOLEAN DEFAULT FALSE,  -- AP Control, AR Control, Inventory, etc.
    control_type            VARCHAR(20),  -- 'AP', 'AR', 'INVENTORY'
    tax_code_id             INT REFERENCES tax_codes(tax_code_id),
    allow_direct_posting    BOOLEAN DEFAULT TRUE,
    balance_type            ENUM('DEBIT', 'CREDIT') NOT NULL,  -- Normal balance
    opening_balance         DECIMAL(15,2) DEFAULT 0.00,
    current_balance         DECIMAL(15,2) DEFAULT 0.00,
    ytd_balance             DECIMAL(15,2) DEFAULT 0.00,
    last_reconciled_date    DATE NULL,
    notes                   TEXT,
    created_by              VARCHAR(50),
    created_date            DATETIME,
    modified_by             VARCHAR(50),
    modified_date           DATETIME,
    
    INDEX idx_account_code (account_code),
    INDEX idx_account_type (account_type),
    INDEX idx_parent (parent_account_id),
    INDEX idx_active (is_active)
);
```

**Key Concepts:**
- **Header Accounts**: Used for grouping (e.g., "1000 - Assets"). Cannot receive direct postings.
- **Control Accounts**: System-managed accounts that auto-post from subsidiary ledgers (AP/AR/Inventory).
- **Balance Type**: Determines the account's normal balance (Assets/Expenses = Debit, Liabilities/Income = Credit).
- **Account Hierarchy**: Supports multi-level chart of accounts with parent-child relationships.

**Standard Account Structure:**
```
1000-1999: Assets
  1000-1099: Current Assets
    1010: Cash and Cash Equivalents
    1020: Accounts Receivable (Control)
    1030: Inventory (Control)
  1100-1999: Fixed Assets
    1100: Property, Plant & Equipment
    1150: Accumulated Depreciation

2000-2999: Liabilities
  2000-2099: Current Liabilities
    2010: Accounts Payable (Control)
    2020: Tax Payable
    2030: Payroll Liabilities
  2100-2999: Long-term Liabilities
    2100: Long-term Debt

3000-3999: Equity
  3000: Retained Earnings
  3010: Current Year Earnings

4000-4999: Income
  4000: Sales Revenue
  4100: Service Revenue
  4900: Other Income

5000-5999: Cost of Goods Sold
  5000: Cost of Goods Sold

6000-9999: Operating Expenses
  6000: Salaries and Wages
  6100: Rent Expense
  6200: Utilities
  ...
```

---

#### 2. GENERAL_JOURNAL

The general journal is the central repository for all accounting entries.

```sql
CREATE TABLE general_journal (
    journal_id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    journal_number          VARCHAR(20) NOT NULL UNIQUE,  -- Sequential: GJ00001, GJ00002
    journal_date            DATE NOT NULL,
    period_id               INT NOT NULL REFERENCES financial_periods(period_id),
    source_module           VARCHAR(20) NOT NULL,  -- 'GL', 'AP', 'AR', 'INV', 'PAY'
    source_document_id      BIGINT,  -- Links back to source transaction
    source_document_number  VARCHAR(50),
    description             VARCHAR(255),
    reference               VARCHAR(50),
    is_posted               BOOLEAN DEFAULT FALSE,
    posted_date             DATETIME,
    posted_by               VARCHAR(50),
    is_reversed             BOOLEAN DEFAULT FALSE,
    reversed_by_journal_id  BIGINT REFERENCES general_journal(journal_id),
    reversal_date           DATE,
    total_debits            DECIMAL(15,2) NOT NULL,
    total_credits           DECIMAL(15,2) NOT NULL,
    is_balanced             BOOLEAN GENERATED ALWAYS AS (total_debits = total_credits) STORED,
    created_by              VARCHAR(50),
    created_date            DATETIME,
    modified_by             VARCHAR(50),
    modified_date           DATETIME,
    
    INDEX idx_journal_number (journal_number),
    INDEX idx_journal_date (journal_date),
    INDEX idx_period (period_id),
    INDEX idx_source (source_module, source_document_id),
    INDEX idx_posted (is_posted),
    
    CHECK (total_debits = total_credits)  -- CRITICAL: Enforce balance
);
```

**Journal Entry States:**
1. **DRAFT**: Created but not posted, can be edited
2. **POSTED**: Posted to ledger, cannot be edited
3. **REVERSED**: Has been reversed by another journal entry

---

#### 3. GENERAL_JOURNAL_LINES

Each journal entry consists of multiple lines (debits and credits).

```sql
CREATE TABLE general_journal_lines (
    line_id                 BIGINT PRIMARY KEY AUTO_INCREMENT,
    journal_id              BIGINT NOT NULL REFERENCES general_journal(journal_id) ON DELETE CASCADE,
    line_number             INT NOT NULL,
    account_id              INT NOT NULL REFERENCES chart_of_accounts(account_id),
    debit_amount            DECIMAL(15,2) DEFAULT 0.00,
    credit_amount           DECIMAL(15,2) DEFAULT 0.00,
    description             VARCHAR(255),
    department_id           INT REFERENCES departments(department_id),
    project_id              INT REFERENCES projects(project_id),
    tax_code_id             INT REFERENCES tax_codes(tax_code_id),
    tax_amount              DECIMAL(15,2) DEFAULT 0.00,
    quantity                DECIMAL(15,4),  -- For inventory items
    unit_price              DECIMAL(15,4),
    
    UNIQUE (journal_id, line_number),
    INDEX idx_account (account_id),
    INDEX idx_journal (journal_id),
    
    CHECK (debit_amount >= 0 AND credit_amount >= 0),
    CHECK (NOT (debit_amount > 0 AND credit_amount > 0))  -- Can't be both debit AND credit
);
```

**Business Logic:**
- Journal must balance (SUM(debits) = SUM(credits))
- Each line has EITHER debit OR credit, never both
- Control accounts only accept postings from source modules
- Cannot post to closed periods
- Cannot post to header accounts

---

#### 4. FINANCIAL_PERIODS

Financial periods control when transactions can be posted.

```sql
CREATE TABLE financial_periods (
    period_id               INT PRIMARY KEY AUTO_INCREMENT,
    fiscal_year             INT NOT NULL,
    period_number           INT NOT NULL,  -- 1-12 or 1-13 for adjusting period
    period_name             VARCHAR(50) NOT NULL,  -- 'January 2024', 'Period 13 2024'
    start_date              DATE NOT NULL,
    end_date                DATE NOT NULL,
    is_open                 BOOLEAN DEFAULT TRUE,
    is_locked               BOOLEAN DEFAULT FALSE,  -- Locked = absolutely no changes
    is_year_end_period      BOOLEAN DEFAULT FALSE,
    closed_date             DATETIME,
    closed_by               VARCHAR(50),
    locked_date             DATETIME,
    locked_by               VARCHAR(50),
    
    UNIQUE (fiscal_year, period_number),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_status (is_open, is_locked)
);
```

**Period Status:**
- **OPEN**: Transactions can be posted
- **CLOSED**: No new transactions, existing can be modified (with permission)
- **LOCKED**: Absolutely no changes allowed

**Year-End Process:**
1. Close all periods for the fiscal year
2. Create closing entries:
   - Close all income accounts to retained earnings
   - Close all expense accounts to retained earnings
   - Transfer current year earnings to retained earnings
3. Create opening balances for new fiscal year
4. Lock previous fiscal year

---

## MODULE DEEP DIVES

### ACCOUNTS PAYABLE MODULE

Accounts Payable tracks money owed to suppliers.

#### SUPPLIERS Table

```sql
CREATE TABLE suppliers (
    supplier_id             INT PRIMARY KEY AUTO_INCREMENT,
    supplier_code           VARCHAR(20) NOT NULL UNIQUE,
    supplier_name           VARCHAR(100) NOT NULL,
    trading_name            VARCHAR(100),
    abn                     VARCHAR(20),  -- Australian Business Number
    contact_person          VARCHAR(100),
    phone                   VARCHAR(20),
    fax                     VARCHAR(20),
    email                   VARCHAR(100),
    website                 VARCHAR(100),
    
    -- Address
    address_line1           VARCHAR(100),
    address_line2           VARCHAR(100),
    city                    VARCHAR(50),
    state                   VARCHAR(50),
    postcode                VARCHAR(10),
    country                 VARCHAR(50) DEFAULT 'Australia',
    
    -- Payment Terms
    payment_terms_id        INT REFERENCES payment_terms(terms_id),
    default_payment_method  VARCHAR(20),  -- 'CHEQUE', 'EFT', 'BPAY'
    
    -- Banking Details (for EFT payments)
    bank_name               VARCHAR(100),
    bank_bsb                VARCHAR(10),
    bank_account_number     VARCHAR(20),
    bank_account_name       VARCHAR(100),
    
    -- GL Integration
    default_expense_account_id  INT REFERENCES chart_of_accounts(account_id),
    ap_control_account_id       INT REFERENCES chart_of_accounts(account_id),
    
    -- Tax
    tax_code_id             INT REFERENCES tax_codes(tax_code_id),
    
    -- Status & Limits
    is_active               BOOLEAN DEFAULT TRUE,
    credit_limit            DECIMAL(15,2) DEFAULT 0.00,
    current_balance         DECIMAL(15,2) DEFAULT 0.00,
    ytd_purchases           DECIMAL(15,2) DEFAULT 0.00,
    
    notes                   TEXT,
    created_by              VARCHAR(50),
    created_date            DATETIME,
    modified_by             VARCHAR(50),
    modified_date           DATETIME,
    
    INDEX idx_supplier_code (supplier_code),
    INDEX idx_supplier_name (supplier_name),
    INDEX idx_active (is_active)
);
```

#### PURCHASE_INVOICES Table

```sql
CREATE TABLE purchase_invoices (
    invoice_id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_number          VARCHAR(50) NOT NULL,  -- Our internal number
    supplier_invoice_number VARCHAR(50),  -- Supplier's invoice number
    supplier_id             INT NOT NULL REFERENCES suppliers(supplier_id),
    invoice_date            DATE NOT NULL,
    due_date                DATE NOT NULL,
    period_id               INT NOT NULL REFERENCES financial_periods(period_id),
    
    -- Amounts
    subtotal                DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount              DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    total_amount            DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    amount_paid             DECIMAL(15,2) DEFAULT 0.00,
    amount_outstanding      DECIMAL(15,2) GENERATED ALWAYS AS (total_amount - amount_paid) STORED,
    
    -- Status
    status                  ENUM('DRAFT', 'POSTED', 'PARTIAL', 'PAID', 'VOID') DEFAULT 'DRAFT',
    is_posted               BOOLEAN DEFAULT FALSE,
    posted_date             DATETIME,
    posted_by               VARCHAR(50),
    
    -- GL Integration
    journal_id              BIGINT REFERENCES general_journal(journal_id),
    
    -- PO Link
    purchase_order_id       BIGINT REFERENCES purchase_orders(order_id),
    
    -- Payment Terms
    payment_terms_id        INT REFERENCES payment_terms(terms_id),
    discount_percent        DECIMAL(5,2) DEFAULT 0.00,
    discount_amount         DECIMAL(15,2) DEFAULT 0.00,
    discount_date           DATE,  -- Pay by this date to get early payment discount
    
    -- Currency (for international suppliers)
    currency_code           VARCHAR(3) DEFAULT 'AUD',
    exchange_rate           DECIMAL(10,6) DEFAULT 1.000000,
    
    reference               VARCHAR(100),
    notes                   TEXT,
    
    created_by              VARCHAR(50),
    created_date            DATETIME,
    modified_by             VARCHAR(50),
    modified_date           DATETIME,
    
    UNIQUE (invoice_number),
    INDEX idx_supplier (supplier_id),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_due_date (due_date),
    INDEX idx_status (status),
    INDEX idx_period (period_id)
);
```

**Invoice Processing Workflow:**

```
1. ENTRY:
   - Enter supplier details
   - Enter invoice date and number
   - Add line items (expenses or inventory)
   - System calculates tax and totals
   - Save as DRAFT

2. APPROVAL (optional):
   - Review invoice details
   - Check against PO (if exists)
   - Approve for posting

3. POSTING:
   - Validate invoice (balanced, valid accounts, etc.)
   - Create GL journal entry:
     DR Expense Account(s) or Inventory
     DR Tax (if applicable)
     CR Accounts Payable (supplier balance)
   - Mark invoice as POSTED
   - Update supplier current_balance

4. PAYMENT:
   - Select invoices to pay
   - Create payment batch
   - Generate payment file (EFT) or print cheques
   - Allocate payment to invoices
   - Create GL journal:
     DR Accounts Payable
     CR Bank Account
   - Update invoice amount_paid
   - Mark invoice as PAID when fully paid
```

#### PURCHASE_INVOICE_LINES Table

```sql
CREATE TABLE purchase_invoice_lines (
    line_id                 BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_id              BIGINT NOT NULL REFERENCES purchase_invoices(invoice_id) ON DELETE CASCADE,
    line_number             INT NOT NULL,
    
    -- Item or Account
    line_type               ENUM('ITEM', 'ACCOUNT', 'COMMENT') DEFAULT 'ACCOUNT',
    item_id                 INT REFERENCES inventory_items(item_id),
    account_id              INT REFERENCES chart_of_accounts(account_id),
    
    description             VARCHAR(255),
    
    -- Quantity (for inventory items)
    quantity                DECIMAL(15,4) DEFAULT 0.0000,
    unit_cost               DECIMAL(15,4) DEFAULT 0.0000,
    
    -- Amounts
    line_amount             DECIMAL(15,2) NOT NULL,
    tax_code_id             INT REFERENCES tax_codes(tax_code_id),
    tax_amount              DECIMAL(15,2) DEFAULT 0.00,
    line_total              DECIMAL(15,2) GENERATED ALWAYS AS (line_amount + tax_amount) STORED,
    
    -- Job Costing
    job_id                  INT REFERENCES jobs(job_id),
    department_id           INT REFERENCES departments(department_id),
    
    -- PO Line Link
    purchase_order_line_id  BIGINT REFERENCES purchase_order_lines(line_id),
    
    UNIQUE (invoice_id, line_number),
    INDEX idx_invoice (invoice_id),
    INDEX idx_item (item_id),
    INDEX idx_account (account_id)
);
```

**Line Types:**
1. **ITEM**: Inventory purchase - links to inventory_items
2. **ACCOUNT**: Expense purchase - links to expense account
3. **COMMENT**: Text-only line for notes

---

