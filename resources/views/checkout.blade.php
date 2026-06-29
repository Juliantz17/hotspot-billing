<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mtandao wa Wi-Fi</title>
    <style>
        :root {
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: #d1d5db;
            --primary: #0f172a;
            --primary-hover: #1e293b;
            --focus-ring: #cbd5e1;
            --error: #ef4444;
            --info-bg: #f8fafc;
            --info-border: #e2e8f0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }

        .container {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .card {
            background-color: var(--card-bg);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--info-border);
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .header p {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.5;
        }


        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            font-size: 0.875rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: #fff;
            color: var(--text-main);
            transition: all 0.2s;
            appearance: none;
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--focus-ring);
        }

        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            font-weight: 500;
            color: #fff;
            background-color: var(--primary);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
        }

        .error-message {
            color: var(--error);
            font-size: 0.75rem;
            margin-top: 0.375rem;
            display: block;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Mtandao wa Wi-Fi</h1>
                <p>Tafadhali chagua kifurushi na uweke namba yako ya simu kuunganishwa.</p>
            </div>

            <form action="{{ route('hotspot.pay') }}" method="POST" id="checkout-form" onsubmit="document.getElementById('submit-btn').disabled = true; document.getElementById('submit-btn').innerText = 'Tafadhali subiri...'; document.getElementById('submit-btn').style.opacity = '0.7';">
                @csrf
                <input type="hidden" name="mac" value="{{ $mac }}">


                <div class="form-group">
                    <label for="package_id">Chagua Kifurushi</label>
                    <select name="package_id" id="package_id" class="form-control">
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }} — {{ number_format($package->price) }} TZS</option>
                        @endforeach
                    </select>
                    @error('package_id')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="phone">Namba ya Simu</label>
                    <input type="tel" name="phone" id="phone" placeholder="mf. 0712345678" required class="form-control">
                    @error('phone')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" id="submit-btn" class="btn-submit">
                    Lipia Uunganishwe
                </button>
            </form>
        </div>
    </div>

</body>
</html>