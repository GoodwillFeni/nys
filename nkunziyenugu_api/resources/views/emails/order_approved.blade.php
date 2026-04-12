<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
  .card { background: #fff; border-radius: 8px; max-width: 600px; margin: 0 auto; padding: 30px; }
  .header { background: #2e7d32; color: #fff; padding: 20px 30px; border-radius: 8px 8px 0 0; margin: -30px -30px 24px; }
  h2 { margin: 0; font-size: 20px; }
  .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: bold; background: #4caf50; color: #fff; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; }
  th { text-align: left; padding: 8px; background: #f0f0f0; font-size: 13px; }
  td { padding: 8px; border-bottom: 1px solid #eee; font-size: 13px; }
  .total { font-weight: bold; font-size: 15px; text-align: right; margin-top: 8px; }
  .footer { margin-top: 24px; font-size: 12px; color: #999; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h2>NYS — Order Approved</h2>
  </div>

  <p>Hi {{ $order->user->name }},</p>
  <p>Great news! Your order <strong>#{{ $order->id }}</strong> has been <strong>approved</strong> and is being processed.</p>

  <p><span class="badge">Approved</span></p>

  <table>
    <tr><th>Order #</th><td>{{ $order->id }}</td></tr>
    <tr><th>Approved On</th><td>{{ $order->approved_at?->format('d M Y H:i') }}</td></tr>
    <tr><th>Payment Method</th><td>{{ ucwords(str_replace('_', ' ', $order->payment_method ?? 'N/A')) }}</td></tr>
  </table>

  <table>
    <thead>
      <tr><th>Product</th><th>Qty</th><th>Total</th></tr>
    </thead>
    <tbody>
      @foreach($order->items as $item)
      <tr>
        <td>{{ $item->product->product_name ?? 'Product' }}</td>
        <td>{{ $item->qty }}</td>
        <td>R {{ number_format($item->total_price, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="total">Grand Total: R {{ number_format($order->total_amount, 2) }}</div>

  <p style="margin-top:20px">Thank you for shopping with us.</p>

  <div class="footer">NYS System — This is an automated message, please do not reply.</div>
</div>
</body>
</html>
