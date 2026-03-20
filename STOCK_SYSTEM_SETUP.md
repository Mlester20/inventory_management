# Inventory Stock System - Setup Checklist & Quick Reference

## ✅ Setup Verification Checklist

### Step 1: Files Created/Modified
- [ ] `database/migrations/2026_03_19_120000_create_stock_movements_table.php` exists
- [ ] `app/Models/StockMovement.php` exists
- [ ] `app/Models/Item.php` updated with relationships
- [ ] `app/Services/StockService.php` exists
- [ ] `app/Http/Controllers/StockController.php` exists
- [ ] `routes/web.php` updated with stock routes
- [ ] `tests/Feature/StockManagementTest.php` exists

### Step 2: Database Setup
```bash
# Run this command
php artisan migrate

# Expected output:
# Migration table created successfully.
# Migrating: 2026_03_19_120000_create_stock_movements_table
# Migrated: 2026_03_19_120000_create_stock_movements_table
```
- [ ] Migration completed without errors
- [ ] `stock_movements` table exists in database
- [ ] Table has all required columns and indexes

### Step 3: Verify Database Structure
```bash
# Check table structure
php artisan tinker
```
Then in Tinker:
```php
>>> \Schema::getColumns('stock_movements')
# Should show: id, item_id, user_id, quantity, type, remarks, created_at, updated_at

>>> \DB::table('stock_movements')->count()
# Should return 0 (no records yet)

>>> App\Models\Item::first()
# Should return an item if items exist

>>> exit
```
- [ ] All columns present
- [ ] Relationships work (no errors)

### Step 4: Run Tests
```bash
php artisan test tests/Feature/StockManagementTest.php -v

# Expected output:
# Tests: 25 passed (X ms)
```
- [ ] All 25 tests pass
- [ ] No warnings or errors

### Step 5: Verify Routes
```bash
php artisan route:list | grep stock
```

Expected routes:
- [ ] `GET  /admin/stock/items/{item}/history`
- [ ] `POST /admin/stock/items/{item}/restock`
- [ ] `POST /admin/stock/items/{item}/deduct`
- [ ] `POST /admin/stock/items/{item}/adjust`
- [ ] `GET  /admin/stock/items/{item}/report`
- [ ] `GET  /admin/stock/low-stock`
- [ ] `GET  /admin/stock/out-of-stock`

---

## 🚀 Quick Start Commands

### Test the System

#### Option 1: Using Artisan Tinker
```bash
php artisan tinker
```

```php
// Get an item
$item = App\Models\Item::first();

// Create stock service
$service = app(App\Services\StockService::class);

// Test restock
$movement = $service->restock($item, 100, 'Test restock');
// Check: Item quantity should increase

// Test deduction
$movement = $service->deduct($item, 25, 'Test deduction');
// Check: Item quantity should decrease

// View history
$history = $service->getMovementHistory($item);
// Check: Should show both movements

// Get low stock items
$lowStock = $service->getLowStockItems();

// Exit
exit
```

#### Option 2: Using HTTP API (POST requests)
```bash
# First, authenticate or get auth token

# 1. Restock item 1 with 100 units
curl -X POST http://localhost:8000/admin/stock/items/1/restock \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"quantity": 100, "remarks": "Initial stock"}'

# 2. Deduct 25 units
curl -X POST http://localhost:8000/admin/stock/items/1/deduct \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"quantity": 25, "remarks": "Sale order"}'

# 3. View stock report
curl http://localhost:8000/admin/stock/items/1/report \
  -H "Authorization: Bearer YOUR_TOKEN"

# 4. Get low stock items
curl http://localhost:8000/admin/stock/low-stock \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Option 3: Unit Tests
```bash
# Run all stock tests
php artisan test tests/Feature/StockManagementTest.php

# Run specific test
php artisan test tests/Feature/StockManagementTest.php --filter test_restock_increases_quantity_without_duplicate_item

# Run with verbose output
php artisan test tests/Feature/StockManagementTest.php -v

# Run with coverage report
php artisan test tests/Feature/StockManagementTest.php --coverage-html=coverage
```

---

## 📖 Common Tasks & Code Snippets

### Task 1: Restock an Item
**Location**: Controller, Service, or Command

```php
use App\Services\StockService;
use App\Models\Item;

$service = app(StockService::class);
$item = Item::find(1);

$movement = $service->restock(
    $item,
    100,
    'PO#INV-2026-001'
);

echo "Restocked! New quantity: " . $item->quantity;
```

### Task 2: Deduct Stock (Sale)
```php
use App\Services\StockService;
use App\Models\Item;
use Illuminate\Validation\ValidationException;

$service = app(StockService::class);
$item = Item::find(1);

try {
    $movement = $service->deduct(
        $item,
        10,
        'Order #12345'
    );
    echo "Deducted! New quantity: " . $item->quantity;
} catch (ValidationException $e) {
    echo "Error: " . $e->errors()['quantity'][0];
}
```

### Task 3: Get Low Stock Items
```php
use App\Services\StockService;

$service = app(StockService::class);
$lowStockItems = $service->getLowStockItems();

foreach ($lowStockItems as $item) {
    echo "{$item->item_name}: {$item->quantity}/{$item->low_stock_threshold}\n";
}
```

### Task 4: Display Stock Report
```php
use App\Models\Item;

$item = Item::with(['category', 'supplier'])->find(1);

echo "Item: {$item->item_name}\n";
echo "Category: {$item->category->category_name}\n";
echo "Current Stock: {$item->quantity}\n";
echo "Low Threshold: {$item->low_stock_threshold}\n";
echo "Status: " . ($item->isLowOnStock() ? 'LOW' : 'OK') . "\n";

// Get last 10 movements
$movements = $item->stockMovements()
    ->with('user')
    ->latest('created_at')
    ->limit(10)
    ->get();

foreach ($movements as $m) {
    echo "{$m->type}: {$m->quantity} by {$m->user->name}\n";
}
```

### Task 5: Physical Inventory Count/Stocktake
```php
use App\Services\StockService;
use App\Models\Item;

$service = app(StockService::class);

// After physical count
$physicalCounts = [
    ['item_id' => 1, 'physical_count' => 85],
    ['item_id' => 2, 'physical_count' => 120],
    ['item_id' => 3, 'physical_count' => 0],
];

foreach ($physicalCounts as $count) {
    $item = Item::find($count['item_id']);
    
    $movement = $service->adjust(
        $item,
        $count['physical_count'],
        'Physical count 2026-03-19'
    );
    
    if ($movement) {
        $variance = $movement->quantity;
        echo "{$item->item_name}: Adjusted by {$variance}\n";
    }
}
```

### Task 6: Query Stock Movements
```php
use App\Models\StockMovement;

// All movements for item 1
$allMovements = StockMovement::where('item_id', 1)
    ->with(['user', 'item'])
    ->latest('created_at')
    ->get();

// Only restock movements
$restocks = StockMovement::where('item_id', 1)
    ->in()  // Using scope
    ->get();

// Only deduction movements
$deductions = StockMovement::where('item_id', 1)
    ->out()  // Using scope
    ->get();

// Movements from last 7 days
$recent = StockMovement::where('item_id', 1)
    ->where('created_at', '>=', now()->subDays(7))
    ->latest('created_at')
    ->get();

// Movements by specific user
$byAdmin = StockMovement::where('user_id', 1)
    ->latest('created_at')
    ->get();

// Paginated results
$movements = StockMovement::where('item_id', 1)
    ->with(['user', 'item'])
    ->paginate(50);
```

### Task 7: Create Custom Dashboard Query
```php
use App\Models\Item;

// Get inventory summary
$summary = [
    'total_items' => Item::count(),
    'total_stock' => Item::sum('quantity'),
    'total_value' => Item::selectRaw('SUM(quantity * unit_price)')->first(),
    'low_stock_count' => Item::lowStock()->count(),
    'out_of_stock_count' => Item::outOfStock()->count(),
];

// Get items by status
$items = Item::with(['category', 'supplier'])
    ->selectRaw('
        id,
        item_name,
        quantity,
        low_stock_threshold,
        unit_price,
        quantity * unit_price as total_value,
        CASE
            WHEN quantity <= 0 THEN "out_of_stock"
            WHEN quantity <= low_stock_threshold THEN "low_stock"
            ELSE "in_stock"
        END as status
    ')
    ->get();

foreach ($items as $item) {
    echo "{$item->item_name}: {$item->status}\n";
}
```

---

## 🔍 Debugging Commands

### Check Database Connection
```bash
php artisan tinker
>>> \DB::connection()->getPdo()
>>> exit
```

### Verify Tables
```bash
php artisan tinker
>>> \Schema::getTables()
>>> exit
```

### Check Migrations
```bash
php artisan migrate:status
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:cache --show
```

### Database Reset (USE WITH CAUTION!)
```bash
# Rollback all migrations
php artisan migrate:reset

# Rollback specific migration
php artisan migrate:rollback --step=1

# Refresh (rollback + migrate)
php artisan migrate:refresh

# Refresh with seeding
php artisan migrate:refresh --seed
```

---

## 🧪 Testing Commands

### Run Tests
```bash
# All tests
php artisan test

# Stock management tests only
php artisan test tests/Feature/StockManagementTest.php

# Single test method
php artisan test tests/Feature/StockManagementTest.php --filter="test_restock_increases_quantity_without_duplicate_item"

# Verbose (more detail)
php artisan test tests/Feature/StockManagementTest.php -v

# With code coverage
php artisan test tests/Feature/StockManagementTest.php --coverage-html coverage/

# Stop on first failure
php artisan test tests/Feature/StockManagementTest.php --stop-on-failure

# Show failed tests info
php artisan test tests/Feature/StockManagementTest.php --display-errors
```

---

## 📊 Database Queries

### Check Stock Movements Table
```bash
php artisan tinker
```

```php
// Count movements
>>> \DB::table('stock_movements')->count()

// View recent movements
>>> \DB::table('stock_movements')
    ->latest('created_at')
    ->limit(5)
    ->get()

// Movements by type
>>> \DB::table('stock_movements')
    ->where('type', 'in')
    ->sum('quantity')

// Exit
>>> exit
```

### Export Data
```bash
php artisan tinker
```

```php
// Export to CSV
$items = App\Models\Item::with('stockMovements')->get();
$export = $items->map(fn($i) => [
    'name' => $i->item_name,
    'qty' => $i->quantity,
    'movements' => $i->stockMovements->count()
]);

dd($export);
exit
```

---

## ⚙️ Configuration Tips

### Environment Variables
Make sure `.env` is configured:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory_app
DB_USERNAME=root
DB_PASSWORD=
```

### Test Configuration
For tests, ensure `.env.testing` exists:
```
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

---

## 🛡️ Security Reminders

✓ **Always authenticate** before accessing stock endpoints
✓ **Use authorization** - only admins can modify stock
✓ **Log activities** - track who made changes
✓ **Validate inputs** - all quantities are validated
✓ **Use transactions** - ensures data consistency
✓ **Audit trail** - all changes are recorded

---

## 📞 Quick Reference

| Action | Command |
|--------|---------|
| Run tests | `php artisan test tests/Feature/StockManagementTest.php` |
| Run migration | `php artisan migrate` |
| Rollback | `php artisan migrate:rollback --step=1` |
| Tinker repl | `php artisan tinker` |
| Clear cache | `php artisan cache:clear` |
| Show routes | `php artisan route:list \| grep stock` |
| Database status | `php artisan migrate:status` |

---

## 📝 Documentation Files

- **`STOCK_SYSTEM_IMPLEMENTATION.md`** - Overview and next steps
- **`STOCK_SYSTEM_DOCUMENTATION.md`** - Complete technical guide and API reference
- **`STOCK_SYSTEM_ARCHITECTURE.md`** - System design and data flow diagrams
- **`STOCK_SYSTEM_QUERIES.md`** - SQL queries for reporting
- **`STOCK_SYSTEM_SETUP.md`** - This file (setup checklist)

---

## 🎓 Learning Path

1. **Start here**: `STOCK_SYSTEM_IMPLEMENTATION.md` (overview)
2. **Understand the flow**: `STOCK_SYSTEM_ARCHITECTURE.md` (diagrams)
3. **See it in action**: Run tests with `php artisan test`
4. **Use the API**: Try curl commands above
5. **Deep dive**: `STOCK_SYSTEM_DOCUMENTATION.md` (complete reference)
6. **Query data**: `STOCK_SYSTEM_QUERIES.md` (SQL examples)

---

**Status**: ✅ Ready to use
**Last Updated**: March 19, 2026
**Version**: 1.0
