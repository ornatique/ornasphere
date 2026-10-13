<?php
namespace App\Support;

use App\Models\{CustomerOrder, CustomerAdvanceVoucher};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderProduction
{
    public static function check(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['production' => $message]);
    }

    public static function active(CustomerOrder $order): void
    {
        self::check($order->status === 'confirmed', 'Confirm the order and remove any hold before continuing production.');
    }

    public static function event(CustomerOrder $order, int $userId, string $description): void
    {
        DB::table('order_production_events')->insert(['customer_order_id' => $order->id, 'user_id' => $userId,
            'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
    }

    // Only attach an existing accounting entry. Never post a second ledger entry.
    public static function linkVoucher(int $orderId, CustomerAdvanceVoucher $voucher, int $userId): void
    {
        DB::transaction(function () use ($orderId, $voucher, $userId) {
            $order = CustomerOrder::where('company_id', $voucher->company_id)->lockForUpdate()->findOrFail($orderId);
            self::check((int) $order->customer_id === (int) $voucher->customer_id, 'Voucher customer must match the order customer.');
            self::check($order->status !== 'cancelled', 'Cannot add a voucher to a cancelled order.');
            CustomerAdvanceVoucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('customer_order_vouchers')->where('voucher_id', $voucher->id)->first();
            self::check(!$existing || (int) $existing->customer_order_id === $order->id, 'This voucher is already linked to another order.');
            if (!$existing) {
                $order->vouchers()->attach($voucher->id);
                self::event($order, $userId, 'Linked voucher '.$voucher->voucher_no);
            }
        });
    }
}
