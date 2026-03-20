# Inventory Stock System - Useful SQL Queries

These queries can be used directly or as references for creating scopes and methods in your application.

## 1. Current Item Inventory Summary

```sql
SELECT 
    i.id,
    i.item_name,
    i.quantity,
    i.low_stock_threshold,
    i.unit_price,
    (i.quantity * i.unit_price) as total_value,
    CASE 
        WHEN i.quantity <= 0 THEN 'out_of_stock'
        WHEN i.quantity <= i.low_stock_threshold THEN 'low_stock'
        ELSE 'in_stock'
    END as status,
    c.category_name,
    s.supplier_name
FROM items i
LEFT JOIN categories c ON i.category_id = c.id
LEFT JOIN suppliers s ON i.supplier_id = s.id
ORDER BY i.quantity ASC;
```

## 2. Low Stock Alert

```sql
SELECT 
    i.id,
    i.item_name,
    i.quantity,
    i.low_stock_threshold,
    (i.low_stock_threshold - i.quantity) as quantity_to_reorder,
    s.supplier_name,
    s.contact_email
FROM items i
LEFT JOIN suppliers s ON i.supplier_id = s.id
WHERE i.quantity <= i.low_stock_threshold
ORDER BY (i.low_stock_threshold - i.quantity) DESC;
```

## 3. Stock Movement History by Item

```sql
SELECT 
    sm.id,
    i.item_name,
    sm.type,
    sm.quantity,
    CONCAT(u.name, ' (', u.role, ')') as performed_by,
    sm.remarks,
    sm.created_at
FROM stock_movements sm
LEFT JOIN items i ON sm.item_id = i.id
LEFT JOIN users u ON sm.user_id = u.id
WHERE i.id = ?
ORDER BY sm.created_at DESC;
```

## 4. Daily Stock Activity Report

```sql
SELECT 
    DATE(sm.created_at) as date,
    sm.type,
    COUNT(*) as movement_count,
    SUM(ABS(sm.quantity)) as total_quantity,
    i.item_name
FROM stock_movements sm
LEFT JOIN items i ON sm.item_id = i.id
WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(sm.created_at), sm.type, i.id
ORDER BY sm.created_at DESC;
```

## 5. Top Restocked Items (Last 30 Days)

```sql
SELECT 
    i.id,
    i.item_name,
    COUNT(sm.id) as restock_count,
    SUM(sm.quantity) as total_restocked,
    c.category_name
FROM items i
LEFT JOIN stock_movements sm ON i.id = sm.item_id AND sm.type = 'in'
LEFT JOIN categories c ON i.category_id = c.id
WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY i.id
ORDER BY total_restocked DESC
LIMIT 10;
```

## 6. Top Deducted Items (Last 30 Days) - Sales/Usage

```sql
SELECT 
    i.id,
    i.item_name,
    COUNT(sm.id) as deduction_count,
    SUM(ABS(sm.quantity)) as total_deducted,
    c.category_name,
    s.supplier_name
FROM items i
LEFT JOIN stock_movements sm ON i.id = sm.item_id AND sm.type = 'out'
LEFT JOIN categories c ON i.category_id = c.id
LEFT JOIN suppliers s ON i.supplier_id = s.id
WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY i.id
ORDER BY total_deducted DESC
LIMIT 10;
```

## 7. Inventory Value by Category

```sql
SELECT 
    c.category_name,
    COUNT(i.id) as item_count,
    SUM(i.quantity) as total_quantity,
    SUM(i.quantity * i.unit_price) as total_value
FROM items i
LEFT JOIN categories c ON i.category_id = c.id
GROUP BY c.id, c.category_name
ORDER BY total_value DESC;
```

## 8. Inventory Value by Supplier

```sql
SELECT 
    s.supplier_name,
    s.contact_email,
    COUNT(i.id) as item_count,
    SUM(i.quantity) as total_quantity,
    SUM(i.quantity * i.unit_price) as total_value
FROM items i
LEFT JOIN suppliers s ON i.supplier_id = s.id
GROUP BY s.id, s.supplier_name
ORDER BY total_value DESC;
```

## 9. Stock Movement by User

```sql
SELECT 
    u.id,
    u.name,
    u.role,
    COUNT(sm.id) as movement_count,
    DATE(MAX(sm.created_at)) as last_activity
FROM users u
LEFT JOIN stock_movements sm ON u.id = sm.user_id
WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY u.id, u.name
ORDER BY movement_count DESC;
```

## 10. Recent Stock Movements (Last 24 Hours)

```sql
SELECT 
    sm.id,
    i.item_name,
    sm.type,
    sm.quantity,
    u.name as performed_by,
    sm.remarks,
    sm.created_at
FROM stock_movements sm
LEFT JOIN items i ON sm.item_id = i.id
LEFT JOIN users u ON sm.user_id = u.id
WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY sm.created_at DESC;
```

## 11. Items Never Restocked (No Stock-In Movements)

```sql
SELECT 
    i.id,
    i.item_name,
    i.quantity,
    i.low_stock_threshold,
    c.category_name,
    s.supplier_name
FROM items i
LEFT JOIN categories c ON i.category_id = c.id
LEFT JOIN suppliers s ON i.supplier_id = s.id
WHERE i.id NOT IN (
    SELECT DISTINCT item_id 
    FROM stock_movements 
    WHERE type = 'in'
);
```

## 12. Inventory Audit - Physical vs System Count

```sql
SELECT 
    i.id,
    i.item_name,
    i.quantity as system_count,
    ? as physical_count,  -- Replace with physical count variable
    (? - i.quantity) as variance,
    c.category_name
FROM items i
LEFT JOIN categories c ON i.category_id = c.id
WHERE i.id IN (...)  -- Specify item IDs that were physically counted
ORDER BY ABS(? - i.quantity) DESC;  -- Items with highest variance
```

## 13. Total Inventory Value

```sql
SELECT 
    SUM(i.quantity * i.unit_price) as total_inventory_value,
    COUNT(i.id) as total_items,
    AVG(i.quantity * i.unit_price) as avg_item_value,
    MIN(i.quantity * i.unit_price) as min_item_value,
    MAX(i.quantity * i.unit_price) as max_item_value
FROM items i;
```

## 14. Items Below Minimum Stock Level - Urgent Reorder

```sql
SELECT 
    i.id,
    i.item_name,
    i.quantity,
    i.low_stock_threshold,
    (i.low_stock_threshold - i.quantity) as shortage,
    i.unit_price,
    ((i.low_stock_threshold - i.quantity) * i.unit_price) as estimated_cost,
    s.supplier_name,
    s.contact_email
FROM items i
LEFT JOIN suppliers s ON i.supplier_id = s.id
WHERE i.quantity < (i.low_stock_threshold * 0.5)  -- Below 50% of threshold
ORDER BY shortage DESC;
```

## 15. Stock Movements with Details (Comprehensive View)

```sql
SELECT 
    sm.id as movement_id,
    i.id as item_id,
    i.item_name,
    sm.type,
    sm.quantity,
    ABS(sm.quantity) as absolute_quantity,
    u.name as performed_by,
    u.email,
    sm.remarks,
    sm.created_at,
    i.quantity as current_stock,
    c.category_name,
    s.supplier_name
FROM stock_movements sm
LEFT JOIN items i ON sm.item_id = i.id
LEFT JOIN categories c ON i.category_id = c.id
LEFT JOIN suppliers s ON i.supplier_id = s.id
LEFT JOIN users u ON sm.user_id = u.id
ORDER BY sm.created_at DESC
LIMIT 100;
```

## Usage in Laravel Queries

These queries can be converted to Eloquent queries:

```php
// Example: Get low stock items
$lowStockItems = Item::join('categories', 'items.category_id', '=', 'categories.id')
    ->join('suppliers', 'items.supplier_id', '=', 'suppliers.id')
    ->where('items.quantity', '<=', DB::raw('items.low_stock_threshold'))
    ->select([
        'items.id',
        'items.item_name',
        'items.quantity',
        'items.low_stock_threshold',
        'categories.category_name',
        'suppliers.supplier_name'
    ])
    ->orderBy('items.quantity', 'asc')
    ->get();

// Example: Get total inventory value
$totalValue = Item::selectRaw('
    SUM(quantity * unit_price) as total_value,
    COUNT(id) as total_items
')
->first();

// Example: Get recent movements
$recentMovements = StockMovement::with(['item', 'user'])
    ->where('created_at', '>=', now()->subHours(24))
    ->latest('created_at')
    ->get();
```

## Performance Optimization Tips

1. **Add Database Indexes** (already included in migration):
   - `stock_movements.item_id`
   - `stock_movements.user_id`
   - `stock_movements.type`
   - `stock_movements.created_at`

2. **Use Eager Loading** in Eloquent:
   ```php
   Item::with(['category', 'supplier', 'stockMovements'])->get();
   ```

3. **Use Query Pagination** for large result sets:
   ```php
   $items = Item::lowStock()->with(['category', 'supplier'])->paginate(50);
   ```

4. **Create Database Views** for complex reports:
   ```sql
   CREATE VIEW v_inventory_summary AS
   SELECT i.id, i.item_name, i.quantity, i.low_stock_threshold,
   -- ... rest of complex query
   FROM items i
   -- ... joins
   ```

5. **Use Raw Queries** for complex aggregations:
   ```php
   $report = DB::select(DB::raw('
       SELECT i.id, i.item_name, COUNT(sm.id) as movement_count
       FROM items i
       LEFT JOIN stock_movements sm ON i.id = sm.item_id
       GROUP BY i.id
   '));
   ```
