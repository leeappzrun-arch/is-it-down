<!DOCTYPE html>
<html lang="en">
    <body style="margin: 0; background: #f4f4f5; color: #18181b; font-family: Arial, sans-serif;">
        <div style="margin: 0 auto; max-width: 680px; padding: 32px 20px;">
            <div style="border-radius: 24px; background: #ffffff; padding: 32px; box-shadow: 0 20px 40px rgba(24, 24, 27, 0.08);">
                @include('emails.partials.brand')

                <h1 style="margin: 0 0 12px; font-size: 28px; line-height: 1.2;">SSL certificate expiring soon</h1>

                <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.7; color: #3f3f46;">
                    The SSL certificate for <strong>{{ $service->name }}</strong> is nearing expiry.
                </p>

                <div style="border-radius: 18px; background: #fafafa; padding: 20px;">
                    <div style="margin-bottom: 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">Service details</div>
                    <div style="margin-bottom: 10px;"><strong>Name:</strong> {{ $service->name }}</div>
                    <div style="margin-bottom: 10px;"><strong>URL:</strong> {{ $service->url }}</div>
                    <div style="margin-bottom: 10px;"><strong>Certificate expiry:</strong> {{ $certificate->expiresAt->toDayDateTimeString() }} UTC</div>
                    <div style="margin-bottom: 10px;"><strong>Expiry window:</strong> {{ $certificate->summary($checkedAt) }}</div>
                    <div><strong>Checked at:</strong> {{ $checkedAt->toDayDateTimeString() }} UTC</div>
                </div>
            </div>
        </div>
    </body>
</html>
