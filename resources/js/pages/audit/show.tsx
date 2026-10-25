import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import CheckTable from '@/components/trust/check-table';
import RecordHeader from '@/components/trust/record-header';
import StatusStamp from '@/components/trust/status-stamp';
import { formatDateTime, shortId } from '@/lib/format';
import type {
    AuditEvent,
    Candidate,
    Check as CheckRow,
    EngineInfo,
    RequirementsPayload,
} from '@/types/trustcart';

type ApprovalRow = {
    uuid: string;
    status: string;
    product: string | null;
    total: string;
    authorized_at: string | null;
    expires_at: string;
    invalidated_reason: string | null;
    orders: Array<{
        uuid: string;
        status: string;
        amount: string;
        paypal_order_id: string | null;
        capture_id: string | null;
        failure_reason: string | null;
        events: Array<{
            id: number;
            type: string;
            status: string | null;
            created_at: string | null;
        }>;
    }>;
};

export default function AuditShow({
    session,
    recommendation,
    approvals,
    auditEvents,
    labels,
}: {
    session: {
        uuid: string;
        intent: string;
        requirements: RequirementsPayload;
        created_at: string;
        ai_provider: string | null;
        ai_model: string | null;
    };
    recommendation: {
        verdict: string;
        score: number;
        narrative: string;
        narrative_source: string;
        checks: CheckRow[];
        candidates: Candidate[];
        engine: EngineInfo;
    } | null;
    approvals: ApprovalRow[];
    auditEvents: AuditEvent[];
    labels: { features: string[]; minimums: string[] };
}) {
    return (
        <>
            <Head title={`Record DR-${shortId(session.uuid)}`} />

            <RecordHeader
                kicker="Decision file"
                id={`DR-${shortId(session.uuid)}`}
                title={
                    recommendation?.verdict === 'recommended'
                        ? 'Recommendation record'
                        : 'Refusal record'
                }
                description="Everything below is stored evidence: the exact request, the parsed requirement, each rule outcome, the advisory narrative, the authorization and the payment events."
                meta={[
                    {
                        label: 'Created',
                        value: formatDateTime(session.created_at),
                    },
                    {
                        label: 'Verdict',
                        value: (
                            <StatusStamp
                                kind={recommendation?.verdict ?? 'pending'}
                            />
                        ),
                    },
                    {
                        label: 'Score',
                        value: recommendation
                            ? `${recommendation.score}/100`
                            : '—',
                    },
                    {
                        label: 'Model',
                        value: session.ai_provider
                            ? `${session.ai_provider} / ${session.ai_model}`
                            : 'deterministic fallback',
                    },
                    {
                        label: 'Rules version',
                        value: recommendation?.engine.rules_version ?? '—',
                    },
                ]}
                actions={
                    <>
                        <Link
                            href="/audit"
                            className="inline-flex items-center gap-2 text-[12px] text-ink-soft hover:text-ink"
                        >
                            <ArrowLeft
                                className="h-3.5 w-3.5"
                                strokeWidth={1.6}
                            />
                            All records
                        </Link>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="btn-quiet no-print"
                        >
                            <Printer
                                className="h-3.5 w-3.5"
                                strokeWidth={1.6}
                            />
                            Print
                        </button>
                    </>
                }
            />

            <section className="mt-8 grid gap-8 lg:grid-cols-12">
                <div className="lg:col-span-7">
                    <h2 className="border-t-2 border-ink pt-3 text-[20px] font-semibold">
                        Request &amp; parsed requirement
                    </h2>
                    <blockquote className="mt-3 border border-rule bg-paper-deep px-4 py-4">
                        <p className="text-[16px] leading-[1.5]">
                            “{session.intent}”
                        </p>
                    </blockquote>

                    <dl className="mt-4 border-t border-rule text-[12px]">
                        {[
                            ['Category', session.requirements.category],
                            ['Currency', session.requirements.currency],
                            [
                                'Budget',
                                session.requirements.budget_cents
                                    ? `${(session.requirements.budget_cents / 100).toFixed(2)} ${session.requirements.currency}`
                                    : 'No ceiling',
                            ],
                            [
                                'Required features',
                                labels.features.length > 0
                                    ? labels.features.join(' · ')
                                    : 'None',
                            ],
                            [
                                'Minimums',
                                labels.minimums.length > 0
                                    ? labels.minimums.join(' · ')
                                    : 'None',
                            ],
                        ].map(([label, value]) => (
                            <div
                                key={label as string}
                                className="flex flex-col justify-between gap-1 border-b border-rule py-2.5 sm:flex-row sm:gap-6"
                            >
                                <dt className="text-[10px] tracking-[0.12em] text-muted uppercase">
                                    {label as string}
                                </dt>
                                <dd className="sm:text-right">
                                    {String(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div className="lg:col-span-5">
                    <h2 className="border-t-2 border-ink pt-3 text-[20px] font-semibold">
                        Advisory narrative
                    </h2>
                    <div className="mt-3 border border-rule px-4 py-4">
                        <p className="text-[9px] tracking-[0.16em] text-muted uppercase">
                            {recommendation?.narrative_source === 'ai'
                                ? `${recommendation.engine.narrative_provider ?? 'model'} narrative`
                                : 'Deterministic narrative'}
                        </p>
                        <p className="mt-2 text-[13px] leading-[1.7] text-ink">
                            {recommendation?.narrative ??
                                'No narrative was recorded.'}
                        </p>
                        <p className="mt-3 border-t border-rule pt-2 text-[10px] leading-relaxed text-muted">
                            The narrative is stored after the verdict and cannot
                            alter the check matrix below.
                        </p>
                    </div>

                    <div className="mt-4 border border-rule px-4 py-3">
                        <p className="text-[10px] tracking-[0.14em] text-muted uppercase">
                            Engine
                        </p>
                        <dl className="mt-2 space-y-1.5 text-[11px]">
                            {[
                                [
                                    'Extraction',
                                    recommendation?.engine.extraction_source ??
                                        '—',
                                ],
                                [
                                    'Provider',
                                    recommendation?.engine.provider ??
                                        'fallback',
                                ],
                                [
                                    'Duration',
                                    `${recommendation?.engine.duration_ms ?? '—'} ms`,
                                ],
                                [
                                    'Catalog size',
                                    recommendation?.engine.catalog_size ?? '—',
                                ],
                                [
                                    'Evaluated',
                                    formatDateTime(
                                        recommendation?.engine.evaluated_at ??
                                            null,
                                    ),
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={label as string}
                                    className="flex justify-between gap-3"
                                >
                                    <dt className="text-muted">
                                        {label as string}
                                    </dt>
                                    <dd className="text-right font-mono text-[10px]">
                                        {String(value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                </div>
            </section>

            {recommendation && (
                <>
                    <h2 className="mt-10 border-t-2 border-ink pt-3 text-[20px] font-semibold">
                        Rule outcomes
                    </h2>
                    <div className="mt-3 overflow-x-auto border border-rule">
                        <CheckTable checks={recommendation.checks} />
                    </div>

                    <h2 className="mt-10 border-t-2 border-ink pt-3 text-[20px] font-semibold">
                        Candidate matrix
                    </h2>
                    <div className="mt-3 overflow-x-auto border border-rule">
                        <table className="table-record">
                            <thead>
                                <tr>
                                    <th className="pl-4 sm:pl-6">Product</th>
                                    <th>Price</th>
                                    <th>Verdict</th>
                                    <th className="pr-4 sm:pr-6">Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recommendation.candidates.map((candidate) => (
                                    <tr key={candidate.product.sku}>
                                        <td className="pl-4 font-medium sm:pl-6">
                                            {candidate.product.name}
                                            <span className="mt-0.5 block font-mono text-[10px] font-normal text-muted">
                                                {candidate.product.sku}
                                            </span>
                                        </td>
                                        <td>{candidate.product.price}</td>
                                        <td>
                                            <StatusStamp
                                                kind={candidate.verdict}
                                            />
                                        </td>
                                        <td className="pr-4 text-ink-soft sm:pr-6">
                                            {candidate.reason}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            <h2 className="mt-10 border-t-2 border-ink pt-3 text-[20px] font-semibold">
                Authorizations &amp; payments
            </h2>

            {approvals.length === 0 ? (
                <p className="mt-3 border border-rule px-4 py-6 text-[12px] text-muted">
                    No authorization was created. The AI can refuse, and a
                    refusal never reaches PayPal.
                </p>
            ) : (
                <div className="mt-3 space-y-5">
                    {approvals.map((approval) => (
                        <div key={approval.uuid} className="border border-rule">
                            <div className="record-head">
                                <div>
                                    <p className="font-mono text-[11px]">
                                        AP-{shortId(approval.uuid)}
                                    </p>
                                    <p className="mt-0.5 text-[11px] text-muted">
                                        {approval.product} · {approval.total}
                                    </p>
                                </div>
                                <StatusStamp kind={approval.status} />
                            </div>

                            <dl className="grid gap-3 px-4 py-3 text-[11px] sm:grid-cols-3 sm:px-6">
                                <div>
                                    <dt className="text-[9px] tracking-[0.12em] text-muted uppercase">
                                        Expires
                                    </dt>
                                    <dd className="mt-0.5">
                                        {formatDateTime(approval.expires_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[9px] tracking-[0.12em] text-muted uppercase">
                                        Authorized
                                    </dt>
                                    <dd className="mt-0.5">
                                        {formatDateTime(approval.authorized_at)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[9px] tracking-[0.12em] text-muted uppercase">
                                        Void reason
                                    </dt>
                                    <dd className="mt-0.5">
                                        {approval.invalidated_reason ?? '—'}
                                    </dd>
                                </div>
                            </dl>

                            {approval.orders.map((order) => (
                                <div
                                    key={order.uuid}
                                    className="border-t border-rule px-4 py-3 sm:px-6"
                                >
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <p className="font-mono text-[11px]">
                                            PY-{shortId(order.uuid)}
                                        </p>
                                        <div className="flex items-center gap-3">
                                            <span className="text-[11px]">
                                                {order.amount}
                                            </span>
                                            <StatusStamp kind={order.status} />
                                        </div>
                                    </div>
                                    <dl className="mt-2 grid gap-2 text-[10px] sm:grid-cols-2">
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-muted">
                                                PayPal order
                                            </dt>
                                            <dd className="font-mono break-all">
                                                {order.paypal_order_id ?? '—'}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-3">
                                            <dt className="text-muted">
                                                Capture
                                            </dt>
                                            <dd className="font-mono break-all">
                                                {order.capture_id ?? '—'}
                                            </dd>
                                        </div>
                                    </dl>
                                    {order.failure_reason && (
                                        <p className="mt-2 text-[11px] text-fail">
                                            {order.failure_reason}
                                        </p>
                                    )}

                                    {order.events.length > 0 && (
                                        <ol className="mt-3 border-t border-rule pt-2">
                                            {order.events.map((event) => (
                                                <li
                                                    key={event.id}
                                                    className="flex flex-wrap items-baseline justify-between gap-2 border-b border-rule py-1.5 text-[10px] last:border-b-0"
                                                >
                                                    <span className="font-mono">
                                                        {event.type}
                                                    </span>
                                                    <span className="text-muted">
                                                        {formatDateTime(
                                                            event.created_at,
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                </div>
                            ))}
                        </div>
                    ))}
                </div>
            )}

            <h2 className="mt-10 border-t-2 border-ink pt-3 text-[20px] font-semibold">
                Append-only events
            </h2>
            <div className="mt-3 border border-rule">
                {auditEvents.length === 0 ? (
                    <p className="px-4 py-5 text-[12px] text-muted">
                        No audit events.
                    </p>
                ) : (
                    auditEvents.map((event) => (
                        <details
                            key={event.id}
                            className="border-b border-rule last:border-b-0"
                        >
                            <summary className="flex cursor-pointer flex-wrap items-baseline justify-between gap-2 px-4 py-2.5 text-[11px] hover:bg-paper-deep">
                                <span className="font-mono">{event.type}</span>
                                <span className="text-[10px] text-muted">
                                    {formatDateTime(event.created_at)}
                                </span>
                            </summary>
                            <pre className="overflow-x-auto border-t border-rule bg-paper-deep px-4 py-3 font-mono text-[10px] leading-relaxed text-ink-soft">
                                {JSON.stringify(event.data, null, 2)}
                            </pre>
                        </details>
                    ))
                )}
            </div>

            <p className="mt-4 text-[10px] leading-relaxed text-muted">
                Content-hash chaining and external retention are not implemented
                in this prototype. The audit table is append-only by application
                policy, not by cryptographic proof.
            </p>
        </>
    );
}
