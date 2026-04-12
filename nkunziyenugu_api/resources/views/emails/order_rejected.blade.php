<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
  .card { background: #fff; border-radius: 8px; max-width: 600px; margin: 0 auto; padding: 30px; }
  .header { background: #c62828; color: #fff; padding: 20px 30px; border-radius: 8px 8px 0 0; margin: -30px -30px 24px; }
  h2 { margin: 0; font-size: 20px; }
  .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: bold; background: #ef5350; color: #fff; }
  .reason-box { background: #fff3f3; border-left: 4px solid #ef5350; padding: 12px 16px; border-radius: 4px; margin: 16px 0; }
  .footer { margin-top: 24px; font-size: 12px; color: #999; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h2>NYS — Order Update</h2>
  </div>

  <p>Hi {{ $order->user->name }},</p>
  <p>Unfortunately your order <strong>#{{ $order->id }}</strong> could not be approved at this time.</p>

  <p><span class="badge">Not Approved</span></p>

  @if($order->rejection_reason)
  <div class="reason-box">
    <strong>Reason:</strong> {{ $order->rejection_reason }}
  </div>
  @endif

  <p>If you have questions or would like to place a new order, please contact us.</p>

  <div class="footer">NYS System — This is an automated message, please do not reply.</div>
</div>
</body>
</html>
