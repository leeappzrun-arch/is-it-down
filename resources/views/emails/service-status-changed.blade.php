<!DOCTYPE html>
<html lang="en">
    <body style="margin: 0; background: #f4f4f5; color: #18181b; font-family: Arial, sans-serif;">
        <div style="margin: 0 auto; max-width: 680px; padding: 32px 20px;">
            <div style="border-radius: 24px; background: #ffffff; padding: 32px; box-shadow: 0 20px 40px rgba(24, 24, 27, 0.08);">
                @include('emails.partials.brand')

                <h1 style="margin: 0 0 12px; font-size: 28px; line-height: 1.2;">
                    {{ $currentStatus === \App\Models\Service::STATUS_DOWN ? 'A monitored service is down' : 'A monitored service has recovered' }}
                </h1>

                <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.7; color: #3f3f46;">
                    {{ $service->name }} is currently marked as <strong>{{ strtoupper($currentStatus) }}</strong>.
                    {{ $reason }}
                </p>

                <div style="border-radius: 18px; background: #fafafa; padding: 20px;">
                    <div style="margin-bottom: 12px; font-size: 13px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #71717a;">Service details</div>
                    <div style="margin-bottom: 10px;"><strong>Name:</strong> {{ $service->name }}</div>
                    <div style="margin-bottom: 10px;"><strong>URL:</strong> {{ $service->url }}</div>
                    <div style="margin-bottom: 10px;"><strong>Status change:</strong> {{ strtoupper($previousStatus ?? 'unknown') }} to {{ strtoupper($currentStatus) }}</div>
                    @if ($downtime?->ended_at)
                        <div style="margin-bottom: 10px;"><strong>Downtime:</strong> {{ $downtime->durationSummary($downtime->ended_at) }}</div>
                    @endif
                    <div style="margin-bottom: 10px;"><strong>Checked at:</strong> {{ $checkedAt->toDayDateTimeString() }} UTC</div>
                    @if ($responseCode !== null)
                        <div style="margin-bottom: 10px;"><strong>HTTP response:</strong> {{ $responseCode }}</div>
                    @endif
                    <div style="margin-bottom: 10px;"><strong>Reason:</strong> {{ $reason }}</div>
                    @if ($downtime?->ai_summary)
                        <div style="margin-bottom: 10px;"><strong>Dave thinks:</strong> {{ $downtime->ai_summary }}</div>
                    @endif
                    @if ($downtime?->screenshotUrl())
                        <div style="margin-bottom: 10px;"><strong>Screenshot:</strong> <a href="{{ $downtime->screenshotUrl() }}">{{ $downtime->screenshotUrl() }}</a></div>
                    @endif
                    @if ($service->hasExpectation())
                        <div><strong>Expectation:</strong> {{ $service->expectSummary() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </body>
</html>
