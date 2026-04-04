<!DOCTYPE html>
<html lang="en">
    <body style="margin: 0; background: #f4f4f5; color: #18181b; font-family: Arial, sans-serif;">
        <div style="margin: 0 auto; max-width: 720px; padding: 32px 20px;">
            <div style="border-radius: 24px; background: #ffffff; padding: 32px; box-shadow: 0 20px 40px rgba(24, 24, 27, 0.08);">
                @include('emails.partials.brand')

                <h1 style="margin: 0 0 12px; font-size: 28px; line-height: 1.2;">Webhook delivery failed</h1>

                <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.7; color: #3f3f46;">
                    One or more webhook recipients failed while delivering the latest <strong>{{ strtoupper($triggeredStatus) }}</strong> alert for {{ $service->name }}.
                </p>

                <div style="margin-bottom: 24px; border-radius: 18px; background: #fafafa; padding: 20px;">
                    <div style="margin-bottom: 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">Service details</div>
                    <div style="margin-bottom: 10px;"><strong>Name:</strong> {{ $service->name }}</div>
                    <div style="margin-bottom: 10px;"><strong>URL:</strong> {{ $service->url }}</div>
                    <div><strong>Checked at:</strong> {{ $checkedAt->toDayDateTimeString() }} UTC</div>
                </div>

                @foreach ($failures as $failure)
                    <div style="margin-bottom: 16px; border-radius: 18px; border: 1px solid #e4e4e7; padding: 20px;">
                        <div style="margin-bottom: 10px; font-size: 18px; font-weight: 700;">{{ $failure['recipient_name'] }}</div>
                        <div style="margin-bottom: 8px;"><strong>Webhook URL:</strong> {{ $failure['webhook_url'] }}</div>
                        <div style="margin-bottom: 8px;"><strong>Authentication:</strong> {{ $failure['authentication'] }}</div>
                        <div style="margin-bottom: 8px;"><strong>Failure:</strong> {{ $failure['reason'] }}</div>
                        <div><strong>Recipient source:</strong> {{ implode(', ', $failure['sources']) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </body>
</html>
