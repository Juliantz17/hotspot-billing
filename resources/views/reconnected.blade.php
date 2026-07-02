<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umefanikiwa Kuunganishwa!</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
        }
        .card {
            background-color: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 2.5rem 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .icon {
            color: #16a34a;
            width: 64px;
            height: 64px;
            margin: 0 auto 1.5rem;
        }
        h1 {
            color: #0f172a;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        p {
            color: #475569;
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        .btn {
            display: inline-block;
            background-color: #16a34a;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #15803d;
        }
    </style>
</head>
<body>
    <div class="card">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <h1>Umefanikiwa Kuunganishwa!</h1>
        <p>Karibu tena! Kifurushi chako bado kipo hai na kitaisha tarehe <br><strong>{{ \Carbon\Carbon::parse($expires_at)->format('d M Y, H:i') }}</strong>.</p>
        <p>Muda huu unaweza kuendelea kutumia intaneti.</p>
        <a href="https://www.google.com" class="btn">Endelea Kutumia Intaneti</a>
    </div>

    <script>
        // Automatically redirect to google so the device's Captive Portal detects internet and closes
        setTimeout(function() {
            window.location.href = 'http://www.google.com/generate_204';
        }, 2000);
        
        setTimeout(function() {
            window.location.href = 'https://www.google.com';
        }, 3500);
    </script>
</body>
</html>
