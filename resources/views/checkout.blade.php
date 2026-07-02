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
            --success-bg: #f0fdf4;
            --success-text: #16a34a;
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
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
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

        .form-group label.form-label {
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

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--focus-ring);
        }

        /* Package Cards CSS */
        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .package-label {
            display: block;
            cursor: pointer;
        }

        .package-input {
            display: none;
        }

        .package-card {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.2s;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            height: 100%;
        }

        .package-card:hover {
            border-color: var(--focus-ring);
            background-color: var(--info-bg);
        }

        .package-input:checked + .package-card {
            border-color: var(--primary);
            background-color: #f8fafc;
            box-shadow: 0 0 0 1px var(--primary);
        }

        .wifi-icon {
            width: 28px;
            height: 28px;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .package-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .package-price {
            font-size: 1.125rem;
            font-weight: bold;
            color: var(--primary);
        }

        .package-badge {
            background-color: var(--success-bg);
            color: var(--success-text);
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 0.25rem;
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
        @if(session('success'))
            <div style="width: 100%; max-width: 420px; background: var(--success-bg); color: var(--success-text); padding: 1rem; border-radius: 8px; border: 1px solid #bbf7d0; text-align: center; font-size: 0.875rem;">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->has('resume'))
            <div style="width: 100%; max-width: 420px; background: var(--danger-bg); color: var(--danger-text); padding: 1rem; border-radius: 8px; border: 1px solid #fecaca; text-align: center; font-size: 0.875rem;">
                {{ $errors->first('resume') }}
            </div>
        @endif

        <div class="card">
            <div class="header">
                <div style="display: flex; justify-content: center; margin-bottom: 1rem;">
                    <svg style="width: 64px; height: 64px; color: var(--primary);" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" />
                    </svg>
                </div>
                <h1>Hillton Wi-Fi</h1>
                <p>Tafadhali chagua kifurushi na uweke namba yako ya simu Lipa kuunganishwa.</p>
            </div>

            <form action="{{ route('hotspot.pay') }}" method="POST" id="checkout-form" onsubmit="document.getElementById('submit-btn').disabled = true; document.getElementById('submit-btn').innerText = 'Tafadhali subiri...'; document.getElementById('submit-btn').style.opacity = '0.7';">
                @csrf
                <input type="hidden" name="mac" value="{{ $mac }}">
                <input type="hidden" name="ip" value="{{ $ip ?? '' }}">


                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="margin-bottom: 0.75rem;">Chagua Kifurushi</label>
                    <div class="packages-grid">
                        @foreach($packages as $index => $package)
                            <label class="package-label">
                                <input type="radio" name="package_id" value="{{ $package->id }}" class="package-input" required {{ $index === 0 ? 'checked' : '' }}>
                                <div class="package-card">
                                    <svg class="wifi-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" />
                                    </svg>
                                    <span class="package-name">{{ $package->name }}</span>
                                    <span class="package-price">{{ number_format($package->price) }}<small style="font-size: 0.65rem; color: var(--text-muted);"> TZS</small></span>
                                    <div style="display: flex; gap: 0.25rem; flex-wrap: wrap; justify-content: center;">
                                        <span class="package-badge">Unlimited Data</span>
                                        <span class="package-badge" style="background-color: #e0f2fe; color: #0284c7;">
                                            {{ $package->speed_limit ? 'Speed: ' . str_replace('/',' | ', $package->speed_limit) : 'Max Speed' }}
                                        </span>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('package_id')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Namba ya Simu</label>
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

        <!-- Resume Session Card -->
        <div class="card" style="padding: 1.5rem 2rem;">
            <div class="header" style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.125rem; font-weight: 600; color: var(--primary);">Umeshalipia Kifurushi?</h2>
                <p style="font-size: 0.75rem; margin-top: 0.25rem;">Weka namba ya simu na PIN yako ya siri kurejesha internet yako.</p>
            </div>
            
            <form action="{{ route('hotspot.resume') }}" method="POST" id="resume-form" onsubmit="document.getElementById('resume-submit-btn').disabled = true; document.getElementById('resume-submit-btn').innerText = 'Tafadhali subiri...';">
                @csrf
                <input type="hidden" name="mac" value="{{ $mac }}">
                <input type="hidden" name="ip" value="{{ $ip ?? '' }}">
                
                <div class="form-group">
                    <label for="resume_phone" class="form-label">Namba ya Simu</label>
                    <input type="tel" name="phone" id="resume_phone" placeholder="mf. 0712345678" required class="form-control">
                </div>

                <div class="form-group">
                    <label for="pin" class="form-label">PIN yako ya Siri</label>
                    <input type="text" name="pin" id="pin" placeholder="mf. 123456" maxlength="6" required class="form-control" style="letter-spacing: 2px; text-align: center; font-weight: bold;">
                </div>

                <button type="submit" id="resume-submit-btn" class="btn-submit" style="background-color: var(--text-muted);">
                    Rejesha Internet
                </button>
            </form>
        </div>
    </div>

</body>
</html>