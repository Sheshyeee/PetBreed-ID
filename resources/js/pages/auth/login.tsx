import AuthLayout from '@/layouts/auth-layout';

import { usePage } from '@inertiajs/react';

import { CheckCircle2, PawPrint, XCircle } from 'lucide-react';

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
        <AuthLayout title="" description="">
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap');

                /* ── HIDE AUTH LAYOUT'S TOP LOGO BLOCK ── */
                .lv-kill-header {
                    display: none !important;
                    visibility: hidden !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                @keyframes lv-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(1.5)} }
                @keyframes lv-sweep { 0%{left:-80%} 100%{left:160%} }
                @keyframes lv-fade  { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

                .lv-f1 { animation:lv-fade .4s cubic-bezier(.16,1,.3,1) .05s both; }
                .lv-f2 { animation:lv-fade .4s cubic-bezier(.16,1,.3,1) .14s both; }
                .lv-f3 { animation:lv-fade .4s cubic-bezier(.16,1,.3,1) .22s both; }
                .lv-f4 { animation:lv-fade .4s cubic-bezier(.16,1,.3,1) .30s both; }

                .lv-root { font-family:'Plus Jakarta Sans',sans-serif; }

                /* ── CARD — wider to prevent text cutoff ── */
                .lv-card {
                    display: flex;
                    border-radius: 18px;
                    overflow: hidden;
                    border: 1px solid rgba(0,0,0,.08);
                    box-shadow: 0 16px 48px rgba(0,0,0,.1);
                    background: #fff;
                    width: 100%;
                    max-width: 760px; /* ✅ increased from 660px */
                    margin: 0 auto;
                }
                :is(.dark) .lv-card {
                    background: #0D1117;
                    border-color: rgba(255,255,255,.07);
                    box-shadow: 0 20px 60px rgba(0,0,0,.6);
                }

                /* ── LEFT — slightly narrower to give right more room ── */
                .lv-left {
                    width: 160px; /* ✅ reduced from 170px */
                    flex-shrink: 0;
                    position: relative;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 32px 16px;
                    gap: 18px;
                    background: #09201a;
                }
                .lv-left::before {
                    content:'';
                    position:absolute; inset:0; pointer-events:none;
                    background-image:radial-gradient(circle, rgba(16,185,129,.10) 1px, transparent 1px);
                    background-size:20px 20px;
                    -webkit-mask-image:radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%);
                    mask-image:radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%);
                }
                .lv-left-beam {
                    position:absolute; top:0; left:0; right:0; height:1px;
                    background:rgba(16,185,129,.30);
                }
                .lv-blob1 { position:absolute; top:-40px; left:-30px; width:150px; height:150px; border-radius:50%; background:rgba(16,185,129,.05); filter:blur(48px); pointer-events:none; }
                .lv-blob2 { position:absolute; bottom:-30px; right:-20px; width:110px; height:110px; border-radius:50%; background:rgba(6,182,212,.04); filter:blur(42px); pointer-events:none; }

                /* icon */
                .lv-icon-wrap {
                    position:relative; width:76px; height:76px;
                    display:flex; align-items:center; justify-content:center;
                }
                .lv-ring1 { position:absolute; inset:-10px; border-radius:50%; border:1px solid rgba(16,185,129,.18); }
                .lv-ring2 { position:absolute; inset:-20px; border-radius:50%; border:1px solid rgba(16,185,129,.06); }
                .lv-icon-bg {
                    width:76px; height:76px; border-radius:22px;
                    display:flex; align-items:center; justify-content:center;
                    border:1px solid rgba(16,185,129,.20);
                    background:rgba(16,185,129,.09);
                }

                /* left text */
                .lv-left-body { text-align:center; }
                .lv-left-title {
                    font-size:15px; font-weight:700; color:#fff;
                    line-height:1.3; letter-spacing:-.01em; margin-bottom:6px;
                }
                .lv-left-title span { color:#10b981; }
                .lv-left-sub {
                    font-size:10px; color:rgba(255,255,255,.30);
                    line-height:1.6; max-width:140px; margin:0 auto;
                }

                /* dots */
                .lv-dots { display:flex; gap:5px; justify-content:center; margin-top:16px; }
                .lv-dot  { width:5px; height:5px; border-radius:99px; background:rgba(255,255,255,.14); }
                .lv-dot.on { width:16px; background:#10b981; }

                /* ── RIGHT — more horizontal padding, no min-width squeeze ── */
                .lv-right {
                    flex: 1;
                    min-width: 0;
                    padding: 32px 36px; /* ✅ increased horizontal padding from 28px → 36px */
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    gap: 14px;
                    background: #fff;
                }
                :is(.dark) .lv-right { background:#0D1117; }

                /* brand */
                .lv-brand {
                    display:inline-flex; align-items:center; gap:5px;
                    padding:3px 10px; border-radius:99px;
                    border:1px solid rgba(16,185,129,.18);
                    background:rgba(16,185,129,.06);
                    width:fit-content;
                }
                .lv-brand-dot { width:5px; height:5px; border-radius:50%; background:#10b981; animation:lv-pulse 2s ease-in-out infinite; }
                .lv-brand-text { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; letter-spacing:.12em; color:#10b981; text-transform:uppercase; }

                .lv-title { font-size:21px; font-weight:800; letter-spacing:-.02em; color:#0f172a; line-height:1.2; }
                :is(.dark) .lv-title { color:#f8fafc; }
                .lv-sub { font-size:11px; color:#94a3b8; line-height:1.5; margin-top:3px; }

                /* Google btn */
                .lv-google {
                    position:relative; overflow:hidden; width:100%;
                    display:flex; align-items:center; justify-content:center; gap:9px;
                    padding:13px 16px; border-radius:11px;
                    background:#fff; border:1.5px solid #e2e8f0;
                    color:#1e293b; font-size:13px; font-weight:600;
                    font-family:'Plus Jakarta Sans',sans-serif;
                    cursor:pointer;
                    white-space:nowrap;
                    transition:transform .17s, box-shadow .17s, border-color .17s;
                    box-shadow:0 1px 3px rgba(0,0,0,.05);
                }
                .lv-google:hover { transform:translateY(-1px); box-shadow:0 4px 16px rgba(0,0,0,.09); border-color:#cbd5e1; }
                .lv-google:active { transform:translateY(0); }
                :is(.dark) .lv-google { background:#111827; border-color:rgba(255,255,255,.08); color:#f1f5f9; box-shadow:none; }
                :is(.dark) .lv-google:hover { border-color:rgba(16,185,129,.22); }
                .lv-shine { position:absolute; top:0; left:-80%; width:40%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent); transform:skewX(-18deg); pointer-events:none; }
                .lv-google:hover .lv-shine { animation:lv-sweep .5s ease forwards; }

                /* divider */
                .lv-divider { display:flex; align-items:center; gap:10px; }
                .lv-div-line { flex:1; height:1px; background:#f1f5f9; }
                :is(.dark) .lv-div-line { background:rgba(255,255,255,.06); }
                .lv-div-text { font-family:'JetBrains Mono',monospace; font-size:8px; letter-spacing:.12em; color:#cbd5e1; text-transform:uppercase; white-space:nowrap; }
                :is(.dark) .lv-div-text { color:rgba(255,255,255,.14); }

                /* footer note */
                .lv-note { font-size:10px; color:#94a3b8; text-align:center; line-height:1.5; }
                :is(.dark) .lv-note { color:rgba(255,255,255,.20); }

                /* alerts */
                .lv-alert { display:flex; align-items:flex-start; gap:8px; padding:10px 12px; border-radius:10px; font-size:12px; font-weight:500; margin-bottom:10px; }
                .lv-ok  { border:1px solid rgba(16,185,129,.22); background:rgba(16,185,129,.06); color:#059669; }
                :is(.dark) .lv-ok { color:#6ee7b7; }
                .lv-err { border:1px solid rgba(239,68,68,.22); background:rgba(239,68,68,.06); color:#dc2626; }
                :is(.dark) .lv-err { color:#fca5a5; }

                /* mobile */
                @media (max-width:540px) {
                    .lv-card { flex-direction:column; }
                    .lv-left { width:100%; flex-direction:row; padding:20px 18px; gap:14px; justify-content:flex-start; min-height:auto; }
                    .lv-left-body { text-align:left; }
                    .lv-left-sub { margin:0; }
                    .lv-dots { justify-content:flex-start; }
                    .lv-right { padding:22px 24px; }
                }
            `}</style>

            <div
                style={{ display: 'none' }}
                ref={(el) => {
                    if (!el) return;
                    const parent =
                        el.closest('form, [class]')?.parentElement ??
                        el.parentElement;
                    if (!parent) return;
                    Array.from(parent.children).forEach((child) => {
                        const el2 = child as HTMLElement;
                        if (
                            !el2.classList.contains('lv-root') &&
                            !el2.querySelector('.lv-root') &&
                            !el2.querySelector('.lv-alert')
                        ) {
                            el2.style.cssText = 'display:none!important';
                        }
                    });
                }}
            />

            <div className="lv-root">
                {status && (
                    <div className="lv-alert lv-ok lv-f1">
                        <CheckCircle2
                            size={13}
                            style={{ flexShrink: 0, marginTop: 1 }}
                        />
                        {status}
                    </div>
                )}
                {flash?.error && (
                    <div className="lv-alert lv-err lv-f1">
                        <XCircle
                            size={13}
                            style={{ flexShrink: 0, marginTop: 1 }}
                        />
                        {flash.error}
                    </div>
                )}

                <div className="lv-card">
                    {/* LEFT */}
                    <div className="lv-left">
                        <div className="lv-left-beam" />
                        <div className="lv-blob1" />
                        <div className="lv-blob2" />

                        <div className="lv-icon-wrap">
                            <div className="lv-ring1" />
                            <div className="lv-ring2" />
                            <div className="lv-icon-bg">
                                <PawPrint
                                    size={30}
                                    style={{ color: '#10b981' }}
                                />
                            </div>
                        </div>

                        <div className="lv-left-body">
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
                                <div className="lv-dot on" />
                                <div className="lv-dot" />
                                <div className="lv-dot" />
                            </div>
                        </div>
                    </div>

                    {/* RIGHT */}
                    <div className="lv-right">
                        <div className="lv-brand lv-f1">
                            <span className="lv-brand-dot" />
                            <span className="lv-brand-text">DogLens</span>
                        </div>

                        <div className="lv-f2">
                            <p className="lv-title">Welcome back</p>
                            <p className="lv-sub">
                                Sign in to access your breed analysis
                            </p>
                        </div>

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

                        <div className="lv-divider lv-f4">
                            <div className="lv-div-line" />
                            <span className="lv-div-text">
                                secured · oauth 2.0
                            </span>
                            <div className="lv-div-line" />
                        </div>

                        <p className="lv-note lv-f4">
                            By signing in you agree to our terms &amp; privacy
                            policy
                        </p>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
