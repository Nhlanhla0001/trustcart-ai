import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = '.impeccable/review';
await mkdir(out, { recursive: true });

const browser = await chromium.launch({ channel: 'chrome' });
const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
});
const page = await context.newPage();
const problems = [];
page.on(
    'console',
    (m) => m.type() === 'error' && problems.push(`console: ${m.text()}`),
);
page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
page.on('dialog', (dialog) => dialog.accept());

// 1. Requirement
await page.goto(`${base}/assistant`, { waitUntil: 'networkidle' });
await page
    .getByRole('textbox')
    .first()
    .fill(
        'Find wireless headphones under $100 with active noise cancellation and at least 20 hours of battery life.',
    );
await page.getByRole('button', { name: 'Evaluate request' }).click();
await page.waitForURL(/\/sessions\//, { timeout: 90000 });
await page.waitForLoadState('networkidle');
await page.waitForTimeout(800);
console.log('decision:', page.url());
await page.screenshot({ path: `${out}/flow-decision.png`, fullPage: true });

// 2. Authorization instrument
await page.getByRole('button', { name: /Review & authorize/i }).click();
await page.waitForURL(/\/approvals\//, { timeout: 30000 });
await page.waitForLoadState('networkidle');
await page.waitForTimeout(800);
console.log('approval:', page.url());
await page.screenshot({ path: `${out}/flow-approval.png`, fullPage: true });

// 3. Price change invalidates the approval
const reprice = page.getByRole('button', { name: /Change price \+12%/i });
if (await reprice.count()) {
    await reprice.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    await page.waitForTimeout(600);
    console.log('after reprice:', page.url());
    await page.screenshot({
        path: `${out}/flow-approval-repriced.png`,
        fullPage: true,
    });
}

// 4. Audit trail now lists the record
await page.goto(`${base}/audit`, { waitUntil: 'networkidle' });
await page.screenshot({ path: `${out}/flow-audit.png`, fullPage: true });
const auditLink = page.getByRole('link', { name: 'Open', exact: true }).first();
if (await auditLink.count()) {
    await Promise.all([
        page.waitForURL(/\/audit\/[0-9a-f-]{36}/, { timeout: 30000 }),
        auditLink.click(),
    ]);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    await page.screenshot({
        path: `${out}/flow-audit-record.png`,
        fullPage: true,
    });
    console.log('audit record:', page.url());
}

// 5. Refusal path in a fresh context (reset prices first so the demo is truthful)
const refusalContext = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
});
const refusal = await refusalContext.newPage();
refusal.on('pageerror', (e) =>
    problems.push(`refusal pageerror: ${e.message}`),
);
await refusal.goto(`${base}/catalog`, { waitUntil: 'networkidle' });
const reset = refusal.getByRole('button', { name: /Reset demo catalog/i });
if (await reset.count()) {
    await reset.click();
    await refusal.waitForLoadState('networkidle');
    await refusal.waitForTimeout(800);
}
await refusal.goto(`${base}/assistant`, { waitUntil: 'networkidle' });
await refusal.getByRole('button', { name: /Impossible budget/i }).click();
await refusal.getByRole('button', { name: 'Evaluate request' }).click();
await refusal.waitForURL(/\/sessions\//, { timeout: 90000 });
await refusal.waitForLoadState('networkidle');
await refusal.waitForTimeout(800);
console.log('refusal:', refusal.url());
await refusal.screenshot({ path: `${out}/flow-refusal.png`, fullPage: true });

const mobile = await browser.newContext({
    viewport: { width: 390, height: 844 },
});
const mobilePage = await mobile.newPage();
await mobilePage.goto(`${base}/catalog`, { waitUntil: 'networkidle' });
await mobilePage.screenshot({
    path: `${out}/catalog-mobile.png`,
    fullPage: true,
});
await mobilePage.goto(`${base}/audit`, { waitUntil: 'networkidle' });
await mobilePage.screenshot({
    path: `${out}/audit-mobile.png`,
    fullPage: true,
});

await browser.close();

if (problems.length) {
    console.log('PROBLEMS');
    problems.forEach((p) => console.log(p));
    process.exitCode = 1;
}
