import { Head, useForm } from '@inertiajs/react';
import { RefreshCw, ShieldAlert } from 'lucide-react';
import ProductIllustration from '@/components/trust/product-illustration';
import RecordHeader from '@/components/trust/record-header';
import { formatDate } from '@/lib/format';
import type { ProductSnapshot } from '@/types/trustcart';

type CatalogProduct = ProductSnapshot & {
    description: string;
    integrity_flag: boolean;
};

const categoryOrder = [
    'headphones',
    'laptops',
    'monitors',
    'keyboards',
    'accessories',
];

const categoryLabels: Record<string, string> = {
    headphones: 'Headphones',
    laptops: 'Laptops',
    monitors: 'Monitors',
    keyboards: 'Keyboards',
    accessories: 'Accessories',
};

function specValue(value: unknown): string {
    if (typeof value === 'number') {
        return String(value);
    }

    return String(value);
}

export default function Catalog({
    products,
    lastSynced,
    featureLabels,
    minSpecLabels,
}: {
    products: CatalogProduct[];
    lastSynced: string | null;
    featureLabels: Record<string, string>;
    minSpecLabels: Record<string, string>;
}) {
    const reset = useForm({});

    const grouped = categoryOrder
        .map((category) => ({
            category,
            items: products.filter((product) => product.category === category),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <>
            <Head title="Catalog evidence" />

            <RecordHeader
                kicker="Catalog"
                id={`${products.length} synthetic listings`}
                title="The only facts the agent is allowed to use."
                description="Prices, stock, merchants and specifications are stored in the database. The model receives a structured requirement, never this page, and never a description. One listing contains an instruction-like payload on purpose."
                meta={[
                    { label: 'Currency', value: 'USD' },
                    {
                        label: 'Data',
                        value: 'Fictional products and merchants',
                    },
                    { label: 'Last sync', value: formatDate(lastSynced) },
                ]}
                actions={
                    <button
                        type="button"
                        className="btn-quiet no-print"
                        disabled={reset.processing}
                        onClick={() => reset.post('/demo/reset-catalog')}
                    >
                        <RefreshCw
                            className={`h-3.5 w-3.5 ${reset.processing ? 'animate-spin' : ''}`}
                            strokeWidth={1.6}
                        />
                        Reset demo catalog
                    </button>
                }
            />

            <div className="mt-6 flex flex-wrap gap-x-8 gap-y-2 border border-rule bg-paper-deep px-4 py-3 text-[11px] sm:px-6">
                <span className="flex items-center gap-2">
                    <span className="inline-block h-2 w-2 border border-rule-strong" />
                    Missing evidence is shown, never inferred
                </span>
                <span className="flex items-center gap-2">
                    <span className="inline-block h-2 w-2 border border-warn/60 bg-warn/20" />
                    Gaps make a product unverified, not qualified
                </span>
                <span className="flex items-center gap-2">
                    <span className="inline-block h-2 w-2 border border-fail/60 bg-fail/20" />
                    Instruction-like text is quarantined
                </span>
            </div>

            {grouped.map((group) => (
                <section key={group.category} className="mt-10">
                    <div className="flex items-baseline justify-between border-b-2 border-ink pb-2">
                        <h2 className="text-[20px] font-semibold">
                            {categoryLabels[group.category] ?? group.category}
                        </h2>
                        <span className="text-[10px] text-muted">
                            {group.items.length} listing
                            {group.items.length === 1 ? '' : 's'}
                        </span>
                    </div>

                    {group.items.map((product) => (
                        <article
                            key={product.sku}
                            className="grid grid-cols-[5rem_1fr] gap-4 border-b border-rule py-6 sm:grid-cols-[8rem_1fr_10rem] sm:gap-6"
                        >
                            <div className="h-20 w-20 border border-rule p-1.5 text-ink-soft sm:h-24 sm:w-full sm:p-2">
                                <ProductIllustration
                                    kind={product.illustration}
                                    label={`${product.name} line drawing`}
                                />
                            </div>

                            <div className="min-w-0">
                                <p className="text-[9px] tracking-[0.14em] text-muted uppercase">
                                    {product.brand} · {product.sku}
                                </p>
                                <h3 className="mt-0.5 text-[17px] font-semibold">
                                    {product.name}
                                </h3>
                                <p className="mt-1 text-[12px] text-ink-soft">
                                    {product.tagline}
                                </p>

                                <p className="mt-2 max-w-[62ch] text-[11px] leading-relaxed whitespace-pre-line text-muted">
                                    {product.description}
                                </p>

                                {product.integrity_flag && (
                                    <div className="mt-3 border border-fail/40 bg-fail/5 px-3 py-2">
                                        <p className="flex items-center gap-2 text-[10px] font-semibold tracking-[0.12em] text-fail uppercase">
                                            <ShieldAlert
                                                className="h-3.5 w-3.5"
                                                strokeWidth={1.8}
                                            />
                                            Instruction-like text · quarantined
                                        </p>
                                        <p className="mt-1 text-[11px] leading-relaxed text-ink-soft">
                                            This description tries to instruct
                                            the model to ignore the rules and
                                            change the total. It is displayed to
                                            the operator, excluded from prompts,
                                            and cannot affect a check, price or
                                            amount.
                                        </p>
                                    </div>
                                )}

                                <div className="mt-3 flex flex-wrap gap-1.5">
                                    {(product.specs.features ?? []).map(
                                        (feature) => (
                                            <span
                                                key={feature}
                                                className="border border-rule-strong px-1.5 py-0.5 text-[10px] text-ink-soft"
                                            >
                                                {featureLabels[feature] ??
                                                    feature}
                                            </span>
                                        ),
                                    )}
                                    {Object.entries(product.specs)
                                        .filter(
                                            ([key, value]) =>
                                                key !== 'features' &&
                                                key !== 'feature_flags' &&
                                                typeof value === 'number',
                                        )
                                        .map(([key, value]) => (
                                            <span
                                                key={key}
                                                className="border border-rule px-1.5 py-0.5 text-[10px] text-muted"
                                            >
                                                {minSpecLabels[key] ?? key}:{' '}
                                                {specValue(value)}
                                            </span>
                                        ))}
                                </div>

                                {product.data_gaps.length > 0 && (
                                    <p className="mt-2 flex flex-wrap items-center gap-1.5 text-[10px] text-warn">
                                        <span className="font-semibold tracking-[0.1em] uppercase">
                                            Missing data
                                        </span>
                                        {product.data_gaps.map((gap) => (
                                            <span
                                                key={gap}
                                                className="border border-warn/40 bg-warn/5 px-1.5 py-0.5"
                                            >
                                                {featureLabels[gap] ??
                                                    minSpecLabels[gap] ??
                                                    gap}
                                            </span>
                                        ))}
                                    </p>
                                )}
                            </div>

                            <div className="col-span-2 flex flex-wrap items-end gap-x-6 gap-y-1 border-t border-rule pt-2 sm:col-span-1 sm:block sm:border-0 sm:pt-0 sm:text-right">
                                <p className="text-[22px] font-semibold">
                                    {product.price}
                                </p>
                                <p className="text-[10px] text-muted">
                                    {product.currency}
                                </p>
                                <p className="mt-0 text-[11px] sm:mt-2">
                                    {product.stock > 0
                                        ? `${product.stock} in stock`
                                        : 'Out of stock'}
                                </p>
                                <p className="mt-0 text-[11px] text-ink-soft sm:mt-0.5">
                                    {product.merchant}
                                </p>
                                <p className="text-[10px] text-muted">
                                    {product.merchant_verified
                                        ? 'Verified merchant'
                                        : 'Unverified merchant'}
                                </p>
                                <p className="mt-0 border-0 pt-0 text-[10px] text-muted sm:mt-2 sm:border-t sm:border-rule sm:pt-2">
                                    Synced {formatDate(product.last_synced_at)}
                                </p>
                            </div>
                        </article>
                    ))}
                </section>
            ))}

            <p className="mt-8 max-w-[80ch] text-[10px] leading-relaxed text-muted">
                Every listing is fictional and built for the PayPal AI Hackathon
                demonstration. Specifications are stored as structured JSON;
                prose is never parsed into a constraint. The catalog has no live
                merchant feed, no affiliate relationship and no real inventory.
            </p>
        </>
    );
}
