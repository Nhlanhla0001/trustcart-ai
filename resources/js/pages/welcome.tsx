import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    FileText,
    Lock,
    ShieldCheck,
    X,
} from 'lucide-react';
import type { Check as CheckRow } from '@/types/trustcart';

type Specimen = {
    requirements: { summary: string; budget: string };
    qualified: {
        name: string;
        price: string;
        merchant: string;
        score: number;
        checks: CheckRow[];
    } | null;
    cheaper: {
        name: string;
        price: string;
        reason: string;
        checks: CheckRow[];
    } | null;
    qualified_count: number;
    rejected_count: number;
    unverified_count: number;
};

const scenarios = [
    [
        'A',
        'Successful purchase',
        'Recommend → explicit approval → PayPal sandbox capture → verified status recorded.',
    ],
    [
        'B',
        'Unsuitable cheaper product',
        'The $54 option is refused because the catalog records no noise cancellation.',
    ],
    [
        'C',
        'Budget violation',
        'Anything above the stated ceiling fails before it can be ranked.',
    ],
    [
        'D',
        'Missing specification',
        'An absent spec is “unverified”, never a pass, and is shown to the buyer.',
    ],
    [
        'E',
        'Price change after approval',
        'A merchant sync voids the authorization and demands a new priced approval.',
    ],
    [
        'F',
        'Buyer cancels at PayPal',
        'No capture is attempted and no paid status is written.',
    ],
    [
        'G',
        'Duplicate capture attempt',
        'An atomic claim plus PayPal request IDs stop the second capture.',
    ],
    [
        'H',
        'Instruction-like catalog text',
        'The text is quarantined, displayed, and never sent to the model.',
    ],
];

function Stamp({ kind }: { kind: 'pass' | 'fail' | 'unverified' }) {
    const copy = {
        pass: ['Pass', 'text-pass border-pass/40'],
        fail: ['Fail', 'text-fail border-fail/40'],
        unverified: ['Unverified', 'text-warn border-warn/40'],
    }[kind];

    return (
        <span
            className={`border px-1.5 py-0.5 text-[9px] font-semibold tracking-[0.1em] uppercase ${copy[1]}`}
        >
            {copy[0]}
        </span>
    );
}

export default function Welcome({
    catalogSize,
    specimen,
}: {
    catalogSize: number;
    specimen: Specimen;
}) {
    return (
        <>
            <Head title="AI that knows when not to buy" />

            <section className="grid gap-10 py-12 lg:grid-cols-12 lg:gap-12 lg:py-16">
                <div className="lg:col-span-7">
                    <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] tracking-[0.2em] uppercase">
                        <span className="text-muted">
                            Agentic commerce, controlled
                        </span>
                        <span className="h-px w-8 bg-rule-strong" />
                        <span className="text-signal-deep">PayPal sandbox</span>
                    </p>

                    <h1 className="mt-6 text-[clamp(2.75rem,6.2vw,5.2rem)] leading-[0.94] font-bold tracking-[-0.045em]">
                        AI that knows
                        <br />
                        when <span className="text-signal-deep">not</span> to
                        buy.
                    </h1>

                    <p className="mt-7 max-w-[52ch] text-[17px] leading-[1.55] text-ink-soft">
                        Say what you need. TrustCart turns it into hard
                        constraints, tests every product against recorded
                        catalog evidence, and explains what fails. Then it stops
                        — because the model never gets to spend your money.
                    </p>

                    <div className="mt-9 flex flex-wrap items-center gap-4">
                        <Link href="/assistant" className="btn-primary">
                            Run a decision
                            <ArrowRight className="h-4 w-4" strokeWidth={1.6} />
                        </Link>
                        <Link
                            href="/audit"
                            className="link-underline text-[13px] font-medium"
                        >
                            See the audit record
                        </Link>
                    </div>

                    <dl className="mt-12 grid grid-cols-2 gap-x-8 gap-y-4 border-t border-rule pt-5 sm:grid-cols-4">
                        {[
                            ['Products', String(catalogSize)],
                            ['Refusal paths', '5'],
                            ['Approval window', '15 min'],
                            ['Money at risk', '$0.00'],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-[9px] tracking-[0.16em] text-muted uppercase">
                                    {label}
                                </dt>
                                <dd className="mt-1 text-[20px] font-semibold">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div className="lg:col-span-5">
                    <div className="bg-panel text-paper">
                        <div className="flex items-center justify-between border-b border-white/15 px-5 py-2.5 text-[9px] tracking-[0.18em] uppercase">
                            <span>Decision record 0041</span>
                            <span className="text-white/50">Demo catalog</span>
                        </div>

                        <div className="px-5 pt-5">
                            <p className="text-[10px] tracking-[0.16em] text-white/45 uppercase">
                                Requirement
                            </p>
                            <p className="mt-1.5 text-[14px] leading-snug text-white/90">
                                {specimen.requirements.summary}
                            </p>
                        </div>

                        <div className="mt-5 border-t border-white/15">
                            {specimen.qualified && (
                                <div className="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-3.5">
                                    <div>
                                        <p className="text-[13px] font-semibold">
                                            {specimen.qualified.name}
                                        </p>
                                        <p className="mt-0.5 text-[11px] text-white/50">
                                            {specimen.qualified.price} ·{' '}
                                            {specimen.qualified.merchant}
                                        </p>
                                    </div>
                                    <span className="border border-pass/60 px-1.5 py-0.5 text-[9px] font-semibold tracking-[0.1em] text-[oklch(0.82_0.12_155)] uppercase">
                                        Qualified
                                    </span>
                                </div>
                            )}
                            {specimen.cheaper && (
                                <div className="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-3.5">
                                    <div>
                                        <p className="text-[13px] font-semibold">
                                            {specimen.cheaper.name}
                                        </p>
                                        <p className="mt-0.5 text-[11px] text-white/50">
                                            {specimen.cheaper.price} ·{' '}
                                            {specimen.cheaper.reason}
                                        </p>
                                    </div>
                                    <span className="border border-fail/60 px-1.5 py-0.5 text-[9px] font-semibold tracking-[0.1em] text-[oklch(0.8_0.14_27)] uppercase">
                                        Refused
                                    </span>
                                </div>
                            )}
                        </div>

                        <div className="px-5 py-4">
                            <p className="mb-2 text-[9px] tracking-[0.16em] text-white/40 uppercase">
                                Checks on the qualified pick
                            </p>
                            <ul className="space-y-1.5">
                                {specimen.qualified?.checks.map((check) => (
                                    <li
                                        key={check.key}
                                        className="flex items-center justify-between gap-3 text-[11px]"
                                    >
                                        <span className="flex items-center gap-2 text-white/70">
                                            <Check
                                                className="h-3 w-3 text-[oklch(0.82_0.12_155)]"
                                                strokeWidth={2}
                                            />
                                            {check.label}
                                        </span>
                                        <span className="font-mono text-[10px] text-white/50">
                                            {check.evidence}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="flex items-center justify-between border-t border-white/15 px-5 py-2.5 text-[9px] tracking-[0.12em] text-white/45 uppercase">
                            <span>
                                {specimen.qualified_count} qualified ·{' '}
                                {specimen.rejected_count} rejected ·{' '}
                                {specimen.unverified_count} unverified
                            </span>
                            <span>Rules v1</span>
                        </div>
                    </div>

                    <p className="mt-3 text-[11px] leading-relaxed text-muted">
                        Computed from the live demo catalog on this page load,
                        not a screenshot.
                    </p>
                </div>
            </section>

            <section className="-mx-5 bg-signal px-5 py-10 text-ink sm:-mx-8 sm:px-8 lg:py-12">
                <div className="grid gap-8 lg:grid-cols-[1.1fr_2fr] lg:gap-14">
                    <div>
                        <h2 className="text-[clamp(1.7rem,3.2vw,2.4rem)] leading-[1.05] font-semibold">
                            Three authorities.
                            <br />
                            One of them is you.
                        </h2>
                        <p className="mt-4 max-w-[36ch] text-[13px] leading-relaxed text-ink/80">
                            Most shopping agents blur recommendation, permission
                            and payment into one conversation. TrustCart keeps
                            them apart on purpose.
                        </p>
                    </div>

                    <div className="grid gap-8 sm:grid-cols-3 sm:gap-0 sm:divide-x sm:divide-ink/25">
                        {[
                            {
                                n: '01',
                                title: 'Interpret',
                                body: 'The model converts prose into a validated shape: category, budget, required features, minimum specs. Anything outside that vocabulary is discarded.',
                            },
                            {
                                n: '02',
                                title: 'Validate',
                                body: 'PHP checks every constraint against catalog rows. Failed requirements cannot be argued away, and a missing spec is never treated as a pass.',
                            },
                            {
                                n: '03',
                                title: 'Authorize',
                                body: 'You approve a priced total with an expiry. If the price moves, the approval dies. Only then does PayPal open, and only the server can capture.',
                            },
                        ].map((item) => (
                            <div
                                key={item.n}
                                className="sm:px-6 sm:first:pl-0 sm:last:pr-0"
                            >
                                <p className="flex items-center gap-2 text-[10px] font-semibold tracking-[0.16em] uppercase">
                                    <span className="font-mono text-[10px] text-ink/55">
                                        {item.n}
                                    </span>
                                    {item.title}
                                </p>
                                <p className="mt-3 text-[12px] leading-[1.65] text-ink/85">
                                    {item.body}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="grid gap-10 py-14 lg:grid-cols-12 lg:gap-12 lg:py-20">
                <div className="lg:col-span-5">
                    <h2 className="text-[clamp(1.8rem,3.6vw,2.8rem)] leading-[1.03] font-semibold">
                        A cheaper product
                        <br />
                        is not a better answer.
                    </h2>
                    <p className="mt-5 max-w-[46ch] text-[14px] leading-[1.65] text-ink-soft">
                        The request asked for noise cancellation. The cheaper
                        option does not have it. TrustCart recommends nothing,
                        shows the exact failing check, and tells the buyer what
                        would unlock a match.
                    </p>
                    <p className="mt-4 max-w-[46ch] text-[13px] leading-[1.65] text-muted">
                        Refusal is a first-class outcome, not an error state.
                        The same evidence panel answers “why not?” as clearly as
                        “why this one?”.
                    </p>
                    <Link
                        href="/assistant"
                        className="mt-7 inline-flex items-center gap-2 text-[13px] font-medium underline decoration-signal decoration-2 underline-offset-4 hover:text-signal-deep"
                    >
                        Try an impossible request
                        <ArrowRight className="h-4 w-4" strokeWidth={1.6} />
                    </Link>
                </div>

                <div className="lg:col-span-7">
                    {specimen.cheaper && (
                        <div className="record">
                            <div className="record-head">
                                <div>
                                    <p className="text-[9px] tracking-[0.18em] text-muted uppercase">
                                        Refused candidate
                                    </p>
                                    <p className="mt-1 text-[17px] font-semibold">
                                        {specimen.cheaper.name}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-[20px] font-semibold">
                                        {specimen.cheaper.price}
                                    </p>
                                    <p className="text-[10px] tracking-[0.12em] text-muted uppercase">
                                        Kestrel Supply
                                    </p>
                                </div>
                            </div>

                            <div className="bg-signal/10 px-4 py-3 text-[12px] text-ink sm:px-6">
                                <span className="mr-2 inline-flex h-4 w-4 items-center justify-center border border-fail/50 text-fail">
                                    <X className="h-3 w-3" strokeWidth={2} />
                                </span>
                                Not recommended. It fails a mandatory
                                requirement, so its lower price is irrelevant to
                                this request.
                            </div>

                            <div className="overflow-x-auto px-4 py-2 sm:px-6">
                                <table className="table-record">
                                    <thead>
                                        <tr>
                                            <th>Check</th>
                                            <th className="hidden sm:table-cell">
                                                Evidence
                                            </th>
                                            <th className="text-right">
                                                Result
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {specimen.cheaper.checks.map(
                                            (check) => (
                                                <tr key={check.key}>
                                                    <td>
                                                        <span className="font-medium">
                                                            {check.label}
                                                        </span>
                                                        <span className="mt-0.5 block text-[11px] text-muted">
                                                            {check.detail}
                                                        </span>
                                                    </td>
                                                    <td className="hidden text-ink-soft sm:table-cell">
                                                        {check.evidence}
                                                    </td>
                                                    <td className="text-right">
                                                        <Stamp
                                                            kind={
                                                                check.result as
                                                                    | 'pass'
                                                                    | 'fail'
                                                                    | 'unverified'
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </section>

            <section className="border-t border-rule py-14 lg:py-20">
                <div className="grid gap-10 lg:grid-cols-12 lg:gap-14">
                    <div className="lg:col-span-6">
                        <h2 className="text-[clamp(1.8rem,3.6vw,2.8rem)] leading-[1.03] font-semibold">
                            Every purchase
                            <br />
                            leaves a record.
                        </h2>
                        <p className="mt-5 max-w-[48ch] text-[14px] leading-[1.65] text-ink-soft">
                            Not a chat transcript. A decision file: the request,
                            the catalog snapshot it saw, every rule outcome, the
                            model narrative, the amount you approved, the PayPal
                            order, the capture, and anything that went wrong.
                        </p>

                        <div className="mt-8 border-t border-rule">
                            {[
                                [
                                    'Requirements',
                                    'Parsed, validated, versioned',
                                ],
                                [
                                    'Evidence',
                                    'Price, stock, features, minimums',
                                ],
                                ['Approval', 'Priced, expiring, IP-stamped'],
                                ['PayPal', 'Order ID, capture ID, status'],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="flex items-baseline justify-between gap-4 border-b border-rule py-3"
                                >
                                    <span className="text-[11px] tracking-[0.12em] text-muted uppercase">
                                        {label}
                                    </span>
                                    <span className="text-[12px] text-ink">
                                        {value}
                                    </span>
                                </div>
                            ))}
                        </div>

                        <Link
                            href="/audit"
                            className="mt-7 inline-flex items-center gap-2 text-[13px] font-medium underline decoration-signal decoration-2 underline-offset-4 hover:text-signal-deep"
                        >
                            Open the audit trail
                            <ArrowRight className="h-4 w-4" strokeWidth={1.6} />
                        </Link>
                    </div>

                    <div className="lg:col-span-6">
                        <div className="record">
                            <div className="record-head">
                                <span className="flex items-center gap-2 text-[10px] tracking-[0.16em] uppercase">
                                    <FileText
                                        className="h-3.5 w-3.5"
                                        strokeWidth={1.6}
                                    />
                                    Record contents
                                </span>
                                <span className="mono-id">
                                    immutable · exportable
                                </span>
                            </div>
                            <div className="space-y-0 px-4 py-3 sm:px-6">
                                {[
                                    [
                                        ShieldCheck,
                                        'Rules evaluated before any model prose',
                                        'The verdict is stored with the check matrix that produced it.',
                                    ],
                                    [
                                        Lock,
                                        'Human authorization is a separate row',
                                        'Amount, currency, expiry, IP and user agent — not a chat message.',
                                    ],
                                    [
                                        Check,
                                        'PayPal status is quoted, not inferred',
                                        'COMPLETED, PENDING, DECLINED or failed. No simulated success.',
                                    ],
                                ].map(([Icon, title, body]) => {
                                    const Component = Icon as typeof Check;

                                    return (
                                        <div
                                            key={title as string}
                                            className="flex gap-4 border-b border-rule py-4 last:border-b-0"
                                        >
                                            <Component
                                                className="mt-0.5 h-4 w-4 shrink-0 text-signal-deep"
                                                strokeWidth={1.6}
                                            />
                                            <div>
                                                <p className="text-[12px] font-medium">
                                                    {title as string}
                                                </p>
                                                <p className="mt-1 text-[11px] leading-relaxed text-muted">
                                                    {body as string}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section className="border-t border-rule py-14 lg:py-20">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <h2 className="text-[clamp(1.8rem,3.6vw,2.8rem)] leading-[1.03] font-semibold">
                        Eight scenarios,
                        <br />
                        not eight slides.
                    </h2>
                    <p className="max-w-[38ch] text-[12px] leading-relaxed text-muted">
                        The demo is built around the failure modes that make
                        agentic commerce dangerous. Each one is exercised by the
                        application.
                    </p>
                </div>

                <div className="mt-8 border-t-2 border-ink">
                    <div className="hidden overflow-x-auto sm:block">
                        <table className="table-record">
                            <thead>
                                <tr>
                                    <th className="w-10">Ref</th>
                                    <th className="w-56">Scenario</th>
                                    <th>How TrustCart handles it</th>
                                </tr>
                            </thead>
                            <tbody>
                                {scenarios.map(([ref, name, detail]) => (
                                    <tr key={ref}>
                                        <td className="font-mono text-[11px] text-muted">
                                            {ref}
                                        </td>
                                        <td className="font-medium text-ink">
                                            {name}
                                        </td>
                                        <td className="text-ink-soft">
                                            {detail}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <ul className="sm:hidden">
                        {scenarios.map(([ref, name, detail]) => (
                            <li
                                key={ref}
                                className="flex gap-3 border-b border-rule py-3 last:border-b-0"
                            >
                                <span className="w-4 shrink-0 font-mono text-[10px] text-muted">
                                    {ref}
                                </span>
                                <div>
                                    <p className="text-[13px] font-medium">
                                        {name}
                                    </p>
                                    <p className="mt-1 text-[11px] leading-relaxed text-ink-soft">
                                        {detail}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            <section className="mb-4 grid gap-8 border-t-2 border-ink py-12 lg:grid-cols-[1.5fr_1fr] lg:items-end lg:py-16">
                <div>
                    <h2 className="text-[clamp(2rem,4.4vw,3.4rem)] leading-[0.98] font-semibold tracking-[-0.04em]">
                        Bring a requirement.
                        <br />
                        Watch it get tested.
                    </h2>
                    <p className="mt-5 max-w-[50ch] text-[14px] leading-relaxed text-ink-soft">
                        Eight synthetic electronics, five ways to fail, one
                        PayPal sandbox at the end. Nothing is charged until you
                        say so.
                    </p>
                    <div className="mt-7 flex flex-wrap items-center gap-4">
                        <Link href="/assistant" className="btn-ink">
                            Start a decision
                            <ArrowRight className="h-4 w-4" strokeWidth={1.6} />
                        </Link>
                        <Link
                            href="/catalog"
                            className="link-underline text-[13px]"
                        >
                            Inspect the catalog evidence
                        </Link>
                    </div>
                </div>

                <div className="border border-rule p-5">
                    <p className="text-[10px] tracking-[0.16em] text-muted uppercase">
                        What this is not
                    </p>
                    <ul className="mt-3 space-y-2 text-[12px] leading-relaxed text-ink-soft">
                        <li>
                            Not a real storefront. Products, brands and
                            merchants are fictional.
                        </li>
                        <li>
                            Not real money. PayPal sandbox only, and no card
                            data ever reaches TrustCart.
                        </li>
                        <li>
                            Not an autonomous spender. The agent cannot
                            authorize or capture a payment.
                        </li>
                    </ul>
                </div>
            </section>
        </>
    );
}
