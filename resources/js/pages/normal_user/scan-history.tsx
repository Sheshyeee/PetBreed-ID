import Header from '@/components/header';
import { Link, router } from '@inertiajs/react';
import {
    Activity, Calendar, Camera, ChevronRight, Clock, Download, Filter,
    History, QrCode, Search, Shield, Smartphone, Trash2, TrendingUp, X, Zap,
} from 'lucide-react';
import { useState } from 'react';

interface Scan {
    id: number;
    scan_id: string;
    image: string;
    breed: string;
    confidence: number;
    date: string;
    status: 'pending' | 'verified';
}
interface User { name: string; email: string; avatar?: string; }
interface ScanHistoryProps { mockScans: Scan[]; user: User; }

const ScanHistory: React.FC<ScanHistoryProps> = ({ mockScans, user }) => {
    const [showQRModal, setShowQRModal] = useState(false);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState<'all' | 'verified' | 'pending'>('all');
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const handleDelete = (scanId: number) => {
        setDeletingId(scanId);
        router.delete(`/scanhistory/${scanId}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingId(null),
            onError: () => { setDeletingId(null); alert('Failed to delete scan. Please try again.'); },
        });
    };

    const filtered = mockScans.filter(s => {
        const matchSearch = s.breed.toLowerCase().includes(search.toLowerCase());
        const matchFilter = filter === 'all' || s.status === filter;
        return matchSearch && matchFilter;
    });

    const verifiedCount = mockScans.filter(s => s.status === 'verified').length;
    const pendingCount = mockScans.filter(s => s.status === 'pending').length;
    const avgConfidence = mockScans.length > 0 ? Math.round(mockScans.reduce((a, s) => a + s.confidence, 0) / mockScans.length) : 0;

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400;500&display=swap');

                html.dark {
                    --neon:#00FFB2; --neon2:#00D4FF; --nd:rgba(0,255,178,0.09);
                    --ng:0 0 20px rgba(0,255,178,0.3),0 0 50px rgba(0,255,178,0.1);
                    --bg:#07090C; --s1:#0D1014; --s2:#131720; --s3:#1A1F2A;
                    --br:rgba(255,255,255,0.06); --bn:rgba(0,255,178,0.2);
                    --t1:#ECF0F5; --t2:#68788F; --t3:#38404F;
                    --red:#FF4757; --amber:#FFB830;
                    --sh:0 8px 40px rgba(0,0,0,0.55),0 1px 0 rgba(255,255,255,0.04) inset;
                }
                html.light {
                    --neon:#009E6A; --neon2:#007BAA; --nd:rgba(0,158,106,0.08);
                    --ng:0 0 16px rgba(0,158,106,0.2),0 0 40px rgba(0,158,106,0.07);
                    --bg:#EFF1F6; --s1:#F8F9FC; --s2:#FFFFFF; --s3:#F2F4F8;
                    --br:rgba(0,0,0,0.07); --bn:rgba(0,158,106,0.25);
                    --t1:#0D1117; --t2:#556070; --t3:#99A0AE;
                    --red:#E03040; --amber:#D4900A;
                    --sh:0 4px 24px rgba(0,0,0,0.07),0 1px 0 rgba(255,255,255,0.9) inset;
                }
                :root {
                    --neon:#00FFB2; --neon2:#00D4FF; --nd:rgba(0,255,178,0.09);
                    --ng:0 0 20px rgba(0,255,178,0.3),0 0 50px rgba(0,255,178,0.1);
                    --bg:#07090C; --s1:#0D1014; --s2:#131720; --s3:#1A1F2A;
                    --br:rgba(255,255,255,0.06); --bn:rgba(0,255,178,0.2);
                    --t1:#ECF0F5; --t2:#68788F; --t3:#38404F;
                    --red:#FF4757; --amber:#FFB830;
                    --sh:0 8px 40px rgba(0,0,0,0.55),0 1px 0 rgba(255,255,255,0.04) inset;
                }

                .sh * { font-family: 'Syne', sans-serif !important; box-sizing: border-box; }
                .mono { font-family: 'DM Mono', monospace !important; }
                .sh { background: var(--bg); min-height: 100vh; position: relative; overflow-x: hidden; }

                .sh-grid { position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background-image: linear-gradient(var(--br) 1px, transparent 1px), linear-gradient(90deg, var(--br) 1px, transparent 1px);
                    background-size: 52px 52px;
                    mask-image: radial-gradient(ellipse 100% 60% at 50% 0%, black 30%, transparent 100%); }
                .sh-g1 { position: fixed; width: 600px; height: 300px; top: -160px; left: -100px; border-radius: 50%; background: radial-gradient(var(--neon), transparent 70%); filter: blur(90px); opacity: .08; pointer-events: none; z-index: 0; animation: gd 14s ease-in-out infinite alternate; }
                .sh-g2 { position: fixed; width: 400px; height: 250px; top: -100px; right: -60px; border-radius: 50%; background: radial-gradient(var(--neon2), transparent 70%); filter: blur(90px); opacity: .05; pointer-events: none; z-index: 0; animation: gd 14s ease-in-out infinite alternate-reverse; }
                @keyframes gd { from { transform: translateX(0) scale(1); } to { transform: translateX(50px) scale(1.08); } }
                .sh-z { position: relative; z-index: 1; }

                /* HUD */
                .hud { display: flex; align-items: center; background: var(--s1); border-bottom: 1px solid var(--br); padding: 5px 20px; overflow-x: auto; scrollbar-width: none; }
                .hud::-webkit-scrollbar { display: none; }
                .hud-it { display: flex; align-items: center; gap: 5px; padding: 3px 12px; font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--t2); border-right: 1px solid var(--br); white-space: nowrap; }
                .hud-it:last-child { border: none; }
                .hud-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
                .hud-val { color: var(--neon); font-size: 10px; }
                .hud-tick { margin-left: auto; font-size: 10px; color: var(--t3); padding-left: 12px; white-space: nowrap; animation: tf 1.2s ease-in-out infinite alternate; }
                @keyframes tf { from { opacity: .35; } to { opacity: 1; } }

                /* Layout */
                .wrap { max-width: 1260px; margin: 0 auto; padding: 24px 20px 72px; }

                /* Stat cards row */
                .stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
                @media(max-width: 860px) { .stat-row { grid-template-columns: repeat(2, 1fr); } }
                @media(max-width: 480px) { .stat-row { grid-template-columns: 1fr 1fr; } }

                .stat-c { background: var(--s2); border: 1px solid var(--br); border-radius: 14px; padding: 16px; position: relative; overflow: hidden; transition: all .25s; cursor: default; box-shadow: var(--sh); }
                .stat-c::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--neon), transparent); opacity: 0; transition: opacity .3s; }
                .stat-c:hover::before { opacity: 1; }
                .stat-c:hover { border-color: var(--bn); transform: translateY(-2px); box-shadow: var(--ng); }
                .stat-ic { width: 30px; height: 30px; border-radius: 8px; background: var(--nd); border: 1px solid var(--bn); display: flex; align-items: center; justify-content: center; color: var(--neon); margin-bottom: 10px; }
                .stat-lbl { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--t2); margin-bottom: 2px; }
                .stat-val { font-size: 20px; font-weight: 800; color: var(--t1); letter-spacing: -.02em; }
                .stat-sub { font-size: 10px; color: var(--t2); margin-top: 2px; }
                .stat-bar-bg { position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: var(--br); }
                .stat-bar-f { height: 100%; background: var(--neon); box-shadow: 0 0 6px var(--neon); border-radius: 2px; animation: sbg 1.8s ease-out forwards; }
                @keyframes sbg { from { width: 0; } }

                /* Info banner */
                .info-banner { background: rgba(0,212,255,0.05); border: 1px solid rgba(0,212,255,0.18); border-radius: 14px; padding: 16px 18px; margin-bottom: 22px; display: flex; gap: 12px; align-items: flex-start; }
                .info-icon { width: 32px; height: 32px; border-radius: 9px; background: rgba(0,212,255,.1); border: 1px solid rgba(0,212,255,.2); display: flex; align-items: center; justify-content: center; color: var(--neon2); flex-shrink: 0; }

                /* Toolbar */
                .toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
                .search-wrap { position: relative; flex: 1; min-width: 200px; }
                .search-ic { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--t3); pointer-events: none; }
                .search-inp { width: 100%; background: var(--s2); border: 1px solid var(--br); border-radius: 10px; padding: 9px 12px 9px 34px; font-size: 13px; color: var(--t1); outline: none; transition: all .2s; font-family: 'Syne', sans-serif; }
                .search-inp::placeholder { color: var(--t3); }
                .search-inp:focus { border-color: var(--bn); box-shadow: 0 0 0 3px var(--nd); }
                .filter-btn { display: flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; letter-spacing: .04em; border: 1px solid var(--br); background: var(--s2); color: var(--t2); cursor: pointer; transition: all .2s; white-space: nowrap; }
                .filter-btn:hover, .filter-btn.active { border-color: var(--bn); color: var(--neon); background: var(--nd); }
                .filter-btn.active { box-shadow: var(--ng); }
                .new-scan-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; background: var(--neon); color: #000; font-weight: 700; font-size: 13px; border: none; border-radius: 10px; cursor: pointer; text-decoration: none; transition: all .25s; white-space: nowrap; position: relative; overflow: hidden; }
                .new-scan-btn::before { content: ''; position: absolute; top: 0; left: -100%; width: 60%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,.28), transparent); transform: skewX(-20deg); transition: left .5s; }
                .new-scan-btn:hover { box-shadow: var(--ng); transform: translateY(-1px); }
                .new-scan-btn:hover::before { left: 160%; }

                /* Empty state */
                .empty { background: var(--s2); border: 1px solid var(--br); border-radius: 20px; padding: 60px 20px; text-align: center; position: relative; overflow: hidden; box-shadow: var(--sh); }
                .empty::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--neon), transparent); opacity: .3; }
                .empty-ic { width: 70px; height: 70px; border-radius: 50%; background: var(--nd); border: 1.5px solid var(--bn); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: var(--neon); position: relative; }
                .empty-ic::before { content: ''; position: absolute; inset: -8px; border-radius: 50%; border: 1px solid rgba(0,255,178,.1); animation: rp 2.5s ease-out infinite; }
                @keyframes rp { 0% { transform: scale(.9); opacity: .7; } 70% { transform: scale(1.1); opacity: 0; } 100% { transform: scale(1.1); opacity: 0; } }

                /* Grid */
                .scan-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
                @media(max-width: 1000px) { .scan-grid { grid-template-columns: repeat(2, 1fr); } }
                @media(max-width: 600px) { .scan-grid { grid-template-columns: 1fr; } }

                /* Scan card */
                .scan-card { background: var(--s2); border: 1px solid var(--br); border-radius: 16px; overflow: hidden; transition: all .25s; box-shadow: var(--sh); position: relative; animation: fadeup .4s ease both; }
                @keyframes fadeup { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
                .scan-card:hover { border-color: var(--bn); transform: translateY(-3px); box-shadow: var(--ng), var(--sh); }
                .scan-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--neon), transparent); opacity: 0; transition: opacity .3s; z-index: 3; }
                .scan-card:hover::before { opacity: .7; }

                /* Image */
                .scan-img-wrap { position: relative; height: 180px; overflow: hidden; }
                .scan-img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
                .scan-card:hover .scan-img { transform: scale(1.04); }
                .scan-img-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,.55) 100%); z-index: 1; }
                /* HUD corners on image */
                .sic { position: absolute; width: 16px; height: 16px; border-color: var(--neon); border-style: solid; opacity: 0; transition: opacity .3s; z-index: 2; }
                .scan-card:hover .sic { opacity: .8; }
                .sic-tl { top: 8px; left: 8px; border-width: 2px 0 0 2px; }
                .sic-tr { top: 8px; right: 8px; border-width: 2px 2px 0 0; }
                .sic-bl { bottom: 8px; left: 8px; border-width: 0 0 2px 2px; }
                .sic-br { bottom: 8px; right: 8px; border-width: 0 2px 2px 0; }
                /* Delete overlay on image */
                .del-overlay { position: absolute; top: 8px; right: 8px; z-index: 3; opacity: 0; transition: opacity .2s; }
                .scan-card:hover .del-overlay { opacity: 1; }
                .del-btn-sm { width: 30px; height: 30px; border-radius: 8px; background: rgba(255,71,87,.85); backdrop-filter: blur(8px); border: 1px solid rgba(255,71,87,.5); color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .2s; }
                .del-btn-sm:hover { background: var(--red); box-shadow: 0 0 14px rgba(255,71,87,.5); }
                /* Confidence badge on image */
                .conf-badge { position: absolute; bottom: 8px; right: 8px; z-index: 2; font-size: 11px; font-weight: 700; color: #000; background: var(--neon); border-radius: 6px; padding: 2px 8px; letter-spacing: .02em; }

                /* Card body */
                .card-body { padding: 14px; }
                .card-breed { font-size: 16px; font-weight: 700; color: var(--t1); margin: 0 0 10px; letter-spacing: -.01em; line-height: 1.2; }
                .card-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
                .card-date { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--t2); }
                .badge-v { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 100px; font-size: 10px; font-weight: 700; letter-spacing: .05em; }
                .badge-verified { background: rgba(0,255,178,.1); border: 1px solid rgba(0,255,178,.25); color: var(--neon); }
                .badge-pending { background: rgba(255,184,48,.08); border: 1px solid rgba(255,184,48,.22); color: var(--amber); }

                /* Confidence bar in body */
                .conf-row { margin-bottom: 12px; }
                .conf-label { display: flex; justify-content: space-between; margin-bottom: 5px; }
                .conf-txt { font-size: 10px; color: var(--t2); font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
                .conf-pct { font-size: 11px; color: var(--neon); font-weight: 700; }
                .conf-bar-bg { height: 4px; background: var(--br); border-radius: 3px; overflow: hidden; }
                .conf-bar-f { height: 100%; background: linear-gradient(90deg, var(--neon), var(--neon2)); border-radius: 3px; box-shadow: 0 0 6px var(--neon); animation: sbg 1.5s ease-out forwards; }

                /* Card footer */
                .card-footer { border-top: 1px solid var(--br); padding: 10px 14px; display: flex; align-items: center; gap: 8px; }
                .scan-id-txt { font-size: 9px; color: var(--t3); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                .del-btn { display: flex; align-items: center; gap: 5px; background: transparent; border: 1px solid var(--br); border-radius: 7px; color: var(--red); font-size: 11px; font-weight: 600; padding: 5px 10px; cursor: pointer; transition: all .2s; }
                .del-btn:hover { background: rgba(255,71,87,.08); border-color: rgba(255,71,87,.3); }
                .del-btn:disabled { opacity: .4; cursor: not-allowed; }

                /* Page header */
                .pg-ey { display: inline-flex; align-items: center; gap: 7px; padding: 3px 11px 3px 7px; background: var(--nd); border: 1px solid var(--bn); border-radius: 100px; margin-bottom: 9px; }
                .pg-ed { width: 7px; height: 7px; border-radius: 50%; background: var(--neon); box-shadow: 0 0 8px var(--neon); animation: sdp 2s infinite; }
                @keyframes sdp { 0%,100% { transform: scale(1); box-shadow: 0 0 8px var(--neon); } 50% { transform: scale(1.3); box-shadow: 0 0 16px var(--neon); } }
                .pg-et { font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--neon); }

                /* QR FAB */
                .qfab { position: fixed; bottom: 26px; right: 26px; z-index: 40; width: 48px; height: 48px; border-radius: 13px; background: var(--neon); color: #000; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 22px rgba(0,255,178,.38); transition: all .25s; }
                .qfab::before { content: ''; position: absolute; inset: -3px; border-radius: 16px; border: 1.5px solid rgba(0,255,178,.28); animation: fr 2s ease-out infinite; }
                @keyframes fr { 0% { transform: scale(1); opacity: .8; } 100% { transform: scale(1.28); opacity: 0; } }
                .qfab:hover { transform: scale(1.08) translateY(-3px); box-shadow: 0 8px 34px rgba(0,255,178,.52); }

                /* QR Modal */
                .qrov { position: fixed; inset: 0; background: rgba(0,0,0,.78); backdrop-filter: blur(16px); z-index: 50; display: flex; align-items: center; justify-content: center; padding: 16px; animation: ovin .2s ease; }
                @keyframes ovin { from { opacity: 0; } to { opacity: 1; } }
                .qrmod { background: var(--s2); border: 1px solid var(--br); border-radius: 22px; padding: 30px; max-width: 400px; width: 100%; position: relative; animation: mu .3s cubic-bezier(.16,1,.3,1); box-shadow: var(--sh); }
                @keyframes mu { from { transform: translateY(18px) scale(.97); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
                .qrc { position: absolute; width: 15px; height: 15px; border-color: var(--neon); border-style: solid; opacity: .45; }
                .qrc-tl { top: 9px; left: 9px; border-width: 2px 0 0 2px; } .qrc-tr { top: 9px; right: 9px; border-width: 2px 2px 0 0; }
                .qrc-bl { bottom: 9px; left: 9px; border-width: 0 0 2px 2px; } .qrc-br { bottom: 9px; right: 9px; border-width: 0 2px 2px 0; }
                .qrx { position: absolute; top: 13px; right: 13px; width: 30px; height: 30px; border-radius: 7px; background: var(--s1); border: 1px solid var(--br); color: var(--t2); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .2s; }
                .qrx:hover { color: var(--t1); border-color: var(--bn); }
                .mf { display: flex; align-items: center; gap: 9px; padding: 9px 0; border-bottom: 1px solid var(--br); color: var(--t2); font-size: 13px; }
                .mf:last-child { border: none; }
                .mf-ic { width: 26px; height: 26px; border-radius: 6px; background: var(--nd); border: 1px solid var(--bn); display: flex; align-items: center; justify-content: center; color: var(--neon); flex-shrink: 0; }

                .btn-n { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: var(--neon); color: #000; font-weight: 700; font-size: 14px; border: none; border-radius: 11px; padding: 13px 26px; cursor: pointer; text-decoration: none; transition: all .25s; }
                .btn-n:hover { box-shadow: var(--ng); transform: translateY(-1px); }
            `}</style>

            <div className="sh">
                <div className="sh-grid" /><div className="sh-g1" /><div className="sh-g2" />
                <Header />

                {/* HUD */}
                <div className="hud sh-z">
                    {[{ l: 'System', v: 'ONLINE', c: '#00FFB2' }, { l: 'Records', v: `${mockScans.length}`, c: '#00D4FF' }, { l: 'Verified', v: `${verifiedCount}`, c: '#00FFB2' }, { l: 'Pending', v: `${pendingCount}`, c: '#FFB830' }].map((x, i) => (
                        <div className="hud-it mono" key={i}>
                            <div className="hud-dot" style={{ background: x.c, boxShadow: `0 0 6px ${x.c}` }} />
                            <span>{x.l}</span><span className="hud-val mono">{x.v}</span>
                        </div>
                    ))}
                    <div className="hud-tick mono">▶ SCAN HISTORY</div>
                </div>

                {/* QR Modal */}
                {showQRModal && (
                    <div className="qrov" onClick={() => setShowQRModal(false)}>
                        <div className="qrmod" onClick={e => e.stopPropagation()}>
                            <div className="qrc qrc-tl" /><div className="qrc qrc-tr" /><div className="qrc qrc-bl" /><div className="qrc qrc-br" />
                            <button className="qrx" onClick={() => setShowQRModal(false)}><X size={13} /></button>
                            <div style={{ textAlign: 'center', marginBottom: 22 }}>
                                <div style={{ width: 48, height: 48, background: 'var(--nd)', border: '1.5px solid var(--bn)', borderRadius: 13, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 12px' }}>
                                    <Smartphone size={22} color="var(--neon)" />
                                </div>
                                <h2 style={{ color: 'var(--t1)', fontSize: 19, fontWeight: 800, margin: 0 }}>Install Mobile App</h2>
                                <p style={{ color: 'var(--t2)', fontSize: 12, marginTop: 5 }}>Scan to download the Android app</p>
                            </div>
                            <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 20 }}>
                                <div style={{ background: '#fff', borderRadius: 12, padding: 9 }}>
                                    <img src="/doglens_apk_qr.jpeg" alt="QR" style={{ width: 140, height: 140, display: 'block' }} />
                                </div>
                            </div>
                            <div style={{ background: 'var(--s1)', border: '1px solid var(--br)', borderRadius: 11, overflow: 'hidden', marginBottom: 16 }}>
                                {[{ icon: <Download size={12} />, text: 'Fast & Easy Installation' }, { icon: <Smartphone size={12} />, text: 'Available on Android' }, { icon: <Camera size={12} />, text: 'All Features On-The-Go' }].map((f, i) => (
                                    <div className="mf" key={i} style={{ padding: '9px 13px' }}><div className="mf-ic">{f.icon}</div>{f.text}</div>
                                ))}
                            </div>
                            <button className="btn-n" style={{ width: '100%' }} onClick={() => setShowQRModal(false)}>Close</button>
                        </div>
                    </div>
                )}

                {/* FAB */}
                <button className="qfab" onClick={() => setShowQRModal(true)} title="Install App"><QrCode size={19} /></button>

                <div className="wrap sh-z">

                    {/* Page header */}
                    <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 24, gap: 16, flexWrap: 'wrap' }}>
                        <div>
                            <div className="pg-ey"><span className="pg-ed" /><span className="pg-et">Scan Records</span></div>
                            <h1 style={{ color: 'var(--t1)', fontSize: 'clamp(22px,3.5vw,30px)', fontWeight: 800, margin: 0, letterSpacing: '-.025em', lineHeight: 1.1 }}>My Scan History</h1>
                            <p style={{ color: 'var(--t2)', fontSize: 13, marginTop: 6, lineHeight: 1.6 }}>View and manage your pet breed identification scans.</p>
                        </div>
                        <Link href="/scan" className="new-scan-btn">
                            <Zap size={15} />New Scan<ChevronRight size={13} style={{ opacity: .5 }} />
                        </Link>
                    </div>

                    {/* Stat row */}
                    <div className="stat-row">
                        {[
                            { icon: <History size={14} />, lbl: 'Total Scans', val: mockScans.length, sub: 'All time', bar: 100 },
                            { icon: <Shield size={14} />, lbl: 'Verified', val: verifiedCount, sub: 'By licensed vets', bar: mockScans.length ? (verifiedCount / mockScans.length) * 100 : 0 },
                            { icon: <Clock size={14} />, lbl: 'Pending', val: pendingCount, sub: 'Awaiting review', bar: mockScans.length ? (pendingCount / mockScans.length) * 100 : 0 },
                            { icon: <TrendingUp size={14} />, lbl: 'Avg Confidence', val: `${avgConfidence}%`, sub: 'Accuracy score', bar: avgConfidence },
                        ].map((s, i) => (
                            <div className="stat-c" key={i}>
                                <div className="stat-ic">{s.icon}</div>
                                <div className="stat-lbl mono">{s.lbl}</div>
                                <div className="stat-val">{s.val}</div>
                                <div className="stat-sub mono">{s.sub}</div>
                                <div className="stat-bar-bg"><div className="stat-bar-f" style={{ width: `${s.bar}%`, animationDelay: `${i * .15}s` }} /></div>
                            </div>
                        ))}
                    </div>

                    {/* Vet info banner */}
                    <div className="info-banner">
                        <div className="info-icon"><Shield size={15} /></div>
                        <div>
                            <p style={{ color: 'var(--neon2)', fontWeight: 700, fontSize: 13, margin: '0 0 4px' }}>Veterinarian Verification</p>
                            <p style={{ color: 'var(--t2)', fontSize: 12, margin: 0, lineHeight: 1.6 }}>
                                All system breed identifications can be reviewed by licensed veterinarians. Verified scans are confirmed by professional vets, while pending scans await review. This ensures the most reliable breed information for your pet.
                            </p>
                        </div>
                    </div>

                    {/* Toolbar */}
                    <div className="toolbar">
                        <div className="search-wrap">
                            <Search size={14} className="search-ic" />
                            <input className="search-inp" placeholder="Search breeds..." value={search} onChange={e => setSearch(e.target.value)} />
                        </div>
                        {(['all', 'verified', 'pending'] as const).map(f => (
                            <button key={f} className={`filter-btn ${filter === f ? 'active' : ''}`} onClick={() => setFilter(f)}>
                                <Filter size={11} />{f.charAt(0).toUpperCase() + f.slice(1)}
                            </button>
                        ))}
                    </div>

                    {/* Empty state */}
                    {mockScans.length === 0 && (
                        <div className="empty">
                            <div className="empty-ic"><Calendar size={28} /></div>
                            <h3 style={{ color: 'var(--t1)', fontSize: 20, fontWeight: 700, margin: '0 0 8px' }}>No scans yet</h3>
                            <p style={{ color: 'var(--t2)', fontSize: 14, marginBottom: 24 }}>Start by scanning your first pet!</p>
                            <Link href="/scan" className="new-scan-btn" style={{ display: 'inline-flex' }}><Zap size={15} />Scan Your Pet</Link>
                        </div>
                    )}

                    {/* Scan grid */}
                    {filtered.length > 0 && (
                        <div className="scan-grid">
                            {filtered.map((scan, idx) => (
                                <div className="scan-card" key={scan.id} style={{ animationDelay: `${idx * .05}s` }}>
                                    {/* Image */}
                                    <div className="scan-img-wrap">
                                        <img src={scan.image} alt={scan.breed} className="scan-img" />
                                        <div className="scan-img-overlay" />
                                        <div className="sic sic-tl" /><div className="sic sic-tr" /><div className="sic sic-bl" /><div className="sic sic-br" />
                                        <div className="del-overlay">
                                            <button className="del-btn-sm" onClick={() => handleDelete(scan.id)} title="Delete" disabled={deletingId === scan.id}>
                                                <Trash2 size={13} />
                                            </button>
                                        </div>
                                        <div className="conf-badge mono">{scan.confidence}%</div>
                                    </div>

                                    {/* Body */}
                                    <div className="card-body">
                                        <h3 className="card-breed">{scan.breed}</h3>

                                        {/* Confidence bar */}
                                        <div className="conf-row">
                                            <div className="conf-label">
                                                <span className="conf-txt mono">Confidence</span>
                                                <span className="conf-pct mono">{scan.confidence}%</span>
                                            </div>
                                            <div className="conf-bar-bg"><div className="conf-bar-f" style={{ width: `${scan.confidence}%` }} /></div>
                                        </div>

                                        <div className="card-meta">
                                            <div className="card-date">
                                                <Calendar size={12} color="var(--t3)" />
                                                <span className="mono" style={{ fontSize: 11 }}>{scan.date}</span>
                                            </div>
                                            <span className={`badge-v ${scan.status === 'verified' ? 'badge-verified' : 'badge-pending'}`}>
                                                {scan.status === 'verified' ? (
                                                    <><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><polyline points="20 6 9 17 4 12" /></svg>Verified</>
                                                ) : (
                                                    <><svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>Pending</>
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Footer */}
                                    <div className="card-footer">
                                        <span className="scan-id-txt mono">{scan.scan_id}</span>
                                        <button className="del-btn" onClick={() => handleDelete(scan.id)} disabled={deletingId === scan.id}>
                                            <Trash2 size={11} />{deletingId === scan.id ? 'Deleting...' : 'Delete'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* No results from filter/search */}
                    {mockScans.length > 0 && filtered.length === 0 && (
                        <div className="empty">
                            <div className="empty-ic"><Search size={28} /></div>
                            <h3 style={{ color: 'var(--t1)', fontSize: 18, fontWeight: 700, margin: '0 0 8px' }}>No results found</h3>
                            <p style={{ color: 'var(--t2)', fontSize: 13 }}>Try adjusting your search or filter.</p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
};

export default ScanHistory;