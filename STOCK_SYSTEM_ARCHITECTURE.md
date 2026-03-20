# Inventory Stock System - Architecture & Data Flow

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER/CLIENT                              │
│                    (Web Browser/API)                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    HTTP REQUESTS                                │
│  POST /admin/stock/items/{id}/restock                           │
│  POST /admin/stock/items/{id}/deduct                            │
│  POST /admin/stock/items/{id}/adjust                            │
│  GET  /admin/stock/items/{id}/report                            │
│  GET  /admin/stock/low-stock                                    │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│              STOCK CONTROLLER                                    │
│  (app/Http/Controllers/StockController.php)                     │
│                                                                  │
│  • Input Validation                                             │
│  • Error Handling                                               │
│  • JSON Responses                                               │
└──────────────────────┬───────────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│              STOCK SERVICE (Business Logic)                      │
│  (app/Services/StockService.php)                                │
│                                                                  │
│  ┌────────────────┬─────────────────┬──────────────────┐        │
│  │  restock()     │  deduct()       │  adjust()        │        │
│  │                │                 │                  │        │
│  │ • Validate     │ • Validate      │ • Validate       │        │
│  │ • Increment    │ • Check stock   │ • Calculate      │        │
│  │ • Create mov.  │ • Decrement     │ • Set quantity   │        │
│  │   type='in'    │ • Create mov.   │ • Create mov.    │        │
│  │                │   type='out'    │   type='in/out'  │        │
│  └────────────────┴─────────────────┴──────────────────┘        │
│                                                                  │
│  + Transactions (All or Nothing)                                │
│  + Atomicity (No partial updates)                               │
└──────────────────────┬───────────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│              MODELS (Data Access Layer)                          │
│                                                                  │
│  ┌─────────────────────┐      ┌──────────────────────┐          │
│  │   Item Model        │      │  StockMovement Model │          │
│  │                     │      │                      │          │
│  │ • id                │      │ • id                 │          │
│  │ • item_name         │      │ • item_id (FK)       │          │
│  │ • quantity          │◄─────┼ • user_id (FK)       │          │
│  │ • low_stock_        │      │ • quantity           │          │
│  │   threshold         │      │ • type ('in'/'out')  │          │
│  │ • unit_price        │      │ • remarks            │          │
│  │ • timestamps        │      │ • timestamps         │          │
│  │                     │      │                      │          │
│  │ Relationships:      │      │ Relationships:       │          │
│  │ • hasMany(...       │      │ • belongsTo(Item)    │          │
│  │   Movements)        │      │ • belongsTo(User)    │          │
│  │ • belongsTo(        │      │                      │          │
│  │   Category,         │      │ Scopes:              │          │
│  │   Supplier)         │      │ • in()               │          │
│  │                     │      │ • out()              │          │
│  │ Scopes:             │      │                      │          │
│  │ • lowStock()        │      │ Attributes:          │          │
│  │ • outOfStock()      │      │ • absolute_quantity  │          │
│  │                     │      │                      │          │
│  │ Methods:            │      │                      │          │
│  │ • isLowOnStock()    │      │                      │          │
│  │ • getTotalRestocked │      │                      │          │
│  │ • getTotalDeducted  │      │                      │          │
│  └─────────────────────┘      └──────────────────────┘          │
└──────────────────────┬───────────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│              DATABASE LAYER                                      │
│                                                                  │
│  ┌─────────────────────┐      ┌──────────────────────┐          │
│  │   items table       │      │ stock_movements table│          │
│  │                     │      │                      │          │
│  │ id INT (PK)         │      │ id INT (PK)          │          │
│  │ item_name VARCHAR   │      │ item_id INT (FK)     │          │
│  │ category_id INT     │      │ user_id INT (FK)     │          │
│  │ supplier_id INT     │      │ quantity INT         │          │
│  │ quantity INT        │◄─────┼ type ENUM('in/out')  │          │
│  │ low_stock_          │      │ remarks VARCHAR      │          │
│  │   threshold INT     │      │ created_at TIMESTAMP │          │
│  │ unit_price DECIMAL  │      │ updated_at TIMESTAMP │          │
│  │ created_at TIMESTAMP│      │                      │          │
│  │ updated_at TIMESTAMP│      │ INDEXES:             │          │
│  │                     │      │ • item_id            │          │
│  │ UNIQUE: item_name   │      │ • user_id            │          │
│  │ (one record per     │      │ • type               │          │
│  │  product!)          │      │ • created_at         │          │
│  └─────────────────────┘      └──────────────────────┘          │
└──────────────────────────────────────────────────────────────────┘
```

## Data Flow - Restock Operation

```
1. USER ACTION: "Restock 100 units"
   │
   ▼
2. API REQUEST: POST /admin/stock/items/1/restock
   │ {quantity: 100, remarks: "From supplier"}
   │
   ▼
3. CONTROLLER: Validates input
   │ quantity: required, integer, min:1 ✓
   │
   ▼
4. SERVICE: Calls restock() method
   │ Database Transaction begins
   │
   ├─► Fetch Item from database
   │   items.id=1, quantity=50
   │
   ├─► Validate quantity > 0 ✓
   │
   ├─► UPDATE item quantity
   │   UPDATE items SET quantity = 150 WHERE id = 1
   │
   ├─► INSERT stock movement
   │   INSERT INTO stock_movements (item_id, user_id, quantity, type, remarks)
   │   VALUES (1, 1, 100, 'in', 'From supplier')
   │
   └─► Database Transaction commits (both updates succeed or both rollback)
   │
   ▼
5. RESPONSE: JSON
   {
       "success": true,
       "message": "Item restocked. New quantity: 150",
       "item": { id: 1, quantity: 150, ... },
       "movement": { id: X, quantity: 100, type: 'in', ... }
   }
```

## Data Flow - Deduction Operation

```
1. USER ACTION: "Deduct 25 units (sale)"
   │
   ▼
2. API REQUEST: POST /admin/stock/items/1/deduct
   │ {quantity: 25, remarks: "Order #12345"}
   │
   ▼
3. CONTROLLER: Validates input
   │ quantity: required, integer, min:1 ✓
   │
   ▼
4. SERVICE: Calls deduct() method
   │ Database Transaction begins
   │
   ├─► Fetch Item from database
   │   items.id=1, quantity=150
   │
   ├─► Validate quantity > 0 ✓
   │
   ├─► Check if sufficient stock
   │   Available: 150 >= Requested: 25 ✓
   │
   ├─► UPDATE item quantity DOWN
   │   UPDATE items SET quantity = 125 WHERE id = 1
   │
   ├─► INSERT stock movement (NEGATIVE quantity)
   │   INSERT INTO stock_movements (item_id, user_id, quantity, type, remarks)
   │   VALUES (1, 1, -25, 'out', 'Order #12345')
   │
   └─► Database Transaction commits
   │
   ▼
5. RESPONSE: JSON
   {
       "success": true,
       "message": "Stock deducted. New quantity: 125",
       "item": { id: 1, quantity: 125, ... },
       "movement": { id: Y, quantity: -25, type: 'out', ... }
   }
```

## Data Flow - Error Scenario (Insufficient Stock)

```
1. USER ACTION: "Try to deduct 200 units"
   │
   ▼
2. API REQUEST: POST /admin/stock/items/1/deduct
   │ {quantity: 200}
   │
   ▼
3. CONTROLLER: Validates input
   │ quantity: required, integer, min:1 ✓
   │
   ▼
4. SERVICE: Calls deduct() method
   │ Database Transaction begins
   │
   ├─► Fetch Item: quantity = 125
   │
   ├─► Validate quantity > 0 ✓
   │
   ├─► Check if sufficient stock
   │   Available: 125 >= Requested: 200 ✗ FAILS!
   │
   ├─► Throw ValidationException
   │   "Insufficient stock. Available: 125, Requested: 200"
   │
   └─► Database Transaction ROLLBACKS
       (NO changes to database)
   │
   ▼
5. RESPONSE: JSON with Error (422)
   {
       "success": false,
       "errors": {
           "quantity": ["Insufficient stock. Available: 125, Requested: 200"]
       }
   }
```

## Query Examples - Getting Information

### Finding Low Stock Items
```
StockMovement Model                      Item Model
    └── user_id ──────┐            ┌──── has quantity
    └── item_id ──────┼────►    item_id ──► quantity <= low_stock_threshold?
                      │
                 ┌────┴─────┐
                 │ Database  │
                 │ Query     │
                 └───────────┘
                      │
                      ▼
              ┌──────────────────┐
              │ Low Stock Items  │
              │ • Item A: 5/10   │
              │ • Item B: 2/20   │
              │ • Item C: 8/15   │
              └──────────────────┘
```

### Viewing Stock History
```
User ──"Show history for Item 1"──► StockHistory Query
                                             │
                                    ┌────────▼────────┐
                                    │ SELECT * FROM   │
                                    │ stock_movements │
                                    │ WHERE           │
                                    │ item_id = 1     │
                                    │ ORDER BY        │
                                    │ created_at DESC │
                                    └────────┬────────┘
                                             │
                                    ┌────────▼────────────────┐
                                    │ Movement 1: 100 'in'    │
                                    │ Movement 2: -25 'out'   │
                                    │ Movement 3: -50 'out'   │
                                    │ Movement 4: 75 'in'     │
                                    └────────────────────────┘
                                             │
                                    ┌────────▼────────────────┐
                                    │ Calculate:             │
                                    │ • Total Restocked: 175 │
                                    │ • Total Deducted: 75   │
                                    │ • Current: 100         │
                                    └────────────────────────┘
```

## Key Design Principles

### 1. Single Item Record (No Duplicates)
```
WRONG (Old way):
Item 1: "Widget A" - qty 50
Item 2: "Widget A" - qty 100  ← DUPLICATE! (Same product)

CORRECT (Our system):
Item 1: "Widget A" - qty 150 (single record)
  ├─ Movement: +50 (restock)
  ├─ Movement: +100 (restock)
  └─ Movement: -25 (sale)
```

### 2. Complete Audit Trail
```
Every change is recorded:

Item Quantity: 50 ──► 75 ──► 50 ──► 150 ──► 125
                │        │       │        │
             +25    -25      +100     -25
            restock sale   restock   sale
           admin1  admin2   admin1   admin3
        timestamp timestamp timestamp timestamp
         remarks   remarks  remarks  remarks
```

### 3. Atomicity (All or Nothing)
```
Operation: Restock 100 units

Step 1: Update item qty
Step 2: Create movement

If BOTH succeed → Committed to database
If ONE fails → BOTH are rolled back
             → Database remains unchanged
             → Error returned to user
```

### 4. Data Integrity
```
Validation layers:

User Input
    ↓ Controller Validation
    ├─ quantity: required, integer, min:1
    ├─ remarks: optional, string, max:255
    ↓
Service Validation
    ├─ Is quantity positive?
    ├─ Is sufficient stock available? (for deduction)
    ├─ Is quantity a valid integer?
    ↓
Database Operation
    ├─ Transaction ensures consistency
    ├─ Foreign keys prevent orphaned records
    ├─ Indexes ensure performance
    ↓
Success or Error Response
```

## Relationships Summary

```
┌────────────────┐         ┌─────────────────────┐
│ User           │         │ Item                │
│                │         │                     │
│ id             │         │ id                  │
│ name           │         │ item_name           │
│ email          │         │ quantity            │
│ role           │         │ unit_price          │
└────────────────┘         │ low_stock_threshold │
        ▲                   └──────────┬──────────┘
        │                             │
        │                             │
        │                  hasMany    │
        │              ┌──────────────▼──────────────┐
        │              │                             │
        │       StockMovement                       │
        │       (belongsTo)                         │
        │              │                             │
        │belongsTo      │                             │
        --─────────────┼  • id                       │
                       │  • item_id ────────────┐    │
                       │  • user_id ────────────┼───►│
                       │  • quantity            │    │
                       │  • type                │    │
                       │  • remarks             │    │
                       │  • created_at          │    │
                       └────────────────────────┘    │
                                                     │
                                belongsTo            │
                                        ┌────────────┘
                                        │
                                        ▼
                                  ┌──────────────┐
                                  │ Category     │
                                  │ Supplier     │
                                  │ Purchase     │
                                  └──────────────┘
```

## Trade-offs & Decisions

### Why Negative Values for 'out' Movements?
```
Option A: Store absolute value + type
Movement: quantity=25, type='out'
▸ Requires additional logic to determine direction
▸ Need to check type every time

Option B: Store signed value (CHOSEN)
Movement: quantity=-25, type='out'
▸ Sum all movements to get current stock
▸ Intuitive: positive adds, negative subtracts
▸ Simpler arithmetic: sum(stock_movements.quantity) = final_quantity
```

### Why Immutable Movements?
```
Can't edit: Prevents audit trail corruption
Can't delete: Maintains historical accuracy

If you made a mistake:
• Don't edit the old movement
• Create a new corrective movement
• This leaves full audit trail
```

### Why Database Transactions?
```
Without transactions:
Item updated ──► Success
Movement insert ──► Fails
Result: Item quantity changed but no record of the change!

With transactions:
Both succeed ──► Commit (good)
One fails ──► Rollback (both undone, database clean)
```

## Performance Considerations

### Indexed Fields
```
stock_movements table indexes:
• item_id      → Fast lookups by item
• user_id      → Fast lookups by user
• type         → Fast filtering by 'in'/'out'
• created_at   → Fast time-range queries
```

### Query Optimization
```
GOOD:
Item::with('stockMovements').get()  ← Eager load (1+1 queries)

BAD:
foreach($items as $item) {
    $movements = $item->stockMovements->get();  ← N+1 queries!
}

BETTER:
StockMovement::with(['item', 'user'])
    ->latest('created_at')
    ->paginate(50)
```

---

This architecture ensures:
✓ No duplicate items on restock
✓ Complete, immutable audit trail
✓ Data consistency and integrity
✓ Accountability and traceability
✓ High performance and scalability
✓ Easy to test and maintain
