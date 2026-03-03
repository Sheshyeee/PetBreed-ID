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
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap');

                /* Force the AuthLayout wrapper to step aside */
                .lv-kill-header {
                    display: none !important;
                    visibility: hidden !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                /* Make every ancestor of lv-root stop clipping */
                .lv-root,
                .lv-root * {
                    box-sizing: border-box;
                }

                /* Override any constraining layout shells injected by AuthLayout */
                .lv-outer {
                    position: fixed;
                    inset: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #050d0a;
                    padding: 16px;
                    z-index: 50;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                }

                .lv-card {
                    display: flex;
                    flex-direction: row;
                    width: 100%;
                    max-width: 860px;
                    min-height: 440px;
                    border-radius: 18px;
                    overflow: hidden;
                    border: 1px solid rgba(255,255,255,0.08);
                    box-shadow: 0 24px 64px rgba(0,0,0,0.7);
                }

                /* ── LEFT PANEL ── */
                .lv-left {
                    position: relative;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 20px;
                    background: #09201a;
                    width: 280px;
                    flex-shrink: 0;
                    padding: 40px 32px;
                }

                /* ── RIGHT PANEL ── */
                .lv-right {
                    flex: 1;
                    min-width: 0;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    gap: 20px;
                    padding: 48px 52px;
                    background: #0D1117;
                }

                /* ── GOOGLE BUTTON ── */
                .lv-google-btn {
                    position: relative;
                    overflow: hidden;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 12px;
                    padding: 13px 16px;
                    border-radius: 11px;
                    background: #111827;
                    border: 1.5px solid rgba(255,255,255,0.1);
                    color: #f1f5f9;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                    box-shadow: none;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                }
                .lv-google-btn:hover {
                    transform: translateY(-1px);
                    border-color: rgba(16,185,129,0.3);
                    box-shadow: 0 4px 16px rgba(0,0,0,0.3);
                }
                .lv-google-btn:active {
                    transform: translateY(0);
                }
                .lv-google-shine {
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 40%;
                    height: 100%;
                    background: linear-gradient(to right, transparent, rgba(255,255,255,0.08), transparent);
                    transform: skewX(-18deg);
                    pointer-events: none;
                    transition: left 0.7s ease-in-out;
                }
                .lv-google-btn:hover .lv-google-shine {
                    left: 120%;
                }

                /* ── ALERTS ── */
                .lv-alert-success {
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                    padding: 10px 12px;
                    border-radius: 10px;
                    font-size: 12px;
                    font-weight: 500;
                    border: 1px solid rgba(16,185,129,0.2);
                    background: rgba(16,185,129,0.1);
                    color: #6ee7b7;
                    margin-bottom: 4px;
                }
                .lv-alert-error {
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                    padding: 10px 12px;
                    border-radius: 10px;
                    font-size: 12px;
                    font-weight: 500;
                    border: 1px solid rgba(239,68,68,0.2);
                    background: rgba(239,68,68,0.1);
                    color: #fca5a5;
                    margin-bottom: 4px;
                }

                /* ── RESPONSIVE: stack vertically on small screens ── */
                @media (max-width: 600px) {
                    .lv-card {
                        flex-direction: column;
                        max-width: 100%;
                        min-height: unset;
                        border-radius: 16px;
                    }
                    .lv-left {
                        width: 100%;
                        padding: 32px 24px;
                    }
                    .lv-right {
                        padding: 32px 24px;
                    }
                }
            `}</style>

            {/* Hidden helper to neutralise any constraining AuthLayout children */}
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
                            !el2.classList.contains('lv-outer') &&
                            !el2.querySelector('.lv-outer') &&
                            !el2.querySelector('.lv-alert-success') &&
                            !el2.querySelector('.lv-alert-error')
                        ) {
                            el2.style.cssText = 'display:none!important';
                        }
                    });
                }}
            />

            {/* ── FULL-SCREEN OVERLAY ── */}
            <div className="lv-outer">
                <div className="lv-card">
                    {/* ════════════════════════ LEFT PANEL ════════════════════════ */}
                    <div className="lv-left">
                        {/* Grid dot background */}
                        <div
                            style={{
                                position: 'absolute',
                                inset: 0,
                                pointerEvents: 'none',
                                backgroundImage:
                                    'radial-gradient(circle, rgba(16,185,129,0.10) 1px, transparent 1px)',
                                backgroundSize: '20px 20px',
                                maskImage:
                                    'radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%)',
                                WebkitMaskImage:
                                    'radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%)',
                            }}
                        />
                        {/* Top line */}
                        <div
                            style={{
                                position: 'absolute',
                                top: 0,
                                left: 0,
                                right: 0,
                                height: 1,
                                background: 'rgba(16,185,129,0.3)',
                            }}
                        />
                        {/* Glow orbs */}
                        <div
                            style={{
                                position: 'absolute',
                                top: -40,
                                left: -32,
                                width: 150,
                                height: 150,
                                borderRadius: '50%',
                                background: 'rgba(16,185,129,0.05)',
                                filter: 'blur(48px)',
                                pointerEvents: 'none',
                            }}
                        />
                        <div
                            style={{
                                position: 'absolute',
                                bottom: -32,
                                right: -20,
                                width: 110,
                                height: 110,
                                borderRadius: '50%',
                                background: 'rgba(6,182,212,0.05)',
                                filter: 'blur(42px)',
                                pointerEvents: 'none',
                            }}
                        />

                        {/* Icon */}
                        <div
                            style={{
                                position: 'relative',
                                width: 76,
                                height: 76,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                flexShrink: 0,
                            }}
                        >
                            <div
                                style={{
                                    position: 'absolute',
                                    inset: -10,
                                    borderRadius: '50%',
                                    border: '1px solid rgba(16,185,129,0.2)',
                                }}
                            />
                            <div
                                style={{
                                    position: 'absolute',
                                    inset: -20,
                                    borderRadius: '50%',
                                    border: '1px solid rgba(16,185,129,0.1)',
                                }}
                            />
                            <div
                                style={{
                                    width: 76,
                                    height: 76,
                                    borderRadius: 22,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    border: '1px solid rgba(16,185,129,0.2)',
                                    background: 'rgba(16,185,129,0.1)',
                                    position: 'relative',
                                    zIndex: 1,
                                }}
                            >
                                <PawPrint size={30} color="#10b981" />
                            </div>
                        </div>

                        {/* Left copy */}
                        <div
                            style={{
                                textAlign: 'center',
                                position: 'relative',
                                zIndex: 1,
                            }}
                        >
                            <p
                                style={{
                                    fontSize: 16,
                                    fontWeight: 700,
                                    color: '#fff',
                                    lineHeight: 1.3,
                                    letterSpacing: '-0.02em',
                                    marginBottom: 8,
                                }}
                            >
                                Identify any
                                <br />
                                <span style={{ color: '#10b981' }}>
                                    dog breed
                                </span>
                            </p>
                            <p
                                style={{
                                    fontSize: 11,
                                    color: 'rgba(255,255,255,0.4)',
                                    lineHeight: 1.6,
                                    maxWidth: 150,
                                    margin: '0 auto',
                                }}
                            >
                                Upload a photo, get results instantly with
                                health insights
                            </p>
                            {/* Dots */}
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 6,
                                    justifyContent: 'center',
                                    marginTop: 20,
                                }}
                            >
                                <div
                                    style={{
                                        width: 16,
                                        height: 6,
                                        borderRadius: 3,
                                        background: '#10b981',
                                    }}
                                />
                                <div
                                    style={{
                                        width: 6,
                                        height: 6,
                                        borderRadius: '50%',
                                        background: 'rgba(255,255,255,0.15)',
                                    }}
                                />
                                <div
                                    style={{
                                        width: 6,
                                        height: 6,
                                        borderRadius: '50%',
                                        background: 'rgba(255,255,255,0.15)',
                                    }}
                                />
                            </div>
                        </div>
                    </div>

                    {/* ════════════════════════ RIGHT PANEL ════════════════════════ */}
                    <div className="lv-right">
                        {/* Alerts */}
                        {status && (
                            <div className="lv-alert-success">
                                <CheckCircle2
                                    size={14}
                                    style={{ flexShrink: 0, marginTop: 1 }}
                                />
                                {status}
                            </div>
                        )}
                        {flash?.error && (
                            <div className="lv-alert-error">
                                <XCircle
                                    size={14}
                                    style={{ flexShrink: 0, marginTop: 1 }}
                                />
                                {flash.error}
                            </div>
                        )}

                        {/* Brand pill */}
                        <div
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 8,
                                padding: '6px 12px',
                                borderRadius: 999,
                                border: '1px solid rgba(16,185,129,0.2)',
                                background: 'rgba(16,185,129,0.05)',
                                width: 'fit-content',
                            }}
                        >
                            <span
                                style={{
                                    width: 6,
                                    height: 6,
                                    borderRadius: '50%',
                                    background: '#10b981',
                                    animation: 'pulse 2s infinite',
                                }}
                            />
                            <span
                                style={{
                                    fontFamily: "'JetBrains Mono', monospace",
                                    fontSize: 10,
                                    fontWeight: 600,
                                    letterSpacing: '0.12em',
                                    color: '#10b981',
                                    textTransform: 'uppercase',
                                }}
                            >
                                DogLens
                            </span>
                        </div>

                        {/* Title */}
                        <div>
                            <p
                                style={{
                                    fontSize: 28,
                                    fontWeight: 800,
                                    letterSpacing: '-0.03em',
                                    color: '#f8fafc',
                                    lineHeight: 1.15,
                                    margin: 0,
                                }}
                            >
                                Welcome back
                            </p>
                            <p
                                style={{
                                    fontSize: 14,
                                    color: '#94a3b8',
                                    marginTop: 6,
                                    lineHeight: 1.5,
                                }}
                            >
                                Sign in to access your breed analysis
                            </p>
                        </div>

                        {/* Google button */}
                        <button
                            className="lv-google-btn"
                            onClick={() =>
                                (window.location.href = '/auth/google')
                            }
                        >
                            <span className="lv-google-shine" />
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

                        {/* Divider */}
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <div
                                style={{
                                    flex: 1,
                                    height: 1,
                                    background: 'rgba(255,255,255,0.07)',
                                }}
                            />
                            <span
                                style={{
                                    fontFamily: "'JetBrains Mono', monospace",
                                    fontSize: 9,
                                    letterSpacing: '0.12em',
                                    color: 'rgba(255,255,255,0.2)',
                                    textTransform: 'uppercase',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                secured · oauth 2.0
                            </span>
                            <div
                                style={{
                                    flex: 1,
                                    height: 1,
                                    background: 'rgba(255,255,255,0.07)',
                                }}
                            />
                        </div>

                        {/* Footer note */}
                        <p
                            style={{
                                fontSize: 11,
                                color: 'rgba(255,255,255,0.25)',
                                textAlign: 'center',
                                lineHeight: 1.6,
                                margin: 0,
                            }}
                        >
                            By signing in you agree to our terms &amp; privacy
                            policy
                        </p>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
