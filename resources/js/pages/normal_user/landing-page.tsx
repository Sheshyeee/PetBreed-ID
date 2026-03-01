import { login } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    Camera,
    ChevronRight,
    Download,
    Heart,
    MapPin,
    PawPrintIcon,
    QrCode,
    Scan,
    ShieldCheck,
    Smartphone,
    Sparkles,
    X,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

function LandingPage() {
    const [open, setOpen] = useState(false);
    const [showQRModal, setShowQRModal] = useState(false);
    const { auth } = usePage<SharedData>().props;

    const allowedEmails = [
        'modeltraining2000@gmail.com',
        'jrbd2022-8800-57025@bicol-u.edu.ph',
    ];
    const isAdmin = auth.user && allowedEmails.includes(auth.user.email);

    const getScanLink = () => {
        if (!auth.user) return login();
        if (isAdmin) return '/dashboard';
        return '/scan';
    };

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

                .lp-root { font-family: 'Plus Jakarta Sans', sans-serif; }

                @keyframes lp-float   { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
                @keyframes lp-fadein  { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
                @keyframes lp-shimmer { 0%{background-position:200% center} 100%{background-position:-200% center} }
                @keyframes lp-modal   { from{opacity:0;transform:scale(.96) translateY(8px)} to{opacity:1;transform:scale(1) translateY(0)} }
                @keyframes lp-pulse   { 0%,100%{opacity:.6} 50%{opacity:1} }

                .lp-fu { animation: lp-fadein .45s cubic-bezier(.16,1,.3,1) both; }

                .lp-hero-card {
                    background: linear-gradient(135deg, #0C134F 0%, #1a2270 55%, #0f1a65 100%);
                    position: relative;
                    overflow: hidden;
                }
                .lp-hero-orb-1 {
                    position: absolute; top:-70px; right:-50px;
                    width:220px; height:220px; border-radius:50%;
                    background: radial-gradient(circle, rgba(92,70,156,.4) 0%, transparent 70%);
                    pointer-events:none;
                }
                .lp-hero-orb-2 {
                    position: absolute; bottom:-50px; left:-30px;
                    width:160px; height:160px; border-radius:50%;
                    background: radial-gradient(circle, rgba(12,19,79,.7) 0%, transparent 70%);
                    pointer-events:none;
                }

                .lp-badge {
                    display:inline-flex; align-items:center; gap:7px;
                    background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
                    border-radius:40px; padding:5px 13px;
                    font-size:11px; font-weight:600; color:rgba(255,255,255,.85);
                    width:fit-content;
                }

                .lp-shimmer-text {
                    background: linear-gradient(90deg, #a78bfa, #c4b5fd, #818cf8, #a78bfa);
                    background-size: 200% auto;
                    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
                    animation: lp-shimmer 3s linear infinite;
                }

                .lp-cta {
                    display:inline-flex; align-items:center; gap:8px;
                    background:white; color:#0C134F;
                    border:none; border-radius:12px;
                    padding:11px 22px; font-size:14px; font-weight:700;
                    cursor:pointer; transition:all .2s;
                    box-shadow:0 4px 20px rgba(0,0,0,.2);
                    font-family:'Plus Jakarta Sans',sans-serif;
                }
                .lp-cta:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(0,0,0,.28); }

                .lp-feature-card {
                    background: linear-gradient(150deg, #5C469C 0%, #4b3886 100%);
                    border-radius: 14px; padding: 20px 22px;
                    position: relative; overflow: hidden;
                    transition: transform .2s, box-shadow .2s;
                    min-height: 160px;
                    display: flex; flex-direction: column; justify-content: space-between;
                }
                .lp-feature-card:hover { transform:translateY(-3px); box-shadow:0 14px 36px rgba(92,70,156,.38); }
                .lp-feature-card::after {
                    content:''; position:absolute; top:-24px; right:-24px;
                    width:90px; height:90px; border-radius:50%;
                    background:radial-gradient(circle,rgba(255,255,255,.07) 0%,transparent 70%);
                    pointer-events:none;
                }

                .lp-feat-icon {
                    width:36px; height:36px; border-radius:10px;
                    background:rgba(255,255,255,.13); border:1px solid rgba(255,255,255,.15);
                    display:flex; align-items:center; justify-content:center;
                    color:white; margin-bottom:10px;
                }

                .lp-feat-action {
                    display:inline-flex; align-items:center; gap:6px;
                    background:rgba(255,255,255,.13); border:1px solid rgba(255,255,255,.18);
                    border-radius:8px; padding:6px 12px;
                    font-size:12px; font-weight:600; color:white;
                    cursor:pointer; transition:background .2s; width:fit-content;
                    font-family:'Plus Jakarta Sans',sans-serif;
                }
                .lp-feat-action:hover { background:rgba(255,255,255,.21); }

                .lp-side-card {
                    background: linear-gradient(155deg, #1D267D 0%, #141e6b 100%);
                    border-radius: 14px; padding: 20px 22px;
                    position: relative; overflow: hidden;
                    width: 100%;
                }
                .lp-side-card::before {
                    content:''; position:absolute; top:-50px; right:-30px;
                    width:180px; height:180px; border-radius:50%;
                    background:radial-gradient(circle,rgba(92,70,156,.22) 0%,transparent 70%);
                    pointer-events:none;
                }

                .lp-dog-frame {
                    position:relative; border-radius:12px; overflow:hidden;
                    box-shadow:0 8px 32px rgba(0,0,0,.4); margin-bottom:16px;
                }
                .lp-dog-frame::after {
                    content:''; position:absolute; inset:0;
                    background:linear-gradient(180deg,transparent 50%,rgba(12,19,79,.55) 100%);
                    pointer-events:none;
                }

                .lp-vet-row {
                    display:flex; align-items:center; gap:12px;
                    background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1);
                    border-radius:12px; padding:11px 14px; margin-bottom:12px;
                }
                .lp-vet-icon {
                    width:38px; height:38px; flex-shrink:0; border-radius:10px;
                    background:rgba(74,222,128,.12); border:1px solid rgba(74,222,128,.22);
                    display:flex; align-items:center; justify-content:center;
                }

                .lp-checklist { display:flex; flex-direction:column; gap:0; margin-bottom:16px; }
                .lp-check-row {
                    display:flex; align-items:flex-start; gap:10px;
                    padding:9px 0; border-bottom:1px solid rgba(255,255,255,.07);
                }
                .lp-check-row:last-child { border-bottom:none; }

                .lp-outline-cta {
                    display:block; width:100%; text-align:center;
                    background:transparent; border:1.5px solid rgba(255,255,255,.3);
                    border-radius:12px; padding:11px; font-size:14px; font-weight:700;
                    color:white; cursor:pointer; transition:all .2s;
                    font-family:'Plus Jakarta Sans',sans-serif;
                }
                .lp-outline-cta:hover { background:rgba(255,255,255,.1); border-color:rgba(255,255,255,.55); }

                .lp-modal-overlay {
                    position:fixed; inset:0; z-index:50;
                    background:rgba(0,0,0,.65); backdrop-filter:blur(10px);
                    display:flex; align-items:center; justify-content:center; padding:16px;
                }
                .lp-modal-box {
                    animation:lp-modal .28s cubic-bezier(.16,1,.3,1) both;
                    background:linear-gradient(145deg,#1D267D,#0C134F);
                    border:1px solid rgba(255,255,255,.12);
                    border-radius:20px; padding:28px;
                    width:100%; max-width:360px;
                    position:relative; box-shadow:0 28px 70px rgba(0,0,0,.55);
                }
                .lp-modal-close {
                    position:absolute; top:14px; right:14px;
                    width:28px; height:28px; border-radius:8px;
                    background:rgba(255,255,255,.1); border:none; cursor:pointer;
                    display:flex; align-items:center; justify-content:center;
                    color:rgba(255,255,255,.55); transition:background .2s;
                }
                .lp-modal-close:hover { background:rgba(255,255,255,.18); }

                .lp-fab {
                    position:fixed; right:20px; bottom:20px; z-index:40;
                    width:46px; height:46px; border-radius:50%;
                    background:linear-gradient(135deg,#5C469C,#3b2f7a);
                    border:none; cursor:pointer;
                    display:flex; align-items:center; justify-content:center;
                    box-shadow:0 4px 20px rgba(92,70,156,.5); color:white;
                    transition:all .2s;
                }
                .lp-fab:hover { transform:scale(1.09); box-shadow:0 8px 30px rgba(92,70,156,.6); }
            `}</style>

            <div className="lp-root">
                {/* QR Modal */}
                {showQRModal && (
                    <div
                        className="lp-modal-overlay"
                        onClick={() => setShowQRModal(false)}
                    >
                        <div
                            className="lp-modal-box"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <button
                                className="lp-modal-close"
                                onClick={() => setShowQRModal(false)}
                            >
                                <X size={13} />
                            </button>

                            <div
                                style={{
                                    textAlign: 'center',
                                    marginBottom: 22,
                                }}
                            >
                                <div
                                    style={{
                                        width: 50,
                                        height: 50,
                                        borderRadius: 14,
                                        background: 'rgba(255,255,255,.1)',
                                        border: '1px solid rgba(255,255,255,.15)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        margin: '0 auto 12px',
                                    }}
                                >
                                    <Smartphone
                                        size={24}
                                        style={{ color: 'white' }}
                                    />
                                </div>
                                <h2
                                    style={{
                                        fontSize: 18,
                                        fontWeight: 800,
                                        color: 'white',
                                        margin: '0 0 4px',
                                    }}
                                >
                                    Install Mobile App
                                </h2>
                                <p
                                    style={{
                                        fontSize: 12,
                                        color: 'rgba(255,255,255,.45)',
                                        margin: 0,
                                    }}
                                >
                                    Scan QR code to download the Android app
                                </p>
                            </div>

                            <div
                                style={{
                                    display: 'flex',
                                    justifyContent: 'center',
                                    marginBottom: 20,
                                }}
                            >
                                <div
                                    style={{
                                        background: 'white',
                                        padding: 10,
                                        borderRadius: 14,
                                        boxShadow: '0 4px 24px rgba(0,0,0,.35)',
                                    }}
                                >
                                    <img
                                        src="/doglens_apk_qr.jpeg"
                                        alt="QR"
                                        style={{
                                            display: 'block',
                                            width: 148,
                                            height: 148,
                                        }}
                                    />
                                </div>
                            </div>

                            <div
                                style={{
                                    background: 'rgba(255,255,255,.06)',
                                    border: '1px solid rgba(255,255,255,.09)',
                                    borderRadius: 12,
                                    overflow: 'hidden',
                                    marginBottom: 16,
                                }}
                            >
                                {[
                                    {
                                        icon: <Download size={13} />,
                                        t: 'Fast & Easy Installation',
                                    },
                                    {
                                        icon: <Smartphone size={13} />,
                                        t: 'Available on Android',
                                    },
                                    {
                                        icon: <Camera size={13} />,
                                        t: 'All Features On-The-Go',
                                    },
                                ].map((f, i) => (
                                    <div
                                        key={i}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 10,
                                            padding: '10px 14px',
                                            borderBottom:
                                                i < 2
                                                    ? '1px solid rgba(255,255,255,.07)'
                                                    : 'none',
                                            fontSize: 13,
                                            color: 'rgba(255,255,255,.72)',
                                        }}
                                    >
                                        <span style={{ color: '#a78bfa' }}>
                                            {f.icon}
                                        </span>
                                        {f.t}
                                    </div>
                                ))}
                            </div>

                            <button
                                onClick={() => setShowQRModal(false)}
                                style={{
                                    width: '100%',
                                    background: 'rgba(255,255,255,.14)',
                                    border: '1.5px solid rgba(255,255,255,.22)',
                                    borderRadius: 12,
                                    padding: '10px',
                                    fontSize: 14,
                                    fontWeight: 700,
                                    color: 'white',
                                    cursor: 'pointer',
                                    fontFamily:
                                        "'Plus Jakarta Sans',sans-serif",
                                }}
                            >
                                Close
                            </button>
                        </div>
                    </div>
                )}

                {/* FAB */}
                <button
                    className="lp-fab"
                    onClick={() => setShowQRModal(true)}
                    title="Install Mobile App"
                >
                    <QrCode size={19} />
                </button>

                {/* ── MAIN ── */}
                <div className="lp-fu flex w-full flex-col gap-4 lg:flex-row">
                    <div className="flex w-full flex-col gap-4">
                        {/* HERO */}
                        <div className="lp-hero-card flex h-auto w-full flex-col items-center justify-between gap-4 rounded-xl p-6 sm:p-8 lg:h-[290px] lg:flex-row">
                            <div className="lp-hero-orb-1" />
                            <div className="lp-hero-orb-2" />

                            <div className="relative z-10 flex flex-1 flex-col justify-center gap-3.5 text-center lg:text-left">
                                <div className="lp-badge mx-auto lg:mx-0">
                                    <PawPrintIcon size={12} />
                                    AI-Powered Breed Detection
                                </div>
                                <h1 className="text-2xl leading-tight font-extrabold text-white sm:text-3xl">
                                    Identify dog{' '}
                                    <span className="lp-shimmer-text">
                                        breed
                                    </span>{' '}
                                    instantly
                                </h1>
                                <p
                                    style={{
                                        fontSize: 13,
                                        color: 'rgba(255,255,255,.58)',
                                        lineHeight: 1.65,
                                        maxWidth: 400,
                                    }}
                                    className="mx-auto lg:mx-0"
                                >
                                    Upload a photo and get accurate breed
                                    identification powered by advanced AI
                                    technology
                                </p>
                                <div>
                                    <Link
                                        href={getScanLink()}
                                        onClick={() => setOpen(false)}
                                    >
                                        <button className="lp-cta">
                                            <Scan size={15} />
                                            Scan Pet Now
                                            <ChevronRight
                                                size={13}
                                                style={{ opacity: 0.45 }}
                                            />
                                        </button>
                                    </Link>
                                </div>
                            </div>

                            <div className="relative z-10 hidden lg:block">
                                <div
                                    style={{
                                        animation:
                                            'lp-float 4s ease-in-out infinite',
                                    }}
                                >
                                    <img
                                        src="/paww.png"
                                        alt="Dog"
                                        style={{
                                            width: 110,
                                            height: 110,
                                            borderRadius: 14,
                                            objectFit: 'cover',
                                            boxShadow:
                                                '0 8px 28px rgba(0,0,0,.4)',
                                            marginTop: 80,
                                        }}
                                    />
                                </div>
                            </div>
                        </div>

                        {/* FEATURE CARDS */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {[
                                {
                                    icon: <Calendar size={17} />,
                                    title: 'Growth Simulation',
                                    desc: 'Visualize how your dog will look through different life stages from puppy to senior',
                                    action: 'See simulation',
                                    aIcon: <Calendar size={11} />,
                                },
                                {
                                    icon: <Heart size={17} />,
                                    title: 'Health Risk Analysis',
                                    desc: 'Discover breed-specific health risks and get preventive care recommendations',
                                    action: 'View health risks',
                                    aIcon: <Heart size={11} />,
                                },
                                {
                                    icon: <MapPin size={17} />,
                                    title: 'Origin & History',
                                    desc: "Learn about your dog's breed origins, historical purpose, and cultural significance",
                                    action: 'Explore history',
                                    aIcon: <MapPin size={11} />,
                                    span: true,
                                },
                            ].map((c, i) => (
                                <div
                                    key={i}
                                    className={`lp-feature-card ${c.span ? 'sm:col-span-2 lg:col-span-1' : ''}`}
                                >
                                    <div>
                                        <div className="lp-feat-icon">
                                            {c.icon}
                                        </div>
                                        <h3
                                            style={{
                                                fontSize: 14,
                                                fontWeight: 700,
                                                color: 'white',
                                                margin: '0 0 6px',
                                            }}
                                        >
                                            {c.title}
                                        </h3>
                                        <p
                                            style={{
                                                fontSize: 12,
                                                color: 'rgba(255,255,255,.62)',
                                                lineHeight: 1.6,
                                                margin: 0,
                                            }}
                                        >
                                            {c.desc}
                                        </p>
                                    </div>
                                    <button
                                        className="lp-feat-action"
                                        style={{ marginTop: 14 }}
                                    >
                                        {c.aIcon}
                                        {c.action}
                                        <ChevronRight
                                            size={10}
                                            style={{ opacity: 0.55 }}
                                        />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* SIDE CARD */}
                    <div className="lp-side-card lg:w-[390px]">
                        <div className="lp-dog-frame">
                            <img
                                src="/dog1.png"
                                style={{
                                    display: 'block',
                                    width: '100%',
                                    height: 220,
                                    objectFit: 'cover',
                                }}
                                alt="Dog"
                            />
                        </div>

                        <h2
                            style={{
                                fontSize: 16,
                                fontWeight: 800,
                                color: 'white',
                                margin: '0 0 14px',
                                lineHeight: 1.35,
                            }}
                        >
                            Professional Breed Analysis You Can Trust
                        </h2>

                        <div className="lp-vet-row">
                            <div className="lp-vet-icon">
                                <ShieldCheck
                                    size={18}
                                    style={{ color: '#4ade80' }}
                                />
                            </div>
                            <div>
                                <p
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 700,
                                        color: 'white',
                                        margin: '0 0 2px',
                                    }}
                                >
                                    Veterinary Verified
                                </p>
                                <p
                                    style={{
                                        fontSize: 11,
                                        color: 'rgba(255,255,255,.48)',
                                        margin: 0,
                                    }}
                                >
                                    Licensed vet reviews predictions
                                </p>
                            </div>
                        </div>

                        <div className="lp-checklist">
                            {[
                                {
                                    icon: <Zap size={13} />,
                                    text: 'Results in approximately 1.2 seconds',
                                },
                                {
                                    icon: <PawPrintIcon size={13} />,
                                    text: 'Supports 120+ dog breeds',
                                },
                                {
                                    icon: <Sparkles size={13} />,
                                    text: 'Confidence score on every result',
                                },
                            ].map((item, i) => (
                                <div key={i} className="lp-check-row">
                                    <span
                                        style={{
                                            color: '#a78bfa',
                                            flexShrink: 0,
                                            marginTop: 2,
                                        }}
                                    >
                                        {item.icon}
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 13,
                                            color: 'rgba(255,255,255,.62)',
                                        }}
                                    >
                                        {item.text}
                                    </span>
                                </div>
                            ))}
                        </div>

                        <div
                            style={{
                                borderTop: '1px solid rgba(255,255,255,.1)',
                                paddingTop: 16,
                            }}
                        >
                            <Link href={login()} onClick={() => setOpen(false)}>
                                <button className="lp-outline-cta">
                                    Get Started Now
                                </button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

export default LandingPage;
