import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Loader2,
    Lock,
    RefreshCw,
    ShieldAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import ProductIllustration from '@/components/trust/product-illustration';
import RecordHeader from '@/components/trust/record-header';
import StatusStamp from '@/components/trust/status-stamp';
import type { ProductSnapshot } from '@/types/trustcart';

type ApprovalPayload = {
    uuid: string;
    status: string;
    quantity: number;
    unit_price: string;
    unit_price_cents: number;
    total: string;
    total_cents: number;
    currency: string;
    expires_at: string;
    seconds_remaining: number;
    authorized_at: string | null;
    invalidated_reason: string | null;
};

function reasonCopy(reason: string | null): string {
    switch (reason) {
        case 'price_changed_by_merchant':
        case 'price_changed_before_authorization':
        case 'price_changed_at_checkout':
            return 'A merchant price sync changed the catalog price after this review was prepared. The authorization that quoted the old total is void.';
        case 'out_of_stock':
        case 'out_of_stock_at_checkout':
            return 'The product went out of stock before checkout. No payment was taken.';
        case 'buyer_cancelled_at_paypal':
            return 'You cancelled at PayPal. The authorization is closed and no capture was attempted.';
        case 'superseded_by_new_review':
            return 'A newer review replaced this authorization.';
        default:
            return 'This authorization can no longer be used.';
    }
}

export default function ApprovalShow({
    approval,
    product,
    sessionUuid,
    price_changed,
    catalog_price,
    stock,
    paypal,
    order,
}: {
    approval: ApprovalPayload;
    product: ProductSnapshot;
    sessionUuid: string;
    price_changed: boolean;
    catalog_price: string;
    stock: number;
    paypal: { configured: boolean; environment: string };
    order: { uuid: string; status: string; amount: string } | null;
    demo_mode: boolean;
}) {
    const { data, setData, post, processing, errors } = useForm({
        authorize: approval.status === 'authorized',
    });
    const reprice = useForm({});
    const [secondsLeft, setSecondsLeft] = useState(approval.seconds_remaining);

    useEffect(() => {
        setSecondsLeft(approval.seconds_remaining);
        const timer = window.setInterval(() => {
            setSecondsLeft((value) => Math.max(0, value - 1));
        }, 1000);

        return () => window.clearInterval(timer);
    }, [approval.expires_at, approval.seconds_remaining]);

    const live =
        ['pending', 'authorized'].includes(approval.status) && secondsLeft > 0;
    const blocked = price_changed || !live || stock < approval.quantity;
    const canSubmit = live && !blocked && paypal.configured && data.authorize;

    const minutes = Math.floor(secondsLeft / 60);
    const seconds = String(secondsLeft % 60).padStart(2, '0');

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        post(`/approvals/${approval.uuid}/authorize`);
    };

    const simulatePriceChange = () => {
        const confirmed = window.confirm(
            'Demo control: change this product’s catalog price by 12%. This approval will be voided immediately. Continue?',
        );

        if (confirmed) {
            reprice.post(`/approvals/${approval.uuid}/simulate-price-change`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title={`Authorize ${approval.total}`} />

            <RecordHeader
                kicker="Authorization instrument"
                id={`AP-${approval.uuid.slice(0, 8).toUpperCase()}`}
                title={
                    approval.status === 'completed' ||
                    approval.status === 'consumed' ? (
                        'This purchase is already paid.'
                    ) : live ? (
                        <>Authorize {approval.total}</>
                    ) : (
                        'This authorization is closed.'
                    )
                }
                description={
                    <>
                        This is the only point where a human authorizes money.
                        The amount is re-read from the catalog on submit; the
                        model has no input here.
                    </>
                }
                meta={[
                    {
                        label: 'Status',
                        value: <StatusStamp kind={approval.status} />,
                    },
                    {
                        label: 'Environment',
                        value: `${paypal.environment} · ${approval.currency}`,
                    },
                    {
                        label: 'Window',
                        value: live ? (
                            <span className="font-mono text-[12px]">
                                {minutes}:{seconds}
                            </span>
                        ) : (
                            'Closed'
                        ),
                    },
                    {
                        label: 'Linked record',
                        value: (
                            <Link
                                href={`/sessions/${sessionUuid}`}
                                className="underline decoration-1 underline-offset-4"
                            >
                                DR-{sessionUuid.slice(0, 8).toUpperCase()}
                            </Link>
                        ),
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

            <div className="mt-8 grid gap-8 lg:grid-cols-12 lg:gap-12">
                <section className="lg:col-span-7">
                    <div className="border border-rule">
                        <div className="record-head">
                            <span className="text-[10px] tracking-[0.16em] uppercase">
                                Priced total
                            </span>
                            <span className="text-[10px] text-muted">
                                Server-side catalog price
                            </span>
                        </div>

                        <div className="flex flex-wrap items-center gap-5 border-b border-rule px-4 py-5 sm:px-6">
                            <div className="h-20 w-28 border border-rule p-2 text-ink-soft">
                                <ProductIllustration
                                    kind={product.illustration}
                                    label={`${product.name} line drawing`}
                                />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-[17px] font-semibold">
                                    {product.name}
                                </p>
                                <p className="mt-0.5 text-[11px] text-muted">
                                    {product.brand} · {product.merchant} ·{' '}
                                    {product.sku}
                                </p>
                                <p className="mt-1.5 text-[11px] text-ink-soft">
                                    {stock} in stock · merchant{' '}
                                    {product.merchant_verified
                                        ? 'verified'
                                        : 'unverified'}
                                </p>
                            </div>
                            <StatusStamp
                                kind={price_changed ? 'invalidated' : 'pending'}
                            />
                        </div>

                        <div className="px-4 py-4 sm:px-6">
                            <dl className="space-y-2.5 text-[13px]">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted">Unit price</dt>
                                    <dd>{approval.unit_price}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted">Quantity</dt>
                                    <dd>{approval.quantity}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted">Currency</dt>
                                    <dd>{approval.currency}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted">Merchant</dt>
                                    <dd>{product.merchant}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-4 border-t border-rule pt-3">
                                    <dt className="text-[11px] tracking-[0.14em] text-muted uppercase">
                                        Total authorized
                                    </dt>
                                    <dd className="text-[30px] leading-none font-semibold">
                                        {approval.total}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {price_changed && (
                            <div className="border-t border-fail/40 bg-fail/5 px-4 py-4 text-[12px] leading-relaxed text-fail sm:px-6">
                                <p className="flex items-center gap-2 text-[10px] font-semibold tracking-[0.12em] uppercase">
                                    <ShieldAlert
                                        className="h-4 w-4"
                                        strokeWidth={1.8}
                                    />
                                    Price changed — approval void
                                </p>
                                <p className="mt-1.5">
                                    The catalog now prices this product at{' '}
                                    {catalog_price}. This approval quotes{' '}
                                    {approval.unit_price}. TrustCart will not
                                    charge the difference silently. Go back to
                                    the decision record and authorize a new
                                    total.
                                </p>
                            </div>
                        )}

                        {startsClosed(approval.status) ? (
                            <div className="border-t border-rule bg-paper-deep px-4 py-5 sm:px-6">
                                <p className="text-[10px] tracking-[0.16em] text-muted uppercase">
                                    Closed
                                </p>
                                <p className="mt-1.5 max-w-[54ch] text-[12px] leading-relaxed text-ink-soft">
                                    {reasonCopy(approval.invalidated_reason)}
                                </p>
                                <div className="mt-4 flex flex-wrap gap-3">
                                    <Link
                                        href={`/sessions/${sessionUuid}`}
                                        className="btn-ink"
                                    >
                                        Return to record
                                    </Link>
                                    {order && (
                                        <Link
                                            href={`/checkout/${order.uuid}/result`}
                                            className="btn-quiet"
                                        >
                                            View payment result
                                        </Link>
                                    )}
                                </div>
                            </div>
                        ) : !paypal.configured ? (
                            <div className="border-t border-rule bg-paper-deep px-4 py-5 sm:px-6">
                                <p className="flex items-center gap-2 text-[10px] font-semibold tracking-[0.14em] text-warn uppercase">
                                    <Lock
                                        className="h-3.5 w-3.5"
                                        strokeWidth={1.8}
                                    />
                                    PayPal sandbox not connected
                                </p>
                                <p className="mt-2 max-w-[56ch] text-[12px] leading-relaxed text-ink-soft">
                                    Add sandbox credentials to{' '}
                                    <code className="font-mono text-[11px]">
                                        .env
                                    </code>{' '}
                                    and clear the config cache. TrustCart will
                                    not simulate a successful payment in the
                                    meantime.
                                </p>
                                <pre className="mt-3 overflow-x-auto border border-rule bg-paper px-3 py-2 font-mono text-[10px] leading-relaxed text-ink-soft">
                                    {`PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...
PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com

php artisan config:clear`}
                                </pre>
                                <p className="mt-2 text-[10px] text-muted">
                                    Create these at developer.paypal.com → Apps
                                    &amp; Credentials (Sandbox). Never commit
                                    the secret.
                                </p>
                            </div>
                        ) : (
                            <form
                                onSubmit={submit}
                                className="border-t border-rule px-4 py-5 sm:px-6"
                            >
                                <label
                                    htmlFor="authorize"
                                    className={`flex cursor-pointer items-start gap-3 border p-3 transition-colors ${
                                        data.authorize
                                            ? 'border-signal bg-signal/10'
                                            : 'border-rule-strong'
                                    } ${blocked ? 'cursor-not-allowed opacity-60' : ''}`}
                                >
                                    <span
                                        className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center border ${
                                            data.authorize
                                                ? 'border-ink bg-ink text-paper'
                                                : 'border-rule-strong'
                                        }`}
                                    >
                                        {data.authorize && (
                                            <Check
                                                className="h-3 w-3"
                                                strokeWidth={2.4}
                                            />
                                        )}
                                    </span>
                                    <input
                                        id="authorize"
                                        type="checkbox"
                                        checked={data.authorize}
                                        disabled={blocked}
                                        onChange={(event) =>
                                            setData(
                                                'authorize',
                                                event.target.checked,
                                            )
                                        }
                                        className="sr-only"
                                    />
                                    <span className="text-[12px] leading-relaxed">
                                        I authorize TrustCart AI to start a{' '}
                                        <strong>
                                            PayPal sandbox checkout for{' '}
                                            {approval.total}
                                        </strong>{' '}
                                        for this product. I understand this is
                                        synthetic data and no real money moves.
                                    </span>
                                </label>

                                {errors.authorize && (
                                    <p className="mt-2 text-[12px] text-fail">
                                        {errors.authorize}
                                    </p>
                                )}

                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                                    <p className="text-[10px] text-muted">
                                        Expires in {minutes}:{seconds} · amount
                                        locked to this page
                                    </p>
                                    <button
                                        type="submit"
                                        className="btn-primary"
                                        disabled={!canSubmit || processing}
                                    >
                                        {processing ? (
                                            <>
                                                <Loader2
                                                    className="h-3.5 w-3.5 animate-spin"
                                                    strokeWidth={1.8}
                                                />
                                                Opening PayPal
                                            </>
                                        ) : (
                                            <>
                                                Continue to PayPal
                                                <ArrowRight
                                                    className="h-4 w-4"
                                                    strokeWidth={1.6}
                                                />
                                            </>
                                        )}
                                    </button>
                                </div>

                                {blocked && (
                                    <p className="mt-2 text-[11px] text-fail">
                                        {stock < approval.quantity
                                            ? 'Insufficient stock. Return to the record to choose again.'
                                            : secondsLeft <= 0
                                              ? 'This authorization window has closed. Return to the record to start a new review.'
                                              : 'Resolve the issue above before continuing.'}
                                    </p>
                                )}
                            </form>
                        )}
                    </div>

                    <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border border-rule px-4 py-3">
                        <div>
                            <p className="text-[10px] tracking-[0.14em] text-muted uppercase">
                                Demonstration control
                            </p>
                            <p className="mt-1 text-[11px] text-ink-soft">
                                Re-price the product after this approval exists.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={simulatePriceChange}
                            disabled={reprice.processing}
                            className="btn-quiet"
                        >
                            <RefreshCw
                                className={`h-3.5 w-3.5 ${reprice.processing ? 'animate-spin' : ''}`}
                                strokeWidth={1.6}
                            />
                            Change price +12%
                        </button>
                    </div>
                </section>

                <aside className="lg:col-span-5">
                    <div className="border-t-2 border-ink pt-3">
                        <h2 className="text-[20px] font-semibold">
                            What this authorization covers
                        </h2>
                    </div>

                    <ul className="mt-3 border-t border-rule">
                        {[
                            [
                                'One charge',
                                `${approval.total} in ${approval.currency}, captured once.`,
                            ],
                            [
                                'One merchant',
                                `${product.merchant} via PayPal sandbox.`,
                            ],
                            [
                                'An expiry',
                                'The authorization dies when the timer does.',
                            ],
                            [
                                'No card data',
                                'PayPal holds the funding source. TrustCart stores IDs, not credentials.',
                            ],
                        ].map(([title, body]) => (
                            <li
                                key={title}
                                className="flex gap-3 border-b border-rule py-3"
                            >
                                <Check
                                    className="mt-0.5 h-3.5 w-3.5 shrink-0 text-pass"
                                    strokeWidth={2}
                                />
                                <div>
                                    <p className="text-[12px] font-medium">
                                        {title}
                                    </p>
                                    <p className="mt-0.5 text-[11px] leading-relaxed text-muted">
                                        {body}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>

                    <div className="mt-6 border border-rule bg-paper-deep p-4">
                        <p className="text-[10px] tracking-[0.16em] text-muted uppercase">
                            After you approve
                        </p>
                        <ol className="mt-2 space-y-2 text-[11px] leading-relaxed text-ink-soft">
                            <li>1. PayPal opens its own approval screen.</li>
                            <li>
                                2. On return, the server asks PayPal for the
                                order status.
                            </li>
                            <li>
                                3. The server captures once, verifies the
                                amount, and records the capture ID.
                            </li>
                            <li>
                                4. The result page quotes PayPal's status
                                verbatim.
                            </li>
                        </ol>
                    </div>

                    <p className="mt-4 text-[10px] leading-relaxed text-muted">
                        If PayPal is unavailable or the buyer cancels, the order
                        is recorded as failed or cancelled. There is no fallback
                        that fakes a success.
                    </p>
                </aside>
            </div>
        </>
    );
}

function startsClosed(status: string): boolean {
    return [
        'invalidated',
        'expired',
        'cancelled',
        'consumed',
        'completed',
    ].includes(status);
}
