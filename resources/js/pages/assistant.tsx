import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, CornerDownLeft, Loader2 } from 'lucide-react';
import { useState } from 'react';
import RecordHeader from '@/components/trust/record-header';

type Example = { label: string; category: string; intent: string };
type Feature = { key: string; label: string };

const categoryLabels: Record<string, string> = {
    headphones: 'Headphones',
    laptops: 'Laptops',
    monitors: 'Monitors',
    keyboards: 'Keyboards',
    accessories: 'Accessories',
};

export default function Assistant({
    categories,
    features,
    examples,
    ai,
}: {
    categories: string[];
    features: Feature[];
    examples: Example[];
    ai: { provider: string; configured: boolean };
}) {
    const { data, setData, post, processing, errors } = useForm({
        intent: '',
        category: 'headphones',
        budget: '',
    });
    const [activeExample, setActiveExample] = useState<string | null>(null);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post('/assistant');
    };

    const useExample = (example: Example) => {
        setData({
            intent: example.intent,
            category: example.category,
            budget: '',
        });
        setActiveExample(example.label);
    };

    return (
        <>
            <Head title="Shopping assistant" />

            <RecordHeader
                kicker="New decision"
                id="INPUT / REQUIREMENT"
                title="State the requirement. Not the product."
                description="Describe the purchase in your own words. The model turns it into constraints; the rules engine decides what qualifies; you decide whether to pay."
                meta={[
                    { label: 'Catalog', value: '8 synthetic products' },
                    {
                        label: 'Parser',
                        value: ai.configured
                            ? ai.provider
                            : 'deterministic fallback',
                    },
                    { label: 'Payment', value: 'PayPal sandbox · USD' },
                ]}
            />

            <div className="mt-8 grid gap-10 lg:grid-cols-12 lg:gap-12">
                <form onSubmit={submit} className="lg:col-span-7">
                    <div className="border border-rule">
                        <div className="record-head">
                            <span className="text-[10px] tracking-[0.16em] uppercase">
                                Your request
                            </span>
                            <span className="text-[10px] text-muted">
                                Plain language
                            </span>
                        </div>

                        <div className="px-4 py-5 sm:px-6">
                            <label htmlFor="intent" className="sr-only">
                                Describe what you want to buy
                            </label>
                            <textarea
                                id="intent"
                                value={data.intent}
                                onChange={(event) =>
                                    setData('intent', event.target.value)
                                }
                                onKeyDown={(event) => {
                                    if (
                                        (event.metaKey || event.ctrlKey) &&
                                        event.key === 'Enter'
                                    ) {
                                        event.preventDefault();
                                        post('/assistant');
                                    }
                                }}
                                rows={6}
                                maxLength={1200}
                                placeholder="Find wireless headphones under $100 with active noise cancellation and at least 20 hours of battery life."
                                className="w-full resize-none border-0 bg-transparent p-0 text-[19px] leading-[1.5] text-ink placeholder:text-rule-strong focus:ring-0 focus:outline-none"
                            />
                            <div className="mt-3 flex items-center justify-between border-t border-rule pt-2 text-[10px] text-muted">
                                <span>
                                    {data.intent.length}/1200 · the model never
                                    sees a price it can change
                                </span>
                                <span className="hidden sm:inline">
                                    ⌘/Ctrl + Enter
                                </span>
                            </div>
                            {errors.intent && (
                                <p className="mt-2 text-[12px] text-fail">
                                    {errors.intent}
                                </p>
                            )}
                        </div>

                        <div className="border-t border-rule px-4 py-4 sm:px-6">
                            <fieldset>
                                <legend className="text-[10px] tracking-[0.16em] text-muted uppercase">
                                    Category
                                </legend>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {categories.map((category) => (
                                        <button
                                            key={category}
                                            type="button"
                                            aria-pressed={
                                                data.category === category
                                            }
                                            onClick={() =>
                                                setData('category', category)
                                            }
                                            className={`border px-2.5 py-1.5 text-[11px] tracking-[0.06em] transition-colors ${
                                                data.category === category
                                                    ? 'border-ink bg-ink text-paper'
                                                    : 'border-rule-strong text-ink-soft hover:border-ink hover:text-ink'
                                            }`}
                                        >
                                            {categoryLabels[category] ??
                                                category}
                                        </button>
                                    ))}
                                </div>
                                {errors.category && (
                                    <p className="mt-2 text-[12px] text-fail">
                                        {errors.category}
                                    </p>
                                )}
                            </fieldset>

                            <div className="mt-5 max-w-[16rem]">
                                <label
                                    htmlFor="budget"
                                    className="text-[10px] tracking-[0.16em] text-muted uppercase"
                                >
                                    Budget ceiling (optional)
                                </label>
                                <div className="mt-2 flex items-center border border-rule-strong focus-within:border-ink">
                                    <span className="border-r border-rule px-2.5 text-[12px] text-muted">
                                        $
                                    </span>
                                    <input
                                        id="budget"
                                        type="number"
                                        min={1}
                                        step={1}
                                        value={data.budget}
                                        onChange={(event) =>
                                            setData(
                                                'budget',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="100"
                                        className="w-full border-0 bg-transparent px-2.5 py-2 text-[13px] focus:ring-0 focus:outline-none"
                                    />
                                    <span className="px-2.5 text-[10px] text-muted">
                                        USD
                                    </span>
                                </div>
                                <p className="mt-1.5 text-[10px] leading-relaxed text-muted">
                                    Used only if your sentence does not state a
                                    limit.
                                </p>
                                {errors.budget && (
                                    <p className="mt-1 text-[12px] text-fail">
                                        {errors.budget}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-rule bg-paper-deep px-4 py-3 sm:px-6">
                            <p className="text-[10px] text-muted">
                                Nothing is charged. This creates a decision
                                record.
                            </p>
                            <button
                                type="submit"
                                className="btn-primary"
                                disabled={processing}
                            >
                                {processing ? (
                                    <>
                                        <Loader2
                                            className="h-3.5 w-3.5 animate-spin"
                                            strokeWidth={1.8}
                                        />
                                        Evaluating
                                    </>
                                ) : (
                                    <>
                                        Evaluate request
                                        <ArrowRight
                                            className="h-4 w-4"
                                            strokeWidth={1.6}
                                        />
                                    </>
                                )}
                            </button>
                        </div>
                    </div>

                    <div className="mt-8">
                        <p className="text-[10px] tracking-[0.16em] text-muted uppercase">
                            Demo requests
                        </p>
                        <div className="mt-2 border-t border-rule">
                            {examples.map((example) => (
                                <button
                                    key={example.label}
                                    type="button"
                                    onClick={() => useExample(example)}
                                    className={`flex w-full items-baseline justify-between gap-4 border-b border-rule py-3 text-left transition-colors ${
                                        activeExample === example.label
                                            ? 'text-ink'
                                            : 'text-ink-soft hover:text-ink'
                                    }`}
                                >
                                    <span>
                                        <span className="text-[10px] tracking-[0.12em] text-muted uppercase">
                                            {example.label}
                                        </span>
                                        <span className="mt-0.5 block text-[12px]">
                                            {example.intent}
                                        </span>
                                    </span>
                                    <CornerDownLeft
                                        className="h-3.5 w-3.5 shrink-0 text-muted"
                                        strokeWidth={1.6}
                                    />
                                </button>
                            ))}
                        </div>
                    </div>
                </form>

                <aside className="lg:col-span-5">
                    <div className="bg-panel text-paper">
                        <div className="flex items-center justify-between border-b border-white/15 px-5 py-2.5 text-[9px] tracking-[0.18em] uppercase">
                            <span>Decision pipeline</span>
                            <span className="text-white/45">
                                {ai.configured ? ai.provider : 'fallback'}
                            </span>
                        </div>

                        <ol className="px-5 py-4">
                            {[
                                [
                                    'Parse',
                                    'Model turns prose into a validated requirement. Unknown vocabulary is dropped.',
                                ],
                                [
                                    'Retrieve',
                                    'Products come from the catalog by category. The model cannot add one.',
                                ],
                                [
                                    'Validate',
                                    'Budget, stock, currency, features and minimums are checked in PHP. Missing evidence is unverified.',
                                ],
                                [
                                    'Explain',
                                    'Model narrative describes the verdict. It cannot change it.',
                                ],
                                [
                                    'Stop',
                                    'A human approves a priced total. Only the server can capture it.',
                                ],
                            ].map(([title, body], index) => (
                                <li
                                    key={title}
                                    className="flex gap-4 border-b border-white/10 py-3 last:border-b-0"
                                >
                                    <span className="w-4 font-mono text-[10px] text-white/35">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <div>
                                        <p className="text-[12px] font-semibold">
                                            {title}
                                        </p>
                                        <p className="mt-0.5 text-[11px] leading-relaxed text-white/55">
                                            {body}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>

                    <div className="mt-5 border border-rule p-5">
                        <p className="text-[10px] tracking-[0.16em] text-muted uppercase">
                            Model permissions
                        </p>
                        <div className="mt-3 space-y-2 text-[12px] leading-relaxed">
                            <p className="flex gap-2">
                                <span className="text-pass">✓</span>
                                Interpret language, rank qualified products,
                                explain tradeoffs.
                            </p>
                            <p className="flex gap-2">
                                <span className="text-fail">✕</span>
                                Set a price, change stock, override a failed
                                check, approve a payment, move money.
                            </p>
                        </div>
                        <p className="mt-4 border-t border-rule pt-3 text-[10px] leading-relaxed text-muted">
                            If the model is unavailable, a deterministic parser
                            extracts the same structure and the record states
                            which one was used.
                        </p>
                    </div>

                    <div className="mt-5 border border-rule bg-paper-deep p-5">
                        <p className="text-[10px] tracking-[0.16em] text-muted uppercase">
                            Feature vocabulary
                        </p>
                        <p className="mt-2 flex flex-wrap gap-1.5">
                            {features.map((feature) => (
                                <span
                                    key={feature.key}
                                    className="border border-rule-strong px-1.5 py-0.5 text-[10px] text-ink-soft"
                                >
                                    {feature.label}
                                </span>
                            ))}
                        </p>
                        <p className="mt-3 text-[10px] leading-relaxed text-muted">
                            Only these keys can become a hard requirement.
                            Anything else the model returns is discarded before
                            validation.
                        </p>
                    </div>
                </aside>
            </div>
        </>
    );
}
