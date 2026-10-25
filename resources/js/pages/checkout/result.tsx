import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CheckCircle2,
    Clock,
    FileText,
    XCircle,
} from 'lucide-react';
import RecordHeader from '@/components/trust/record-header';
import StatusStamp from '@/components/trust/status-stamp';
import { formatDateTime, shortId } from '@/lib/format';
import type { DemoEvent, ProductSnapshot } from '@/types/trustcart';

type OrderPayload = {
    uuid: string;
    status: string;
    amount: string;
    amount_cents: number;
    currency: string;
    paypal_order_id: string | null;
    capture_id: string | null;
    payer_id: string | null;
    failure_reason: string | null;
    environment: string;
    created_at: string;
};

const state = {
    completed: {
        heading: 'Paid in PayPal sandbox.',
        note: 'PayPal returned COMPLETED for this capture. The amount below is what was verified, not what was requested.',
        tone: 'pass',
    },
    cancelled: {
        heading: 'Checkout cancelled.',
        note: 'You cancelled at PayPal. No capture was attempted and no paid status exists for this order.',
        tone: 'quiet',
    },
    failed: {
        heading: 'Payment was not completed.',
        note: 'PayPal did not complete this payment. Nothing is recorded as paid.',
        tone: 'fail',
    },
    pending: {
        heading: 'PayPal is reviewing the payment.',
        note: 'A pending status is not a paid status. TrustCart will not present it as one.',
        tone: 'warn',
    },
    capturing: {
        heading: 'Verifying with PayPal.',
        note: 'The capture is in progress. Refresh to see the status PayPal returns.',
        tone: 'warn',
    },
    created: {
        heading: 'Checkout was started but not completed.',
        note: 'No payment has been captured for this order.',
        tone: 'quiet',
    },
} as const;

export default function CheckoutResult({
    order,
    approval,
    product,
    sessionUuid,
    events,
}: {
    order: OrderPayload;
    approval: { uuid: string; status: string; total: string };
    product: ProductSnapshot | null;
    sessionUuid: string;
    events: DemoEvent[];
    paypal: { configured: boolean; environment: string };
}) {
    const view = state[order.status as keyof typeof state] ?? state.failed;
    const paid = order.status === 'completed';

    return (
        <>
            <Head title={paid ? 'Payment verified' : 'Checkout result'} />

            <RecordHeader
                kicker="Payment result"
                id={`PY-${shortId(order.uuid)}`}
                title={view.heading}
                description={view.note}
                meta={[
                    {
                        label: 'Order status',
                        value: <StatusStamp kind={order.status} />,
                    },
                    {
                        label: 'Approval',
                        value: <StatusStamp kind={approval.status} />,
                    },
                    {
                        label: 'Environment',
                        value: `${order.environment} · ${order.currency}`,
                    },
                    {
                        label: 'Recorded',
                        value: formatDateTime(order.created_at),
                    },
                ]}
                actions={
                    <Link
                        href={`/sessions/${sessionUuid}`}
                        className="inline-flex items-center gap-2 text-[12px] text-ink-soft hover:text-ink"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" strokeWidth={1.6} />
                        Decision record
                    </Link>
                }
            />

            <section
                className={`mt-8 border ${
                    paid
                        ? 'border-pass/40 bg-pass/5 text-pass'
                        : view.tone === 'fail'
                          ? 'border-fail/40 bg-fail/5 text-fail'
                          : view.tone === 'warn'
                            ? 'border-warn/40 bg-warn/5 text-warn'
                            : 'border-rule bg-paper-deep text-ink'
                }`}
            >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink/15 px-5 py-2.5">
                    <span className="flex items-center gap-2 text-[10px] font-semibold tracking-[0.16em] uppercase">
                        {paid ? (
                            <CheckCircle2
                                className="h-4 w-4"
                                strokeWidth={1.8}
                            />
                        ) : view.tone === 'fail' ? (
                            <XCircle className="h-4 w-4" strokeWidth={1.8} />
                        ) : (
                            <Clock className="h-4 w-4" strokeWidth={1.8} />
                        )}
                        {paid ? 'Verified by PayPal' : 'No payment recorded'}
                    </span>
                    <span className="text-[10px] tracking-[0.14em] text-ink/50 uppercase">
                        {order.environment}
                    </span>
                </div>

                <div className="grid gap-6 px-5 py-6 text-ink sm:grid-cols-[1.4fr_1fr] sm:items-end">
                    <div>
                        <p className="text-[10px] tracking-[0.14em] text-muted uppercase">
                            {paid
                                ? 'Amount captured'
                                : 'Amount that was approved'}
                        </p>
                        <p className="mt-1 text-[clamp(2.6rem,6vw,4rem)] leading-none font-semibold tracking-[-0.03em]">
                            {order.amount}
                        </p>
                        <p className="mt-2 text-[12px] text-ink-soft">
                            {product?.name ?? 'Product unavailable'} ·{' '}
                            {product?.merchant ?? 'merchant unavailable'}
                        </p>
                    </div>
                    <dl className="space-y-2 border-t border-ink/15 pt-3 text-[11px] sm:border-t-0 sm:border-l sm:pt-0 sm:pl-5">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Approved total</dt>
                            <dd>{approval.total}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Currency</dt>
                            <dd>{order.currency}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Capture</dt>
                            <dd className="font-mono text-[10px]">
                                {order.capture_id ?? 'not captured'}
                            </dd>
                        </div>
                    </dl>
                </div>

                {!paid && order.failure_reason && (
                    <p className="border-t border-ink/15 px-5 py-3 text-[12px] text-ink">
                        {order.failure_reason}
                    </p>
                )}
            </section>

            <div className="mt-8 grid gap-8 lg:grid-cols-12">
                <section className="lg:col-span-7">
                    <h2 className="border-t-2 border-ink pt-3 text-[20px] font-semibold">
                        Payment identifiers
                    </h2>
                    <dl className="mt-3 border-t border-rule text-[12px]">
                        {[
                            ['PayPal order ID', order.paypal_order_id],
                            ['PayPal capture ID', order.capture_id],
                            ['PayPal payer ID', order.payer_id],
                            ['TrustCart order', order.uuid],
                            ['Approval', approval.uuid],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="flex flex-col justify-between gap-1 border-b border-rule py-3 sm:flex-row sm:items-baseline sm:gap-6"
                            >
                                <dt className="text-[10px] tracking-[0.12em] text-muted uppercase">
                                    {label}
                                </dt>
                                <dd className="font-mono text-[11px] break-all sm:text-right">
                                    {value ?? '—'}
                                </dd>
                            </div>
                        ))}
                    </dl>

                    <p className="mt-4 flex items-start gap-2 text-[10px] leading-relaxed text-muted">
                        <FileText
                            className="mt-0.5 h-3.5 w-3.5 shrink-0"
                            strokeWidth={1.6}
                        />
                        Only identifiers, statuses and amount snapshots are
                        stored. No card number, CVV or PayPal account password
                        ever reaches TrustCart.
                    </p>

                    <div className="mt-6 flex flex-wrap gap-3">
                        <Link
                            href={`/audit/${sessionUuid}`}
                            className="btn-ink"
                        >
                            Open full audit record
                            <ArrowRight className="h-4 w-4" strokeWidth={1.6} />
                        </Link>
                        <Link href="/assistant" className="btn-quiet">
                            Run another decision
                        </Link>
                    </div>
                </section>

                <aside className="lg:col-span-5">
                    <h2 className="border-t-2 border-ink pt-3 text-[20px] font-semibold">
                        Payment events
                    </h2>
                    <ol className="mt-3 border-t border-rule">
                        {events.length === 0 && (
                            <li className="border-b border-rule py-4 text-[12px] text-muted">
                                No payment events were recorded.
                            </li>
                        )}
                        {events.map((event) => (
                            <li
                                key={event.id}
                                className="border-b border-rule py-3"
                            >
                                <div className="flex items-baseline justify-between gap-3">
                                    <span className="font-mono text-[11px]">
                                        {event.type}
                                    </span>
                                    <StatusStamp
                                        kind={(
                                            event.status ?? 'created'
                                        ).toLowerCase()}
                                    />
                                </div>
                                <p className="mt-1 text-[10px] text-muted">
                                    {formatDateTime(event.created_at)}
                                    {event.provider_event_id
                                        ? ` · ${event.provider_event_id.slice(0, 22)}`
                                        : ''}
                                </p>
                            </li>
                        ))}
                    </ol>

                    <p className="mt-4 border border-rule bg-paper-deep px-3 py-2.5 text-[10px] leading-relaxed text-muted">
                        This demo runs against the PayPal sandbox. A “paid”
                        result here is a sandbox capture, not a real transfer of
                        funds.
                    </p>
                </aside>
            </div>
        </>
    );
}
