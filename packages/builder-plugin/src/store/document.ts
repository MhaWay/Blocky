import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';
import {
  collectMegaMenuCustomSlotNames,
  defaultMegaMenuItems,
  isMegaMenuCustomSlotName,
  normalizeMegaMenuItems,
} from '../megaMenu/items';
import {
  defaultOverlayId,
  normalizeManagedOverlayIds,
  overlayIdForNode,
  supportsManagedOverlayId,
  supportsOverlaySlots,
} from '../overlays/identity';
import type { BlockInsertPreset, BuilderDocument, BuilderNode } from '../sdk/types';
import {
  compileFrontendPageCss,
  compileFrontendPageCssFromHtml,
} from '../tailwind/pageCssCompiler';
import { t } from '../i18n';

const API_BASE = window.BlockyBuilderConfig?.restUrl ?? '/wp-json/blocky/v1/';
const NONCE = window.BlockyBuilderConfig?.nonce ?? '';
let previewRequestSequence = 0;

interface SaveDocumentResponse {
  html?: unknown;
  classCandidates?: unknown;
}

export type InsertPosition = 'before' | 'after' | 'inside';
export type MoveDirection = 'up' | 'down' | 'left' | 'right';
export type PageStarterId =
  | 'page'
  | 'landing'
  | 'base-template'
  | 'header'
  | 'footer'
  | 'menu'
  | 'sidebar';
export interface GridPlacement {
  column: number;
  row: number;
}

export interface BuilderPageRecord {
  id: number;
  title: string;
  status: string;
  type: string;
  link: string;
  modified: string;
  hasDocument: boolean;
}

export interface CreatePostOptions {
  title?: string;
  starter?: PageStarterId;
}

interface DocumentState {
  document: BuilderDocument | null;
  previewHtml: string;
  previewCss: string;
  isLoading: boolean;
  isDirty: boolean;
  isSaving: boolean;
  lastSavedAt: number | null;
  historyDepth: number;
  futureDepth: number;
  postId: number | null;
  currentPost: BuilderPageRecord | null;
  pages: BuilderPageRecord[];
  isLoadingPages: boolean;
  isPageLibraryOpen: boolean;

  loadPost: (postId: number) => Promise<void>;
  save: () => Promise<void>;
  createPost: (input?: string | CreatePostOptions) => Promise<void>;
  loadPageLibrary: () => Promise<void>;
  openPageLibrary: () => void;
  closePageLibrary: () => void;
  deletePage: (postId: number) => Promise<void>;
  publishCurrentPost: () => Promise<void>;
  renameCurrentPost: (title: string) => Promise<void>;
  addBlock: (type: string, parentId?: string, slotName?: string) => void;
  insertBlock: (
    type: string,
    targetId?: string,
    position?: InsertPosition,
    slotName?: string,
    preset?: BlockInsertPreset,
    gridPlacement?: GridPlacement
  ) => void;
  removeBlock: (id: string) => void;
  duplicateBlock: (id: string) => void;
  moveBlock: (id: string, direction: MoveDirection) => void;
  moveBlockTo: (
    id: string,
    targetId: string,
    position: InsertPosition,
    slotName?: string,
    gridPlacement?: GridPlacement
  ) => void;
  updateProps: (id: string, props: Record<string, unknown>) => void;
  updateVariant: (id: string, key: string, value: string) => void;
  refreshPreview: () => Promise<void>;
}

function makeId(): string {
  return Math.random().toString(36).slice(2, 10);
}

function defaultPropsForType(type: string): Record<string, unknown> {
  const defaults: Record<string, Record<string, unknown>> = {
    'bky/heading': { level: 2, text: 'Heading', anchorId: '', tone: 'default', align: 'start' },
    'bky/text': { content: 'Enter your text here.', size: 'base', color: 'base', align: 'start' },
    'bky/button': {
      label: '',
      href: '#',
      variant: 'primary',
      size: 'base',
      target: '_self',
      align: 'start',
      fullWidth: false,
      background: 'transparent',
      textColor: 'inherit',
      border: 'none',
      radius: 'button',
      shadow: 'none',
      overflow: 'visible',
    },
    'bky/image': {
      attachmentId: 0,
      alt: '',
      size: 'large',
      rounded: 'base',
      aspectRatio: 'auto',
      align: 'center',
      width: 'full',
      loading: 'lazy',
      decoding: 'async',
      fit: 'cover',
      focalX: 50,
      focalY: 50,
    },
    'bky/section': {
      paddingY: 'lg',
      paddingX: 'base',
      background: 'transparent',
      textColor: 'base',
      fullWidth: false,
      contentWidth: 'container',
      minHeight: 'auto',
      verticalAlign: 'start',
      horizontalAlign: 'start',
      gap: 'base',
      border: 'none',
      radius: 'none',
      shadow: 'none',
      overflow: 'visible',
      customClass: '',
    },
    'bky/rows': {
      count: 3,
      gap: 'base',
      alignItems: 'stretch',
      background: 'transparent',
      textColor: 'inherit',
      border: 'none',
      radius: 'none',
      shadow: 'none',
      overflow: 'visible',
    },
    'bky/grid': {
      columns: 3,
      rows: 1,
      gap: 'base',
      rowGap: 'base',
      alignItems: 'stretch',
      justifyItems: 'stretch',
      background: 'transparent',
      textColor: 'inherit',
      border: 'none',
      radius: 'none',
      shadow: 'none',
      overflow: 'visible',
    },
    'bky/container': {
      maxWidth: 'base',
      align: 'center',
      padding: 'none',
      background: 'transparent',
      radius: 'none',
    },
    'bky/columns': {
      count: 2,
      gap: 'base',
      stackAt: 'md',
      verticalAlign: 'start',
      background: 'transparent',
      textColor: 'inherit',
      border: 'none',
      radius: 'none',
      shadow: 'none',
      overflow: 'visible',
    },
    'bky/card': {
      padding: 'base',
      background: 'elevated',
      border: true,
      shadow: 'sm',
      overflow: 'hidden',
    },
    'bky/divider': { style: 'solid', ornament: '◆', tone: 'subtle', width: 'full' },
    'bky/spacer': { size: 'base', mobileSize: 'sm', tabletSize: 'base', desktopSize: 'lg' },
    'bky/list': { ordered: false, items: 'First item\nSecond item\nThird item' },
    'bky/icon': {
      icon: '★',
      href: '',
      target: '_self',
      size: 'base',
      tone: 'accent',
      background: 'transparent',
      border: 'none',
      radius: 'full',
      shadow: 'none',
      align: 'start',
    },
    'bky/icon-box': {
      icon: '✨',
      title: 'Icon box',
      text: 'Describe this feature or benefit.',
      href: '',
      target: '_self',
      layout: 'vertical',
      align: 'start',
      gap: 'base',
      iconSize: 'base',
      iconTone: 'accent',
      background: 'transparent',
      textColor: 'inherit',
      border: 'none',
      radius: 'lg',
      shadow: 'none',
    },
    'bky/icon-list': {
      icon: '✓',
      items: 'First item\nSecond item\nThird item',
      gap: 'base',
      tone: 'base',
    },
    'bky/image-box': {
      attachmentId: 0,
      title: 'Image box',
      text: 'Describe this image or linked content.',
      href: '',
      target: '_self',
      layout: 'vertical',
      align: 'start',
      gap: 'base',
      imageWidth: 'full',
      rounded: 'base',
      aspectRatio: 'wide',
      background: 'transparent',
      textColor: 'inherit',
      border: 'none',
      radius: 'lg',
      shadow: 'none',
    },
    'bky/alert': {
      title: 'Heads up',
      message: 'Share an important update or status message.',
      icon: 'ℹ',
      tone: 'info',
      radius: 'lg',
    },
    'bky/progress-bar': {
      label: 'Progress',
      value: 65,
      showLabel: true,
      striped: false,
      size: 'base',
      tone: 'accent',
      radius: 'base',
    },
    'bky/counter': {
      prefix: '',
      value: 128,
      suffix: '+',
      label: 'Satisfied customers',
      align: 'start',
      size: 'base',
      tone: 'accent',
    },
    'bky/star-rating': {
      value: 4.5,
      max: 5,
      label: '4.5 average rating',
      size: 'base',
      tone: 'gold',
      align: 'start',
    },
    'bky/anchor': { anchorId: 'section-anchor', label: 'Section anchor' },
    'bky/social-icons': {
      items:
        'facebook|https://facebook.com\ninstagram|https://instagram.com\nlinkedin|https://linkedin.com',
      layout: 'row',
      gap: 'base',
      align: 'start',
      size: 'base',
      tone: 'brand',
      radius: 'full',
    },
    'bky/share-buttons': {
      networks: 'facebook,x,linkedin',
      title: 'Share this page',
      gap: 'base',
      align: 'start',
      size: 'base',
      tone: 'neutral',
      radius: 'button',
    },
    'bky/search-form': {
      placeholder: 'Search…',
      buttonLabel: 'Search',
      layout: 'inline',
      gap: 'base',
      buttonTone: 'primary',
      radius: 'button',
    },
    'bky/nav-menu': {
      menuLocation: '',
      items: 'Home|/\nAbout|/about\nContact|/contact',
      layout: 'row',
      gap: 'base',
      align: 'start',
    },
    'bky/breadcrumbs': { separator: '/', showCurrent: true },
    'bky/posts-list': { postType: 'post', perPage: 3, showExcerpt: true },
    'bky/posts-grid': { postType: 'post', perPage: 6, columns: '3', showExcerpt: true },
    'bky/featured-posts': {
      title: 'Featured posts',
      perPage: 3,
      layout: 'grid',
      showExcerpt: true,
      stickyOnly: true,
    },
    'bky/taxonomy-list': {
      title: 'Browse topics',
      taxonomy: 'category',
      layout: 'pills',
      showCount: true,
      limit: 8,
    },
    'bky/archive-posts': { perPage: 6, showExcerpt: true },
    'bky/pagination': { prevLabel: 'Previous', nextLabel: 'Next', align: 'start' },
    'bky/image-gallery': { items: '', columns: '3', gap: 'base', aspectRatio: 'square' },
    'bky/basic-gallery': { items: '', columns: '3', gap: 'base' },
    'bky/image-carousel': {
      items: '',
      slidesVisible: '1',
      gap: 'base',
      aspectRatio: 'video',
      showCaptions: true,
      autoPlay: false,
      interval: 5,
    },
    'bky/content-carousel': {
      items:
        'Slide 1|Highlight your offer|Learn more|#\nSlide 2|Guide visitors to the next step|Contact us|#',
      slidesVisible: '1',
      gap: 'base',
      align: 'start',
      tone: 'surface',
      autoPlay: false,
      interval: 5,
    },
    'bky/slider': {
      items:
        'Slide 1|Highlight your offer|Learn more|#\nSlide 2|Guide visitors to the next step|Contact us|#',
      slidesVisible: '1',
      gap: 'base',
      align: 'start',
      tone: 'surface',
      autoPlay: false,
      interval: 5,
    },
    'bky/call-to-action': {
      attachmentId: 0,
      eyebrow: 'Limited release',
      title: 'Launch a focused campaign with Blocky',
      text: 'Combine persuasive copy, supporting media, and clear next steps inside a reusable CTA section.',
      primaryLabel: 'Start now',
      primaryUrl: '#',
      secondaryLabel: 'Book a demo',
      secondaryUrl: '#',
      layout: 'split',
      align: 'start',
      mediaPosition: 'end',
      tone: 'surface',
      padding: 'lg',
    },
    'bky/testimonial': {
      attachmentId: 0,
      quote: 'Blocky helped us ship faster without losing layout control.',
      author: 'Alex Morgan',
      role: 'Product Marketing Lead',
      layout: 'card',
      tone: 'surface',
    },
    'bky/price-table': {
      title: 'Growth',
      subtitle: 'For teams ready to scale.',
      price: '49',
      currency: '€',
      cadence: '/mo',
      features: 'Unlimited sections\nShared design tokens\nPriority support',
      buttonLabel: 'Choose plan',
      buttonUrl: '#',
      featured: false,
      align: 'start',
      tone: 'surface',
    },
    'bky/price-list': {
      items:
        'Strategy Session|60 minute workshop|€120\nImplementation Sprint|Landing page setup|€900',
      showDividers: true,
      tone: 'surface',
    },
    'bky/flip-box': {
      frontTitle: 'Feature teaser',
      frontText: 'Keep the front side concise and visual.',
      backTitle: 'Reveal the full pitch',
      backText: 'Use the back side for detail, proof, or the next action.',
      buttonLabel: 'Learn more',
      buttonUrl: '#',
      height: 'base',
      verticalAlign: 'end',
      tone: 'surface',
    },
    'bky/hotspot': {
      attachmentId: 0,
      points:
        'Hero area|28|35|Point out the main value proposition.\nCall to action|70|62|Use a second marker for the next step.',
      tone: 'surface',
    },
    'bky/before-after-slider': {
      beforeAttachmentId: 0,
      afterAttachmentId: 0,
      startingPoint: 50,
      aspectRatio: 'video',
    },
    'bky/countdown': {
      targetDate: '2030-01-01T00:00:00',
      showLabels: true,
      layout: 'grid',
      tone: 'surface',
    },
    'bky/animated-headline': {
      prefix: 'Build',
      words: 'faster\nsmarter\nwith Blocky',
      suffix: 'pages',
      effect: 'rotate',
      interval: 3,
      align: 'start',
      tone: 'accent',
    },
    'bky/marquee': {
      items: 'Launch faster\nReusable sections\nWordPress native\nTailwind-first',
      speed: 20,
      direction: 'left',
      pauseOnHover: true,
      tone: 'surface',
    },
    'bky/lottie': {
      url: '',
      poster: 0,
      autoplay: true,
      loop: true,
      speed: 1,
      aspectRatio: 'square',
    },
    'bky/embed-google-maps': {
      provider: 'google',
      query: 'Milan, Italy',
      latitude: '',
      longitude: '',
      zoom: 12,
      title: 'Map embed',
      allowFullscreen: true,
      height: 'base',
      aspectRatio: 'wide',
    },
    'bky/embed-iframe': {
      url: '',
      title: 'Embedded iframe',
      sandbox: 'allow-scripts allow-same-origin allow-forms',
      allow: 'fullscreen; autoplay; clipboard-read; clipboard-write',
      lazyLoad: true,
      allowFullscreen: true,
      height: 'base',
      aspectRatio: 'video',
    },
    'bky/code-highlight': {
      code: "const hello = 'Blocky';\nconsole.log(hello);",
      language: 'javascript',
      caption: '',
      showLineNumbers: true,
      tone: 'contrast',
    },
    'bky/mega-menu': {
      menuLocation: '',
      items: defaultMegaMenuItems(),
      openOn: 'hover',
      columns: '3',
      panelWidth: 'lg',
      gap: 'base',
      align: 'start',
    },
    'bky/login-form': {
      formId: '',
      redirectUrl: '',
      title: 'Welcome back',
      buttonLabel: 'Sign in',
      showRemember: true,
      showLostPassword: true,
      showRegisterLink: true,
      gap: 'base',
      padding: 'base',
      background: 'surface',
      border: 'subtle',
      radius: 'lg',
    },
    'bky/register-form': {
      formId: '',
      redirectUrl: '',
      title: 'Create your account',
      submitLabel: 'Create account',
      successMessage: 'Your account has been created.',
      loginAfterRegister: false,
      gap: 'base',
      padding: 'base',
      background: 'surface',
      border: 'subtle',
      radius: 'lg',
    },
    'bky/contact-form': {
      formId: '',
      title: "Let's talk",
      successMessage: 'Thanks, we received your message.',
      nameLabel: 'Name',
      emailLabel: 'Email',
      subjectLabel: 'Subject',
      messageLabel: 'Message',
      submitLabel: 'Send message',
      gap: 'base',
      padding: 'base',
      background: 'surface',
      border: 'subtle',
      radius: 'lg',
    },
    'bky/author-box': {
      title: 'About the author',
      showAvatar: true,
      showBio: true,
      showArchiveLink: true,
      avatarSize: 'base',
      layout: 'row',
      padding: 'base',
      background: 'surface',
      border: 'subtle',
      radius: 'lg',
    },
    'bky/comments': {
      title: 'Discussion',
      perPage: 6,
      showAvatar: true,
      showDate: true,
      order: 'asc',
    },
    'bky/comment-form': {
      titleReply: 'Leave a reply',
      buttonLabel: 'Post comment',
      showNotes: true,
    },
    'bky/post-navigation': {
      prevLabel: 'Previous post',
      nextLabel: 'Next post',
      layout: 'between',
      showLabels: true,
    },
    'bky/sitemap': {
      title: 'Sitemap',
      includePages: true,
      includePosts: true,
      postsPerPage: 6,
      showDescriptions: false,
      orderBy: 'menu_order',
    },
    'bky/table-of-contents': { title: 'On this page', minLevel: 2, maxLevel: 4, ordered: false },
    'bky/scroll-progress': { position: 'top', height: 'sm', tone: 'accent', showTrack: true },
    'bky/sticky-bar': {
      title: 'Keep this page within reach',
      text: 'Pin an important CTA, promo, or status message while visitors scroll.',
      buttonLabel: 'Get started',
      buttonUrl: '#',
      position: 'bottom',
      showAfter: 15,
      dismissible: true,
      tone: 'surface',
    },
    'bky/back-to-top': {
      label: 'Back to top',
      showLabel: false,
      position: 'right',
      showAfter: 20,
      size: 'base',
      tone: 'accent',
    },
    'bky/popup': {
      overlayId: '',
      title: 'Popup title',
      description: 'Use this popup for announcements, lead capture, or offers.',
      placement: 'center',
      size: 'md',
      backdrop: 'dim',
      dismissEsc: true,
      dismissOutside: true,
      focusTrap: true,
      openOn: 'exit-intent',
      frequency: 'session',
      closeOn: '',
      defaultOpen: false,
    },
    'bky/notification-toast': {
      overlayId: '',
      title: 'Saved',
      description: 'Your changes were saved successfully.',
      placement: 'right',
      size: 'sm',
      backdrop: 'none',
      dismissEsc: true,
      dismissOutside: false,
      focusTrap: false,
      openOn: 'delay:1500',
      frequency: 'always',
      closeOn: 'timeout:3000',
      defaultOpen: false,
    },
    'bky/cookie-banner': {
      overlayId: '',
      title: 'We use cookies',
      description:
        'Use cookies to improve the browsing experience and measure content performance.',
      placement: 'bottom',
      size: 'xl',
      backdrop: 'none',
      dismissEsc: true,
      dismissOutside: false,
      focusTrap: false,
      openOn: 'load',
      frequency: 'once',
      closeOn: '',
      defaultOpen: false,
      cookieName: 'blocky_cookie_consent',
      acceptLabel: 'Accept',
      rejectLabel: 'Reject',
      cookieDurationDays: 180,
    },
    'bky/lightbox': {
      overlayId: '',
      triggerLabel: 'Open media',
      mediaUrl: '',
      mediaType: 'image',
      caption: '',
      thumbnailUrl: '',
      aspectRatio: 'video',
    },
    'bky/command-palette': {
      overlayId: '',
      title: 'Command palette',
      description: 'Search common actions, routes, or documentation.',
      placement: 'top',
      size: 'lg',
      backdrop: 'blur',
      dismissEsc: true,
      dismissOutside: true,
      focusTrap: true,
      openOn: 'shortcut:mod+k',
      frequency: 'always',
      closeOn: '',
      defaultOpen: false,
    },
    'bky/tabs': {
      items: 'Tab 1|First tab content\nTab 2|Second tab content\nTab 3|Third tab content',
      activeIndex: 0,
    },
    'bky/accordion': { items: 'Question 1|Answer one\nQuestion 2|Answer two', openFirst: true },
    'bky/toggle': { title: 'Toggle title', content: 'Toggle content', open: false },
    'bky/form': {
      formId: '',
      action: '',
      method: 'post',
      name: '',
      successMessage: 'Thanks, your submission has been saved.',
      enctype: 'default',
      noValidate: false,
      gap: 'base',
      align: 'start',
      padding: 'none',
      background: 'transparent',
      border: 'none',
      radius: 'none',
      shadow: 'none',
    },
    'bky/form-field-text': {
      label: 'Your name',
      name: 'name',
      placeholder: 'Enter a value',
      inputType: 'text',
      autocomplete: '',
      helpText: '',
      required: false,
      defaultValue: '',
      width: 'full',
    },
    'bky/form-field-textarea': {
      label: 'Message',
      name: 'message',
      placeholder: 'Write your message',
      rows: 4,
      helpText: '',
      required: false,
      defaultValue: '',
      width: 'full',
    },
    'bky/form-field-select': {
      label: 'Topic',
      name: 'topic',
      options: 'Support|support\nSales|sales\nPartnerships|partnerships',
      placeholder: 'Choose an option',
      helpText: '',
      required: false,
      defaultValue: '',
      width: 'full',
    },
    'bky/form-field-radio': {
      label: 'Preferred contact',
      name: 'contact_preference',
      options: 'Email|email\nPhone|phone',
      helpText: '',
      required: false,
      defaultValue: 'email',
      layout: 'stacked',
      width: 'full',
    },
    'bky/form-field-checkbox': {
      label: 'Interests',
      name: 'interests',
      options: 'Design|design\nDevelopment|development\nStrategy|strategy',
      helpText: '',
      required: false,
      defaultValue: '',
      layout: 'stacked',
      width: 'full',
    },
    'bky/form-field-date': {
      label: 'Preferred date',
      name: 'preferred_date',
      inputType: 'date',
      min: '',
      max: '',
      helpText: '',
      required: false,
      defaultValue: '',
      width: 'full',
    },
    'bky/form-field-file': {
      label: 'Attachment',
      name: 'attachment',
      accept: '',
      helpText: '',
      multiple: false,
      required: false,
      width: 'full',
    },
    'bky/form-field-hidden': { name: 'source', value: 'builder' },
    'bky/form-field-honeypot': { name: 'website', label: 'Leave this field blank' },
    'bky/form-submit': {
      label: 'Send message',
      variant: 'primary',
      size: 'base',
      align: 'start',
      fullWidth: false,
      radius: 'button',
      shadow: 'none',
    },
    'bky/modal': {
      overlayId: '',
      title: 'Modal title',
      description: 'Add content to the modal body slot.',
      placement: 'center',
      size: 'md',
      backdrop: 'dim',
      dismissEsc: true,
      dismissOutside: true,
      focusTrap: true,
      openOn: '',
      frequency: 'always',
      closeOn: '',
      defaultOpen: false,
    },
    'bky/offcanvas': {
      overlayId: '',
      title: 'Offcanvas panel',
      description: 'Add navigation, filters, or utility content.',
      placement: 'right',
      size: 'md',
      backdrop: 'dim',
      dismissEsc: true,
      dismissOutside: true,
      focusTrap: true,
      openOn: '',
      frequency: 'always',
      closeOn: '',
      defaultOpen: false,
    },
    'bky/drawer': {
      overlayId: '',
      title: 'Drawer',
      description: 'Add compact actions, filters, or support content.',
      placement: 'left',
      size: 'sm',
      backdrop: 'dim',
      dismissEsc: true,
      dismissOutside: true,
      focusTrap: true,
      openOn: '',
      frequency: 'always',
      closeOn: '',
      defaultOpen: false,
    },
    'bky/modal-trigger': {
      label: 'Open overlay',
      targetOverlayId: '',
      action: 'overlay.open',
      variant: 'primary',
      size: 'base',
      align: 'start',
      fullWidth: false,
      radius: 'button',
      shadow: 'none',
    },
    'bky/popover': {
      overlayId: '',
      title: 'Popover',
      description: 'Add short contextual content next to a trigger.',
      placement: 'bottom',
      size: 'sm',
      backdrop: 'none',
      dismissEsc: true,
      dismissOutside: true,
      focusTrap: false,
      openOn: '',
      frequency: 'always',
      closeOn: '',
      defaultOpen: false,
    },
    'bky/tooltip': {
      overlayId: '',
      title: 'Tooltip',
      description: 'Short helper text for a nearby trigger.',
      placement: 'top',
      size: 'sm',
      backdrop: 'none',
      dismissEsc: true,
      dismissOutside: true,
      focusTrap: false,
      openOn: '',
      frequency: 'always',
      closeOn: '',
      defaultOpen: false,
    },
    'bky/dialog-confirm': {
      overlayId: '',
      title: 'Confirm action',
      description: 'Review the action before confirming.',
      placement: 'center',
      size: 'sm',
      backdrop: 'dim',
      dismissEsc: true,
      dismissOutside: false,
      focusTrap: true,
      openOn: '',
      frequency: 'always',
      closeOn: '',
      defaultOpen: false,
      cancelLabel: 'Cancel',
      confirmLabel: 'Confirm',
    },
    'bky/quote': { quote: 'A short quote or testimonial.', citation: '' },
    'bky/video': { url: '', title: t('document.embeddedVideo', 'Embedded video') },
    'bky/html': { html: '<p>Custom HTML</p>' },
    'bky/wp-post-title': { level: 1 },
    'bky/wp-post-content': {},
    'bky/wp-featured-image': { size: 'large', rounded: 'none' },
    'bky/wp-template-part': { postId: 0 },
    'bky/wp-shortcode': { shortcode: '' },
    'bky/wp-hook': { hookName: '', type: 'action' },
    'bky/theme-toggle': {
      label: t('document.toggleTheme', 'Toggle Theme'),
      size: 'md',
      variant: 'outline',
    },
  };
  return defaults[type] ?? {};
}

function defaultSlotsForType(type: string): Record<string, string[]> {
  if (type === 'bky/card') return { media: [], header: [], default: [], footer: [] };
  if (supportsOverlaySlots(type)) {
    return { body: [], header: [], footer: [], close: [] };
  }
  if (type === 'bky/rows') {
    return { 'row-1': [], 'row-2': [], 'row-3': [], 'row-4': [], 'row-5': [], 'row-6': [] };
  }
  const slotted = ['bky/section', 'bky/grid', 'bky/container', 'bky/button', 'bky/form'];
  if (slotted.includes(type)) return { default: [] };
  if (type === 'bky/columns') {
    return {
      'column-1': [],
      'column-2': [],
      'column-3': [],
      'column-4': [],
      'column-5': [],
      'column-6': [],
    };
  }
  return {};
}

function collectNodeSubtreeIds(
  document: BuilderDocument,
  nodeId: string,
  target: Set<string>
): void {
  if (target.has(nodeId)) return;
  target.add(nodeId);
  const node = document.nodes[nodeId];
  if (!node) return;
  Object.values(node.slots).forEach((children) => {
    (children ?? []).forEach((childId) => collectNodeSubtreeIds(document, childId, target));
  });
}

function syncMegaMenuSlots(
  document: BuilderDocument,
  node: BuilderNode,
  nextItems: unknown,
  pruneRemoved: boolean
): void {
  if (node.type !== 'bky/mega-menu') {
    return;
  }

  const items = normalizeMegaMenuItems(nextItems);
  const nextSlotNames = new Set(collectMegaMenuCustomSlotNames(items));
  const currentSlotNames = Object.keys(node.slots).filter(isMegaMenuCustomSlotName);

  nextSlotNames.forEach((slotName) => {
    node.slots[slotName] ??= [];
  });

  if (!pruneRemoved) {
    return;
  }

  currentSlotNames.forEach((slotName) => {
    if (nextSlotNames.has(slotName)) {
      return;
    }

    const toDelete = new Set<string>();
    (node.slots[slotName] ?? []).forEach((childId) =>
      collectNodeSubtreeIds(document, childId, toDelete)
    );
    toDelete.forEach((childId) => {
      delete document.nodes[childId];
    });
    delete node.slots[slotName];
  });
}

function normalizeMegaMenuStructures(document: BuilderDocument, pruneRemoved: boolean): boolean {
  let changed = false;

  Object.values(document.nodes).forEach((node) => {
    if (node.type !== 'bky/mega-menu') {
      return;
    }

    const normalizedItems = normalizeMegaMenuItems(node.props['items']);
    const previousItems = JSON.stringify(node.props['items'] ?? null);
    const nextItems = JSON.stringify(normalizedItems);
    if (previousItems !== nextItems) {
      node.props['items'] = normalizedItems;
      changed = true;
    }

    const previousSlotNames = JSON.stringify(
      Object.keys(node.slots).filter(isMegaMenuCustomSlotName).sort()
    );
    syncMegaMenuSlots(document, node, normalizedItems, pruneRemoved);
    const nextSlotNames = JSON.stringify(
      Object.keys(node.slots).filter(isMegaMenuCustomSlotName).sort()
    );
    if (previousSlotNames !== nextSlotNames) {
      changed = true;
    }
  });

  return changed;
}

function slotNamesForInsertion(node: BuilderNode): string[] {
  const canonicalSlots = Object.keys(defaultSlotsForType(node.type));
  if (canonicalSlots.length > 0) return canonicalSlots;

  const existingSlots = Object.keys(node.slots ?? {});
  if (existingSlots.length > 0) return existingSlots;
  return [];
}

function resolveInsideSlot(node: BuilderNode, requestedSlotName: string): string | null {
  const slotNames = slotNamesForInsertion(node);
  if (slotNames.includes(requestedSlotName)) return requestedSlotName;
  return slotNames[0] ?? null;
}

function strProp(value: unknown): string {
  return typeof value === 'string' ? value : '';
}

function createNode(type: string, preset?: BlockInsertPreset): BuilderNode {
  const id = makeId();
  const node: BuilderNode = {
    id,
    type,
    props: { ...defaultPropsForType(type), ...(preset?.props ?? {}) },
    slots: defaultSlotsForType(type),
    variants: { ...(preset?.variants ?? {}) },
  };

  if (supportsManagedOverlayId(type) && strProp(node.props['overlayId']).trim() === '') {
    node.props['overlayId'] = defaultOverlayId(type, id);
  }

  return node;
}

function remapOverlayTargets(node: BuilderNode, overlayTargetMap: Map<string, string>): void {
  const targetOverlayId = strProp(node.props['targetOverlayId']).trim();
  if (targetOverlayId !== '' && overlayTargetMap.has(targetOverlayId)) {
    node.props['targetOverlayId'] = overlayTargetMap.get(targetOverlayId) ?? targetOverlayId;
  }

  const interactions = node.props['interactions'];
  if (!Array.isArray(interactions)) {
    return;
  }

  node.props['interactions'] = (interactions as unknown[]).map((entry): unknown => {
    if (!entry || typeof entry !== 'object' || Array.isArray(entry)) {
      return entry;
    }

    const record = entry as Record<string, unknown>;
    const action = typeof record['action'] === 'string' ? record['action'] : '';
    const rawTarget = typeof record['target'] === 'string' ? record['target'].trim() : '';
    if (!action.startsWith('overlay.') || rawTarget === '' || !overlayTargetMap.has(rawTarget)) {
      return entry;
    }

    return {
      ...record,
      target: overlayTargetMap.get(rawTarget) ?? rawTarget,
    };
  });
}

interface ParentSlotRef {
  parentId: string;
  slotName: string;
  index: number;
}

interface GridPosition {
  row: number;
  column: number;
}

function findParentSlot(document: BuilderDocument, nodeId: string): ParentSlotRef | null {
  for (const parent of Object.values(document.nodes)) {
    for (const [slotName, children] of Object.entries(parent.slots ?? {})) {
      const index = (children ?? []).indexOf(nodeId);
      if (index !== -1) {
        return { parentId: parent.id, slotName, index };
      }
    }
  }
  return null;
}

function isDescendant(document: BuilderDocument, ancestorId: string, candidateId: string): boolean {
  const ancestor = document.nodes[ancestorId];
  if (!ancestor) return false;
  const children = Object.values(ancestor.slots).flat();
  if (children.includes(candidateId)) return true;
  return children.some((childId) => isDescendant(document, childId, candidateId));
}

function insertNodeId(
  document: BuilderDocument,
  nodeId: string,
  targetId?: string,
  position: InsertPosition = 'inside',
  slotName = 'default'
): boolean {
  const target = targetId ? document.nodes[targetId] : document.nodes[document.root];
  if (!target) return false;
  if (!target.slots) target.slots = {};

  if (position === 'inside') {
    const resolvedSlotName = resolveInsideSlot(target, slotName);
    if (!resolvedSlotName) return false;

    (target.slots[resolvedSlotName] ??= []).push(nodeId);
    return true;
  }

  const parentRef = findParentSlot(document, target.id);
  if (!parentRef) {
    const rootNode = document.nodes[document.root];
    if (!rootNode) return false;
    if (!rootNode.slots) rootNode.slots = {};
    (rootNode.slots['default'] ??= []).push(nodeId);
    return true;
  }

  const parent = document.nodes[parentRef.parentId];
  const slot = parent?.slots[parentRef.slotName];
  if (!slot) return false;

  const insertAt = position === 'before' ? parentRef.index : parentRef.index + 1;
  slot.splice(insertAt, 0, nodeId);
  return true;
}

function removeNodeIdFromParent(document: BuilderDocument, nodeId: string): boolean {
  const parentRef = findParentSlot(document, nodeId);
  if (!parentRef) return false;
  const parent = document.nodes[parentRef.parentId];
  const slot = parent?.slots[parentRef.slotName];
  if (!slot) return false;
  slot.splice(parentRef.index, 1);
  return true;
}

function targetIndexForMove(
  parent: BuilderNode,
  index: number,
  childCount: number,
  direction: MoveDirection
): number | null {
  const target = direction === 'up' || direction === 'left' ? index - 1 : index + 1;
  return target >= 0 && target < childCount ? target : null;
}

function orderedStructuredSlotNames(parent: BuilderNode): string[] {
  if (parent.type === 'bky/columns')
    return ['column-1', 'column-2', 'column-3', 'column-4', 'column-5', 'column-6'];
  if (parent.type === 'bky/rows') return ['row-1', 'row-2', 'row-3', 'row-4', 'row-5', 'row-6'];
  return [];
}

function moveStructuredChild(
  document: BuilderDocument,
  parent: BuilderNode,
  fromSlotName: string,
  nodeId: string,
  index: number,
  direction: MoveDirection
): boolean {
  const currentSlot = parent.slots[fromSlotName] ?? [];
  const orderedSlots = orderedStructuredSlotNames(parent);
  const slotIndex = orderedSlots.indexOf(fromSlotName);

  if (slotIndex === -1) return false;

  const movesAcrossSlots =
    parent.type === 'bky/columns'
      ? direction === 'left' || direction === 'right'
      : direction === 'up' || direction === 'down';

  if (!movesAcrossSlots) {
    const target = targetIndexForMove(parent, index, currentSlot.length, direction);
    if (target === null) return false;
    [currentSlot[index], currentSlot[target]] = [currentSlot[target]!, currentSlot[index]!];
    return true;
  }

  const nextSlotIndex = direction === 'left' || direction === 'up' ? slotIndex - 1 : slotIndex + 1;
  const nextSlotName = orderedSlots[nextSlotIndex];
  if (!nextSlotName) return false;

  const nextSlot = (parent.slots[nextSlotName] ??= []);
  currentSlot.splice(index, 1);
  nextSlot.push(nodeId);
  return true;
}

function moveGridChild(
  document: BuilderDocument,
  parent: BuilderNode,
  slot: string[],
  nodeId: string,
  index: number,
  direction: MoveDirection
): boolean {
  const columns = gridColumnCount(parent);
  const positions = gridChildPositions(document, slot, columns);
  const current = positions.get(nodeId);
  if (!current) return false;

  const rows = gridRowCount(parent, slot.length, positions);
  const target = targetGridPosition(current, columns, rows, direction);
  if (!target) return false;

  const occupantId = slot.find((childId) => {
    if (childId === nodeId) return false;
    const position = positions.get(childId);
    return position ? sameGridPosition(position, target) : false;
  });

  if (occupantId) {
    positions.set(occupantId, current);
  }
  positions.set(nodeId, target);

  for (const childId of slot) {
    const child = document.nodes[childId];
    const position = positions.get(childId);
    if (!child || !position) continue;
    child.props['gridColumnStart'] = position.column;
    child.props['gridRowStart'] = position.row;
  }

  return true;
}

function gridChildPositions(
  document: BuilderDocument,
  slot: string[],
  columns: number
): Map<string, GridPosition> {
  const positions = new Map<string, GridPosition>();
  slot.forEach((childId, index) => {
    positions.set(childId, gridPositionForNode(document.nodes[childId], index, columns));
  });
  return positions;
}

function gridPositionForNode(
  node: BuilderNode | undefined,
  index: number,
  columns: number
): GridPosition {
  const fallback = implicitGridPosition(index, columns);
  if (!node) return fallback;
  return {
    column: boundedInteger(node.props['gridColumnStart'], fallback.column, 1, columns),
    row: boundedInteger(node.props['gridRowStart'], fallback.row, 1, 12),
  };
}

function implicitGridPosition(index: number, columns: number): GridPosition {
  return {
    column: (index % columns) + 1,
    row: Math.floor(index / columns) + 1,
  };
}

function targetGridPosition(
  position: GridPosition,
  columns: number,
  rows: number,
  direction: MoveDirection
): GridPosition | null {
  const target = { ...position };
  if (direction === 'left') target.column -= 1;
  if (direction === 'right') target.column += 1;
  if (direction === 'up') target.row -= 1;
  if (direction === 'down') target.row += 1;

  if (target.column < 1 || target.column > columns || target.row < 1 || target.row > rows) {
    return null;
  }
  return target;
}

function gridRowCount(
  parent: BuilderNode,
  childCount: number,
  positions: Map<string, GridPosition>
): number {
  const configuredRows = boundedInteger(parent.props['rows'] ?? parent.variants['rows'], 1, 1, 12);
  const implicitRows = Math.max(1, Math.ceil(childCount / gridColumnCount(parent)));
  const placedRows = Math.max(1, ...Array.from(positions.values(), (position) => position.row));
  return Math.max(configuredRows, implicitRows, placedRows);
}

function sameGridPosition(a: GridPosition, b: GridPosition): boolean {
  return a.row === b.row && a.column === b.column;
}

function boundedInteger(value: unknown, fallback: number, min: number, max: number): number {
  const numberValue = Number(value);
  if (!Number.isFinite(numberValue)) return fallback;
  return Math.max(min, Math.min(max, Math.trunc(numberValue)));
}

function gridColumnCount(node: BuilderNode): number {
  return boundedInteger(node.props['columns'] ?? node.variants['columns'], 3, 1, 12);
}

function applyGridPlacement(
  document: BuilderDocument,
  nodeId: string,
  targetId: string,
  gridPlacement?: GridPlacement
): void {
  if (!gridPlacement) return;

  const parent = document.nodes[targetId];
  const node = document.nodes[nodeId];
  if (!parent || parent.type !== 'bky/grid' || !node) return;

  node.props['gridColumnStart'] = boundedInteger(
    gridPlacement.column,
    1,
    1,
    gridColumnCount(parent)
  );
  node.props['gridRowStart'] = boundedInteger(gridPlacement.row, 1, 1, 12);
}

/*
 * Documents authored outside the builder (REST/MCP agents) may omit the
 * slots map or use the children shorthand. Normalize on load so every node
 * is safe for the immer-driven mutating actions.
 */
function normalizeLoadedDocument(document: BuilderDocument): void {
  for (const node of Object.values(document.nodes ?? {})) {
    if (!node || typeof node !== 'object') continue;
    if (!node.props || typeof node.props !== 'object') node.props = {};
    if (!node.variants || typeof node.variants !== 'object') node.variants = {};
    if (!node.slots || typeof node.slots !== 'object') {
      node.slots = {};
      const legacyChildren = (node as { children?: unknown }).children;
      if (Array.isArray(legacyChildren)) {
        node.slots['default'] = legacyChildren.filter(
          (child): child is string => typeof child === 'string'
        );
      }
      delete (node as { children?: unknown }).children;
    }
  }
}
export const useDocumentStore = create<DocumentState>()(
  immer((set, get) => ({
    document: null,
    previewHtml: '',
    previewCss: '',
    isLoading: false,
    isDirty: false,
    isSaving: false,
    lastSavedAt: null,
    historyDepth: 0,
    futureDepth: 0,
    postId: null,
    currentPost: null,
    pages: [],
    isLoadingPages: false,
    isPageLibraryOpen: false,

    async loadPost(postId) {
      set((s) => {
        s.isLoading = true;
        s.postId = postId;
      });
      try {
        const res = await apiFetch(`documents/${postId}`);
        const data = (await res.json()) as {
          document?: BuilderDocument | null;
          html?: unknown;
          post?: unknown;
        };

        const nextDocument = data.document ?? emptyDocument();
        normalizeLoadedDocument(nextDocument);
        const normalizedOverlayIds = normalizeManagedOverlayIds(nextDocument);
        const normalizedMegaMenus = normalizeMegaMenuStructures(nextDocument, true);
        const nextHtml = typeof data.html === 'string' ? data.html : '';
        const nextPreviewCss =
          nextHtml !== '' ? await compileFrontendPageCssFromHtml(nextHtml) : '';
        const nextPost = normalizePageRecord(data.post);

        set((s) => {
          s.document = nextDocument;
          s.previewHtml = nextHtml;
          s.previewCss = nextPreviewCss;
          s.isLoading = false;
          s.isDirty = normalizedOverlayIds || normalizedMegaMenus;
          s.isPageLibraryOpen = false;
          if (nextPost) {
            s.currentPost = nextPost;
            s.pages = upsertPageRecord(s.pages, nextPost);
          }
        });

        syncBuilderUrl(postId);

        if (normalizedOverlayIds || normalizedMegaMenus || nextHtml === '') {
          void get().refreshPreview();
        }
      } catch {
        set((s) => {
          s.isLoading = false;
        });
      }
    },

    async save() {
      const { document: currentDocument, postId } = get();
      if (!currentDocument || postId === null) return;
      if (get().isSaving) return;

      set((s) => {
        s.isSaving = true;
      });

      const document = JSON.parse(JSON.stringify(currentDocument)) as BuilderDocument;
      normalizeManagedOverlayIds(document);
      normalizeMegaMenuStructures(document, true);

      try {
        const res = await apiFetch(`documents/${postId}`, 'POST', { document });
        const payload = await saveDocumentResponseFromResponse(res);
        if (!payload) return;

        const previewCss = await compileAndPersistPageCss(postId, payload.classCandidates);

        markHistoryBaseline();
        set((s) => {
          s.document = document;
          s.previewHtml = payload.html;
          s.previewCss = previewCss;
          s.isDirty = false;
          s.isSaving = false;
          s.lastSavedAt = Date.now();
        });
      } catch {
        set((s) => {
          s.isSaving = false;
        });
      }
    },

    async createPost(input) {
      const options = normalizeCreatePostInput(input);
      const title = options.title || defaultTitleForStarter(options.starter);
      set((s) => {
        s.isLoading = true;
      });
      try {
        const res = await apiFetch('documents/library', 'POST', { title });
        const data = (await res.json()) as { post?: unknown };
        const nextPost = normalizePageRecord(data.post);

        if (!nextPost) {
          set((s) => {
            s.isLoading = false;
          });
          return;
        }

        syncBuilderUrl(nextPost.id);

        set((s) => {
          s.postId = nextPost.id;
          s.currentPost = nextPost;
          s.document = starterDocument(options.starter);
          s.previewHtml = '';
          s.previewCss = '';
          s.isDirty = true;
          s.isLoading = false;
          s.isPageLibraryOpen = false;
          s.pages = upsertPageRecord(s.pages, nextPost);
        });

        await get().refreshPreview();
        await get().save();
      } catch {
        set((s) => {
          s.isLoading = false;
        });
      }
    },

    async loadPageLibrary() {
      set((s) => {
        s.isLoadingPages = true;
      });
      try {
        const res = await apiFetch('documents/library');
        const data = (await res.json()) as { items?: unknown[] };
        const pages = Array.isArray(data.items)
          ? data.items
              .map((item) => normalizePageRecord(item))
              .filter((item): item is BuilderPageRecord => item !== null)
          : [];

        set((s) => {
          s.pages = pages;
          s.isLoadingPages = false;
        });
      } catch {
        set((s) => {
          s.isLoadingPages = false;
        });
      }
    },

    openPageLibrary() {
      syncBuilderUrl();
      set((s) => {
        s.isPageLibraryOpen = true;
      });
    },

    closePageLibrary() {
      const { postId } = get();
      if (postId !== null) {
        syncBuilderUrl(postId);
      }
      set((s) => {
        s.isPageLibraryOpen = false;
      });
    },

    async deletePage(postId) {
      const deletingCurrent = get().postId === postId || get().currentPost?.id === postId;

      try {
        const res = await apiFetch(`documents/library/${postId}`, 'DELETE');
        if (!res.ok) return;

        set((s) => {
          s.pages = s.pages.filter((page) => page.id !== postId);

          if (deletingCurrent) {
            s.postId = null;
            s.currentPost = null;
            s.document = null;
            s.previewHtml = '';
            s.previewCss = '';
            s.isDirty = false;
            s.isPageLibraryOpen = true;
          }
        });

        if (deletingCurrent) {
          syncBuilderUrl();
        }
      } catch {
        /* page delete errors are non-fatal for now */
      }
    },

    async publishCurrentPost() {
      const { currentPost, document, postId } = get();
      if (!currentPost || !document || postId === null) return;
      if (currentPost.status === 'publish') return;

      try {
        const saveRes = await apiFetch(`documents/${postId}`, 'POST', { document });
        const payload = await saveDocumentResponseFromResponse(saveRes);
        if (!payload) return;

        const previewCss = await compileAndPersistPageCss(postId, payload.classCandidates);

        set((s) => {
          s.previewHtml = payload.html;
          s.previewCss = previewCss;
          s.isDirty = false;
        });

        const res = await apiFetch(`documents/${postId}/details`, 'POST', { status: 'publish' });
        if (!res.ok) return;

        const data = (await res.json()) as { post?: unknown };
        const updatedPost = normalizePageRecord(data.post);
        if (!updatedPost) return;

        set((s) => {
          s.currentPost = updatedPost;
          s.pages = upsertPageRecord(s.pages, updatedPost);
        });
      } catch {
        /* publish errors are non-fatal for now */
      }
    },

    async renameCurrentPost(title) {
      const { currentPost, postId } = get();
      if (!currentPost || postId === null) return;

      const nextTitle = title.trim();
      if (nextTitle === '' || nextTitle === currentPost.title) return;

      const previousTitle = currentPost.title;
      const optimisticPost = { ...currentPost, title: nextTitle };

      set((s) => {
        s.currentPost = optimisticPost;
        s.pages = upsertPageRecord(s.pages, optimisticPost);
      });

      try {
        const res = await apiFetch(`documents/${postId}/details`, 'POST', { title: nextTitle });
        if (!res.ok) throw new Error('rename_failed');

        const data = (await res.json()) as { post?: unknown };
        const updatedPost = normalizePageRecord(data.post) ?? optimisticPost;

        set((s) => {
          s.currentPost = updatedPost;
          s.pages = upsertPageRecord(s.pages, updatedPost);
        });
      } catch {
        set((s) => {
          if (!s.currentPost) return;
          s.currentPost.title = previousTitle;
          s.pages = upsertPageRecord(s.pages, { ...optimisticPost, title: previousTitle });
        });
      }
    },

    addBlock(type, parentId, slotName = 'default') {
      get().insertBlock(type, parentId, 'inside', slotName);
    },

    insertBlock(type, targetId, position = 'inside', slotName = 'default', preset, gridPlacement) {
      set((s) => {
        if (!s.document) s.document = emptyDocument();

        const node = createNode(type, preset);
        const target = targetId ?? s.document.root;
        const inserted =
          insertNodeId(s.document, node.id, target, position, slotName) ||
          (target !== s.document.root &&
            insertNodeId(s.document, node.id, s.document.root, 'inside'));

        if (!inserted) return;

        s.document.nodes[node.id] = node;
        applyGridPlacement(s.document, node.id, target, gridPlacement);

        s.isDirty = true;
      });
      void get().refreshPreview();
    },

    removeBlock(id) {
      set((s) => {
        if (!s.document) return;
        if (id === s.document.root) return;
        // Recursively collect this node and all descendants
        const toDelete = new Set<string>();
        const collect = (nodeId: string) => {
          if (toDelete.has(nodeId)) return;
          toDelete.add(nodeId);
          const node = s.document!.nodes[nodeId];
          if (!node) return;
          Object.values(node.slots).forEach((children) => {
            (children ?? []).forEach((childId) => collect(childId));
          });
        };
        collect(id);
        // Remove from all parent slots
        Object.values(s.document.nodes).forEach((node) => {
          Object.keys(node.slots).forEach((slot) => {
            node.slots[slot] = (node.slots[slot] ?? []).filter((nid: string) => !toDelete.has(nid));
          });
        });
        toDelete.forEach((nodeId) => {
          delete s.document!.nodes[nodeId];
        });
        s.isDirty = true;
      });
      void get().refreshPreview();
    },

    duplicateBlock(id) {
      set((s) => {
        if (!s.document) return;
        const original = s.document.nodes[id];
        if (!original) return;

        // Deep-clone the subtree producing fresh ids
        const overlayTargetMap = new Map<string, string>();
        const clonedIds: string[] = [];
        const cloneSubtree = (sourceId: string): string => {
          const src = s.document!.nodes[sourceId];
          if (!src) return sourceId;
          const newId = makeId();
          const newSlots: Record<string, string[]> = {};
          const nextProps = JSON.parse(JSON.stringify(src.props)) as Record<string, unknown>;
          if (supportsManagedOverlayId(src.type)) {
            const oldOverlayId = overlayIdForNode(src);
            const nextOverlayId = defaultOverlayId(src.type, newId);
            nextProps['overlayId'] = nextOverlayId;
            overlayTargetMap.set(oldOverlayId, nextOverlayId);
          }
          Object.entries(src.slots).forEach(([slotName, children]) => {
            newSlots[slotName] = (children ?? []).map((childId) => cloneSubtree(childId));
          });
          s.document!.nodes[newId] = {
            id: newId,
            type: src.type,
            props: nextProps,
            slots: newSlots,
            variants: { ...src.variants },
          };
          clonedIds.push(newId);
          return newId;
        };

        const newId = cloneSubtree(id);
        clonedIds.forEach((clonedId) => {
          const clonedNode = s.document!.nodes[clonedId];
          if (clonedNode) {
            remapOverlayTargets(clonedNode, overlayTargetMap);
          }
        });
        normalizeManagedOverlayIds(s.document);

        // Find parent + slot of original and insert the clone right after it
        for (const node of Object.values(s.document.nodes)) {
          for (const slotName of Object.keys(node.slots)) {
            const arr = node.slots[slotName] ?? [];
            const idx = arr.indexOf(id);
            if (idx !== -1) {
              arr.splice(idx + 1, 0, newId);
              s.isDirty = true;
              return;
            }
          }
        }
        s.isDirty = true;
      });
      void get().refreshPreview();
    },

    moveBlock(id, direction) {
      set((s) => {
        if (!s.document) return;
        for (const node of Object.values(s.document.nodes)) {
          for (const slotName of Object.keys(node.slots)) {
            const arr = node.slots[slotName] ?? [];
            const idx = arr.indexOf(id);
            if (idx === -1) continue;
            if (node.type === 'bky/grid') {
              if (!moveGridChild(s.document, node, arr, id, idx, direction)) return;
              s.isDirty = true;
              return;
            }
            if (node.type === 'bky/columns' || node.type === 'bky/rows') {
              if (!moveStructuredChild(s.document, node, slotName, id, idx, direction)) return;
              s.isDirty = true;
              return;
            }
            const target = targetIndexForMove(node, idx, arr.length, direction);
            if (target === null) return;
            [arr[idx], arr[target]] = [arr[target]!, arr[idx]!];
            s.isDirty = true;
            return;
          }
        }
      });
      void get().refreshPreview();
    },

    moveBlockTo(id, targetId, position, slotName = 'default', gridPlacement) {
      set((s) => {
        if (!s.document) return;
        if (id === s.document.root || id === targetId) return;
        if (!s.document.nodes[id] || !s.document.nodes[targetId]) return;
        if (isDescendant(s.document, id, targetId)) return;

        const removed = removeNodeIdFromParent(s.document, id);
        if (!removed) return;

        const inserted = insertNodeId(s.document, id, targetId, position, slotName);
        if (!inserted) {
          insertNodeId(s.document, id, s.document.root, 'inside');
        } else {
          applyGridPlacement(s.document, id, targetId, gridPlacement);
        }
        s.isDirty = true;
      });
      void get().refreshPreview();
    },

    updateProps(id, props) {
      set((s) => {
        const node = s.document?.nodes[id];
        if (node) {
          const nextProps = { ...props };
          if (
            node.type === 'bky/mega-menu' &&
            Object.prototype.hasOwnProperty.call(nextProps, 'items') &&
            s.document
          ) {
            nextProps.items = normalizeMegaMenuItems(nextProps.items);
            syncMegaMenuSlots(s.document, node, nextProps.items, true);
          }
          Object.assign(node.props, nextProps);
          if (
            s.document &&
            supportsManagedOverlayId(node.type) &&
            Object.prototype.hasOwnProperty.call(props, 'overlayId')
          ) {
            normalizeManagedOverlayIds(s.document, id);
          }
          s.isDirty = true;
        }
      });
      void get().refreshPreview();
    },

    updateVariant(id, key, value) {
      set((s) => {
        const node = s.document?.nodes[id];
        if (node) {
          node.variants[key] = value;
          node.props[key] = value;
          s.isDirty = true;
        }
      });
      void get().refreshPreview();
    },

    async refreshPreview() {
      const { document } = get();
      if (!document) return;
      const requestId = ++previewRequestSequence;
      try {
        const res = await apiFetch('builder/preview', 'POST', { document });
        const html = await htmlFromResponse(res);
        if (html !== null && requestId === previewRequestSequence) {
          const previewCss = await compileFrontendPageCssFromHtml(html);
          if (requestId === previewRequestSequence) {
            set((s) => {
              s.previewHtml = html;
              s.previewCss = previewCss;
            });
          }
        }
      } catch {
        /* preview errors are non-fatal */
      }
    },
  }))
);

function emptyDocument(): BuilderDocument {
  const rootId = 'root';
  return {
    root: rootId,
    nodes: {
      [rootId]: {
        id: rootId,
        type: 'bky/section',
        props: defaultPropsForType('bky/section'),
        slots: { default: [] },
        variants: {},
      },
    },
  };
}

function starterDocument(starter: PageStarterId = 'page'): BuilderDocument {
  const document = emptyDocument();
  const rootId = document.root;
  const root = document.nodes[rootId];

  if (!root) return document;

  switch (starter) {
    case 'base-template': {
      root.props = {
        ...root.props,
        paddingY: 'none',
        paddingX: 'none',
        fullWidth: true,
        contentWidth: 'full',
        gap: 'none',
      };

      const headerSectionId = addStarterNode(document, 'bky/section', rootId, 'default', {
        props: {
          paddingY: 'none',
          paddingX: 'none',
          fullWidth: true,
          contentWidth: 'full',
          background: 'transparent',
          gap: 'none',
        },
      });
      addStarterNode(document, 'bky/wp-template-part', headerSectionId, 'default', {
        props: { postId: 0 },
      });

      const mainSectionId = addStarterNode(document, 'bky/section', rootId, 'default', {
        props: {
          paddingY: 'lg',
          paddingX: 'base',
          contentWidth: 'container',
          gap: 'base',
        },
      });
      addStarterNode(document, 'bky/wp-post-title', mainSectionId, 'default', {
        props: { level: 1 },
      });
      addStarterNode(document, 'bky/wp-post-content', mainSectionId, 'default');

      const footerSectionId = addStarterNode(document, 'bky/section', rootId, 'default', {
        props: {
          paddingY: 'none',
          paddingX: 'none',
          fullWidth: true,
          contentWidth: 'full',
          background: 'transparent',
          gap: 'none',
        },
      });
      addStarterNode(document, 'bky/wp-template-part', footerSectionId, 'default', {
        props: { postId: 0 },
      });
      break;
    }
    case 'landing': {
      root.props = {
        ...root.props,
        paddingY: 'xl',
        horizontalAlign: 'center',
        verticalAlign: 'center',
        minHeight: 'screen50',
        gap: 'lg',
      };
      addStarterNode(document, 'bky/heading', rootId, 'default', {
        props: { level: 1, text: 'Landing page', align: 'center' },
      });
      addStarterNode(document, 'bky/text', rootId, 'default', {
        props: {
          content:
            'Presenta il messaggio principale qui e guida l’utente verso una call to action chiara.',
          align: 'center',
        },
      });
      addStarterNode(document, 'bky/button', rootId, 'default', {
        props: { label: 'Start here', href: '#', align: 'center' },
      });
      break;
    }
    case 'header': {
      root.props = {
        ...root.props,
        paddingY: 'sm',
        background: 'surface',
        border: 'subtle',
        gap: 'none',
      };
      const containerId = addStarterNode(document, 'bky/container', rootId, 'default', {
        props: {
          maxWidth: 'full',
          align: 'center',
          padding: 'none',
          twClasses: ['flex', 'w-full', 'items-center', 'justify-between', 'gap-4'],
        },
      });
      addStarterNode(document, 'bky/heading', containerId, 'default', {
        props: { level: 3, text: 'Brand', align: 'start' },
      });
      const menuId = addStarterNode(document, 'bky/container', containerId, 'default', {
        props: {
          maxWidth: 'full',
          align: 'end',
          padding: 'none',
          twClasses: ['flex', 'items-center', 'gap-3', 'flex-wrap'],
        },
      });
      ['Home', 'Servizi', 'Chi siamo', 'Contatti'].forEach((label) => {
        addStarterNode(document, 'bky/button', menuId, 'default', {
          props: { label, href: '#', variant: 'outline' },
        });
      });
      break;
    }
    case 'footer': {
      root.props = {
        ...root.props,
        paddingY: 'lg',
        background: 'sunken',
        textColor: 'muted',
        gap: 'base',
      };
      const containerId = addStarterNode(document, 'bky/container', rootId, 'default', {
        props: {
          maxWidth: 'full',
          align: 'center',
          padding: 'none',
          twClasses: ['flex', 'w-full', 'items-center', 'justify-between', 'gap-4', 'flex-wrap'],
        },
      });
      addStarterNode(document, 'bky/text', containerId, 'default', {
        props: { content: '© 2026 Your Company. Tutti i diritti riservati.' },
      });
      const linksId = addStarterNode(document, 'bky/container', containerId, 'default', {
        props: {
          maxWidth: 'full',
          align: 'end',
          padding: 'none',
          twClasses: ['flex', 'items-center', 'gap-3', 'flex-wrap'],
        },
      });
      ['Privacy', 'Termini', 'Contatti'].forEach((label) => {
        addStarterNode(document, 'bky/button', linksId, 'default', {
          props: { label, href: '#', variant: 'ghost' },
        });
      });
      break;
    }
    case 'menu': {
      root.props = {
        ...root.props,
        paddingY: 'sm',
        horizontalAlign: 'center',
        gap: 'sm',
      };
      const menuId = addStarterNode(document, 'bky/container', rootId, 'default', {
        props: {
          maxWidth: 'full',
          align: 'center',
          padding: 'none',
          twClasses: ['flex', 'items-center', 'justify-center', 'gap-3', 'flex-wrap'],
        },
      });
      [
        t('starter.menuHome', 'Home'),
        t('starter.menuProducts', 'Products'),
        t('starter.menuBlog', 'Blog'),
        t('starter.menuSupport', 'Support'),
      ].forEach((label) => {
        addStarterNode(document, 'bky/button', menuId, 'default', {
          props: { label, href: '#', variant: 'outline' },
        });
      });
      break;
    }
    case 'sidebar': {
      root.props = {
        ...root.props,
        contentWidth: 'wide',
        gap: 'base',
      };
      const columnsId = addStarterNode(document, 'bky/columns', rootId, 'default', {
        props: { count: 3, gap: 'base', stackAt: 'lg', verticalAlign: 'start' },
      });
      addStarterNode(document, 'bky/heading', columnsId, 'column-1', {
        props: { level: 2, text: t('starter.mainContent', 'Main content') },
      });
      addStarterNode(document, 'bky/text', columnsId, 'column-1', {
        props: {
          content: t(
            'starter.mainContentDescription',
            'Use this column for the main content of the page.'
          ),
        },
      });
      addStarterNode(document, 'bky/list', columnsId, 'column-2', {
        props: {
          ordered: false,
          items: t('starter.keyPoints', 'Key point one\nKey point two\nKey point three'),
        },
      });
      const cardId = addStarterNode(document, 'bky/card', columnsId, 'column-3', {
        props: { padding: 'base', background: 'elevated', border: true, shadow: 'sm' },
      });
      addStarterNode(document, 'bky/heading', cardId, 'default', {
        props: { level: 3, text: t('starter.sidebar', 'Sidebar') },
      });
      addStarterNode(document, 'bky/text', cardId, 'default', {
        props: {
          content: t(
            'starter.sidebarDescription',
            'Add CTAs, quick links, or secondary information here.'
          ),
        },
      });
      addStarterNode(document, 'bky/button', cardId, 'default', {
        props: { label: t('starter.callToAction', 'Call to action'), href: '#', align: 'start' },
      });
      break;
    }
    case 'page':
    default: {
      root.props = {
        ...root.props,
        contentWidth: 'container',
        gap: 'base',
      };
      addStarterNode(document, 'bky/heading', rootId, 'default', {
        props: { level: 1, text: t('starter.newPage', 'New page') },
      });
      addStarterNode(document, 'bky/text', rootId, 'default', {
        props: {
          content: t(
            'starter.newPageDescription',
            'Start here: add sections, content, and layout to build the page.'
          ),
        },
      });
      break;
    }
  }

  return document;
}

function addStarterNode(
  document: BuilderDocument,
  type: string,
  parentId: string,
  slotName = 'default',
  preset?: BlockInsertPreset
): string {
  const node = createNode(type, preset);
  document.nodes[node.id] = node;
  insertNodeId(document, node.id, parentId, 'inside', slotName);
  return node.id;
}

function normalizeCreatePostInput(input?: string | CreatePostOptions): CreatePostOptions {
  if (typeof input === 'string') {
    return { title: input, starter: 'page' };
  }

  const title = input?.title;
  return {
    ...(title !== undefined ? { title } : {}),
    starter: input?.starter ?? 'page',
  };
}

function defaultTitleForStarter(starter: PageStarterId = 'page'): string {
  switch (starter) {
    case 'landing':
      return 'Landing Page';
    case 'base-template':
      return 'Base Template';
    case 'header':
      return 'Header Layout';
    case 'footer':
      return 'Footer Layout';
    case 'menu':
      return 'Navigation Menu';
    case 'sidebar':
      return 'Sidebar Layout';
    case 'page':
    default:
      return 'New Blocky Page';
  }
}

function normalizePageRecord(value: unknown): BuilderPageRecord | null {
  if (!value || typeof value !== 'object') return null;

  const record = value as Record<string, unknown>;
  const id = Number(record.id);
  if (!Number.isFinite(id) || id <= 0) return null;

  return {
    id,
    title:
      typeof record.title === 'string' && record.title.trim() !== '' ? record.title : '(Untitled)',
    status: typeof record.status === 'string' ? record.status : 'draft',
    type: typeof record.type === 'string' ? record.type : 'page',
    link: typeof record.link === 'string' ? record.link : '',
    modified: typeof record.modified === 'string' ? record.modified : '',
    hasDocument: record.hasDocument === true,
  };
}

function upsertPageRecord(
  items: BuilderPageRecord[],
  nextItem: BuilderPageRecord
): BuilderPageRecord[] {
  const filtered = items.filter((item) => item.id !== nextItem.id);
  return [nextItem, ...filtered].sort((left, right) => {
    const leftTime = Date.parse(left.modified);
    const rightTime = Date.parse(right.modified);
    if (Number.isNaN(leftTime) || Number.isNaN(rightTime)) {
      return right.id - left.id;
    }
    return rightTime - leftTime;
  });
}

function syncBuilderUrl(postId?: number): void {
  const url = new URL(window.location.href);
  if (postId && postId > 0) {
    url.searchParams.set('post_id', String(postId));
  } else {
    url.searchParams.delete('post_id');
  }
  window.history.replaceState({}, '', url.toString());
}

async function apiFetch(path: string, method = 'GET', body?: unknown): Promise<Response> {
  return fetch(`${API_BASE}${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': NONCE,
    },
    body: body !== undefined ? JSON.stringify(body) : null,
  });
}

async function htmlFromResponse(res: Response): Promise<string | null> {
  if (!res.ok) return null;

  const data = (await res.json()) as { html?: unknown };
  return typeof data.html === 'string' ? data.html : null;
}

async function saveDocumentResponseFromResponse(
  res: Response
): Promise<{ html: string; classCandidates: string[] } | null> {
  if (!res.ok) return null;

  const data = (await res.json()) as SaveDocumentResponse;
  if (typeof data.html !== 'string') {
    return null;
  }

  const classCandidates = Array.isArray(data.classCandidates)
    ? data.classCandidates.filter(
        (value): value is string => typeof value === 'string' && value !== ''
      )
    : [];

  return {
    html: data.html,
    classCandidates,
  };
}

async function compileAndPersistPageCss(
  postId: number,
  classCandidates: string[]
): Promise<string> {
  const css = await compileFrontendPageCss(classCandidates);

  try {
    await apiFetch(`documents/${postId}/css`, 'POST', { css });
  } catch {
    /* CSS cache generation is best-effort; HTML save remains authoritative. */
  }

  return css;
}

/*
 * F3: snapshot undo/redo + idle autosave.
 *
 * immer keeps every previous document immutable, so history is a list of
 * plain references (zero copy). Rapid edits inside the coalescing window
 * collapse into a single history entry (slider drags, keystrokes).
 */
type DocumentRef = BuilderDocument | null;

const undoStack: DocumentRef[] = [];
const redoStack: DocumentRef[] = [];
const HISTORY_LIMIT = 120;
const COALESCE_WINDOW_MS = 500;

let historyLastDoc: DocumentRef = null;
let historyLastPostId: number | null = null;
let historyLastPushAt = 0;
let historyRestoreToken: DocumentRef = null;
let historyExpectBaseline = false;
let lastEditAt = 0;

function markHistoryBaseline(): void {
  historyExpectBaseline = true;
}

function syncHistoryDepth(): void {
  /* Never setState inside a dispatch: zustand notifies subscribers while
   * the immer draft is still finalising, and a re-entrant produce throws.
   * Flush on the microtask, after the current dispatch has committed. */
  queueMicrotask(() => {
    useDocumentStore.setState({
      historyDepth: undoStack.length,
      futureDepth: redoStack.length,
    });
  });
}

useDocumentStore.subscribe((state) => {
  if (state.postId !== historyLastPostId) {
    historyLastPostId = state.postId;
    undoStack.length = 0;
    redoStack.length = 0;
    historyLastDoc = null;
    historyLastPushAt = 0;
    syncHistoryDepth();
    return;
  }

  if (state.document === historyLastDoc) return;

  if (historyRestoreToken !== null && state.document === historyRestoreToken) {
    historyRestoreToken = null;
    historyLastDoc = state.document;
    return;
  }

  if (historyExpectBaseline) {
    historyExpectBaseline = false;
    historyLastDoc = state.document;
    return;
  }

  /* First document arrival (initial load) sets the baseline silently. */
  if (historyLastDoc === null && undoStack.length === 0) {
    historyLastDoc = state.document;
    return;
  }

  const now = Date.now();
  lastEditAt = now;

  if (now - historyLastPushAt > COALESCE_WINDOW_MS) {
    undoStack.push(historyLastDoc);
    if (undoStack.length > HISTORY_LIMIT) undoStack.shift();
    redoStack.length = 0;
    historyLastPushAt = now;
    syncHistoryDepth();
  }

  historyLastDoc = state.document;
});

function applyHistoryRestore(target: DocumentRef): void {
  historyRestoreToken = target;
  useDocumentStore.setState({ document: target, isDirty: true });
  syncHistoryDepth();
  /* The canvas renders server-produced HTML, so every restore must
   * regenerate it, exactly like the mutating actions do. */
  void useDocumentStore.getState().refreshPreview();
}

export function undo(): boolean {
  if (undoStack.length === 0) return false;
  const target = undoStack.pop() ?? null;
  redoStack.push(historyLastDoc);
  historyRestoreToken = null;
  historyExpectBaseline = false;
  applyHistoryRestore(target);
  return true;
}

export function redo(): boolean {
  if (redoStack.length === 0) return false;
  const target = redoStack.pop() ?? null;
  undoStack.push(historyLastDoc);
  historyRestoreToken = null;
  historyExpectBaseline = false;
  applyHistoryRestore(target);
  return true;
}

export function historyDepths(): { past: number; future: number } {
  return { past: undoStack.length, future: redoStack.length };
}

/* Idle autosave: only when dirty, not already saving, tab visible, and the
 * designer paused typing for a moment. Server-side validation still gates
 * every write (invalid documents keep the last good state via REST 400). */
const AUTOSAVE_IDLE_MS = 15000;
const AUTOSAVE_MIN_SPACING_MS = 20000;

if (typeof window !== 'undefined') {
  window.setInterval(() => {
    const state = useDocumentStore.getState();
    if (state.postId === null || !state.isDirty || state.isSaving || state.isLoading) return;
    if (document.visibilityState !== 'visible') return;
    const now = Date.now();
    if (now - lastEditAt < AUTOSAVE_IDLE_MS) return;
    if (state.lastSavedAt !== null && now - state.lastSavedAt < AUTOSAVE_MIN_SPACING_MS) return;
    lastEditAt = now;
    void state.save();
  }, 5000);
}
