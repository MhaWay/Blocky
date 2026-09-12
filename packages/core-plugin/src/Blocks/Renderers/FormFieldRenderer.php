<?php
declare(strict_types=1);

namespace Blocky\Core\Blocks\Renderers;

use Blocky\Core\Blocks\Renderer\BlockRendererInterface;
use Blocky\Core\Blocks\Node;
use Blocky\Core\Support\HtmlString;
use Blocky\Core\Support\RenderContext;

final class FormFieldRenderer implements BlockRendererInterface
{
    public function __construct(
        private readonly string $kind,
    ) {}

    public function render(Node $node, RenderContext $ctx): HtmlString
    {
        return match ($this->kind) {
            'text' => $this->renderTextField($node, $ctx),
            'textarea' => $this->renderTextareaField($node, $ctx),
            'select' => $this->renderSelectField($node, $ctx),
            'radio' => $this->renderChoiceField($node, $ctx, 'radio'),
            'checkbox' => $this->renderChoiceField($node, $ctx, 'checkbox'),
            'date' => $this->renderDateField($node, $ctx),
            'file' => $this->renderFileField($node, $ctx),
            'hidden' => $this->renderHiddenField($node, $ctx),
            'honeypot' => $this->renderHoneypotField($node, $ctx),
            default => HtmlString::element('div', $ctx->blockAttrs($node), ''),
        };
    }

    private function renderTextField(Node $node, RenderContext $ctx): HtmlString
    {
        $fieldId = self::fieldId($node);
        $inputType = (string) ($node->props['inputType'] ?? 'text');
        if (!in_array($inputType, ['text', 'email', 'tel', 'url', 'number'], true)) {
            $inputType = 'text';
        }

        $control = HtmlString::void('input', [
            'id' => $fieldId,
            'type' => $inputType,
            'name' => self::fieldName($node, 'field'),
            'placeholder' => self::stringProp($node, 'placeholder'),
            'value' => self::stringProp($node, 'defaultValue'),
            'autocomplete' => self::nullableProp($node, 'autocomplete'),
            'required' => self::boolProp($node, 'required'),
            'class' => self::inputClasses(),
        ])->toString();

        return $this->wrapField($node, $ctx, $fieldId, $control);
    }

    private function renderTextareaField(Node $node, RenderContext $ctx): HtmlString
    {
        $fieldId = self::fieldId($node);
        $rows = max(2, (int) ($node->props['rows'] ?? 4));

        $control = HtmlString::element('textarea', [
            'id' => $fieldId,
            'name' => self::fieldName($node, 'message'),
            'placeholder' => self::stringProp($node, 'placeholder'),
            'required' => self::boolProp($node, 'required'),
            'rows' => (string) $rows,
            'class' => self::inputClasses('min-h-28'),
        ], \esc_html(self::stringProp($node, 'defaultValue')))->toString();

        return $this->wrapField($node, $ctx, $fieldId, $control);
    }

    private function renderSelectField(Node $node, RenderContext $ctx): HtmlString
    {
        $fieldId = self::fieldId($node);
        $defaultValue = self::stringProp($node, 'defaultValue');
        $placeholder = self::stringProp($node, 'placeholder');
        $optionsHtml = '';

        if ($placeholder !== '') {
            $optionsHtml .= '<option value=""' . ($defaultValue === '' ? ' selected' : '') . ' disabled>' . \esc_html($placeholder) . '</option>';
        }

        foreach (self::parseOptions(self::stringProp($node, 'options')) as $option) {
            $optionsHtml .= '<option value="' . \esc_attr($option['value']) . '"' . ($option['value'] === $defaultValue ? ' selected' : '') . '>' . \esc_html($option['label']) . '</option>';
        }

        $control = HtmlString::element('select', [
            'id' => $fieldId,
            'name' => self::fieldName($node, 'select'),
            'required' => self::boolProp($node, 'required'),
            'class' => self::inputClasses(),
        ], $optionsHtml)->toString();

        return $this->wrapField($node, $ctx, $fieldId, $control);
    }

    private function renderChoiceField(Node $node, RenderContext $ctx, string $inputType): HtmlString
    {
        $fieldId = self::fieldId($node);
        $label = self::stringProp($node, 'label');
        $helpText = self::stringProp($node, 'helpText');
        $required = self::boolProp($node, 'required');
        $layout = (string) ($node->props['layout'] ?? 'stacked');
        $defaultValue = self::stringProp($node, 'defaultValue');
        $defaultValues = array_values(array_filter(array_map('trim', explode(',', $defaultValue))));
        $options = self::parseOptions(self::stringProp($node, 'options'));
        $groupName = self::fieldName($node, $inputType === 'radio' ? 'choice' : 'checks');
        if ($inputType === 'checkbox' && count($options) > 1 && !str_ends_with($groupName, '[]')) {
            $groupName .= '[]';
        }

        $items = '';
        foreach ($options as $index => $option) {
            $optionId = $fieldId . '-' . $index;
            $checked = $inputType === 'radio'
                ? $option['value'] === $defaultValue
                : in_array($option['value'], $defaultValues, true);

            $items .= '<label for="' . \esc_attr($optionId) . '" class="inline-flex items-center gap-2 text-sm text-text-base">'
                . '<input id="' . \esc_attr($optionId) . '" type="' . \esc_attr($inputType) . '" name="' . \esc_attr($groupName) . '" value="' . \esc_attr($option['value']) . '" class="h-4 w-4 accent-accent-base"'
                . ($checked ? ' checked' : '')
                . ($required && $index === 0 ? ' required' : '')
                . '>'
                . '<span>' . \esc_html($option['label']) . '</span>'
                . '</label>';
        }

        $legend = $label !== ''
            ? '<legend class="text-sm font-medium text-text-base">' . \esc_html($label) . ($required ? ' *' : '') . '</legend>'
            : '';
        $help = $helpText !== '' ? '<p class="text-xs text-text-muted">' . \esc_html($helpText) . '</p>' : '';
        $layoutClasses = $layout === 'inline' ? 'flex flex-wrap gap-3' : 'flex flex-col gap-2';

        return HtmlString::element('fieldset', self::fieldWrapperAttrs($node, $ctx), $legend . '<div class="' . \esc_attr($layoutClasses) . '">' . $items . '</div>' . $help);
    }

    private function renderDateField(Node $node, RenderContext $ctx): HtmlString
    {
        $fieldId = self::fieldId($node);
        $inputType = (string) ($node->props['inputType'] ?? 'date');
        if (!in_array($inputType, ['date', 'time', 'datetime-local'], true)) {
            $inputType = 'date';
        }

        $control = HtmlString::void('input', [
            'id' => $fieldId,
            'type' => $inputType,
            'name' => self::fieldName($node, 'date'),
            'value' => self::nullableProp($node, 'defaultValue'),
            'min' => self::nullableProp($node, 'min'),
            'max' => self::nullableProp($node, 'max'),
            'required' => self::boolProp($node, 'required'),
            'class' => self::inputClasses(),
        ])->toString();

        return $this->wrapField($node, $ctx, $fieldId, $control);
    }

    private function renderFileField(Node $node, RenderContext $ctx): HtmlString
    {
        $fieldId = self::fieldId($node);
        $control = HtmlString::void('input', [
            'id' => $fieldId,
            'type' => 'file',
            'name' => self::fieldName($node, 'upload'),
            'accept' => self::nullableProp($node, 'accept'),
            'multiple' => self::boolProp($node, 'multiple'),
            'required' => self::boolProp($node, 'required'),
            'class' => self::inputClasses('cursor-pointer'),
        ])->toString();

        return $this->wrapField($node, $ctx, $fieldId, $control);
    }

    private function renderHiddenField(Node $node, RenderContext $ctx): HtmlString
    {
        $name = self::fieldName($node, 'hidden');
        $value = self::stringProp($node, 'value');

        if ($ctx->isEditorMode()) {
            return HtmlString::element('div', $ctx->blockAttrs($node, [
                'class' => 'w-full rounded-base border border-dashed border-border-base bg-surface-elevated px-3 py-2 text-xs text-text-muted',
            ]), 'Hidden field: ' . \esc_html($name) . ($value !== '' ? ' = ' . \esc_html($value) : ''));
        }

        return HtmlString::void('input', $ctx->blockAttrs($node, [
            'type' => 'hidden',
            'name' => $name,
            'value' => $value,
        ]));
    }

    private function renderHoneypotField(Node $node, RenderContext $ctx): HtmlString
    {
        $name = self::fieldName($node, 'website');
        $label = self::stringProp($node, 'label');

        if ($ctx->isEditorMode()) {
            return HtmlString::element('div', $ctx->blockAttrs($node, [
                'class' => 'w-full rounded-base border border-dashed border-border-base bg-surface-elevated px-3 py-2 text-xs text-text-muted',
            ]), 'Honeypot field: ' . \esc_html($name));
        }

        $input = HtmlString::void('input', [
            'type' => 'text',
            'name' => $name,
            'value' => '',
            'tabindex' => '-1',
            'autocomplete' => 'off',
        ])->toString();

        return HtmlString::element('div', $ctx->blockAttrs($node, [
            'aria-hidden' => 'true',
            'style' => 'position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;',
        ]), '<label>' . \esc_html($label !== '' ? $label : 'Leave this field blank') . $input . '</label>');
    }

    private function wrapField(Node $node, RenderContext $ctx, string $fieldId, string $control): HtmlString
    {
        $label = self::stringProp($node, 'label');
        $helpText = self::stringProp($node, 'helpText');
        $required = self::boolProp($node, 'required');

        $labelHtml = $label !== ''
            ? '<label for="' . \esc_attr($fieldId) . '" class="text-sm font-medium text-text-base">' . \esc_html($label) . ($required ? ' *' : '') . '</label>'
            : '';
        $helpHtml = $helpText !== '' ? '<p class="text-xs text-text-muted">' . \esc_html($helpText) . '</p>' : '';

        return HtmlString::element('div', self::fieldWrapperAttrs($node, $ctx), $labelHtml . $control . $helpHtml);
    }

    /** @return array<string, string|bool|null> */
    private static function fieldWrapperAttrs(Node $node, RenderContext $ctx): array
    {
        $widthClasses = $ctx->resolveVariantClasses($node, [
            'width' => [
                'full' => 'w-full',
                'narrow' => 'w-full max-w-xl',
                'compact' => 'w-full max-w-md',
            ],
        ]);

        return $ctx->blockAttrs($node, [
            'class' => trim('flex flex-col gap-2 ' . $widthClasses),
        ]);
    }

    private static function fieldId(Node $node): string
    {
        return $node->id . '-field';
    }

    private static function fieldName(Node $node, string $fallback): string
    {
        $name = trim((string) ($node->props['name'] ?? ''));
        return $name !== '' ? $name : $fallback;
    }

    private static function stringProp(Node $node, string $prop): string
    {
        return (string) ($node->props[$prop] ?? '');
    }

    private static function nullableProp(Node $node, string $prop): ?string
    {
        $value = trim((string) ($node->props[$prop] ?? ''));
        return $value !== '' ? $value : null;
    }

    private static function boolProp(Node $node, string $prop): bool
    {
        return (bool) ($node->props[$prop] ?? false);
    }

    private static function inputClasses(string $extra = ''): string
    {
        return trim('w-full rounded-input border border-border-base bg-surface-base px-3 py-2 text-sm text-text-base placeholder:text-text-faint focus:border-accent-base focus:outline-none ' . $extra);
    }

    /** @return array<int, array{label:string, value:string}> */
    private static function parseOptions(string $raw): array
    {
        $options = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$label, $value] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $normalizedLabel = $label !== '' ? $label : 'Option';
            $options[] = [
                'label' => $normalizedLabel,
                'value' => $value !== '' ? $value : sanitize_title($normalizedLabel),
            ];
        }

        return $options !== [] ? $options : [['label' => 'Option 1', 'value' => 'option-1']];
    }
}