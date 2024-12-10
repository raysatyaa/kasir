<?php

namespace Modules\Sale\Http\Controllers;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Category;
use Modules\Product\Entities\Product;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SaleDetails;
use Modules\Sale\Entities\SalePayment;
use Modules\Sale\Http\Requests\StorePosSaleRequest;

class PosController extends Controller
{
    public function index()
    {
        // Hapus semua item dalam keranjang sebelumnya
        Cart::instance('sale')->destroy();

        // Ambil data pelanggan dan kategori produk
        $customers = Customer::all();
        $product_categories = Category::all();

        return view('sale::pos.index', compact('product_categories', 'customers'));
    }

    public function store(StorePosSaleRequest $request)
    {
        DB::transaction(function () use ($request) {
            // Hitung jumlah yang harus dibayar
            $due_amount = $request->total_amount - $request->paid_amount;
            $payment_status = $this->determinePaymentStatus($due_amount, $request->total_amount);

            // Buat entri penjualan
            $sale = Sale::create([
                'date' => now()->format('Y-m-d'),
                'reference' => 'PSL-' . strtoupper(uniqid()),
                'customer_id' => $request->customer_id,
                'customer_name' => Customer::findOrFail($request->customer_id)->customer_name,
                'tax_percentage' => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount' => $request->shipping_amount * 100,
                'paid_amount' => $request->paid_amount * 100,
                'total_amount' => $request->total_amount * 100,
                'due_amount' => $due_amount * 100,
                'status' => 'Completed',
                'payment_status' => $payment_status,
                'payment_method' => $request->payment_method,
                'note' => $request->note,
                'tax_amount' => Cart::instance('sale')->tax() * 100,
                'discount_amount' => Cart::instance('sale')->discount() * 100,
            ]);

            // Proses setiap item di keranjang
            foreach (Cart::instance('sale')->content() as $cart_item) {
                $this->processCartItem($cart_item, $sale->id);
            }

            // Hapus keranjang setelah proses selesai
            Cart::instance('sale')->destroy();

            // Tambahkan pembayaran jika ada
            if ($sale->paid_amount > 0) {
                $this->createSalePayment($sale, $request->payment_method);
            }
        });

        // Tampilkan notifikasi berhasil
        toast('POS Sale Created!', 'success');

        return redirect()->route('sales.index');
    }

    private function determinePaymentStatus($due_amount, $total_amount)
    {
        if ($due_amount == $total_amount) {
            return 'Unpaid';
        } elseif ($due_amount > 0) {
            return 'Partial';
        }
        return 'Paid';
    }

    private function processCartItem($cart_item, $sale_id)
    {
        SaleDetails::create([
            'sale_id' => $sale_id,
            'product_id' => $cart_item->id,
            'product_name' => $cart_item->name,
            'product_code' => $cart_item->options->code,
            'quantity' => $cart_item->qty,
            'price' => $cart_item->price * 100,
            'unit_price' => $cart_item->options->unit_price * 100,
            'sub_total' => $cart_item->options->sub_total * 100,
            'product_discount_amount' => $cart_item->options->product_discount * 100,
            'product_discount_type' => $cart_item->options->product_discount_type,
            'product_tax_amount' => $cart_item->options->product_tax * 100,
        ]);

        // Update stok produk
        $product = Product::findOrFail($cart_item->id);
        if ($product->product_quantity < $cart_item->qty) {
            throw new \Exception("Stok produk {$product->product_name} tidak mencukupi.");
        }
        $product->decrement('product_quantity', $cart_item->qty);
    }

    private function createSalePayment($sale, $payment_method)
    {
        SalePayment::create([
            'date' => now()->format('Y-m-d'),
            'reference' => 'INV/' . $sale->reference,
            'amount' => $sale->paid_amount,
            'sale_id' => $sale->id,
            'payment_method' => $payment_method
        ]);
    }
}
