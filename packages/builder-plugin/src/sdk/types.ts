export interface BuilderNode {
  id:       string;
  type:     string;
  props:    Record<string, unknown>;
  slots:    Record<string, string[]>;
  variants: Record<string, string>;
}

export interface BuilderDocument {
  root:  string;
  nodes: Record<string, BuilderNode>;
}

export interface BlockInsertPreset {
  props?:    Record<string, unknown>;
  variants?: Record<string, string>;
}

export interface BlockDefinition {
  type:         string;
  label?:       string;
  category?:    string;
  description?: string;
  keywords?:    string[];
  icon?:        string;
  schema:       Record<string, unknown>;
  variants:     Record<string, Record<string, string>>;
  editorConfig: {
    controls?:    Array<{
      id:          string;
      type:        string;
      label:       string;
      variantKey?: string;
      options?:    Array<[string | number, string]>;
      tab?:        string;
      min?:        number;
      max?:        number;
      step?:       number;
      mediaType?:  string;
      mediaReturn?: string;
    }>;
    tabs?:        Array<{
      id:       string;
      label:    string;
      controls: Array<{
        id:          string;
        type:        string;
        label:       string;
        variantKey?: string;
        options?:    Array<[string | number, string]>;
        tab?:        string;
        min?:        number;
        max?:        number;
        step?:       number;
        mediaType?:  string;
        mediaReturn?: string;
      }>;
    }>;
    hasSlots?:    string[];
    isContainer?: boolean;
  };
  interactive: boolean;
}

export interface BlockyConfig {
  restUrl:            string;
  nonce:              string;
  assetUrl:           string;
  postTypes:          string[];
  previewStylesheets: string[];
  compilerCssFragments?: string[];
  locale?:            string;
  i18n?:              Record<string, string>;
}

declare global {
  interface Window {
    BlockyBuilderConfig?: BlockyConfig;
    BlockyBuilderDrag?: {
      blockType?: string;
      preset?:    BlockInsertPreset;
    };
  }
}
