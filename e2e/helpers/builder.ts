import { expect, type Frame, type FrameLocator, type Locator, type Page } from '@playwright/test';

export const BUILDER_URL = '/wp-admin/admin.php?page=blocky-builder&post_id=9';

type BuilderInspectorTabKey = 'content' | 'layout' | 'style' | 'animations' | 'advanced' | 'classes';

export type BuilderInspectorCheck = {
  type: string;
} & Partial<Record<BuilderInspectorTabKey, string[]>>;

type LoginToBuilderOptions = {
  attempts?: number;
  expectInspector?: boolean;
  waitUntil?: 'load' | 'domcontentloaded' | 'networkidle' | 'commit';
};

const PREVIEW_IFRAME_SELECTOR = 'iframe[title="Page Preview"]';
const INSPECTOR_TAB_LABELS: Record<BuilderInspectorTabKey, string> = {
  content: 'Content',
  layout: 'Layout',
  style: 'Style',
  animations: 'Animations',
  advanced: 'Advanced',
  classes: 'Classes',
};
const INSPECTOR_TAB_ORDER: BuilderInspectorTabKey[] = ['content', 'layout', 'style', 'animations', 'advanced', 'classes'];

export function builderPreviewFrame(page: Page): FrameLocator {
  return page.frameLocator(PREVIEW_IFRAME_SELECTOR);
}

export function inspectorTab(page: Page, name: string): Locator {
  return page.getByRole('button', { name, exact: true }).last();
}

export async function loginToBuilder(page: Page, options: LoginToBuilderOptions = {}): Promise<void> {
  const attempts = options.attempts ?? 2;
  const expectInspector = options.expectInspector ?? false;
  const waitUntil = options.waitUntil ?? 'networkidle';

  for (let attempt = 0; attempt < attempts; attempt += 1) {
    await page.goto(BUILDER_URL, { waitUntil });

    if (page.url().includes('wp-login.php')) {
      await page.locator('#user_login').fill('admin');
      await page.locator('#user_pass').fill('admin123');
      await page.locator('#wp-submit').click();
      await page.waitForURL(/blocky-builder/);
    }

    if (await page.locator('.blocky-builder-root').count()) {
      await waitForBuilderReady(page, expectInspector);
      return;
    }
  }

  await waitForBuilderReady(page, expectInspector);
}

export async function ensureCanvasBlock(page: Page, blockType: string, frame: FrameLocator = builderPreviewFrame(page)): Promise<void> {
  const existingCount = await frame.locator(`[data-bky-type="${blockType}"]`).count();
  if (existingCount > 0) {
    return;
  }

  await page.evaluate((type: string) => {
    (window as Window & { BlockyBuilderDrag?: { blockType: string } }).BlockyBuilderDrag = { blockType: type };
  }, blockType);

  const previewFrame = await previewFrameHandle(page, 'transient block insertion');
  const dataTransfer = await previewFrame.evaluateHandle(() => new DataTransfer());
  await previewFrame.locator('body').dispatchEvent('drop', { dataTransfer });
  await expect(frame.locator(`[data-bky-type="${blockType}"]`).first()).toBeVisible();
}

export async function selectCanvasBlock(page: Page, blockType: string, frame: FrameLocator = builderPreviewFrame(page)): Promise<void> {
  const block = frame.locator(`[data-bky-type="${blockType}"]`).first();
  await expect(block, `Missing block type ${blockType} in the preview canvas`).toBeVisible();

  const blockId = await block.getAttribute('data-bky-id');
  if (!blockId) {
    throw new Error(`Missing data-bky-id for block type ${blockType}`);
  }

  const previewFrame = await previewFrameHandle(page, 'block selection');
  await previewFrame.evaluate((id: string) => {
    window.parent.postMessage({ type: 'bky:select', id }, '*');
  }, blockId);
}

export async function assertBuilderInspectorChecks(
  page: Page,
  checks: BuilderInspectorCheck[],
  frame: FrameLocator = builderPreviewFrame(page),
): Promise<void> {
  for (const check of checks) {
    await ensureCanvasBlock(page, check.type, frame);
    await selectCanvasBlock(page, check.type, frame);

    for (const tabKey of INSPECTOR_TAB_ORDER) {
      const controls = check[tabKey];
      if (!controls?.length) {
        continue;
      }

      await inspectorTab(page, INSPECTOR_TAB_LABELS[tabKey]).click();
      for (const control of controls) {
        await expectVisibleExactText(page, control);
      }
    }
  }
}

async function waitForBuilderReady(page: Page, expectInspector: boolean): Promise<void> {
  await expect(page.locator('.blocky-builder-root')).toBeVisible();
  if (expectInspector) {
    await expect(page.getByText('INSPECTOR')).toBeVisible();
  }
  await expect(builderPreviewFrame(page).locator('body')).toBeVisible();
}

async function previewFrameHandle(page: Page, purpose: string): Promise<Frame> {
  const previewFrame = await page.locator(PREVIEW_IFRAME_SELECTOR).elementHandle().then(handle => handle?.contentFrame() ?? null);
  if (!previewFrame) {
    throw new Error(`Preview iframe is not available for ${purpose}`);
  }

  return previewFrame;
}

async function expectVisibleExactText(page: Page, text: string): Promise<void> {
  const matches = page.getByText(text, { exact: true });
  const count = await matches.count();

  if (count === 0) {
    await expect(matches).toBeVisible();
    return;
  }

  for (let index = count - 1; index >= 0; index -= 1) {
    const candidate = matches.nth(index);
    if (await candidate.isVisible()) {
      await expect(candidate).toBeVisible();
      return;
    }
  }

  await expect(matches.last()).toBeVisible();
}