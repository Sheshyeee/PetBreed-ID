import Header from '@/components/header';
import { Link, router } from '@inertiajs/react';
import {
    Calendar,
    Camera,
    ChevronRight,
    Clock,
    Download,
    Filter,
    History,
    QrCode,
    Search,
    Shield,
    Smartphone,
    Trash2,
    TrendingUp,
    X,
    Zap,
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
interface User {
    name: string;
    email: string;
    avatar?: string;
}
interface Props {
    mockScans: Scan[];
    user: User;
}

export default function ScanHistory({ mockScans, user }: Props) {
    const [showQRModal, setShowQRModal] = useState(false);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState<'all' | 'verified' | 'pending'>('all');
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [hoveredId, setHoveredId] = useState<number | null>(null);

    const handleDelete = (id: number) => {
        setDeletingId(id);
        router.delete(`/scanhistory/${id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingId(null),
            onError: () => {
                setDeletingId(null);
                alert('Failed to delete. Please try again.');
            },
        });
    };

    const filtered = mockScans.filter(
        (s) =>
            s.breed.toLowerCase().includes(search.toLowerCase()) &&
            (filter === 'all' || s.status === filter),
    );

    const verifiedCount = mockScans.filter((s) => s.status === 'verified').length;
    const pendingCount = mockScans.filter((s) => s.status === 'pending').length;
    const avgConfidence =
        mockScans.length > 0
            ? Math.round(mockScans.reduce((a, s) => a + s.confidence, 0) / mockScans.length)
            : 0;

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap');

                *, *::before, *::after { box-sizing: border-box; }

                @keyframes float-slow  { 0%,100%{transform:translateY(0) rotate(0deg)} 50%{transform:translateY(-18px) rotate(1.5deg)} }
                @keyframes float-med   { 0%,100%{transform:translateY(0) rotate(0deg)} 50%{transform:translateY(-12px) rotate(-1deg)} }
                @keyframes pulse-dot   { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.7)} }
                @keyframes scan-line   { 0%{top:0;opacity:.7} 100%{top:100%;opacity:0} }
                @keyframes bar-fill    { from{width:0} }
                @keyframes fade-up     { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
                @keyframes slide-in    { from{opacity:0;transform:translateX(-16px)} to{opacity:1;transform:translateX(0)} }
                @keyframes modal-pop   { from{opacity:0;transform:scale(.94) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
                @keyframes ring-expand { 0%{transform:scale(1);opacity:.6} 100%{transform:scale(1.5);opacity:0} }
                @keyframes ticker-blink{ 0%,100%{opacity:1} 50%{opacity:.3} }
                @keyframes shimmer     { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
                @keyframes glow-drift  { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(30px,-20px) scale(1.06)} 66%{transform:translate(-20px,15px) scale(.96)} }
                @keyframes counter-up  { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
                @keyframes border-spin { from{--angle:0deg} to{--angle:360deg} }

                .sh-root {
                    font-family: 'DM Sans', sans-serif;
                    min-height: 100vh;
                    background: #070A0E;
                    color: #e2e8f0;
                    overflow-x: hidden;
                }

                /* === BACKGROUND CANVAS === */
                .bg-canvas {
                    position: fixed; inset: 0; z-index: 0; pointer-events: none;
                    background:
                        radial-gradient(ellipse 60% 40% at 20% 10%, rgba(16,185,129,.07) 0%, transparent 70%),
                        radial-gradient(ellipse 50% 35% at 80% 85%, rgba(6,182,212,.05) 0%, transparent 70%),
                        radial-gradient(ellipse 40% 30% at 50% 50%, rgba(16,185,129,.03) 0%, transparent 60%);
                }
                .bg-grid {
                    position: fixed; inset: 0; z-index: 0; pointer-events: none;
                    background-image:
                        linear-gradient(rgba(16,185,129,.04) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(16,185,129,.04) 1px, transparent 1px);
                    background-size: 48px 48px;
                    mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black, transparent);
                }

                /* === FLOATING ORBS === */
                .orb { position: fixed; border-radius: 50%; filter: blur(80px); pointer-events: none; z-index: 0; }
                .orb-1 { width:500px; height:500px; background:rgba(16,185,129,.04); top:-200px; left:-150px; animation: glow-drift 20s ease-in-out infinite; }
                .orb-2 { width:350px; height:350px; background:rgba(6,182,212,.035); bottom:-100px; right:-80px; animation: glow-drift 25s ease-in-out infinite reverse; }

                /* === HERO SECTION === */
                .hero-section {
                    position: relative; z-index: 10;
                    padding: 56px 0 48px;
                    border-bottom: 1px solid rgba(255,255,255,.05);
                }
                .hero-eyebrow {
                    display: inline-flex; align-items: center; gap: 8px;
                    font-family: 'Space Mono', monospace;
                    font-size: 10px; font-weight: 700;
                    letter-spacing: .18em; text-transform: uppercase;
                    color: #10b981;
                    background: rgba(16,185,129,.07);
                    border: 1px solid rgba(16,185,129,.18);
                    padding: 5px 12px; border-radius: 100px;
                    margin-bottom: 20px;
                }
                .hero-eyebrow-dot {
                    width: 6px; height: 6px; border-radius: 50%;
                    background: #10b981;
                    animation: ticker-blink 1.8s ease-in-out infinite;
                }
                .hero-title {
                    font-family: 'Syne', sans-serif;
                    font-size: clamp(2.4rem, 5vw, 4rem);
                    font-weight: 800;
                    line-height: 1;
                    letter-spacing: -.03em;
                    color: #fff;
                    margin: 0 0 12px;
                }
                .hero-title span {
                    background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .hero-sub {
                    font-size: 14px; color: rgba(226,232,240,.45);
                    line-height: 1.7; max-width: 380px;
                }

                /* === NEW SCAN BUTTON === */
                .btn-newscan {
                    position: relative; overflow: hidden;
                    display: inline-flex; align-items: center; gap: 8px;
                    font-family: 'Syne', sans-serif;
                    font-size: 13px; font-weight: 700; letter-spacing: .01em;
                    color: #000;
                    background: linear-gradient(135deg, #10b981, #06b6d4);
                    border: none; border-radius: 14px;
                    padding: 13px 22px; cursor: pointer;
                    text-decoration: none;
                    box-shadow: 0 0 30px rgba(16,185,129,.25), 0 4px 20px rgba(0,0,0,.3);
                    transition: transform .2s, box-shadow .2s;
                }
                .btn-newscan::before {
                    content:''; position:absolute; inset:0;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
                    transform: translateX(-100%) skewX(-15deg);
                    transition: transform .5s;
                }
                .btn-newscan:hover { transform: translateY(-2px); box-shadow: 0 0 45px rgba(16,185,129,.4), 0 8px 30px rgba(0,0,0,.4); }
                .btn-newscan:hover::before { transform: translateX(200%) skewX(-15deg); }

                /* === STATS STRIP (horizontal, big numbers) === */
                .stats-strip {
                    position: relative; z-index: 10;
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 0;
                    border: 1px solid rgba(255,255,255,.06);
                    border-radius: 20px;
                    overflow: hidden;
                    margin-bottom: 40px;
                    background: rgba(255,255,255,.02);
                    backdrop-filter: blur(12px);
                }
                .stat-cell {
                    position: relative;
                    padding: 28px 28px 24px;
                    transition: background .25s;
                    animation: fade-up .5s ease both;
                }
                .stat-cell:not(:last-child) {
                    border-right: 1px solid rgba(255,255,255,.05);
                }
                .stat-cell:hover { background: rgba(16,185,129,.04); }
                .stat-cell-icon {
                    width: 34px; height: 34px; border-radius: 10px;
                    background: rgba(16,185,129,.1);
                    border: 1px solid rgba(16,185,129,.18);
                    display: flex; align-items: center; justify-content: center;
                    color: #10b981; margin-bottom: 16px;
                }
                .stat-label {
                    font-family: 'Space Mono', monospace;
                    font-size: 9px; font-weight: 700;
                    letter-spacing: .14em; text-transform: uppercase;
                    color: rgba(226,232,240,.3); margin-bottom: 6px;
                }
                .stat-number {
                    font-family: 'Syne', sans-serif;
                    font-size: clamp(1.8rem, 3vw, 2.6rem);
                    font-weight: 800; line-height: 1;
                    letter-spacing: -.03em; color: #fff;
                    margin-bottom: 4px;
                    animation: counter-up .6s ease both;
                }
                .stat-sub {
                    font-size: 11px; color: rgba(226,232,240,.35);
                }
                .stat-bar-track {
                    position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
                    background: rgba(255,255,255,.05);
                }
                .stat-bar-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #10b981, #06b6d4);
                    box-shadow: 0 0 8px rgba(16,185,129,.5);
                    animation: bar-fill 1.6s cubic-bezier(.16,1,.3,1) forwards;
                }

                /* === VET BANNER === */
                .vet-banner {
                    position: relative; z-index: 10;
                    display: flex; align-items: flex-start; gap: 16px;
                    background: linear-gradient(135deg, rgba(6,182,212,.06) 0%, rgba(16,185,129,.04) 100%);
                    border: 1px solid rgba(6,182,212,.15);
                    border-radius: 16px;
                    padding: 18px 22px;
                    margin-bottom: 36px;
                }
                .vet-banner-icon {
                    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
                    background: rgba(6,182,212,.1); border: 1px solid rgba(6,182,212,.2);
                    display: flex; align-items: center; justify-content: center;
                    color: #06b6d4;
                }
                .vet-banner-title {
                    font-family: 'Syne', sans-serif;
                    font-size: 13px; font-weight: 700; color: #06b6d4; margin-bottom: 4px;
                }
                .vet-banner-text {
                    font-size: 12px; color: rgba(226,232,240,.45); line-height: 1.65;
                }

                /* === TOOLBAR === */
                .toolbar {
                    position: relative; z-index: 10;
                    display: flex; align-items: center; gap: 10px;
                    flex-wrap: wrap; margin-bottom: 32px;
                }
                .search-wrap {
                    position: relative; flex: 1; min-width: 200px;
                }
                .search-input {
                    width: 100%;
                    background: rgba(255,255,255,.04);
                    border: 1px solid rgba(255,255,255,.08);
                    border-radius: 12px;
                    padding: 11px 14px 11px 40px;
                    font-size: 13px; font-family: 'DM Sans', sans-serif;
                    color: #e2e8f0; outline: none;
                    transition: border-color .2s, background .2s;
                }
                .search-input::placeholder { color: rgba(226,232,240,.25); }
                .search-input:focus {
                    border-color: rgba(16,185,129,.35);
                    background: rgba(16,185,129,.04);
                }
                .search-icon {
                    position: absolute; top: 50%; left: 13px; transform: translateY(-50%);
                    color: rgba(226,232,240,.25); pointer-events: none;
                }
                .filter-pill {
                    display: flex; align-items: center; gap: 6px;
                    font-family: 'Space Mono', monospace;
                    font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
                    padding: 10px 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,.07);
                    background: rgba(255,255,255,.03);
                    color: rgba(226,232,240,.4);
                    cursor: pointer; transition: all .2s;
                }
                .filter-pill:hover { border-color: rgba(16,185,129,.25); color: #10b981; background: rgba(16,185,129,.04); }
                .filter-pill.active {
                    border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.1);
                    color: #10b981; box-shadow: 0 0 16px rgba(16,185,129,.1);
                }

                /* === SCAN GRID — 2-col masonry-ish === */
                .scan-grid {
                    position: relative; z-index: 10;
                    columns: 1; column-gap: 20px;
                }
                @media(min-width:640px) { .scan-grid { columns: 2; } }
                @media(min-width:1024px) { .scan-grid { columns: 3; } }

                /* === SCAN CARD === */
                .scan-card {
                    display: inline-block; width: 100%;
                    margin-bottom: 20px;
                    border-radius: 20px;
                    overflow: hidden;
                    border: 1px solid rgba(255,255,255,.07);
                    background: rgba(255,255,255,.025);
                    backdrop-filter: blur(8px);
                    transition: transform .3s cubic-bezier(.16,1,.3,1), box-shadow .3s, border-color .3s;
                    animation: fade-up .45s ease both;
                    cursor: default;
                    position: relative;
                }
                .scan-card:hover {
                    transform: translateY(-4px);
                    border-color: rgba(16,185,129,.25);
                    box-shadow: 0 20px 60px rgba(0,0,0,.5), 0 0 30px rgba(16,185,129,.07);
                }
                /* top accent on hover */
                .scan-card::before {
                    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
                    background: linear-gradient(90deg, transparent, #10b981, transparent);
                    opacity: 0; transition: opacity .3s; z-index: 5;
                }
                .scan-card:hover::before { opacity: .7; }

                /* IMAGE WRAPPER — tall for visual drama */
                .card-img-wrap {
                    position: relative;
                    height: 220px;
                    overflow: hidden;
                }
                .card-img {
                    width: 100%; height: 100%; object-fit: cover;
                    transition: transform .6s cubic-bezier(.16,1,.3,1);
                }
                .scan-card:hover .card-img { transform: scale(1.07); }

                /* scan-line sweep on hover */
                .card-scan-line {
                    position: absolute; left: 0; right: 0; height: 2px;
                    background: linear-gradient(90deg, transparent, rgba(16,185,129,.7), transparent);
                    pointer-events: none; z-index: 4; top: 0; opacity: 0;
                    transition: opacity .1s;
                }
                .scan-card:hover .card-scan-line {
                    opacity: 1;
                    animation: scan-line 1.4s linear infinite;
                }

                /* gradient overlay */
                .card-img-overlay {
                    position: absolute; inset: 0;
                    background: linear-gradient(to top, rgba(7,10,14,.85) 0%, rgba(7,10,14,.2) 50%, transparent 100%);
                    z-index: 2;
                }

                /* HUD corners */
                .hud-corner {
                    position: absolute; width: 14px; height: 14px;
                    border-color: rgba(16,185,129,.6); border-style: solid;
                    opacity: 0; transition: opacity .25s; z-index: 3;
                }
                .scan-card:hover .hud-corner { opacity: 1; }
                .hud-tl { top: 8px; left: 8px; border-width: 2px 0 0 2px; }
                .hud-tr { top: 8px; right: 8px; border-width: 2px 2px 0 0; }
                .hud-bl { bottom: 8px; left: 8px; border-width: 0 0 2px 2px; }
                .hud-br { bottom: 8px; right: 8px; border-width: 0 2px 2px 0; }

                /* confidence badge on image */
                .conf-badge {
                    position: absolute; bottom: 10px; right: 10px; z-index: 4;
                    font-family: 'Space Mono', monospace;
                    font-size: 11px; font-weight: 700;
                    color: #000;
                    background: linear-gradient(135deg, #10b981, #06b6d4);
                    padding: 3px 10px; border-radius: 6px;
                    box-shadow: 0 2px 10px rgba(16,185,129,.4);
                }

                /* status on image */
                .status-badge {
                    position: absolute; top: 10px; left: 10px; z-index: 4;
                    font-family: 'Space Mono', monospace;
                    font-size: 9px; font-weight: 700; letter-spacing: .08em;
                    text-transform: uppercase;
                    padding: 3px 9px; border-radius: 6px;
                    backdrop-filter: blur(8px);
                }
                .status-verified {
                    background: rgba(16,185,129,.2);
                    border: 1px solid rgba(16,185,129,.35);
                    color: #10b981;
                }
                .status-pending {
                    background: rgba(245,158,11,.15);
                    border: 1px solid rgba(245,158,11,.3);
                    color: #f59e0b;
                }

                /* delete overlay on image */
                .delete-overlay {
                    position: absolute; top: 10px; right: 10px; z-index: 5;
                    opacity: 0; transition: opacity .2s;
                }
                .scan-card:hover .delete-overlay { opacity: 1; }
                .btn-del-img {
                    width: 30px; height: 30px; border-radius: 8px;
                    background: rgba(239,68,68,.7); border: 1px solid rgba(239,68,68,.5);
                    backdrop-filter: blur(8px); color: #fff;
                    display: flex; align-items: center; justify-content: center;
                    cursor: pointer; transition: background .2s;
                }
                .btn-del-img:hover { background: rgba(239,68,68,.9); }
                .btn-del-img:disabled { opacity: .4; cursor: not-allowed; }

                /* CARD BODY */
                .card-body { padding: 16px 18px 14px; }

                .card-breed {
                    font-family: 'Syne', sans-serif;
                    font-size: 17px; font-weight: 700;
                    color: #fff; letter-spacing: -.01em;
                    margin-bottom: 12px;
                    line-height: 1.2;
                }

                /* confidence bar in body */
                .conf-row {
                    display: flex; align-items: center; justify-content: space-between;
                    margin-bottom: 6px;
                }
                .conf-label {
                    font-family: 'Space Mono', monospace;
                    font-size: 9px; font-weight: 700; letter-spacing: .1em;
                    text-transform: uppercase; color: rgba(226,232,240,.3);
                }
                .conf-val {
                    font-family: 'Space Mono', monospace;
                    font-size: 11px; font-weight: 700;
                    color: #10b981;
                }
                .conf-track {
                    height: 3px; border-radius: 100px;
                    background: rgba(255,255,255,.07);
                    overflow: hidden; margin-bottom: 14px;
                }
                .conf-fill {
                    height: 100%; border-radius: 100px;
                    background: linear-gradient(90deg, #10b981, #06b6d4);
                    box-shadow: 0 0 6px rgba(16,185,129,.5);
                    animation: bar-fill 1.5s cubic-bezier(.16,1,.3,1) forwards;
                }

                /* CARD FOOTER */
                .card-footer {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 12px 18px 14px;
                    border-top: 1px solid rgba(255,255,255,.05);
                }
                .card-date {
                    display: flex; align-items: center; gap: 6px;
                    font-family: 'Space Mono', monospace;
                    font-size: 10px; color: rgba(226,232,240,.25);
                }
                .card-scan-id {
                    font-family: 'Space Mono', monospace;
                    font-size: 9px; color: rgba(226,232,240,.15);
                    max-width: 120px; white-space: nowrap; overflow: hidden;
                    text-overflow: ellipsis;
                }
                .btn-delete {
                    display: flex; align-items: center; gap: 5px;
                    font-family: 'Space Mono', monospace;
                    font-size: 10px; font-weight: 700; letter-spacing: .04em;
                    color: rgba(239,68,68,.55);
                    background: transparent; border: 1px solid rgba(239,68,68,.15);
                    border-radius: 8px; padding: 6px 12px; cursor: pointer;
                    transition: all .2s;
                }
                .btn-delete:hover { color: #ef4444; border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.05); }
                .btn-delete:disabled { opacity: .35; cursor: not-allowed; }

                /* === EMPTY STATES === */
                .empty-state {
                    position: relative; z-index: 10;
                    text-align: center; padding: 80px 20px;
                    border: 1px solid rgba(255,255,255,.06);
                    border-radius: 24px;
                    background: rgba(255,255,255,.02);
                    overflow: hidden;
                }
                .empty-icon-ring {
                    position: relative; display: inline-flex;
                    align-items: center; justify-content: center;
                    width: 80px; height: 80px;
                    border: 1px solid rgba(16,185,129,.15);
                    border-radius: 50%;
                    margin-bottom: 20px;
                    background: rgba(16,185,129,.07);
                    color: #10b981;
                }
                .empty-icon-ring::before {
                    content: ''; position: absolute; inset: -10px;
                    border-radius: 50%; border: 1px solid rgba(16,185,129,.08);
                    animation: ring-expand 2.5s ease-out infinite;
                }
                .empty-title {
                    font-family: 'Syne', sans-serif;
                    font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 8px;
                }
                .empty-sub { font-size: 13px; color: rgba(226,232,240,.35); }

                /* === QR MODAL === */
                .modal-bg {
                    position: fixed; inset: 0; z-index: 60;
                    display: flex; align-items: center; justify-content: center;
                    background: rgba(0,0,0,.75); padding: 16px;
                    backdrop-filter: blur(20px);
                }
                .modal-box {
                    width: 100%; max-width: 360px;
                    background: #0e1218;
                    border: 1px solid rgba(255,255,255,.08);
                    border-radius: 24px; padding: 28px;
                    position: relative; overflow: hidden;
                    animation: modal-pop .28s cubic-bezier(.16,1,.3,1) both;
                    box-shadow: 0 30px 80px rgba(0,0,0,.7);
                }
                .modal-box::before {
                    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1px;
                    background: linear-gradient(90deg, transparent, #10b981, #06b6d4, transparent);
                    opacity: .5;
                }

                /* === FAB === */
                .fab {
                    position: fixed; right: 20px; bottom: 20px; z-index: 40;
                    width: 46px; height: 46px; border-radius: 14px;
                    background: linear-gradient(135deg, #10b981, #06b6d4);
                    border: none; color: #000; cursor: pointer;
                    display: flex; align-items: center; justify-content: center;
                    box-shadow: 0 0 30px rgba(16,185,129,.3), 0 8px 24px rgba(0,0,0,.4);
                    transition: transform .2s, box-shadow .2s;
                    overflow: visible;
                }
                .fab::before {
                    content: ''; position: absolute; inset: -4px;
                    border-radius: 18px; border: 1.5px solid rgba(16,185,129,.25);
                    animation: ring-expand 2.5s ease-out infinite;
                }
                .fab:hover { transform: scale(1.08); }

                .nsb::-webkit-scrollbar { display: none; }
                .nsb { scrollbar-width: none; }
            `}</style>

            <div className="sh-root">
                {/* Ambient background */}
                <div className="bg-canvas" />
                <div className="bg-grid" />
                <div className="orb orb-1" />
                <div className="orb orb-2" />

                {/* Header */}
                <div style={{ position: 'relative', zIndex: 20 }}>
                    <Header />
                </div>

                {/* QR Modal */}
                {showQRModal && (
                    <div className="modal-bg" onClick={() => setShowQRModal(false)}>
                        <div className="modal-box" onClick={(e) => e.stopPropagation()}>
                            <button
                                onClick={() => setShowQRModal(false)}
                                style={{
                                    position: 'absolute', top: 12, right: 12,
                                    width: 28, height: 28, borderRadius: 8,
                                    background: 'rgba(255,255,255,.06)', border: '1px solid rgba(255,255,255,.08)',
                                    color: 'rgba(226,232,240,.5)', display: 'flex',
                                    alignItems: 'center', justifyContent: 'center',
                                    cursor: 'pointer',
                                }}
                            >
                                <X size={13} />
                            </button>
                            <div style={{ textAlign: 'center', marginBottom: 22 }}>
                                <div style={{
                                    width: 44, height: 44, borderRadius: 13,
                                    background: 'rgba(16,185,129,.1)', border: '1px solid rgba(16,185,129,.2)',
                                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    color: '#10b981', margin: '0 auto 14px',
                                }}>
                                    <Smartphone size={20} />
                                </div>
                                <div style={{ fontFamily: 'Syne, sans-serif', fontSize: 18, fontWeight: 800, color: '#fff', marginBottom: 4 }}>
                                    Install Mobile App
                                </div>
                                <div style={{ fontSize: 12, color: 'rgba(226,232,240,.35)' }}>Scan to download the Android app</div>
                            </div>
                            <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 22 }}>
                                <div style={{ background: '#fff', padding: 10, borderRadius: 14 }}>
                                    <img src="/doglens_apk_qr.jpeg" alt="QR" style={{ display: 'block', width: 128, height: 128 }} />
                                </div>
                            </div>
                            <div style={{ borderRadius: 12, overflow: 'hidden', border: '1px solid rgba(255,255,255,.07)', marginBottom: 18 }}>
                                {[
                                    { icon: <Download size={12} />, t: 'Fast & Easy Installation' },
                                    { icon: <Smartphone size={12} />, t: 'Available on Android' },
                                    { icon: <Camera size={12} />, t: 'All Features On-The-Go' },
                                ].map((f, i) => (
                                    <div key={i} style={{
                                        display: 'flex', alignItems: 'center', gap: 10,
                                        padding: '10px 14px', fontSize: 12,
                                        color: 'rgba(226,232,240,.45)',
                                        borderBottom: i < 2 ? '1px solid rgba(255,255,255,.05)' : 'none',
                                        background: 'rgba(255,255,255,.02)',
                                    }}>
                                        <div style={{
                                            width: 22, height: 22, borderRadius: 6, flexShrink: 0,
                                            background: 'rgba(16,185,129,.1)', color: '#10b981',
                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        }}>{f.icon}</div>
                                        {f.t}
                                    </div>
                                ))}
                            </div>
                            <button
                                onClick={() => setShowQRModal(false)}
                                style={{
                                    width: '100%', padding: '13px',
                                    background: 'linear-gradient(135deg, #10b981, #06b6d4)',
                                    border: 'none', borderRadius: 12,
                                    fontFamily: 'Syne, sans-serif', fontWeight: 800, fontSize: 13,
                                    color: '#000', cursor: 'pointer',
                                }}
                            >
                                Close
                            </button>
                        </div>
                    </div>
                )}

                {/* FAB */}
                <button className="fab" onClick={() => setShowQRModal(true)}>
                    <QrCode size={18} />
                </button>

                {/* PAGE BODY */}
                <div style={{ position: 'relative', zIndex: 10, maxWidth: 1280, margin: '0 auto', padding: '0 24px 80px' }}>

                    {/* HERO */}
                    <div className="hero-section" style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 20, flexWrap: 'wrap' }}>
                        <div>
                            <div className="hero-eyebrow">
                                <span className="hero-eyebrow-dot" />
                                Scan Records
                            </div>
                            <h1 className="hero-title">
                                My <span>Scan</span><br />History
                            </h1>
                            <p className="hero-sub">View and manage your pet breed identification scans.</p>
                        </div>
                        <Link href="/scan" className="btn-newscan">
                            <Zap size={14} />
                            New Scan
                            <ChevronRight size={13} style={{ opacity: .6 }} />
                        </Link>
                    </div>

                    {/* STATS STRIP */}
                    <div className="stats-strip" style={{ marginTop: 36 }}>
                        {[
                            { icon: <History size={15} />, lbl: 'Total Scans', val: mockScans.length, sub: 'All time', bar: 100 },
                            { icon: <Shield size={15} />, lbl: 'Verified', val: verifiedCount, sub: 'By licensed vets', bar: mockScans.length ? (verifiedCount / mockScans.length) * 100 : 0 },
                            { icon: <Clock size={15} />, lbl: 'Pending', val: pendingCount, sub: 'Awaiting review', bar: mockScans.length ? (pendingCount / mockScans.length) * 100 : 0 },
                            { icon: <TrendingUp size={15} />, lbl: 'Avg Confidence', val: `${avgConfidence}%`, sub: 'Accuracy score', bar: avgConfidence },
                        ].map((s, i) => (
                            <div key={i} className="stat-cell" style={{ animationDelay: `${i * 0.08}s` }}>
                                <div className="stat-cell-icon">{s.icon}</div>
                                <div className="stat-label">{s.lbl}</div>
                                <div className="stat-number">{s.val}</div>
                                <div className="stat-sub">{s.sub}</div>
                                <div className="stat-bar-track">
                                    <div className="stat-bar-fill" style={{ width: `${s.bar}%`, animationDelay: `${i * 0.12 + 0.3}s` }} />
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* VET BANNER */}
                    <div className="vet-banner">
                        <div className="vet-banner-icon"><Shield size={15} /></div>
                        <div>
                            <div className="vet-banner-title">Veterinarian Verification</div>
                            <div className="vet-banner-text">
                                All system breed identifications can be reviewed by licensed veterinarians. Verified scans are confirmed by professional vets, while pending scans await review.
                            </div>
                        </div>
                    </div>

                    {/* TOOLBAR */}
                    <div className="toolbar">
                        <div className="search-wrap">
                            <Search size={14} className="search-icon" />
                            <input
                                className="search-input"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search breeds…"
                            />
                        </div>
                        {(['all', 'verified', 'pending'] as const).map((f) => (
                            <button
                                key={f}
                                onClick={() => setFilter(f)}
                                className={`filter-pill${filter === f ? ' active' : ''}`}
                            >
                                <Filter size={10} />
                                {f.charAt(0).toUpperCase() + f.slice(1)}
                            </button>
                        ))}
                    </div>

                    {/* EMPTY: no scans */}
                    {mockScans.length === 0 && (
                        <div className="empty-state">
                            <div className="empty-icon-ring">
                                <Calendar size={30} />
                            </div>
                            <div className="empty-title">No scans yet</div>
                            <div className="empty-sub" style={{ marginBottom: 24 }}>Start by scanning your first pet!</div>
                            <Link href="/scan" className="btn-newscan" style={{ display: 'inline-flex' }}>
                                <Zap size={14} /> Scan Your Pet
                            </Link>
                        </div>
                    )}

                    {/* EMPTY: no results */}
                    {mockScans.length > 0 && filtered.length === 0 && (
                        <div className="empty-state">
                            <div className="empty-icon-ring">
                                <Search size={28} />
                            </div>
                            <div className="empty-title">No results found</div>
                            <div className="empty-sub">Try adjusting your search or filter.</div>
                        </div>
                    )}

                    {/* SCAN GRID */}
                    {filtered.length > 0 && (
                        <div className="scan-grid">
                            {filtered.map((scan, idx) => (
                                <div
                                    key={scan.id}
                                    className="scan-card"
                                    style={{ animationDelay: `${idx * 0.045}s` }}
                                    onMouseEnter={() => setHoveredId(scan.id)}
                                    onMouseLeave={() => setHoveredId(null)}
                                >
                                    {/* IMAGE */}
                                    <div className="card-img-wrap">
                                        <img src={scan.image} alt={scan.breed} className="card-img" />
                                        <div className="card-scan-line" />
                                        <div className="card-img-overlay" />

                                        {/* HUD corners */}
                                        <div className="hud-corner hud-tl" />
                                        <div className="hud-corner hud-tr" />
                                        <div className="hud-corner hud-bl" />
                                        <div className="hud-corner hud-br" />

                                        {/* Status badge */}
                                        <div className={`status-badge ${scan.status === 'verified' ? 'status-verified' : 'status-pending'}`}>
                                            {scan.status === 'verified' ? '✓ Verified' : '⏳ Pending'}
                                        </div>

                                        {/* Delete on image */}
                                        <div className="delete-overlay">
                                            <button
                                                className="btn-del-img"
                                                onClick={() => handleDelete(scan.id)}
                                                disabled={deletingId === scan.id}
                                            >
                                                <Trash2 size={12} />
                                            </button>
                                        </div>

                                        {/* Confidence badge */}
                                        <div className="conf-badge">{scan.confidence}%</div>
                                    </div>

                                    {/* BODY */}
                                    <div className="card-body">
                                        <div className="card-breed">{scan.breed}</div>

                                        <div className="conf-row">
                                            <span className="conf-label">Confidence</span>
                                            <span className="conf-val">{scan.confidence}%</span>
                                        </div>
                                        <div className="conf-track">
                                            <div className="conf-fill" style={{ width: `${scan.confidence}%` }} />
                                        </div>
                                    </div>

                                    {/* FOOTER */}
                                    <div className="card-footer">
                                        <div>
                                            <div className="card-date">
                                                <Calendar size={10} />
                                                {scan.date}
                                            </div>
                                            <div className="card-scan-id">{scan.scan_id}</div>
                                        </div>
                                        <button
                                            className="btn-delete"
                                            onClick={() => handleDelete(scan.id)}
                                            disabled={deletingId === scan.id}
                                        >
                                            <Trash2 size={10} />
                                            {deletingId === scan.id ? 'Deleting…' : 'Delete'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}