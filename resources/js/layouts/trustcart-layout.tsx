import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { SharedProps } from '@/types/trustcart';

const nav = [
    { href: '/assistant', label: 'Assistant' },
    { href: '/catalog', label: 'Catalog' },
    { href: '/audit', label: 'Audit trail' },
];

function SealMark() {
    return (
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
            className="h-[22px] w-[22px] shrink-0"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.6"
        >
            <rect x="1.5" y="1.5" width="21" height="21" />
            <path d="M6.5 12.4l3.4 3.4 7.6-7.9" />
        </svg>
    );
}

function LiveStatus() {
    const { demo } = usePage<SharedProps>().props;

    return (
        <div className="border-b border-rule bg-ink text-paper">
            <div className="mx-auto flex w-full max-w-[1180px] flex-wrap items-center justify-between gap-x-6 gap-y-1 px-5 py-1.5 text-[10px] tracking-[0.16em] uppercase sm:px-8">
                <span className="hidden sm:inline">
                    Sandbox environment · synthetic catalog · no real money
                </span>
                <span className="sm:hidden">Sandbox · synthetic catalog</span>
                <span className="flex items-center gap-3">
                    <span className="flex items-center gap-1.5">
                        <span
                            className={`h-1.5 w-1.5 rounded-full ${
                                demo?.paypal_configured
                                    ? 'bg-pass'
                                    : 'bg-signal'
                            }`}
                        />
                        <span className="hidden sm:inline">
                            {demo?.paypal_configured
                                ? 'PayPal sandbox connected'
                                : 'PayPal sandbox not configured'}
                        </span>
                        <span className="sm:hidden">
                            {demo?.paypal_configured
                                ? 'PayPal ready'
                                : 'PayPal off'}
                        </span>
                    </span>
                    <span className="hidden sm:inline">
                        {demo?.ai_configured
                            ? `AI: ${demo.ai_provider}`
                            : 'AI: fallback parser'}
                    </span>
                </span>
            </div>
        </div>
    );
}

export default function TrustCartLayout({ children }: { children: ReactNode }) {
    const page = usePage<SharedProps>();
    const url = page.url;
    const flash = page.props.flash;

    const isActive = (href: string) =>
        href === '/assistant'
            ? url.startsWith('/assistant')
            : url.startsWith(href);

    return (
        <div className="flex min-h-screen flex-col">
            <header>
                <div className="mx-auto flex w-full max-w-[1180px] items-center justify-between gap-6 px-5 py-4 sm:px-8">
                    <Link
                        href="/"
                        className="flex items-center gap-3 text-ink"
                        aria-label="TrustCart AI, home"
                    >
                        <SealMark />
                        <span className="flex items-baseline gap-3">
                            <span className="text-[13px] font-bold tracking-[0.18em] sm:text-[15px] sm:tracking-[0.2em]">
                                TRUSTCART
                            </span>
                            <span className="hidden text-[10px] tracking-[0.2em] text-muted uppercase sm:inline">
                                AI decision engine
                            </span>
                        </span>
                    </Link>

                    <nav
                        aria-label="Primary"
                        className="flex items-center gap-3 text-[10px] tracking-[0.12em] uppercase sm:gap-7 sm:text-[11px] sm:tracking-[0.14em]"
                    >
                        {nav.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={
                                    isActive(item.href) ? 'page' : undefined
                                }
                                className={`pb-0.5 transition-colors ${
                                    isActive(item.href)
                                        ? 'border-b border-signal text-ink'
                                        : 'text-ink-soft hover:text-ink'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                </div>
                <LiveStatus />
                {flash?.error && (
                    <div className="border-b border-rule bg-fail/5">
                        <div className="mx-auto w-full max-w-[1180px] px-5 py-3 text-[13px] text-fail sm:px-8">
                            <span className="mr-2 text-[10px] font-semibold tracking-[0.12em] uppercase">
                                Error
                            </span>
                            {flash.error}
                        </div>
                    </div>
                )}
                {!flash?.error && flash?.info && (
                    <div className="border-b border-rule bg-signal/10">
                        <div className="mx-auto w-full max-w-[1180px] px-5 py-3 text-[13px] text-ink sm:px-8">
                            <span className="mr-2 text-[10px] font-semibold tracking-[0.12em] uppercase">
                                Catalog update
                            </span>
                            {flash.info}
                        </div>
                    </div>
                )}
                {!flash?.error && !flash?.info && flash?.success && (
                    <div className="border-b border-rule bg-pass/5">
                        <div className="mx-auto w-full max-w-[1180px] px-5 py-3 text-[13px] text-pass sm:px-8">
                            <span className="mr-2 text-[10px] font-semibold tracking-[0.12em] uppercase">
                                Confirmed
                            </span>
                            {flash.success}
                        </div>
                    </div>
                )}
            </header>

            <main className="mx-auto w-full max-w-[1180px] flex-1 px-5 sm:px-8">
                {children}
            </main>

            <footer className="mt-20 border-t border-rule">
                <div className="mx-auto flex w-full max-w-[1180px] flex-col justify-between gap-4 px-5 py-8 text-[11px] text-muted sm:flex-row sm:items-end sm:px-8">
                    <div className="space-y-1.5">
                        <p className="font-semibold tracking-[0.16em] text-ink uppercase">
                            TrustCart AI
                        </p>
                        <p>AI that knows when not to buy.</p>
                        <p>
                            Fictional products and merchants. PayPal sandbox
                            only. No real money moves.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-x-6 gap-y-2">
                        <Link href="/assistant" className="hover:text-ink">
                            Run a decision
                        </Link>
                        <Link href="/catalog" className="hover:text-ink">
                            Catalog evidence
                        </Link>
                        <Link href="/audit" className="hover:text-ink">
                            Audit record
                        </Link>
                        <span>PayPal AI Hackathon 2026</span>
                    </div>
                </div>
            </footer>
        </div>
    );
}
