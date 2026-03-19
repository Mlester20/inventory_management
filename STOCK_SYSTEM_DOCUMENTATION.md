# Inventory Stock System Documentation

## Overview

This inventory stock system ensures proper inventory management by:
- Maintaining a single record per item (product)
- Tracking all stock changes through the `stock_movements` table
- Preventing duplicate item records on restock
- Providing audit trail for all stock operations
- Ensuring data consistency and integrity

## Architecture

### Database Tables

#### `items` Table
```
- id (PK)
- item_name
- category_id (FK)
- supplier_id (FK)
- description (nullable)
- quantity (current stock)
- unit_price
- low_stock_threshold
- timestamps
```

#### `stock_movements` Table
Tracks all inventory changes with complete audit trail:
```
- id (PK)
- item_id (FK) - References items table
- user_id (FK, nullable) - User who made the action
- quantity (int) - Positive for stock-in, negative for stock-out
- type (enum) - 'in' (restock) or 'out' (deduction)
- remarks (nullable) - Additional notes/reason
- timestamps - created_at, updated_at
```

### Models

#### Item Model (`app/Models/Item.php`)
**Relationships:**
- `hasMany('stockMovements')` - All stock movements for this item
- `hasMany('restockMovements')` - Only stock-in movements
- `hasMany('deductionMovements')` - Only stock-out movements
- `belongsTo('supplier')` - Supplier of the item
- `belongsTo('category')` - Item category

**Methods:**
- `isLowOnStock()` - Check if item is below threshold
- `getTotalRestockedAttribute()` - Get total quantity restocked
- `getTotalDeductedAttribute()` - Get total quantity deducted

**Query Scopes:**
- `lowStock()` - Filter items with quantity <= low_stock_threshold
- `outOfStock()` - Filter items with quantity <= 0

#### StockMovement Model (`app/Models/StockMovement.php`)
**Relationships:**
- `belongsTo('item')` - Associated item
- `belongsTo('user')` - User who made the movement

**Scopes:**
- `in()` - Filter stock-in movements
- `out()` - Filter stock-out movements

## Services

### StockService (`app/Services/StockService.php`)

Core business logic for inventory operations. All methods use database transactions for atomicity.

#### Methods

##### `restock(Item $item, int $quantity, ?string $remarks = null, ?int $userId = null): StockMovement`
Increases item quantity and creates a stock-in movement.

**Validation:**
- Quantity must be positive integer
- Quantity must be > 0

**Returns:** Created StockMovement record

**Example:**
```php
$stockService = app(StockService::class);
$movement = $stockService->restock($item, 100, 'Emergency restock from supplier A');
// Item quantity increased by 100
// StockMovement created with type='in', quantity=100
```

---

##### `deduct(Item $item, int $quantity, ?string $remarks = null, ?int $userId = null): StockMovement`
Decreases item quantity and creates a stock-out movement.

**Validation:**
- Quantity must be positive integer
- Current stock must be >= requested quantity
- Prevents stock from going below 0

**Throws:** `ValidationException` if insufficient stock

**Returns:** Created StockMovement record

**Example:**
```php
try {
    $movement = $stockService->deduct($item, 50, 'Sale transaction #12345');
    // Item quantity decreased by 50
    // StockMovement created with type='out', quantity=-50
} catch (\Illuminate\Validation\ValidationException $e) {
    // Handle insufficient stock
    echo $e->errors()['quantity'][0]; // "Insufficient stock. Available: X, Requested: 50"
}
```

---

##### `adjust(Item $item, int $newQuantity, ?string $remarks = null, ?int $userId = null): ?StockMovement`
Adjusts stock to a specific level (inventory count/stocktake).

**Validation:**
- newQuantity must be >= 0
- Returns null if no change needed

**Returns:** StockMovement record or null if no change

**Example:**
```php
// Physical count shows item should have 500 units (currently has 480)
$movement = $stockService->adjust($item, 500, 'Stock count verification');
// StockMovement created with type='in', quantity=20 (difference)

// If adjustment decreases stock instead
$movement = $stockService->adjust($item, 450, 'Stock count verification');
// StockMovement created with type='out', quantity=-30 (difference)
```

---

##### `getMovementHistory(Item $item, int $limit = 50): Collection`
Gets recent stock movements for an item with user information.

**Returns:** Collection of StockMovement records (latest first, limited to 50 by default)

**Example:**
```php
$movements = $stockService->getMovementHistory($item, 100);
foreach ($movements as $movement) {
    echo "{$movement->type}: {$movement->quantity} by {$movement->user->name} on {$movement->created_at}";
}
```

---

##### `getLowStockItems(): Collection`
Gets all items that are below their low_stock_threshold.

**Returns:** Collection of Item records

**Example:**
```php
$lowStockItems = $stockService->getLowStockItems();
foreach ($lowStockItems as $item) {
    echo "{$item->item_name}: Current={$item->quantity}, Threshold={$item->low_stock_threshold}";
}
```

---

##### `getOutOfStockItems(): Collection`
Gets all items with quantity <= 0.

**Returns:** Collection of Item records

**Example:**
```php
$outOfStock = $stockService->getOutOfStockItems();
// Use to notify suppliers for urgent restocking
```

## Controller

### StockController (`app/Http/Controllers/StockController.php`)

HTTP endpoints for stock operations.

#### Routes

All routes are prefixed with `/admin/stock` and require authentication + admin middleware.

| Method | Endpoint | Controller Method | Purpose |
|--------|----------|------------------|---------|
| GET | `/items/{item}/history` | `history()` | Get stock movement history |
| POST | `/items/{item}/restock` | `restock()` | Restock an item |
| POST | `/items/{item}/deduct` | `deduct()` | Deduct stock |
| POST | `/items/{item}/adjust` | `adjust()` | Adjust to specific level |
| GET | `/items/{item}/report` | `report()` | Get detailed stock report |
| GET | `/low-stock` | `lowStockItems()` | List all low stock items |
| GET | `/out-of-stock` | `outOfStockItems()` | List all out-of-stock items |

#### API Examples

##### Restock an Item
**Request:**
```bash
POST /admin/stock/items/1/restock
Content-Type: application/json

{
    "quantity": 100,
    "remarks": "Restock from supplier A"
}
```

**Request Validation:**
- `quantity` (required, integer, min: 1)
- `remarks` (optional, string, max: 255)

**Success Response (200):**
```json
{
    "success": true,
    "message": "Item restocked successfully. New quantity: 150",
    "movement": {
        "id": 1,
        "item_id": 1,
        "quantity": 100,
        "type": "in",
        "remarks": "Restock from supplier A",
        "user": {
            "id": 1,
            "name": "Admin User"
        },
        "created_at": "2026-03-19T12:00:00Z"
    },
    "item": {
        "id": 1,
        "item_name": "Widget A",
        "quantity": 150
    }
}
```

---

##### Deduct Stock (Sale/Usage)
**Request:**
```bash
POST /admin/stock/items/1/deduct
Content-Type: application/json

{
    "quantity": 25,
    "remarks": "Sale PO #54321"
}
```

**Error Response (422 - Insufficient Stock):**
```json
{
    "success": false,
    "errors": {
        "quantity": ["Insufficient stock. Available: 10, Requested: 25"]
    }
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "Stock deducted successfully. New quantity: 125",
    "movement": {
        "id": 2,
        "item_id": 1,
        "quantity": -25,
        "type": "out",
        "remarks": "Sale PO #54321",
        "created_at": "2026-03-19T12:05:00Z"
    },
    "item": {
        "id": 1,
        "quantity": 125
    }
}
```

---

##### Adjust Stock Level
**Request:**
```bash
POST /admin/stock/items/1/adjust
Content-Type: application/json

{
    "quantity": 100,
    "remarks": "Physical inventory count"
}
```

**Validation:**
- `quantity` (required, integer, min: 0)
- `remarks` (optional, string, max: 255)

**Success Response (200):**
```json
{
    "success": true,
    "message": "Stock adjusted to 100",
    "movement": {
        "id": 3,
        "item_id": 1,
        "quantity": -25,
        "type": "out",
        "remarks": "Physical inventory count",
        "created_at": "2026-03-19T12:10:00Z"
    },
    "item": {
        "id": 1,
        "quantity": 100
    }
}
```

---

##### Get Stock Report
**Request:**
```bash
GET /admin/stock/items/1/report
```

**Response (200):**
```json
{
    "item": {
        "id": 1,
        "item_name": "Widget A",
        "quantity": 100,
        "unit_price": "25.00",
        "low_stock_threshold": 20,
        "category": {
            "id": 1,
            "category_name": "Electronics"
        },
        "supplier": {
            "id": 2,
            "supplier_name": "Supplier A"
        }
    },
    "summary": {
        "current_stock": 100,
        "total_restocked": 250,
        "total_deducted": 150,
        "is_low_stock": false,
        "low_stock_threshold": 20,
        "unit_price": "25.00",
        "total_value": "2500.00"
    },
    "movements": [
        {
            "id": 3,
            "item_id": 1,
            "quantity": -25,
            "type": "out",
            "remarks": "Physical inventory count",
            "user": {
                "id": 1,
                "name": "Admin User"
            },
            "created_at": "2026-03-19T12:10:00Z"
        },
        {
            "id": 2,
            "item_id": 1,
            "quantity": -25,
            "type": "out",
            "remarks": "Sale PO #54321",
            "created_at": "2026-03-19T12:05:00Z"
        },
        {
            "id": 1,
            "item_id": 1,
            "quantity": 100,
            "type": "in",
            "remarks": "Restock from supplier A",
            "created_at": "2026-03-19T12:00:00Z"
        }
    ]
}
```

---

##### Get Low Stock Items
**Request:**
```bash
GET /admin/stock/low-stock
```

**Response (200):**
```json
{
    "count": 3,
    "items": [
        {
            "id": 2,
            "item_name": "Widget B",
            "quantity": 5,
            "low_stock_threshold": 10,
            "unit_price": "15.00",
            "category": {...},
            "supplier": {...}
        },
        {
            "id": 5,
            "item_name": "Widget E",
            "quantity": 2,
            "low_stock_threshold": 20,
            "unit_price": "30.00",
            "category": {...},
            "supplier": {...}
        }
    ]
}
```

---

##### Get Out of Stock Items
**Request:**
```bash
GET /admin/stock/out-of-stock
```

**Response (200):**
```json
{
    "count": 1,
    "items": [
        {
            "id": 7,
            "item_name": "Widget G",
            "quantity": 0,
            "low_stock_threshold": 10,
            "unit_price": "20.00",
            "category": {...},
            "supplier": {...}
        }
    ]
}
```

---

##### Get Stock Movement History
**Request:**
```bash
GET /admin/stock/items/1/history
```

**Response (200):**
```json
{
    "item": {
        "id": 1,
        "item_name": "Widget A",
        "quantity": 100,
        "low_stock_threshold": 20,
        "category": {...},
        "supplier": {...}
    },
    "movements": [
        {
            "id": 3,
            "item_id": 1,
            "quantity": -25,
            "type": "out",
            "remarks": "Physical inventory count",
            "user": {
                "id": 1,
                "name": "Admin User"
            },
            "created_at": "2026-03-19T12:10:00Z"
        }
    ],
    "summary": {
        "current_stock": 100,
        "is_low_stock": false,
        "low_stock_threshold": 20
    }
}
```

## Usage Examples in Code

### Example 1: Complete Restock Workflow
```php
<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected StockService $stockService)
    {}

    public function receiveOrder(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'po_number' => 'required|string',
        ]);

        $item = Item::find($validated['item_id']);
        
        // Restock the item (no duplicate record created!)
        $movement = $this->stockService->restock(
            $item,
            $validated['quantity'],
            "PO: {$validated['po_number']}"
        );

        return response()->json([
            'message' => 'Order received and stock updated',
            'item' => $item,
            'movement' => $movement,
        ]);
    }
}
```

### Example 2: Sales Deduction with Validation
```php
<?php

public function processSale(Request $request)
{
    $validated = $request->validate([
        'item_id' => 'required|exists:items,id',
        'quantity_sold' => 'required|integer|min:1',
        'order_id' => 'required|string',
    ]);

    $item = Item::find($validated['item_id']);

    try {
        // Deduct stock (validates sufficient quantity exists)
        $movement = $this->stockService->deduct(
            $item,
            $validated['quantity_sold'],
            "Order: {$validated['order_id']}"
        );

        // Process payment, send confirmation, etc...
        
        return response()->json(['success' => true, 'movement' => $movement]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Handle "out of stock" scenario
        return response()->json(['error' => $e->errors()], 422);
    }
}
```

### Example 3: Dashboard Low Stock Alerts
```php
<?php

public function dashboard()
{
    $lowStockItems = app(StockService::class)->getLowStockItems();
    $outOfStockItems = app(StockService::class)->getOutOfStockItems();

    return view('admin.dashboard', [
        'lowStockCount' => $lowStockItems->count(),
        'outOfStockCount' => $outOfStockItems->count(),
        'lowStockItems' => $lowStockItems,
        'outOfStockItems' => $outOfStockItems,
    ]);
}
```

### Example 4: Inventory Count/Stocktake
```php
<?php

public function stocktake(Request $request)
{
    $validated = $request->validate([
        'counts' => 'required|array',
        'counts.*.item_id' => 'required|exists:items,id',
        'counts.*.physical_count' => 'required|integer|min:0',
    ]);

    $results = [];

    foreach ($validated['counts'] as $count) {
        $item = Item::find($count['item_id']);
        
        // Adjust to physical count
        $movement = $this->stockService->adjust(
            $item,
            $count['physical_count'],
            'Physical inventory stocktake'
        );

        $results[] = [
            'item' => $item->item_name,
            'old_quantity' => $item->quantity,
            'new_quantity' => $count['physical_count'],
            'difference' => $movement ? $movement->quantity : 0,
        ];
    }

    return response()->json(['results' => $results]);
}
```

### Example 5: Query Stock History with Filters
```php
<?php

public function getStockMovements(Item $item)
{
    // Get all movements
    $allMovements = $item->stockMovements()
        ->with('user')
        ->latest('created_at')
        ->get();

    // Get only restock movements
    $restocks = $item->restockMovements()->with('user')->get();

    // Get only deduction movements
    $deductions = $item->deductionMovements()->with('user')->get();

    // Movements from last 7 days
    $recentMovements = $item->stockMovements()
        ->where('created_at', '>=', now()->subDays(7))
        ->latest('created_at')
        ->get();

    // Find movements by specific user
    $adminMovements = $item->stockMovements()
        ->where('user_id', Auth::id())
        ->latest('created_at')
        ->get();
}
```

## Running Migrations

After setting up all files, run migrations to create the tables:

```bash
php artisan migrate
```

If needed, rollback specific migration:

```bash
php artisan migrate:rollback --step=1
```

## Key Features

✅ **No Duplicate Items**: Restocking updates existing item quantity instead of creating new records

✅ **Complete Audit Trail**: Every stock change is tracked in `stock_movements`

✅ **User Accountability**: Track which user made each stock movement

✅ **Data Integrity**: Database transactions ensure atomicity of operations

✅ **Validation**: Prevents negative stock and validates all inputs

✅ **Query Scopes**: Easy filtering for low stock and out-of-stock items

✅ **Relationship Loading**: Efficient eager loading prevents N+1 queries

✅ **RESTful API**: Standard HTTP endpoints for all operations

## Notes

- All stock operations use database transactions for data consistency
- User ID is automatically captured from authenticated user if not provided
- Stock movements cannot be edited or deleted via API (audit trail protection)
- Quantities in `stock_movements` use positive values for 'in' type and negative for 'out' type
- Always use the `StockService` class for stock operations to ensure consistency
