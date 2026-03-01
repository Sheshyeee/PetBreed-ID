import AuthLayout from '@/layouts/auth-layout';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, PawPrint, Shield, XCircle } from 'lucide-react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

type Errors = {
    flash?: { error?: string };
};

export default function Login({ status }: LoginProps) {
    const { flash } = usePage<Errors>().props;

    return (
        <AuthLayout
            title="Dog Breed Identification System"
            description="Sign in to access professional breed analysis"
        >
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes lv-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(1.7)} }
                @keyframes lv-ring  { 0%{transform:scale(.8);opacity:.65} 70%,100%{transform:scale(1.3);opacity:0} }
                @keyframes lv-paw   { 0%,100%{transform:rotate(0deg)} 30%{transform:rotate(-10deg)} 70%{transform:rotate(10deg)} }
                @keyframes lv-orb   { from{transform:rotate(0deg) translateX(30px) rotate(0deg)} to{transform:rotate(360deg) translateX(30px) rotate(-360deg)} }
                @keyframes lv-orb2  { from{transform:rotate(180deg) translateX(20px) rotate(-180deg)} to{transform:rotate(540deg) translateX(20px) rotate(-540deg)} }
                @keyframes lv-sweep { 0%{left:-80%} 100%{left:160%} }
                @keyframes lv-fade  { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
                @keyframes lv-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }

                .lv-f1 { animation:lv-fade .4s cubic-bezier(.16,1,.3,1) .05s both; }
                .lv-f2 { animation:lv-fade .4s cubic-bezier(.16,1,.3,1) .14s both; }
                .lv-f3 { animation:lv-fade .4s cubic-bezier(.16,1,.3,1) .22s both; }
                .lv-f4 { animation:lv-fade .4s cubic-bezier(.16,1,.3,1) .30s both; }

                /* ── CARD WRAPPER ── */
                .lv-card {
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    display: flex;
                    border-radius: 20px;
                    overflow: hidden;
                    box-shadow: 0 20px 60px rgba(0,0,0,.12);
                    border: 1px solid rgba(0,0,0,.07);
                    background: #fff;
                    min-height: 360px;
                }
                :is(.dark) .lv-card {
                    background: #0D1117;
                    border-color: rgba(255,255,255,.07);
                    box-shadow: 0 20px 60px rgba(0,0,0,.5);
                }

                /* ── LEFT VISUAL PANEL ── */
                .lv-left {
                    width: 200px;
                    flex-shrink: 0;
                    position: relative;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 28px 20px;
                    background: linear-gradient(160deg, #0f2b1e 0%, #0a1f2e 100%);
                }

                /* dot grid */
                .lv-left::before {
                    content: '';
                    position: absolute; inset: 0; pointer-events: none;
                    background-image: radial-gradient(circle, rgba(16,185,129,.12) 1px, transparent 1px);
                    background-size: 20px 20px;
                    -webkit-mask-image: radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%);
                    mask-image: radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%);
                }
                /* top beam */
                .lv-left-beam {
                    position: absolute; top: 0; left: 0; right: 0; height: 1.5px;
                    background: linear-gradient(90deg, transparent, #10b981 50%, transparent);
                    opacity: .6;
                }
                /* glow blobs */
                .lv-blob1 { position:absolute; top:-40px; left:-30px; width:160px; height:160px; border-radius:50%; background:rgba(16,185,129,.08); filter:blur(50px); pointer-events:none; }
                .lv-blob2 { position:absolute; bottom:-30px; right:-20px; width:120px; height:120px; border-radius:50%; background:rgba(6,182,212,.06); filter:blur(45px); pointer-events:none; }

                /* icon */
                .lv-icon-wrap {
                    position: relative;
                    width: 80px; height: 80px;
                    display: flex; align-items: center; justify-content: center;
                    margin-bottom: 20px;
                    animation: lv-float 4s ease-in-out infinite;
                }
                .lv-ring1 { position:absolute; inset:-10px; border-radius:50%; border:1px solid rgba(16,185,129,.2); animation:lv-ring 2.8s ease-out infinite; }
                .lv-ring2 { position:absolute; inset:-20px; border-radius:50%; border:1px solid rgba(16,185,129,.07); animation:lv-ring 2.8s ease-out infinite .9s; }
                .lv-orb  { position:absolute; width:6px; height:6px; border-radius:50%; top:50%; left:50%; margin:-3px 0 0 -3px; background:#10b981; box-shadow:0 0 10px #10b981; animation:lv-orb 3.4s linear infinite; }
                .lv-orb2 { position:absolute; width:4px; height:4px; border-radius:50%; top:50%; left:50%; margin:-2px 0 0 -2px; background:#06b6d4; box-shadow:0 0 8px #06b6d4; animation:lv-orb2 4s linear infinite; }
                .lv-icon-bg {
                    width: 80px; height: 80px; border-radius: 24px;
                    display: flex; align-items: center; justify-content: center;
                    border: 1px solid rgba(16,185,129,.25);
                    background: linear-gradient(135deg, rgba(16,185,129,.15), rgba(6,182,212,.07));
                    box-shadow: 0 0 30px rgba(16,185,129,.2), inset 0 0 20px rgba(16,185,129,.05);
                }

                /* left text */
                .lv-left-title {
                    font-size: 17px; font-weight: 800; color: #fff;
                    text-align: center; line-height: 1.25; letter-spacing: -.01em;
                    margin-bottom: 6px;
                }
                .lv-left-title span {
                    background: linear-gradient(135deg, #10b981, #06b6d4);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .lv-left-sub {
                    font-size: 10px; color: rgba(255,255,255,.38);
                    text-align: center; line-height: 1.55; max-width: 150px;
                }

                /* dots indicator (decorative) */
                .lv-dots { display:flex; gap:5px; margin-top:20px; }
                .lv-dot  { width:5px; height:5px; border-radius:50%; background:rgba(255,255,255,.2); }
                .lv-dot.active { background:#10b981; box-shadow:0 0 6px #10b981; width:16px; border-radius:99px; transition:all .3s; }

                /* ── RIGHT FORM ── */
                .lv-right {
                    flex: 1;
                    padding: 28px 26px;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    gap: 0;
                    background: #fff;
                }
                :is(.dark) .lv-right { background: #0D1117; }

                /* brand pill */
                .lv-brand {
                    display: inline-flex; align-items: center; gap: 5px;
                    padding: 3px 10px; border-radius: 99px;
                    border: 1px solid rgba(16,185,129,.2);
                    background: rgba(16,185,129,.07);
                    margin-bottom: 14px; width: fit-content;
                }
                .lv-brand-dot { width:5px; height:5px; border-radius:50%; background:#10b981; box-shadow:0 0 5px #10b981; animation:lv-pulse 2s ease-in-out infinite; }
                .lv-brand-text { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; letter-spacing:.12em; color:#10b981; text-transform:uppercase; }

                .lv-form-title {
                    font-size: 20px; font-weight: 800; letter-spacing: -.015em;
                    color: #0f172a; line-height: 1.2; margin-bottom: 4px;
                }
                :is(.dark) .lv-form-title { color: #f8fafc; }

                .lv-form-sub {
                    font-size: 12px; color: #94a3b8; line-height: 1.5; margin-bottom: 22px;
                }

                /* Google btn */
                .lv-google {
                    position: relative; overflow: hidden; width: 100%;
                    display: flex; align-items: center; justify-content: center; gap: 9px;
                    padding: 12px 18px; border-radius: 11px;
                    background: #fff;
                    border: 1.5px solid #e2e8f0;
                    color: #1e293b; font-size: 13px; font-weight: 700;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    cursor: pointer;
                    transition: transform .18s, box-shadow .18s, border-color .18s;
                    box-shadow: 0 1px 4px rgba(0,0,0,.07);
                    margin-bottom: 16px;
                }
                .lv-google:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(0,0,0,.1); border-color:#cbd5e1; }
                .lv-google:active { transform:translateY(0); }
                :is(.dark) .lv-google { background:#161d27; border-color:rgba(255,255,255,.09); color:#f1f5f9; box-shadow:none; }
                :is(.dark) .lv-google:hover { border-color:rgba(16,185,129,.3); box-shadow:0 6px 20px rgba(16,185,129,.08); }
                .lv-shine {
                    position:absolute; top:0; left:-80%; width:40%; height:100%;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);
                    transform:skewX(-18deg); pointer-events:none;
                }
                .lv-google:hover .lv-shine { animation:lv-sweep .5s ease forwards; }

                /* divider */
                .lv-divider { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
                .lv-div-line { flex:1; height:1px; background:#f1f5f9; }
                :is(.dark) .lv-div-line { background:rgba(255,255,255,.07); }
                .lv-div-text { font-family:'JetBrains Mono',monospace; font-size:9px; letter-spacing:.12em; color:#cbd5e1; text-transform:uppercase; white-space:nowrap; }
                :is(.dark) .lv-div-text { color:rgba(255,255,255,.2); }

                /* trust row */
                .lv-trust { display:flex; gap:8px; }
                .lv-trust-item {
                    flex:1; display:flex; align-items:center; gap:7px;
                    padding: 9px 10px; border-radius:10px;
                    border:1px solid #f1f5f9;
                    background:#fafafa;
                }
                :is(.dark) .lv-trust-item { border-color:rgba(255,255,255,.06); background:rgba(255,255,255,.025); }
                .lv-trust-ico {
                    width:24px; height:24px; border-radius:7px; flex-shrink:0;
                    display:flex; align-items:center; justify-content:center;
                }
                .lv-trust-name { font-size:11px; font-weight:700; color:#334155; line-height:1; }
                :is(.dark) .lv-trust-name { color:rgba(255,255,255,.75); }
                .lv-trust-sub  { font-size:9px; color:#94a3b8; margin-top:2px; }

                /* alerts */
                .lv-alert { display:flex; align-items:flex-start; gap:8px; padding:10px 12px; border-radius:10px; font-size:12px; font-weight:500; margin-bottom:12px; }
                .lv-ok  { border:1px solid rgba(16,185,129,.25); background:rgba(16,185,129,.07); color:#059669; }
                :is(.dark) .lv-ok { color:#6ee7b7; }
                .lv-err { border:1px solid rgba(239,68,68,.25); background:rgba(239,68,68,.07); color:#dc2626; }
                :is(.dark) .lv-err { color:#fca5a5; }

                /* responsive: stack on mobile */
                @media (max-width: 560px) {
                    .lv-card { flex-direction: column; }
                    .lv-left { width: 100%; min-height: 180px; padding: 24px 20px; flex-direction: row; gap: 16px; justify-content: flex-start; align-items: center; }
                    .lv-icon-wrap { margin-bottom: 0; width:60px; height:60px; flex-shrink:0; }
                    .lv-icon-bg { width:60px; height:60px; border-radius:18px; }
                    .lv-left-title { text-align:left; font-size:15px; }
                    .lv-left-sub { text-align:left; }
                    .lv-dots { margin-top:8px; }
                    .lv-right { padding: 22px 20px; }
                }
            `}</style>

            <div style={{ fontFamily: "'Plus Jakarta Sans', sans-serif" }}>
                {/* Alerts above card */}
                {status && (
                    <div
                        className="lv-alert lv-ok lv-f1"
                        style={{ marginBottom: 12 }}
                    >
                        <CheckCircle2
                            size={13}
                            style={{ flexShrink: 0, marginTop: 1 }}
                        />
                        {status}
                    </div>
                )}
                {flash?.error && (
                    <div
                        className="lv-alert lv-err lv-f1"
                        style={{ marginBottom: 12 }}
                    >
                        <XCircle
                            size={13}
                            style={{ flexShrink: 0, marginTop: 1 }}
                        />
                        {flash.error}
                    </div>
                )}

                <div className="lv-card">
                    {/* ── LEFT ── */}
                    <div className="lv-left">
                        <div className="lv-left-beam" />
                        <div className="lv-blob1" />
                        <div className="lv-blob2" />

                        {/* floating icon */}
                        <div className="lv-icon-wrap">
                            <div className="lv-ring1" />
                            <div className="lv-ring2" />
                            <div className="lv-orb" />
                            <div className="lv-orb2" />
                            <div className="lv-icon-bg">
                                <PawPrint
                                    size={32}
                                    style={{
                                        animation:
                                            'lv-paw 3s ease-in-out infinite',
                                        color: '#10b981',
                                    }}
                                />
                            </div>
                        </div>

                        <div>
                            <p className="lv-left-title">
                                Identify any
                                <br />
                                <span>dog breed</span>
                            </p>
                            <p className="lv-left-sub">
                                Upload a photo, get results instantly with
                                health insights
                            </p>
                            <div className="lv-dots">
                                <div className="lv-dot active" />
                                <div className="lv-dot" />
                                <div className="lv-dot" />
                            </div>
                        </div>
                    </div>

                    {/* ── RIGHT ── */}
                    <div className="lv-right">
                        <div className="lv-brand lv-f1">
                            <span className="lv-brand-dot" />
                            <span className="lv-brand-text">DogLens</span>
                        </div>

                        <p className="lv-form-title lv-f2">Welcome back</p>
                        <p className="lv-form-sub lv-f2">
                            Sign in to access your breed analysis
                        </p>

                        <button
                            onClick={() =>
                                (window.location.href = '/auth/google')
                            }
                            className="lv-google lv-f3"
                        >
                            <span className="lv-shine" />
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg"
                                style={{ flexShrink: 0 }}
                            >
                                <path
                                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                    fill="#4285F4"
                                />
                                <path
                                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                    fill="#34A853"
                                />
                                <path
                                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                    fill="#FBBC05"
                                />
                                <path
                                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                    fill="#EA4335"
                                />
                            </svg>
                            Continue with Google
                        </button>

                        <div className="lv-divider lv-f3">
                            <div className="lv-div-line" />
                            <span className="lv-div-text">
                                secured · oauth 2.0
                            </span>
                            <div className="lv-div-line" />
                        </div>

                        <div className="lv-trust lv-f4">
                            {[
                                {
                                    icon: <Shield size={13} color="#10b981" />,
                                    bg: 'rgba(16,185,129,.1)',
                                    name: 'Secure Login',
                                    sub: 'OAuth 2.0',
                                },
                                {
                                    icon: (
                                        <CheckCircle2
                                            size={13}
                                            color="#06b6d4"
                                        />
                                    ),
                                    bg: 'rgba(6,182,212,.1)',
                                    name: 'Vet Verified',
                                    sub: 'Licensed review',
                                },
                            ].map((c, i) => (
                                <div className="lv-trust-item" key={i}>
                                    <div
                                        className="lv-trust-ico"
                                        style={{ background: c.bg }}
                                    >
                                        {c.icon}
                                    </div>
                                    <div>
                                        <div className="lv-trust-name">
                                            {c.name}
                                        </div>
                                        <div className="lv-trust-sub">
                                            {c.sub}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
