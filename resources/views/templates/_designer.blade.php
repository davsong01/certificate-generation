@php
    $template = $template ?? null;
    $certificateRoute = $certificateRoute ?? config('certificates.routes.name', 'certificates.').'manage.templates';
    $moduleLabels = $moduleLabels ?? $modules ?? config('certificates.modules', []);
    $fontOptions = $fontOptions ?? [];
    $backgroundPreview = $backgroundPreview ?? null;
    $canvasDefaults = config('certificates.designer.canvas', ['width' => 1123, 'height' => 794, 'orientation' => 'landscape']);
    $elementDefaults = config('certificates.designer.element_defaults', []);
    $uploadAccept = collect(config('certificates.uploads.mimes', ['jpg', 'jpeg', 'png', 'webp']))->map(fn ($mime) => '.'.ltrim($mime, '.'))->implode(',');
    $ui = config('certificates.ui', []);
    $defaultSettings = [
        'canvas' => $canvasDefaults,
        'elements' => [],
    ];
    $settingsJson = old('settings');
    if (blank($settingsJson)) {
        $settingsJson = json_encode($template?->settings ?: $defaultSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $selectedModules = old('supported_modules', $template?->supported_modules ?? []);
    if (! is_array($selectedModules)) {
        $selectedModules = [];
    }
    $initialCanvas = data_get($template?->settings, 'canvas', $defaultSettings['canvas']);
    $previewKey = old('preview_key', $previewKey ?? ($template?->id ? 'template-' . $template->id : (string) \Illuminate\Support\Str::uuid()));
    $previewEnabled = config('certificates.routes.preview_enabled', true);
    $previewUrl = $previewEnabled
        ? ($previewUrl ?? ($template
            ? route($certificateRoute.'.preview-template', ['template' => $template->id])
            : route($certificateRoute.'.preview')))
        : '#';
    $previewTemplateUrl = $previewEnabled
        ? ($previewTemplateUrl ?? ($template
            ? route($certificateRoute.'.preview-template', ['template' => $template->id])
            : ''))
        : '';
    $indexUrl = $indexUrl ?? route($certificateRoute.'.index');
    $elementLibrary = config('certificates.element_library', []);
    $elementLibraryEnabled = (bool) ($elementLibrary['enabled'] ?? true);
    $paletteGroups = $elementLibraryEnabled ? ($elementLibrary['groups'] ?? []) : [];
    $textTypeOptions = collect($paletteGroups)
        ->mapWithKeys(function (array $group) {
            return [
                $group['key'] => collect($group['items'] ?? [])
                    ->pluck('label', 'text_type')
                    ->all(),
            ];
        })
        ->all();
    $flatTextTypeOptions = collect($textTypeOptions)->flatMap(fn ($items) => $items)->all();
@endphp

<style>
    .certificate-designer {
        --designer-bg: #0f172a;
        --designer-panel: #ffffff;
        --designer-muted: #6b7280;
        --designer-border: #e5e7eb;
        --designer-accent: #2563eb;
        --designer-accent-soft: rgba(37, 99, 235, .08);
        min-height: calc(100vh - 140px);
    }

    .certificate-designer .designer-toolbar,
    .certificate-designer .designer-top-card,
    .certificate-designer .designer-panel,
    .certificate-designer .designer-stage-card {
        border: 1px solid var(--designer-border);
        border-radius: 18px;
        background: var(--designer-panel);
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
    }

    .certificate-designer .designer-top-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
    }

    .certificate-designer .designer-toolbar .btn {
        border-radius: 12px;
    }

    .certificate-designer .designer-shell {
        margin-bottom: 1rem;
    }

    .certificate-designer .designer-panel {
        position: sticky;
        top: 1rem;
        max-height: calc(100vh - 180px);
        overflow: auto;
        background: transparent;
        border: 0;
        box-shadow: none;
    }

    .certificate-designer .designer-panel .panel-card {
        border: 1px solid var(--designer-border);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        overflow: hidden;
    }

    .certificate-designer .designer-panel .panel-card + .panel-card {
        margin-top: .85rem;
    }

    .certificate-designer .panel-header {
        padding: .8rem .9rem .65rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border-bottom: 1px solid var(--designer-border);
    }

    .certificate-designer .panel-header .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #2563eb;
        font-weight: 700;
    }

    .certificate-designer .panel-header h6 {
        margin: .25rem 0 0;
        font-size: .95rem;
        font-weight: 700;
        color: #0f172a;
    }

    .certificate-designer .panel-header p {
        margin: .25rem 0 0;
        font-size: .8rem;
        color: var(--designer-muted);
        line-height: 1.4;
    }

    .certificate-designer .panel-body {
        padding: .75rem .85rem .9rem;
    }

    .certificate-designer .panel-header-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .3rem;
        margin-top: .45rem;
    }

    .certificate-designer .panel-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .25rem .5rem;
        border-radius: 999px;
        background: #eef4ff;
        border: 1px solid #dbeafe;
        color: #2252b7;
        font-size: .72rem;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    .certificate-designer .designer-panel .btn {
        min-height: 32px;
        padding: .32rem .65rem;
        font-size: .86rem;
        border-radius: 12px;
    }

    .certificate-designer .palette-item {
        border: 1px dashed #d7dde8;
        border-radius: 14px;
        background: #fafcff;
        padding: .48rem .65rem;
        cursor: grab;
        transition: all .16s ease;
        font-size: .88rem;
    }

    .certificate-designer .palette-item:hover {
        border-color: var(--designer-accent);
        background: var(--designer-accent-soft);
        transform: translateY(-1px);
    }

    .certificate-designer .palette-item .palette-chip {
        display: inline-flex;
        align-items: center;
        margin-top: .25rem;
        padding: .16rem .4rem;
        border-radius: 999px;
        background: rgba(37, 99, 235, .08);
        color: #2563eb;
        font-size: .68rem;
        font-weight: 700;
        line-height: 1;
    }

    .certificate-designer .palette-item small {
        color: var(--designer-muted);
        font-size: .76rem;
    }

    .certificate-designer .designer-workspace {
        min-width: 0;
    }

    .certificate-designer .canvas-shell {
        position: relative;
        background: linear-gradient(180deg, #f9fbff 0%, #eef3fb 100%);
        border-radius: 20px;
        padding: 1rem;
        border: 1px solid var(--designer-border);
    }

    .certificate-designer .canvas-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .75rem;
    }

    .certificate-designer .canvas-viewer {
        overflow: auto;
        min-height: 680px;
        padding: .5rem;
        border-radius: 18px;
        background:
            linear-gradient(90deg, rgba(255,255,255,.55), rgba(255,255,255,.35)),
            repeating-linear-gradient(0deg, rgba(2, 6, 23, .03) 0, rgba(2, 6, 23, .03) 1px, transparent 1px, transparent 22px),
            repeating-linear-gradient(90deg, rgba(2, 6, 23, .03) 0, rgba(2, 6, 23, .03) 1px, transparent 1px, transparent 22px);
    }

    .certificate-designer .canvas-stage {
        position: relative;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, .5);
        box-shadow: 0 18px 50px rgba(15, 23, 42, .12);
        overflow: hidden;
        transform-origin: top left;
    }

    .certificate-designer .canvas-background {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        user-select: none;
        pointer-events: none;
    }

    .certificate-designer .canvas-overlay {
        position: absolute;
        inset: 0;
    }

    .certificate-designer .guide-line {
        position: absolute;
        pointer-events: none;
        z-index: 20;
        background: rgba(37, 99, 235, .7);
        box-shadow: 0 0 0 1px rgba(37, 99, 235, .15);
    }

    .certificate-designer .guide-line.vertical {
        top: 0;
        bottom: 0;
        width: 1px;
    }

    .certificate-designer .guide-line.horizontal {
        left: 0;
        right: 0;
        height: 1px;
    }

    .certificate-designer .certificate-element {
        position: absolute;
        box-sizing: border-box;
        border: 1px solid transparent;
        cursor: move;
        user-select: none;
        touch-action: none;
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
    }

    .certificate-designer .certificate-element.is-selected {
        border-color: var(--designer-accent);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
    }

    .certificate-designer .certificate-element.is-locked {
        cursor: not-allowed;
    }

    .certificate-designer .element-body {
        width: 100%;
        height: 100%;
        overflow: hidden;
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
        background: transparent;
    }

    .certificate-designer .element-body.is-qr {
        background: rgba(15, 23, 42, .04);
        border: 1px dashed rgba(15, 23, 42, .2);
        border-radius: 10px;
        align-items: center;
        justify-content: center;
        color: #0f172a;
    }

    .certificate-designer .element-body .element-text {
        display: block;
        width: 100%;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .certificate-designer .element-handle {
        position: absolute;
        right: -5px;
        bottom: -5px;
        width: 11px;
        height: 11px;
        background: #fff;
        border: 1px solid var(--designer-accent);
        border-radius: 50%;
        cursor: nwse-resize;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, .15);
    }

    .certificate-designer .element-tag {
        position: absolute;
        left: 0;
        top: -1.6rem;
        background: rgba(15, 23, 42, .9);
        color: #fff;
        font-size: .70rem;
        padding: .15rem .45rem;
        border-radius: 999px;
        white-space: nowrap;
        pointer-events: none;
    }

    .certificate-designer .ruler-top,
    .certificate-designer .ruler-left {
        position: sticky;
        background:
            linear-gradient(to right, rgba(148, 163, 184, .45) 1px, transparent 1px) 0 100%/50px 100%,
            linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
        z-index: 3;
    }

    .certificate-designer .ruler-top {
        height: 24px;
        border-bottom: 1px solid rgba(148, 163, 184, .4);
        margin-bottom: .5rem;
    }

    .certificate-designer .ruler-left {
        width: 24px;
        border-right: 1px solid rgba(148, 163, 184, .4);
        background:
            linear-gradient(to bottom, rgba(148, 163, 184, .45) 1px, transparent 1px) 100% 0/100% 50px,
            linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
    }

    .certificate-designer .muted-help {
        color: var(--designer-muted);
        font-size: .85rem;
    }

    .certificate-designer .module-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .25rem .55rem;
        border-radius: 999px;
        background: #eef4ff;
        color: #2252b7;
        font-size: .78rem;
    }

    .certificate-designer .context-toolbar-floating {
        position: absolute;
        z-index: 40;
        width: min(680px, calc(100% - 1.25rem));
        pointer-events: none;
        transform: translate3d(0, 0, 0);
        transition: top .15s ease, left .15s ease, opacity .15s ease;
    }

    .certificate-designer .context-toolbar-floating .card {
        pointer-events: auto;
        border: 1px solid rgba(37, 99, 235, .18);
        border-radius: 16px;
        background: rgba(255, 255, 255, .98);
        box-shadow: 0 16px 42px rgba(15, 23, 42, .14);
        backdrop-filter: blur(8px);
    }

    .certificate-designer .context-toolbar-floating .form-label {
        margin-bottom: .2rem;
        font-size: .74rem;
        font-weight: 600;
        color: #475569;
    }

    .certificate-designer .context-toolbar-floating .card-body {
        padding: .75rem .8rem !important;
    }

    .certificate-designer .context-toolbar-floating .btn-sm {
        padding: .27rem .5rem;
        line-height: 1.1;
    }

    .certificate-designer .context-toolbar-floating .form-control-sm,
    .certificate-designer .context-toolbar-floating .form-select-sm {
        min-height: calc(1.45rem + 2px);
        padding-top: .18rem;
        padding-bottom: .18rem;
        font-size: .8rem;
    }

    .certificate-designer .context-toolbar-floating .compact-toggle {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        margin: 0;
        font-size: .76rem;
    }

    .certificate-designer .context-toolbar-floating .compact-toggle .form-check-input {
        margin-top: 0;
    }

    .certificate-designer .context-toolbar-floating .color-control {
        display: flex;
        gap: .4rem;
        align-items: center;
    }

    .certificate-designer .context-toolbar-floating .color-control input[type="color"] {
        width: 2.15rem;
        min-width: 2.15rem;
        height: 1.95rem;
        padding: 0;
        border-radius: .5rem;
        overflow: hidden;
        flex: 0 0 auto;
    }

    .certificate-designer .context-toolbar-floating .color-control .hex-input {
        flex: 1 1 auto;
        min-width: 96px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        text-transform: uppercase;
    }

    .certificate-designer .context-toolbar-floating .inspector-row {
        display: grid;
        gap: .55rem;
    }

    .certificate-designer .context-toolbar-floating .inspector-row + .inspector-row {
        margin-top: .6rem;
        padding-top: .6rem;
        border-top: 1px solid rgba(148, 163, 184, .22);
    }

    .certificate-designer .context-toolbar-floating .inspector-meta {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
    }

    .certificate-designer .context-toolbar-floating .inspector-meta .btn-list {
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .35rem;
    }

    .certificate-designer .context-toolbar-floating .inspector-row-fields {
        display: grid;
        gap: .6rem;
        grid-template-columns: minmax(0, 1.4fr) minmax(88px, .55fr) minmax(88px, .55fr) minmax(0, 1fr) minmax(88px, .55fr);
    }

    .certificate-designer .context-toolbar-floating .inspector-row-controls {
        display: grid;
        gap: .55rem;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }

    .certificate-designer .context-toolbar-floating .toggle-list {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem .75rem;
        align-items: center;
    }

    @media (max-width: 1199.98px) {
        .certificate-designer .context-toolbar-floating .inspector-row-fields,
        .certificate-designer .context-toolbar-floating .inspector-row-controls {
            grid-template-columns: 1fr 1fr;
        }
    }

    .certificate-designer .inline-text-editor {
        position: absolute;
        z-index: 25;
        border: 2px solid var(--designer-accent);
        border-radius: 10px;
        background: rgba(255, 255, 255, .96);
        padding: .45rem .6rem;
        box-shadow: 0 14px 30px rgba(15, 23, 42, .18);
        resize: none;
        outline: none;
        font-family: inherit;
    }

    @media (max-width: 1400px) {
        .certificate-designer .designer-shell {
            grid-template-columns: 1fr;
        }

        .certificate-designer .designer-panel {
            position: static;
            max-height: none;
        }
    }
</style>

<div class="certificate-designer" id="certificateDesigner" data-preview-url="{{ $previewUrl }}" data-preview-template-url="{{ $previewTemplateUrl }}">
    <div class="card designer-top-card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge bg-blue-lt text-blue">{{ $ui['designer_badge'] ?? 'Certificate Designer' }}</span>
                        @if(!empty($selectedModules))
                            @foreach($selectedModules as $module)
                                <span class="module-pill">{{ $moduleLabels[$module] ?? $module }}</span>
                            @endforeach
                        @endif
                    </div>
                    <h3 class="page-title mb-1">{{ $template ? ($ui['edit_title'] ?? 'Edit Certificate Template') : ($ui['create_title'] ?? 'Create Certificate Template') }}</h3>
                    <div class="text-muted">{{ $ui['designer_description'] ?? 'Build reusable certificates with drag-and-drop positioning, server preview, and one JSON settings payload.' }}</div>
                </div>
                <div class="btn-list">
                    <button type="button" class="btn btn-light" data-designer-action="fit">
                        <i class="ti ti-maximize me-2"></i>Fit
                    </button>
                    <button type="button" class="btn btn-light" data-designer-action="zoom-out">
                        <i class="ti ti-zoom-out me-2"></i>-
                    </button>
                    <button type="button" class="btn btn-light" data-designer-action="zoom-in">
                        <i class="ti ti-zoom-in me-2"></i>+
                    </button>
                    <button type="button" class="btn btn-light" data-designer-action="duplicate">
                        <i class="ti ti-copy me-2"></i>Duplicate
                    </button>
                    <button type="button" class="btn btn-light" data-designer-action="bring-forward">
                        <i class="ti ti-arrow-up me-2"></i>Forward
                    </button>
                    <button type="button" class="btn btn-light" data-designer-action="send-backward">
                        <i class="ti ti-arrow-down me-2"></i>Backward
                    </button>
                    @if($previewEnabled)<button type="button" class="btn btn-outline-secondary" data-designer-action="preview">
                        <i class="ti ti-eye me-2"></i>Preview
                    </button>@endif
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-2"></i>{{ $ui['save_label'] ?? 'Save Template' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('certificates::partials.alerts')

    <div class="card designer-toolbar mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label">Template Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $template->name ?? '') }}" required>
                </div>
                <div class="col-lg-5">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', $template->description ?? '') }}" placeholder="Optional note about the template">
                </div>
                <div class="col-lg-2">
                    <label class="form-label d-block">Status</label>
                    <input type="hidden" name="status" value="0">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="status" value="1" id="templateStatus" @checked(old('status', $template->status ?? true))>
                        <label class="form-check-label" for="templateStatus">Active</label>
                    </div>
                </div>
                @if($moduleLabels !== [])
                <div class="col-lg-6">
                    <label class="form-label">Supported Modules</label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($moduleLabels as $key => $label)
                            <label class="form-check form-switch mb-0">
                                <input type="checkbox" class="form-check-input js-module-toggle" name="supported_modules[]" value="{{ $key }}" @checked(in_array($key, $selectedModules, true))>
                                <span class="form-check-label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="muted-help mt-1">Only enabled modules will appear in the element palette.</div>
                </div>
                @endif
                <div class="col-lg-4">
                    <label class="form-label">Background Image @unless($template)<span class="text-danger">*</span>@endunless</label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="file" name="certificate_template_upload" id="certificateBackgroundUpload" class="form-control" accept="{{ $uploadAccept }}" hidden>
                        <button type="button" class="btn btn-outline-secondary" id="certificateBackgroundButton">
                            <i class="ti ti-upload me-2"></i>Choose Background
                        </button>
                        <span style="display:none" class="text-muted small" id="certificateBackgroundLabel">{{ $template?->certificate_template ? basename((string) $template->certificate_template) : 'No file selected' }}</span>
                    </div>
                    @if($template?->certificate_template)
                        <div class="muted-help mt-1">Stored securely; changing it will not move your objects.</div>
                    @endif
                </div>
                <div class="col-lg-2">
                    <label class="form-label">Canvas</label>
                    <div class="form-control-plaintext small text-muted" id="canvasSummary">Loading…</div>
                </div>
            </div>
        </div>
    </div>

    <textarea name="settings" id="certificateSettingsJson" class="d-none" aria-hidden="true">{{ $settingsJson }}</textarea>
    <input type="hidden" name="preview_key" value="{{ $previewKey }}">

    <div class="designer-shell row g-4 align-items-start">
        <aside class="col-md-3">
            <div class="designer-panel h-100">
                @if($elementLibraryEnabled)
                <div class="panel-card">
                    <div class="panel-header">
                        <div class="eyebrow"><i class="ti ti-layout-grid me-1"></i>{{ $elementLibrary['eyebrow'] ?? 'Library' }}</div>
                        <h6>{{ $elementLibrary['title'] ?? 'Elements Library' }}</h6>
                        <p>{{ $elementLibrary['description'] ?? 'Drag fields and static items onto the certificate canvas.' }}</p>
                        <div class="panel-header-actions">
                            <span class="panel-chip"><i class="ti ti-stack-2 me-1"></i><span id="libraryCountChip">0 items</span></span>
                            <span id="libraryGroupChips" class="d-inline-flex flex-wrap gap-1"></span>
                        </div>
                    </div>
                    <div class="panel-body">
                    <div id="paletteContainer" class="d-grid gap-2"></div>
                    <div class="muted-help mt-3">
                        {{ $elementLibrary['help'] ?? 'Drag an item onto the certificate or click it to place it at the current viewport center.' }}
                    </div>
                    </div>
                </div>
                @endif

                <div class="panel-card">
                    <div class="panel-header">
                        <div class="eyebrow"><i class="ti ti-adjustments-horizontal me-1"></i>Tools</div>
                        <h6>Canvas Tools</h6>
                        <p>Fine-tune the editing experience while you position elements.</p>
                        <div class="panel-header-actions">
                            <span class="panel-chip"><i class="ti ti-toggle-right me-1"></i>3 controls</span>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-light justify-content-start" data-designer-action="toggle-grid">
                                <i class="ti ti-grid-dots me-2"></i>Grid: <span id="gridStateLabel">On</span>
                            </button>
                            <button type="button" class="btn btn-light justify-content-start" data-designer-action="toggle-snap">
                                <i class="ti ti-magnet me-2"></i>Snap: <span id="snapStateLabel">On</span>
                            </button>
                            <button type="button" class="btn btn-light justify-content-start" data-designer-action="toggle-guides">
                                <i class="ti ti-ruler-measure-2 me-2"></i>Guides: <span id="guidesStateLabel">On</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <section class="designer-workspace col-md-9">
            <div class="canvas-shell">
                <div class="canvas-toolbar">
                    <div>
                        <div class="fw-semibold">Design Canvas</div>
                        <div class="muted-help">Objects stay within the canvas and render with the same JSON used by the server preview.</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark" id="zoomLabel">100%</span>
                        <span class="badge bg-light text-dark" id="canvasDimensionLabel">{{ $initialCanvas['width'] ?? $canvasDefaults['width'] }} × {{ $initialCanvas['height'] ?? $canvasDefaults['height'] }}</span>
                    </div>
                </div>

                <div class="context-toolbar-floating d-none" id="contextToolbar">
                    <div class="card">
                        <div class="card-body py-2">
                            <div class="inspector-row inspector-meta">
                                <div>
                                    <div class="fw-semibold" id="contextToolbarTitle">Selected Element</div>
                                    <div class="muted-help" id="contextToolbarSubtitle">Double-click text to edit it directly on the canvas.</div>
                                </div>
                                <div class="btn-list">
                                    <button type="button" class="btn btn-light btn-sm" data-designer-action="hide-toolbar" title="Hide toolbar">
                                        <i class="ti ti-x me-1"></i>Hide
                                    </button>
                                    <button type="button" class="btn btn-light btn-sm" data-designer-action="edit-text">
                                        <i class="ti ti-pencil me-1"></i>Edit Text
                                    </button>
                                    <button type="button" class="btn btn-light btn-sm" data-designer-action="duplicate">
                                        <i class="ti ti-copy me-1"></i>Duplicate
                                    </button>
                                    <button type="button" class="btn btn-light btn-sm text-danger" data-designer-action="delete">
                                        <i class="ti ti-trash me-1"></i>Delete
                                    </button>
                                </div>
                            </div>

                            <div class="inspector-row inspector-row-fields">
                                <div>
                                    <label class="form-label">Font</label>
                                    <select class="form-select form-select-sm" id="contextFont">
                                        @foreach($fontOptions as $fontFile => $fontLabel)
                                            <option value="{{ $fontFile }}">{{ $fontLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Size</label>
                                    <input type="number" min="1" class="form-control form-control-sm" id="contextFontSize">
                                </div>
                                <div>
                                    <label class="form-label">Weight</label>
                                    <input type="number" min="100" max="900" step="100" class="form-control form-control-sm" id="contextFontWeight">
                                </div>
                                <div>
                                    <label class="form-label">Color</label>
                                    <div class="color-control">
                                        <input type="text" class="form-control form-control-sm hex-input" id="contextColorHex" placeholder="#ffffff" inputmode="text" autocomplete="off" spellcheck="false">
                                        <input type="color" class="form-control form-control-color" id="contextColor" title="Pick a color" aria-label="Pick a color">
                                    </div>
                                    <div class="muted-help mt-1">Hex or picker.</div>
                                </div>
                                <div>
                                    <label class="form-label">Align</label>
                                    <select class="form-select form-select-sm" id="contextAlign">
                                        <option value="left">Left</option>
                                        <option value="center">Center</option>
                                        <option value="right">Right</option>
                                    </select>
                                </div>
                            </div>

                            <div class="inspector-row inspector-row-controls">
                                <div>
                                    <label class="form-label">Opacity</label>
                                    <input type="range" min="0" max="1" step="0.01" class="form-range" id="contextOpacity">
                                </div>
                                <div class="toggle-list">
                                    <label class="form-check compact-toggle">
                                        <input class="form-check-input" type="checkbox" id="contextBold">
                                        <span class="form-check-label">Bold</span>
                                    </label>
                                    <label class="form-check compact-toggle">
                                        <input class="form-check-input" type="checkbox" id="contextItalic">
                                        <span class="form-check-label">Italic</span>
                                    </label>
                                    <label class="form-check compact-toggle">
                                        <input class="form-check-input" type="checkbox" id="contextUppercase">
                                        <span class="form-check-label">Uppercase</span>
                                    </label>
                                    <label class="form-check compact-toggle">
                                        <input class="form-check-input" type="checkbox" id="contextVisible">
                                        <span class="form-check-label">Visible</span>
                                    </label>
                                    <label class="form-check compact-toggle">
                                        <input class="form-check-input" type="checkbox" id="contextLocked">
                                        <span class="form-check-label">Locked</span>
                                    </label>
                                </div>
                            </div>

                            <div class="row g-2 mt-1 d-none" id="contextQrGroup">
                                <div class="col-lg-2">
                                    <label class="form-label">QR Size</label>
                                    <input type="number" min="60" class="form-control form-control-sm" id="contextQrSize">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="canvas-viewer" id="canvasViewport">
                    <div class="ruler-top"></div>
                    <div class="d-flex align-items-start gap-0">
                        <div class="ruler-left"></div>
                        <div class="position-relative flex-grow-1" id="canvasStageHost">
                            <div class="canvas-stage" id="canvasStage">
                                <img id="canvasBackgroundImage" class="canvas-background" alt="Certificate background" src="{{ $backgroundPreview ?? '' }}" style="{{ $backgroundPreview ? '' : 'display:none;' }}">
                                <div id="canvasOverlay" class="canvas-overlay"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <div class="row mt-3">
        <div class="col-12 d-flex justify-content-between flex-wrap gap-2">
            <a href="{{ $indexUrl }}" class="btn btn-light">
                <i class="ti ti-arrow-left me-2"></i>Back to List
            </a>
            <div class="btn-list">
                @if($previewEnabled)<button type="button" class="btn btn-outline-secondary" data-designer-action="preview">
                    <i class="ti ti-eye me-2"></i>Preview
                </button>@endif
                <button type="submit" class="btn btn-primary">
                    <i class="ti ti-device-floppy me-2"></i>{{ $ui['save_label'] ?? 'Save Template' }}
                </button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="certificatePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Server Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img src="" alt="Certificate preview" id="certificatePreviewImage" class="img-fluid rounded border">
                    </div>
                    <div class="alert alert-danger d-none mt-3" id="certificatePreviewError"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const designer = document.getElementById('certificateDesigner');
    if (!designer) {
        return;
    }

    const form = designer.closest('form');
    const settingsField = document.getElementById('certificateSettingsJson');
    const backgroundInput = document.getElementById('certificateBackgroundUpload');
    const backgroundButton = document.getElementById('certificateBackgroundButton');
    const backgroundLabel = document.getElementById('certificateBackgroundLabel');
    const backgroundImage = document.getElementById('canvasBackgroundImage');
    const canvasShell = document.querySelector('.certificate-designer .canvas-shell');
    const canvasStage = document.getElementById('canvasStage');
    const canvasViewport = document.getElementById('canvasViewport');
    const canvasOverlay = document.getElementById('canvasOverlay');
    const paletteContainer = document.getElementById('paletteContainer');
    const libraryGroupChips = document.getElementById('libraryGroupChips');
    const canvasSummary = document.getElementById('canvasSummary');
    const canvasDimensionLabel = document.getElementById('canvasDimensionLabel');
    const zoomLabel = document.getElementById('zoomLabel');
    const gridStateLabel = document.getElementById('gridStateLabel');
    const snapStateLabel = document.getElementById('snapStateLabel');
    const guidesStateLabel = document.getElementById('guidesStateLabel');
    const libraryCountChip = document.getElementById('libraryCountChip');
    const contextToolbar = document.getElementById('contextToolbar');
    const contextToolbarTitle = document.getElementById('contextToolbarTitle');
    const contextToolbarSubtitle = document.getElementById('contextToolbarSubtitle');
    const contextFont = document.getElementById('contextFont');
    const contextFontSize = document.getElementById('contextFontSize');
    const contextFontWeight = document.getElementById('contextFontWeight');
    const contextColorHex = document.getElementById('contextColorHex');
    const contextColor = document.getElementById('contextColor');
    const contextAlign = document.getElementById('contextAlign');
    const contextOpacity = document.getElementById('contextOpacity');
    const contextBold = document.getElementById('contextBold');
    const contextItalic = document.getElementById('contextItalic');
    const contextUppercase = document.getElementById('contextUppercase');
    const contextVisible = document.getElementById('contextVisible');
    const contextLocked = document.getElementById('contextLocked');
    const contextQrGroup = document.getElementById('contextQrGroup');
    const contextQrSize = document.getElementById('contextQrSize');
    const previewModalElement = document.getElementById('certificatePreviewModal');
    const previewImage = document.getElementById('certificatePreviewImage');
    const previewError = document.getElementById('certificatePreviewError');
    let inlineEditor = null;
    let inlineEditorTarget = null;

    const textTypeLabels = @json($flatTextTypeOptions);
    const fontOptions = @json($fontOptions);
    const previewRoute = designer.dataset.previewUrl;
    const defaultCanvas = @json($canvasDefaults);
    const designerElementDefaults = @json($elementDefaults);
    const designerGridSize = Math.max(1, Number(@json(config('certificates.designer.grid_size', 10))) || 10);

    const sampleValues = @json(config('certificates.sample_data', []));
    const elementLibrary = @json($elementLibrary);
    const paletteGroups = @json($paletteGroups);
    const state = {
        zoom: 1,
        canvas: { ...defaultCanvas },
        elements: [],
        selectedId: null,
        gridEnabled: true,
        snapEnabled: true,
        guidesEnabled: true,
        clipboard: null,
        dragging: null,
        resizing: null,
        backgroundUrl: backgroundImage?.getAttribute('src') || '',
        canvasExplicit: false,
        activeColor: '#000000',
    };

    const initialSettings = (() => {
        try {
            return JSON.parse(settingsField.value || '{}');
        } catch (error) {
            return {};
        }
    })();

    if (initialSettings.canvas && typeof initialSettings.canvas === 'object') {
        state.canvas = {
            width: Math.max(1, parseInt(initialSettings.canvas.width ?? defaultCanvas.width, 10)),
            height: Math.max(1, parseInt(initialSettings.canvas.height ?? defaultCanvas.height, 10)),
            orientation: initialSettings.canvas.orientation ?? defaultCanvas.orientation,
        };
        state.canvasExplicit = true;
    }

    state.elements = Array.isArray(initialSettings.elements)
        ? initialSettings.elements.map((item) => normalizeElement(item))
        : [];

    if (! state.backgroundUrl) {
        backgroundImage.style.display = 'none';
    }

    canvasSummary.textContent = `${state.canvas.width} × ${state.canvas.height}`;
    canvasDimensionLabel.textContent = `${state.canvas.width} × ${state.canvas.height}`;

    function uuid() {
        return (window.crypto && typeof window.crypto.randomUUID === 'function')
            ? window.crypto.randomUUID()
            : `element_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
    }

    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function normalizeElement(element) {
        const font = element.font || element.text_type_face || Object.keys(fontOptions)[0] || 'Times New Roman.ttf';
        const opacity = element.opacity ?? element.auto_certificate_opacity ?? designerElementDefaults.opacity ?? 1;
        return {
            id: element.id || uuid(),
            text_type: element.text_type || 'custom_text',
            label: element.label || textTypeLabels[element.text_type] || 'Custom Text',
            font,
            text_type_face: font,
            color: element.color || element.auto_certificate_color || designerElementDefaults.color || '#000000',
            top: parseInt(element.top ?? element.auto_certificate_top_offset ?? 0, 10),
            left: parseInt(element.left ?? element.auto_certificate_left_offset ?? 0, 10),
            width: parseInt(element.width ?? designerElementDefaults.width ?? 320, 10),
            height: parseInt(element.height ?? designerElementDefaults.height ?? 80, 10),
            size: parseInt(element.size ?? Math.max(parseInt(element.width ?? 0, 10), parseInt(element.height ?? 0, 10), designerElementDefaults.size ?? 120), 10),
            font_size: parseInt(element.font_size ?? element.auto_certificate_name_font_size ?? designerElementDefaults.font_size ?? 36, 10),
            font_weight: parseInt(element.font_weight ?? element.auto_certificate_name_font_weight ?? designerElementDefaults.font_weight ?? 400, 10),
            align: element.align || element.text_align || designerElementDefaults.align || 'left',
            rotation: parseFloat(element.rotation ?? 0),
            opacity: parseFloat(opacity) > 1 ? parseFloat(opacity) / 100 : parseFloat(opacity),
            visible: !!(element.visible ?? true),
            uppercase: !!(element.uppercase ?? false),
            bold: !!(element.bold ?? false),
            italic: !!(element.italic ?? false),
            line_height: parseFloat(element.line_height ?? designerElementDefaults.line_height ?? 1.2),
            letter_spacing: parseFloat(element.letter_spacing ?? designerElementDefaults.letter_spacing ?? 0),
            z_index: parseInt(element.z_index ?? 1, 10),
            locked: !!(element.locked ?? false),
            sample_text: element.sample_text ?? null,
            custom_text: element.custom_text ?? null,
        };
    }

    function setStateFromSettings(settings) {
        state.canvas = {
            width: Math.max(1, parseInt(settings.canvas?.width ?? defaultCanvas.width, 10)),
            height: Math.max(1, parseInt(settings.canvas?.height ?? defaultCanvas.height, 10)),
            orientation: settings.canvas?.orientation ?? defaultCanvas.orientation,
        };
        state.canvasExplicit = true;
        state.elements = Array.isArray(settings.elements) ? settings.elements.map(normalizeElement) : [];
        state.selectedId = state.elements[0]?.id ?? null;
        syncUI();
    }

    function syncUI() {
        renderPalette();
        syncContextToolbar();
        render();
    }

    function syncSettingsField() {
        settingsField.value = JSON.stringify({
            canvas: {
                width: Math.max(1, Math.round(state.canvas.width)),
                height: Math.max(1, Math.round(state.canvas.height)),
                orientation: state.canvas.orientation || 'landscape',
            },
            elements: state.elements.map((item) => ({
                id: item.id,
                text_type: item.text_type,
                label: item.label,
                font: item.font,
                text_type_face: item.text_type_face,
                color: item.color,
                top: Math.round(item.top),
                left: Math.round(item.left),
                width: Math.round(item.width),
                height: Math.round(item.height),
                size: Math.round(item.size),
                font_size: Math.round(item.font_size),
                font_weight: Math.round(item.font_weight),
                align: item.align,
                rotation: Number(item.rotation) || 0,
                opacity: Number(item.opacity) ?? 1,
                visible: !!item.visible,
                uppercase: !!item.uppercase,
                bold: !!item.bold,
                italic: !!item.italic,
                line_height: Number(item.line_height) || 1.2,
                letter_spacing: Number(item.letter_spacing) || 0,
                z_index: Number(item.z_index) || 1,
                locked: !!item.locked,
                sample_text: item.sample_text,
                custom_text: item.custom_text,
            })),
        });
    }

    function selectedElement() {
        return state.elements.find((item) => item.id === state.selectedId) || null;
    }

    function clampElement(element) {
        element.width = Math.max(20, Number(element.width) || 20);
        element.height = Math.max(20, Number(element.height) || 20);
        element.size = Math.max(60, Number(element.size) || 60);
        element.font_size = Math.max(8, Number(element.font_size) || 8);

        const maxLeft = Math.max(0, state.canvas.width - element.width);
        const maxTop = Math.max(0, state.canvas.height - element.height);
        element.left = Math.min(Math.max(0, Number(element.left) || 0), maxLeft);
        element.top = Math.min(Math.max(0, Number(element.top) || 0), maxTop);

        if (element.text_type === 'qr_code') {
            const qrMaxLeft = Math.max(0, state.canvas.width - element.size);
            const qrMaxTop = Math.max(0, state.canvas.height - element.size);
            element.left = Math.min(Math.max(0, Number(element.left) || 0), qrMaxLeft);
            element.top = Math.min(Math.max(0, Number(element.top) || 0), qrMaxTop);
        }

        return element;
    }

    function snapValue(value) {
        if (! state.snapEnabled) {
            return value;
        }

        const grid = designerGridSize;
        return Math.round(value / grid) * grid;
    }

    function activeModules() {
        return Array.from(document.querySelectorAll('.js-module-toggle:checked')).map((input) => input.value);
    }

    function allowedForGroup(group) {
        return ! group.module || activeModules().includes(group.module);
    }

    function renderPalette() {
        if (! paletteContainer) {
            return;
        }

        paletteContainer.innerHTML = '';
        let visibleItemCount = 0;
        const visibleGroups = paletteGroups.filter(allowedForGroup);

        visibleGroups.forEach((group) => {
            const items = Array.isArray(group.items) ? group.items : [];
            const groupCount = items.length;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = `
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <div class="fw-semibold">${group.label}</div>
                    <span class="panel-chip">${groupCount} item${groupCount === 1 ? '' : 's'}</span>
                </div>
                <div class="d-grid gap-2 mb-3"></div>
            `;
            const list = wrapper.querySelector('.d-grid');

            items.forEach((item) => {
                visibleItemCount++;
                const el = document.createElement('div');
                el.className = 'palette-item';
                el.draggable = true;
                el.dataset.textType = item.text_type;
                el.dataset.label = item.label;
                el.dataset.sampleText = item.sample_text || '';
                el.innerHTML = `
                    <div class="fw-semibold">${item.icon ? `<i class="${item.icon} me-1"></i>` : ''}${item.label}</div>
                    <small>${elementLibrary.item_action || 'Drag to canvas'}</small>
                    <div class="palette-chip">${item.text_type.replace(/_/g, ' ')}</div>
                `;
                el.addEventListener('dragstart', (event) => {
                    event.dataTransfer.setData('text/plain', JSON.stringify(item));
                });
                el.addEventListener('click', () => {
                    placeElement(item, state.canvas.width / 2 - 140, state.canvas.height / 2 - 40);
                });
                list.appendChild(el);
            });

            paletteContainer.appendChild(wrapper);
        });

        if (libraryCountChip) {
            libraryCountChip.textContent = `${visibleItemCount} item${visibleItemCount === 1 ? '' : 's'}`;
        }

        if (libraryGroupChips) {
            libraryGroupChips.innerHTML = visibleGroups
                .map((group) => `<span class="panel-chip">${group.label}</span>`)
                .join('');
        }

        if (visibleItemCount === 0) {
            paletteContainer.innerHTML = `<div class="muted-help">${elementLibrary.empty_message || 'No certificate elements are configured.'}</div>`;
        }
    }

    function objectPreviewText(item) {
        if (item.text_type === 'qr_code') {
            return 'QR';
        }

        if (item.text_type === 'custom_text') {
            return item.custom_text || item.sample_text || item.label;
        }

        return item.sample_text || sampleValues[item.text_type] || item.label || textTypeLabels[item.text_type] || 'Text';
    }

    function render() {
        syncSettingsField();

        const width = Math.round(state.canvas.width * state.zoom);
        const height = Math.round(state.canvas.height * state.zoom);

        canvasStage.style.width = `${width}px`;
        canvasStage.style.height = `${height}px`;
        canvasSummary.textContent = `${state.canvas.width} × ${state.canvas.height}`;
        canvasDimensionLabel.textContent = `${state.canvas.width} × ${state.canvas.height}`;
        zoomLabel.textContent = `${Math.round(state.zoom * 100)}%`;
        gridStateLabel.textContent = state.gridEnabled ? 'On' : 'Off';
        snapStateLabel.textContent = state.snapEnabled ? 'On' : 'Off';
        guidesStateLabel.textContent = state.guidesEnabled ? 'On' : 'Off';

        if (backgroundImage.getAttribute('src')) {
            backgroundImage.style.display = 'block';
        }

        const gridStep = Math.max(18, Math.round(50 * state.zoom));
        canvasStage.style.backgroundImage = state.gridEnabled
            ? `repeating-linear-gradient(0deg, rgba(2, 6, 23, .04) 0, rgba(2, 6, 23, .04) 1px, transparent 1px, transparent ${gridStep}px), repeating-linear-gradient(90deg, rgba(2, 6, 23, .04) 0, rgba(2, 6, 23, .04) 1px, transparent 1px, transparent ${gridStep}px)`
            : 'none';

        const sorted = [...state.elements].sort((a, b) => (a.z_index || 0) - (b.z_index || 0));
        canvasOverlay.innerHTML = '';

        sorted.forEach((item) => {
            const element = document.createElement('div');
            element.className = `certificate-element${state.selectedId === item.id ? ' is-selected' : ''}${item.locked ? ' is-locked' : ''}`;
            element.dataset.id = item.id;
            element.style.left = `${item.left * state.zoom}px`;
            element.style.top = `${item.top * state.zoom}px`;
            element.style.width = `${(item.text_type === 'qr_code' ? item.size : item.width) * state.zoom}px`;
            element.style.height = `${(item.text_type === 'qr_code' ? item.size : item.height) * state.zoom}px`;
            element.style.zIndex = item.z_index || 1;
            element.style.opacity = item.visible === false ? '0.25' : String(item.opacity ?? 1);
            element.style.transform = `rotate(${item.rotation || 0}deg)`;
            element.title = item.label || textTypeLabels[item.text_type] || 'Element';

            const body = document.createElement('div');
            body.className = `element-body${item.text_type === 'qr_code' ? ' is-qr' : ''}`;

            if (item.text_type === 'qr_code') {
                body.innerHTML = `<div class="text-center fw-semibold"><i class="ti ti-qrcode fs-1 d-block mb-1"></i>${item.label || 'QR Code'}</div>`;
            } else {
                const text = document.createElement('div');
                text.className = 'element-text';
                text.textContent = objectPreviewText(item);
                text.style.fontFamily = resolveBrowserFont(item.font);
                text.style.fontSize = `${Math.max(8, item.font_size * state.zoom)}px`;
                text.style.fontWeight = item.bold ? 700 : item.font_weight || 400;
                text.style.fontStyle = item.italic ? 'italic' : 'normal';
                text.style.color = item.color || '#000000';
                text.style.textAlign = item.align || 'left';
                text.style.letterSpacing = `${item.letter_spacing || 0}px`;
                text.style.lineHeight = String(item.line_height || 1.2);
                text.style.textTransform = item.uppercase ? 'uppercase' : 'none';
                text.style.padding = '2px 3px';
                body.appendChild(text);
            }

            element.appendChild(body);

            const tag = document.createElement('div');
            tag.className = 'element-tag';
            tag.textContent = item.label || textTypeLabels[item.text_type] || item.text_type;
            element.appendChild(tag);

            if (! item.locked) {
                const handle = document.createElement('div');
                handle.className = 'element-handle';
                handle.addEventListener('pointerdown', (event) => startResize(event, item.id));
                element.appendChild(handle);
            }

            element.addEventListener('pointerdown', (event) => startDrag(event, item.id));
            element.addEventListener('click', (event) => {
                event.stopPropagation();
                selectElement(item.id);
            });
            element.addEventListener('dblclick', (event) => {
                event.preventDefault();
                event.stopPropagation();
                selectElement(item.id);
                inlineEditorTarget = item.id;
                render();
            });

            canvasOverlay.appendChild(element);
        });

        renderGuides(sorted);

        renderInlineEditor();
        syncContextToolbar();
        positionContextToolbar();
    }

    function resolveBrowserFont(fontFile) {
        const key = (fontFile || '').toLowerCase();
        if (key.includes('times')) {
            return '"Times New Roman", serif';
        }
        if (key.includes('arial')) {
            return 'Arial, sans-serif';
        }
        if (key.includes('verdana')) {
            return 'Verdana, sans-serif';
        }
        if (key.includes('georgia')) {
            return 'Georgia, serif';
        }
        return '"Times New Roman", serif';
    }

    function renderGuides(sortedElements) {
        if (! state.guidesEnabled) {
            return;
        }

        const active = selectedElement();
        if (! active) {
            return;
        }

        const width = state.canvas.width;
        const height = state.canvas.height;
        const threshold = 8;
        const activeWidth = active.text_type === 'qr_code' ? active.size : active.width;
        const activeHeight = active.text_type === 'qr_code' ? active.size : active.height;
        const points = [];

        const guideTargetsX = [
            { value: 0, type: 'start' },
            { value: width / 2, type: 'center' },
            { value: width, type: 'end' },
        ];

        const guideTargetsY = [
            { value: 0, type: 'start' },
            { value: height / 2, type: 'center' },
            { value: height, type: 'end' },
        ];

        const activeLinesX = [
            { value: active.left, type: 'start' },
            { value: active.left + activeWidth / 2, type: 'center' },
            { value: active.left + activeWidth, type: 'end' },
        ];

        const activeLinesY = [
            { value: active.top, type: 'start' },
            { value: active.top + activeHeight / 2, type: 'center' },
            { value: active.top + activeHeight, type: 'end' },
        ];

        guideTargetsX.forEach((target) => {
            activeLinesX.forEach((line) => {
                if (Math.abs(target.value - line.value) <= threshold) {
                    points.push({ axis: 'x', value: target.value });
                }
            });
        });

        guideTargetsY.forEach((target) => {
            activeLinesY.forEach((line) => {
                if (Math.abs(target.value - line.value) <= threshold) {
                    points.push({ axis: 'y', value: target.value });
                }
            });
        });

        const used = new Set();
        points.forEach((point) => {
            const key = `${point.axis}:${Math.round(point.value)}`;
            if (used.has(key)) {
                return;
            }

            used.add(key);
            const guide = document.createElement('div');
            guide.className = `guide-line ${point.axis === 'x' ? 'vertical' : 'horizontal'}`;
            if (point.axis === 'x') {
                guide.style.left = `${point.value * state.zoom}px`;
            } else {
                guide.style.top = `${point.value * state.zoom}px`;
            }
            canvasOverlay.appendChild(guide);
        });
    }

    function selectElement(id) {
        if (state.selectedId !== id) {
            stopInlineEditing(false);
        }
        state.selectedId = id;
        render();
    }

    function createElementFromPalette(item, x, y) {
        const configuredDefaults = item.defaults && typeof item.defaults === 'object' ? item.defaults : {};
        const base = {
            id: uuid(),
            text_type: item.text_type,
            label: item.label,
            font: Object.keys(fontOptions)[0] || 'Times New Roman.ttf',
            text_type_face: Object.keys(fontOptions)[0] || 'Times New Roman.ttf',
            color: '#000000',
            top: Math.max(0, Math.round(y ?? 40)),
            left: Math.max(0, Math.round(x ?? 40)),
            width: 320,
            height: 80,
            size: 120,
            font_size: 36,
            font_weight: 400,
            align: 'left',
            rotation: 0,
            opacity: 1,
            visible: true,
            uppercase: false,
            bold: false,
            italic: false,
            line_height: 1.2,
            letter_spacing: 0,
            z_index: state.elements.length + 1,
            locked: false,
            sample_text: item.sample_text || textTypeLabels[item.text_type] || item.label,
            custom_text: item.text_type === 'custom_text' ? (item.sample_text || 'Congratulations!') : null,
            ...designerElementDefaults,
            id: uuid(),
            text_type: item.text_type,
            label: item.label,
            top: Math.max(0, Math.round(y ?? 40)),
            left: Math.max(0, Math.round(x ?? 40)),
            z_index: state.elements.length + 1,
            sample_text: item.sample_text || textTypeLabels[item.text_type] || item.label,
            custom_text: item.text_type === 'custom_text' ? (item.sample_text || item.label) : null,
            ...configuredDefaults,
        };

        if (item.text_type === 'qr_code') {
            base.width = configuredDefaults.width ?? 120;
            base.height = configuredDefaults.height ?? 120;
            base.size = configuredDefaults.size ?? 120;
            base.label = configuredDefaults.label ?? item.label;
            base.sample_text = configuredDefaults.sample_text ?? item.sample_text ?? item.label;
        }

        if (item.text_type === 'custom_text') {
            base.sample_text = configuredDefaults.sample_text ?? item.sample_text ?? item.label;
            base.custom_text = configuredDefaults.custom_text ?? base.sample_text;
        }

        if (item.text_type !== 'qr_code') {
            base.label = item.label;
        }

        return clampElement(base);
    }

    function placeElement(item, x, y) {
        state.elements.push(createElementFromPalette(item, x, y));
        state.selectedId = state.elements[state.elements.length - 1].id;
        render();
    }

    function canvasPoint(event) {
        const rect = canvasStage.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) / state.zoom,
            y: (event.clientY - rect.top) / state.zoom,
        };
    }

    function startDrag(event, id) {
        const target = state.elements.find((item) => item.id === id);
        if (! target || target.locked || event.button !== 0) {
            return;
        }

        selectElement(id);
        event.preventDefault();
        event.stopPropagation();

        const start = canvasPoint(event);
        state.dragging = {
            id,
            startX: start.x,
            startY: start.y,
            originalLeft: target.left,
            originalTop: target.top,
        };

        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', stopInteraction);
    }

    function startResize(event, id) {
        const target = state.elements.find((item) => item.id === id);
        if (! target || target.locked || event.button !== 0) {
            return;
        }

        selectElement(id);
        event.preventDefault();
        event.stopPropagation();

        const start = canvasPoint(event);
        state.resizing = {
            id,
            startX: start.x,
            startY: start.y,
            originalWidth: target.text_type === 'qr_code' ? target.size : target.width,
            originalHeight: target.text_type === 'qr_code' ? target.size : target.height,
            originalLeft: target.left,
            originalTop: target.top,
        };

        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', stopInteraction);
    }

    function onPointerMove(event) {
        if (state.dragging) {
            const element = state.elements.find((item) => item.id === state.dragging.id);
            if (! element) {
                return;
            }

            const point = canvasPoint(event);
            const deltaX = point.x - state.dragging.startX;
            const deltaY = point.y - state.dragging.startY;
            const nextLeft = state.dragging.originalLeft + deltaX;
            const nextTop = state.dragging.originalTop + deltaY;
            element.left = state.snapEnabled ? snapValue(nextLeft) : nextLeft;
            element.top = state.snapEnabled ? snapValue(nextTop) : nextTop;
            clampElement(element);
            render();
        }

        if (state.resizing) {
            const element = state.elements.find((item) => item.id === state.resizing.id);
            if (! element) {
                return;
            }

            const point = canvasPoint(event);
            const deltaX = point.x - state.resizing.startX;
            const deltaY = point.y - state.resizing.startY;
            const nextWidth = Math.max(20, state.resizing.originalWidth + deltaX);
            const nextHeight = Math.max(20, state.resizing.originalHeight + deltaY);

            if (element.text_type === 'qr_code') {
                element.size = state.snapEnabled ? snapValue(Math.max(60, nextWidth)) : Math.max(60, nextWidth);
                element.width = element.size;
                element.height = element.size;
            } else {
                element.width = state.snapEnabled ? snapValue(nextWidth) : nextWidth;
                element.height = state.snapEnabled ? snapValue(nextHeight) : nextHeight;
            }

            clampElement(element);
            render();
        }
    }

    function stopInteraction() {
        state.dragging = null;
        state.resizing = null;
        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerup', stopInteraction);
    }

    function duplicateSelected() {
        const current = selectedElement();
        if (! current) {
            return;
        }

        const copy = normalizeElement({
            ...clone(current),
            id: uuid(),
            top: current.top + 20,
            left: current.left + 20,
            z_index: (current.z_index || 1) + 1,
        });

        state.elements.push(copy);
        state.selectedId = copy.id;
        render();
    }

    function deleteSelected() {
        if (! state.selectedId) {
            return;
        }

        state.elements = state.elements.filter((item) => item.id !== state.selectedId);
        state.selectedId = state.elements.at(-1)?.id ?? null;
        render();
    }

    function shiftZ(delta) {
        const current = selectedElement();
        if (! current) {
            return;
        }

        current.z_index = Math.max(1, (current.z_index || 1) + delta);
        render();
    }

    function setZoom(level) {
        state.zoom = Math.max(0.25, Math.min(2.5, Number(level) || 1));
        render();
    }

    function fitToScreen() {
        const bounds = canvasViewport.getBoundingClientRect();
        const availableWidth = Math.max(320, bounds.width - 60);
        const availableHeight = Math.max(320, bounds.height - 100);
        const zoom = Math.min(availableWidth / state.canvas.width, availableHeight / state.canvas.height, 1.05);
        setZoom(zoom);
    }

    function stopInlineEditing(commit = true) {
        if (! inlineEditor) {
            inlineEditorTarget = null;
            return;
        }

        const editor = inlineEditor;
        const targetId = inlineEditorTarget;
        const nextValue = editor.value;

        editor.remove();
        inlineEditor = null;
        inlineEditorTarget = null;

        if (! commit || ! targetId) {
            return;
        }

        const target = state.elements.find((item) => item.id === targetId);
        if (! target) {
            return;
        }

        if (target.text_type === 'custom_text') {
            target.custom_text = nextValue;
        } else if (nextValue.trim()) {
            target.sample_text = nextValue;
        }

        syncSettingsField();
        render();
    }

    function updateElementPreviewText(id, text) {
        const preview = canvasOverlay.querySelector(`.certificate-element[data-id="${CSS.escape(id)}"] .element-text`);
        if (preview) {
            preview.textContent = text;
        }
    }

    function renderInlineEditor() {
        if (! inlineEditorTarget) {
            return;
        }

        const target = state.elements.find((item) => item.id === inlineEditorTarget);
        if (! target) {
            return;
        }

        const existing = inlineEditor;
        if (existing && existing.isConnected) {
            return;
        }

        const node = canvasOverlay.querySelector(`.certificate-element[data-id="${CSS.escape(target.id)}"]`);
        if (! node) {
            return;
        }

        const textNode = node.querySelector('.element-text');
        const rect = node.getBoundingClientRect();
        const canvasRect = canvasStage.getBoundingClientRect();
        const editor = document.createElement('textarea');
        editor.className = 'inline-text-editor';
        editor.value = target.text_type === 'custom_text'
            ? (target.custom_text || target.sample_text || '')
            : (target.sample_text || textNode?.textContent || '');
        editor.style.left = `${(rect.left - canvasRect.left)}px`;
        editor.style.top = `${(rect.top - canvasRect.top)}px`;
        editor.style.width = `${Math.max(80, rect.width)}px`;
        editor.style.height = `${Math.max(36, rect.height)}px`;
        editor.style.fontFamily = resolveBrowserFont(target.font);
        editor.style.fontSize = `${Math.max(12, (target.font_size || 24) * state.zoom)}px`;
        editor.style.fontWeight = target.bold ? '700' : String(target.font_weight || 400);
        editor.style.fontStyle = target.italic ? 'italic' : 'normal';
        editor.style.color = target.color || '#000000';
        editor.style.textAlign = target.align || 'left';
        editor.style.letterSpacing = `${target.letter_spacing || 0}px`;
        editor.style.lineHeight = String(target.line_height || 1.2);
        editor.style.textTransform = target.uppercase ? 'uppercase' : 'none';

        editor.addEventListener('input', () => updateElementPreviewText(target.id, editor.value));
        editor.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                stopInlineEditing(false);
            }
            if (event.key === 'Enter' && ! event.shiftKey) {
                event.preventDefault();
                stopInlineEditing(true);
            }
        });
        editor.addEventListener('blur', () => stopInlineEditing(true));

        canvasOverlay.appendChild(editor);
        inlineEditor = editor;
        editor.focus();
        editor.select();
    }

    function normalizeHexColor(value) {
        let hex = String(value || '').trim();
        if (! hex) {
            return '#000000';
        }

        if (! hex.startsWith('#')) {
            hex = `#${hex}`;
        }

        if (/^#([0-9a-fA-F]{3})$/.test(hex)) {
            const [, short] = hex.match(/^#([0-9a-fA-F]{3})$/);
            return `#${short.split('').map((char) => char + char).join('')}`.toLowerCase();
        }

        if (/^#([0-9a-fA-F]{6})$/.test(hex)) {
            return hex.toLowerCase();
        }

        return '#000000';
    }

    function isValidHexColor(value) {
        return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(String(value || '').trim());
    }

    function commitHexColorInput(input) {
        if (! input) {
            return;
        }

        const raw = String(input.value || '').trim();
        if (! isValidHexColor(raw)) {
            input.value = selectedElement()?.color || '#000000';
            return;
        }

        const normalized = normalizeHexColor(raw);
        input.value = normalized;
        if (contextColor && contextColor.value !== normalized) {
            contextColor.value = normalized;
        }
        updateSelectedProperty('color', normalized);
    }

    function updateSelectedProperty(property, value) {
        const current = selectedElement();
        if (! current) {
            return;
        }

        if (inlineEditor) {
            stopInlineEditing(true);
        }

        switch (property) {
            case 'font':
                current.font = value;
                current.text_type_face = value;
                break;
            case 'font_size':
                current.font_size = Math.max(1, parseInt(value || '1', 10));
                break;
            case 'font_weight':
                current.font_weight = Math.max(100, Math.min(900, parseInt(value || '400', 10)));
                break;
            case 'color':
                current.color = normalizeHexColor(value);
                break;
            case 'align':
                current.align = value || 'left';
                break;
            case 'opacity':
                current.opacity = Math.max(0, Math.min(1, parseFloat(value || '1')));
                break;
            case 'bold':
            case 'italic':
            case 'uppercase':
            case 'visible':
            case 'locked':
                current[property] = value === true || value === '1' || value === 1 || value === 'on';
                break;
            case 'size':
                current.size = Math.max(60, parseInt(value || '60', 10));
                current.width = current.size;
                current.height = current.size;
                break;
            default:
                current[property] = value;
        }

        clampElement(current);
        syncSettingsField();
        syncContextToolbar();
        render();
    }

    function syncContextToolbar() {
        const current = selectedElement();
        if (! contextToolbar) {
            return;
        }

        if (! current) {
            contextToolbar.classList.add('d-none');
            contextToolbar.style.left = '';
            contextToolbar.style.top = '';
            return;
        }

        contextToolbar.classList.remove('d-none');
        contextToolbarTitle.textContent = current.label || textTypeLabels[current.text_type] || 'Selected Element';
        contextToolbarSubtitle.textContent = current.text_type === 'custom_text'
            ? 'Double-click to edit the text directly on the canvas.'
            : 'Use the controls below to refine typography and layout.';

        const isQr = current.text_type === 'qr_code';
        contextFont.value = current.font || Object.keys(fontOptions)[0] || '';
        contextFontSize.value = current.font_size || 24;
        contextFontWeight.value = current.font_weight || 400;
        contextColor.value = normalizeHexColor(current.color || '#000000');
        if (contextColorHex) {
            contextColorHex.value = normalizeHexColor(current.color || '#000000');
        }
        contextAlign.value = current.align || 'left';
        contextOpacity.value = current.opacity ?? 1;
        contextBold.checked = !!current.bold;
        contextItalic.checked = !!current.italic;
        contextUppercase.checked = !!current.uppercase;
        contextVisible.checked = current.visible !== false;
        contextLocked.checked = !!current.locked;
        contextFont.disabled = isQr;
        contextFontSize.disabled = isQr;
        contextFontWeight.disabled = isQr;
        contextColor.disabled = isQr;
        if (contextColorHex) {
            contextColorHex.disabled = isQr;
        }
        contextAlign.disabled = isQr;
        contextBold.disabled = isQr;
        contextItalic.disabled = isQr;
        contextUppercase.disabled = isQr;
        contextQrGroup.classList.toggle('d-none', ! isQr);
        contextQrSize.value = current.size || 120;
    }

    function hideContextToolbar() {
        stopInlineEditing(false);
        state.selectedId = null;
        if (contextToolbar) {
            contextToolbar.classList.add('d-none');
            contextToolbar.style.left = '';
            contextToolbar.style.top = '';
        }
        render();
    }

    function positionContextToolbar() {
        if (! contextToolbar || contextToolbar.classList.contains('d-none') || ! canvasShell) {
            return;
        }

        const current = selectedElement();
        if (! current) {
            return;
        }

        const selectedNode = canvasOverlay.querySelector(`.certificate-element[data-id="${CSS.escape(current.id)}"]`);
        if (! selectedNode) {
            return;
        }

        const shellRect = canvasShell.getBoundingClientRect();
        const nodeRect = selectedNode.getBoundingClientRect();
        const toolbarRect = contextToolbar.getBoundingClientRect();
        const gap = 12;

        let left = (nodeRect.left - shellRect.left) + (nodeRect.width / 2) - (toolbarRect.width / 2);
        let top = (nodeRect.top - shellRect.top) - toolbarRect.height - gap;

        if (top < gap) {
            top = (nodeRect.bottom - shellRect.top) + gap;
        }

        const maxLeft = Math.max(gap, shellRect.width - toolbarRect.width - gap);
        const maxTop = Math.max(gap, shellRect.height - toolbarRect.height - gap);

        left = Math.min(Math.max(gap, left), maxLeft);
        top = Math.min(Math.max(gap, top), maxTop);

        contextToolbar.style.left = `${left}px`;
        contextToolbar.style.top = `${top}px`;
    }

    [contextFont, contextFontSize, contextFontWeight, contextColor, contextAlign, contextOpacity, contextQrSize].forEach((field) => {
        field?.addEventListener('input', () => {
            if (field === contextColor) {
                const normalized = normalizeHexColor(field.value);
                if (contextColorHex) {
                    contextColorHex.value = normalized;
                }
                field.value = normalized;
                updateSelectedProperty('color', normalized);
                return;
            }

            const property = field.id.replace('context', '');
            const map = {
                Font: 'font',
                FontSize: 'font_size',
                FontWeight: 'font_weight',
                Color: 'color',
                Align: 'align',
                Opacity: 'opacity',
                QrSize: 'size',
            };
            updateSelectedProperty(map[property] || property.toLowerCase(), field.value);
        });
    });

    contextColorHex?.addEventListener('change', () => commitHexColorInput(contextColorHex));
    contextColorHex?.addEventListener('blur', () => commitHexColorInput(contextColorHex));
    contextColorHex?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            commitHexColorInput(contextColorHex);
        }
    });

    [
        [contextBold, 'bold'],
        [contextItalic, 'italic'],
        [contextUppercase, 'uppercase'],
        [contextVisible, 'visible'],
        [contextLocked, 'locked'],
    ].forEach(([field, property]) => {
        field?.addEventListener('change', () => updateSelectedProperty(property, field.checked));
    });

    function handleCanvasDrop(event) {
        event.preventDefault();
        const data = event.dataTransfer.getData('text/plain');
        if (! data) {
            return;
        }

        try {
            const item = JSON.parse(data);
            const point = canvasPoint(event);
            placeElement(item, point.x - 80, point.y - 30);
        } catch (error) {
            // no-op
        }
    }

    function readCanvasFile(file) {
        if (! file) {
            return;
        }

        backgroundLabel.textContent = file.name;

        const url = URL.createObjectURL(file);
        backgroundImage.onload = () => {
            if (! state.canvasExplicit || state.elements.length === 0) {
                state.canvas.width = backgroundImage.naturalWidth || defaultCanvas.width;
                state.canvas.height = backgroundImage.naturalHeight || defaultCanvas.height;
                state.canvasExplicit = true;
                fitToScreen();
            }
            render();
        };
        backgroundImage.src = url;
        backgroundImage.style.display = 'block';
        state.backgroundUrl = url;
    }

    function setBackgroundPreview(url, preserveCanvas = false) {
        if (! url) {
            return;
        }

        backgroundImage.onload = () => {
            if (! preserveCanvas && (! state.canvasExplicit || state.elements.length === 0)) {
                state.canvas.width = backgroundImage.naturalWidth || defaultCanvas.width;
                state.canvas.height = backgroundImage.naturalHeight || defaultCanvas.height;
                state.canvasExplicit = true;
                fitToScreen();
            }
            render();
        };
        backgroundImage.src = url;
        backgroundImage.style.display = 'block';
    }

    function previewTemplate() {
        syncSettingsField();
        const formData = new FormData(form);
        formData.set('settings', settingsField.value);

        const modal = new bootstrap.Modal(previewModalElement);
        previewError.classList.add('d-none');
        previewError.textContent = '';
        previewImage.removeAttribute('src');

        const button = designer.querySelector('[data-designer-action="preview"]');
        const originalHtml = button?.innerHTML;
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Rendering…';
        }

        fetch(previewRoute, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'image/*,application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: formData,
        })
            .then(async (response) => {
                const contentType = response.headers.get('content-type') || '';
                if (! response.ok) {
                    if (contentType.includes('application/json')) {
                        const payload = await response.json();
                        throw new Error(Object.values(payload.errors || { error: [payload.message || 'Unable to generate preview.'] }).flat().join(' '));
                    }

                    const text = await response.text();
                    throw new Error(text || 'Unable to generate preview.');
                }

                if (contentType.includes('application/json')) {
                    const payload = await response.json();
                    const previewSource = payload.preview_data_url || payload.preview_url;
                    if (! previewSource) {
                        throw new Error(payload.message || 'Unable to generate preview.');
                    }

                    previewImage.src = previewSource;
                    modal.show();
                    return null;
                }

                if (! contentType.startsWith('image/')) {
                    const text = await response.text();
                    throw new Error(text ? 'Preview returned an unexpected response. Please try again.' : 'Preview did not return an image.');
                }

                return response.blob();
            })
            .then((blob) => {
                if (! blob) {
                    return;
                }
                previewImage.src = URL.createObjectURL(blob);
                modal.show();
            })
            .catch((error) => {
                previewError.textContent = error.message || 'Unable to generate preview right now.';
                previewError.classList.remove('d-none');
                modal.show();
            })
            .finally(() => {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            });
    }

    backgroundButton?.addEventListener('click', () => backgroundInput?.click());
    backgroundInput?.addEventListener('change', () => readCanvasFile(backgroundInput.files?.[0]));

    document.querySelectorAll('.js-module-toggle').forEach((input) => {
        input.addEventListener('change', () => {
            renderPalette();
        });
    });

    canvasViewport.addEventListener('dragover', (event) => event.preventDefault());
    canvasViewport.addEventListener('drop', handleCanvasDrop);
    canvasViewport.addEventListener('scroll', () => positionContextToolbar(), { passive: true });
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (inlineEditor) {
                event.preventDefault();
                stopInlineEditing(false);
                render();
                return;
            }

            if (state.selectedId || ! contextToolbar.classList.contains('d-none')) {
                event.preventDefault();
                hideContextToolbar();
                return;
            }
        }

        if (! form.contains(document.activeElement) && ! state.selectedId) {
            return;
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') {
            event.preventDefault();
            duplicateSelected();
            return;
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'c') {
            const current = selectedElement();
            if (current) {
                state.clipboard = clone(current);
            }
            return;
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'v') {
            if (state.clipboard) {
                const copy = normalizeElement({
                    ...clone(state.clipboard),
                    id: uuid(),
                    top: state.clipboard.top + 20,
                    left: state.clipboard.left + 20,
                    z_index: (state.clipboard.z_index || 1) + 1,
                });
                state.elements.push(copy);
                state.selectedId = copy.id;
                render();
            }
            return;
        }

        if (event.key === 'Delete' || event.key === 'Backspace') {
            if (document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                return;
            }
            deleteSelected();
            return;
        }

        const selected = selectedElement();
        if (! selected) {
            return;
        }

        const step = event.shiftKey ? 10 : 1;
        let handled = false;

        switch (event.key) {
            case 'ArrowLeft':
                selected.left -= step;
                handled = true;
                break;
            case 'ArrowRight':
                selected.left += step;
                handled = true;
                break;
            case 'ArrowUp':
                selected.top -= step;
                handled = true;
                break;
            case 'ArrowDown':
                selected.top += step;
                handled = true;
                break;
            default:
                break;
        }

        if (handled) {
            event.preventDefault();
            clampElement(selected);
            render();
        }
    });

    designer.querySelectorAll('[data-designer-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.designerAction;
        switch (action) {
                case 'hide-toolbar':
                    hideContextToolbar();
                    break;
                case 'fit':
                    fitToScreen();
                    break;
                case 'zoom-in':
                    setZoom(state.zoom + 0.1);
                    break;
                case 'zoom-out':
                    setZoom(state.zoom - 0.1);
                    break;
                case 'duplicate':
                    duplicateSelected();
                    break;
                case 'delete':
                    deleteSelected();
                    break;
                case 'edit-text':
                    {
                        const current = selectedElement();
                        if (current && current.text_type !== 'qr_code') {
                            inlineEditorTarget = current.id;
                            render();
                        }
                    }
                    break;
                case 'bring-forward':
                    shiftZ(1);
                    break;
                case 'send-backward':
                    shiftZ(-1);
                    break;
                case 'toggle-grid':
                    state.gridEnabled = ! state.gridEnabled;
                    render();
                    break;
                case 'toggle-snap':
                    state.snapEnabled = ! state.snapEnabled;
                    render();
                    break;
                case 'toggle-guides':
                    state.guidesEnabled = ! state.guidesEnabled;
                    render();
                    break;
                case 'preview':
                    previewTemplate();
                    break;
                default:
                    break;
            }
        });
    });

    window.addEventListener('resize', () => {
        fitToScreen();
        positionContextToolbar();
    });

    const submitButton = form.querySelector('button[type="submit"]');
    form.addEventListener('submit', () => {
        syncSettingsField();
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.originalLabel = submitButton.innerHTML;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving…';
        }
    });

    canvasViewport.addEventListener('pointerdown', (event) => {
        if (event.target === canvasViewport || event.target === canvasStage || event.target === canvasOverlay || event.target === canvasStageHost || event.target === backgroundImage) {
            state.selectedId = null;
            stopInlineEditing();
            render();
        }
    });

    if (state.backgroundUrl) {
        setBackgroundPreview(state.backgroundUrl, true);
    }

    renderPalette();
    syncSettingsField();
    render();
    fitToScreen();
})();
</script>
@endpush
