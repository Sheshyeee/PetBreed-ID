import AuthLayout from '@/layouts/auth-layout';
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
        <AuthLayout
            title="Dog Breed Identification System"
            description="Sign in to access professional breed analysis"
        >
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes lc-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(1.6)} }
                @keyframes lc-ring  { 0%{transform:scale(.82);opacity:.6} 70%,100%{transform:scale(1.26);opacity:0} }
                @keyframes lc-paw   { 0%,100%{transform:rotate(0deg)} 30%{transform:rotate(-9deg)} 70%{transform:rotate(9deg)} }
                @keyframes lc-orb   { from{transform:rotate(0deg) translateX(26px) rotate(0deg)} to{transform:rotate(360deg) translateX(26px) rotate(-360deg)} }
                @keyframes lc-orb2  { from{transform:rotate(180deg) translateX(18px) rotate(-180deg)} to{transform:rotate(540deg) translateX(18px) rotate(-540deg)} }
                @keyframes lc-sweep { 0%{left:-80%} 100%{left:160%} }
                @keyframes lc-fade  { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }

                .lc-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .lc-mono { font-family:'JetBrains Mono',monospace !important; }
                .lc-f1 { animation:lc-fade .35s cubic-bezier(.16,1,.3,1) .05s both; }
                .lc-f2 { animation:lc-fade .35s cubic-bezier(.16,1,.3,1) .14s both; }
                .lc-f3 { animation:lc-fade .35s cubic-bezier(.16,1,.3,1) .22s both; }

                /* Two-col wrapper */
                .lc-cols {
                    display: flex;
                    gap: 0;
                    border-radius: 16px;
                    overflow: hidden;
                    border: 1px solid rgba(255,255,255,.08);
                    background: #0D1117;
                }

                /* LEFT */
                .lc-left {
                    width: 160px;
                    flex-shrink: 0;
                    padding: 20px 16px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 14px;
                    position: relative;
                    overflow: hidden;
                    border-right: 1px solid rgba(255,255,255,.07);
                    background: linear-gradient(160deg, rgba(16,185,129,.06) 0%, rgba(6,182,212,.03) 100%);
                }
                .lc-left-dot-grid {
                    position: absolute; inset: 0; pointer-events: none;
                    background-image: radial-gradient(circle, rgba(16,185,129,.1) 1px, transparent 1px);
                    background-size: 18px 18px;
                    -webkit-mask-image: radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%);
                    mask-image: radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%);
                }
                .lc-left-beam {
                    position: absolute; top: 0; left: 0; right: 0; height: 1.5px;
                    background: linear-gradient(90deg, transparent, #10b981 50%, transparent);
                    opacity: .5;
                }

                /* Icon */
                .lc-icon-wrap {
                    position: relative;
                    width: 64px; height: 64px;
                    display: flex; align-items: center; justify-content: center;
                }
                .lc-ring1 { position:absolute; inset:-8px; border-radius:50%; border:1px solid rgba(16,185,129,.2); animation:lc-ring 2.8s ease-out infinite; }
                .lc-ring2 { position:absolute; inset:-16px; border-radius:50%; border:1px solid rgba(16,185,129,.07); animation:lc-ring 2.8s ease-out infinite .9s; }
                .lc-orb  { position:absolute; width:5px; height:5px; border-radius:50%; top:50%; left:50%; margin:-2.5px 0 0 -2.5px; background:#10b981; box-shadow:0 0 8px #10b981; animation:lc-orb 3.4s linear infinite; }
                .lc-orb2 { position:absolute; width:4px; height:4px; border-radius:50%; top:50%; left:50%; margin:-2px 0 0 -2px; background:#06b6d4; box-shadow:0 0 6px #06b6d4; animation:lc-orb2 4s linear infinite; }
                .lc-icon-center {
                    width: 64px; height: 64px; border-radius: 18px;
                    display: flex; align-items: center; justify-content: center;
                    border: 1px solid rgba(16,185,129,.22);
                    background: linear-gradient(135deg, rgba(16,185,129,.13), rgba(6,182,212,.06));
                    box-shadow: 0 0 22px rgba(16,185,129,.15), inset 0 0 14px rgba(16,185,129,.04);
                }

                /* Mini stats */
                .lc-stats { display: flex; flex-direction: column; gap: 7px; width: 100%; }
                .lc-stat {
                    display: flex; align-items: center; gap: 8px;
                    padding: 7px 9px; border-radius: 9px;
                    border: 1px solid rgba(255,255,255,.055);
                    background: rgba(255,255,255,.025);
                }
                .lc-stat-icon {
                    width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0;
                    display: flex; align-items: center; justify-content: center;
                }
                .lc-stat-val   { font-size: 11px; font-weight: 800; color: #fff; line-height: 1; }
                .lc-stat-label { font-size: 9px; color: rgba(255,255,255,.38); margin-top: 1px; }

                /* RIGHT */
                .lc-right {
                    flex: 1;
                    padding: 20px 18px;
                    display: flex;
                    flex-direction: column;
                    gap: 14px;
                }

                /* Badge */
                .lc-badge {
                    display: inline-flex; align-items: center; gap: 5px;
                    padding: 4px 10px; border-radius: 99px;
                    border: 1px solid rgba(16,185,129,.2);
                    background: rgba(16,185,129,.08);
                    width: fit-content;
                }
                .lc-badge-dot { width:5px; height:5px; border-radius:50%; background:#10b981; box-shadow:0 0 5px #10b981; animation:lc-pulse 2s ease-in-out infinite; }
                .lc-badge-text { font-size:9px; font-weight:600; letter-spacing:.12em; color:#10b981; text-transform:uppercase; font-family:'JetBrains Mono',monospace; }

                .lc-form-title { font-size: 17px; font-weight: 800; color: #fff; letter-spacing: -.01em; line-height: 1.2; }
                .lc-form-title span {
                    background: linear-gradient(135deg, #10b981, #06b6d4);
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .lc-form-sub { font-size: 11px; color: rgba(255,255,255,.35); line-height: 1.5; margin-top: 3px; }

                /* Google btn */
                .lc-google-btn {
                    position: relative; overflow: hidden; width: 100%;
                    display: flex; align-items: center; justify-content: center; gap: 8px;
                    padding: 11px 16px; border-radius: 10px;
                    background: #fff; border: none;
                    color: #1e293b; font-size: 13px; font-weight: 700;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    cursor: pointer;
                    transition: transform .17s, box-shadow .17s;
                    box-shadow: 0 2px 10px rgba(0,0,0,.2);
                }
                .lc-google-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,.28), 0 0 0 1px rgba(16,185,129,.18); }
                .lc-google-btn:active { transform: translateY(0); }
                .lc-shine {
                    position: absolute; top:0; left:-80%; width:40%; height:100%;
                    background: linear-gradient(90deg,transparent,rgba(255,255,255,.5),transparent);
                    transform: skewX(-18deg); pointer-events:none;
                }
                .lc-google-btn:hover .lc-shine { animation: lc-sweep .5s ease forwards; }

                /* Divider */
                .lc-div { display:flex; align-items:center; gap:8px; }
                .lc-div-line { flex:1; height:1px; background:rgba(255,255,255,.07); }
                .lc-div-text { font-size:8px; letter-spacing:.12em; color:rgba(255,255,255,.2); text-transform:uppercase; font-family:'JetBrains Mono',monospace; white-space:nowrap; }

                /* Trust */
                .lc-trust { display:grid; grid-template-columns:1fr 1fr; gap:7px; }
                .lc-trust-card {
                    display:flex; align-items:center; gap:7px;
                    padding:8px 10px; border-radius:9px;
                    border:1px solid rgba(255,255,255,.055);
                    background:rgba(255,255,255,.02);
                }
                .lc-trust-ico {
                    width:22px; height:22px; border-radius:6px; flex-shrink:0;
                    display:flex; align-items:center; justify-content:center;
                    border:1px solid rgba(255,255,255,.07);
                }
                .lc-trust-name { font-size:10px; font-weight:700; color:rgba(255,255,255,.75); line-height:1; }
                .lc-trust-sub  { font-size:8px; color:rgba(255,255,255,.28); margin-top:2px; }

                /* Alerts */
                .lc-alert { display:flex; align-items:flex-start; gap:8px; padding:10px 12px; border-radius:10px; font-size:12px; font-weight:500; margin-bottom:4px; }
                .lc-ok  { border:1px solid rgba(16,185,129,.25); background:rgba(16,185,129,.08); color:#6ee7b7; }
                .lc-err { border:1px solid rgba(239,68,68,.25); background:rgba(239,68,68,.08); color:#fca5a5; }
            `}</style>

            <div className="lc-root lc-f1">
                {/* Alerts outside the cols */}
                {status && (
                    <div
                        className="lc-alert lc-ok"
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
                        className="lc-alert lc-err"
                        style={{ marginBottom: 12 }}
                    >
                        <XCircle
                            size={13}
                            style={{ flexShrink: 0, marginTop: 1 }}
                        />
                        {flash.error}
                    </div>
                )}

                <div className="lc-cols">
                    {/* ── LEFT: Brand ── */}
                    <div className="lc-left">
                        <div className="lc-left-dot-grid" />
                        <div className="lc-left-beam" />

                        {/* Icon */}
                        <div className="lc-icon-wrap">
                            <div className="lc-ring1" />
                            <div className="lc-ring2" />
                            <div className="lc-orb" />
                            <div className="lc-orb2" />
                            <div className="lc-icon-center">
                                <PawPrint
                                    size={28}
                                    style={{
                                        animation:
                                            'lc-paw 3s ease-in-out infinite',
                                        color: '#10b981',
                                    }}
                                />
                            </div>
                        </div>

                        {/* Stats */}
                        <div className="lc-stats">
                            {[
                                {
                                    icon: <Zap size={11} color="#10b981" />,
                                    bg: 'rgba(16,185,129,.1)',
                                    val: '~1.2s',
                                    label: 'Scan speed',
                                },
                                {
                                    icon: (
                                        <Sparkles size={11} color="#06b6d4" />
                                    ),
                                    bg: 'rgba(6,182,212,.1)',
                                    val: '95%+',
                                    label: 'Accuracy',
                                },
                                {
                                    icon: <Shield size={11} color="#a78bfa" />,
                                    bg: 'rgba(167,139,250,.1)',
                                    val: 'Vet OK',
                                    label: 'Verified',
                                },
                            ].map((s, i) => (
                                <div className="lc-stat" key={i}>
                                    <div
                                        className="lc-stat-icon"
                                        style={{ background: s.bg }}
                                    >
                                        {s.icon}
                                    </div>
                                    <div>
                                        <div className="lc-stat-val">
                                            {s.val}
                                        </div>
                                        <div className="lc-stat-label">
                                            {s.label}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* ── RIGHT: Form ── */}
                    <div className="lc-right lc-f2">
                        <div>
                            <div className="lc-badge">
                                <span className="lc-badge-dot" />
                                <span className="lc-badge-text">DogLens</span>
                            </div>
                            <p
                                className="lc-form-title"
                                style={{ marginTop: 10 }}
                            >
                                Sign in to <span>DogLens</span>
                            </p>
                            <p className="lc-form-sub">
                                Access breed analysis & health reports
                            </p>
                        </div>

                        <button
                            onClick={() =>
                                (window.location.href = '/auth/google')
                            }
                            className="lc-google-btn lc-f3"
                        >
                            <span className="lc-shine" />
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

                        <div className="lc-div">
                            <div className="lc-div-line" />
                            <span className="lc-div-text">OAuth 2.0</span>
                            <div className="lc-div-line" />
                        </div>

                        <div className="lc-trust lc-f3">
                            {[
                                {
                                    icon: <Shield size={12} color="#10b981" />,
                                    bg: 'rgba(16,185,129,.1)',
                                    name: 'Secure Login',
                                    sub: 'OAuth 2.0',
                                },
                                {
                                    icon: (
                                        <CheckCircle2
                                            size={12}
                                            color="#06b6d4"
                                        />
                                    ),
                                    bg: 'rgba(6,182,212,.1)',
                                    name: 'Vet Verified',
                                    sub: 'Licensed',
                                },
                            ].map((c, i) => (
                                <div className="lc-trust-card" key={i}>
                                    <div
                                        className="lc-trust-ico"
                                        style={{ background: c.bg }}
                                    >
                                        {c.icon}
                                    </div>
                                    <div>
                                        <div className="lc-trust-name">
                                            {c.name}
                                        </div>
                                        <div className="lc-trust-sub">
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
