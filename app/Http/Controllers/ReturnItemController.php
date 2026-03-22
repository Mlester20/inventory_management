<?php

namespace App\Http\Controllers;

use App\Models\ReturnItem;
use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\Request;

class ReturnItemController extends Controller
{
    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->expectsJson()) {
            // Return user's own return items for API requests
            $returnItems = ReturnItem::where('user_id', auth()->id())
                ->with('item')
                ->latest()
                ->get();

            return response()->json([
                'data' => $returnItems,
            ]);
        }

        // Admin view - all return items
        $returnItems = ReturnItem::all();
        $items = Item::all();
        return view('admin.return-items', compact('returnItems', 'items'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'return_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['status'] = 'pending';

        $returnItem = ReturnItem::create($validated);

        // Return JSON for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Return item created successfully.',
                'data' => $returnItem,
            ], 201);
        }

        return redirect()->route('admin.return-items')
            ->with('success', 'Return item created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ReturnItem $returnItem)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReturnItem $returnItem)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReturnItem $returnItem)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'reason' => 'sometimes|string|max:255',
        ]);

        $returnItem->update($validated);

        return redirect()->route('admin.return-items')
            ->with('success', 'Return item updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReturnItem $returnItem)
    {
        $returnItem->delete();

        return redirect()->route('admin.return-items')
            ->with('success', 'Return item deleted successfully.');
    }

    /**
     * Approve a return item and restock the item.
     */
    public function approve(Request $request, ReturnItem $returnItem)
    {
        try {
            // Only approve if status is pending
            if ($returnItem->status !== 'pending') {
                return back()->with('error', 'Only pending return items can be approved.');
            }

            // Use transaction to ensure atomicity
            \DB::transaction(function () use ($returnItem) {
                // Restock the item using StockService
                $this->stockService->restock(
                    $returnItem->item,
                    $returnItem->quantity,
                    "Return item approved - Return ID: {$returnItem->id}, Reason: {$returnItem->reason}",
                    auth()->id()
                );

                // Update return item status to approved
                $returnItem->update(['status' => 'approved']);
            });

            return back()->with('success', 'Return item approved successfully and stock updated.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error approving return item: ' . $e->getMessage());
        }
    }

    /**
     * Reject a return item.
     */
    public function reject(Request $request, ReturnItem $returnItem)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        try {
            // Only reject if status is pending
            if ($returnItem->status !== 'pending') {
                return back()->with('error', 'Only pending return items can be rejected.');
            }

            $rejectionReason = $request->input('rejection_reason', 'No reason provided');

            // Update return item status to rejected with reason
            $returnItem->update([
                'status' => 'rejected',
                'reason' => $returnItem->reason . ' | Rejected: ' . $rejectionReason,
            ]);

            return back()->with('success', 'Return item rejected successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error rejecting return item: ' . $e->getMessage());
        }
    }
}
