<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment...</title>
    <style>
        :root {
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: #d8dde6;
            --primary: #111827;
            --success-bg: #f0fdf4;
            --success-text: #16a34a;
            --danger-bg: #fef2f2;
            --danger-text: #dc2626;
            --info-bg: #eff6ff;
            --info-text: #2563eb;
            --error: #ef4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: radial-gradient(circle at top, #ffffff 0, var(--bg-color) 46%);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }

        .card {
            background-color: var(--card-bg);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem 2rem;
            border-radius: 18px;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .spinner-container {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            width: 64px;
            height: 64px;
        }

        .spinner {
            width: 64px;
            height: 64px;
            border: 4px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .spinner-text {
            position: absolute;
            font-size: 0.75rem;
            font-weight: bold;
            color: var(--primary);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }

        .title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .desc {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .txn-box {
            background-color: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            font-family: monospace;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .txn-label {
            display: block;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-bottom: 0.25rem;
        }

        .status-box {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .status-success {
            background-color: var(--success-bg);
            color: var(--success-text);
        }

        .status-failed {
            background-color: var(--danger-bg);
            color: var(--danger-text);
        }

        .status-pending {
            background-color: var(--info-bg);
            color: var(--info-text);
        }

        .pulse-text {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: bold;
            text-decoration: none;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
        }

        .btn-danger {
            background-color: var(--error);
            color: white;
        }
        .btn-danger:hover {
            background-color: #b91c1c;
        }

        .link-muted {
            font-size: 0.75rem;
            color: var(--info-text);
            text-decoration: underline;
        }
        .link-muted:hover {
            color: #1d4ed8;
        }

        .footer-note {
            font-size: 0.7rem;
            color: #9ca3af;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>

    <div class="card">
        
        <div class="spinner-container">
            <div class="spinner"></div>
            <div class="spinner-text">TZS</div>
        </div>
        
        <h2 class="title">Angalia Simu Yako!</h2>
        <p class="desc">
            Tumekutumia ujumbe wa malipo (<strong>STK Push</strong>) kwenye simu yako. Tafadhali <strong>weka PIN yako</strong> ya sasa ya Mtandao ili kukamilisha ununuzi wa internet.
        </p>
        
        <div class="txn-box">
            <span class="txn-label">Kumbukumbu ya Muamala</span>
            {{ $txn }}
        </div>
        
        @if($status === 'SUCCESS')
        <div class="status-box status-success">
            <span>Malipo Yamekamilika! Tunakuunganisha...</span>
        </div>
        @elseif($status === 'FAILED')
        <div class="status-box status-failed">
            <span>Malipo Yameshindikana.</span>
            <a href="{{ route('hotspot.checkout', ['mac' => $mac, 'ip' => $ip ?? '']) }}" class="btn btn-danger">Jaribu Tena</a>
        </div>
        @else
        <div class="status-box status-pending">
            <span class="pulse-text">Inasubiri malipo yako...</span>
            <a href="{{ route('hotspot.checkout', ['mac' => $mac ?? '', 'ip' => $ip ?? '']) }}" class="link-muted">Ghairi / Jaribu Tena</a>
        </div>
        @endif

        <p class="footer-note">
            Ukurasa huu utajifunga na internet itafunguka yenyewe punde tu ukishaweka PIN yako.
        </p>
    </div>

    <script>
        @if($status === 'SUCCESS')
        // Automatically redirect to google to dismiss the captive portal after successful payment
        setTimeout(function() {
            window.location.href = 'https://www.google.com';
        }, 3000);
        @elseif($status !== 'FAILED')
        // Only reload if the payment is still pending
        setTimeout(function() {
            window.location.reload();
        }, 5000);
        @endif
    </script>

</body>
</html>
