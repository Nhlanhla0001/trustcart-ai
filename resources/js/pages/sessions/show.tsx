import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ChevronDown,
    Loader2,
    RefreshCw,
    X,
} from 'lucide-react';
import { useState } from 'react';
import CheckTable from '@/components/trust/check-table';
import ProductIllustration from '@/components/trust/product-illustration';
import RecordHeader from '@/components/trust/record-header';
import StatusStamp from '@/components/trust/status-stamp';
import type {
    Candidate,
    Check as CheckRow,
    EngineInfo,
    ProductSnapshot,
    RequirementsPayload,
} from '@/types/trustcart';

type SessionPayload = {
    uuid: string;
    intent: string;
    created_at: string;
    requirements: RequirementsPayload;
    labels: {
        category: string;
        budget: string | null;
        currency: string;
        features: Array<{ key: string; label: string }>;
        minimums: Array<{ key: string; label: string; value: string }>;
    };
};

type RecommendationPayload = {
    verdict: 'recommended' | 'refused';
    score: number;
    narrative: string;
    narrative_source: string;
    checks: CheckRow[];
    checks_subject: 'selected' | 'closest';
    candidates: Candidate[];
    engine: EngineInfo;
    product: ProductSnapshot | null;
};

type ApprovalPayload = {
    uuid: string;
    status: string;
    total: string;
    expires_at: string;
};

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function CandidateTags({ candidate }: { candidate: Candidate }) {
    if (candidate.verdict === 'qualified') {
        return null;
    }

    return (
        <span className="mt-1.5 flex flex-wrap gap-1">
            {candidate.checks
                .filter((check) => check.result !== 'pass')
                .slice(0, 3)
                .map((check) => (
                    <span
                        key={check.key}
                        className="border border-rule-strong px-1 py-0.5 text-[9px] text-muted"
                    >
                        {check.label}
                    </span>
                ))}
        </span>
    );
}

export default function SessionShow({
    session,
    recommendation,
    approval,
    checkout,
}: {
    session: SessionPayload;
    recommendation: RecommendationPayload;
    approval: ApprovalPayload | null;
    checkout: { paypal_configured: boolean; environment: string };
}) {
    const approve = useForm({});
    const reprice = useForm({});
    const [showTrace, setShowTrace] = useState(false);

    const recommended =
        recommendation.verdict === 'recommended' && recommendation.product;
    const product = recommendation.product;
    const others = recommendation.candidates.filter(
        (candidate) => candidate.verdict !== 'qualified',
    );
    const qualifiedCount = recommendation.candidates.filter(
        (candidate) => candidate.verdict === 'qualified',
    ).length;

    const narrativeLabel =
        recommendation.narrative_source === 'ai'
            ? `${recommendation.engine.narrative_provider ?? recommendation.engine.provider ?? 'model'} narrative`
            : 'Deterministic narrative';

    const simulatePriceChange = () => {
        const confirmed = window.confirm(
            'Demo control: update this product’s catalog price by 12%. Any live approval will be voided and the buyer will have to authorize the new total. Continue?',
        );

        if (confirmed) {
            reprice.post(`/sessions/${session.uuid}/simulate-price-change`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head
                title={
                    recommended
                        ? `${product?.name} · decision`
                        : 'Decision refused'
                }
            />

            <RecordHeader
                kicker="Decision record"
                id={`DR-${session.uuid.slice(0, 8).toUpperCase()}`}
                title={
                    recommended ? (
                        <>
                            {product?.name}
                            <span className="mt-1 block text-[13px] font-normal tracking-normal text-muted">
                                {product?.brand} · {product?.merchant}
                            </span>
                        </>
                    ) : (
                        'No product met the requirements.'
                    )
                }
                description={
                    recommended
                        ? 'This product clears every mandatory check against recorded catalog evidence. The payment is still yours to authorize.'
                        : 'TrustCart refused to recommend a purchase. The closest candidates and the exact failing checks are below.'
                }
                meta={[
                    {
                        label: 'Requested',
                        value: formatDate(session.created_at),
                    },
                    { label: 'Category', value: session.labels.category },
                    {
                        label: 'Budget',
                        value: session.labels.budget ?? 'No ceiling',
                    },
                    {
                        label: 'Rules',
                        value: recommendation.engine.rules_version ?? 'v1',
                    },
                    {
                        label: 'Model',
                        value:
                            recommendation.engine.provider &&
                            recommendation.engine.model
                                ? `${recommendation.engine.provider} / ${recommendation.engine.model}`
                                : 'fallback parser',
                    },
                    {
                        label: 'Environment',
                        value: `${checkout.environment} · USD`,
                    },
                ]}
                actions={
                    <Link
                        href="/assistant"
                        className="inline-flex items-center gap-2 text-[12px] text-ink-soft hover:text-ink"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" strokeWidth={1.6} />
                        New request
                    </Link>
                }
            />

            <section className="mt-6 border border-rule">
                <div className="record-head">
                    <span className="text-[10px] tracking-[0.16em] uppercase">
                        Parsed requirement
                    </span>
                    <span className="text-[10px] text-muted">
                        extracted by{' '}
                        {recommendation.engine.extraction_source === 'ai'
                            ? recommendation.engine.provider
                            : 'deterministic parser'}
                    </span>
                </div>
                <div className="grid gap-6 px-4 py-5 sm:px-6 lg:grid-cols-12">
                    <blockquote className="lg:col-span-7">
                        <p className="text-[9px] tracking-[0.16em] text-muted uppercase">
                            Your words
                        </p>
                        <p className="mt-2 max-w-[62ch] text-[17px] leading-[1.5] text-ink">
                            “{session.intent}”
                        </p>
                    </blockquote>

                    <dl className="space-y-2.5 border-t border-rule pt-4 text-[12px] lg:col-span-5 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Category</dt>
                            <dd className="text-right font-medium">
                                {session.labels.category}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Budget</dt>
                            <dd className="text-right font-medium">
                                {session.labels.budget ?? 'No ceiling'}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Must have</dt>
                            <dd className="flex max-w-[18rem] flex-wrap justify-end gap-1">
                                {session.labels.features.length === 0 ? (
                                    <span className="text-muted">
                                        Nothing stated
                                    </span>
                                ) : (
                                    session.labels.features.map((feature) => (
                                        <span
                                            key={feature.key}
                                            className="border border-rule-strong px-1.5 py-0.5 text-[10px]"
                                        >
                                            {feature.label}
                                        </span>
                                    ))
                                )}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Minimums</dt>
                            <dd className="flex max-w-[18rem] flex-wrap justify-end gap-1">
                                {session.labels.minimums.length === 0 ? (
                                    <span className="text-muted">
                                        None stated
                                    </span>
                                ) : (
                                    session.labels.minimums.map((minimum) => (
                                        <span
                                            key={minimum.key}
                                            className="border border-rule-strong px-1.5 py-0.5 text-[10px]"
                                        >
                                            {minimum.label} ≥ {minimum.value}
                                        </span>
                                    ))
                                )}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            {recommended && product ? (
                <section className="mt-6 bg-ink text-paper">
                    <div className="relative flex flex-wrap items-center justify-between gap-3 overflow-hidden border-b border-white/15 px-5 py-2.5 text-[9px] tracking-[0.18em] uppercase">
                        <span className="flex items-center gap-2">
                            <Check
                                className="h-3.5 w-3.5 text-[oklch(0.82_0.12_155)]"
                                strokeWidth={2}
                            />
                            Qualified — every mandatory check passes
                        </span>
                        <span className="text-white/50">
                            {recommendation.score}/100 match
                        </span>
                        <span className="sweep-line absolute bottom-0 left-0 h-px w-full origin-left bg-signal" />
                    </div>

                    <div className="grid gap-5 px-5 py-6 sm:grid-cols-[9rem_1fr_auto] sm:items-center">
                        <div className="h-24 w-full border border-white/20 p-2 text-white/70 sm:w-36">
                            <ProductIllustration
                                kind={product.illustration}
                                label={`${product.name} line drawing`}
                            />
                        </div>
                        <div>
                            <p className="text-[20px] font-semibold">
                                {product.name}
                            </p>
                            <p className="mt-1 text-[12px] text-white/60">
                                {product.brand} · {product.merchant}
                                {product.merchant_verified && (
                                    <span className="ml-2 border border-white/25 px-1 py-0.5 text-[9px] tracking-[0.1em] uppercase">
                                        Verified merchant
                                    </span>
                                )}
                            </p>
                            <p className="mt-2 max-w-[46ch] text-[12px] leading-relaxed text-white/65">
                                {product.tagline}
                            </p>
                            <p className="mt-2 text-[11px] text-white/45">
                                {product.stock} in stock · synced{' '}
                                {product.last_synced_at
                                    ? formatDate(product.last_synced_at)
                                    : 'unknown'}
                            </p>
                        </div>
                        <div className="sm:text-right">
                            <p className="text-[10px] tracking-[0.14em] text-white/45 uppercase">
                                Unit price
                            </p>
                            <p className="mt-1 text-[34px] leading-none font-semibold">
                                {product.price}
                            </p>
                            <p className="mt-1 text-[10px] text-white/45">
                                {product.currency}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-4 border-t border-white/15 px-5 py-4">
                        <p className="max-w-[46ch] text-[11px] leading-relaxed text-white/55">
                            Nothing is charged yet. The next screen prices the
                            total, records your authorization with an expiry,
                            and only then opens PayPal.
                        </p>
                        {approval &&
                        ['pending', 'authorized'].includes(approval.status) ? (
                            <Link
                                href={`/approvals/${approval.uuid}`}
                                className="btn-primary"
                            >
                                Open approval · {approval.total}
                                <ArrowRight
                                    className="h-4 w-4"
                                    strokeWidth={1.6}
                                />
                            </Link>
                        ) : (
                            <button
                                type="button"
                                className="btn-primary"
                                disabled={approve.processing}
                                onClick={() =>
                                    approve.post(
                                        `/sessions/${session.uuid}/approve`,
                                    )
                                }
                            >
                                {approve.processing ? (
                                    <>
                                        <Loader2
                                            className="h-3.5 w-3.5 animate-spin"
                                            strokeWidth={1.8}
                                        />
                                        Preparing
                                    </>
                                ) : (
                                    <>
                                        Review &amp; authorize
                                        <ArrowRight
                                            className="h-4 w-4"
                                            strokeWidth={1.6}
                                        />
                                    </>
                                )}
                            </button>
                        )}
                    </div>
                </section>
            ) : (
                <section className="mt-6 border-2 border-fail/60">
                    <div className="flex flex-wrap items-center justify-between gap-3 bg-fail px-5 py-2.5 text-[9px] tracking-[0.18em] text-paper uppercase">
                        <span className="flex items-center gap-2">
                            <X className="h-3.5 w-3.5" strokeWidth={2} />
                            Refused — no purchasable match
                        </span>
                        <span className="text-paper/70">
                            {qualifiedCount} of{' '}
                            {recommendation.candidates.length} qualified
                        </span>
                    </div>
                    <div className="grid gap-6 px-5 py-6 lg:grid-cols-[1.4fr_1fr]">
                        <div>
                            <p className="text-[17px] leading-[1.45] text-ink">
                                TrustCart is not going to sell you the closest
                                thing and call it a match. Mandatory
                                requirements are mandatory.
                            </p>
                            <Link href="/assistant" className="btn-ink mt-5">
                                Change the requirement
                                <ArrowRight
                                    className="h-4 w-4"
                                    strokeWidth={1.6}
                                />
                            </Link>
                        </div>
                        <div className="border-t border-rule pt-4 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-6">
                            <p className="text-[10px] tracking-[0.16em] text-muted uppercase">
                                Closest option
                            </p>
                            {others[0] ? (
                                <>
                                    <p className="mt-1.5 text-[14px] font-medium">
                                        {others[0].product.name} ·{' '}
                                        {others[0].product.price}
                                    </p>
                                    <p className="mt-1 text-[11px] leading-relaxed text-muted">
                                        {others[0].reason}
                                    </p>
                                </>
                            ) : (
                                <p className="mt-1.5 text-[12px] text-muted">
                                    No candidates in this category.
                                </p>
                            )}
                        </div>
                    </div>
                </section>
            )}

            {approval && (
                <div
                    className={`mt-4 flex flex-wrap items-center justify-between gap-3 border px-4 py-3 text-[12px] ${
                        approval.status === 'invalidated' ||
                        approval.status === 'expired'
                            ? 'border-fail/40 bg-fail/5 text-fail'
                            : 'border-rule bg-paper-deep text-ink-soft'
                    }`}
                >
                    <span>
                        Approval {approval.uuid.slice(0, 8).toUpperCase()} ·{' '}
                        <StatusStamp kind={approval.status} className="ml-1" />{' '}
                        {approval.status === 'invalidated' &&
                            'This authorization was voided. The amount it quoted is no longer live.'}
                        {approval.status === 'expired' &&
                            'This authorization window closed.'}
                    </span>
                    <Link
                        href={`/approvals/${approval.uuid}`}
                        className="font-medium underline decoration-1 underline-offset-4"
                    >
                        Open approval
                    </Link>
                </div>
            )}

            <section className="mt-8 grid gap-8 lg:grid-cols-12">
                <div className="lg:col-span-8">
                    <div className="border-t-2 border-ink pt-3">
                        <div className="flex items-baseline justify-between gap-4">
                            <h2 className="text-[20px] font-semibold">
                                {recommendation.checks_subject === 'closest'
                                    ? 'Checks on the closest option'
                                    : 'Checks performed'}
                            </h2>
                            <span className="text-[10px] text-muted">
                                {recommendation.checks.length} checks · rules
                                engine
                            </span>
                        </div>
                    </div>

                    <div className="mt-3 overflow-x-auto border border-rule">
                        <CheckTable checks={recommendation.checks} />
                    </div>

                    <h2 className="mt-10 border-t-2 border-ink pt-3 text-[20px] font-semibold">
                        The alternatives
                    </h2>
                    <p className="mt-1 text-[12px] text-muted">
                        Ranked by verdict, then match score. Rejected options
                        stay visible so the refusal can be inspected.
                    </p>

                    <div className="mt-3 border border-rule">
                        <div className="hidden overflow-x-auto sm:block">
                            <table className="table-record">
                                <thead>
                                    <tr>
                                        <th className="pl-6">Product</th>
                                        <th>Price</th>
                                        <th>Verdict</th>
                                        <th className="pr-6">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recommendation.candidates.map(
                                        (candidate) => (
                                            <tr key={candidate.product.sku}>
                                                <td className="pl-6">
                                                    <span className="font-medium">
                                                        {candidate.product.name}
                                                    </span>
                                                    <span className="mt-0.5 block text-[10px] text-muted">
                                                        {
                                                            candidate.product
                                                                .merchant
                                                        }{' '}
                                                        ·{' '}
                                                        {candidate.product.sku}
                                                    </span>
                                                </td>
                                                <td>
                                                    {candidate.product.price}
                                                </td>
                                                <td>
                                                    <StatusStamp
                                                        kind={candidate.verdict}
                                                    />
                                                </td>
                                                <td className="pr-6">
                                                    <span className="text-ink-soft">
                                                        {candidate.reason}
                                                    </span>
                                                    <CandidateTags
                                                        candidate={candidate}
                                                    />
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <ul className="sm:hidden">
                            {recommendation.candidates.map((candidate) => (
                                <li
                                    key={candidate.product.sku}
                                    className="border-b border-rule px-4 py-3 last:border-b-0"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-[13px] font-medium">
                                                {candidate.product.name}
                                            </p>
                                            <p className="mt-0.5 text-[10px] text-muted">
                                                {candidate.product.merchant} ·{' '}
                                                {candidate.product.sku}
                                            </p>
                                        </div>
                                        <span className="text-[13px]">
                                            {candidate.product.price}
                                        </span>
                                    </div>
                                    <div className="mt-2 flex items-start gap-2">
                                        <StatusStamp kind={candidate.verdict} />
                                        <span className="text-[11px] leading-relaxed text-ink-soft">
                                            {candidate.reason}
                                        </span>
                                    </div>
                                    <CandidateTags candidate={candidate} />
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <aside className="lg:col-span-4">
                    <div className="border-t-2 border-ink pt-3">
                        <h2 className="text-[20px] font-semibold">
                            Explanation
                        </h2>
                    </div>

                    <div className="mt-3 border border-rule bg-paper-deep px-4 py-4">
                        <p className="text-[9px] tracking-[0.16em] text-muted uppercase">
                            {narrativeLabel}
                        </p>
                        <p className="mt-2 text-[13px] leading-[1.7] text-ink">
                            {recommendation.narrative}
                        </p>
                        <p className="mt-3 border-t border-rule pt-2 text-[10px] leading-relaxed text-muted">
                            Advisory only. This text was generated after the
                            rules verdict and cannot change a check, a price, or
                            an amount.
                        </p>
                    </div>

                    <div className="mt-6 border border-rule">
                        <button
                            type="button"
                            onClick={() => setShowTrace((value) => !value)}
                            aria-expanded={showTrace}
                            className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                        >
                            <span>
                                <span className="block text-[10px] tracking-[0.16em] text-muted uppercase">
                                    Decision trace
                                </span>
                                <span className="mt-0.5 block text-[12px]">
                                    {recommendation.engine.duration_ms ?? '—'}{' '}
                                    ms ·{' '}
                                    {recommendation.engine.catalog_size ?? '—'}{' '}
                                    products
                                </span>
                            </span>
                            <ChevronDown
                                className={`h-4 w-4 text-muted transition-transform ${
                                    showTrace ? 'rotate-180' : ''
                                }`}
                                strokeWidth={1.6}
                            />
                        </button>

                        {showTrace && (
                            <div className="border-t border-rule px-4 py-3">
                                <dl className="space-y-1.5 text-[11px]">
                                    {[
                                        [
                                            'Extraction',
                                            recommendation.engine
                                                .extraction_source ?? '—',
                                        ],
                                        [
                                            'Narrative',
                                            recommendation.engine
                                                .narrative_source ?? '—',
                                        ],
                                        [
                                            'Provider',
                                            recommendation.engine.provider ??
                                                'fallback',
                                        ],
                                        [
                                            'Model',
                                            recommendation.engine.model ?? '—',
                                        ],
                                        [
                                            'Prompt',
                                            recommendation.engine
                                                .prompt_version ?? '—',
                                        ],
                                        [
                                            'Evaluated',
                                            recommendation.engine
                                                .evaluated_at ?? '—',
                                        ],
                                    ].map(([label, value]) => (
                                        <div
                                            key={label}
                                            className="flex justify-between gap-3"
                                        >
                                            <dt className="text-muted">
                                                {label}
                                            </dt>
                                            <dd className="text-right font-mono text-[10px]">
                                                {String(value)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                {(recommendation.engine.extraction_error ||
                                    recommendation.engine.narrative_error) && (
                                    <p className="mt-2 border-t border-rule pt-2 font-mono text-[10px] text-warn">
                                        {recommendation.engine
                                            .extraction_error ??
                                            recommendation.engine
                                                .narrative_error}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    {recommended && (
                        <div className="mt-6 border border-rule px-4 py-4">
                            <p className="text-[10px] tracking-[0.16em] text-muted uppercase">
                                Demonstration control
                            </p>
                            <p className="mt-1.5 text-[11px] leading-relaxed text-ink-soft">
                                Simulate a legitimate merchant price sync. Any
                                approval quoting the old price is voided; the
                                buyer must authorize again.
                            </p>
                            <button
                                type="button"
                                onClick={simulatePriceChange}
                                disabled={reprice.processing}
                                className="btn-quiet mt-3 w-full"
                            >
                                <RefreshCw
                                    className={`h-3.5 w-3.5 ${reprice.processing ? 'animate-spin' : ''}`}
                                    strokeWidth={1.6}
                                />
                                Re-price this product +12%
                            </button>
                        </div>
                    )}

                    <div className="mt-6 flex items-start gap-2 border border-rule px-4 py-3 text-[11px] leading-relaxed text-muted">
                        <span className="mt-0.5 inline-block h-2 w-2 shrink-0 border border-rule-strong" />
                        <span>
                            PayPal is{' '}
                            {checkout.paypal_configured
                                ? 'configured'
                                : 'not configured'}{' '}
                            in this environment. TrustCart never writes a paid
                            status that PayPal did not return.
                        </span>
                    </div>
                </aside>
            </section>
        </>
    );
}
