@extends('layout.app')

@section('title', 'Purchases')

@section('content')


    <div class="card mt-4">
        <h5 class="card-header">Purchases/Transactions</h5>
        <div class="table-responsive nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cashier</th>
                        <th>Item Name</th>
                        <th>Quantity Sold</th>
                        <th>Unit Price</th>
                        <th>Total Price</th>
                        <th>Purchase Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td>{{ $purchase->id }}</td>
                            <td>
                                @if($purchase->user)
                                    <span class="badge bg-label-info">{{ $purchase->user->name }}</span>
                                @else
                                    <span class="badge bg-label-secondary">System</span>
                                @endif
                            </td>
                            <td>{{ $purchase->productBatch->product->item_name }}</td>
                            <td>{{ $purchase->quantity_sold }}</td>
                            <td>₱{{ number_format($purchase->unit_price, 2) }}</td>
                            <td>₱{{ number_format($purchase->total_price, 2) }}</td>
                            <td>{{ $purchase->purchase_date }}</td>
                            <td>
                                <form
                                    action="{{ route('purchases.destroy', $purchase) }}"
                                    method="POST"
                                    class="d-inline"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to delete this purchase?')"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No purchases yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchases->hasPages())
            <div class="card-footer">
                {{ $purchases->links() }}
            </div>
        @endif
    </div>
@endsection

@section('scripts')
@endsection