import type { FunctionComponent, JSX } from 'preact';
import { useEffect, useMemo, useState } from 'preact/hooks';
import { useDocumentStore } from '../store/document';
import type { MoveDirection } from '../store/document';
import { useUiStore } from '../store/ui';
import { useBlockRegistry } from '../store/blockRegistry';
import { MegaMenuItemsControl } from './MegaMenuItemsControl';
import { ListItemsControl } from './ListItemsControl';
import { overlayIdForNode } from '../overlays/identity';
import type { BlockDefinition, BuilderDocument, BuilderNode } from '../sdk/types';
import {
  tailwindColorRoles,
  tailwindUtilityGroups,
  tailwindVariantOptions,
} from '../generated/tailwindUtilityCatalog';
import { t } from '../i18n';

export const Inspector: FunctionComponent = () => {
  const selectedId = useUiStore((s) => s.selectedNodeId);
  const responsiveBreakpoint = useUiStore((s) => s.responsiveBreakpoint);
  const setResponsiveBreakpoint = useUiStore((s) => s.setResponsiveBreakpoint);
  const document = useDocumentStore((s) => s.document);
  const updateProps = useDocumentStore((s) => s.updateProps);
  const updateVariant = useDocumentStore((s) => s.updateVariant);
  const removeBlock = useDocumentStore((s) => s.removeBlock);
  const moveBlock = useDocumentStore((s) => s.moveBlock);
  const definitions = useBlockRegistry((s) => s.definitions);
  const [activeTab, setActiveTab] = useState('content');

  const node = selectedId ? document?.nodes[selectedId] : null;
  const def = node ? definitions.find((d) => d.type === node.type) : null;
  const isRoot = !!node && node.id === document?.root;
  const tabs = useMemo(() => {
    if (!def) return [];

    return ensureFixedInspectorTabs([
      ...tabsForDefinition(def),
      { id: 'classes', label: t('inspector.classesTab', 'Classes'), controls: [] },
    ]);
  }, [def]);
  const visibleTab = tabs.some((tab) => tab.id === activeTab) ? activeTab : tabs[0]?.id;
  const activeControls = tabs.find((tab) => tab.id === visibleTab)?.controls ?? [];

  useEffect(() => {
    setActiveTab('content');
  }, [selectedId, def?.type]);

  return (
    <aside
      class="flex flex-col border-l border-border-subtle bg-surface-elevated"
      style={{ width: 'var(--builder-inspector-width)' }}
    >
      <div class="flex items-center justify-between border-b border-border-subtle px-4 py-3">
        <h2 class="text-sm font-semibold text-text-muted uppercase tracking-wide">
          {node
            ? t('inspector.settingsTitle', '%s Settings', [friendlyName(node.type)])
            : t('inspector.title', 'Inspector')}
        </h2>
        {node && !isRoot && (
          <button
            type="button"
            onClick={() => removeBlock(node.id)}
            class="text-xs text-feedback-danger hover:underline"
          >
            {t('inspector.remove', 'Remove')}
          </button>
        )}
        {isRoot && (
          <span class="text-xs font-medium text-text-faint">
            {t('inspector.rootSection', 'Root section')}
          </span>
        )}
      </div>

      <div class="flex-1 overflow-y-auto p-4">
        {!node && (
          <p class="text-sm text-text-faint">
            {t('inspector.selectBlock', 'Select a block on the canvas to edit its properties.')}
          </p>
        )}

        {node && def && (
          <div class="space-y-4">
            {tabs.length > 1 && (
              <div class="grid grid-cols-2 gap-1 rounded-input border border-border-subtle bg-surface-base p-1">
                {tabs.map((tab) => (
                  <button
                    key={tab.id}
                    type="button"
                    onClick={() => setActiveTab(tab.id)}
                    class={`rounded-input px-2 py-1.5 text-xs font-medium transition-colors ${
                      tab.id === visibleTab
                        ? 'bg-accent-base text-text-on-accent'
                        : 'text-text-muted hover:bg-surface-overlay hover:text-text-base'
                    }`}
                  >
                    {tab.label}
                  </button>
                ))}
              </div>
            )}

            {node && ['style', 'animations', 'classes'].includes(visibleTab ?? '') && (
              <ResponsiveBreakpointBar
                value={responsiveBreakpoint}
                onChange={setResponsiveBreakpoint}
              />
            )}

            <div class="space-y-4">
              {activeControls.map((control) =>
                isTemplatePartPageControl(node, control) ? (
                  <TemplatePartControl
                    key={`${visibleTab}-${control.id}`}
                    label={control.label}
                    value={valueForControl(node, def, control)}
                    onChange={(v) => updateProps(node.id, { [control.id]: v })}
                  />
                ) : isStyleColorControl(control) ? (
                  <StyleColorControl
                    key={`${visibleTab}-${control.id}`}
                    control={control}
                    node={node}
                    value={valueForControl(node, def, control)}
                    onVariantChange={(v) =>
                      updateVariant(node.id, control.variantKey ?? control.id, v)
                    }
                    onUpdate={(props) => updateProps(node.id, props)}
                  />
                ) : (
                  <ControlField
                    key={`${visibleTab}-${control.id}`}
                    control={control}
                    document={document}
                    node={node}
                    value={valueForControl(node, def, control)}
                    onChange={(v) => {
                      if (control.type === 'variant') {
                        updateVariant(node.id, control.variantKey ?? control.id, String(v));
                      } else {
                        updateProps(node.id, { [control.id]: v });
                      }
                    }}
                    onUpdate={(props) => updateProps(node.id, props)}
                  />
                )
              )}
              {visibleTab === 'layout' && (
                <>
                  {document && (
                    <GridItemPanel
                      document={document}
                      node={node}
                      onMove={(direction) => moveBlock(node.id, direction)}
                    />
                  )}
                  <BoxModelPanel node={node} onUpdate={(props) => updateProps(node.id, props)} />
                  {shouldShowFlexLayoutPanel(node, classesForNode(node)) && (
                    <FlexLayoutPanel
                      node={node}
                      onUpdate={(props) => updateProps(node.id, props)}
                    />
                  )}
                </>
              )}
              {visibleTab === 'animations' && (
                <AnimationsPanel node={node} onUpdate={(props) => updateProps(node.id, props)} />
              )}
              {visibleTab === 'classes' && (
                <TailwindClassPanel node={node} onUpdate={(props) => updateProps(node.id, props)} />
              )}
              {visibleTab === 'advanced' && (
                <InteractionsPanel
                  document={document}
                  node={node}
                  onUpdate={(props) => updateProps(node.id, props)}
                />
              )}
            </div>
          </div>
        )}
      </div>
    </aside>
  );
};

interface BlockControl {
  id: string;
  /** Descriptor-driven line-list editor fields (pipe-protocol string props). */
  listFields?: ListFieldDescriptor[];
  type:
    | 'text'
    | 'richtext'
    | 'select'
    | 'toggle'
    | 'variant'
    | 'number'
    | 'range'
    | 'color'
    | 'media';
  label: string;
  variantKey?: string;
  options?: Array<[string | number, string]>;
  tab?: string;
  min?: number;
  max?: number;
  step?: number;
  numericEnum?: boolean;
  mediaType?: string; // 'image' | 'video' | '' (any)
  mediaReturn?: string; // 'id' (default) | 'url'
}

interface InspectorTab {
  id: string;
  label: string;
  controls: BlockControl[];
}

interface ControlFieldProps {
  control: BlockControl;
  document?: BuilderDocument | null;
  node: BuilderNode;
  value: unknown;
  onChange: (v: unknown) => void;
  onUpdate: (props: Record<string, unknown>) => void;
}

interface StyleColorControlProps {
  control: BlockControl;
  node: BuilderNode;
  value: unknown;
  onVariantChange: (value: string) => void;
  onUpdate: (props: Record<string, unknown>) => void;
}

function SegmentedControl(props: {
  label: string;
  value: string;
  options: Array<[string | number, string]>;
  numericEnum?: boolean;
  onChange: (v: unknown) => void;
}) {
  return (
    <div class="space-y-1">
      <span class="text-xs font-medium text-text-muted">{props.label}</span>
      <div
        class="flex overflow-hidden rounded-input border border-border-base"
        role="group"
        aria-label={props.label}
      >
        {props.options.map(([optionValue, optionLabel]) => {
          const active = props.value === String(optionValue);
          return (
            <button
              key={String(optionValue)}
              type="button"
              aria-pressed={active}
              onClick={() =>
                props.onChange(props.numericEnum ? Number(optionValue) : String(optionValue))
              }
              class={
                'flex-1 px-2 py-1.5 text-xs font-medium transition-colors ' +
                (active
                  ? 'bg-accent-base text-white'
                  : 'bg-surface-base text-text-muted hover:text-text-base')
              }
            >
              {optionLabel}
            </button>
          );
        })}
      </div>
    </div>
  );
}

interface StyleColorRoleConfig {
  id: 'background' | 'text';
  utility: 'bg' | 'text';
  variableName: string;
  gradientVariableName?: string | undefined;
  imageUrlVariableName?: string | undefined;
  imagePositionXVariableName?: string | undefined;
  imagePositionYVariableName?: string | undefined;
}

interface StyleGradientDraft {
  from: string;
  to: string;
  angle: string;
}

type InteractionEvent = 'click' | 'hover' | 'focus' | 'load';
type InteractionAction =
  | 'overlay.open'
  | 'overlay.close'
  | 'overlay.toggle'
  | 'class.add'
  | 'class.remove'
  | 'class.toggle'
  | 'custom.emit';
type InteractionDevice = 'any' | 'desktop' | 'tablet' | 'mobile';
type InteractionLoginState = 'any' | 'logged-in' | 'logged-out';

interface InteractionRule {
  event: InteractionEvent;
  action: InteractionAction;
  target: string;
  className?: string | undefined;
  delay?: number | undefined;
  debounce?: number | undefined;
  throttle?: number | undefined;
  once?: boolean | undefined;
  preventDefault?: boolean | undefined;
  stopPropagation?: boolean | undefined;
  device?: InteractionDevice | undefined;
  loginState?: InteractionLoginState | undefined;
  queryKey?: string | undefined;
  queryValue?: string | undefined;
  cookieKey?: string | undefined;
  cookieValue?: string | undefined;
}

type LayoutIconKind = 'direction' | 'wrap' | 'justify' | 'align' | 'content';

interface VisualOption {
  value: string;
  label: string;
  icon: LayoutIconKind;
}

const VISUAL_CONTROL_ICON_MAP: Record<string, Partial<Record<string, LayoutIconKind>>> = {
  align: {
    start: 'justify',
    center: 'justify',
    end: 'justify',
    stretch: 'justify',
  },
  horizontalAlign: {
    start: 'justify',
    center: 'justify',
    end: 'justify',
    stretch: 'justify',
  },
  verticalAlign: {
    start: 'align',
    center: 'align',
    end: 'align',
    stretch: 'align',
  },
  alignItems: {
    start: 'align',
    center: 'align',
    end: 'align',
    stretch: 'align',
    baseline: 'align',
  },
  justifyItems: {
    start: 'justify',
    center: 'justify',
    end: 'justify',
    stretch: 'justify',
  },
};

const CUSTOM_COLOR_VALUE = '__customColor';
const GRADIENT_COLOR_VALUE = '__gradientColor';
const IMAGE_BACKGROUND_VALUE = '__imageBackground';
const CUSTOM_COLOR_DEFAULT = '#0ea5e9';
const GRADIENT_COLOR_TO_DEFAULT = '#9333ea';
const DEFAULT_STYLE_GRADIENT: StyleGradientDraft = {
  from: CUSTOM_COLOR_DEFAULT,
  to: GRADIENT_COLOR_TO_DEFAULT,
  angle: '135',
};
const GRADIENT_ANGLE_OPTIONS: Array<[string, string]> = [
  ['0', '0°'],
  ['45', '45°'],
  ['90', '90°'],
  ['135', '135°'],
  ['180', '180°'],
  ['225', '225°'],
  ['270', '270°'],
  ['315', '315°'],
];
const STYLE_COLOR_ROLES: Record<StyleColorRoleConfig['id'], StyleColorRoleConfig> = {
  background: {
    id: 'background',
    utility: 'bg',
    variableName: '--bky-tw-base-background',
    gradientVariableName: '--bky-tw-base-background-gradient',
    imageUrlVariableName: '--bky-tw-base-background-image-url',
    imagePositionXVariableName: '--bky-tw-base-background-position-x',
    imagePositionYVariableName: '--bky-tw-base-background-position-y',
  },
  text: { id: 'text', utility: 'text', variableName: '--bky-tw-base-text' },
};
const STYLE_COLOR_CONTROL_ROLES: Record<string, StyleColorRoleConfig['id']> = {
  background: 'background',
  color: 'text',
  textColor: 'text',
  tone: 'text',
};

const StyleColorControl: FunctionComponent<StyleColorControlProps> = ({
  control,
  node,
  value,
  onVariantChange,
  onUpdate,
}) => {
  const responsiveBreakpoint = useUiStore((s) => s.responsiveBreakpoint);
  const role = styleColorRoleForControl(control);
  const scopedRole = role ? responsiveStyleRole(role, responsiveBreakpoint) : null;
  const colorVars = useMemo(() => colorVarsForNode(node), [node]);
  const styleVars = useMemo(() => styleVarsForNode(node), [node]);
  const activeClasses = useMemo(() => classesForNode(node), [node]);
  const currentCustomColor = scopedRole
    ? (normalizeHexColor(colorVars[scopedRole.variableName] ?? '') ?? CUSTOM_COLOR_DEFAULT)
    : CUSTOM_COLOR_DEFAULT;
  const currentGradient = scopedRole?.gradientVariableName
    ? (parseGradientValue(styleVars[scopedRole.gradientVariableName] ?? '') ??
      DEFAULT_STYLE_GRADIENT)
    : DEFAULT_STYLE_GRADIENT;
  const currentImageUrl = scopedRole?.imageUrlVariableName
    ? parseBackgroundImageUrl(styleVars[scopedRole.imageUrlVariableName] ?? '')
    : '';
  const currentImageFocalX = scopedRole?.imagePositionXVariableName
    ? parseBackgroundPositionValue(styleVars[scopedRole.imagePositionXVariableName] ?? '')
    : 50;
  const currentImageFocalY = scopedRole?.imagePositionYVariableName
    ? parseBackgroundPositionValue(styleVars[scopedRole.imagePositionYVariableName] ?? '')
    : 50;
  const hasCustomClass = scopedRole
    ? activeClasses.some((className) => isCustomColorClass(className, scopedRole))
    : false;
  const hasGradientClass = scopedRole
    ? activeClasses.some((className) => isCustomGradientClass(className, scopedRole))
    : false;
  const hasImageBackground = !!(scopedRole?.imageUrlVariableName && currentImageUrl);
  const isGradient = String(value) === GRADIENT_COLOR_VALUE || hasGradientClass;
  const isImage = String(value) === IMAGE_BACKGROUND_VALUE || hasImageBackground;
  const isCustom = !isGradient && (String(value) === CUSTOM_COLOR_VALUE || hasCustomClass);
  const [draftColor, setDraftColor] = useState(currentCustomColor);
  const [draftGradient, setDraftGradient] = useState<StyleGradientDraft>(currentGradient);
  const [draftImageUrl, setDraftImageUrl] = useState(currentImageUrl);
  const selectedValue =
    role?.id === 'background'
      ? isImage
        ? IMAGE_BACKGROUND_VALUE
        : isGradient
          ? GRADIENT_COLOR_VALUE
          : isCustom
            ? CUSTOM_COLOR_VALUE
            : 'transparent'
      : isGradient
        ? GRADIENT_COLOR_VALUE
        : isCustom
          ? CUSTOM_COLOR_VALUE
          : String(value ?? '');

  useEffect(() => {
    setDraftColor(currentCustomColor);
  }, [currentCustomColor, node.id, control.id]);

  useEffect(() => {
    setDraftGradient(currentGradient);
  }, [currentGradient, node.id, control.id]);

  useEffect(() => {
    setDraftImageUrl(currentImageUrl);
  }, [currentImageUrl, node.id, control.id]);

  if (!role || !scopedRole) {
    return (
      <ControlField
        control={control}
        document={null}
        node={node}
        value={value}
        onChange={(v) => onVariantChange(String(v))}
        onUpdate={onUpdate}
      />
    );
  }

  const applyCustomColor = (nextColor: string): void => {
    const normalizedColor = normalizeHexColor(nextColor);
    if (!normalizedColor) return;
    onVariantChange(CUSTOM_COLOR_VALUE);
    onUpdate(customColorPropsForNode(node, scopedRole, normalizedColor, responsiveBreakpoint));
  };

  const applyGradient = (nextGradient: StyleGradientDraft): void => {
    const normalizedGradient = gradientValueFromDraft(nextGradient);
    if (!normalizedGradient) return;
    onVariantChange(GRADIENT_COLOR_VALUE);
    onUpdate(
      customGradientPropsForNode(node, scopedRole, normalizedGradient, responsiveBreakpoint)
    );
  };

  const applyBackgroundImage = (nextUrl: string): void => {
    if (!scopedRole.imageUrlVariableName) return;
    const normalizedUrl = normalizeBackgroundImageUrl(nextUrl);
    if (!normalizedUrl) return;
    onVariantChange(IMAGE_BACKGROUND_VALUE);
    onUpdate(
      customBackgroundImagePropsForNode(
        node,
        scopedRole,
        normalizedUrl,
        responsiveBreakpoint,
        currentImageFocalX,
        currentImageFocalY
      )
    );
  };

  const applyBackgroundFocalPoint = (next: { x: number; y: number }): void => {
    if (!scopedRole.imageUrlVariableName) return;
    const normalizedUrl = normalizeBackgroundImageUrl(draftImageUrl);
    if (!normalizedUrl) return;
    onVariantChange(IMAGE_BACKGROUND_VALUE);
    onUpdate(
      customBackgroundImagePropsForNode(
        node,
        scopedRole,
        normalizedUrl,
        responsiveBreakpoint,
        next.x,
        next.y
      )
    );
  };

  const selectableOptions =
    scopedRole.id === 'background'
      ? ([
          ['transparent', t('inspector.backgroundTransparent', 'Transparent')],
          [CUSTOM_COLOR_VALUE, t('inspector.backgroundCustom', 'Custom')],
          [GRADIENT_COLOR_VALUE, t('inspector.backgroundGradient', 'Gradient')],
          [IMAGE_BACKGROUND_VALUE, t('inspector.backgroundImage', 'Image')],
        ] satisfies Array<[string, string]>)
      : (control.options ?? []);

  const handleSelect = (nextValue: string): void => {
    if (nextValue === CUSTOM_COLOR_VALUE) {
      applyCustomColor(draftColor);
      return;
    }
    if (nextValue === GRADIENT_COLOR_VALUE) {
      applyGradient(draftGradient);
      return;
    }
    if (nextValue === IMAGE_BACKGROUND_VALUE) {
      if (!normalizeBackgroundImageUrl(draftImageUrl)) {
        onVariantChange(IMAGE_BACKGROUND_VALUE);
        onUpdate(clearStyleColorPropsForNode(node, scopedRole));
        return;
      }
      applyBackgroundImage(draftImageUrl);
      return;
    }
    onVariantChange(nextValue);
    onUpdate(clearStyleColorPropsForNode(node, scopedRole));
  };

  const handleColorInput = (nextColor: string): void => {
    setDraftColor(nextColor);
    applyCustomColor(nextColor);
  };

  const updateGradientDraft = (patch: Partial<StyleGradientDraft>): void => {
    const nextGradient = { ...draftGradient, ...patch };
    setDraftGradient(nextGradient);
    applyGradient(nextGradient);
  };

  return (
    <div class="block space-y-2">
      <label class="block space-y-1">
        <span class="text-xs font-medium text-text-muted">{control.label}</span>
        <select
          class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                 text-text-base focus:border-accent-base focus:outline-none"
          value={selectedValue}
          onChange={(e) => handleSelect((e.target as HTMLSelectElement).value)}
        >
          {selectableOptions.map(([val, label]) => (
            <option key={String(val)} value={String(val)}>
              {label}
            </option>
          ))}
        </select>
      </label>

      {isCustom && (
        <div class="flex gap-2">
          <input
            type="color"
            value={colorPickerValue(draftColor)}
            onInput={(e) => handleColorInput((e.target as HTMLInputElement).value)}
            class="h-10 w-12 rounded-input border border-border-base bg-surface-base"
            aria-label={`${control.label} custom color picker`}
          />
          <input
            type="text"
            value={draftColor}
            onInput={(e) => handleColorInput((e.target as HTMLInputElement).value)}
            class="min-w-0 flex-1 rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                   text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
            placeholder={CUSTOM_COLOR_DEFAULT}
          />
        </div>
      )}

      {isGradient && role.gradientVariableName && (
        <div class="space-y-3 rounded-input border border-border-subtle bg-surface-base p-3">
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <label class="block space-y-1">
              <span class="text-xs font-medium text-text-muted">From</span>
              <div class="flex gap-2">
                <input
                  type="color"
                  value={colorPickerValue(draftGradient.from)}
                  onInput={(e) =>
                    updateGradientDraft({ from: (e.target as HTMLInputElement).value })
                  }
                  class="h-10 w-12 rounded-input border border-border-base bg-surface-base"
                  aria-label={`${control.label} gradient start color picker`}
                />
                <input
                  type="text"
                  value={draftGradient.from}
                  onInput={(e) =>
                    updateGradientDraft({ from: (e.target as HTMLInputElement).value })
                  }
                  class="min-w-0 flex-1 rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                         text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
                  placeholder={CUSTOM_COLOR_DEFAULT}
                />
              </div>
            </label>

            <label class="block space-y-1">
              <span class="text-xs font-medium text-text-muted">To</span>
              <div class="flex gap-2">
                <input
                  type="color"
                  value={colorPickerValue(draftGradient.to)}
                  onInput={(e) => updateGradientDraft({ to: (e.target as HTMLInputElement).value })}
                  class="h-10 w-12 rounded-input border border-border-base bg-surface-base"
                  aria-label={`${control.label} gradient end color picker`}
                />
                <input
                  type="text"
                  value={draftGradient.to}
                  onInput={(e) => updateGradientDraft({ to: (e.target as HTMLInputElement).value })}
                  class="min-w-0 flex-1 rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                         text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
                  placeholder={GRADIENT_COLOR_TO_DEFAULT}
                />
              </div>
            </label>
          </div>

          <label class="block space-y-1">
            <span class="text-xs font-medium text-text-muted">Angle</span>
            <select
              class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                     text-text-base focus:border-accent-base focus:outline-none"
              value={draftGradient.angle}
              onChange={(e) =>
                updateGradientDraft({ angle: (e.target as HTMLSelectElement).value })
              }
            >
              {GRADIENT_ANGLE_OPTIONS.map(([angle, label]) => (
                <option key={angle} value={angle}>
                  {label}
                </option>
              ))}
            </select>
          </label>
        </div>
      )}

      {isImage && role.imageUrlVariableName && (
        <div class="space-y-3 rounded-input border border-border-subtle bg-surface-base p-3">
          <MediaControl
            control={{
              id: `${control.id}Image`,
              type: 'media',
              label: t('inspector.backgroundImage', 'Image'),
              mediaType: 'image',
              mediaReturn: 'url',
            }}
            value={draftImageUrl}
            onChange={(nextValue) => {
              const nextUrl = String(nextValue ?? '');
              setDraftImageUrl(nextUrl);
              applyBackgroundImage(nextUrl);
            }}
          />
          <FocalPointControl
            label={t('inspector.focalPoint', 'Focal Point')}
            x={currentImageFocalX}
            y={currentImageFocalY}
            onChange={applyBackgroundFocalPoint}
          />
        </div>
      )}
    </div>
  );
};

const ControlField: FunctionComponent<ControlFieldProps> = ({
  control,
  document,
  node,
  value,
  onChange,
  onUpdate,
}) => {
  const strVal = String(value ?? '');
  const visualOptions = visualOptionsForControl(control);

  if (node.type === 'bky/mega-menu' && control.id === 'items') {
    return (
      <MegaMenuItemsControl
        label={control.label}
        value={value}
        onChange={(items) => onChange(items)}
      />
    );
  }

  if (control.listFields && control.listFields.length > 0) {
    return (
      <ListItemsControl
        label={control.label}
        fields={control.listFields}
        value={value}
        onChange={onChange}
      />
    );
  }

  if (control.id === 'targetOverlayId') {
    const overlayIds = overlayIdsForInspector(document ?? null, node.id);
    if (overlayIds.length > 0) {
      return (
        <label class="block space-y-1">
          <span class="text-xs font-medium text-text-muted">{control.label}</span>
          <select
            class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                   text-text-base focus:border-accent-base focus:outline-none"
            value={strVal}
            onChange={(e) => onChange((e.target as HTMLSelectElement).value)}
          >
            <option value="">{t('inspector.selectOverlayTarget', 'Select overlay…')}</option>
            {overlayIds.map((overlayId) => (
              <option key={overlayId} value={overlayId}>
                {overlayId}
              </option>
            ))}
          </select>
        </label>
      );
    }
  }

  if (control.type === 'text' || control.type === 'richtext') {
    return (
      <label class="block space-y-1">
        <span class="text-xs font-medium text-text-muted">{control.label}</span>
        <textarea
          class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                 text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
          rows={3}
          value={strVal}
          onInput={(e) => onChange((e.target as HTMLTextAreaElement).value)}
        />
      </label>
    );
  }

  if (control.type === 'color') {
    const colorValue = strVal.startsWith('#') ? strVal : '#ffffff';
    return (
      <label class="block space-y-1">
        <span class="text-xs font-medium text-text-muted">{control.label}</span>
        <div class="flex gap-2">
          <input
            type="color"
            value={colorValue}
            onInput={(e) => onChange((e.target as HTMLInputElement).value)}
            class="h-10 w-12 rounded-input border border-border-base bg-surface-base"
          />
          <input
            type="text"
            value={strVal}
            onInput={(e) => onChange((e.target as HTMLInputElement).value)}
            class="min-w-0 flex-1 rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                   text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
          />
        </div>
      </label>
    );
  }

  if (control.type === 'select' || control.type === 'variant') {
    if (isLinkTargetControl(control)) {
      return (
        <label class="flex items-center justify-between">
          <span class="text-xs font-medium text-text-muted">{control.label}</span>
          <input
            type="checkbox"
            checked={strVal === '_blank'}
            onChange={(e) => onChange((e.target as HTMLInputElement).checked ? '_blank' : '_self')}
            class="h-4 w-4 rounded accent-accent-base"
          />
        </label>
      );
    }

    if (visualOptions) {
      return (
        <VisualOptionControl
          label={control.label}
          value={strVal}
          options={visualOptions}
          onChange={onChange}
        />
      );
    }

    const options = control.options ?? [];
    if (options.length >= 2 && options.length <= 5) {
      return (
        <SegmentedControl
          label={control.label}
          value={strVal}
          options={options}
          numericEnum={control.numericEnum === true}
          onChange={onChange}
        />
      );
    }
    return (
      <label class="block space-y-1">
        <span class="text-xs font-medium text-text-muted">{control.label}</span>
        <select
          class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                 text-text-base focus:border-accent-base focus:outline-none"
          value={strVal}
          onChange={(e) =>
            onChange(
              control.numericEnum
                ? Number((e.target as HTMLSelectElement).value)
                : (e.target as HTMLSelectElement).value
            )
          }
        >
          {options.map(([val, label]) => (
            <option key={String(val)} value={String(val)}>
              {label}
            </option>
          ))}
        </select>
      </label>
    );
  }

  if (control.type === 'toggle') {
    return (
      <label class="flex items-center justify-between">
        <span class="text-xs font-medium text-text-muted">{control.label}</span>
        <input
          type="checkbox"
          checked={value === true || strVal === 'true' || strVal === '1'}
          onChange={(e) => onChange((e.target as HTMLInputElement).checked)}
          class="h-4 w-4 rounded accent-accent-base"
        />
      </label>
    );
  }

  if (control.type === 'number' || control.type === 'range') {
    const clamp = (next: number): number => {
      if (control.min !== undefined) next = Math.max(control.min, next);
      if (control.max !== undefined) next = Math.min(control.max, next);
      return next;
    };
    const bounded = control.min !== undefined && control.max !== undefined;
    const asSlider =
      control.type === 'range' ||
      (bounded && control.max! - control.min! <= 30 && (control.step ?? 1) >= 1);
    const numVal = Number.isFinite(Number(strVal)) ? Number(strVal) : (control.min ?? 0);

    if (asSlider) {
      return (
        <label class="block space-y-1">
          <span class="flex items-center justify-between text-xs font-medium text-text-muted">
            <span>{control.label}</span>
            <span class="tabular-nums text-text-base">{clamp(numVal)}</span>
          </span>
          <input
            type="range"
            min={control.min}
            max={control.max}
            step={control.step ?? 1}
            class="w-full accent-accent-base"
            value={clamp(numVal)}
            onInput={(e) => onChange(clamp(Number((e.target as HTMLInputElement).value)))}
          />
        </label>
      );
    }

    return (
      <label class="block space-y-1">
        <span class="flex items-center justify-between text-xs font-medium text-text-muted">
          <span>{control.label}</span>
          {(control.min !== undefined || control.max !== undefined) && (
            <span class="text-[10px] text-text-faint">
              {control.min ?? '-inf'}…{control.max ?? '+inf'}
            </span>
          )}
        </span>
        <input
          type="number"
          min={control.min}
          max={control.max}
          step={control.step ?? 1}
          class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                 text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
          value={strVal}
          onInput={(e) => {
            const raw = (e.target as HTMLInputElement).value;
            if (raw.trim() === '' || !Number.isFinite(Number(raw))) return;
            onChange(clamp(Number(raw)));
          }}
          onBlur={(e) => {
            const input = e.target as HTMLInputElement;
            if (input.value.trim() !== '' && Number.isFinite(Number(input.value))) {
              input.value = String(clamp(Number(input.value)));
            }
          }}
        />
      </label>
    );
  }

  if (control.type === 'media') {
    return (
      <MediaControl
        control={control}
        node={node}
        value={value}
        onChange={onChange}
        onUpdate={onUpdate}
      />
    );
  }

  return null;
};

interface VisualOptionControlProps {
  label: string;
  value: string;
  options: VisualOption[];
  onChange: (v: unknown) => void;
}

const VisualOptionControl: FunctionComponent<VisualOptionControlProps> = ({
  label,
  value,
  options,
  onChange,
}) => {
  const activeOption = options.find((option) => option.value === value) ?? options[0];

  return (
    <div class="block space-y-1.5">
      <span class="flex items-center justify-between gap-2 text-xs font-medium text-text-muted">
        <span>{label}</span>
        {activeOption && <span class="text-text-faint">{activeOption.label}</span>}
      </span>
      <div class="flex flex-wrap gap-1">
        {options.map((option) => {
          const isActive = option.value === value;
          return (
            <button
              key={option.value}
              type="button"
              onClick={() => onChange(option.value)}
              title={option.label}
              aria-label={`${label}: ${option.label}`}
              aria-pressed={isActive}
              class={`flex h-8 w-8 items-center justify-center rounded-input border transition-colors ${
                isActive
                  ? 'border-accent-base bg-accent-subtle text-accent-text'
                  : 'border-border-base bg-surface-base text-text-muted hover:border-accent-base hover:text-text-base'
              }`}
            >
              <LayoutMiniIcon kind={option.icon} value={option.value} />
            </button>
          );
        })}
      </div>
    </div>
  );
};

function visualOptionsForControl(control: BlockControl): VisualOption[] | null {
  if (control.type !== 'select' && control.type !== 'variant') return null;
  const optionIconMap =
    VISUAL_CONTROL_ICON_MAP[control.variantKey ?? control.id] ??
    VISUAL_CONTROL_ICON_MAP[control.id];
  if (!optionIconMap || !control.options?.length) return null;

  const options = control.options
    .map(([rawValue, rawLabel]) => {
      const value = String(rawValue);
      const icon = optionIconMap[value];
      if (!icon) return null;
      return { value, label: String(rawLabel), icon } satisfies VisualOption;
    })
    .filter((option): option is VisualOption => option !== null);

  return options.length >= 2 ? options : null;
}

function isTemplatePartPageControl(node: BuilderNode, control: BlockControl): boolean {
  return node.type === 'bky/wp-template-part' && control.id === 'postId';
}

interface LayoutMiniIconProps {
  kind: LayoutIconKind;
  value: string;
}

const LayoutMiniIcon: FunctionComponent<LayoutMiniIconProps> = ({ kind, value }) => {
  if (kind === 'align') return <AlignIcon value={value} />;
  if (kind === 'content') return <ContentIcon value={value} />;
  if (kind === 'wrap') return <WrapIcon value={value} />;
  if (kind === 'direction') return <DirectionIcon value={value} />;
  return <JustifyIcon value={value} />;
};

const JustifyIcon: FunctionComponent<{ value: string }> = ({ value }) => {
  const xPositions =
    value.endsWith('center') || value === 'center'
      ? [6, 10, 14]
      : value.endsWith('end') || value === 'end'
        ? [10, 14, 18]
        : value.endsWith('between') || value === 'between'
          ? [3, 10, 17]
          : value.endsWith('around') || value === 'around'
            ? [4, 10, 16]
            : value.endsWith('evenly') || value === 'evenly'
              ? [3.5, 10, 16.5]
              : [2, 6, 10];
  const stretch = value.endsWith('stretch') || value === 'stretch';
  return (
    <svg width="22" height="18" viewBox="0 0 22 18" fill="none" aria-hidden="true">
      <line x1="2" y1="3" x2="2" y2="15" stroke="currentColor" stroke-width="1.2" opacity="0.55" />
      <line
        x1="20"
        y1="3"
        x2="20"
        y2="15"
        stroke="currentColor"
        stroke-width="1.2"
        opacity="0.55"
      />
      {stretch ? (
        <rect x="4" y="7" width="14" height="4" rx="0.8" fill="currentColor" />
      ) : (
        xPositions.map((position) => (
          <rect
            key={position}
            x={position}
            y="6"
            width="3"
            height="6"
            rx="0.8"
            fill="currentColor"
          />
        ))
      )}
    </svg>
  );
};

const AlignIcon: FunctionComponent<{ value: string }> = ({ value }) => {
  const baseline = value.endsWith('baseline') || value === 'baseline';
  const stretch = value.endsWith('stretch') || value === 'stretch';
  const y =
    value.endsWith('center') || value === 'center'
      ? 6
      : value.endsWith('end') || value === 'end'
        ? 10
        : 2;
  return (
    <svg width="22" height="18" viewBox="0 0 22 18" fill="none" aria-hidden="true">
      <line x1="3" y1="2" x2="19" y2="2" stroke="currentColor" stroke-width="1.2" opacity="0.35" />
      <line
        x1="3"
        y1="16"
        x2="19"
        y2="16"
        stroke="currentColor"
        stroke-width="1.2"
        opacity="0.35"
      />
      {baseline && (
        <line
          x1="3"
          y1="13"
          x2="19"
          y2="13"
          stroke="currentColor"
          stroke-width="1.2"
          opacity="0.8"
        />
      )}
      {stretch
        ? [4, 9, 14].map((position) => (
            <rect
              key={position}
              x={position}
              y="3"
              width="3"
              height="12"
              rx="0.8"
              fill="currentColor"
            />
          ))
        : [4, 9, 14].map((position, index) => (
            <rect
              key={position}
              x={position}
              y={baseline ? 13 - (index + 2) * 2 : y}
              width="3"
              height={baseline ? (index + 2) * 2 : 6}
              rx="0.8"
              fill="currentColor"
            />
          ))}
    </svg>
  );
};

const ContentIcon: FunctionComponent<{ value: string }> = ({ value }) => {
  const yPositions =
    value.endsWith('center') || value === 'center'
      ? [5, 10]
      : value.endsWith('end') || value === 'end'
        ? [9, 13]
        : value.endsWith('between') || value === 'between'
          ? [3, 13]
          : value.endsWith('around') || value === 'around'
            ? [4, 12]
            : value.endsWith('evenly') || value === 'evenly'
              ? [4, 11]
              : [2, 6];
  const stretch = value.endsWith('stretch') || value === 'stretch';
  return (
    <svg width="22" height="18" viewBox="0 0 22 18" fill="none" aria-hidden="true">
      <line x1="3" y1="2" x2="19" y2="2" stroke="currentColor" stroke-width="1.2" opacity="0.35" />
      <line
        x1="3"
        y1="16"
        x2="19"
        y2="16"
        stroke="currentColor"
        stroke-width="1.2"
        opacity="0.35"
      />
      {stretch
        ? [4, 10].map((position) => (
            <rect
              key={position}
              x="5"
              y={position}
              width="12"
              height="3"
              rx="0.8"
              fill="currentColor"
            />
          ))
        : yPositions.map((position) => (
            <rect
              key={position}
              x="5"
              y={position}
              width="12"
              height="3"
              rx="0.8"
              fill="currentColor"
            />
          ))}
    </svg>
  );
};

const DirectionIcon: FunctionComponent<{ value: string }> = ({ value }) => {
  const isColumn = value.endsWith('col') || value.endsWith('col-reverse');
  const isReverse = value.endsWith('reverse');
  const positions = isColumn ? [3, 7, 11] : [3, 8, 13];
  return (
    <svg width="22" height="18" viewBox="0 0 22 18" fill="none" aria-hidden="true">
      {positions.map((position, index) => (
        <rect
          key={position}
          x={isColumn ? 8 : position}
          y={isColumn ? position : 6}
          width={isColumn ? 6 : 4}
          height={isColumn ? 3 : 6}
          rx="0.8"
          fill="currentColor"
          opacity={isReverse && index === 0 ? '0.55' : '1'}
        />
      ))}
      <path
        d={
          isColumn
            ? isReverse
              ? 'M16 5v8m0-8-2 2m2-2 2 2'
              : 'M16 5v8m0 0-2-2m2 2 2-2'
            : isReverse
              ? 'M17 13H5m0 0 2-2m-2 2 2 2'
              : 'M5 13h12m0 0-2-2m2 2-2 2'
        }
        stroke="currentColor"
        stroke-width="1.2"
        stroke-linecap="round"
        stroke-linejoin="round"
        opacity="0.75"
      />
    </svg>
  );
};

const WrapIcon: FunctionComponent<{ value: string }> = ({ value }) => {
  const isReverse = value.endsWith('reverse');
  const isNowrap = value.endsWith('nowrap') || value === 'nowrap';
  return (
    <svg width="22" height="18" viewBox="0 0 22 18" fill="none" aria-hidden="true">
      {isNowrap
        ? [3, 8, 13].map((position) => (
            <rect
              key={position}
              x={position}
              y="6"
              width="4"
              height="6"
              rx="0.8"
              fill="currentColor"
            />
          ))
        : [
            [3, isReverse ? 10 : 3],
            [8, isReverse ? 10 : 3],
            [3, isReverse ? 3 : 10],
            [8, isReverse ? 3 : 10],
          ].map(([x, y]) => (
            <rect key={`${x}-${y}`} x={x} y={y} width="4" height="4" rx="0.8" fill="currentColor" />
          ))}
      <path
        d={isNowrap ? 'M4 14h14' : 'M13 5h4v4h-4'}
        stroke="currentColor"
        stroke-width="1.2"
        stroke-linecap="round"
        stroke-linejoin="round"
        opacity="0.75"
      />
    </svg>
  );
};

// ── MediaControl ────────────────────────────────────────────────────────────

interface MediaControlProps {
  control: BlockControl;
  value: unknown;
  onChange: (v: unknown) => void;
  node?: BuilderNode;
  onUpdate?: (props: Record<string, unknown>) => void;
}

interface ImageControlBindings {
  alt: string;
  size: string;
  loading: string;
  decoding: string;
  fit: string;
  focalX: string;
  focalY: string;
}

interface MediaAttachmentPreview {
  sourceUrl: string | null;
  altText: string;
  sizeOptions: Array<[string, string]>;
}

interface TemplatePartControlProps {
  label: string;
  value: unknown;
  onChange: (v: unknown) => void;
}

interface FocalPointControlProps {
  label: string;
  x: number;
  y: number;
  onChange: (next: { x: number; y: number }) => void;
}

interface SelectFieldProps {
  label: string;
  value: string;
  options: Array<[string, string]>;
  onChange: (value: string) => void;
}

const IMAGE_CONTROL_BINDINGS: Record<string, ImageControlBindings> = {
  'bky/image:attachmentId': {
    alt: 'alt',
    size: 'size',
    loading: 'loading',
    decoding: 'decoding',
    fit: 'fit',
    focalX: 'focalX',
    focalY: 'focalY',
  },
};

const IMAGE_LOADING_OPTIONS: Array<[string, string]> = [
  ['lazy', 'Lazy'],
  ['eager', 'Eager'],
];

const IMAGE_DECODING_OPTIONS: Array<[string, string]> = [
  ['async', 'Async'],
  ['sync', 'Sync'],
  ['auto', 'Auto'],
];

const IMAGE_FIT_OPTIONS: Array<[string, string]> = [
  ['cover', 'Cover'],
  ['contain', 'Contain'],
  ['fill', 'Fill'],
  ['none', 'None'],
  ['scale-down', 'Scale Down'],
];

const DEFAULT_IMAGE_SIZE_OPTIONS: Array<[string, string]> = [
  ['thumbnail', 'Thumbnail'],
  ['medium', 'Medium'],
  ['large', 'Large'],
  ['full', 'Full'],
];

const FOCAL_POINT_PRESETS = [
  { key: 'top-left', x: 0, y: 0 },
  { key: 'top-center', x: 50, y: 0 },
  { key: 'top-right', x: 100, y: 0 },
  { key: 'center-left', x: 0, y: 50 },
  { key: 'center', x: 50, y: 50 },
  { key: 'center-right', x: 100, y: 50 },
  { key: 'bottom-left', x: 0, y: 100 },
  { key: 'bottom-center', x: 50, y: 100 },
  { key: 'bottom-right', x: 100, y: 100 },
];

const RESPONSIVE_BREAKPOINT_OPTIONS: Array<{
  id: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  label: string;
}> = [
  { id: 'base', label: 'Base' },
  { id: 'sm', label: 'SM' },
  { id: 'md', label: 'MD' },
  { id: 'lg', label: 'LG' },
  { id: 'xl', label: 'XL' },
  { id: '2xl', label: '2XL' },
];

const TemplatePartControl: FunctionComponent<TemplatePartControlProps> = ({
  label,
  value,
  onChange,
}) => {
  const pages = useDocumentStore((s) => s.pages);
  const postId = useDocumentStore((s) => s.postId);
  const isLoadingPages = useDocumentStore((s) => s.isLoadingPages);
  const loadPageLibrary = useDocumentStore((s) => s.loadPageLibrary);
  const selectedPostId = Number(value ?? 0);
  const [isPickerOpen, setIsPickerOpen] = useState(false);
  const [query, setQuery] = useState('');

  useEffect(() => {
    if (pages.length === 0 && !isLoadingPages) {
      void loadPageLibrary();
    }
  }, [isLoadingPages, loadPageLibrary, pages.length]);

  useEffect(() => {
    if (!isPickerOpen) return;

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setIsPickerOpen(false);
    };

    window.addEventListener('keydown', handleEscape);
    return () => window.removeEventListener('keydown', handleEscape);
  }, [isPickerOpen]);

  const availablePages = useMemo(() => pages, [pages]);
  const filteredPages = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) return availablePages;

    return availablePages.filter((page) => {
      const haystack = [page.title, page.status, page.type].join(' ').toLowerCase();
      return haystack.includes(normalizedQuery);
    });
  }, [availablePages, query]);
  const selectedPage = availablePages.find((page) => page.id === selectedPostId) ?? null;

  return (
    <div class="block space-y-2">
      <span class="text-xs font-medium text-text-muted">{label}</span>
      <div class="rounded-card border border-border-subtle bg-surface-base p-3">
        {selectedPage ? (
          <div class="space-y-2">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-text-base">
                  {selectedPage.title || `Untitled #${selectedPage.id}`}
                </p>
                <p class="mt-0.5 text-xs text-text-faint">
                  {selectedPage.type} · {selectedPage.status}
                </p>
              </div>
              <span
                class={`shrink-0 rounded-badge px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${templatePartStatusClasses(selectedPage.status)}`}
              >
                {selectedPage.status}
              </span>
            </div>
            <div class="flex items-center gap-2 text-xs text-text-muted">
              <span
                class={`rounded-badge px-2 py-0.5 font-semibold uppercase tracking-wide ${
                  selectedPage.hasDocument
                    ? 'bg-accent-subtle text-accent-text'
                    : 'bg-surface-overlay text-text-faint'
                }`}
              >
                {selectedPage.hasDocument
                  ? t('inspector.blockyShort', 'Blocky')
                  : t('inspector.wordpress', 'WordPress')}
              </span>
              <span>
                {selectedPage.hasDocument
                  ? t(
                      'inspector.containsReusableBlockyDocument',
                      'Contains a reusable Blocky document.'
                    )
                  : t(
                      'inspector.standardWordPressPageWithoutBlocky',
                      'Standard WordPress page without a saved Blocky document.'
                    )}
              </span>
            </div>
          </div>
        ) : (
          <p class="text-sm text-text-faint">
            {t('inspector.noPageSelected', 'No page selected.')}
          </p>
        )}
      </div>
      <div class="flex gap-2">
        <button
          type="button"
          onClick={() => setIsPickerOpen(true)}
          class="flex-1 rounded-input border border-border-base bg-surface-elevated px-3 py-1.5 text-xs font-medium text-text-base hover:border-accent-base hover:bg-accent-subtle focus:outline-none"
        >
          {selectedPage
            ? t('inspector.changePage', 'Change page')
            : t('inspector.choosePage', 'Choose page')}
        </button>
        {selectedPage && (
          <button
            type="button"
            onClick={() => onChange(0)}
            class="rounded-input border border-border-base bg-surface-elevated px-3 py-1.5 text-xs font-medium text-feedback-danger hover:border-feedback-danger focus:outline-none"
          >
            {t('inspector.clearPage', 'Clear')}
          </button>
        )}
      </div>
      <p class="text-xs text-text-faint">
        {isLoadingPages
          ? t('inspector.loadingBlockyPages', 'Loading Blocky pages...')
          : availablePages.length > 0
            ? t(
                'inspector.pagesListedHint',
                'All pages are listed. Blocky pages are marked with a badge.'
              )
            : t('inspector.noPagesAvailableYet', 'No pages available yet.')}
      </p>
      {isPickerOpen && (
        <div
          class="fixed inset-0 z-[100002] flex items-center justify-center bg-black/45 p-6"
          onClick={() => setIsPickerOpen(false)}
        >
          <div
            class="flex max-h-[85vh] w-full max-w-5xl flex-col overflow-hidden rounded-card border border-border-strong bg-surface-elevated shadow-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div class="flex items-center justify-between gap-4 border-b border-border-subtle px-5 py-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-text-faint">
                  {t('inspector.templatePart', 'Template Part')}
                </p>
                <p class="mt-1 text-lg font-semibold text-text-base">
                  {t('inspector.selectPage', 'Select a page')}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setIsPickerOpen(false)}
                class="flex h-8 w-8 items-center justify-center rounded-input text-text-muted transition-colors hover:bg-surface-overlay hover:text-text-base"
                aria-label={t('inspector.closePicker', 'Close picker')}
              >
                ×
              </button>
            </div>

            <div class="border-b border-border-subtle px-5 py-4">
              <label class="block">
                <span class="sr-only">{t('inspector.filterPages', 'Filter pages')}</span>
                <input
                  type="search"
                  value={query}
                  onInput={(event) => setQuery((event.target as HTMLInputElement).value)}
                  placeholder={t(
                    'inspector.filterPagesPlaceholder',
                    'Filter by page title or status…'
                  )}
                  class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
                />
              </label>
            </div>

            <div class="flex-1 overflow-y-auto p-5">
              {isLoadingPages ? (
                <div class="flex min-h-40 items-center justify-center text-sm text-text-muted">
                  {t('inspector.loadingPages', 'Loading Blocky pages…')}
                </div>
              ) : filteredPages.length === 0 ? (
                <div class="flex min-h-40 flex-col items-center justify-center gap-2 text-center text-text-muted">
                  <p class="text-base font-semibold text-text-base">
                    {t('inspector.noPagesFound', 'No pages found')}
                  </p>
                  <p class="text-sm">
                    {t(
                      'inspector.noPagesFoundDescription',
                      'Try a different filter or create a new page.'
                    )}
                  </p>
                </div>
              ) : (
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                  {filteredPages.map((page) => {
                    const isSelected = page.id === selectedPostId;
                    const isCurrentPage = page.id === postId;
                    return (
                      <button
                        key={page.id}
                        type="button"
                        disabled={isCurrentPage}
                        onClick={() => {
                          if (isCurrentPage) return;
                          onChange(page.id);
                          setIsPickerOpen(false);
                        }}
                        class={`group flex min-h-44 flex-col items-start gap-4 rounded-card border p-4 text-left transition-colors ${
                          isCurrentPage
                            ? 'cursor-not-allowed border-border-subtle bg-surface-base opacity-70'
                            : isSelected
                              ? 'border-accent-base bg-accent-subtle'
                              : 'border-border-subtle bg-surface-base hover:border-accent-base hover:bg-surface-overlay'
                        }`}
                      >
                        <div class="flex w-full items-start justify-between gap-3">
                          <span
                            class={`flex h-10 w-10 items-center justify-center rounded-input text-sm font-bold ${
                              isSelected
                                ? 'bg-accent-base text-text-on-accent'
                                : 'bg-surface-elevated text-accent-base'
                            }`}
                          >
                            {page.hasDocument ? 'BK' : 'PG'}
                          </span>
                          <div class="flex items-center gap-2">
                            {isCurrentPage && (
                              <span class="rounded-badge bg-feedback-warning/12 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-feedback-warning">
                                {t('inspector.current', 'Current')}
                              </span>
                            )}
                            <span
                              class={`rounded-badge px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${
                                page.hasDocument
                                  ? 'bg-accent-subtle text-accent-text'
                                  : 'bg-surface-overlay text-text-faint'
                              }`}
                            >
                              {page.hasDocument
                                ? t('inspector.blockyShort', 'Blocky')
                                : t('inspector.wpShort', 'WP')}
                            </span>
                            <span
                              class={`rounded-badge px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${templatePartStatusClasses(page.status)}`}
                            >
                              {page.status}
                            </span>
                          </div>
                        </div>

                        <div class="flex-1">
                          <h3 class="line-clamp-2 text-base font-semibold text-text-base">
                            {page.title || `Untitled #${page.id}`}
                          </h3>
                          <p class="mt-1 text-sm text-text-muted">
                            {page.type} · {templatePartModifiedLabel(page.modified)}
                          </p>
                        </div>

                        <div class="flex w-full items-center justify-between gap-3 text-xs text-text-faint">
                          <span>
                            {isCurrentPage
                              ? t(
                                  'inspector.currentPageUnavailable',
                                  'Current page cannot be inserted into itself'
                                )
                              : page.hasDocument
                                ? t(
                                    'inspector.containsBlockyDocument',
                                    'Contains a Blocky document'
                                  )
                                : t('inspector.standardWordPressPage', 'Standard WordPress page')}
                          </span>
                          <span
                            class={`font-semibold transition-transform ${isSelected ? 'text-accent-text' : 'text-accent-text group-hover:translate-x-0.5'}`}
                          >
                            {isCurrentPage
                              ? t('inspector.unavailable', 'Unavailable')
                              : isSelected
                                ? t('inspector.selected', 'Selected')
                                : t('inspector.useThis', 'Use this')}
                          </span>
                        </div>
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

function templatePartStatusClasses(status: string): string {
  if (status === 'publish') return 'bg-feedback-success/12 text-feedback-success';
  if (status === 'draft') return 'bg-accent-subtle text-accent-text';
  if (status === 'private') return 'bg-surface-overlay text-text-muted';
  return 'bg-feedback-warning/12 text-feedback-warning';
}

function templatePartModifiedLabel(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return t('inspector.recentlyUpdated', 'Recently updated');
  return t('inspector.updatedOn', 'Updated %s', [
    date.toLocaleDateString(window.BlockyBuilderConfig?.locale ?? 'en-US', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }),
  ]);
}

const MediaControl: FunctionComponent<MediaControlProps> = ({
  control,
  value,
  onChange,
  node,
  onUpdate,
}) => {
  const returnsUrl = control.mediaReturn === 'url';
  const attachmentId = returnsUrl ? 0 : Number(value ?? 0);
  const urlValue = returnsUrl ? String(value ?? '') : '';
  const [previewSrc, setPreviewSrc] = useState<string | null>(null);
  const [attachmentPreview, setAttachmentPreview] = useState<MediaAttachmentPreview>({
    sourceUrl: null,
    altText: '',
    sizeOptions: DEFAULT_IMAGE_SIZE_OPTIONS,
  });
  const WP_REST_BASE = (window.BlockyBuilderConfig?.restUrl ?? '/wp-json/blocky/v1/').replace(
    /blocky\/v1\/?$/,
    'wp/v2/'
  );
  const imageBindings = useMemo(
    () => mediaImageBindingsForControl(node, control),
    [control.id, control.mediaType, node?.type]
  );
  const isExtendedImageControl = !!(
    imageBindings &&
    node &&
    onUpdate &&
    control.mediaType === 'image' &&
    !returnsUrl
  );
  const currentAlt = imageBindings && node ? stringProp(node.props[imageBindings.alt]) : '';
  const currentSize =
    imageBindings && node ? stringProp(node.props[imageBindings.size], 'large') : 'large';
  const currentLoading =
    imageBindings && node ? stringProp(node.props[imageBindings.loading], 'lazy') : 'lazy';
  const currentDecoding =
    imageBindings && node ? stringProp(node.props[imageBindings.decoding], 'async') : 'async';
  const currentFit =
    imageBindings && node ? stringProp(node.props[imageBindings.fit], 'cover') : 'cover';
  const currentFocalX =
    imageBindings && node ? percentageProp(node.props[imageBindings.focalX], 50) : 50;
  const currentFocalY =
    imageBindings && node ? percentageProp(node.props[imageBindings.focalY], 50) : 50;
  const availableSizeOptions = useMemo(
    () =>
      mergeSelectOptions(DEFAULT_IMAGE_SIZE_OPTIONS, attachmentPreview.sizeOptions, [
        [currentSize, labelFromId(currentSize)],
      ]),
    [attachmentPreview.sizeOptions, currentSize]
  );

  useEffect(() => {
    if (returnsUrl) {
      setPreviewSrc(urlValue || null);
      setAttachmentPreview({
        sourceUrl: urlValue || null,
        altText: '',
        sizeOptions: DEFAULT_IMAGE_SIZE_OPTIONS,
      });
      return;
    }
    if (attachmentId <= 0) {
      setPreviewSrc(null);
      setAttachmentPreview({
        sourceUrl: null,
        altText: '',
        sizeOptions: DEFAULT_IMAGE_SIZE_OPTIONS,
      });
      return;
    }
    fetch(`${WP_REST_BASE}media/${attachmentId}?_fields=source_url,alt_text,media_details`, {
      headers: { 'X-WP-Nonce': window.BlockyBuilderConfig?.nonce ?? '' },
    })
      .then((res) => (res.ok ? res.json() : null))
      .then(
        (
          data: {
            source_url?: string;
            alt_text?: string;
            media_details?: { sizes?: Record<string, { width?: number; height?: number }> };
          } | null
        ) => {
          setPreviewSrc(data?.source_url ?? null);
          setAttachmentPreview({
            sourceUrl: data?.source_url ?? null,
            altText: data?.alt_text ?? '',
            sizeOptions: extractWordPressSizeOptions(data?.media_details?.sizes),
          });
        }
      )
      .catch(() => {
        setPreviewSrc(null);
        setAttachmentPreview({
          sourceUrl: null,
          altText: '',
          sizeOptions: DEFAULT_IMAGE_SIZE_OPTIONS,
        });
      });
  }, [attachmentId, urlValue, returnsUrl, WP_REST_BASE]);

  const applySingleValue = (nextValue: unknown): void => {
    if (onUpdate && node) {
      onUpdate({ [control.id]: nextValue });
      return;
    }
    onChange(nextValue);
  };

  const updateImageProp = (key: keyof ImageControlBindings, nextValue: string | number): void => {
    if (!imageBindings || !onUpdate) return;
    onUpdate({ [imageBindings[key]]: nextValue });
  };

  const updateFocalPoint = (next: { x: number; y: number }): void => {
    if (!imageBindings || !onUpdate) return;
    onUpdate({
      [imageBindings.focalX]: next.x,
      [imageBindings.focalY]: next.y,
    });
  };

  const openPicker = () => {
    const wp = (
      window as Window & {
        wp?: {
          media?: (opts: Record<string, unknown>) => {
            on: (ev: string, cb: () => void) => void;
            state: () => {
              get: (k: string) => { first: () => { toJSON: () => Record<string, unknown> } };
            };
            open: () => void;
          };
        };
      }
    ).wp;
    if (!wp?.media) {
      alert(t('inspector.mediaLibraryUnavailable', 'WordPress media library not available.'));
      return;
    }
    const mediaType = control.mediaType || (returnsUrl ? 'video' : 'image');
    const frame = wp.media({
      title: control.label || t('inspector.selectMedia', 'Select Media'),
      button: { text: t('inspector.select', 'Select') },
      multiple: false,
      library: mediaType ? { type: mediaType } : {},
    });
    frame.on('select', () => {
      const att = frame.state().get('selection').first().toJSON();
      const nextValue = returnsUrl ? ((att['url'] as string) ?? '') : ((att['id'] as number) ?? 0);
      if (isExtendedImageControl && imageBindings && onUpdate) {
        const nextProps: Record<string, unknown> = { [control.id]: nextValue };
        const selectedAlt = String(att['alt'] ?? att['alt_text'] ?? '').trim();
        if (selectedAlt && currentAlt.trim() === '') {
          nextProps[imageBindings.alt] = selectedAlt;
        }
        onUpdate(nextProps);
        return;
      }
      onChange(nextValue);
    });
    frame.open();
  };

  const clear = () => applySingleValue(returnsUrl ? '' : 0);

  return (
    <div class="block space-y-2">
      <span class="text-xs font-medium text-text-muted">{control.label}</span>
      {previewSrc && (
        <div class="relative overflow-hidden rounded-input border border-border-base bg-surface-base">
          {control.mediaType === 'video' || returnsUrl ? (
            <video src={previewSrc} class="max-h-28 w-full object-cover" muted />
          ) : (
            <img src={previewSrc} alt="" class="max-h-28 w-full object-cover" />
          )}
        </div>
      )}
      {!previewSrc && (
        <div class="flex h-16 items-center justify-center rounded-input border border-dashed border-border-base bg-surface-base text-xs text-text-faint">
          {t('inspector.noMediaSelected', 'No media selected')}
        </div>
      )}
      <div class="flex gap-2">
        <button
          type="button"
          onClick={openPicker}
          class="flex-1 rounded-input border border-border-base bg-surface-elevated px-3 py-1.5 text-xs
                 font-medium text-text-base hover:border-accent-base hover:bg-accent-subtle focus:outline-none"
        >
          {previewSrc ? t('inspector.change', 'Change') : t('inspector.select', 'Select')}
        </button>
        {previewSrc && (
          <button
            type="button"
            onClick={clear}
            class="rounded-input border border-border-base bg-surface-elevated px-3 py-1.5 text-xs
                   font-medium text-feedback-danger hover:border-feedback-danger focus:outline-none"
          >
            {t('inspector.remove', 'Remove')}
          </button>
        )}
      </div>

      {isExtendedImageControl && (
        <div class="space-y-3 rounded-input border border-border-subtle bg-surface-base p-3">
          <label class="block space-y-1">
            <span class="text-xs font-medium text-text-muted">
              {t('inspector.altText', 'Alt Text')}
            </span>
            <input
              type="text"
              value={currentAlt}
              onInput={(event) => updateImageProp('alt', (event.target as HTMLInputElement).value)}
              placeholder={
                attachmentPreview.altText || t('inspector.altSuggestion', 'Describe the image')
              }
              class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
            />
          </label>

          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <SelectField
              label={t('inspector.imageSize', 'Image Size')}
              value={currentSize}
              options={availableSizeOptions}
              onChange={(nextValue) => updateImageProp('size', nextValue)}
            />
            <SelectField
              label={t('inspector.objectFit', 'Object Fit')}
              value={currentFit}
              options={IMAGE_FIT_OPTIONS}
              onChange={(nextValue) => updateImageProp('fit', nextValue)}
            />
          </div>

          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <SelectField
              label={t('inspector.loading', 'Loading')}
              value={currentLoading}
              options={IMAGE_LOADING_OPTIONS}
              onChange={(nextValue) => updateImageProp('loading', nextValue)}
            />
            <SelectField
              label={t('inspector.decoding', 'Decoding')}
              value={currentDecoding}
              options={IMAGE_DECODING_OPTIONS}
              onChange={(nextValue) => updateImageProp('decoding', nextValue)}
            />
          </div>

          <FocalPointControl
            label={t('inspector.focalPoint', 'Focal Point')}
            x={currentFocalX}
            y={currentFocalY}
            onChange={updateFocalPoint}
          />
        </div>
      )}
    </div>
  );
};

const FocalPointControl: FunctionComponent<FocalPointControlProps> = ({
  label,
  x,
  y,
  onChange,
}) => (
  <div class="space-y-2">
    <span class="text-xs font-medium text-text-muted">{label}</span>
    <div class="grid grid-cols-3 gap-1">
      {FOCAL_POINT_PRESETS.map((preset) => {
        const isActive = preset.x === x && preset.y === y;
        return (
          <button
            key={preset.key}
            type="button"
            onClick={() => onChange({ x: preset.x, y: preset.y })}
            class={`flex h-8 items-center justify-center rounded-input border text-xs transition-colors ${
              isActive
                ? 'border-accent-base bg-accent-subtle text-accent-text'
                : 'border-border-base bg-surface-elevated text-text-muted hover:border-accent-base hover:text-text-base'
            }`}
            aria-label={`${label}: ${preset.key}`}
          >
            <span
              class={`h-2.5 w-2.5 rounded-full ${isActive ? 'bg-accent-text' : 'bg-current'}`}
            />
          </button>
        );
      })}
    </div>
    <div class="grid grid-cols-2 gap-2">
      <label class="block space-y-1">
        <span class="text-xs font-medium text-text-muted">X</span>
        <input
          type="number"
          min={0}
          max={100}
          step={1}
          value={String(x)}
          onInput={(event) =>
            onChange({ x: percentageProp((event.target as HTMLInputElement).value, x), y })
          }
          class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base focus:border-accent-base focus:outline-none"
        />
      </label>
      <label class="block space-y-1">
        <span class="text-xs font-medium text-text-muted">Y</span>
        <input
          type="number"
          min={0}
          max={100}
          step={1}
          value={String(y)}
          onInput={(event) =>
            onChange({ x, y: percentageProp((event.target as HTMLInputElement).value, y) })
          }
          class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base focus:border-accent-base focus:outline-none"
        />
      </label>
    </div>
  </div>
);

const SelectField: FunctionComponent<SelectFieldProps> = ({ label, value, options, onChange }) => (
  <label class="block space-y-1">
    <span class="text-xs font-medium text-text-muted">{label}</span>
    <select
      class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base focus:border-accent-base focus:outline-none"
      value={value}
      onChange={(event) => onChange((event.target as HTMLSelectElement).value)}
    >
      {options.map(([optionValue, optionLabel]) => (
        <option key={optionValue} value={optionValue}>
          {optionLabel}
        </option>
      ))}
    </select>
  </label>
);

interface ResponsiveBreakpointBarProps {
  value: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  onChange: (value: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl') => void;
}

const ResponsiveBreakpointBar: FunctionComponent<ResponsiveBreakpointBarProps> = ({
  value,
  onChange,
}) => (
  <div class="space-y-1 rounded-input border border-border-subtle bg-surface-base p-2">
    <div class="flex items-center justify-between gap-2">
      <span class="text-xs font-medium text-text-muted">
        {t('inspector.breakpointScope', 'Breakpoint scope')}
      </span>
      <span class="text-[11px] font-semibold uppercase tracking-wide text-text-faint">{value}</span>
    </div>
    <div class="grid grid-cols-6 gap-1">
      {RESPONSIVE_BREAKPOINT_OPTIONS.map((option) => {
        const isActive = option.id === value;
        return (
          <button
            key={option.id}
            type="button"
            onClick={() => onChange(option.id)}
            class={`rounded-input px-2 py-1 text-[11px] font-semibold transition-colors ${
              isActive
                ? 'bg-accent-base text-text-on-accent'
                : 'bg-surface-elevated text-text-muted hover:bg-surface-overlay hover:text-text-base'
            }`}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  </div>
);

interface TailwindClassPanelProps {
  node: BuilderNode;
  onUpdate: (props: Record<string, unknown>) => void;
}

const CUSTOM_PREFIX = '__custom';
const QUICK_STATE_VARIANTS = ['base', 'hover', 'focus', 'active', 'disabled'];
const QUICK_SCREEN_VARIANTS = ['sm', 'md', 'lg', 'xl', '2xl'];
const MOTION_TRANSITION_OPTIONS: Array<[string, string]> = [
  ['transition-none', 'None'],
  ['transition', 'Default'],
  ['transition-colors', 'Colors'],
  ['transition-opacity', 'Opacity'],
  ['transition-shadow', 'Shadow'],
  ['transition-transform', 'Transform'],
  ['transition-all', 'All'],
];
const MOTION_DURATION_OPTIONS: Array<[string, string]> = [
  ['duration-75', '75ms'],
  ['duration-100', '100ms'],
  ['duration-150', '150ms'],
  ['duration-200', '200ms'],
  ['duration-300', '300ms'],
  ['duration-500', '500ms'],
  ['duration-700', '700ms'],
  ['duration-1000', '1000ms'],
];
const MOTION_DELAY_OPTIONS: Array<[string, string]> = [
  ['', 'None'],
  ['delay-0', '0ms'],
  ['delay-75', '75ms'],
  ['delay-100', '100ms'],
  ['delay-150', '150ms'],
  ['delay-200', '200ms'],
  ['delay-300', '300ms'],
  ['delay-500', '500ms'],
  ['delay-700', '700ms'],
  ['delay-1000', '1000ms'],
];
const MOTION_EASING_OPTIONS: Array<[string, string]> = [
  ['ease-linear', 'Linear'],
  ['ease-in', 'Ease In'],
  ['ease-out', 'Ease Out'],
  ['ease-in-out', 'Ease In Out'],
];
const MOTION_GUARD_OPTIONS: Array<[string, string]> = [
  ['motion-safe', 'Motion Safe'],
  ['', 'No Guard'],
  ['motion-reduce', 'Motion Reduce'],
];
const HOVER_SCALE_OPTIONS: Array<[string, string]> = [
  ['', 'None'],
  ['scale-95', '95%'],
  ['scale-100', '100%'],
  ['scale-105', '105%'],
  ['scale-110', '110%'],
  ['scale-125', '125%'],
];
const HOVER_OPACITY_OPTIONS: Array<[string, string]> = [
  ['', 'None'],
  ['opacity-75', '75%'],
  ['opacity-90', '90%'],
  ['opacity-95', '95%'],
  ['opacity-100', '100%'],
];
interface FlexLayoutGroup {
  id: string;
  label: string;
  defaultValue: string;
  options: VisualOption[];
}

const FLEX_LAYOUT_NODE_TYPES = new Set(['bky/container', 'bky/card']);
const FLEX_DISPLAY_OPTIONS: VisualOption[] = [
  { value: 'flex', label: 'Flex', icon: 'direction' },
  { value: 'inline-flex', label: 'Inline Flex', icon: 'direction' },
];
const FLEX_DIRECTION_OPTIONS: VisualOption[] = [
  { value: 'flex-row', label: 'Row', icon: 'direction' },
  { value: 'flex-row-reverse', label: 'Row Reverse', icon: 'direction' },
  { value: 'flex-col', label: 'Column', icon: 'direction' },
  { value: 'flex-col-reverse', label: 'Column Reverse', icon: 'direction' },
];
const FLEX_WRAP_OPTIONS: VisualOption[] = [
  { value: 'flex-nowrap', label: 'No Wrap', icon: 'wrap' },
  { value: 'flex-wrap', label: 'Wrap', icon: 'wrap' },
  { value: 'flex-wrap-reverse', label: 'Wrap Reverse', icon: 'wrap' },
];
const FLEX_JUSTIFY_OPTIONS: VisualOption[] = [
  { value: 'justify-start', label: 'Start', icon: 'justify' },
  { value: 'justify-center', label: 'Center', icon: 'justify' },
  { value: 'justify-end', label: 'End', icon: 'justify' },
  { value: 'justify-between', label: 'Space Between', icon: 'justify' },
  { value: 'justify-around', label: 'Space Around', icon: 'justify' },
  { value: 'justify-evenly', label: 'Space Evenly', icon: 'justify' },
];
const FLEX_ALIGN_ITEMS_OPTIONS: VisualOption[] = [
  { value: 'items-start', label: 'Start', icon: 'align' },
  { value: 'items-center', label: 'Center', icon: 'align' },
  { value: 'items-end', label: 'End', icon: 'align' },
  { value: 'items-stretch', label: 'Stretch', icon: 'align' },
  { value: 'items-baseline', label: 'Baseline', icon: 'align' },
];
const FLEX_ALIGN_CONTENT_OPTIONS: VisualOption[] = [
  { value: 'content-start', label: 'Start', icon: 'content' },
  { value: 'content-center', label: 'Center', icon: 'content' },
  { value: 'content-end', label: 'End', icon: 'content' },
  { value: 'content-stretch', label: 'Stretch', icon: 'content' },
  { value: 'content-between', label: 'Space Between', icon: 'content' },
  { value: 'content-around', label: 'Space Around', icon: 'content' },
  { value: 'content-evenly', label: 'Space Evenly', icon: 'content' },
];
const FLEX_DISPLAY_GROUP: FlexLayoutGroup = {
  id: 'display',
  label: 'Display',
  defaultValue: 'flex',
  options: FLEX_DISPLAY_OPTIONS,
};
const FLEX_LAYOUT_GROUPS: FlexLayoutGroup[] = [
  FLEX_DISPLAY_GROUP,
  {
    id: 'direction',
    label: 'Direction',
    defaultValue: 'flex-row',
    options: FLEX_DIRECTION_OPTIONS,
  },
  { id: 'wrap', label: 'Wrap', defaultValue: 'flex-nowrap', options: FLEX_WRAP_OPTIONS },
  {
    id: 'justify',
    label: 'Justify Content',
    defaultValue: 'justify-start',
    options: FLEX_JUSTIFY_OPTIONS,
  },
  {
    id: 'alignItems',
    label: 'Align Items',
    defaultValue: 'items-stretch',
    options: FLEX_ALIGN_ITEMS_OPTIONS,
  },
  {
    id: 'alignContent',
    label: 'Align Content',
    defaultValue: 'content-start',
    options: FLEX_ALIGN_CONTENT_OPTIONS,
  },
];
const FLEX_MANAGED_BASE_CLASSES = new Set(
  FLEX_LAYOUT_GROUPS.flatMap((group) => group.options.map((option) => option.value))
);
type BoxModelKind = 'margin' | 'padding';
type BoxModelSide = 'top' | 'right' | 'bottom' | 'left';
type BoxModelUnit = 'px' | 'rem' | 'em' | '%' | 'vh' | 'vw';

interface BoxModelSideConfig {
  side: BoxModelSide;
  label: string;
  shortLabel: string;
}

const BOX_MODEL_SIDES: BoxModelSideConfig[] = [
  { side: 'top', label: 'Top', shortLabel: 'T' },
  { side: 'right', label: 'Right', shortLabel: 'R' },
  { side: 'bottom', label: 'Bottom', shortLabel: 'B' },
  { side: 'left', label: 'Left', shortLabel: 'L' },
];
const BOX_MODEL_UNITS: BoxModelUnit[] = ['px', 'rem', 'em', '%', 'vh', 'vw'];
const BOX_MODEL_CLASS_PREFIX: Record<BoxModelKind, Record<BoxModelSide, string>> = {
  margin: { top: 'mt', right: 'mr', bottom: 'mb', left: 'ml' },
  padding: { top: 'pt', right: 'pr', bottom: 'pb', left: 'pl' },
};
const BOX_MODEL_VAR_PREFIX: Record<BoxModelKind, string> = {
  margin: '--bky-space-margin',
  padding: '--bky-space-padding',
};
const MOTION_TRANSITION_VALUES = new Set(
  MOTION_TRANSITION_OPTIONS.map(([value]) => value).filter(Boolean)
);
const MOTION_EASING_VALUES = new Set(MOTION_EASING_OPTIONS.map(([value]) => value));
const HOVER_SCALE_VALUES = new Set(HOVER_SCALE_OPTIONS.map(([value]) => value).filter(Boolean));
const HOVER_OPACITY_VALUES = new Set(HOVER_OPACITY_OPTIONS.map(([value]) => value).filter(Boolean));
const VARIANT_SHORT_LABELS: Record<string, string> = {
  base: 'B',
  hover: 'H',
  focus: 'F',
  active: 'A',
  disabled: 'D',
  sm: 'SM',
  md: 'MD',
  lg: 'LG',
  xl: 'XL',
  '2xl': '2XL',
};
const INTERACTION_DEVICE_OPTIONS: Array<[InteractionDevice, string]> = [
  ['any', 'Any Device'],
  ['desktop', 'Desktop'],
  ['tablet', 'Tablet'],
  ['mobile', 'Mobile'],
];
const INTERACTION_LOGIN_STATE_OPTIONS: Array<[InteractionLoginState, string]> = [
  ['any', 'Any State'],
  ['logged-in', 'Logged In'],
  ['logged-out', 'Logged Out'],
];

const TailwindClassPanel: FunctionComponent<TailwindClassPanelProps> = ({ node, onUpdate }) => {
  const responsiveBreakpoint = useUiStore((s) => s.responsiveBreakpoint);
  const setResponsiveBreakpoint = useUiStore((s) => s.setResponsiveBreakpoint);
  const [query, setQuery] = useState('');
  const [prefix, setPrefix] = useState(breakpointPrefix(responsiveBreakpoint));
  const [customPrefix, setCustomPrefix] = useState('');
  const [manualClass, setManualClass] = useState('');
  const [forceOverride, setForceOverride] = useState(false);
  const [colorRole, setColorRole] = useState(tailwindColorRoles[0]?.id ?? 'background');
  const [hexColor, setHexColor] = useState('#0ea5e9');
  const [motionTransition, setMotionTransition] = useState('transition');
  const [motionDuration, setMotionDuration] = useState('duration-200');
  const [motionDelay, setMotionDelay] = useState('');
  const [motionEasing, setMotionEasing] = useState('ease-out');
  const [motionGuard, setMotionGuard] = useState('motion-safe');

  const activeClasses = useMemo(() => classesForNode(node), [node]);
  const activeClassSet = useMemo(() => new Set(activeClasses), [activeClasses]);
  const colorVars = useMemo(() => colorVarsForNode(node), [node]);
  const styleVars = useMemo(() => styleVarsForNode(node), [node]);
  const hasMotionSet = useMemo(() => activeClasses.some(isMotionTimingClass), [activeClasses]);
  const activePrefix = prefix === CUSTOM_PREFIX ? normalizePrefix(customPrefix) : prefix;
  const activeVariant =
    prefix === CUSTOM_PREFIX
      ? null
      : (tailwindVariantOptions.find((option) => option.prefix === activePrefix) ??
        tailwindVariantOptions[0] ??
        null);
  const selectedColorRole =
    tailwindColorRoles.find((role) => role.id === colorRole) ?? tailwindColorRoles[0] ?? null;
  const normalizedQuery = query.trim().toLowerCase();

  const filteredGroups = useMemo(() => {
    if (normalizedQuery === '') return tailwindUtilityGroups;
    return tailwindUtilityGroups
      .map((group) => ({
        ...group,
        utilities: group.utilities
          .map((utility) => ({
            ...utility,
            classes: utility.classes.filter(
              (className) =>
                className.toLowerCase().includes(normalizedQuery) ||
                utility.label.toLowerCase().includes(normalizedQuery) ||
                group.label.toLowerCase().includes(normalizedQuery)
            ),
          }))
          .filter((utility) => utility.classes.length > 0),
      }))
      .filter((group) => group.utilities.length > 0);
  }, [normalizedQuery]);

  useEffect(() => {
    if (prefix === CUSTOM_PREFIX) return;
    setPrefix(breakpointPrefix(responsiveBreakpoint));
  }, [prefix, responsiveBreakpoint]);

  const toggleClass = (className: string): void => {
    const nextClass = composeUtilityClass(activePrefix, className, forceOverride);
    if (activeClassSet.has(nextClass)) {
      updateClasses(activeClasses.filter((current) => current !== nextClass));
      return;
    }
    updateClasses([...activeClasses, nextClass]);
  };

  const removeClass = (className: string): void => {
    const nextClasses = activeClasses.filter((current) => current !== className);
    const variableName = variableNameFromUtilityClass(className);
    if (variableName && variableName in colorVars) {
      const nextVars = { ...colorVars };
      delete nextVars[variableName];
      onUpdate({ twClasses: nextClasses, twColorVars: nextVars });
      return;
    }
    if (variableName && variableName in styleVars) {
      const nextVars = { ...styleVars };
      delete nextVars[variableName];
      onUpdate({ twClasses: nextClasses, twStyleVars: nextVars });
      return;
    }
    updateClasses(nextClasses);
  };

  const addManualClasses = (): void => {
    const nextClasses = manualClass
      .split(/\s+/)
      .map((className) => className.trim())
      .filter(Boolean)
      .map((className) => composeUtilityClass(activePrefix, className, forceOverride));
    if (!nextClasses.length) return;
    updateClasses([...new Set([...activeClasses, ...nextClasses])]);
    setManualClass('');
  };

  const addRuntimeColor = (): void => {
    const normalizedColor = normalizeHexColor(hexColor);
    if (!activeVariant || !selectedColorRole || !normalizedColor) return;
    const variableName = `--bky-tw-${activeVariant.id}-${selectedColorRole.id}`;
    const runtimeClass = composeUtilityClass(
      activePrefix,
      `${selectedColorRole.utility}-[var(${variableName})]`,
      forceOverride
    );
    onUpdate({
      twClasses: [...new Set([...activeClasses, runtimeClass])],
      twColorVars: { ...colorVars, [variableName]: normalizedColor },
    });
  };

  const applyMotionSet = (): void => {
    const motionClasses = [motionTransition, motionDuration, motionDelay, motionEasing]
      .filter(Boolean)
      .map((className) => composeMotionClass(motionGuard, className));
    updateClasses([
      ...new Set([
        ...activeClasses.filter((className) => !isMotionTimingClass(className)),
        ...motionClasses,
      ]),
    ]);
  };

  const clearMotionSet = (): void => {
    updateClasses(activeClasses.filter((className) => !isMotionTimingClass(className)));
  };

  const updateClasses = (classes: string[]): void => {
    onUpdate({ twClasses: classes });
  };

  const renderVariantButton = (variantId: string): JSX.Element | null => {
    const option = tailwindVariantOptions.find((item) => item.id === variantId);
    if (!option) return null;
    const isActive = prefix !== CUSTOM_PREFIX && activeVariant?.id === option.id;
    return (
      <button
        key={option.id}
        type="button"
        onClick={() => {
          if (option.id === 'base' || QUICK_SCREEN_VARIANTS.includes(option.id)) {
            setResponsiveBreakpoint(option.id as 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl');
          }
          setPrefix(option.prefix);
          setCustomPrefix('');
        }}
        class={`rounded-input border px-2 py-1.5 text-xs font-semibold ${
          isActive
            ? 'border-accent-base bg-accent-subtle text-accent-text'
            : 'border-border-base bg-surface-elevated text-text-muted hover:border-accent-base hover:text-text-base'
        }`}
        title={option.label}
        aria-label={option.label}
      >
        {VARIANT_SHORT_LABELS[option.id] ?? option.label.slice(0, 2).toUpperCase()}
      </button>
    );
  };

  return (
    <div class="space-y-4">
      <div class="space-y-2">
        <div class="grid grid-cols-5 gap-1">{QUICK_STATE_VARIANTS.map(renderVariantButton)}</div>
        <div class="grid grid-cols-5 gap-1">{QUICK_SCREEN_VARIANTS.map(renderVariantButton)}</div>
      </div>

      <label class="block space-y-1">
        <span class="text-xs font-medium text-text-muted">
          {t('inspector.variantPrefix', 'Variant prefix')}
        </span>
        <select
          class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                 text-text-base focus:border-accent-base focus:outline-none"
          value={prefix}
          onChange={(e) => setPrefix((e.target as HTMLSelectElement).value)}
        >
          {tailwindVariantOptions.map((option) => (
            <option key={option.id} value={option.prefix}>
              {option.label}
            </option>
          ))}
          <option value={CUSTOM_PREFIX}>{t('inspector.customChain', 'Custom chain')}</option>
        </select>
      </label>

      {prefix === CUSTOM_PREFIX && (
        <label class="block space-y-1">
          <span class="text-xs font-medium text-text-muted">
            {t('inspector.customVariantChain', 'Custom variant chain')}
          </span>
          <input
            type="text"
            value={customPrefix}
            placeholder={t('inspector.customVariantPlaceholder', 'md:hover')}
            onInput={(e) => setCustomPrefix((e.target as HTMLInputElement).value)}
            class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                   text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
          />
        </label>
      )}

      <label class="flex items-center justify-between gap-3 rounded-input border border-border-subtle bg-surface-base px-3 py-2 text-xs text-text-muted">
        <span>{t('inspector.forceOverride', 'Force override')}</span>
        <input
          type="checkbox"
          checked={forceOverride}
          onChange={(e) => setForceOverride((e.target as HTMLInputElement).checked)}
          class="h-4 w-4 accent-accent-base"
        />
      </label>

      <div class="space-y-2 rounded-input border border-border-subtle bg-surface-base p-3">
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs font-semibold uppercase tracking-wide text-text-faint">
            {t('inspector.motionSet', 'Motion Set')}
          </span>
          {hasMotionSet && (
            <button
              type="button"
              onClick={clearMotionSet}
              class="rounded-input px-2 py-1 text-xs font-medium text-feedback-danger hover:bg-surface-overlay"
            >
              {t('inspector.clear', 'Clear')}
            </button>
          )}
        </div>
        <div class="grid grid-cols-2 gap-2">
          <MotionSelect
            label={t('inspector.transition', 'Transition')}
            value={motionTransition}
            options={MOTION_TRANSITION_OPTIONS}
            onChange={setMotionTransition}
          />
          <MotionSelect
            label={t('inspector.duration', 'Duration')}
            value={motionDuration}
            options={MOTION_DURATION_OPTIONS}
            onChange={setMotionDuration}
          />
          <MotionSelect
            label={t('inspector.delay', 'Delay')}
            value={motionDelay}
            options={MOTION_DELAY_OPTIONS}
            onChange={setMotionDelay}
          />
          <MotionSelect
            label={t('inspector.easing', 'Easing')}
            value={motionEasing}
            options={MOTION_EASING_OPTIONS}
            onChange={setMotionEasing}
          />
        </div>
        <MotionSelect
          label={t('inspector.guard', 'Guard')}
          value={motionGuard}
          options={MOTION_GUARD_OPTIONS}
          onChange={setMotionGuard}
        />
        <button
          type="button"
          onClick={applyMotionSet}
          class="w-full rounded-button bg-surface-elevated px-3 py-2 text-xs font-medium text-text-base hover:bg-surface-overlay"
        >
          {t('inspector.applyMotionSet', 'Apply Motion Set')}
        </button>
      </div>

      <div class="space-y-2 rounded-input border border-border-subtle bg-surface-base p-3">
        <div class="grid grid-cols-[1fr_auto] gap-2">
          <select
            class="min-w-0 rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                   text-text-base focus:border-accent-base focus:outline-none"
            value={colorRole}
            onChange={(e) => setColorRole((e.target as HTMLSelectElement).value)}
          >
            {tailwindColorRoles.map((role) => (
              <option key={role.id} value={role.id}>
                {role.label}
              </option>
            ))}
          </select>
          <input
            type="color"
            value={normalizeHexColor(hexColor) ?? '#000000'}
            onInput={(e) => setHexColor((e.target as HTMLInputElement).value)}
            class="h-10 w-12 rounded-input border border-border-base bg-surface-base"
            aria-label={t('inspector.hexColorPicker', 'Hex color picker')}
          />
        </div>
        <div class="flex gap-2">
          <input
            type="text"
            value={hexColor}
            onInput={(e) => setHexColor((e.target as HTMLInputElement).value)}
            class="min-w-0 flex-1 rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                   text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
            placeholder={t('inspector.hexColorPlaceholder', '#0ea5e9')}
          />
          <button
            type="button"
            onClick={addRuntimeColor}
            disabled={prefix === CUSTOM_PREFIX || !normalizeHexColor(hexColor)}
            class="rounded-button bg-surface-elevated px-3 py-2 text-xs font-medium text-text-base hover:bg-surface-overlay disabled:cursor-not-allowed disabled:opacity-50"
          >
            {t('inspector.add', 'Add')}
          </button>
        </div>
      </div>

      <label class="block space-y-1">
        <span class="text-xs font-medium text-text-muted">
          {t('inspector.searchUtilities', 'Search utilities')}
        </span>
        <input
          type="search"
          value={query}
          onInput={(e) => setQuery((e.target as HTMLInputElement).value)}
          class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                 text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
          placeholder={t('inspector.searchUtilitiesPlaceholder', 'padding, grid, bg, hover...')}
        />
      </label>

      <div class="space-y-2">
        <span class="text-xs font-medium text-text-muted">
          {t('inspector.activeClasses', 'Active classes')}
        </span>
        {activeClasses.length === 0 ? (
          <p class="rounded-input border border-dashed border-border-base px-3 py-2 text-xs text-text-faint">
            {t('inspector.noTailwindClasses', 'No Tailwind utility classes on this block.')}
          </p>
        ) : (
          <div class="flex flex-wrap gap-1.5">
            {activeClasses.map((className) => (
              <button
                key={className}
                type="button"
                onClick={() => removeClass(className)}
                class="max-w-full rounded-input border border-border-base bg-surface-elevated px-2 py-1 text-left text-xs text-text-base hover:border-feedback-danger"
                title={t('inspector.removeClass', 'Remove class')}
              >
                <span class="break-all">{className}</span>
              </button>
            ))}
          </div>
        )}
      </div>

      <div class="space-y-2">
        <label class="block space-y-1">
          <span class="text-xs font-medium text-text-muted">
            {t('inspector.manualClass', 'Manual class')}
          </span>
          <input
            type="text"
            value={manualClass}
            onInput={(e) => setManualClass((e.target as HTMLInputElement).value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                addManualClasses();
              }
            }}
            class="w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm
                   text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
            placeholder={t(
              'inspector.manualClassPlaceholder',
              'w-[42rem] md:hover:bg-accent-subtle'
            )}
          />
        </label>
        <button
          type="button"
          onClick={addManualClasses}
          class="w-full rounded-button bg-surface-elevated px-3 py-2 text-xs font-medium text-text-base hover:bg-surface-overlay"
        >
          {t('inspector.addClass', 'Add class')}
        </button>
      </div>

      <div class="space-y-3">
        {filteredGroups.map((group) => (
          <section key={group.id} class="space-y-2">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-text-faint">
              {group.label}
            </h3>
            <div class="space-y-2">
              {group.utilities.map((utility) => (
                <details
                  key={utility.id}
                  open={normalizedQuery !== ''}
                  class="rounded-input border border-border-subtle bg-surface-base"
                >
                  <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-text-muted">
                    {utility.label}
                  </summary>
                  <div class="flex flex-wrap gap-1.5 border-t border-border-subtle p-2">
                    {utility.classes.map((className) => {
                      const nextClass = composeUtilityClass(activePrefix, className, forceOverride);
                      const isActive = activeClassSet.has(nextClass);
                      return (
                        <button
                          key={className}
                          type="button"
                          onClick={() => toggleClass(className)}
                          class={`max-w-full rounded-input border px-2 py-1 text-left text-xs ${
                            isActive
                              ? 'border-accent-base bg-accent-subtle text-accent-text'
                              : 'border-border-base bg-surface-elevated text-text-base hover:border-accent-base'
                          }`}
                          title={nextClass}
                        >
                          <span class="break-all">{nextClass}</span>
                        </button>
                      );
                    })}
                  </div>
                </details>
              ))}
            </div>
          </section>
        ))}
      </div>
    </div>
  );
};

interface MotionSelectProps {
  label: string;
  value: string;
  options: Array<[string, string]>;
  onChange: (value: string) => void;
}

const MotionSelect: FunctionComponent<MotionSelectProps> = ({
  label,
  value,
  options,
  onChange,
}) => (
  <label class="block space-y-1">
    <span class="text-xs font-medium text-text-muted">{label}</span>
    <select
      class="w-full rounded-input border border-border-base bg-surface-base px-2 py-1.5 text-xs
             text-text-base focus:border-accent-base focus:outline-none"
      value={value}
      onChange={(event) => onChange((event.target as HTMLSelectElement).value)}
    >
      {options.map(([optionValue, optionLabel]) => (
        <option key={optionValue || 'none'} value={optionValue}>
          {optionLabel}
        </option>
      ))}
    </select>
  </label>
);

interface AnimationsPanelProps {
  node: BuilderNode;
  onUpdate: (props: Record<string, unknown>) => void;
}

const AnimationsPanel: FunctionComponent<AnimationsPanelProps> = ({ node, onUpdate }) => {
  const responsiveBreakpoint = useUiStore((s) => s.responsiveBreakpoint);
  const activeClasses = useMemo(() => classesForNode(node), [node]);
  const [transition, setTransition] = useState('transition');
  const [duration, setDuration] = useState('duration-200');
  const [delay, setDelay] = useState('');
  const [easing, setEasing] = useState('ease-out');
  const [guard, setGuard] = useState('motion-safe');
  const [hoverScale, setHoverScale] = useState('');
  const [hoverOpacity, setHoverOpacity] = useState('');

  const hasAnimationSet = useMemo(
    () => activeClasses.some(isManagedAnimationClass),
    [activeClasses]
  );
  const activeTransition = useMemo(
    () =>
      activeClasses
        .map(baseUtilityClass)
        .find((className) => MOTION_TRANSITION_VALUES.has(className)) ?? 'transition',
    [activeClasses]
  );
  const activeDuration = useMemo(
    () =>
      activeClasses.map(baseUtilityClass).find((className) => /^duration-\d+$/.test(className)) ??
      'duration-200',
    [activeClasses]
  );
  const activeDelay = useMemo(
    () =>
      activeClasses.map(baseUtilityClass).find((className) => /^delay-\d+$/.test(className)) ?? '',
    [activeClasses]
  );
  const activeEasing = useMemo(
    () =>
      activeClasses
        .map(baseUtilityClass)
        .find((className) => MOTION_EASING_VALUES.has(className)) ?? 'ease-out',
    [activeClasses]
  );
  const activeGuard = useMemo(() => {
    if (
      activeClasses.some((className) => variantPrefixesForClass(className).includes('motion-safe'))
    )
      return 'motion-safe';
    if (
      activeClasses.some((className) =>
        variantPrefixesForClass(className).includes('motion-reduce')
      )
    )
      return 'motion-reduce';
    return '';
  }, [activeClasses]);
  const activeHoverScale = useMemo(
    () => activeHoverAnimationValue(activeClasses, HOVER_SCALE_VALUES),
    [activeClasses]
  );
  const activeHoverOpacity = useMemo(
    () => activeHoverAnimationValue(activeClasses, HOVER_OPACITY_VALUES),
    [activeClasses]
  );

  useEffect(() => {
    setTransition(activeTransition);
    setDuration(activeDuration);
    setDelay(activeDelay);
    setEasing(activeEasing);
    setGuard(activeGuard);
    setHoverScale(activeHoverScale);
    setHoverOpacity(activeHoverOpacity);
  }, [
    activeDelay,
    activeDuration,
    activeEasing,
    activeGuard,
    activeHoverOpacity,
    activeHoverScale,
    activeTransition,
    node.id,
  ]);

  const applyAnimationSet = (): void => {
    const scopePrefix = breakpointScopeValue(responsiveBreakpoint);
    const guardPrefix = normalizePrefix([scopePrefix, guard].filter(Boolean).join(':'));
    const hoverPrefix = normalizePrefix([scopePrefix, guard, 'hover'].filter(Boolean).join(':'));
    const timingClasses = [transition, duration, delay, easing]
      .filter(Boolean)
      .map((className) => composeUtilityClass(guardPrefix, className, false));
    const hoverClasses = [hoverScale, hoverOpacity]
      .filter(Boolean)
      .map((className) => composeUtilityClass(hoverPrefix, className, false));

    onUpdate({
      twClasses: [
        ...new Set([
          ...activeClasses.filter((className) => !isManagedAnimationClass(className)),
          ...timingClasses,
          ...hoverClasses,
        ]),
      ],
    });
  };

  const clearAnimationSet = (): void => {
    onUpdate({
      twClasses: activeClasses.filter((className) => !isManagedAnimationClass(className)),
    });
  };

  return (
    <section class="space-y-3 rounded-input border border-border-subtle bg-surface-base p-3">
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold uppercase tracking-wide text-text-faint">
          {t('inspector.motionSet', 'Motion Set')}
        </span>
        {hasAnimationSet && (
          <button
            type="button"
            onClick={clearAnimationSet}
            class="rounded-input px-2 py-1 text-xs font-medium text-feedback-danger hover:bg-surface-overlay"
          >
            {t('inspector.clear', 'Clear')}
          </button>
        )}
      </div>

      <div class="grid grid-cols-2 gap-2">
        <MotionSelect
          label={t('inspector.transition', 'Transition')}
          value={transition}
          options={MOTION_TRANSITION_OPTIONS}
          onChange={setTransition}
        />
        <MotionSelect
          label={t('inspector.duration', 'Duration')}
          value={duration}
          options={MOTION_DURATION_OPTIONS}
          onChange={setDuration}
        />
        <MotionSelect
          label={t('inspector.delay', 'Delay')}
          value={delay}
          options={MOTION_DELAY_OPTIONS}
          onChange={setDelay}
        />
        <MotionSelect
          label={t('inspector.easing', 'Easing')}
          value={easing}
          options={MOTION_EASING_OPTIONS}
          onChange={setEasing}
        />
      </div>

      <MotionSelect
        label={t('inspector.guard', 'Guard')}
        value={guard}
        options={MOTION_GUARD_OPTIONS}
        onChange={setGuard}
      />

      <div class="grid grid-cols-2 gap-2">
        <MotionSelect
          label={t('inspector.hoverScale', 'Hover Scale')}
          value={hoverScale}
          options={HOVER_SCALE_OPTIONS}
          onChange={setHoverScale}
        />
        <MotionSelect
          label={t('inspector.hoverOpacity', 'Hover Opacity')}
          value={hoverOpacity}
          options={HOVER_OPACITY_OPTIONS}
          onChange={setHoverOpacity}
        />
      </div>

      <button
        type="button"
        onClick={applyAnimationSet}
        class="w-full rounded-button bg-surface-elevated px-3 py-2 text-xs font-medium text-text-base hover:bg-surface-overlay"
      >
        {t('inspector.applyMotionSet', 'Apply Motion Set')}
      </button>
    </section>
  );
};

interface InteractionsPanelProps {
  document: BuilderDocument | null;
  node: BuilderNode;
  onUpdate: (props: Record<string, unknown>) => void;
}

const InteractionsPanel: FunctionComponent<InteractionsPanelProps> = ({
  document,
  node,
  onUpdate,
}) => {
  const interactions = useMemo(() => interactionsForNode(node), [node]);
  const overlayIds = useMemo(() => overlayIdsForInspector(document, node.id), [document, node.id]);
  const eventOptions: Array<[InteractionEvent, string]> = [
    ['click', t('inspector.interactionEventClick', 'Click')],
    ['hover', t('inspector.interactionEventHover', 'Hover')],
    ['focus', t('inspector.interactionEventFocus', 'Focus')],
    ['load', t('inspector.interactionEventLoad', 'Load')],
  ];
  const actionOptions: Array<[InteractionAction, string]> = [
    ['overlay.open', t('inspector.interactionActionOverlayOpen', 'Overlay Open')],
    ['overlay.close', t('inspector.interactionActionOverlayClose', 'Overlay Close')],
    ['overlay.toggle', t('inspector.interactionActionOverlayToggle', 'Overlay Toggle')],
    ['class.add', t('inspector.interactionActionClassAdd', 'Class Add')],
    ['class.remove', t('inspector.interactionActionClassRemove', 'Class Remove')],
    ['class.toggle', t('inspector.interactionActionClassToggle', 'Class Toggle')],
    ['custom.emit', t('inspector.interactionActionCustomEmit', 'Custom Event')],
  ];

  const updateRules = (nextRules: InteractionRule[]): void => {
    onUpdate({ interactions: nextRules });
  };

  const updateRule = (index: number, patch: Partial<InteractionRule>): void => {
    const nextRules = interactions.map((rule, ruleIndex) => {
      if (ruleIndex !== index) return rule;
      const nextRule = { ...rule, ...patch };
      if (!nextRule.action.startsWith('class.')) {
        delete nextRule.className;
      }
      return nextRule;
    });
    updateRules(nextRules);
  };

  return (
    <section class="space-y-3 rounded-input border border-border-subtle bg-surface-base p-3">
      <div class="flex items-center justify-between gap-2">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-text-faint">
            {t('inspector.interactionsTitle', 'Interactions')}
          </p>
          <p class="text-[11px] text-text-faint">
            {t(
              'inspector.interactionsDescription',
              'Trigger, action, target, and basic modifiers for runtime behaviors.'
            )}
          </p>
        </div>
        {interactions.length > 0 && (
          <button
            type="button"
            onClick={() => updateRules([])}
            class="rounded-input px-2 py-1 text-xs font-medium text-feedback-danger hover:bg-surface-overlay"
          >
            {t('inspector.clear', 'Clear')}
          </button>
        )}
      </div>

      <div class="space-y-3">
        {interactions.map((rule, index) => (
          <div
            key={`interaction-${index}`}
            class="space-y-3 rounded-input border border-border-subtle bg-surface-elevated p-3"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-semibold text-text-muted">
                {t('inspector.interactionRule', 'Rule %s', [String(index + 1)])}
              </span>
              <button
                type="button"
                onClick={() =>
                  updateRules(interactions.filter((_, ruleIndex) => ruleIndex !== index))
                }
                class="rounded-input px-2 py-1 text-xs font-medium text-feedback-danger hover:bg-surface-overlay"
              >
                {t('inspector.remove', 'Remove')}
              </button>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <SelectField
                label={t('inspector.interactionEvent', 'Event')}
                value={rule.event}
                options={eventOptions}
                onChange={(value) => updateRule(index, { event: value as InteractionEvent })}
              />
              <SelectField
                label={t('inspector.interactionAction', 'Action')}
                value={rule.action}
                options={actionOptions}
                onChange={(value) => updateRule(index, { action: value as InteractionAction })}
              />
            </div>

            {rule.action.startsWith('overlay.') && overlayIds.length > 0 ? (
              <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                <span>{t('inspector.interactionTarget', 'Target')}</span>
                <select
                  value={rule.target}
                  onChange={(event) =>
                    updateRule(index, { target: (event.target as HTMLSelectElement).value })
                  }
                  class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                >
                  <option value="">{t('inspector.selectOverlayTarget', 'Select overlay…')}</option>
                  {overlayIds.map((overlayId) => (
                    <option key={overlayId} value={overlayId}>
                      {overlayId}
                    </option>
                  ))}
                </select>
              </label>
            ) : (
              <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                <span>{t('inspector.interactionTarget', 'Target')}</span>
                <input
                  type="text"
                  value={rule.target}
                  onInput={(event) => updateRule(index, { target: currentTargetValue(event) })}
                  placeholder={
                    rule.action === 'custom.emit'
                      ? t('inspector.interactionTargetEventPlaceholder', 'blocky:event-name')
                      : t(
                          'inspector.interactionTargetSelectorPlaceholder',
                          '#overlay-id or .selector'
                        )
                  }
                  class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                />
              </label>
            )}

            {rule.action.startsWith('class.') && (
              <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                <span>{t('inspector.interactionClassName', 'Class Name')}</span>
                <input
                  type="text"
                  value={rule.className ?? ''}
                  onInput={(event) => updateRule(index, { className: currentTargetValue(event) })}
                  placeholder={t('inspector.interactionClassPlaceholder', 'is-open')}
                  class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                />
              </label>
            )}

            <div class="space-y-2 rounded-input border border-border-subtle bg-surface-base p-3">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-text-faint">
                {t('inspector.interactionConditions', 'Conditions')}
              </p>
              <div class="grid grid-cols-2 gap-2">
                <SelectField
                  label={t('inspector.interactionDevice', 'Device')}
                  value={rule.device ?? 'any'}
                  options={INTERACTION_DEVICE_OPTIONS.map(([value, label]) => [
                    value,
                    t(`inspector.interactionDevice.${value}`, label),
                  ])}
                  onChange={(value) => updateRule(index, { device: value as InteractionDevice })}
                />
                <SelectField
                  label={t('inspector.interactionLoginState', 'Login State')}
                  value={rule.loginState ?? 'any'}
                  options={INTERACTION_LOGIN_STATE_OPTIONS.map(([value, label]) => [
                    value,
                    t(`inspector.interactionLoginState.${value}`, label),
                  ])}
                  onChange={(value) =>
                    updateRule(index, { loginState: value as InteractionLoginState })
                  }
                />
              </div>
              <div class="grid grid-cols-2 gap-2">
                <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                  <span>{t('inspector.interactionQueryKey', 'Query Param')}</span>
                  <input
                    type="text"
                    value={rule.queryKey ?? ''}
                    onInput={(event) => updateRule(index, { queryKey: currentTargetValue(event) })}
                    placeholder={t('inspector.interactionQueryKeyPlaceholder', 'utm_campaign')}
                    class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                  />
                </label>
                <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                  <span>{t('inspector.interactionQueryValue', 'Query Value')}</span>
                  <input
                    type="text"
                    value={rule.queryValue ?? ''}
                    onInput={(event) =>
                      updateRule(index, { queryValue: currentTargetValue(event) })
                    }
                    placeholder={t('inspector.interactionQueryValuePlaceholder', 'spring-sale')}
                    class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                  />
                </label>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                  <span>{t('inspector.interactionCookieKey', 'Cookie')}</span>
                  <input
                    type="text"
                    value={rule.cookieKey ?? ''}
                    onInput={(event) => updateRule(index, { cookieKey: currentTargetValue(event) })}
                    placeholder={t('inspector.interactionCookieKeyPlaceholder', 'promo_seen')}
                    class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                  />
                </label>
                <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                  <span>{t('inspector.interactionCookieValue', 'Cookie Value')}</span>
                  <input
                    type="text"
                    value={rule.cookieValue ?? ''}
                    onInput={(event) =>
                      updateRule(index, { cookieValue: currentTargetValue(event) })
                    }
                    placeholder={t('inspector.interactionCookieValuePlaceholder', '1')}
                    class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                  />
                </label>
              </div>
            </div>

            <div class="space-y-2 rounded-input border border-border-subtle bg-surface-base p-3">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-text-faint">
                {t('inspector.interactionModifiers', 'Modifiers')}
              </p>
              <div class="grid grid-cols-3 gap-2">
                <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                  <span>{t('inspector.interactionDelay', 'Delay (ms)')}</span>
                  <input
                    type="number"
                    min={0}
                    step={50}
                    value={String(rule.delay ?? 0)}
                    onInput={(event) =>
                      updateRule(index, {
                        delay: normalizeInteractionDelay(currentTargetValue(event)),
                      })
                    }
                    class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                  />
                </label>
                <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                  <span>{t('inspector.interactionDebounce', 'Debounce (ms)')}</span>
                  <input
                    type="number"
                    min={0}
                    step={50}
                    value={String(rule.debounce ?? 0)}
                    onInput={(event) =>
                      updateRule(index, {
                        debounce: normalizeInteractionDelay(currentTargetValue(event)),
                      })
                    }
                    class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                  />
                </label>
                <label class="flex flex-col gap-1 text-xs font-medium text-text-muted">
                  <span>{t('inspector.interactionThrottle', 'Throttle (ms)')}</span>
                  <input
                    type="number"
                    min={0}
                    step={50}
                    value={String(rule.throttle ?? 0)}
                    onInput={(event) =>
                      updateRule(index, {
                        throttle: normalizeInteractionDelay(currentTargetValue(event)),
                      })
                    }
                    class="rounded-input border border-border-base bg-surface-elevated px-3 py-2 text-sm text-text-base"
                  />
                </label>
              </div>

              <div class="grid grid-cols-3 gap-2">
                <label class="flex items-center justify-between rounded-input border border-border-subtle bg-surface-elevated px-3 py-2 text-sm text-text-base">
                  <span>{t('inspector.interactionOnce', 'Run Once')}</span>
                  <input
                    type="checkbox"
                    checked={rule.once ?? false}
                    onChange={(event) => updateRule(index, { once: event.currentTarget.checked })}
                    class="h-4 w-4 rounded border-border-base text-accent-base"
                  />
                </label>
                <label class="flex items-center justify-between rounded-input border border-border-subtle bg-surface-elevated px-3 py-2 text-sm text-text-base">
                  <span>{t('inspector.interactionPreventDefault', 'Prevent Default')}</span>
                  <input
                    type="checkbox"
                    checked={rule.preventDefault ?? true}
                    onChange={(event) =>
                      updateRule(index, { preventDefault: event.currentTarget.checked })
                    }
                    class="h-4 w-4 rounded border-border-base text-accent-base"
                  />
                </label>
                <label class="flex items-center justify-between rounded-input border border-border-subtle bg-surface-elevated px-3 py-2 text-sm text-text-base">
                  <span>{t('inspector.interactionStopPropagation', 'Stop Propagation')}</span>
                  <input
                    type="checkbox"
                    checked={rule.stopPropagation ?? false}
                    onChange={(event) =>
                      updateRule(index, { stopPropagation: event.currentTarget.checked })
                    }
                    class="h-4 w-4 rounded border-border-base text-accent-base"
                  />
                </label>
              </div>
            </div>
          </div>
        ))}
      </div>

      <button
        type="button"
        onClick={() =>
          updateRules([
            ...interactions,
            {
              event: 'click',
              action: 'overlay.open',
              target: '',
              delay: 0,
              debounce: 0,
              throttle: 0,
              once: false,
              preventDefault: true,
              stopPropagation: false,
              device: 'any',
              loginState: 'any',
            },
          ])
        }
        class="w-full rounded-button bg-surface-elevated px-3 py-2 text-xs font-medium text-text-base hover:bg-surface-overlay"
      >
        {t('inspector.addInteraction', 'Add Interaction')}
      </button>
    </section>
  );
};

interface FlexLayoutPanelProps {
  node: BuilderNode;
  onUpdate: (props: Record<string, unknown>) => void;
}

interface GridItemPanelProps {
  document: BuilderDocument;
  node: BuilderNode;
  onMove: (direction: MoveDirection) => void;
}

interface GridItemInfo {
  kind: 'grid' | 'columns' | 'rows';
  row: number;
  column: number;
  rows: number;
  columns: number;
}

const GRID_ITEM_MOVES: Array<{
  direction: MoveDirection;
  label: string;
  icon: string;
  className: string;
}> = [
  { direction: 'up', label: 'Move up', icon: '▲', className: 'col-start-2 row-start-1' },
  { direction: 'left', label: 'Move left', icon: '◀', className: 'col-start-1 row-start-2' },
  { direction: 'right', label: 'Move right', icon: '▶', className: 'col-start-3 row-start-2' },
  { direction: 'down', label: 'Move down', icon: '▼', className: 'col-start-2 row-start-3' },
];

const GridItemPanel: FunctionComponent<GridItemPanelProps> = ({ document, node, onMove }) => {
  const info = useMemo(() => gridItemInfo(document, node.id), [document, node.id]);
  if (!info) return null;

  const title =
    info.kind === 'grid' ? 'Grid Item' : info.kind === 'columns' ? 'Column Item' : 'Row Item';

  return (
    <section class="space-y-3 rounded-input border border-border-subtle bg-surface-base p-3">
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold text-text-faint">{title}</span>
        <span class="text-xs text-text-faint">
          R{info.row} C{info.column}
        </span>
      </div>
      <div class="grid grid-cols-[2.25rem_2.25rem_2.25rem] grid-rows-[2.25rem_2.25rem_2.25rem] justify-center gap-1">
        {GRID_ITEM_MOVES.map((move) => {
          const disabled = gridMoveTargetIndex(info, move.direction) === null;
          return (
            <button
              key={move.direction}
              type="button"
              onClick={() => onMove(move.direction)}
              disabled={disabled}
              title={move.label}
              aria-label={move.label}
              class={`${move.className} flex h-9 w-9 items-center justify-center rounded-input border text-xs transition-colors ${
                disabled
                  ? 'cursor-not-allowed border-border-subtle bg-surface-elevated text-text-faint opacity-50'
                  : 'border-border-base bg-surface-elevated text-text-base hover:border-accent-base hover:bg-accent-subtle hover:text-accent-text'
              }`}
            >
              {move.icon}
            </button>
          );
        })}
        <div
          class="col-start-2 row-start-2 flex h-9 w-9 items-center justify-center rounded-input border border-dashed border-border-base bg-surface-elevated"
          aria-hidden="true"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-text-faint" />
        </div>
      </div>
    </section>
  );
};

interface BoxModelPanelProps {
  node: BuilderNode;
  onUpdate: (props: Record<string, unknown>) => void;
}

interface ParsedBoxValue {
  value: string;
  unit: BoxModelUnit;
}

const BoxModelPanel: FunctionComponent<BoxModelPanelProps> = ({ node, onUpdate }) => {
  const responsiveBreakpoint = useUiStore((s) => s.responsiveBreakpoint);
  const activeClasses = useMemo(() => classesForNode(node), [node]);
  const styleVars = useMemo(() => styleVarsForNode(node), [node]);

  const updateSide = (
    kind: BoxModelKind,
    side: BoxModelSide,
    nextValue: string,
    nextUnit: BoxModelUnit
  ): void => {
    const variableName = boxModelVariableName(kind, side, responsiveBreakpoint);
    const className = boxModelClassName(kind, side, responsiveBreakpoint);
    const normalizedValue = normalizeBoxModelNumber(nextValue);
    const nextVars = { ...styleVars };
    const nextClasses = activeClasses.filter((currentClass) => currentClass !== className);

    if (normalizedValue === '') {
      delete nextVars[variableName];
      onUpdate({ twClasses: nextClasses, twStyleVars: nextVars });
      return;
    }

    nextVars[variableName] = `${normalizedValue}${nextUnit}`;
    onUpdate({ twClasses: [...new Set([...nextClasses, className])], twStyleVars: nextVars });
  };

  const clearKind = (kind: BoxModelKind): void => {
    const classNames = new Set(
      BOX_MODEL_SIDES.map(({ side }) => boxModelClassName(kind, side, responsiveBreakpoint))
    );
    const nextVars = { ...styleVars };
    BOX_MODEL_SIDES.forEach(
      ({ side }) => delete nextVars[boxModelVariableName(kind, side, responsiveBreakpoint)]
    );
    onUpdate({
      twClasses: activeClasses.filter((className) => !classNames.has(className)),
      twStyleVars: nextVars,
    });
  };

  const hasAnySpacing = BOX_MODEL_SIDES.some(({ side }) => {
    return (
      boxModelVariableName('margin', side, responsiveBreakpoint) in styleVars ||
      boxModelVariableName('padding', side, responsiveBreakpoint) in styleVars
    );
  });

  return (
    <section class="space-y-3 rounded-input border border-border-subtle bg-surface-base p-3">
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold text-text-faint">Box Model</span>
        {hasAnySpacing && (
          <button
            type="button"
            onClick={() => {
              const classNames = new Set(
                (['margin', 'padding'] as BoxModelKind[]).flatMap((kind) =>
                  BOX_MODEL_SIDES.map(({ side }) =>
                    boxModelClassName(kind, side, responsiveBreakpoint)
                  )
                )
              );
              const nextVars = { ...styleVars };
              BOX_MODEL_SIDES.forEach(({ side }) => {
                delete nextVars[boxModelVariableName('margin', side, responsiveBreakpoint)];
                delete nextVars[boxModelVariableName('padding', side, responsiveBreakpoint)];
              });
              onUpdate({
                twClasses: activeClasses.filter((className) => !classNames.has(className)),
                twStyleVars: nextVars,
              });
            }}
            class="rounded-input px-2 py-1 text-xs font-medium text-feedback-danger hover:bg-surface-overlay"
          >
            Clear
          </button>
        )}
      </div>

      <div class="space-y-4">
        <BoxModelEditor
          kind="margin"
          title="Margin"
          breakpoint={responsiveBreakpoint}
          styleVars={styleVars}
          onClear={() => clearKind('margin')}
          onChange={(side, value, unit) => updateSide('margin', side, value, unit)}
        />
        <BoxModelEditor
          kind="padding"
          title="Padding"
          breakpoint={responsiveBreakpoint}
          styleVars={styleVars}
          onClear={() => clearKind('padding')}
          onChange={(side, value, unit) => updateSide('padding', side, value, unit)}
        />
      </div>
    </section>
  );
};

interface BoxModelEditorProps {
  kind: BoxModelKind;
  title: string;
  breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  styleVars: Record<string, string>;
  onClear: () => void;
  onChange: (side: BoxModelSide, value: string, unit: BoxModelUnit) => void;
}

const BoxModelEditor: FunctionComponent<BoxModelEditorProps> = ({
  kind,
  title,
  breakpoint,
  styleVars,
  onClear,
  onChange,
}) => {
  const values = Object.fromEntries(
    BOX_MODEL_SIDES.map(({ side }) => [
      side,
      parseBoxModelValue(styleVars[boxModelVariableName(kind, side, breakpoint)]),
    ])
  ) as Record<BoxModelSide, ParsedBoxValue>;
  const hasValues = BOX_MODEL_SIDES.some(({ side }) => values[side].value !== '');

  return (
    <details open={hasValues} class="rounded-input border border-border-subtle bg-surface-elevated">
      <summary class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-xs font-medium text-text-muted">
        <span>{title}</span>
        {hasValues && <span class="text-text-faint">Custom</span>}
      </summary>
      <div class="space-y-2 border-t border-border-subtle p-2">
        {hasValues && (
          <div class="flex justify-end">
            <button
              type="button"
              onClick={onClear}
              class="rounded-input px-2 py-1 text-xs font-medium text-text-faint hover:bg-surface-overlay hover:text-feedback-danger"
            >
              Reset
            </button>
          </div>
        )}
        <div class="grid grid-cols-[1fr_4.75rem_1fr] grid-rows-[auto_auto_auto] gap-2 rounded-input border border-border-subtle bg-surface-elevated p-2">
          <div class="col-start-2 row-start-1">
            <BoxSideInput
              side="top"
              config={BOX_MODEL_SIDES[0]}
              state={values.top}
              onChange={onChange}
            />
          </div>
          <div class="col-start-1 row-start-2 flex items-center">
            <BoxSideInput
              side="left"
              config={BOX_MODEL_SIDES[3]}
              state={values.left}
              onChange={onChange}
            />
          </div>
          <div class="col-start-2 row-start-2 flex min-h-16 items-center justify-center rounded-input border border-dashed border-border-base bg-surface-base text-[11px] font-medium text-text-faint">
            {title}
          </div>
          <div class="col-start-3 row-start-2 flex items-center">
            <BoxSideInput
              side="right"
              config={BOX_MODEL_SIDES[1]}
              state={values.right}
              onChange={onChange}
            />
          </div>
          <div class="col-start-2 row-start-3">
            <BoxSideInput
              side="bottom"
              config={BOX_MODEL_SIDES[2]}
              state={values.bottom}
              onChange={onChange}
            />
          </div>
        </div>
      </div>
    </details>
  );
};

interface BoxSideInputProps {
  side: BoxModelSide;
  config: BoxModelSideConfig | undefined;
  state: ParsedBoxValue;
  onChange: (side: BoxModelSide, value: string, unit: BoxModelUnit) => void;
}

const BoxSideInput: FunctionComponent<BoxSideInputProps> = ({ side, config, state, onChange }) => (
  <label class="flex w-full flex-col gap-1" title={config?.label ?? side}>
    <span class="sr-only">{config?.label ?? side}</span>
    <input
      type="number"
      inputMode="decimal"
      value={state.value}
      onInput={(event) => onChange(side, (event.target as HTMLInputElement).value, state.unit)}
      class="w-full min-w-0 rounded-input border border-border-base bg-surface-base px-2 py-1 text-center text-xs text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none"
      placeholder={config?.shortLabel ?? ''}
    />
    <select
      value={state.unit}
      onChange={(event) =>
        onChange(side, state.value, (event.target as HTMLSelectElement).value as BoxModelUnit)
      }
      class="w-full rounded-input border border-border-base bg-surface-base px-1 py-1 text-center text-xs text-text-muted focus:border-accent-base focus:outline-none"
      aria-label={`${config?.label ?? side} unit`}
    >
      {BOX_MODEL_UNITS.map((unit) => (
        <option key={unit} value={unit}>
          {unit}
        </option>
      ))}
    </select>
  </label>
);

const FlexLayoutPanel: FunctionComponent<FlexLayoutPanelProps> = ({ node, onUpdate }) => {
  const activeClasses = useMemo(() => classesForNode(node), [node]);
  const isFlexEnabled = hasFlexDisplayClass(activeClasses);

  const updateClasses = (classes: string[]): void => {
    onUpdate({ twClasses: classes });
  };

  const applyClassGroup = (group: FlexLayoutGroup, className: string): void => {
    const groupValues = new Set(group.options.map((option) => option.value));
    const nextClasses = activeClasses.filter((currentClass) => {
      const baseClassName = baseClassForLayoutPanel(currentClass);
      return !baseClassName || !groupValues.has(baseClassName);
    });

    if (group.id !== 'display' && !hasFlexDisplayClass(nextClasses)) {
      nextClasses.push('flex');
    }

    nextClasses.push(className);
    updateClasses([...new Set(nextClasses)]);
  };

  const clearFlexLayout = (): void => {
    updateClasses(
      activeClasses.filter((className) => {
        const baseClassName = baseClassForLayoutPanel(className);
        return !baseClassName || !FLEX_MANAGED_BASE_CLASSES.has(baseClassName);
      })
    );
  };

  return (
    <section class="space-y-3 rounded-input border border-border-subtle bg-surface-base p-3">
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-semibold text-text-faint">Flex Layout</span>
        {isFlexEnabled && (
          <button
            type="button"
            onClick={clearFlexLayout}
            class="rounded-input px-2 py-1 text-xs font-medium text-feedback-danger hover:bg-surface-overlay"
          >
            Clear
          </button>
        )}
      </div>

      {!isFlexEnabled ? (
        <button
          type="button"
          onClick={() => applyClassGroup(FLEX_DISPLAY_GROUP, 'flex')}
          class="flex w-full items-center justify-center gap-2 rounded-button border border-border-base bg-surface-elevated px-3 py-2 text-xs font-medium text-text-base hover:border-accent-base hover:bg-accent-subtle"
        >
          <LayoutMiniIcon kind="direction" value="flex-row" />
          <span>Enable Flex</span>
        </button>
      ) : (
        <div class="space-y-3">
          {FLEX_LAYOUT_GROUPS.map((group) => (
            <VisualOptionControl
              key={group.id}
              label={group.label}
              value={activeValueForFlexGroup(activeClasses, group)}
              options={group.options}
              onChange={(className) => applyClassGroup(group, String(className))}
            />
          ))}
        </div>
      )}
    </section>
  );
};

function gridItemInfo(document: BuilderDocument, nodeId: string): GridItemInfo | null {
  for (const parent of Object.values(document.nodes)) {
    if (parent.type !== 'bky/grid' && parent.type !== 'bky/columns' && parent.type !== 'bky/rows')
      continue;
    for (const children of Object.values(parent.slots)) {
      const index = (children ?? []).indexOf(nodeId);
      if (index === -1) continue;
      if (parent.type === 'bky/columns' || parent.type === 'bky/rows') {
        const slotName =
          Object.entries(parent.slots).find(([, slotChildren]) =>
            (slotChildren ?? []).includes(nodeId)
          )?.[0] ?? '';
        const slotIndex = structuredSlotIndex(parent.type, slotName);
        if (slotIndex === null) return null;

        return parent.type === 'bky/columns'
          ? {
              kind: 'columns',
              row: index + 1,
              column: slotIndex + 1,
              rows: children.length,
              columns: structuredSlotCount(parent, 'column-'),
            }
          : {
              kind: 'rows',
              row: slotIndex + 1,
              column: index + 1,
              rows: structuredSlotCount(parent, 'row-'),
              columns: children.length,
            };
      }
      const columns = gridColumnCountForNode(parent);
      const position = gridPositionForNode(document.nodes[nodeId], index, columns);
      const positions = gridChildPositionsForInspector(document, children, columns);
      return {
        kind: 'grid',
        row: position.row,
        column: position.column,
        rows: gridRowCountForNode(parent, children.length, positions),
        columns,
      };
    }
  }
  return null;
}

function gridMoveTargetIndex(info: GridItemInfo, direction: MoveDirection): number | null {
  if (info.kind === 'columns') {
    if (direction === 'left') return info.column > 1 ? 1 : null;
    if (direction === 'right') return info.column < info.columns ? 1 : null;
    if (direction === 'up') return info.row > 1 ? 1 : null;
    if (direction === 'down') return info.row < info.rows ? 1 : null;
  }

  if (info.kind === 'rows') {
    if (direction === 'up') return info.row > 1 ? 1 : null;
    if (direction === 'down') return info.row < info.rows ? 1 : null;
    if (direction === 'left') return info.column > 1 ? 1 : null;
    if (direction === 'right') return info.column < info.columns ? 1 : null;
  }

  let row = info.row;
  let column = info.column;
  if (direction === 'left') column -= 1;
  if (direction === 'right') column += 1;
  if (direction === 'up') row -= 1;
  if (direction === 'down') row += 1;

  return column >= 1 && column <= info.columns && row >= 1 && row <= info.rows ? 1 : null;
}

function structuredSlotIndex(type: 'bky/columns' | 'bky/rows', slotName: string): number | null {
  const prefix = type === 'bky/columns' ? 'column-' : 'row-';
  if (!slotName.startsWith(prefix)) return null;
  const rawIndex = Number(slotName.slice(prefix.length));
  if (!Number.isInteger(rawIndex) || rawIndex < 1) return null;
  return rawIndex - 1;
}

function structuredSlotCount(node: BuilderNode, prefix: 'column-' | 'row-'): number {
  return Object.keys(node.slots).filter((slotName) => slotName.startsWith(prefix)).length;
}

function gridColumnCountForNode(node: BuilderNode): number {
  return boundedInteger(node.props['columns'] ?? node.variants['columns'], 3, 1, 12);
}

function gridRowCountForNode(
  node: BuilderNode,
  childCount: number,
  positions: Map<string, GridItemPosition>
): number {
  const configuredRows = boundedInteger(node.props['rows'] ?? node.variants['rows'], 1, 1, 12);
  const implicitRows = Math.max(1, Math.ceil(childCount / gridColumnCountForNode(node)));
  const placedRows = Math.max(1, ...Array.from(positions.values(), (position) => position.row));
  return Math.max(configuredRows, implicitRows, placedRows);
}

interface GridItemPosition {
  row: number;
  column: number;
}

function gridChildPositionsForInspector(
  document: BuilderDocument,
  children: string[],
  columns: number
): Map<string, GridItemPosition> {
  const positions = new Map<string, GridItemPosition>();
  children.forEach((childId, index) => {
    positions.set(childId, gridPositionForNode(document.nodes[childId], index, columns));
  });
  return positions;
}

function gridPositionForNode(
  node: BuilderNode | undefined,
  index: number,
  columns: number
): GridItemPosition {
  const fallback = {
    column: (index % columns) + 1,
    row: Math.floor(index / columns) + 1,
  };
  if (!node) return fallback;
  return {
    column: boundedInteger(node.props['gridColumnStart'], fallback.column, 1, columns),
    row: boundedInteger(node.props['gridRowStart'], fallback.row, 1, 12),
  };
}

function boundedInteger(value: unknown, fallback: number, min: number, max: number): number {
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return fallback;
  return Math.max(min, Math.min(max, Math.trunc(numberValue)));
}

function classesForNode(node: BuilderNode): string[] {
  const rawClasses = node.props['twClasses'];
  if (typeof rawClasses === 'string') {
    return rawClasses
      .split(/\s+/)
      .map((className) => className.trim())
      .filter(Boolean);
  }
  if (Array.isArray(rawClasses)) {
    return rawClasses.filter(
      (className): className is string => typeof className === 'string' && className.trim() !== ''
    );
  }
  return [];
}

function styleVarsForNode(node: BuilderNode): Record<string, string> {
  const rawVars = node.props['twStyleVars'];
  if (!rawVars || typeof rawVars !== 'object' || Array.isArray(rawVars)) return {};
  return Object.fromEntries(
    Object.entries(rawVars).filter((entry): entry is [string, string] => {
      const [name, value] = entry;
      return (
        /^--bky-[a-z0-9-]+$/.test(name) &&
        typeof value === 'string' &&
        normalizeStyleVarValue(name, value) !== null
      );
    })
  );
}

function normalizeStyleVarValue(name: string, value: string): string | null {
  if (/^--bky-space-(?:[a-z0-9]+-)?(?:margin|padding)-(?:top|right|bottom|left)$/.test(name)) {
    return normalizeCssLength(value);
  }
  if (/^--bky-tw-[a-z0-9-]+-background-gradient$/.test(name)) {
    return normalizeLinearGradientArgs(value);
  }
  if (/^--bky-tw-[a-z0-9-]+-background-image-url$/.test(name)) {
    return normalizeBackgroundImageUrl(value);
  }
  if (/^--bky-tw-[a-z0-9-]+-background-position-[xy]$/.test(name)) {
    return normalizeBackgroundPositionValue(value);
  }
  return null;
}

function boxModelVariableName(
  kind: BoxModelKind,
  side: BoxModelSide,
  breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' = 'base'
): string {
  return breakpoint === 'base'
    ? `${BOX_MODEL_VAR_PREFIX[kind]}-${side}`
    : `${BOX_MODEL_VAR_PREFIX[kind].replace('--bky-space-', `--bky-space-${breakpoint}-`)}-${side}`;
}

function boxModelClassName(
  kind: BoxModelKind,
  side: BoxModelSide,
  breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' = 'base'
): string {
  return composeUtilityClass(
    breakpointPrefix(breakpoint),
    `${BOX_MODEL_CLASS_PREFIX[kind][side]}-[var(${boxModelVariableName(kind, side, breakpoint)})]`,
    false
  );
}

function parseBoxModelValue(value: string | undefined): ParsedBoxValue {
  const normalizedValue = normalizeCssLength(value ?? '');
  if (!normalizedValue) return { value: '', unit: 'px' };
  const match = /^(-?(?:\d+|\d*\.\d+))(px|rem|em|%|vh|vw)$/.exec(normalizedValue);
  if (!match) return { value: '', unit: 'px' };
  return { value: match[1] ?? '', unit: (match[2] ?? 'px') as BoxModelUnit };
}

function normalizeBoxModelNumber(value: string): string {
  const normalized = value.trim().replace(',', '.');
  if (!/^-?(?:\d+|\d*\.\d+)$/.test(normalized)) return '';
  return normalized;
}

function normalizeCssLength(value: string): string | null {
  const normalized = value.trim().replace(',', '.');
  return /^-?(?:\d+|\d*\.\d+)(?:px|rem|em|%|vh|vw)$/.test(normalized) ? normalized : null;
}

function shouldShowFlexLayoutPanel(node: BuilderNode, activeClasses: string[]): boolean {
  return (
    FLEX_LAYOUT_NODE_TYPES.has(node.type) ||
    activeClasses.some((className) => {
      const baseClassName = baseClassForLayoutPanel(className);
      return baseClassName !== null && FLEX_MANAGED_BASE_CLASSES.has(baseClassName);
    })
  );
}

function activeValueForFlexGroup(activeClasses: string[], group: FlexLayoutGroup): string {
  const activeBaseClasses = activeClasses
    .map(baseClassForLayoutPanel)
    .filter((className): className is string => className !== null);
  const activeOption = group.options.find((option) => activeBaseClasses.includes(option.value));
  return activeOption?.value ?? group.defaultValue;
}

function hasFlexDisplayClass(activeClasses: string[]): boolean {
  return activeClasses.some((className) => {
    const baseClassName = baseClassForLayoutPanel(className);
    return baseClassName === 'flex' || baseClassName === 'inline-flex';
  });
}

function baseClassForLayoutPanel(className: string): string | null {
  if (className.includes(':')) return null;
  return baseUtilityClass(className);
}

function colorVarsForNode(node: BuilderNode): Record<string, string> {
  const rawVars = node.props['twColorVars'];
  if (!rawVars || typeof rawVars !== 'object' || Array.isArray(rawVars)) return {};
  return Object.fromEntries(
    Object.entries(rawVars).filter((entry): entry is [string, string] => {
      const [name, value] = entry;
      return (
        /^--bky-tw-[a-z0-9-]+$/.test(name) &&
        typeof value === 'string' &&
        normalizeHexColor(value) !== null
      );
    })
  );
}

function isStyleColorControl(control: BlockControl): boolean {
  return control.type === 'variant' && styleColorRoleForControl(control) !== null;
}

function styleColorRoleForControl(control: BlockControl): StyleColorRoleConfig | null {
  const roleId = STYLE_COLOR_CONTROL_ROLES[control.variantKey ?? control.id];
  return roleId ? (STYLE_COLOR_ROLES[roleId] ?? null) : null;
}

function responsiveStyleRole(
  role: StyleColorRoleConfig,
  breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
): StyleColorRoleConfig {
  if (breakpoint === 'base') return role;

  const replaceScope = (value: string | undefined): string | undefined =>
    value?.replace('-base-', `-${breakpoint}-`);

  return {
    ...role,
    variableName: replaceScope(role.variableName) ?? role.variableName,
    gradientVariableName: replaceScope(role.gradientVariableName),
    imageUrlVariableName: replaceScope(role.imageUrlVariableName),
    imagePositionXVariableName: replaceScope(role.imagePositionXVariableName),
    imagePositionYVariableName: replaceScope(role.imagePositionYVariableName),
  };
}

function customColorPropsForNode(
  node: BuilderNode,
  role: StyleColorRoleConfig,
  color: string,
  breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
): Record<string, unknown> {
  const nextStyleVars = { ...styleVarsForNode(node) };
  if (role.gradientVariableName) delete nextStyleVars[role.gradientVariableName];
  return {
    twClasses: [
      ...new Set([
        ...removeStyleColorRuntimeClasses(classesForNode(node), role),
        customColorClassName(role, breakpoint),
      ]),
    ],
    twColorVars: { ...colorVarsForNode(node), [role.variableName]: color },
    twStyleVars: nextStyleVars,
  };
}

function customGradientPropsForNode(
  node: BuilderNode,
  role: StyleColorRoleConfig,
  gradient: string,
  breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
): Record<string, unknown> {
  const nextColorVars = { ...colorVarsForNode(node) };
  delete nextColorVars[role.variableName];

  if (!role.gradientVariableName) {
    return {
      twClasses: removeStyleColorRuntimeClasses(classesForNode(node), role),
      twColorVars: nextColorVars,
    };
  }

  return {
    twClasses: [
      ...new Set([
        ...removeStyleColorRuntimeClasses(classesForNode(node), role),
        customGradientClassName(role, breakpoint),
      ]),
    ],
    twColorVars: nextColorVars,
    twStyleVars: { ...styleVarsForNode(node), [role.gradientVariableName]: gradient },
  };
}

function clearStyleColorPropsForNode(
  node: BuilderNode,
  role: StyleColorRoleConfig
): Record<string, unknown> {
  const nextColorVars = { ...colorVarsForNode(node) };
  delete nextColorVars[role.variableName];
  const nextStyleVars = { ...styleVarsForNode(node) };
  if (role.gradientVariableName) delete nextStyleVars[role.gradientVariableName];
  if (role.imageUrlVariableName) delete nextStyleVars[role.imageUrlVariableName];
  if (role.imagePositionXVariableName) delete nextStyleVars[role.imagePositionXVariableName];
  if (role.imagePositionYVariableName) delete nextStyleVars[role.imagePositionYVariableName];
  return {
    twClasses: removeStyleColorRuntimeClasses(classesForNode(node), role),
    twColorVars: nextColorVars,
    twStyleVars: nextStyleVars,
  };
}

function removeStyleColorRuntimeClasses(classes: string[], role: StyleColorRoleConfig): string[] {
  return classes.filter(
    (className) => !isCustomColorClass(className, role) && !isCustomGradientClass(className, role)
  );
}

function isCustomColorClass(className: string, role: StyleColorRoleConfig): boolean {
  return className.includes(`var(${role.variableName})`);
}

function isCustomGradientClass(className: string, role: StyleColorRoleConfig): boolean {
  return !!role.gradientVariableName && className.includes(`var(${role.gradientVariableName})`);
}

function customColorClassName(
  role: StyleColorRoleConfig,
  breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
): string {
  return composeUtilityClass(
    breakpointPrefix(breakpoint),
    `${role.utility}-[var(${role.variableName})]`,
    true
  );
}

function customGradientClassName(
  role: StyleColorRoleConfig,
  breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'
): string {
  if (!role.gradientVariableName) return '';
  return composeUtilityClass(
    breakpointPrefix(breakpoint),
    `bg-[linear-gradient(var(${role.gradientVariableName}))]`,
    true
  );
}

function customBackgroundImagePropsForNode(
  node: BuilderNode,
  role: StyleColorRoleConfig,
  imageUrl: string,
  _breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl',
  focalX = 50,
  focalY = 50
): Record<string, unknown> {
  const nextColorVars = { ...colorVarsForNode(node) };
  delete nextColorVars[role.variableName];
  const nextStyleVars = { ...styleVarsForNode(node) };
  if (role.gradientVariableName) delete nextStyleVars[role.gradientVariableName];
  if (role.imageUrlVariableName) nextStyleVars[role.imageUrlVariableName] = imageUrl;
  if (role.imagePositionXVariableName)
    nextStyleVars[role.imagePositionXVariableName] = `${percentageProp(focalX, 50)}%`;
  if (role.imagePositionYVariableName)
    nextStyleVars[role.imagePositionYVariableName] = `${percentageProp(focalY, 50)}%`;
  return {
    twClasses: removeStyleColorRuntimeClasses(classesForNode(node), role),
    twColorVars: nextColorVars,
    twStyleVars: nextStyleVars,
  };
}

function normalizeHexColor(value: string): string | null {
  const normalized = value.trim().startsWith('#') ? value.trim() : `#${value.trim()}`;
  return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(normalized)
    ? normalized
    : null;
}

function normalizeGradientAngle(value: string): string | null {
  const normalized = value.trim();
  if (!/^\d{1,3}$/.test(normalized)) return null;
  const angle = Number.parseInt(normalized, 10);
  return angle >= 0 && angle <= 360 ? String(angle) : null;
}

function normalizeLinearGradientArgs(value: string): string | null {
  const normalized = value.trim().replace(/\s+/g, '');
  const match = /^(\d{1,3})deg,([^,]+),([^,]+)$/.exec(normalized);
  if (!match) return null;

  const angle = normalizeGradientAngle(match[1] ?? '');
  const from = normalizeHexColor(match[2] ?? '');
  const to = normalizeHexColor(match[3] ?? '');
  if (!angle || !from || !to) return null;

  return `${angle}deg,${from},${to}`;
}

function parseGradientValue(value: string): StyleGradientDraft | null {
  const normalized = normalizeLinearGradientArgs(value);
  if (!normalized) return null;

  const [anglePart = '', from = '', to = ''] = normalized.split(',');
  return {
    angle: anglePart.slice(0, -3),
    from,
    to,
  };
}

function normalizeBackgroundImageUrl(value: string): string | null {
  const normalized = value.trim();
  return /^(https?:\/\/|\/)[^\s"'()<>\\]+$/i.test(normalized) ? normalized : null;
}

function parseBackgroundImageUrl(value: string): string {
  return normalizeBackgroundImageUrl(value) ?? '';
}

function normalizeBackgroundPositionValue(value: string): string | null {
  const rawValue = String(value).trim().replace(/%$/, '');
  const numericValue = Number(rawValue);
  if (!Number.isFinite(numericValue)) return null;
  return `${percentageProp(numericValue, 50)}%`;
}

function parseBackgroundPositionValue(value: string): number {
  const normalized = normalizeBackgroundPositionValue(value);
  if (!normalized) return 50;
  return percentageProp(normalized.slice(0, -1), 50);
}

function gradientValueFromDraft(draft: StyleGradientDraft): string | null {
  const angle = normalizeGradientAngle(draft.angle);
  const from = normalizeHexColor(draft.from);
  const to = normalizeHexColor(draft.to);
  if (!angle || !from || !to) return null;

  return `${angle}deg,${from},${to}`;
}

function colorPickerValue(value: string): string {
  const normalized = normalizeHexColor(value);
  if (!normalized) return CUSTOM_COLOR_DEFAULT;

  const hex = normalized.slice(1);
  if (hex.length === 3 || hex.length === 4) {
    return `#${hex
      .slice(0, 3)
      .split('')
      .map((char) => `${char}${char}`)
      .join('')}`;
  }
  if (hex.length === 8) return `#${hex.slice(0, 6)}`;
  return normalized;
}

function variableNameFromUtilityClass(className: string): string | null {
  const match = /var\((--bky-tw-[a-z0-9-]+)\)/.exec(className);
  return match?.[1] ?? null;
}

function normalizePrefix(prefix: string): string {
  const normalized = prefix.trim().replace(/\s+/g, '');
  if (normalized === '') return '';
  return normalized.endsWith(':') ? normalized : `${normalized}:`;
}

function breakpointScopeValue(breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'): string {
  return breakpoint === 'base' ? '' : breakpoint;
}

function breakpointPrefix(breakpoint: 'base' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'): string {
  return normalizePrefix(breakpointScopeValue(breakpoint));
}

function composeUtilityClass(prefix: string, className: string, forceOverride: boolean): string {
  const utilityClass = `${prefix}${className}`;
  if (!forceOverride || utilityClass.includes(':!') || utilityClass.startsWith('!'))
    return utilityClass;

  const lastVariantSeparator = utilityClass.lastIndexOf(':');
  if (lastVariantSeparator === -1) return `!${utilityClass}`;
  return `${utilityClass.slice(0, lastVariantSeparator + 1)}!${utilityClass.slice(lastVariantSeparator + 1)}`;
}

function composeMotionClass(guard: string, className: string): string {
  return guard ? `${guard}:${className}` : className;
}

function isMotionTimingClass(className: string): boolean {
  const baseClassName = baseUtilityClass(className);
  return (
    MOTION_TRANSITION_VALUES.has(baseClassName) ||
    MOTION_EASING_VALUES.has(baseClassName) ||
    /^duration-\d+$/.test(baseClassName) ||
    /^delay-\d+$/.test(baseClassName)
  );
}

function variantPrefixesForClass(className: string): string[] {
  const parts = className.trim().split(':');
  return parts.slice(0, -1).map((part) => part.replace(/^!/, ''));
}

function isHoverAnimationClass(className: string, allowedValues: Set<string>): boolean {
  return (
    variantPrefixesForClass(className).includes('hover') &&
    allowedValues.has(baseUtilityClass(className))
  );
}

function activeHoverAnimationValue(activeClasses: string[], allowedValues: Set<string>): string {
  const activeClass = activeClasses.find((className) =>
    isHoverAnimationClass(className, allowedValues)
  );
  return activeClass ? baseUtilityClass(activeClass) : '';
}

function isManagedAnimationClass(className: string): boolean {
  return (
    isMotionTimingClass(className) ||
    isHoverAnimationClass(className, HOVER_SCALE_VALUES) ||
    isHoverAnimationClass(className, HOVER_OPACITY_VALUES)
  );
}

function baseUtilityClass(className: string): string {
  const parts = className.trim().split(':');
  return (parts[parts.length - 1] ?? className).replace(/^!/, '');
}

function tabsForDefinition(def: BlockDefinition): InspectorTab[] {
  if (def.editorConfig?.tabs?.length) {
    return def.editorConfig.tabs.map((tab) => ({
      id: tab.id,
      label: tab.label,
      controls: tab.controls.map((control) => normalizeControl(control, def)),
    }));
  }

  const controls = controlsForDefinition(def);
  const grouped = new Map<string, BlockControl[]>();
  controls.forEach((control) => {
    const tabId = control.tab ?? tabForControl(control.id);
    grouped.set(tabId, [...(grouped.get(tabId) ?? []), control]);
  });

  return Array.from(grouped.entries()).map(([id, controls]) => ({
    id,
    label: tabLabel(id),
    controls,
  }));
}

function ensureFixedInspectorTabs(tabs: InspectorTab[]): InspectorTab[] {
  return orderInspectorTabs(
    ensureAdvancedTab(ensureAnimationsTab(ensureStyleTab(ensureLayoutTab(ensureContentTab(tabs)))))
  );
}

function ensureContentTab(tabs: InspectorTab[]): InspectorTab[] {
  if (tabs.some((tab) => tab.id === 'content')) return tabs;
  return [{ id: 'content', label: t('inspector.contentTab', 'Content'), controls: [] }, ...tabs];
}

function ensureLayoutTab(tabs: InspectorTab[]): InspectorTab[] {
  if (tabs.some((tab) => tab.id === 'layout')) return tabs;
  const classesIndex = tabs.findIndex((tab) => tab.id === 'classes');
  const layoutTab: InspectorTab = {
    id: 'layout',
    label: t('inspector.layoutTab', 'Layout'),
    controls: [],
  };
  if (classesIndex === -1) return [...tabs, layoutTab];
  return [...tabs.slice(0, classesIndex), layoutTab, ...tabs.slice(classesIndex)];
}

function ensureStyleTab(tabs: InspectorTab[]): InspectorTab[] {
  if (tabs.some((tab) => tab.id === 'style')) return tabs;
  const classesIndex = tabs.findIndex((tab) => tab.id === 'classes');
  const styleTab: InspectorTab = {
    id: 'style',
    label: t('inspector.styleTab', 'Style'),
    controls: [],
  };
  if (classesIndex === -1) return [...tabs, styleTab];
  return [...tabs.slice(0, classesIndex), styleTab, ...tabs.slice(classesIndex)];
}

function ensureAdvancedTab(tabs: InspectorTab[]): InspectorTab[] {
  if (tabs.some((tab) => tab.id === 'advanced')) return tabs;
  return [...tabs, { id: 'advanced', label: t('inspector.advancedTab', 'Advanced'), controls: [] }];
}

function ensureAnimationsTab(tabs: InspectorTab[]): InspectorTab[] {
  if (tabs.some((tab) => tab.id === 'animations')) return tabs;
  const classesIndex = tabs.findIndex((tab) => tab.id === 'classes');
  const animationsTab: InspectorTab = {
    id: 'animations',
    label: t('inspector.animationsTab', 'Animations'),
    controls: [],
  };
  if (classesIndex === -1) return [...tabs, animationsTab];
  return [...tabs.slice(0, classesIndex), animationsTab, ...tabs.slice(classesIndex)];
}

function orderInspectorTabs(tabs: InspectorTab[]): InspectorTab[] {
  const tabOrder: Record<string, number> = {
    content: 0,
    layout: 1,
    style: 2,
    animations: 3,
    advanced: 4,
    classes: 5,
  };

  return tabs
    .map((tab, index) => ({ tab, index }))
    .sort((left, right) => {
      const leftOrder = tabOrder[left.tab.id] ?? 100 + left.index;
      const rightOrder = tabOrder[right.tab.id] ?? 100 + right.index;
      return leftOrder - rightOrder || left.index - right.index;
    })
    .map((entry) => entry.tab);
}

function controlsForDefinition(def: BlockDefinition): BlockControl[] {
  if (def.editorConfig?.controls?.length) {
    return def.editorConfig.controls.map((control) => normalizeControl(control, def));
  }

  const schema = def.schema as { properties?: Record<string, SchemaProperty> };
  return Object.entries(schema.properties ?? {}).map(([id, property]): BlockControl => {
    const variantOptions = def.variants?.[id];
    if (variantOptions) {
      return {
        id,
        type: 'variant',
        label: labelFromId(id),
        variantKey: id,
        options: Object.keys(variantOptions).map((option) => [option, labelFromId(option)]),
        tab: tabForControl(id),
      };
    }

    if (Array.isArray(property.enum)) {
      const control: BlockControl = {
        id,
        type: 'select',
        label: labelFromId(id),
        options: property.enum.map((option) => [String(option), labelFromId(String(option))]),
        tab: tabForControl(id),
      };
      if (property.enum.length > 0 && property.enum.every((option) => typeof option === 'number')) {
        control.numericEnum = true;
      }
      return control;
    }

    if (property.type === 'boolean') {
      return { id, type: 'toggle', label: labelFromId(id), tab: tabForControl(id) };
    }

    if (property.type === 'number' || property.type === 'integer') {
      const control: BlockControl = {
        id,
        type: 'number',
        label: labelFromId(id),
        tab: tabForControl(id),
        step: 1,
      };
      if (property.minimum !== undefined) control.min = property.minimum;
      if (property.maximum !== undefined) control.max = property.maximum;
      return control;
    }

    const control: BlockControl = {
      id,
      type: ['text', 'content', 'items', 'quote', 'html'].includes(id) ? 'richtext' : 'text',
      label: labelFromId(id),
      tab: tabForControl(id),
    };
    if (Array.isArray(property.listFields) && property.listFields.length > 0) {
      control.listFields = property.listFields;
    }
    return control;
  });
}

interface SchemaProperty {
  type?: string;
  enum?: unknown[];
  default?: unknown;
  minimum?: number;
  maximum?: number;
  listFields?: ListFieldDescriptor[];
}

export interface ListFieldDescriptor {
  key: string;
  label: string;
  kind?: 'text' | 'url' | 'image' | 'number';
}

type RawControl = NonNullable<BlockDefinition['editorConfig']['controls']>[number];

function normalizeControl(control: RawControl, def?: BlockDefinition): BlockControl {
  const type = isControlType(control.type) ? control.type : 'text';
  const normalized: BlockControl = {
    id: control.id,
    type,
    label: control.label,
  };
  if (control.variantKey) normalized.variantKey = control.variantKey;
  if (control.options) normalized.options = control.options;
  if (!normalized.options && type === 'variant') {
    const variantKey = normalized.variantKey ?? control.id;
    const variantOptions = def?.variants?.[variantKey];
    if (variantOptions) {
      normalized.options = Object.keys(variantOptions).map((option) => [
        option,
        labelFromId(option),
      ]);
    }
  }
  if (control.tab) normalized.tab = control.tab;
  if (control.min !== undefined) normalized.min = control.min;
  if (control.max !== undefined) normalized.max = control.max;
  // Descriptor-driven line-list editing works for editorConfig controls too:
  // the schema property owns listFields, so blocks stay declarative (rule 5).
  const schemaProperties = def?.schema as
    | { properties?: Record<string, SchemaProperty> }
    | undefined;
  const listFields = schemaProperties?.properties?.[control.id]?.listFields;
  if (Array.isArray(listFields) && listFields.length > 0) {
    normalized.listFields = listFields;
  }
  if (control.step !== undefined) normalized.step = control.step;
  if (control.mediaType) normalized.mediaType = control.mediaType;
  if (control.mediaReturn) normalized.mediaReturn = control.mediaReturn;
  return normalized;
}

function isLinkTargetControl(control: BlockControl): boolean {
  if (control.id !== 'target' || control.type !== 'select') return false;
  const options = control.options ?? [];
  return (
    options.length === 2 &&
    options.some(([value]) => String(value) === '_self') &&
    options.some(([value]) => String(value) === '_blank')
  );
}

function interactionsForNode(node: BuilderNode): InteractionRule[] {
  const rawInteractions = node.props['interactions'];
  if (!Array.isArray(rawInteractions)) return [];

  return rawInteractions.flatMap((entry): InteractionRule[] => {
    if (!entry || typeof entry !== 'object' || Array.isArray(entry)) return [];

    const event = stringProp(entry['event']);
    const action = stringProp(entry['action']);
    if (!isInteractionEvent(event) || !isInteractionAction(action)) return [];

    return [
      {
        event,
        action,
        target: stringProp(entry['target']),
        className: stringProp(entry['className']) || undefined,
        delay:
          typeof entry['delay'] === 'number' && Number.isFinite(entry['delay'])
            ? Math.max(0, Math.round(entry['delay']))
            : 0,
        debounce:
          typeof entry['debounce'] === 'number' && Number.isFinite(entry['debounce'])
            ? Math.max(0, Math.round(entry['debounce']))
            : 0,
        throttle:
          typeof entry['throttle'] === 'number' && Number.isFinite(entry['throttle'])
            ? Math.max(0, Math.round(entry['throttle']))
            : 0,
        once: entry['once'] === true,
        preventDefault: entry['preventDefault'] !== false,
        stopPropagation: entry['stopPropagation'] === true,
        device: isInteractionDevice(stringProp(entry['device'], 'any'))
          ? (stringProp(entry['device'], 'any') as InteractionDevice)
          : 'any',
        loginState: isInteractionLoginState(stringProp(entry['loginState'], 'any'))
          ? (stringProp(entry['loginState'], 'any') as InteractionLoginState)
          : 'any',
        queryKey: stringProp(entry['queryKey']) || undefined,
        queryValue: stringProp(entry['queryValue']) || undefined,
        cookieKey: stringProp(entry['cookieKey']) || undefined,
        cookieValue: stringProp(entry['cookieValue']) || undefined,
      },
    ];
  });
}

const INSPECTOR_OVERLAY_TYPES = new Set([
  'bky/modal',
  'bky/offcanvas',
  'bky/drawer',
  'bky/popover',
  'bky/tooltip',
  'bky/dialog-confirm',
  'bky/popup',
  'bky/notification-toast',
  'bky/cookie-banner',
  'bky/lightbox',
  'bky/command-palette',
]);

function overlayIdsForInspector(
  document: BuilderDocument | null,
  currentNodeId?: string
): string[] {
  if (!document) return [];

  return Array.from(
    new Set(
      Object.values(document.nodes)
        .filter((node) => INSPECTOR_OVERLAY_TYPES.has(node.type) && node.id !== currentNodeId)
        .map((node) => overlayIdForNode(node))
    )
  ).sort((left, right) => left.localeCompare(right));
}

function isInteractionEvent(value: string): value is InteractionEvent {
  return ['click', 'hover', 'focus', 'load'].includes(value);
}

function isInteractionAction(value: string): value is InteractionAction {
  return [
    'overlay.open',
    'overlay.close',
    'overlay.toggle',
    'class.add',
    'class.remove',
    'class.toggle',
    'custom.emit',
  ].includes(value);
}

function isInteractionDevice(value: string): value is InteractionDevice {
  return ['any', 'desktop', 'tablet', 'mobile'].includes(value);
}

function isInteractionLoginState(value: string): value is InteractionLoginState {
  return ['any', 'logged-in', 'logged-out'].includes(value);
}

function currentTargetValue(
  event: JSX.TargetedEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
): string {
  return event.currentTarget.value;
}

function normalizeInteractionDelay(value: string): number {
  const parsed = Number(value);
  if (!Number.isFinite(parsed) || parsed < 0) return 0;
  return Math.round(parsed);
}

function isControlType(type: string): type is BlockControl['type'] {
  return [
    'text',
    'richtext',
    'select',
    'toggle',
    'variant',
    'number',
    'range',
    'color',
    'media',
  ].includes(type);
}

function valueForControl(node: BuilderNode, def: BlockDefinition, control: BlockControl): unknown {
  if (control.type === 'variant') {
    return (
      node.variants[control.variantKey ?? control.id] ??
      node.props[control.id] ??
      defaultFor(def, control.id) ??
      ''
    );
  }
  return node.props[control.id] ?? defaultFor(def, control.id) ?? '';
}

function defaultFor(def: BlockDefinition, id: string): unknown {
  const schema = def.schema as { properties?: Record<string, SchemaProperty> };
  return schema.properties?.[id]?.default;
}

function tabForControl(id: string): string {
  if (
    [
      'content',
      'text',
      'label',
      'href',
      'target',
      'alt',
      'url',
      'title',
      'items',
      'quote',
      'citation',
      'html',
    ].includes(id)
  )
    return 'content';
  if (
    [
      'columns',
      'rows',
      'count',
      'stackAt',
      'maxWidth',
      'contentWidth',
      'fullWidth',
      'minHeight',
      'align',
      'horizontalAlign',
      'verticalAlign',
      'alignItems',
      'justifyItems',
      'gap',
      'rowGap',
      'padding',
      'paddingX',
      'paddingY',
      'width',
      'aspectRatio',
      'size',
    ].includes(id)
  )
    return 'layout';
  if (
    ['transition', 'duration', 'delay', 'easing', 'guard', 'hoverScale', 'hoverOpacity'].includes(
      id
    )
  )
    return 'animations';
  if (
    [
      'background',
      'textColor',
      'color',
      'tone',
      'variant',
      'rounded',
      'border',
      'radius',
      'shadow',
      'overflow',
    ].includes(id)
  )
    return 'style';
  return 'advanced';
}

function tabLabel(id: string): string {
  const labels: Record<string, string> = {
    content: 'Content',
    layout: 'Layout',
    style: 'Style',
    animations: 'Animations',
    advanced: 'Advanced',
  };
  return labels[id] ?? labelFromId(id);
}

function friendlyName(type: string): string {
  const slug = type.split('/')[1] ?? type;
  return slug.charAt(0).toUpperCase() + slug.slice(1);
}

function labelFromId(id: string): string {
  return id
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/[-_]/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function mediaImageBindingsForControl(
  node: BuilderNode | undefined,
  control: BlockControl
): ImageControlBindings | null {
  if (!node || control.mediaType !== 'image') return null;
  return IMAGE_CONTROL_BINDINGS[`${node.type}:${control.id}`] ?? null;
}

function stringProp(value: unknown, fallback = ''): string {
  if (typeof value === 'string') return value;
  if (typeof value === 'number' && Number.isFinite(value)) return String(value);
  return fallback;
}

function percentageProp(value: unknown, fallback: number): number {
  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) return fallback;
  return Math.max(0, Math.min(100, Math.round(numericValue)));
}

function extractWordPressSizeOptions(
  sizes: Record<string, { width?: number; height?: number }> | undefined
): Array<[string, string]> {
  if (!sizes || typeof sizes !== 'object') return DEFAULT_IMAGE_SIZE_OPTIONS;

  const dynamicOptions = Object.entries(sizes).map(
    ([key, info]) =>
      [
        key,
        info.width && info.height
          ? `${labelFromId(key)} (${info.width}x${info.height})`
          : labelFromId(key),
      ] as [string, string]
  );

  return mergeSelectOptions(DEFAULT_IMAGE_SIZE_OPTIONS, dynamicOptions);
}

function mergeSelectOptions(...groups: Array<Array<[string, string]>>): Array<[string, string]> {
  const entries = new Map<string, string>();
  groups.flat().forEach(([value, label]) => {
    if (!entries.has(value)) entries.set(value, label);
  });
  return Array.from(entries.entries());
}
