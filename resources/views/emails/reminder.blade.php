<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>LifePilot AI Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 24px;">
        <h2 style="color: #10b981; margin-top: 0;">🔔 ការរំលឹកពី LifePilot AI</h2>
        <hr style="border-color: #334155; margin-bottom: 20px;">
        
        <p style="font-size: 16px; color: #ffffff;"><strong>ចំណងជើង៖</strong> {{ $reminder->title }}</p>
        
        <p style="font-size: 14px; color: #cbd5e1;">
            <strong>ម៉ោងរំលឹក៖</strong> 
            <span style="color: #38bdf8;">{{ \Carbon\Carbon::parse($reminder->remind_at)->format('Y-m-d h:i A') }}</span>
        </p>

        <p style="font-size: 14px; color: #cbd5e1;">
            <strong>ការសារឡើងវិញ (Frequency)៖</strong> {{ ucfirst($reminder->frequency) }}
        </p>

        <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #334155; font-size: 12px; color: #94a3b8; text-center;">
            សារនេះត្រូវបានផ្ញើចេញដោយស្វ័យប្រវត្តិពីប្រព័ន្ធ LifePilot AI Platform។
        </div>
    </div>
</body>
</html>