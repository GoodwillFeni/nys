<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
  .card { background: #fff; border-radius: 8px; max-width: 600px; margin: 0 auto; padding: 30px; }
  .header { background: #27253f; color: #fff; padding: 20px 30px; border-radius: 8px 8px 0 0; margin: -30px -30px 24px; }
  h2 { margin: 0; font-size: 20px; }
  .message-box { background: #f8f8ff; border-left: 4px solid #27253f; padding: 16px 20px; border-radius: 4px; margin: 16px 0; font-size: 15px; color: #333; line-height: 1.6; }
  .order-ref { font-size: 13px; color: #666; margin-bottom: 16px; }
  .footer { margin-top: 24px; font-size: 12px; color: #999; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <h2>NYS — Question about your Order</h2>
  </div>

  <p>Hi {{ $order->user->name }},</p>
  <p class="order-ref">Regarding Order <strong>#{{ $order->id }}</strong></p>

  <p>We have a question for you:</p>

  <div class="message-box">
    {{ $question }}
  </div>

  <p>Please reply to this email or contact us directly to respond.</p>

  <div class="footer">NYS System — This is an automated message. Reply to this email to respond.</div>
</div>
</body>
</html>
