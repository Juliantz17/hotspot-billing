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
            --info-bg: #eff6ff;
            --info-border: #bfdbfe;
            --info-text: #1d4ed8;
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

        @media (max-width: 480px) {
            .card {
                padding: 1.5rem 1rem;
            }
            .header h1 {
                font-size: 1.25rem;
            }
            .package-card {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>

    <div class="container">
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

                @if(isset($activeTxn))
                <div style="background-color: var(--success-bg); border: 1px solid #86efac; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; text-align: center;">
                    <h3 style="color: var(--success-text); margin-bottom: 0.5rem; font-weight: 600;">Karibu Tena!</h3>
                    <p style="font-size: 0.875rem; color: var(--text-main); margin-bottom: 1rem;">
                        Una kifurushi kinachoendelea ambacho kitaisha muda wake tarehe {{ \Carbon\Carbon::parse($activeTxn->expires_at)->format('d M Y, H:i') }}.
                    </p>
                    <button type="submit" formnovalidate formaction="{{ route('hotspot.reconnect_user') }}" formmethod="POST" class="btn-submit" style="background-color: var(--success-text); margin-top: 0;">Unganisha Tena Bure</button>
                    @if(session('success'))
                        <div style="margin-top: 0.5rem; color: var(--success-text); font-weight: bold;">{{ session('success') }}</div>
                    @endif
                    @error('reconnect')
                        <div style="margin-top: 0.5rem; color: var(--error); font-weight: bold; font-size: 0.875rem;">{{ $message }}</div>
                    @enderror
                </div>
                @else
                <div style="background-color: var(--info-bg); border: 1px solid var(--info-border); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; text-align: center;">
                    <p style="font-size: 0.875rem; color: var(--info-text); margin-bottom: 0.5rem;">
                        Simu yako imebadili MAC Address na umekatika?
                    </p>
                    <button type="button" onclick="document.getElementById('recover-section').style.display='block'; this.style.display='none';" class="btn-submit" style="background-color: transparent; border: 1px solid var(--info-text); color: var(--info-text); margin-top: 0; padding: 0.5rem;">
                        Rudisha Kifurushi Chako
                    </button>
                    
                    <div id="recover-section" style="display: {{ $errors->has('recover') ? 'block' : 'none' }}; margin-top: 1rem;">
                        <input type="tel" name="phone_recover" id="phone_recover" placeholder="Weka Namba (mf. 0712345678)" class="form-control" style="margin-bottom: 0.5rem;" oninput="document.getElementById('phone').value = this.value;">
                        <button type="submit" formnovalidate formaction="{{ route('hotspot.recover_package') }}" formmethod="POST" class="btn-submit" style="background-color: var(--info-text); margin-top: 0; padding: 0.5rem;">Thibitisha Namba</button>
                        @error('recover')
                            <div style="margin-top: 0.5rem; color: var(--error); font-weight: bold; font-size: 0.875rem;">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                @endif

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

        <div style="text-align: center; color: var(--text-muted); font-size: 0.875rem;">
            <p>Unahitaji msaada? Piga simu huduma kwa wateja:</p>
            <a href="tel:+255712402948" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; margin-top: 0.5rem; color: var(--primary); font-weight: bold; text-decoration: none; background: #e2e8f0; padding: 0.5rem 1rem; border-radius: 9999px; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#cbd5e1'" onmouseout="this.style.backgroundColor='#e2e8f0'">
                <svg style="width: 16px; height: 16px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.148a1.5 1.5 0 0 1 1.465 1.175l.716 3.223a1.5 1.5 0 0 1-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 0 0 6.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 0 1 1.767-1.052l3.223.716A1.5 1.5 0 0 1 18 15.352V16.5a1.5 1.5 0 0 1-1.5 1.5H15c-1.149 0-2.263-.15-3.326-.43A13.022 13.022 0 0 1 2.43 8.326 13.019 13.019 0 0 1 2 5V3.5Z" clip-rule="evenodd" />
                </svg>
                +255 712 402 948
            </a>
        </div>
    </div>

</body>
</html>