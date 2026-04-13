<!DOCTYPE html>
<html lang="en">
    <body style="margin: 0; background: #f4f4f5; color: #18181b; font-family: Arial, sans-serif;">
        <div style="margin: 0 auto; max-width: 680px; padding: 32px 20px;">
            <div style="border-radius: 24px; background: #ffffff; padding: 32px; box-shadow: 0 20px 40px rgba(24, 24, 27, 0.08);">
                @include('emails.partials.brand')

                <h1 style="margin: 0 0 12px; font-size: 28px; line-height: 1.2;">
                    Mail configuration test
                </h1>

                <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.7; color: #3f3f46;">
                    Dave successfully sent this test email using the application's current mail configuration.
                </p>

                <div style="border-radius: 18px; background: #fafafa; padding: 20px;">
                    <div style="margin-bottom: 10px;"><strong>Sent at:</strong> {{ $sentAt->toDayDateTimeString() }} UTC</div>
                    <div><strong>Mailer:</strong> {{ config('mail.default') }}</div>
                </div>
            </div>
        </div>
    </body>
</html>
