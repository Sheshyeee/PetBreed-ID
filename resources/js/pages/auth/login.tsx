import { usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    PawPrint,
    Shield,
    Sparkles,
    XCircle,
    Zap,
} from 'lucide-react';

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
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap');

                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

                @keyframes lg-pulse  { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(1.6)} }
                @keyframes lg-ring   { 0%{transform:scale(.82);opacity:.65} 70%,100%{transform:scale(1.24);opacity:0} }
                @keyframes lg-paw    { 0%,100%{transform:rotate(0deg) scale(1)} 25%{transform:rotate(-10deg) scale(1.05)} 75%{transform:rotate(10deg) scale(1.05)} }
                @keyframes lg-fade   { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
                @keyframes lg-sweep  { 0%{left:-80%} 100%{left:160%} }
                @keyframes lg-float  { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-7px)} }
                @keyframes lg-beam   { from{top:-3px} to{top:calc(100%+3px)} }
                @keyframes lg-dot    { 0%,100%{opacity:.3} 50%{opacity:1} }
                @keyframes lg-orb    { from{transform:rotate(0deg) translateX(38px) rotate(0deg)} to{transform:rotate(360deg) translateX(38px) rotate(-360deg)} }
                @keyframes lg-orb2   { from{transform:rotate(180deg) translateX(28px) rotate(-180deg)} to{transform:rotate(540deg) translateX(28px) rotate(-540deg)} }
                @keyframes lg-bgmove { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
                @keyframes lg-stat   { from{opacity:0;transform:translateX(-6px)} to{opacity:1;transform:translateX(0)} }

                .lg-root {
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    min-height: 100vh;
                    display: flex;
                    align-items: stretch;
                    background: #080B0F;
                }

                .lg-mono { font-family: 'JetBrains Mono', monospace !important; }

                /* ── LEFT PANEL ── */
                .lg-left {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: flex-start;
                    padding: 60px 56px;
                    position: relative;
                    overflow: hidden;
                    border-right: 1px solid rgba(255,255,255,.06);
                }

                .lg-left-bg {
                    position: absolute; inset: 0; pointer-events: none;
                    background-image: radial-gradient(circle, rgba(16,185,129,.065) 1px, transparent 1px);
                    background-size: 26px 26px;
                    -webkit-mask-image: radial-gradient(ellipse 90% 80% at 30% 40%, black 0%, transparent 100%);
                    mask-image: radial-gradient(ellipse 90% 80% at 30% 40%, black 0%, transparent 100%);
                }
                .lg-glow1 { position:absolute; top:-80px; left:-60px; width:320px; height:320px; border-radius:50%; background:rgba(16,185,129,.055); filter:blur(80px); pointer-events:none; }
                .lg-glow2 { position:absolute; bottom:-60px; right:20px; width:220px; height:220px; border-radius:50%; background:rgba(6,182,212,.035); filter:blur(70px); pointer-events:none; }

                .lg-left-content { position: relative; z-index: 2; width: 100%; }

                /* Icon cluster */
                .lg-icon-wrap {
                    position: relative;
                    width: 88px; height: 88px;
                    display: flex; align-items: center; justify-content: center;
                    margin-bottom: 36px;
                }
                .lg-icon-ring1 { position:absolute; inset:-10px; border-radius:50%; border:1px solid rgba(16,185,129,.18); animation:lg-ring 2.8s ease-out infinite; }
                .lg-icon-ring2 { position:absolute; inset:-22px; border-radius:50%; border:1px solid rgba(16,185,129,.07); animation:lg-ring 2.8s ease-out infinite .9s; }
                .lg-orb-dot  { position:absolute; width:7px; height:7px; border-radius:50%; top:50%; left:50%; margin:-3.5px 0 0 -3.5px; background:#10b981; box-shadow:0 0 10px #10b981; animation:lg-orb 3.5s linear infinite; }
                .lg-orb-dot2 { position:absolute; width:5px; height:5px; border-radius:50%; top:50%; left:50%; margin:-2.5px 0 0 -2.5px; background:#06b6d4; box-shadow:0 0 8px #06b6d4; animation:lg-orb2 4s linear infinite; }
                .lg-icon-center {
                    position: relative;
                    width: 88px; height: 88px;
                    border-radius: 24px;
                    border: 1px solid rgba(16,185,129,.25);
                    background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(6,182,212,.06));
                    display: flex; align-items: center; justify-content: center;
                    box-shadow: 0 0 30px rgba(16,185,129,.18), inset 0 0 20px rgba(16,185,129,.05);
                }
                .lg-paw-icon { animation: lg-paw 3s ease-in-out infinite; color: #10b981; }

                /* Beam on left panel top */
                .lg-left-beam {
                    position: absolute; top: 0; left: 0; right: 0; height: 1.5px;
                    background: linear-gradient(90deg, transparent, #10b981 40%, #06b6d4 60%, transparent);
                    opacity: .45;
                }

                .lg-title {
                    font-size: 36px; font-weight: 900; line-height: 1.15;
                    letter-spacing: -.02em; color: #fff; margin-bottom: 12px;
                }
                .lg-title-accent {
                    background: linear-gradient(135deg, #10b981, #06b6d4);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .lg-subtitle { font-size: 14px; color: rgba(255,255,255,.45); line-height: 1.6; margin-bottom: 36px; max-width: 300px; }

                /* Stat pills */
                .lg-stats { display: flex; flex-direction: column; gap: 10px; }
                .lg-stat {
                    display: flex; align-items: center; gap: 12px;
                    padding: 12px 14px;
                    border-radius: 12px;
                    border: 1px solid rgba(255,255,255,.055);
                    background: rgba(255,255,255,.025);
                    animation: lg-stat .4s cubic-bezier(.16,1,.3,1) both;
                }
                .lg-stat:nth-child(1) { animation-delay: .1s; }
                .lg-stat:nth-child(2) { animation-delay: .2s; }
                .lg-stat:nth-child(3) { animation-delay: .3s; }
                .lg-stat-icon {
                    width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
                    display: flex; align-items: center; justify-content: center;
                    border: 1px solid rgba(255,255,255,.07);
                }
                .lg-stat-val { font-size: 15px; font-weight: 800; color: #fff; line-height: 1; }
                .lg-stat-label { font-size: 11px; color: rgba(255,255,255,.4); margin-top: 2px; }

                /* ── RIGHT PANEL ── */
                .lg-right {
                    width: 420px; flex-shrink: 0;
                    display: flex; flex-direction: column;
                    justify-content: center;
                    padding: 52px 44px;
                    position: relative;
                    background: #0D1117;
                }

                .lg-right-beam {
                    position: absolute; top: 0; left: 0; right: 0; height: 1.5px;
                    background: linear-gradient(90deg, transparent, rgba(16,185,129,.3) 50%, transparent);
                    opacity: .6;
                }

                .lg-form-title { font-size: 22px; font-weight: 800; color: #fff; letter-spacing: -.01em; margin-bottom: 4px; }
                .lg-form-sub { font-size: 13px; color: rgba(255,255,255,.38); margin-bottom: 28px; }

                /* Google button */
                .lg-google-btn {
                    position: relative; overflow: hidden; width: 100%;
                    display: flex; align-items: center; justify-content: center; gap: 10px;
                    padding: 14px 20px; border-radius: 12px;
                    background: #fff; border: none;
                    color: #1e293b; font-size: 14px; font-weight: 700;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    cursor: pointer;
                    transition: transform .18s, box-shadow .18s;
                    box-shadow: 0 2px 12px rgba(0,0,0,.18);
                }
                .lg-google-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(0,0,0,.28), 0 0 0 1px rgba(16,185,129,.2); }
                .lg-google-btn:active { transform: translateY(0); }
                .lg-google-shine {
                    position: absolute; top: 0; left: -80%; width: 40%; height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,.5), transparent);
                    transform: skewX(-18deg); pointer-events: none;
                }
                .lg-google-btn:hover .lg-google-shine { animation: lg-sweep .55s ease forwards; }

                .lg-divider { display:flex; align-items:center; gap:12px; margin: 22px 0; }
                .lg-divider-line { flex:1; height:1px; background:rgba(255,255,255,.07); }
                .lg-divider-text { font-size: 9px; letter-spacing: .14em; color: rgba(255,255,255,.22); text-transform: uppercase; font-family: 'JetBrains Mono', monospace; white-space: nowrap; }

                /* Trust cards */
                .lg-trust { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                .lg-trust-card {
                    display: flex; align-items: center; gap: 10px;
                    padding: 11px 13px; border-radius: 11px;
                    border: 1px solid rgba(255,255,255,.06);
                    background: rgba(255,255,255,.025);
                }
                .lg-trust-icon {
                    width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0;
                    display: flex; align-items: center; justify-content: center;
                    border: 1px solid rgba(255,255,255,.07);
                    background: rgba(255,255,255,.04);
                }
                .lg-trust-name { font-size: 11px; font-weight: 700; color: rgba(255,255,255,.8); line-height: 1; }
                .lg-trust-sub  { font-size: 9px; color: rgba(255,255,255,.3); margin-top: 2px; }

                /* Status badge */
                .lg-badge {
                    display: inline-flex; align-items: center; gap: 6px;
                    padding: 5px 12px; border-radius: 99px;
                    border: 1px solid rgba(16,185,129,.2);
                    background: rgba(16,185,129,.08);
                    margin-bottom: 20px;
                }
                .lg-badge-dot { width:6px; height:6px; border-radius:50%; background:#10b981; box-shadow:0 0 6px #10b981; animation:lg-pulse 2s ease-in-out infinite; }
                .lg-badge-text { font-size: 10px; font-weight: 600; letter-spacing: .12em; color: #10b981; text-transform: uppercase; font-family: 'JetBrains Mono', monospace; }

                /* Alerts */
                .lg-alert { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:11px; margin-bottom:16px; font-size:13px; font-weight:500; }
                .lg-alert-ok { border:1px solid rgba(16,185,129,.25); background:rgba(16,185,129,.08); color:#6ee7b7; }
                .lg-alert-err { border:1px solid rgba(239,68,68,.25); background:rgba(239,68,68,.08); color:#fca5a5; }

                /* Mobile stacking */
                @media (max-width: 720px) {
                    .lg-root { flex-direction: column; }
                    .lg-left { padding: 40px 28px 32px; border-right: none; border-bottom: 1px solid rgba(255,255,255,.06); }
                    .lg-right { width: 100%; padding: 36px 28px 48px; }
                    .lg-title { font-size: 28px; }
                    .lg-stats { flex-direction: row; flex-wrap: wrap; }
                    .lg-stat { flex: 1; min-width: 140px; }
                }
            `}</style>

            <div className="lg-root">
                {/* ── LEFT: BRANDING ── */}
                <div className="lg-left">
                    <div className="lg-left-beam" />
                    <div className="lg-left-bg" />
                    <div className="lg-glow1" />
                    <div className="lg-glow2" />

                    <div className="lg-left-content">
                        {/* Animated icon */}
                        <div className="lg-icon-wrap">
                            <div className="lg-icon-ring1" />
                            <div className="lg-icon-ring2" />
                            <div className="lg-orb-dot" />
                            <div className="lg-orb-dot2" />
                            <div className="lg-icon-center">
                                <PawPrint size={38} className="lg-paw-icon" />
                            </div>
                        </div>

                        {/* Headline */}
                        <h1 className="lg-title">
                            Know your
                            <br />
                            dog's <span className="lg-title-accent">breed</span>
                            <br />
                            instantly.
                        </h1>
                        <p className="lg-subtitle">
                            Upload a photo. Get accurate breed identification,
                            health insights, and origin history — all in
                            seconds.
                        </p>

                        {/* Stats */}
                        <div className="lg-stats">
                            {[
                                {
                                    icon: <Zap size={14} color="#10b981" />,
                                    bg: 'rgba(16,185,129,.1)',
                                    val: '~1.2s',
                                    label: 'Average scan time',
                                },
                                {
                                    icon: (
                                        <Sparkles size={14} color="#06b6d4" />
                                    ),
                                    bg: 'rgba(6,182,212,.1)',
                                    val: '95%+',
                                    label: 'Breed accuracy rate',
                                },
                                {
                                    icon: <Shield size={14} color="#a78bfa" />,
                                    bg: 'rgba(167,139,250,.1)',
                                    val: 'Vet OK',
                                    label: 'Veterinary verified',
                                },
                            ].map((s, i) => (
                                <div className="lg-stat" key={i}>
                                    <div
                                        className="lg-stat-icon"
                                        style={{ background: s.bg }}
                                    >
                                        {s.icon}
                                    </div>
                                    <div>
                                        <div className="lg-stat-val">
                                            {s.val}
                                        </div>
                                        <div className="lg-stat-label">
                                            {s.label}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* ── RIGHT: FORM ── */}
                <div className="lg-right">
                    <div className="lg-right-beam" />

                    {/* Alerts */}
                    {status && (
                        <div className="lg-alert lg-alert-ok">
                            <CheckCircle2
                                size={14}
                                style={{ flexShrink: 0, marginTop: 1 }}
                            />
                            {status}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="lg-alert lg-alert-err">
                            <XCircle
                                size={14}
                                style={{ flexShrink: 0, marginTop: 1 }}
                            />
                            {flash.error}
                        </div>
                    )}

                    {/* Badge */}
                    <div className="lg-badge">
                        <span className="lg-badge-dot" />
                        <span className="lg-badge-text">DogLens</span>
                    </div>

                    <p className="lg-form-title">Sign in to continue</p>
                    <p className="lg-form-sub">
                        Access breed analysis, health reports & more
                    </p>

                    {/* Google button */}
                    <button
                        onClick={() => (window.location.href = '/auth/google')}
                        className="lg-google-btn"
                    >
                        <span className="lg-google-shine" />
                        <svg
                            width="20"
                            height="20"
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

                    {/* Divider */}
                    <div className="lg-divider">
                        <div className="lg-divider-line" />
                        <span className="lg-divider-text">
                            Secured by OAuth 2.0
                        </span>
                        <div className="lg-divider-line" />
                    </div>

                    {/* Trust cards */}
                    <div className="lg-trust">
                        {[
                            {
                                icon: <Shield size={13} color="#10b981" />,
                                bg: 'rgba(16,185,129,.1)',
                                name: 'Secure Login',
                                sub: 'OAuth 2.0',
                            },
                            {
                                icon: (
                                    <CheckCircle2 size={13} color="#06b6d4" />
                                ),
                                bg: 'rgba(6,182,212,.1)',
                                name: 'Vet Verified',
                                sub: 'Licensed review',
                            },
                        ].map((c, i) => (
                            <div className="lg-trust-card" key={i}>
                                <div
                                    className="lg-trust-icon"
                                    style={{ background: c.bg }}
                                >
                                    {c.icon}
                                </div>
                                <div>
                                    <div className="lg-trust-name">
                                        {c.name}
                                    </div>
                                    <div className="lg-trust-sub">{c.sub}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
