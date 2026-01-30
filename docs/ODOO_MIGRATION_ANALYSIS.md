# Odoo to LaraCRM Migration Analysis

## Executive Summary

After thorough analysis of both systems, **LaraCRM's architecture is fundamentally sound and should be enhanced, not replaced**. The recommendation is to add targeted features Annie actually needs while leveraging LaraCRM's existing strengths.

**Key insight:** Odoo has 882 tables, Annie uses ~15 core tables. LaraCRM has 31 well-designed tables covering most of Annie's needs already.

---

## Annie's Actual Business Usage in Odoo

### Actively Used Features

| Feature | Odoo Records | Notes |
|---------|-------------|-------|
| Customers/Partners | 151 | 76 companies, delivery addresses |
| Products | 72 | Industrial cleaning chemicals |
| Sales Orders | 115 | 93 confirmed |
| Customer Invoices | 66 posted | Primary workflow |
| Vendor Bills | 7 posted | Occasional |
| Payments | 26 | Single bank account |
| Stock Pickings | 121 | Dispatch tracking |
| Stock Moves | 148 | Inventory movements |
| Purchase Orders | 21 | Supplier orders |

### Features NOT Used (Zero Records)

- CRM/Leads
- Projects/Tasks
- Bank Statement Reconciliation
- Asset Management
- Loans
- Analytic Accounting
- Multiple Companies/Entities

### Business Currency

- 96% AUD transactions
- 4% USD (occasional)

---

## LaraCRM Current Capabilities

### Already Implemented (31 tables)

**CRM Domain:**
- Customers with company/individual types
- Contacts with primary flag
- Opportunities (6-stage pipeline) - *exceeds Annie's needs*
- Activities (polymorphic) - *exceeds Annie's needs*
- Multiple addresses per customer

**Accounting Domain:**
- Double-entry GL with journal entries
- Chart of accounts (hierarchical)
- Invoices with line items, PDF generation
- Quotes with line items, expiry tracking
- Payments with auto-allocation
- Products with GL mapping
- Bank accounts
- Expenses
- Tax rates

**Reporting:**
- P&L Statement
- Balance Sheet
- Trial Balance
- AR Aging
- Customer Statements
- Revenue Summary

### Architecture Strengths

1. **Proper double-entry bookkeeping** - JournalEntryService enforces balanced entries
2. **Polymorphic references** - GL entries trace back to source documents
3. **Decimal precision** - bccomp for monetary calculations
4. **Transaction safety** - DB::transaction wrappers
5. **Soft deletes** - Audit trail preserved
6. **Activity logging** - Spatie ActivityLog integration

---

## Gap Analysis: What's Missing

### Critical Gaps (Annie needs these)

| Gap | Priority | Complexity | Notes |
|-----|----------|------------|-------|
| Vendor Bills (AP) | HIGH | Medium | Formalize expense → vendor bill workflow |
| Inventory Quantities | HIGH | Medium | Track stock on hand |
| Stock Movements | HIGH | Medium | Record dispatch/receipt |
| Quote → Invoice Conversion | HIGH | Low | Service method exists, needs completion |
| Purchase Orders | MEDIUM | Medium | For supplier management |

### Nice-to-Have Gaps

| Gap | Priority | Notes |
|-----|----------|-------|
| Multi-currency exchange rates | LOW | Only 4% USD transactions |
| Bank statement import | LOW | Annie doesn't use this in Odoo |
| Recurring invoices | LOW | Not used currently |

### Features Annie WON'T Need

- Complex opportunity pipeline (leads→qualified→proposal→negotiation→won/lost)
- Activity tracking on opportunities
- Project/task management
- Asset depreciation
- Loan management
- Analytic accounting
- Multi-company support

---

## Recommended Architecture Changes

### Phase 1: Core Additions (Essential)

#### 1. Vendor Bill Model (Formalized AP)

```
vendor_bills
├── id
├── vendor_id → customers (where supplier_rank > 0)
├── bill_number (unique)
├── bill_date
├── due_date
├── reference (vendor's invoice number)
├── subtotal, tax_amount, total_amount
├── paid_amount, balance_due
├── status: Draft, Received, Partial, Paid, Void
├── journal_entry_id
└── timestamps

vendor_bill_items
├── id
├── vendor_bill_id
├── product_id
├── description
├── quantity, unit_price
├── tax_rate, line_total
└── sort_order
```

**Service:** `VendorBillService` with GL posting (Debit Expense, Credit AP)

#### 2. Simple Inventory Tracking

```
inventory_locations
├── id
├── name (e.g., "Main Warehouse", "Transit")
├── is_default
└── is_active

stock_levels (denormalized for simplicity)
├── id
├── product_id
├── location_id
├── quantity_on_hand
├── quantity_reserved
├── quantity_available (computed)
└── last_counted_at

stock_movements
├── id
├── product_id
├── from_location_id (nullable for receipts)
├── to_location_id (nullable for shipments)
├── quantity
├── movement_type: receipt, shipment, transfer, adjustment
├── reference_type, reference_id (Invoice, VendorBill, etc.)
├── notes
├── created_by
└── timestamps
```

#### 3. Quote → Invoice Conversion

Enhance existing `QuoteService`:

```php
public function convertToInvoice(Quote $quote): Invoice
{
    // Copy quote data to invoice
    // Copy all quote items to invoice items
    // Update quote status to Converted
    // Link quote.invoice_id
    // Return new invoice
}
```

#### 4. Purchase Orders

```
purchase_orders
├── id
├── vendor_id → customers
├── po_number (unique)
├── order_date
├── expected_date
├── subtotal, tax_amount, total_amount
├── status: Draft, Sent, Confirmed, Received, Cancelled
├── vendor_bill_id (when billed)
├── notes
└── timestamps

purchase_order_items
├── id
├── purchase_order_id
├── product_id
├── description
├── quantity, unit_price
├── quantity_received
├── tax_rate, line_total
└── sort_order
```

### Phase 2: UI Simplification

#### Hide/Remove Unused CRM Features

For Annie's use case, simplify the navigation:

```php
// AdminPanelProvider.php
->navigation(function (NavigationBuilder $builder): NavigationBuilder {
    return $builder
        ->group('Sales', [
            CustomerResource::class,
            QuoteResource::class,
            InvoiceResource::class,
        ])
        ->group('Purchasing', [
            VendorResource::class,  // Filter customers where supplier_rank > 0
            PurchaseOrderResource::class,
            VendorBillResource::class,
        ])
        ->group('Inventory', [
            ProductResource::class,
            StockLevelResource::class,
            StockMovementResource::class,
        ])
        ->group('Payments', [
            PaymentResource::class,
        ])
        ->group('Reports', [
            // Simplified reporting
        ]);
})
```

#### Remove/Hide from Navigation

- Opportunities (unless Annie wants sales pipeline later)
- Activities
- Contacts (show inline on Customer page)
- Complex journal entry management (auto-created by services)

### Phase 3: Data Migration

#### Migration Strategy

1. **Customers** - Direct import from `odoo_partners.csv`
2. **Products** - Direct import from `odoo_products.csv`
3. **Historical Invoices** - Import as "Migrated" status (no GL recreation)
4. **Sales Orders** - Import as Quotes with "Converted" status
5. **Opening Balances** - Single journal entry for AR/AP balances

#### Migration Command

```php
php artisan odoo:import --file=storage/odoo-import/odoo_partners.csv --type=customers
php artisan odoo:import --file=storage/odoo-import/odoo_products.csv --type=products
php artisan odoo:import --file=storage/odoo-import/odoo_invoices.csv --type=invoices --historical
```

---

## Schema Comparison

### Odoo vs LaraCRM (Core Business Tables)

| Odoo Table | LaraCRM Equivalent | Status |
|------------|-------------------|--------|
| res_partner | customers | ✅ Exists |
| res_partner (delivery) | addresses | ✅ Exists |
| product_template | products | ✅ Exists |
| product_product | products | ✅ Merged (no variants) |
| sale_order | quotes | ✅ Exists |
| sale_order_line | quote_items | ✅ Exists |
| account_move (invoice) | invoices | ✅ Exists |
| account_move_line | invoice_items | ✅ Exists |
| account_move (bill) | vendor_bills | ❌ **Needs creation** |
| account_payment | payments | ✅ Exists |
| purchase_order | purchase_orders | ❌ **Needs creation** |
| stock_picking | stock_movements | ❌ **Needs creation** |
| stock_quant | stock_levels | ❌ **Needs creation** |
| account_account | accounts | ✅ Exists |
| account_journal | (not needed) | N/A - simplified |

### Tables NOT Needed from Odoo

- 800+ configuration/system tables
- Multi-company tables (res_company, etc.)
- Complex tax configuration
- Asset management
- Analytic accounting
- Bank reconciliation wizards
- Document management
- Mail/messaging system
- Web/portal tables

---

## Implementation Estimate

### Phase 1: Core Additions
- Vendor Bills + Service: 2-3 days
- Inventory (locations, stock levels, movements): 3-4 days
- Quote → Invoice service: 1 day
- Purchase Orders: 2-3 days
- **Total: ~10-12 days**

### Phase 2: UI Simplification
- Navigation restructure: 1 day
- Dashboard widgets update: 1 day
- **Total: ~2 days**

### Phase 3: Data Migration
- Import commands: 2 days
- Data validation & cleanup: 1-2 days
- Opening balance entry: 1 day
- **Total: ~4-5 days**

### Overall: ~16-19 development days

---

## Recommendation Summary

**DO:**
1. Keep LaraCRM's existing architecture - it's well-designed
2. Add vendor bills, inventory tracking, purchase orders
3. Complete quote-to-invoice conversion
4. Simplify UI for Annie's workflow
5. Import Odoo data via CSV migration

**DON'T:**
1. Don't try to replicate Odoo's complexity
2. Don't import the full Odoo schema
3. Don't recreate historical GL entries (import as opening balances)
4. Don't build features Annie doesn't use

---

## Benefits of This Approach

1. **Familiar Technology** - Laravel + Filament vs Odoo's Python framework
2. **Simpler Maintenance** - 31 tables vs 882 tables
3. **Faster Performance** - SQLite for small business scale
4. **Easier Customization** - Filament resources are straightforward
5. **Better UX** - Filament's modern UI vs Odoo's enterprise complexity
6. **Lower Hosting Costs** - Single Laravel app vs Odoo + PostgreSQL
7. **AI-Friendly** - MCP integration for Claude assistance

---

## References

- [Eloquent IFRS](https://github.com/ekmungai/eloquent-ifrs) - Double-entry patterns
- [ERPSaaS](https://github.com/andrewdwallo/erpsaas) - Laravel/Filament accounting
- [Aureus ERP](https://aureuserp.com/) - Filament-based ERP alternative
- [Abivia Ledger](https://ledger.abivia.com/) - Laravel GL package

---

*Generated: 2026-01-29*
*Analysis by: Claude Code with Odoo database access*
