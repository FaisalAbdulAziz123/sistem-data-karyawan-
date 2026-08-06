<?php session_start(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Karyawan</title>
    <style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

/* ============================================================
   DESIGN TOKENS — Sistem Kepegawaian
   Palette dipilih untuk kesan HR yang tenang, profesional, dan
   dapat dipercaya: indigo gelap sebagai identitas utama,
   amber hangat sebagai aksen "diverifikasi / aktif".
   ============================================================ */
:root {
    --ink: #161B33;
    --primary: #2E3A8F;
    --primary-dark: #141A45;
    --primary-soft: #4C5AC2;
    --accent: #E8A33D;
    --accent-soft: #FBEBD2;
    --bg: #F6F7FB;
    --surface: #FFFFFF;
    --muted: #6B7290;
    --border: #E3E5F2;
    --error-bg: #FDECEC;
    --error-text: #B23A3A;
    --radius-lg: 22px;
    --radius-md: 12px;
    --radius-sm: 8px;
    --shadow-card: 0 24px 48px -20px rgba(20, 26, 69, 0.28);
    --font-display: 'Sora', 'Segoe UI', sans-serif;
    --font-body: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
}

* { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    font-family: var(--font-body);
    background: var(--bg);
    color: var(--ink);
}

/* ============================================================
   SHELL — split screen: panel identitas (kiri) + panel form (kanan)
   ============================================================ */
.auth-shell {
    height: 100vh;
    overflow: hidden;
    display: grid;
    grid-template-columns: 1.05fr 1fr;
}

.auth-shell.reverse { grid-template-columns: 1fr 1.05fr; }
.auth-shell.reverse .auth-panel { order: 2; }
.auth-shell.reverse .auth-form-side { order: 1; }

/* ---------- Panel identitas (branding + ilustrasi) ---------- */
.auth-panel {
    position: relative;
    height: 100%;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 48px 52px;
    color: #F2F3FC;
    background:
        radial-gradient(circle at 12% 12%, rgba(255,255,255,0.10) 0, transparent 42%),
        linear-gradient(155deg, var(--primary-dark) 0%, var(--primary) 55%, var(--primary-soft) 100%);
}

.auth-panel::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255,255,255,0.14) 1.4px, transparent 1.4px);
    background-size: 26px 26px;
    opacity: 0.35;
    pointer-events: none;
}

.auth-panel .panel-top,
.auth-panel .panel-bottom,
.auth-panel .panel-mid {
    position: relative;
    z-index: 1;
}

.panel-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--accent-soft);
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    padding: 7px 14px;
    border-radius: 999px;
    margin-bottom: 22px;
}

.panel-eyebrow .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--accent);
}

.brand-mark {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 1.05rem;
    letter-spacing: 0.01em;
}

.brand-mark .mark {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
}

.panel-headline {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 2.15rem;
    line-height: 1.18;
    margin: 0 0 16px;
    max-width: 420px;
}

.panel-sub {
    font-size: 0.98rem;
    line-height: 1.6;
    color: rgba(242, 243, 252, 0.78);
    max-width: 380px;
    margin: 0 0 32px;
}

.panel-features {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.panel-features li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 0.92rem;
    color: rgba(242, 243, 252, 0.9);
}

.panel-features .feat-icon {
    flex: none;
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: rgba(232, 163, 61, 0.18);
    border: 1px solid rgba(232, 163, 61, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
}

.panel-illustration {
    display: flex;
    justify-content: center;
    margin: 8px 0;
}

.panel-illustration svg { width: 100%; max-width: 250px; height: auto; }

.float-a, .float-b { animation: floaty 6s ease-in-out infinite; }
.float-b { animation-delay: -3s; }

@keyframes floaty {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-7px); }
}

.panel-quote {
    font-size: 0.82rem;
    color: rgba(242, 243, 252, 0.6);
    border-top: 1px solid rgba(255, 255, 255, 0.16);
    padding-top: 16px;
}

/* ---------- Panel form ---------- */
.auth-form-side {
    height: 100%;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 64px 32px 40px;
    background: var(--bg);
}

.auth-card {
    width: 100%;
    max-width: 400px;
}

.auth-card .card-head { margin-bottom: 28px; }

.card-head .title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 1.6rem;
    margin: 0 0 6px;
    color: var(--ink);
}

.card-head .subtitle {
    font-size: 0.92rem;
    color: var(--muted);
    margin: 0;
}

/* ---------- Alert ---------- */
.alert-error {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    background: var(--error-bg);
    color: var(--error-text);
    border: 1px solid rgba(178, 58, 58, 0.25);
    padding: 12px 14px;
    border-radius: var(--radius-sm);
    font-size: 0.87rem;
    margin-bottom: 20px;
}

/* ---------- Segmented role toggle ---------- */
.role-toggle {
    position: relative;
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: #EEF0FA;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 4px;
    margin-bottom: 22px;
}

.role-toggle input { position: absolute; opacity: 0; pointer-events: none; }

.role-toggle label {
    position: relative;
    z-index: 1;
    text-align: center;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 9px 8px;
    border-radius: 999px;
    color: var(--muted);
    cursor: pointer;
    transition: color 0.25s ease;
    user-select: none;
}

.role-toggle::after {
    content: "";
    position: absolute;
    top: 4px;
    left: 4px;
    width: calc(50% - 4px);
    height: calc(100% - 8px);
    border-radius: 999px;
    background: var(--surface);
    box-shadow: 0 6px 14px -6px rgba(20, 26, 69, 0.35);
    transition: transform 0.28s cubic-bezier(.4,0,.2,1);
}

#role-admin:checked ~ .role-toggle-track::after { transform: translateX(100%); }
.role-toggle:has(#role-admin:checked)::after { transform: translateX(100%); }
.role-toggle:has(#role-admin:checked) label[for="role-admin"] { color: var(--primary); }
.role-toggle:has(#role-karyawan:checked) label[for="role-karyawan"] { color: var(--primary); }

/* ---------- Form fields ---------- */
.form-group { margin-bottom: 18px; }

.form-group label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 7px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 0.94rem;
    font-family: var(--font-body);
    background: var(--surface);
    color: var(--ink);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-group input::placeholder { color: #A7ABC3; }

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary-soft);
    box-shadow: 0 0 0 3px rgba(76, 90, 194, 0.18);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.field-hint {
    font-size: 0.76rem;
    color: var(--muted);
    margin-top: 6px;
}

/* ---------- Button ---------- */
.btn-auth {
    width: 100%;
    padding: 13px 16px;
    border: none;
    border-radius: var(--radius-sm);
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-soft) 100%);
    color: #fff;
    font-family: var(--font-body);
    font-weight: 700;
    font-size: 0.95rem;
    letter-spacing: 0.01em;
    cursor: pointer;
    box-shadow: 0 14px 28px -12px rgba(46, 58, 143, 0.55);
    transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
    margin-top: 6px;
}

.btn-auth:hover { transform: translateY(-1px); filter: brightness(1.05); }
.btn-auth:active { transform: translateY(0); }
.btn-auth:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }

/* ---------- Footer links ---------- */
.auth-foot {
    margin-top: 22px;
    text-align: center;
    font-size: 0.87rem;
    color: var(--muted);
}

.auth-foot a {
    color: var(--primary);
    font-weight: 700;
    text-decoration: none;
}

.auth-foot a:hover { text-decoration: underline; }

.auth-copyright {
    margin-top: 34px;
    text-align: center;
    font-size: 0.75rem;
    color: #A7ABC3;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 900px) {
    html, body { height: auto; }
    .auth-shell,
    .auth-shell.reverse {
        grid-template-columns: 1fr;
        height: auto;
        overflow: visible;
    }
    .auth-shell.reverse .auth-panel { order: 1; }
    .auth-shell.reverse .auth-form-side { order: 2; }
    .auth-panel {
        height: auto;
        overflow: visible;
        padding: 36px 28px;
        min-height: 220px;
    }
    .panel-headline { font-size: 1.7rem; }
    .panel-features { display: none; }
    .panel-illustration { display: none; }
    .panel-quote { display: none; }
    .auth-form-side {
        height: auto;
        overflow: visible;
        padding: 34px 22px 46px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .float-a, .float-b { animation: none; }
}

    </style>
</head>
<body>

<div class="auth-shell">

    <!-- ================= PANEL IDENTITAS (KIRI) ================= -->
    <div class="auth-panel">
        <div class="panel-top">
            <div class="brand-mark">
                <span class="mark">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2l8 4v6c0 5-3.4 8.6-8 10-4.6-1.4-8-5-8-10V6l8-4z" stroke="#F2F3FC" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M9 12l2 2 4-4" stroke="#E8A33D" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                Sistem Karyawan
            </div>
        </div>

        <div class="panel-mid">
            <div class="panel-eyebrow"><span class="dot"></span> Pengelolaan Data Kepegawaian</div>
            <h1 class="panel-headline">Satu Portal untuk Seluruh Urusan Kepegawaian</h1>
            <p class="panel-sub">Masuk untuk mengakses data absensi, riwayat kerja, dan informasi kepegawaian secara terpusat dan aman.</p>

            <div class="panel-illustration">
                <!-- Ilustrasi signature: kartu identitas terverifikasi -->
                <svg viewBox="0 0 240 220" xmlns="http://www.w3.org/2000/svg">
                    <g class="float-a">
                        <rect x="30" y="20" width="150" height="100" rx="14" fill="rgba(255,255,255,0.10)" stroke="rgba(255,255,255,0.35)" stroke-width="1.2"/>
                        <circle cx="62" cy="55" r="16" fill="rgba(255,255,255,0.22)"/>
                        <rect x="90" y="44" width="70" height="7" rx="3.5" fill="rgba(255,255,255,0.35)"/>
                        <rect x="90" y="58" width="50" height="6" rx="3" fill="rgba(255,255,255,0.22)"/>
                        <rect x="48" y="82" width="112" height="5" rx="2.5" fill="rgba(255,255,255,0.16)"/>
                        <rect x="48" y="94" width="80" height="5" rx="2.5" fill="rgba(255,255,255,0.16)"/>
                    </g>
                    <g class="float-b">
                        <circle cx="178" cy="118" r="30" fill="#E8A33D"/>
                        <path d="M164 118l9 9 17-19" stroke="#141A45" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    </g>
                    <rect x="46" y="150" width="148" height="34" rx="10" fill="rgba(255,255,255,0.08)" stroke="rgba(255,255,255,0.2)"/>
                    <rect x="60" y="163" width="60" height="6" rx="3" fill="rgba(255,255,255,0.3)"/>
                    <rect x="60" y="163" width="60" height="6" rx="3" fill="rgba(255,255,255,0.3)" transform="translate(0,0)"/>
                    <circle cx="168" cy="167" r="7" fill="rgba(255,255,255,0.3)"/>
                </svg>
            </div>

            <ul class="panel-features">
                <li>
                    <span class="feat-icon">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 6v6l4 2" stroke="#E8A33D" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="#E8A33D" stroke-width="1.6"/></svg>
                    </span>
                    Absensi &amp; jam kerja tercatat secara real-time
                </li>
                <li>
                    <span class="feat-icon">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="16" rx="3" stroke="#E8A33D" stroke-width="1.6"/><path d="M8 9h8M8 13h5" stroke="#E8A33D" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                    Seluruh data karyawan tersimpan dalam satu sistem
                </li>
                <li>
                    <span class="feat-icon">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 3l7 3v5c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3z" stroke="#E8A33D" stroke-width="1.6" stroke-linejoin="round"/></svg>
                    </span>
                    Akses dibedakan berdasarkan peran, karyawan &amp; admin
                </li>
            </ul>
        </div>

        <div class="panel-bottom">
            <p class="panel-quote">Dipercaya untuk mengelola data kepegawaian secara harian oleh tim HRD.</p>
        </div>
    </div>

    <!-- ================= PANEL FORM (KANAN) ================= -->
    <div class="auth-form-side">
        <div class="auth-card">
            <div class="card-head">
                <h2 class="title">Selamat Datang Kembali</h2>
                <p class="subtitle">Masuk ke akun Anda untuk melanjutkan.</p>
            </div>

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="flex:none;margin-top:1px"><circle cx="12" cy="12" r="9" stroke="#B23A3A" stroke-width="1.6"/><path d="M12 8v5M12 16h.01" stroke="#B23A3A" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <form action="proses_login.php" method="POST">

                <label style="display:block;font-size:0.82rem;font-weight:600;color:var(--ink);margin-bottom:7px;">Login Sebagai</label>
                <div class="role-toggle">
                    <input type="radio" id="role-karyawan" name="role" value="karyawan" checked>
                    <input type="radio" id="role-admin" name="role" value="admin">
                    <label for="role-karyawan">Karyawan</label>
                    <label for="role-admin">Administrator / HRD</label>
                </div>

                <div class="form-group">
                    <label for="username">Username / NIP / Email</label>
                    <input type="text" id="username" name="username" placeholder="Masukkan username, NIP, atau email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                </div>

                <button class="btn-auth" type="submit">Masuk</button>
            </form>

            <div class="auth-foot">
                Belum punya akun Admin? <a href="register.php">Daftar Admin</a>
            </div>
        </div>

        <div class="auth-copyright">&copy; <?= date('Y') ?> Pengelolaan Data Karyawan</div>
    </div>

</div>

</body>
</html>