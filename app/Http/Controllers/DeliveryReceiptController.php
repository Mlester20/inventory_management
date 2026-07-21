<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryReceipt;
use App\Models\GenericName;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

class DeliveryReceiptController extends Controller
{
    public function __construct(protected DeliveryReceiptService $deliveryReceiptService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $deliveryReceipts = DeliveryReceipt::query()
            ->with('customer', 'salesOrder')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('dr_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('customer_name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.delivery-receipts.index', compact('deliveryReceipts', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $customers = Customer::orderBy('customer_name')->get();
        $genericNames = GenericName::with('category')->orderBy('generic_name')->get();
        $openSalesOrders = SalesOrder::with('customer')
            ->whereIn('status', ['open', 'partially_delivered'])
            ->latest()
            ->get();
        $users = User::orderBy('name')->get();
        $preselectedSalesOrderId = $request->query('sales_order_id');

        return view('admin.delivery-receipts.create', compact(
            'customers', 'genericNames', 'openSalesOrders', 'users', 'preselectedSalesOrderId'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_type' => 'required|in:advance_order,purchase_order',
            'customer_id' => 'required_if:transaction_type,advance_order|nullable|exists:customers,id',
            'sales_order_id' => 'required_if:transaction_type,purchase_order|nullable|exists:sales_orders,id',
            'receipt_date' => 'required|date',
            'prepared_by' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.sales_order_item_id' => 'nullable|exists:sales_order_items,id',
        ]);

        if ($validated['transaction_type'] === 'purchase_order') {
            $validated['customer_id'] = SalesOrder::findOrFail($validated['sales_order_id'])->customer_id;
        } else {
            $validated['sales_order_id'] = null;
        }

        try {
            $deliveryReceipt = $this->deliveryReceiptService->createDeliveryReceipt($validated, Auth::id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        Alert::success('Success', 'Delivery Receipt created successfully');
        return redirect()->route('delivery-receipts.show', $deliveryReceipt);
    }

    /**
     * Display the specified resource.
     */
    public function show(DeliveryReceipt $deliveryReceipt)
    {
        $deliveryReceipt->load('customer', 'salesOrder', 'preparedBy', 'items.item');

        return view('admin.delivery-receipts.show', compact('deliveryReceipt'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DeliveryReceipt $deliveryReceipt)
    {
        Alert::info('Not supported', 'Editing an issued Delivery Receipt is not supported.');
        return redirect()->route('delivery-receipts.show', $deliveryReceipt);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeliveryReceipt $deliveryReceipt)
    {
        Alert::info('Not supported', 'Editing an issued Delivery Receipt is not supported.');
        return redirect()->route('delivery-receipts.show', $deliveryReceipt);
    }
}
